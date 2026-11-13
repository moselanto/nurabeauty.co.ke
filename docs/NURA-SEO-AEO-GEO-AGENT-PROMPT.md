# NURA Beauty: SEO, AEO, GEO and Structured Data Implementation Brief (v2)

> For Codex, Claude Code or any repository-aware coding agent. Run from the root of `moselanto/nurabeauty.co.ke`. Version 2.0, 1 November 2026. Companion documents: `docs/SEO-AEO-GEO-AUDIT-2026-11.md` (strategy and verified findings F1-F15) and `docs/NURA-BUSINESS-FACTS.md` (confirmed facts and open owner decisions).

## 1. Role and outcome

You are NURA Beauty's lead technical SEO engineer, structured-data architect, WooCommerce data-quality engineer and QA lead. Your outcome is a store whose every page, schema node, AI summary, checkout message and WhatsApp template describes **one verified business**, with product data complete enough to compete in organic search, Merchant Center and AI shopping answers.

Never claim that code guarantees rankings, rich results, indexing or AI recommendations. Report only what you have tested.

## 2. Non-negotiable rules

1. Read in full before editing: `AGENTS.md`, `HANDOVER.md`, `README.md`, `PRD.md`, `ARCHITECTURE.md`, `SECURITY-AUDIT.md`, both companion documents, and every SEO/schema/GEO module listed in section 5.
2. **Never break checkout.** Preserve WhatsApp ordering, M-Pesa Till and Paystack, no-COD enforcement, bookings and deposits, Lipa Mdogo Mdogo, NURA Desk (POS, plans, appointments, sourcing, staff permissions), NURA Circle, AI stylist, wig finder and virtual try-on.
3. **Verified or omitted.** Never invent a street, postcode, coordinates, map link, Place ID, opening hours, shipping fee, brand, GTIN/MPN, rating, review, certification or product claim. If a fact is not marked Confirmed in `docs/NURA-BUSINESS-FACTS.md`, omit it from schema and flag it.
4. **Do not choose between conflicting policies.** Owner decisions D1-D10 in the facts file are blocking. Build the mechanism; leave the value pending until confirmed.
5. Work on a feature branch (`feat/seo-r1-facts`, `feat/seo-r2-schema`, ...). Never commit secrets, customer data or `.env` files. Do not touch production WordPress, DNS, CDN, Business Profile, Search Console or Merchant Center without explicit owner authorisation.
6. Follow the release routine: bump theme/plugin versions, `php -l` every changed PHP file, parse-check JS, regenerate every affected `.min.js` with terser, update HANDOVER.md (session log, sections 1, 2 and 6), README and PRD where relevant.

## 3. Verified defects to fix (from the 1 November 2026 audit)

| ID | Defect | Where |
| --- | --- | --- |
| F1 | Imenti House address in 26 files (checkout gateway text, WhatsApp pay-at-shop label, AI stylist prompt, contact page, Customizer default, city pages, guides, demo content, schema, `nura-boost.js`) | run `grep -rni imenti` |
| F2 | Old coordinates -1.2837, 36.8272 and Imenti map query | `nura-schema-graph.php:234`, `class-nura-schema.php:106-107`, `page-contact.php:25` |
| F3 | Legacy `/llms.txt` handler on `init` priority 1 exits before the GEO handler; category list renders empty because `product_cat` is not registered yet | `nura-seo-plus.php:370-405` vs `nura-geo.php:135-153` |
| F4 | Same-day cut-off 5 PM vs 2 PM | demo pages vs live code |
| F5 | Returns 7 days vs 48 hours | demo pages, `class-nura-pages.php:21` |
| F6 | Guide still offers cash on delivery | `content/guides/how-to-buy-wigs-online-in-kenya-safely.html:1,5` |
| F7 | Unconfirmed opening hours hard-coded in schema | `nura-schema-graph.php:220-224`, `class-nura-schema.php:109-113` |
| F8 | Brand forced to "NURA" / "NURA Beauty" on every product, inconsistently | `nura-seo.php:157`, `seo-schema.php:124`, `nura-growth.php:241` |
| F9 | Shipping rate takes minimum across flat rate **and local pick-up**, so offers can show KSh 0 delivery | `nura-seo-plus.php:39-70,339` |
| F10 | Multiple schema emitters around Rank Math | `nura-seo-plus.php:265`, `nura-schema-graph.php`, `nura-schema-variants.php`, `class-nura-schema.php`, `seo-schema.php` |
| F11 | `/llms-full.txt` capped at 400 products; 6 h cache not purged on facts/policy change | `nura-geo.php:122,148,155` |
| F12 | Variant URLs from `$variation->get_permalink()` unproven | `nura-schema-variants.php:96,117` |
| F13 | Hard-coded Google Place ID fallback | `class-nura-community.php:67` |

## 4. Work packages and acceptance tests

### R1 Business facts and relocation (blocking; ship first)

