# Architecture

## 1. System overview

```
Shopper / staff (browser, phone)
   |  HTTPS (301 + HSTS)
   v
LiteSpeed web server + LiteSpeed Cache
   v
WordPress + WooCommerce (nurabeauty.co.ke)
   |-- nura-beauty-child   active theme, small overrides
   |     '-- nura-beauty   parent theme: templates, UX, SEO, headers, login screen
   |-- nura-experience     plugin: NURA Desk, commerce features, AI, WhatsApp, loyalty, security
   |-- Rank Math SEO, Paystack (M-Pesa + card), shipping zones
   '-- MySQL: WordPress tables + nurax_* custom tables

External: WhatsApp Cloud API, OpenAI / Gemini (optional), n8n, Google Reviews,
Meta / TikTok / GA4 (consent-gated), e-mail (wp_mail).
```

## 2. Parent theme `nura-beauty`

| Path | Responsibility |
| --- | --- |
| `functions.php` | `NURA_VERSION`, loads `inc/` modules via `nura_require()`, template routing |
| `inc/setup.php`, `inc/enqueue.php` | Theme supports, menus, scripts, fonts |
| `inc/woocommerce-support.php`, `nura-commerce.php`, `nura-product-ux.php`, `nura-filters.php`, `nura-search.php` | Shop, cart, checkout (required phone, Nairobi-only COD enforced server-side), product UX, filters, live search |
| `inc/nura-boost.php` + `assets/js/nura-boost.js` | Conversion features incl. Lipa Mdogo Mdogo product box |
| `inc/nura-seo*.php`, `seo-schema.php`, `nura-schema-graph.php` | Titles, schema.org, canonical/noindex fixes |
| `inc/nura-security.php` | Security headers, XML-RPC off, enumeration block, AI-bot blocking, form spam guards, `DISALLOW_FILE_EDIT` |
| `inc/nura-login.php`, `nura-account-login.php` | Branded wp-login.php and account login |
| `inc/customizer.php`, `dynamic-css.php` | Brand settings (phone, WhatsApp, e-mail, colours) |
| `inc/sample-content.php`, `sample-data.php`, `demo/` | Starter content importer |
| `page-book-appointment.php` | Morning-only booking with deposit and terms (posts to `nurax/v1/stylist-booking`) |
| `assets/js/main.js` | Cart drawer, steppers, search, menus, booking form |

## 3. Plugin `nura-experience`

| Class file | Feature |
| --- | --- |
| `class-nura-desk.php` | NURA Desk shell: menu, settings, overview, exports, `guard()` (capability + nonce), digest |
| `class-nura-desk-pos.php` | Point of sale (AJAX with `check_ajax_referer`), receipts, sourced lines |
| `class-nura-desk-orders.php` | WhatsApp / online order management |
| `class-nura-desk-plans.php` | Lipa Mdogo Mdogo plans and payments, public plan request |
| `class-nura-desk-appts.php` | Appointments, deposits, slot holds, "Studio now" board, `/slots` REST |
| `class-nura-desk-source.php` | Sourced sales, supplier ledger, stock-skip for sourced lines |
| `class-nura-desk-remind.php`, `-mkt.php`, `-social.php`, `-subscribe.php` | Reminders, campaigns and e-mail tracking, catalogue feeds (secret key), subscribe |
| `class-nura-staff.php` | Team, permissions, activity log, blocked-action logging |
| `class-nura-vault.php` | AES-256-GCM encrypted contact details, HMAC search index |
| `class-nura-xlsx.php` | Native Excel export (inline strings, formula-safe) |
| `class-nura-wa-order.php` | WhatsApp order pop-up, leads, county delivery quotes |
| `class-ai-stylist.php`, `class-nura-ai*.php`, `class-ai-wig-finder.php`, `class-nura-wig-quiz.php`, `class-virtual-tryon.php` | AI stylist + booking endpoint, providers, wig finder, quiz, try-on |
| `class-nura-whatsapp.php`, `class-nura-whatsapp-bot.php` | WhatsApp links and signed webhook bot |
| `class-nura-account.php`, `class-nura-circle.php`, `class-care-reminders.php` | My Account, 2-step verification, loyalty, care schedule, warranties |
| `class-nura-abandoned-cart.php`, `class-nura-followup.php`, `class-nura-consent.php`, `class-nura-retargeting.php` | Cart recovery, follow-ups, consent, consent-gated pixels |
| `class-shop-enhance.php`, `class-shop-filters.php`, `class-product-page.php`, `class-mega-menu.php`, `class-nura-trust.php`, `class-nura-schema.php`, `class-nura-google-reviews.php`, `class-nura-community.php`, `class-journal.php` | Storefront extras |
| `class-nura-pages.php`, `class-wig-attributes.php`, `class-nura-copy-fix.php`, `class-admin-tidy.php`, `class-settings.php`, `class-nura-perf.php` | Admin tools, page builder (terms, FAQ), settings, performance |
| `class-nura-security.php` | REST POST limiter, enumeration block, XML-RPC off |
| `class-nura-login-guard.php` | Lockout, bot traps, password policy, staff sessions, HTTPS, CORS, application passwords |
| `class-nura-hardening.php` | CSP frame-ancestors, upload safety, no-store Desk, AJAX limits |

