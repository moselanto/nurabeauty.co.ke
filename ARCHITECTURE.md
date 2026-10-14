# Architecture

## 1. System overview

```
Shopper (browser / phone)
   |  HTTPS
   v
WordPress + WooCommerce  (nurabeauty.co.ke)
   |-- nura-beauty-child  (active theme, overrides)
   |     '-- nura-beauty   (parent theme: templates, UX, SEO, security, login)
   |-- nura-experience     (plugin: commerce features, AI, WhatsApp, loyalty, security)
   |-- Rank Math SEO, WooCommerce payments (M-Pesa / Paystack), shipping zones
   '-- MySQL (WordPress tables + nurax custom tables)

External services: WhatsApp Cloud API, OpenAI / Gemini (optional), n8n, Google Reviews,
Meta / TikTok / Google Analytics (consent-gated), email (wp_mail).
```

## 2. Parent theme `nura-beauty`

| Path | Responsibility |
| --- | --- |
| `functions.php` | Defines `NURA_VERSION`, loads modules with `nura_require()`, template routing filters |
| `inc/setup.php`, `inc/enqueue.php` | Theme supports, menus, scripts, fonts, preconnects |
| `inc/woocommerce-support.php` | Shop/cart/checkout integration, required phone, Nairobi-only COD (hidden + server-side block), cart fragments |
| `inc/nura-commerce.php`, `nura-product-ux.php`, `nura-filters.php`, `nura-search.php` | Product page UX, filters, live search (AJAX `nura_search`) |
| `inc/nura-seo*.php`, `seo-schema.php` | Titles, schema.org, canonical/noindex fixes |
| `inc/nura-security.php` | Headers, XML-RPC off, user-enumeration block, AI-bot blocking, newsletter/contact spam guards |
| `inc/nura-login.php` | Branded wp-login.php (photo panel, form styling); photo set in Customizer |
| `inc/customizer.php`, `dynamic-css.php` | Brand settings (phone, WhatsApp, email, colours) |
| `inc/sample-content.php`, `sample-data.php`, `demo/` | One-click starter content importer |
| `inc/nura-speed.php`, `nura-polish.php` | Performance tweaks and UI polish |
| `page-*.php`, `front-page.php`, `header.php`, `footer.php` | Page templates |
| `assets/js/main.js` | Cart drawer, quantity steppers, search, menus |

## 3. Plugin `nura-experience`

| Class file | Feature |
| --- | --- |
| `class-nura-wa-order.php` + `assets/js/nura-wa-order.js` | WhatsApp order pop-up, lead storage, admin leads screen |
| `class-ai-stylist.php`, `class-nura-ai*.php` | AI stylist chat and booking (REST), provider abstraction, n8n bridge |
| `class-ai-wig-finder.php`, `class-nura-wig-quiz.php`, `class-virtual-tryon.php` | Wig finder, quiz, in-browser try-on |
| `class-nura-whatsapp.php`, `class-nura-whatsapp-bot.php` | WhatsApp links and signed Cloud API webhook bot |
| `class-nura-circle.php`, `class-care-reminders.php` | Loyalty points, tiers, care schedule, warranty certificates (My Account endpoints) |
| `class-nura-abandoned-cart.php`, `class-nura-followup.php`, `class-nura-consent.php`, `class-nura-retargeting.php` | Cart recovery emails, follow-ups, marketing consent and unsubscribe, consent-gated pixels |
| `class-shop-enhance.php`, `class-shop-filters.php`, `class-product-page.php`, `class-mega-menu.php` | Quick view (REST), filters, product page extras, mega menu |
| `class-nura-schema.php`, `class-nura-google-reviews.php`, `class-nura-trust.php`, `class-nura-community.php`, `class-journal.php` | Structured data, reviews, trust badges, community, journal shortcode |
| `class-nura-pages.php`, `class-wig-attributes.php`, `class-nura-copy-fix.php`, `class-admin-tidy.php` | Admin tools: page builder, attribute setup, copy fixes, admin clean-up |
| `class-nura-security.php` | Headers fallback, enumeration block, `nurax/v1` POST rate limit |
| `class-nura-login-guard.php` | Login lockout, bot traps, password policy, staff session expiry, HTTPS redirect, CORS, application passwords |
| `class-settings.php` | Settings screen (AI keys as password fields) |

## 4. Data model

| Store | Used for |
| --- | --- |
| WooCommerce products, variations, orders | Catalogue and sales |
| `{prefix}nurax_abandoned_carts` (custom table) | Cart recovery (email, phone, token, status) |
| `{prefix}nurax_consent` (custom table) | Marketing opt-in and unsubscribe tokens |
| Private post type for WhatsApp leads | Order leads with reference `NURA-YYMMDD-XXXX` |
| User meta `_nurax_points`, `_nurax_member`, `_nurax_warranties` | NURA Circle |
| Options / theme mods | Settings, brand details, login photo |
| Transients `nurax_*`, `nura_*` | Rate-limit counters and caches |

## 5. Request flows

- **Checkout:** WooCommerce Checkout block; phone required; shipping zones Nairobi (rider KSh 300, bus 350, Fargo/G4S 550, free over 10,000) and Kenya; COD shown only for Nairobi and re-checked server-side.
- **WhatsApp order:** pop-up collects name/phone/county, POSTs `nurax_wa_order` (honeypot, time trap, rate limit), stores a lead, opens WhatsApp with a formatted message.
- **AI stylist / wig finder:** browser POSTs to `nurax/v1`; per-IP limits; inputs stripped and capped; AI keys read server-side only.
- **WhatsApp bot:** Meta calls `nurax/v1` webhook; `X-Hub-Signature-256` HMAC verified with the app secret; unsigned requests ignored.
- **Login:** wp-login.php or My Account -> bot trap -> lockout check -> WordPress authentication -> session length by role.

## 6. Security layers

Edge (host / Cloudflare WAF, TLS) -> HTTPS redirect + HSTS -> security headers -> bot user-agent block -> per-form honeypot/time-trap/rate limits -> REST rate limiter -> login guard -> capability + nonce checks -> sanitised input / prepared SQL / escaped output. Details in SECURITY-AUDIT.md.

## 7. Release process

1. Change code, bump versions (theme and/or plugin).
2. Commit to `main` on `moselanto/nurabeauty.co.ke`.
3. Build zips (`nura-beauty/` including `demo/`, `nura-experience/`).
4. Upload via WordPress admin "Replace current with uploaded".
5. Verify live: asset `?ver=` strings, checkout, WhatsApp pop-up, login.