- Create one business-facts registry (NURA Desk > Settings section backed by a single option, with a `nura_fact( $key )` helper available to theme and plugin). Fields: see section 4.1 of the audit.
- Route every customer-facing address, hours, cut-off, returns, warranty, payment and COD statement through the registry: visible templates, Customizer default, footer, contact page and map, checkout gateway title/description, WhatsApp order labels and message, AI stylist system prompt, city pages, guides (use a shortcode such as `[nura_fact key="address"]` inside HTML content), POS receipts, `/llms*.txt` and schema.
- Remove old coordinates and map query. Show a map only from an owner-provided verified link.
- Correct demo/importer pages so a fresh import cannot reintroduce stale policies.
- Saving the registry purges `nura_llms`, `nura_llms_full`, schema caches and LiteSpeed.

**Accept when:** `grep -rni imenti` returns nothing outside an intentional dated move notice; rendered homepage, contact, FAQ, delivery, returns, checkout, WhatsApp pop-up, AI stylist answer and both AI files show identical facts; checkout regression (Nairobi delivery, other county, pick-up, WhatsApp order, booking, POS sale, login) passes.

### R2 AI endpoint and schema graph

- Delete the legacy handler in `nura-seo-plus.php`; keep one route (`parse_request`) serving `/llms.txt` (text/markdown) and `/llms-full.txt`, both rendered from the registry and live WooCommerce data. Paginate the catalogue (no cap). Public, catalogue-visible products only. Accurate last-updated time.
- One schema coordinator filter on `rank_math/json_ld` with stable IDs: `/#organization` (Organization + OnlineStore), `/#studio` (Store/HealthAndBeautyBusiness, `parentOrganization` -> `/#organization`, address/geo/hours only when confirmed), `/#website`, `{url}#webpage`, `{url}#product`, `{url}#variant-{id}`. `seo-schema.php` remains the fallback when Rank Math is inactive.
- Brand: use a real brand attribute/taxonomy value; omit `brand` when unknown. Never set NURA as manufacturer of third-party products.
- Shipping: model actual delivery rates per destination from WooCommerce, excluding pick-up; omit `shippingRate` when it cannot be represented truthfully. `MerchantReturnPolicy` from the registry.
- Verify variant URLs in a browser: each must preselect the right attributes, image, price and stock; otherwise use the parent URL with explicit attribute query parameters that WooCommerce honours.
- Do not add Review/AggregateRating without genuine on-site product reviews.

**Accept when:** both endpoints return 200 with current facts and full catalogue; rendered HTML on the 10 representative pages (section 6) contains exactly one Organization, at most one LocalBusiness and one Product/ProductGroup per product page; Rich Results Test and Schema.org Validator show no errors; offer shipping and returns match checkout.

### R3 to R6

Follow the roadmap in section 8 of the audit: platform and listing updates (owner-authorised, manual checklist where credentials are not available), full catalogue data audit (CSV of missing/conflicting fields for every product and variation, no sampling), category page upgrades, intent-map content with named stylist authors and original media, and international pages only after the market readiness checklist is signed off.

## 5. Modules in scope

Theme `nura-beauty/inc/`: `nura-seo.php`, `nura-seo-content.php`, `nura-seo-fixes.php`, `nura-seo-plus.php`, `nura-schema-graph.php`, `nura-schema-variants.php`, `nura-geo.php`, `seo-schema.php`, `nura-city-pages.php`, `nura-content-hub.php`, `nura-guide-links.php`, `nura-growth.php`, `nura-boost.php`, `customizer.php`, `woocommerce-support.php`, `nura-security.php` (robots); templates `front-page.php`, `page-contact.php`, `page-about.php`, `page-book-appointment.php`, `page-ai-wig-finder.php`; `content/` and `demo/`. Plugin `nura-experience/includes/`: `class-nura-schema.php`, `class-nura-pages.php`, `class-nura-wa-order.php`, `class-nura-ai.php`, `class-nura-community.php`, `class-product-page.php`, `class-nura-desk.php`, `class-nura-desk-pos.php`, `class-nura-desk-social.php`.

## 6. Test matrix (every release)

Homepage; contact/studio; About; FAQ; delivery; returns; one category with many products; one simple product; one variable product with several live variations; one service page; one guide; `/llms.txt`; `/llms-full.txt`; `/robots.txt`; `/sitemap_index.xml`.

Automated checks to add under `tools/seo-tests/`: stale-string scan (address, coordinates, cut-off, returns, COD), endpoint status and content-type, JSON-LD parse and duplicate-entity count, sitemap URL status/canonical/indexability, catalogue field coverage. Never run real payments; use test gateways only.

## 7. Deliverables

1. Pull request per release with summary, files changed, test evidence, open owner decisions and rollback steps. Not deployed until the owner approves.
2. Updated `docs/NURA-BUSINESS-FACTS.md` (confirmed vs pending).
3. `docs/seo/catalogue-audit-YYYY-MM-DD.csv`.
4. `docs/seo/intent-map.md`.
5. `docs/RELEASE-CHECKLIST.md` (staging checks, wp-admin steps, Search Console, Bing, Merchant Center, Business Profile, rollback).
6. HANDOVER.md session log entry for every session.

## 8. Final report format

Executive summary; files changed; fixes confirmed with evidence; tests run with pass/fail; owner decisions still blocking; actions needing wp-admin or external account access; deployment and rollback steps; remaining risks and KPIs to watch.

Never write "SEO is complete", "rank #1", "AI will recommend NURA" or "Google has indexed everything" without specific verified evidence.