## 4. Data model

| Store | Used for |
| --- | --- |
| WooCommerce products, variations, orders | Catalogue and sales (POS sales are WooCommerce orders) |
| `nurax_appts` | Bookings incl. `deposit`, `deposit_ref`, `deposit_status`, `arrived_at`, `terms_at` |
| `nurax_lmm_plans`, `nurax_lmm_payments` | Payment plans and instalments |
| `nurax_sourcing` | Sourced lines, supplier, cost, status (waiting / arrived / handed), paid |
| `nurax_contacts` | Encrypted customer contacts (vault) |
| `nurax_campaigns`, `nurax_camp_sends` | Marketing campaigns and sends |
| `nurax_activity` | Staff activity and security log |
| `nurax_abandoned_carts`, `nurax_consent` | Cart recovery, marketing consent |
| Private post type | WhatsApp order leads `NURA-YYMMDD-XXXX` |
| User meta | NURA Circle points, membership, warranties |
| Options / theme mods | Settings, permissions, brand details |
| Transients `nurax_*`, `nura_*` | Rate-limit counters and caches |

## 5. Request flows

- **Checkout:** WooCommerce checkout; phone required; COD only for Nairobi, re-checked server-side.
- **WhatsApp order:** pop-up -> `nurax_wa_order` (honeypot, time trap, rate limit) -> lead -> WhatsApp opens with formatted message.
- **Booking:** page -> `GET nurax/v1/slots` -> `POST nurax/v1/stylist-booking` (rate limit, morning slot list, not past, one per phone/day, M-Pesa code format + re-use check, terms) -> appointment "request" -> staff "Deposit received" -> confirmed WhatsApp.
- **Point of sale:** Desk till -> `nurax_pos_order` (nonce + capability + team permission checks) -> WooCommerce order, receipts, sourced lines.
- **AI stylist / wig finder:** browser -> `nurax/v1` (per-IP limits, stripped and capped input); keys used server-side only.
- **WhatsApp bot:** Meta -> webhook; `X-Hub-Signature-256` verified.
- **Login:** bot trap -> lockout check -> WordPress auth -> session length by role.

## 6. Security layers

Edge (host/WAF, TLS) -> HTTPS redirect + HSTS -> security headers + CSP frame-ancestors -> bot user-agent block -> honeypot / time trap / rate limits -> REST and AJAX limiters -> login guard -> capability + nonce + team permissions -> sanitised input, prepared SQL, escaped output -> encrypted contacts -> activity log. Details: SECURITY-AUDIT.md.

## 7. Release process

1. Change code; bump theme and/or plugin versions.
2. `php -l` and JS parse checks.
3. Commit to `main` on `moselanto/nurabeauty.co.ke`.
4. Build zips (`nura-beauty/` incl. `demo/`, `nura-experience/`).
5. Upload with "Replace current with uploaded", LiteSpeed Cache > Purge All, Ctrl+F5.
6. Verify live: `?ver=` strings, checkout, WhatsApp pop-up, booking, till, login.
