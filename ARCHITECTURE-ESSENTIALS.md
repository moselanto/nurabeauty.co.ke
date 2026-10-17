# Architecture essentials (one page)

- **Platform:** WordPress 6.2+, WooCommerce 8+, PHP 7.4+ (8.x recommended), LiteSpeed Cache, Rank Math SEO. Host: nurabeauty.co.ke over HTTPS.
- **Theme `nura-beauty`** (active through `nura-beauty-child`) renders every page. `functions.php` loads modules from `inc/`; front-end behaviour in `assets/js/main.js`, `nura-boost.js`; styles in `assets/css/`.
- **Plugin `nura-experience`** adds the business features. `nura-experience.php` loads one class per feature from `includes/`.
- **NURA Desk** (wp-admin back office for staff): overview, point of sale, WhatsApp orders, Lipa Mdogo Mdogo payment plans, appointments, sourced sales, reminders, marketing, social, team and permissions, security vault, Excel/CSV exports.
- **Data:** WooCommerce products and orders; custom tables `nurax_appts`, `nurax_lmm_plans`, `nurax_lmm_payments`, `nurax_sourcing`, `nurax_contacts`, `nurax_campaigns`, `nurax_camp_sends`, `nurax_activity`, `nurax_abandoned_carts`, `nurax_consent`; options for settings; transients for rate limits.
- **Public endpoints:** REST `nurax/v1` (`/wig-finder`, `/stylist`, `/stylist-booking`, `/slots`, `/quickview`, WhatsApp webhook) and admin-ajax (`nurax_wa_order`, `nurax_wa_product`, `nurax_wa_ship`, `nurax_lmm_request`, `nurax_subscribe`, `nurax_cart_capture`, `nura_search`). All rate-limited.
- **Integrations:** WhatsApp Cloud API (signed webhook), OpenAI / Gemini (optional), n8n, M-Pesa and card via Paystack, Google reviews, consent-gated Meta / TikTok / GA4.
- **Security layers:** theme `inc/nura-security.php` (headers, XML-RPC off, enumeration and bot blocking, form guards) + plugin `class-nura-security.php` (REST limiter) + `class-nura-login-guard.php` (lockout, password policy, sessions, HTTPS, CORS) + `class-nura-hardening.php` (CSP frame-ancestors, uploads, no-store Desk, AJAX limits) + `class-nura-staff.php` (permissions, activity log) + `class-nura-vault.php` (encrypted contacts).
- **Business rules:** same-day Nairobi before 5:00 PM; rider from KSh 300; free Nairobi delivery over KSh 10,000; COD Nairobi only; studio bookings mornings only with a 50% deposit; Lipa Mdogo Mdogo 50% deposit, balance within 2 weeks.

See ARCHITECTURE.md for detail and SECURITY-AUDIT.md for security.
