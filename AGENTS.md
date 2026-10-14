# AGENTS.md - working on this repository

Guidance for developers and AI coding agents changing the NURA Beauty store code.

## What lives here

| Folder | What it is | Installed as |
| --- | --- | --- |
| `nura-beauty/` | Parent theme: layout, templates, WooCommerce styling, SEO, security, login screen | Appearance > Themes |
| `nura-beauty-child/` | Child theme (the active theme on the live site); small overrides only | Appearance > Themes |
| `nura-experience/` | Plugin: WhatsApp ordering, AI stylist, wig finder, NURA Circle loyalty, abandoned cart, consent, schema, security and login guard | Plugins |
| `docs/` | Images and screenshots used by the README | - |

Live site: https://nurabeauty.co.ke (WordPress + WooCommerce, Kenya, KES).

## Golden rules

1. **Never break checkout.** Test cart, checkout (Nairobi and a non-Nairobi county), the WhatsApp order pop-up and My Account login after every change.
2. **Do not remove features.** Fix or improve in place.
3. **Bump versions** on every release: theme `Version:` in `style.css` and `NURA_VERSION` in `functions.php`; plugin header `Version:` and `NURAX_VERSION` in `nura-experience.php`. Asset URLs use these versions for cache busting.
4. **Commit to `main` and ship a zip.** The owner installs zips via Plugins / Themes > Add New > Upload > "Replace current with uploaded". The theme zip must include `demo/` (the sample-content importer reads it).
5. **No secrets in code.** Read keys from environment variables or `wp-config.php` constants first, then settings.
6. When generating PHP with scripts, check for stray `\!` (escaped exclamation marks) before committing; it is a known tooling pitfall and is a PHP syntax error.

## Secure coding checklist

- Sanitise every input: `sanitize_text_field`, `sanitize_email`, `absint`, `sanitize_key`, allow-lists; always `wp_unslash()` superglobals first.
- Escape every output at the point of output: `esc_html`, `esc_attr`, `esc_url`, `wp_kses`. In JavaScript, never put data into `innerHTML` without the local `esc()` helper; prefer `textContent`.
- SQL: only `$wpdb->prepare()` with placeholders; never concatenate user input.
- Admin actions: `current_user_can()` **and** `check_admin_referer()` / `wp_verify_nonce()`.
- Public endpoints: `permission_callback` must be explicit; public ones need a honeypot, rate limit and length caps.
- Use `wp_safe_redirect()` and `wp_remote_*()`; never `eval`, `unserialize` of user data, or shell functions.

## Testing before release

PHP is not always available in the build sandbox. Before shipping, run `php -l` on changed files (or upload to a staging site first), then check on the live site as a logged-out shopper.
