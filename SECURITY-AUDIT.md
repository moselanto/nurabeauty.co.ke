# NURA Beauty - Security Audit

| | |
| --- | --- |
| Latest audit | 17 October 2026 |
| Scope | Parent theme `nura-beauty` 1.64.0, child theme `nura-beauty-child`, plugin `nura-experience` 1.73.1 (about 32,000 lines of PHP plus JavaScript) |
| Method | Line-by-line review of every public endpoint, every admin action, every database query and every output path; automated pattern scans; `php -l` (PHP 8.3) on all 107 PHP files; JavaScript parse check |
| Previous audit | 14 October 2026 (theme 1.53.0 / plugin 1.55.0) - findings 1-10 below |

## 1. Summary

The code follows WordPress secure-coding practice throughout: prepared SQL, escaped output, nonce + capability checks on every staff action, and honeypot + time-trap + per-IP rate limits on every public form. The 17 October review covered the large amount of code added since the first audit (NURA Desk: point of sale, payment plans, appointments, sourced sales, team permissions, encrypted contacts vault, marketing, exports). No SQL injection, stored XSS, CSRF or privilege-escalation issue was found in that code. Seven lower-severity weaknesses were found and fixed (section 4).

| Area | Status |
| --- | --- |
| SQL injection | Safe. Every query with dynamic data uses `$wpdb->prepare()`. Table names come only from `$wpdb->prefix` + fixed names. No NoSQL database is used. |
| XSS | Safe. PHP output uses `esc_html` / `esc_attr` / `esc_url` / `wp_kses`; JavaScript renders data through an `esc()` helper or `textContent`. |
| CSRF | All 60+ staff actions (`admin_post_*`, `wp_ajax_*`) call `check_admin_referer()` / `check_ajax_referer()`. Public cached forms use honeypot + time trap + rate limits instead of nonces (documented accepted risk). |
| Broken authentication | Login lockout (5 per IP / 15 min, 10 per username / hour, 30 min lock), generic errors, bot traps, e-mail 2-step verification and new-device alerts for customers. |
| Unauthorised access | Every NURA Desk screen requires the Desk capability; sensitive actions are additionally gated by owner-set Team permissions (prices, discounts, refunds, mark paid, exports, delete) and logged to the activity log. |
| Session hijacking / expiry | Secure, HttpOnly WordPress cookies over HTTPS only; staff sessions 8 hours (3 days with "Remember me"); HSTS prevents cookie leakage over HTTP. |
| Password security | WordPress core hashing (bcrypt from WP 6.8). Minimum 10 characters, cannot equal username or e-mail. Passwords are never logged or stored by our code. |
| Insecure file uploads | No custom upload handlers. Media uploads now refuse script-capable types (SVG, HTML, JS, XML, PHP, etc.) for non-administrators, refuse double extensions (`photo.php.jpg`) and verify the real file type. |
| API vulnerabilities | Public `nurax/v1` routes are rate-limited (global 30 POST / 10 min + per-route limits); input allow-lists and length caps; WhatsApp webhook requires a valid `X-Hub-Signature-256`. |
| Data exposure | REST users endpoint closed to guests, `?author=` scans blocked, XML-RPC off, version strings hidden, Desk screens and exports sent with `no-store`, contact details encrypted at rest (AES-256-GCM vault). |
| Secrets | No keys in the repository. AI keys, WhatsApp token/secret and the vault key are read from environment variables or `wp-config.php` constants first, then from password fields in settings. |

## 2. Required security controls - where each is implemented

| Requirement | Implementation |
| --- | --- |
| Rate limiting | Login lockout; registration (3/h/IP) and password reset (5/h/IP); contact, newsletter, subscribe, WhatsApp order, Lipa Mdogo Mdogo request, AI stylist, wig finder and booking limits; global `nurax/v1` POST limiter; **new:** delivery-quote and live-search AJAX limits (`class-nura-hardening.php`). |
| Input sanitisation | `wp_unslash()` then `sanitize_text_field` / `sanitize_email` / `sanitize_key` / `absint` / allow-lists / length caps on every input; dates validated by `NURAX_Desk::valid_date()`; phone numbers normalised to `2547XXXXXXXX`. |
| Password hashing | WordPress core (`wp_hash_password`, bcrypt on WP 6.8+). Policy in `class-nura-login-guard.php`. |
| Environment variables / no hard-coded keys | `getenv()` then constants (`NURAX_OPENAI_KEY`, `NURAX_GEMINI_KEY`, WhatsApp token/secret, `NURA_DATA_KEY`) then settings. Catalogue feeds use a random per-site key compared with `hash_equals()`. |
| CORS configuration | REST API answers only the store's own origin (`nurax_cors_allowed_origins` filter for partners). |
| SQL / NoSQL injection protection | `$wpdb->prepare()` everywhere; no string-built SQL from user input; no NoSQL store. |
| Session expiry | Staff 8 h / 3 days; customers WordPress default; sessions destroyed on password change (core). |
| HTTPS everywhere | 301 redirect from HTTP when the site URL is HTTPS; HSTS one year with sub-domains on front end, login and admin. |
| Security headers | `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy`, `Strict-Transport-Security`; **new:** `Content-Security-Policy: frame-ancestors 'self'; base-uri 'self'; object-src 'none'` and `X-Permitted-Cross-Domain-Policies: none`. |
| Regular dependency updates | See section 6. The project has no Composer/npm dependencies; the dependencies are WordPress core, WooCommerce, Rank Math and the payment plugins. |

