# Architecture essentials (one-page version)

- **Platform:** WordPress 6.2+ with WooCommerce 8+, PHP 7.4+, hosted at nurabeauty.co.ke. SEO by Rank Math.
- **Theme (`nura-beauty`)** renders every page. `functions.php` loads small modules from `inc/` through `nura_require()`; front-end behaviour is in `assets/js/main.js`, styles in `assets/css/`. The active **child theme** inherits it.
- **Plugin (`nura-experience`)** adds business features. `nura-experience.php` loads one class per feature from `includes/`; JavaScript in `assets/js/`.
- **Data:** WooCommerce products/orders; custom tables for abandoned carts and marketing consent; a private post type for WhatsApp order leads; user meta for NURA Circle points and warranty certificates; options for settings.
- **Public endpoints:** REST namespace `nurax/v1` (wig finder, stylist chat, stylist booking, quick view, WhatsApp webhook) and admin-ajax actions (`nurax_wa_order`, `nurax_wa_product`, `nurax_cart_capture`, `nura_search`). All are rate-limited.
- **Integrations:** WhatsApp Cloud API (signed webhook), optional AI providers (OpenAI / Gemini), n8n automation, M-Pesa and card via Paystack, Google reviews, Meta/TikTok/GA retargeting with consent.
- **Security layers:** theme `inc/nura-security.php` (headers, XML-RPC off, enumeration block, bot blocking, form spam guards) + plugin `class-nura-security.php` (REST rate limit) + plugin `class-nura-login-guard.php` (login lockout, bot traps, password policy, session expiry, HTTPS, CORS).
- **Delivery and payment rules:** same-day Nairobi before 5:00 PM; rider from KSh 300; free Nairobi delivery over KSh 10,000; cash on delivery Nairobi only (enforced in `woocommerce-support.php`).

See ARCHITECTURE.md for the full picture.
