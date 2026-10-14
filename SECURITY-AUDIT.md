# NURA Beauty - Security Audit

Audit date: 14 October 2026. Scope: parent theme `nura-beauty` 1.53.0, plugin `nura-experience` 1.55.0 and child theme `nura-beauty-child` (about 20,000 lines of PHP and JavaScript). Reviewed as a production WordPress + WooCommerce store.

## Summary

The codebase already followed most WordPress secure-coding practices: prepared SQL statements, output escaping, nonces and capability checks on admin actions, honeypots and per-IP limits on public forms, and signed WhatsApp webhooks. The main gap was the **login and account layer** (no brute-force protection, no registration/reset limits, long staff sessions, open REST CORS). This release closes those gaps without removing any feature.

| Area | Status before | Status now |
| --- | --- | --- |
| SQL injection | Safe - all dynamic queries use `$wpdb->prepare()`; table names come from `$wpdb->prefix` only | Unchanged, verified |
| XSS (PHP output) | Safe - `esc_html` / `esc_attr` / `esc_url` / `wp_kses` used on output | Unchanged, verified |
| XSS (JavaScript) | Safe - every `innerHTML` that renders server/user data passes through an `esc()` helper | Unchanged, verified |
| CSRF | Admin actions use `check_admin_referer` + capability checks. Public cached forms use honeypot + time-trap + rate limit instead of nonces (deliberate, so cached pages still work) | Unchanged, documented |
| Broken authentication / brute force | **No login attempt limit** | **Fixed** - IP + username lockout |
| Bot sign-ups and reset spam | **No limits** | **Fixed** - honeypot, fill-time trap, 3 registrations / 5 resets per IP per hour |
| Password security | Hashing by WordPress core (bcrypt from WP 6.8). No minimum length | **Improved** - minimum 10 characters, cannot equal username/email |
| Session expiry | WordPress defaults for everyone (14 days with "Remember me") | **Improved** - staff 8 hours (3 days with "Remember me"); customers unchanged |
| HTTPS everywhere | HSTS sent on front end only; no HTTP to HTTPS redirect in code | **Fixed** - 301 to HTTPS, HSTS + headers on login and admin too |
| Security headers | nosniff, SAMEORIGIN, Referrer-Policy, Permissions-Policy, COOP, HSTS on front end | Added to wp-login.php and wp-admin |
| CORS | WordPress REST reflects **any** Origin | **Fixed** - only the site origin (extendable by filter) |
| Unauthorised access | Admin screens check `manage_options` / `manage_woocommerce`; customer certificates read from the logged-in user's own meta only | Unchanged, verified |
| API vulnerabilities | Public `nurax/v1` endpoints are rate-limited per IP (global 30 POST / 10 min plus per-route limits); WhatsApp webhook requires a valid `X-Hub-Signature-256` | Unchanged, verified |
| Application passwords | Available to every user | **Restricted** to administrators |
| Secrets / hardcoded keys | No keys in code. AI keys read from environment variables or constants first, then from the settings screen (password fields) | Unchanged, documented |
| Insecure file uploads | No PHP file-upload handlers in the code (no `$_FILES`, `move_uploaded_file`); Virtual Try-On runs in the browser | Nothing to fix |
| Dangerous functions | No `eval`, `unserialize`, `exec`, `shell_exec`, `base64_decode` | Nothing to fix |
| Data exposure | REST users endpoint closed to guests, `?author=` scans blocked, generic login errors, XML-RPC off | Unchanged |
| Input sanitisation | Mostly correct; two `$_SERVER` reads in the theme were not unslashed/sanitised | **Fixed** |

## Findings and fixes

### 1. No brute-force protection on login (High) - fixed
`wp-login.php` and the WooCommerce My Account login accepted unlimited password guesses.
**Fix:** `nura-experience/includes/class-nura-login-guard.php`. 5 failed logins per IP in 15 minutes or 10 per username in an hour lock that IP / username for 30 minutes. Successful login clears the counters. Limits are filterable (`nurax_login_ip_max`, `nurax_login_user_max`).

### 2. Bots could register accounts and trigger reset emails without limit (Medium) - fixed
**Fix:** a hidden honeypot field and a minimum 2-second fill time on the login, registration and lost-password forms (WordPress and WooCommerce), plus 3 registrations and 5 reset requests per IP per hour (`nurax_registrations_per_hour`, `nurax_password_resets_per_hour`). Forms that do not carry the new fields (mobile apps, API clients) are not affected by the trap.