## 3. Findings from the first audit (14 October 2026) - all fixed

1. No brute-force protection on login (High) - IP + username lockout.
2. Unlimited bot registrations and reset e-mails (Medium) - honeypot, fill-time trap, hourly limits.
3. Weak passwords allowed (Medium) - 10-character minimum, not equal to username/e-mail.
4. Long-lived staff sessions (Medium) - 8 hours / 3 days.
5. HTTPS not enforced in code (Medium) - 301 redirect + HSTS on all screens.
6. REST API reflected any `Origin` (Low/Medium) - same-origin CORS only.
7. Application passwords for every user (Low) - administrators only.
8. Unsanitised `$_SERVER` reads in the theme (Low) - sanitised.
9. Client IP behind a proxy (Informational) - `NURAX_TRUSTED_PROXY_HEADER` constant.
10. Public WhatsApp order form without a nonce (Accepted risk) - honeypot, time trap, link filter, caps, 12/h/IP.

## 4. Findings from this audit (17 October 2026) - all fixed

| # | Severity | Finding | Fix |
| --- | --- | --- | --- |
| 11 | Medium | Public booking form: an old script opened WhatsApp on submit, bypassing the deposit, terms and time checks. | Old handler disabled for the validated form (theme 1.63.0). |
| 12 | Medium | Booking slots could be held with fake or reused M-Pesa codes, past times, invented times, or many bookings per phone. | Unverified deposits release the slot after 3 h (configurable); reused codes refused; past/invalid times refused; one open booking per phone per day (plugin 1.72.0). |
| 13 | Medium | Staff could start a payment plan below the 50% deposit policy. | Deposit floor enforced server-side for non-owners and logged as blocked. |
| 14 | Low | Media uploads accepted SVG/HTML/XML (script-capable) from any user allowed to upload, and did not stop double-extension names. | `class-nura-hardening.php`: risky types and real-type mismatches refused for users without `unfiltered_html` (administrators keep normal imports); double extensions refused for everyone. |
| 15 | Low | Public admin-ajax delivery quote (`nurax_wa_ship`) and live search (`nura_search`) had no rate limit (resource exhaustion / scraping). | 600 and 900 requests per IP per 10 minutes (generous because Kenyan mobile networks share one IP across many phones); staff exempt; `nurax_ajax_limit` filter. |
| 16 | Low | NURA Desk screens and exports could be stored by browser back/forward cache or shared proxies (customer data exposure on shared computers). | `Cache-Control: no-store, private` on every `nurax*` admin screen and `nurax_*` action. |
| 17 | Low | Social catalogue CSV feed did not neutralise spreadsheet formulas (CSV injection if a product name starts with `=`, `+`, `-`, `@`). | Cells starting with `=` or `@` are neutralised; normal titles are left unchanged for Meta/TikTok. |
| 18 | Low | `_ga` cookie used without sanitising in the GA4 server event. | Digits and dots only, 64-character cap. |
| 19 | Low | No modern anti-framing header (clickjacking defence relied only on the legacy `X-Frame-Options`). | `Content-Security-Policy: frame-ancestors 'self'` + `X-Permitted-Cross-Domain-Policies: none` on front end, login and admin. |

Verified with no change needed: all 60+ staff handlers (nonce + capability), POS AJAX (`check_ajax_referer` + capability), team permission changes (owner only + nonce), Vault (AES-256-GCM, random IV, authenticated tag, HMAC search index, key outside the database), Excel exports (inline strings, formula-safe), no `eval` / `unserialize` / shell functions / `$_FILES` handlers, no hard-coded secrets.

## 5. Accepted risks

- Public forms on cached pages use honeypot + time trap + rate limits instead of nonces, so real customers are never blocked by an expired nonce. They only create leads or requests; they cannot change orders, prices or accounts.
- A full script `Content-Security-Policy` is not enforced yet because the store loads Google Fonts, analytics, Meta/TikTok pixels and payment scripts. Next step: run `Content-Security-Policy-Report-Only` for two weeks, then enforce.
- M-Pesa deposit codes are checked for format and re-use, not against Safaricom. Staff must press "Deposit received" only after seeing the payment.

## 6. Maintenance (owner checklist)

1. Weekly: Dashboard > Updates - update WordPress core, WooCommerce, Rank Math, payment and all other plugins. Delete unused plugins and themes.
2. Turn on two-factor authentication for every administrator (for example the "Two Factor" plugin).
3. Unique long passwords for every staff account; remove staff who leave (NURA Desk > Team).
4. In `wp-config.php`: `define( 'FORCE_SSL_ADMIN', true );`, `define( 'DISALLOW_FILE_EDIT', true );`, and the `NURA_DATA_KEY` line shown in NURA Desk > Security. Keep a copy of that key offline.
5. Daily off-site backups; test a restore every quarter.
6. Put the site behind a WAF (Cloudflare or the host firewall) and copy the security headers there so cached pages carry them. If Cloudflare is used, set `NURAX_TRUSTED_PROXY_HEADER` to `HTTP_CF_CONNECTING_IP`.
7. Monthly: review NURA Desk > Team > Activity (blocked and security entries) and WooCommerce > Status > Logs.
