# HANDOVER.md - current state for the next developer / AI agent

Last updated: 1 Nov 2026. Read this with [AGENTS.md](AGENTS.md) before changing anything, so finished work is not redone.

## 0. Working agreement with the owner (read first)

- **Every session must end with a repo update.** Whatever is changed, decided or left pending goes into this file (sections 1, 2 and 6) and the session log below, committed to `main`, so the next AI agent knows exactly where things stand.
- Code releases also update README badges/release table and PRD status where relevant.

### Session log (newest first)

| Date | Agent | What happened | Pending after this session |
| --- | --- | --- | --- |
| 1 Nov 2026 (evening) | Town assistant (Ray) | Owner shared an SEO/AEO/GEO audit (docx) and agent prompt to improve. Verified every claim against `main` and the live site, then rewrote both as v2 and added a business facts register, committed to draft PR #1 (branch `seo-aeo-geo-audit-2026-11`): `docs/SEO-AEO-GEO-AUDIT-2026-11.md`, `docs/NURA-SEO-AEO-GEO-AGENT-PROMPT.md`, `docs/NURA-BUSINESS-FACTS.md`. Key verified issues: Imenti House in 26 files + live site (shop moved to Stanbank House, 2nd Floor, Room 20, Nairobi CBD); old coordinates in 2 schema files; live `/llms.txt` served by legacy `nura-seo-plus.php` handler (old address, empty category list); 5 PM vs 2 PM cut-off; 7 days vs 48 h returns; guide still offers COD; brand forced to NURA; shipping-rate min includes pick-up (KSh 0). Docs only, no code change. | Owner decisions D1-D10 in `docs/NURA-BUSINESS-FACTS.md` (street/postcode, map pin, hours, walk-in policy, cut-off, returns, pick-up, international shipping, AI training crawlers, brands). Then R1 (facts registry + relocation) on a feature branch. Owner to merge PR #1. Access: dedicated WordPress user with 2FA; Google access via added user roles, never shared passwords. |
| 1 Nov 2026 (later) | Town assistant (Ray) | Owner reported "Join The House" bar re-appearing after every reload even after cancelling. Reviewed `nura-subscribe.js`, its `.min.js` twin and `class-nura-desk-subscribe.php`: the 24 h snooze (cookie + localStorage + sessionStorage `nura_house_snooze`, hours from `NURAX_SUB.hours`) is already in `main` as plugin 1.79.2, and the min file contains it. No code change made. Most likely cause: 1.79.2 not yet uploaded to the live site, or a stale LiteSpeed/browser cache. | Owner to confirm the live plugin version (Plugins page should say 1.79.2), upload if older, LiteSpeed Purge All, then test in a normal (non-incognito) window: close the bar, reload. If it still reappears on 1.79.2, check that `nura_house_snooze` cookie is being set (browser dev tools) and whether a cache/consent plugin clears cookies. |
| 1 Nov 2026 | Town assistant (Ray) | Onboarded: studied the repo (theme 1.73.0, plugin 1.79.2, child theme) and the live site. Live homepage matches the 1.79.x copy (no cash on delivery, Till 1679269, Lipa Mdogo Mdogo, same-day Nairobi). Docs only: fixed stale "Nairobi-only COD" wording in ARCHITECTURE.md, ARCHITECTURE-ESSENTIALS.md and PRD.md. No code or version change. | Owner to say which changes/updates come next. Section 6 items still open. |

## 1. Current versions (on `main`)

| Package | Version | Constant |
| --- | --- | --- |
| `nura-beauty/` (parent theme) | **1.73.0** | `Version:` in `style.css`, `NURA_VERSION` in `functions.php` |
| `nura-experience/` (plugin) | **1.79.2** | header `Version:` and `NURAX_VERSION` in `nura-experience.php` |
| `nura-beauty-child/` | unchanged | - |

Scope: this repo is NURA Beauty only. The sister site MK Auto Spares (`moselanto/https-mkautospares.co.ke-`, plugin `mk-desk`) was used only as a reference to copy features from. Do not change the MK repo from NURA work.

## 2. Releases in this round (newest first)

| Theme | Plugin | Commit | What changed |
| --- | --- | --- | --- |
| - | 1.79.2 | (this commit) | Join The House: closing the bar or pop-up (X, No thanks, Esc, backdrop) snoozes everything for 24 h via `nura_house_snooze` (cookie + localStorage + sessionStorage). The tab no longer slides in by itself; preview link respects the snooze. New Desk setting `sub_hours` (default 24, "If closed, ask again after (hours)"), filter `nurax_sub_snooze_hours`. Subscribers still never see it. |
| - | 1.79.1 | f683ecb | Till business name "Nura Beauty & Crown" shown under the till number, in the payment steps and in the WhatsApp message (constant `NURAX_MPESA_TILL_NAME` or filter `nurax_mpesa_till_name`). |
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

- **Relocation (critical):** shop moved from Imenti House to Stanbank House, 2nd Floor, Room 20, Nairobi CBD. Old address still live across site, schema, `/llms.txt`, checkout and WhatsApp text. Plan and blocking owner decisions: PR #1 docs (release R1).

- 1.79.0 / 1.73.0 checklist: WhatsApp order with delivery shows Till panel and no shop option; pick-up shows "Pay at the shop"; message format; code saved and shown in Desk; checkout hides Cash on delivery for delivery methods and shows "Pay at the shop" only for pick-up (enable WooCommerce Local pickup if wanted). Set NURA Desk > Settings > M-Pesa payment details to `Buy Goods Till 1679269 (NURA Beauty)` so reminders and receipts match. Re-save Customizer announcement if it still mentions cash on delivery.

- Nothing in 1.73.8 to 1.77.1 / 1.71.0 to 1.72.0 was tested on the live site by the agent; owner test checklists: part pay to zero, single receipt e-mail, reused code refused, void restocks + VOID, Bill booking with deposit, WhatsApp receipt on website order (API only).
- Address (Imenti House, Moi Avenue, Nairobi CBD, 00100) and hours must match the Google Business Profile.
- Watch error log for "cron event list could not be saved" (DB connection limit); should stop after real cron + Redis.
