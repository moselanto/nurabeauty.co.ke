# HANDOVER.md - current state for the next developer / AI agent

Last updated: 27 Oct 2026. Read this with [AGENTS.md](AGENTS.md) before changing anything, so finished work is not redone.

## 1. Current versions (on `main`)

| Package | Version | Constant |
| --- | --- | --- |
| `nura-beauty/` (parent theme) | **1.73.0** | `Version:` in `style.css`, `NURA_VERSION` in `functions.php` |
| `nura-experience/` (plugin) | **1.79.1** | header `Version:` and `NURAX_VERSION` in `nura-experience.php` |
| `nura-beauty-child/` | unchanged | - |

Scope: this repo is NURA Beauty only. The sister site MK Auto Spares (`moselanto/https-mkautospares.co.ke-`, plugin `mk-desk`) was used only as a reference to copy features from. Do not change the MK repo from NURA work.

## 2. Releases in this round (newest first)

| Theme | Plugin | Commit | What changed |
| --- | --- | --- | --- |
| - | 1.79.1 | (this commit) | Till business name "Nura Beauty & Crown" shown under the till number, in the payment steps and in the WhatsApp message (constant `NURAX_MPESA_TILL_NAME` or filter `nurax_mpesa_till_name`). |
| 1.73.0 | 1.79.0 | b0198f5 | **No cash on delivery; WhatsApp orders paid to M-Pesa Till 1679269.** Plugin `class-nura-wa-order.php` + `nura-wa-order.js/.css`: payment options are now `NURAX_WA_Order::PAY_TILL` ("M-Pesa Till (pay now)") and `PAY_SHOP` ("Pay at the shop on collection", shown only for studio pick-up and forced back to Till server-side for delivery). New Lipa na M-Pesa panel (till, copy button, live amount, 4 steps, optional 10-character M-Pesa code). Code stored in `_nwo` (`mpesa_code`, `code_flag`) and post meta `_nwo_pay_ref`; reuse checked against `NURAX_Desk::ref_used()` and earlier WhatsApp orders and flagged for staff (never blocks the customer). New professional WhatsApp message (order summary, totals, customer, delivery, payment status). Till via constant `NURAX_MPESA_TILL` or filter `nurax_mpesa_till`; optional `nurax_mpesa_till_name`. Desk Orders shows code and flag. Theme `woocommerce-support.php`: WooCommerce `cod` gateway only when every chosen shipping method is `local_pickup`/`pickup_location`, retitled "Pay at the shop on collection (cash or M-Pesa)", blocked in classic and Store API checkout (filter `nura_allow_cod_delivery` to override). All storefront, FAQ, schema, SEO and AI-stylist copy updated; Customizer filters strip saved "on delivery" wording. |
| - | 1.78.0 | - | CRO fix "modal blocking content": the centred Join The House pop-up no longer opens by itself. New visitors get a slim bottom bar (`.nhs-bar`, no image, under 120px desktop / 80px phone, close button) only from their 2nd page view (`localStorage nura_house_pv`) or after scrolling 60% of a page. Exit-intent and timed pop-up removed. Full pop-up opens only from the "Join The House" tab. Settings defaults: `sub_delay` 4 s (on 2nd page), `sub_scroll` 60% minimum. |
| 1.72.0 | 1.77.1 | f9412eb | Login page uses self-hosted fonts (Google Fonts only as fallback). `NURAX_Desk::ref_used()` label now checks `nura_pos` (was `nurax_pos`), so reused codes say "sale #". |
| - | 1.77.0 | c722ad3 | `NURAX_Desk_POS::online_paid()` WhatsApp receipt for paid website orders (processing/completed hooks, API only); `skip_wc_mail()` stops duplicate WooCommerce customer e-mail on till sales; `contacts()` fallback (order, account, WhatsApp order, customer list); `sent_info()` badges on Sales list; `orders_for_appt()` and "Billed #N" on bookings instead of Bill. |
| - | 1.76.0 | 0f82e78 | Void a till sale (op `void`, reason >= 3 chars, `refunds` permission, status cancelled restocks, VOID line on documents, staff log warn); part payments (op `part`, `payments()`, `paid_so_far()`, `balance()`, `add_payment()`, auto-complete at zero balance); "Bill" button on bookings opens till via `?appt=ID` (verified deposit taken off invoice, booking set completed); automatic receipts `deliver()` once per channel after paid sale / mark paid / final part payment. |
| 1.71.0 | 1.75.0 | b082f45 | Self-hosted fonts (`nura-beauty/assets/fonts/*.woff2`, `nura_local_fonts_ok()` in `inc/nura-speed.php`); minified JS served via `script_loader_src` (section 17 of `nura-speed.php`); one payment code = one use (`norm_ref`, `ref_used`, `ref_lock` in `class-nura-desk.php`, wired into POS, plans, appointments); staff idle logout 60 min (`class-nura-login-guard.php`, filter `nurax_staff_idle`). |
| - | 1.74.1 | 17cc982 | Compact "Join The House" pop-up (`assets/css/nura-subscribe.css`). |
| - | 1.74.0 | 4009dc6 | `includes/class-nura-load.php` (`NURAX_Load`): DB-connection load reduction for the host's 12 MySQL connection limit (Action Scheduler 1 batch x 20, heartbeat off front end / 60 s admin, async runner off when `DISABLE_WP_CRON`). Filter `nurax_load_guard` disables it. |
| - | 1.73.9 | 35151f6 | Fallback Google Place ID `ChIJGx-_W1sRLxgRxNUNZe1z9ug` in `class-nura-community.php` (saved setting wins). `/review` confirmed working. |
| - | 1.73.8 | 7b9009c | `/review` short link to Google reviews; WhatsApp "Review" button on paid Desk orders. |