### 3. Weak passwords allowed (Medium) - fixed
**Fix:** new, reset and changed passwords (wp-admin profile, reset screen, WooCommerce account details) must be at least 10 characters and must not equal the username or email. Passwords are never logged or stored by our code; hashing stays with WordPress core.

### 4. Long-lived staff sessions (Medium) - fixed
A stolen admin cookie stayed valid for up to 14 days.
**Fix:** users who can edit content or manage the shop get 8-hour sessions (3 days with "Remember me"). Customers keep the WordPress defaults so shopping is not disrupted. Filters: `nurax_staff_session`, `nurax_staff_session_remember`.

### 5. HTTPS not enforced in code (Medium) - fixed
**Fix:** plain-HTTP front-end requests are 301-redirected to HTTPS when the site URL is HTTPS (no loop risk on HTTP-only staging). HSTS and the core security headers are now also sent on wp-login.php and wp-admin. Disable with `add_filter( 'nurax_force_https', '__return_false' );`.

### 6. Permissive REST CORS (Low/Medium) - fixed
WordPress core echoes back any `Origin`, so other websites could make credentialed REST calls from a logged-in visitor's browser.
**Fix:** only the store's own origin is allowed. Add partner origins with the `nurax_cors_allowed_origins` filter.

### 7. Application passwords for all users (Low) - fixed
**Fix:** only administrators can create application passwords (used by integrations such as n8n).

### 8. Unsanitised server variables (Low) - fixed
`nura-beauty/inc/nura-security.php` read `HTTP_USER_AGENT` and `REMOTE_ADDR` without `wp_unslash` / sanitising. Both are now sanitised.

### 9. Client IP behind a proxy (Informational)
Rate limits key on `REMOTE_ADDR`. If the site is put behind Cloudflare, define in `wp-config.php`:
```php
define( 'NURAX_TRUSTED_PROXY_HEADER', 'HTTP_CF_CONNECTING_IP' );
```
Only do this when the proxy is really in front of the site, otherwise the header can be spoofed.

### 10. Public order form without a nonce (Accepted risk)
The WhatsApp order form (`nurax_wa_order`) is served on cached pages, so a nonce would expire and block real customers. It is protected by a honeypot, a 2.5-second fill-time trap, link filtering, length caps, a strict reference format and 12 submissions per IP per hour. It only creates a lead record; it cannot change orders, prices or accounts.

## Requirement checklist

| Requirement | How it is met |
| --- | --- |
| Rate limiting | Login lockout, registration, password reset, contact, newsletter, WhatsApp order, AI stylist, wig finder, booking and the global `nurax/v1` limiter |
| Input sanitisation | `sanitize_*`, `absint`, `wp_unslash`, allow-lists and length caps on every input |
| Password hashing | WordPress core (bcrypt in WP 6.8+, phpass before); plus the new password policy |
| Environment variables / no hardcoded keys | AI provider keys: env var, then PHP constant, then settings; WhatsApp verify token and app secret support env/constant overrides; no secrets in the repository |
| CORS configuration | Same-origin only for the REST API, extendable by filter |
| SQL / NoSQL injection | `$wpdb->prepare()` everywhere user data reaches SQL; no NoSQL in use |
| Session expiry | Staff 8 h / 3 days, customers WordPress default |
| HTTPS everywhere | 301 redirect + HSTS (1 year, subdomains) |
| Security headers | nosniff, X-Frame-Options, Referrer-Policy, Permissions-Policy, COOP, HSTS |
| Regular dependency updates | See "Maintenance" below |

## Maintenance (do this regularly)

1. Keep WordPress core, WooCommerce, Rank Math and every plugin on the latest version (Dashboard > Updates, weekly).
2. Delete plugins and themes you do not use.
3. Turn on two-factor authentication for every admin account (for example the "Two Factor" or Wordfence plugin).
4. Use a unique, long password for every admin and shop-manager account.
5. Keep daily off-site backups and test a restore once a quarter.
6. Put the site behind a WAF (Cloudflare or the host's firewall) and mirror the security headers there so cached pages carry them.
7. Add to `wp-config.php` if not present: `define( 'FORCE_SSL_ADMIN', true );` and `define( 'DISALLOW_FILE_EDIT', true );` (the theme already sets the second one).
8. Review WooCommerce > Status > Logs and the WhatsApp leads screen monthly for unusual activity.

## Not covered by code (host level)

A Content-Security-Policy is not enabled yet: the store loads Google Fonts, analytics, Meta/TikTok pixels and payment scripts, and a wrong CSP would break checkout. Recommended next step: run a `Content-Security-Policy-Report-Only` header for two weeks, then enforce it.
