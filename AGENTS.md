# AGENTS.md - working on this repository

Rules for developers and AI coding agents changing the NURA Beauty store code.

## 1. What lives here

| Folder | What it is | Installed as |
| --- | --- | --- |
| `nura-beauty/` | Parent theme: templates, WooCommerce styling, booking page, SEO, security headers, branded login | Appearance > Themes |
| `nura-beauty-child/` | Child theme (active on the live site); small overrides only | Appearance > Themes |
| `nura-experience/` | Plugin: NURA Desk back office, WhatsApp ordering, AI stylist and wig finder, loyalty, marketing, security | Plugins |
| `docs/` | Images and screenshots used by the README | - |
| `*.md` | README, PRD, architecture, security audit, this file | - |

Live site: https://nurabeauty.co.ke (WordPress + WooCommerce, Kenya, KES, Africa/Nairobi time).

## 2. Golden rules

1. **Never break checkout.** After every change test cart, checkout (Nairobi and another county), the WhatsApp order pop-up, the booking page, NURA Desk point of sale and My Account login.
2. **Do not remove features.** Fix or improve in place.
3. **Bump versions on every release.** Theme: `Version:` in `style.css` and `NURA_VERSION` in `functions.php`. Plugin: header `Version:` and `NURAX_VERSION` in `nura-experience.php`. Asset URLs use these for cache busting.
4. **Database changes** go through the module's `DBV` constant and `dbDelta()` in `maybe_install()` (runs on `init`). Never drop columns that hold live data.
5. **Commit to `main` and ship zips.** The owner installs via Upload > "Replace current with uploaded", then LiteSpeed Cache > Purge All. The theme zip must include `demo/`.
6. **No secrets in code.** Environment variable, then `wp-config.php` constant, then a password field in settings.
7. Staff-sensitive actions must check `NURAX_Staff::can( '<permission>' )` and write `NURAX_Staff::log()` with a valid area.

## 3. Secure coding checklist

- Input: `wp_unslash()` first, then `sanitize_text_field`, `sanitize_email`, `sanitize_key`, `absint`, allow-lists and length caps.
- Output: escape at the point of output (`esc_html`, `esc_attr`, `esc_url`, `wp_kses`). In JavaScript use `textContent` or the local `esc()` helper, never raw `innerHTML`.
- SQL: `$wpdb->prepare()` with placeholders only.
- Staff actions: `current_user_can()` **and** `check_admin_referer()` / `check_ajax_referer()` (`NURAX_Desk::guard()` does both).
- Public endpoints: explicit `permission_callback`, honeypot, rate limit, length caps; read-only where possible.
- Exports: pass every cell through `NURAX_Desk::csv_cell()` (formula injection).
- Redirects: `wp_safe_redirect()`. HTTP: `wp_remote_*()`. Never `eval`, `unserialize` of user data, or shell functions.

## 4. Coding style

WordPress Coding Standards: tabs, Yoda conditions, `array()` syntax, one class per file named `class-nura-*.php`, a header docblock describing the module, small private helpers, sectioned with `/* ---- */` comments.

## 5. Testing before release

- `php -l` every changed PHP file (a static PHP 8.3 binary works in the build sandbox).
- `node -e "new Function(require('fs').readFileSync(f,'utf8'))"` for changed JavaScript.
- Then test on the live site as a logged-out shopper and as a shop manager.