All MK Desk features worth porting are now in NURA. Not ported on purpose: MK vehicle job card (cars only).

## 3. Conventions that must be kept

- Till orders: `created_via = 'nura_pos'` (never `nurax_pos`). Meta: `_nura_pay_ref`, `_nura_part_ref`, `_nura_payments`, `_nura_channel`, `_nura_cashier`, `_nura_tendered`, `_nura_change`, `_nura_wa_order`, `_nura_appt`, `_nura_dep_from`, `_nura_void_reason`, `_nura_void_by`, `_nura_sent_email_{receipt|invoice}`, `_nura_sent_wa_{receipt|invoice}`. WhatsApp order post meta `_nwo_wc_order`.
- New payment-code meta keys must be added to the `meta_key IN (...)` list in `NURAX_Desk::ref_used()`.
- Staff permission for voids is `refunds` (`NURAX_Staff::can( 'refunds' )`).
- **Minified JS:** 4 theme and 6 plugin scripts have `.min.js` twins that are served instead of the source unless `SCRIPT_DEBUG`. After editing any such `.js`, regenerate: `npx --yes terser f.js -c -m --comments false -o f.min.js`, then syntax-check it. Forgetting this ships stale code.
- Self-hosted fonts are used only while the Customizer fonts are the defaults (Playfair Display, Cormorant Garamond, Montserrat, Jost).
- Filters: `nurax_auto_receipts` (auto e-mail/WhatsApp receipts), `nurax_staff_idle`, `nurax_load_guard`, `nura_local_fonts`, `nura_blocked_user_agents`.

## 4. Release routine

1. `php -l` every changed PHP file; `node -e "new Function(require('fs').readFileSync(f,'utf8'))"` for JS; regenerate `.min.js`.
2. Bump versions (section 1).
3. Zip: plugin folder as `nura-experience/`; theme as `nura-beauty/` excluding `.git*` (include `demo/`).
4. Commit to `main`. Owner installs: Upload > "Replace current with uploaded", LiteSpeed Cache > Purge All, Ctrl+F5.

## 5. Server and SEO settings (done in wp-admin / hosting, not in code)

| Item | Status |
| --- | --- |
| Real cron: Enhance cron every 5 min `wget -q -O - "https://nurabeauty.co.ke/wp-cron.php?doing_wp_cron" >/dev/null 2>&1` | Done - owner confirmed 17 Oct (`-O -` fixed) |
| `define( 'DISABLE_WP_CRON', true );` in `wp-config.php` | Done - owner confirmed 17 Oct |
| Redis on in Enhance + LiteSpeed Object Cache (Redis 127.0.0.1:6379, lifetime 360, persistent ON) | Done, connection test passed; Cache WP-Admin OFF - owner confirmed 17 Oct |
| LiteSpeed UCSS / Critical CSS (QUIC.cloud linked, CSS minify+combine, UCSS, async CSS, selector allowlist of JS state classes, UCSS excludes /cart /checkout /my-account) | Configured; queue run and visual check pending. JS combine left OFF on purpose |
| Rank Math logo ".png.png" | Fixed - Rich Results Test shows `nura-beauty-logo.webp`, Organization/OnlineStore + one HealthAndBeautyBusiness (#studio), no errors |
| Rank Math Local SEO module | Keep OFF - plugin `class-nura-schema.php` supplies address, geo, hours (Mon-Sat 08:00-20:00, confirm with owner), map |
| Search Console | `sitemap_index.xml` Success, 97 URLs (17 Oct). Rank Math sitemaps: pages, products, product categories ON; attachments, product tags OFF; images ON |
| Indexing requests sent 17 Oct | /shop/, /book-appointment/, /about-us/, /faq/, /product-category/wigs/, .../lace-front-wigs/, .../closure-wigs/, /product/kinky-headband-wig/, /product/12-inches-wig/ |
| Indexing requests still to do (daily quota) | Home page, human hair wigs category, /product/minky-closure-wig/, remaining best sellers |
| WebToffee Import Export (Pro) update | Pending (owner given steps: backup, licence, update, test export) |

## 6. Open items / to verify on the live site

- 1.79.0 / 1.73.0 checklist: WhatsApp order with delivery shows Till panel and no shop option; pick-up shows "Pay at the shop"; message format; code saved and shown in Desk; checkout hides Cash on delivery for delivery methods and shows "Pay at the shop" only for pick-up (enable WooCommerce Local pickup if wanted). Set NURA Desk > Settings > M-Pesa payment details to `Buy Goods Till 1679269 (NURA Beauty)` so reminders and receipts match. Re-save Customizer announcement if it still mentions cash on delivery.

- Nothing in 1.73.8 to 1.77.1 / 1.71.0 to 1.72.0 was tested on the live site by the agent; owner test checklists: part pay to zero, single receipt e-mail, reused code refused, void restocks + VOID, Bill booking with deposit, WhatsApp receipt on website order (API only).
- Address (Imenti House, Moi Avenue, Nairobi CBD, 00100) and hours must match the Google Business Profile.
- Watch error log for "cron event list could not be saved" (DB connection limit); should stop after real cron + Redis.
