# NURA Beauty — SEO, AEO, GEO & Structured Data Audit (1 November 2026)

## Executive summary

Repository: https://github.com/moselanto/nurabeauty.co.ke  
Website: https://nurabeauty.co.ke/  
Platform: WordPress + WooCommerce, custom NURA theme, NURA Experience plugin, Rank Math.

NURA has a substantial custom discovery foundation. The repository contains custom SEO title/description logic, category introductions, product/offer enrichment, product-variant schema, an Organization/OnlineStore plus physical studio graph, FAQ/AEO content, AI crawler rules, `/llms.txt` and `/llms-full.txt`, buying guides, city pages and a custom catalogue.

The most important issue found is not absence of SEO code. It is inconsistent business and product facts. The owner confirms the physical store has moved from Imenti House to Stanbank House, 2nd Floor, Room 20, Nairobi CBD. The live site, schema defaults, AI knowledge files, maps and some transaction copy still refer to Imenti House. Public pages and repository content also contain contradictory delivery cut-offs, returns windows and payment/COD claims.

This is a repository and public-site audit. It is not an authenticated crawl and did not include access to Search Console, GA4, Merchant Center, Bing Webmaster Tools or Google Business Profile. Therefore no verified ranking positions, traffic trends, total index coverage or AI citation rates are claimed. No fixes have been deployed in WordPress.

## 1. Existing implementation found

Relevant code files:

- `nura-beauty/inc/nura-seo.php`: homepage Kenya wig guide and FAQs, WooCommerce/Rank Math product Offer enrichment, image alt-text fallbacks.
- `nura-beauty/inc/nura-seo-content.php`: category archive intros and collection/list schema.
- `nura-beauty/inc/nura-seo-plus.php`: dynamic product/category titles and descriptions, category facts, shipping/returns policy enrichment, en-KE hints and an LLMS endpoint.
- `nura-beauty/inc/nura-seo-fixes.php`: contact details in Rank Math schema, homepage Article/Person cleanup, filtered URL noindex rules, hair-type claim conflict warnings.
- `nura-beauty/inc/nura-schema-graph.php`: Rank Math graph enrichment; connected Organization/OnlineStore and studio node; service and CollectionPage additions.
- `nura-beauty/inc/nura-schema-variants.php`: custom ProductGroup and variation Product schema.
- `nura-beauty/inc/nura-geo.php`: GEO/AEO, `/llms.txt`, `/llms-full.txt`, catalogue/category facts and FAQ augmentation.
- `nura-beauty/inc/seo-schema.php`: fallback standalone schema when no SEO plugin is active.
- `nura-experience/includes/class-nura-schema.php`: supplementary/fallback schema, intentionally deferring to Rank Math/Yoast/SEOPress.
- `nura-beauty/inc/nura-guide-links.php`: category-to-guide links.
- `nura-beauty/functions.php`: loads the relevant theme discovery modules.
- `nura-beauty/content/guides/`: topical buying/care guides and guide metadata.
- `nura-beauty/inc/nura-city-pages.php` and `nura-content-hub.php`: additional location/content hubs.

The architecture and handover document Rank Math as the main graph owner when enabled. Preserve this principle. Do not add a second SEO plugin or duplicate schema system.

## 2. Critical findings

### P0 — physical relocation

Current business location provided by owner:

**Stanbank House, 2nd Floor, Room 20, Nairobi CBD, Kenya.**

Old Imenti address remains in:
- Live homepage, location/FAQ/footer and studio text.
- Contact template defaults and map query in `nura-beauty/page-contact.php`.
- Homepage FAQ in `nura-beauty/inc/nura-seo.php`.
- Business description, schema default address and old coordinate/Map URL logic in `nura-beauty/inc/nura-schema-graph.php`.
- `nura-beauty/inc/nura-seo-plus.php` / `nura-geo.php` AI facts and FAQ extension.
- `nura-experience/includes/class-nura-schema.php` fallback graph, including Imenti map and coordinates.
- Guides such as `wig-installation-nairobi-what-to-expect`.
- Checkout, pickup and WhatsApp copy in relevant plugin modules.
- README, PRD, architecture and handover documentation.

Do not reuse Imenti coordinates. Do not invent Stanbank coordinates, street number, map URL, postal code or Google Business Profile URL. Owner must supply a verified Maps share link/pin and confirm exact address wording.

Centralise address and location facts. The physical studio must be a distinct node related to the online organisation/store using stable `@id` references. Do not make the online-only business inherit a studio address incorrectly.

### P0 — factual/policy contradictions

Public/demo content and code contain incompatible claims:
- Same-day Nairobi delivery cut-off: 5:00 PM vs 2:00 PM.
- Returns deadline: 7 days vs 48 hours.
- No delivery COD vs old demo FAQ claiming pay-on-delivery.
- Warranty and provenance/certification claims require evidence.
- About-page sample/demo content contains placeholder and potentially obsolete contacts and claims.
- Service/visit descriptions may say appointment-only despite the move to a physical shop.

Never choose the most frequent value as truth. Record conflicts and require owner-approved policies. Create one centrally managed business/policy source for visible pages, checkout, WhatsApp, metadata, schema and AI files. Do not deploy invented or unconfirmed policies.

### P1 — duplicated LLMS endpoints

Both `nura-seo-plus.php` and `nura-geo.php` register `/llms.txt` handlers using different hooks and content. The older handler may terminate before the newer handler. Consolidate into one route and one rendering source. Ensure:
- `/llms.txt` returns HTTP 200 and text/markdown.
- `/llms-full.txt` returns HTTP 200 and text/markdown.
- Both use the same current business facts and approved policies.
- Catalogue is paginated, not silently capped at 400 products.
- Only published/catalog-visible public products are listed.
- Hidden products, customer/order/account/support data and private URLs are never exposed.
- Product URLs are canonical.
- Price, stock and variation data are current.
- Policy/address changes invalidate caches immediately.
- Last-updated text is accurate.
- Treat LLMS files as optional machine-readable convenience, not a Google ranking factor or guarantee of AI recommendation.

### P1 — structured-data graph

Rank Math is the main schema graph owner. Audit all `rank_math/json_ld` filters, plugin additions and fallback emitters. Inspect final rendered HTML JSON-LD on homepage, product, variable product, category, service, contact, FAQ and article pages.

Requirements:
- One coherent Organisation/OnlineStore entity, one physical Studio/Store entity and stable IDs/relationships.
- No duplicate competing Organization/Product/WebSite nodes.
- Product/ProductGroup/variant relationships accurate.
- Offer price, currency, availability, URL, seller, shipping and return policy match visible content and WooCommerce state.
- Product manufacturer/brand must be real. NURA is retailer/trading brand; do not claim it manufactures every third-party wig.
- Do not invent SKU, GTIN, MPN, ratings, reviews, hours, coordinates, guarantees, certifications or product materials.
- ProductGroup schema only when actual variation data supports it and Google requirements are met.
- FAQPage only when the FAQ is visible and factual. Do not sell FAQ schema as a reliable rich-result/ranking lever.
- Use Article/BlogPosting only on genuine editorial pages, with real authors/reviewers where known.
- Service/OfferCatalog only for published services with current prices and accurate descriptions.
- Use MerchantReturnPolicy and OfferShippingDetails only if exact policies/rates are confirmed and match visible terms.

### P1 — product and shopping data

Audit every published product/variation. Report completeness for:
- accurate title and unique useful description;
- actual manufacturer/brand;
- hair type (human hair, human-hair blend, heat-resistant fibre);
- texture, length, density, lace construction, colour and cap/fit attributes where applicable;
- unique SKU and real GTIN/MPN only where assigned;
- price/currency and stock;
- high-quality product and variation-specific images;
- useful descriptive alt text;
- category assignment and internal links;
- valid metadata and schema.
Flag contradictions such as blend/synthetic attributes while copy claims 100% human hair. Never auto-rewrite product facts based on assumptions.

Audit Merchant Center separately: feed completeness, title quality, identifiers, price/stock parity, shipping, return settings, image issues, destination URLs, disapprovals and account issues. Do not assume schema replaces a shopping feed.

### P1 — technical SEO and crawling

Verify live:
- current `robots.txt` (static file may override dynamic PHP filter);
- XML sitemap contents, response and last-modified;
- canonical URLs and meta robots;
- indexability of important products/categories/guides;
- noindex behaviour for filters/sort/search URLs;
- redirects, chains, 404s, 5xx and mixed content;
- pagination and product variation URL crawl paths;
- orphan pages and internal links;
- initial HTML availability of important text and JSON-LD;
- sitemap vs published catalogue reconciliation;
- mobile usability and Core Web Vitals.

The project handover records 97 sitemap URLs submitted on 17 Oct 2026; this is historic evidence, not current indexing state.

Recommended current Core Web Vitals targets: LCP <= 2.5s, INP <= 200ms and CLS <= 0.1 at the 75th percentile. Verify with field data and lab tests; do not infer performance from one run.

### P1 — AEO and topical authority

Existing content has a useful start: human hair vs blend vs fibre, wig longevity/care, frontal vs closure, glueless/headband, cap sizing, face-shape advice, bridal and Nairobi installation. Improve with original stylist expertise, photos, demonstrations, real product examples, accurate prices, first-hand product experience, genuine reviews and sources. Avoid scaled generic AI pages and keyword stuffing.

Build useful clusters:
- materials and wig types;
- lace/closure construction;
- styling, textures and lengths;
- beginner/glueless/headband;
- budgets and pricing;
- cap size, density and face shape;
- washing, conditioning, storage and revamp;
- local shop, fittings and installation;
- delivery/payment/returns;
- wholesale and salon supply.

Every guide should answer intent fully, link to relevant products/categories, include original imagery and be reviewed for factual claims.

### P1 — GEO and AI search

Crawler rules presently list OAI-SearchBot, ChatGPT-User and other AI search crawlers as allowed in the dynamic robots output. Verify actual public robots and server/WAF handling. Distinguish search crawlers from training crawlers and choose policies deliberately. OpenAI documents OAI-SearchBot for ChatGPT search discovery; access is not a guarantee that NURA will be cited.

Track ChatGPT Search, Google AI features, Perplexity and Bing/Copilot with a fixed prompt sample. Save exact prompt, date, model/product result, citations, NURA mention, competitor mentions and click-through URL. Treat as directional manual monitoring, not comprehensive share-of-voice.

### P2 — local authority and international expansion

Update existing Google Business Profile, not create a duplicate listing. Confirm category, address, pin, phone, hours, photos, website, products/services and reviews. Update genuine business directories and social profiles to consistent NAP.

Expand first in Kenya, then East Africa where fulfilment is supported. International country pages should only be launched where NURA can genuinely support delivery, payment, currency, returns, duties/tax clarity and customer support. Avoid thin doorway pages and mechanically cloned location pages.

## 3. Owner decisions required before production truth changes

1. Exact public address wording beyond Stanbank House, 2nd Floor, Room 20, Nairobi CBD.
2. Verified Google Maps share URL/pin/coordinates.
3. Postal code if applicable.
4. Opening days and hours.
5. Walk-in vs appointment-only and collection procedure.
6. Same-day Nairobi order cut-off and delivery promise.
7. National/international shipping zones, delivery times, fees and free-shipping thresholds.
8. Returns/exchange deadline and conditions (48 hours or 7 days must be resolved).
9. Warranty terms and exactly which product types qualify.
10. Whether any product has provenance/certificates and supporting evidence.
11. Confirm COD availability/absence and current payment methods.
12. International target countries actually served and terms.
13. Correct brands/manufacturers per catalogue.
14. Official phone, WhatsApp, email and profile URLs.
15. Read-only exports/access for GSC, GA4, Merchant Center, Bing Webmaster and GBP.

Until confirmed, keep unresolved facts out of generated claims/schema or visibly mark them for owner approval. The confirmed Stanbank building/floor/room string can replace Imenti in current location copy without inventing additional details.

## 4. Implementation sequence

1. Branch, backup and baseline crawl. Keep checkout/production safe.
2. Inventory all old addresses, map coordinates, delivery/returns/COD/warranty/brand claims. Produce CSV with path, line, current value, risk, recommended disposition and owner-confirmation flag.
3. Create one business facts/policy source and migrate consumers.
4. Update live-content templates, FAQ, contact/about, delivery/returns, installation guides, pickup/WhatsApp/checkout and all AI/schema outputs.
5. Consolidate LLMS routes and implement safe paginated public catalogue output.
6. Consolidate schema graph and remove duplication; test final HTML.
7. Run product and variation audit; produce exceptions CSV, do not silently invent/fix.
8. Fix technical crawl/index issues and sitemap.
9. Improve highest-value AEO pages and category/product content with original expertise.
10. Update external business listings and Merchant Center with owner/admin access.
11. Add repeatable baseline and reporting.
12. Update README, PRD, architecture, HANDOVER and session log. Bump theme/plugin versions as needed.
13. Deliver reviewable pull request on a branch, not direct production deployment.

## 5. Required QA and release gates

Before release:
- `php -l` every changed PHP file.
- JavaScript syntax/build and regenerate served `.min.js` twin files where applicable.
- Run tests/static scans and full text audit for obsolete Imenti address and stale policy variants.
- Inspect public endpoint responses for robots, sitemap, LLMS.
- Validate JSON-LD in rendered page HTML using Schema.org Validator and Google Rich Results Test on representative simple/variable product, home, category, FAQ, contact, service and article pages.
- Compare schema prices/stock/brands/policies with visible content and WooCommerce.
- Test checkout, M-Pesa/card payment handoff, WhatsApp ordering, studio pickup, delivery quote, bookings, My Account, POS and staff permissions.
- Mobile test and measure Core Web Vitals.
- Test cache invalidation.
- Review privacy and prevent catalogue endpoint exposure of hidden/private information.

No task is complete until evidence is recorded. Code written is not the same as code deployed; code deployed is not the same as indexed; indexed is not the same as ranking or AI citation.

## 6. Tracking baseline

Use authorized exports:
- GSC: query/page/country/device performance, indexing, sitemap, enhancements, manual actions.
- GA4: organic sessions, landing pages, product views, add-to-cart, checkout, purchases/revenue and conversion.
- Merchant Center: disapprovals, account issues, price/stock/shipping mismatch and free listings.
- Bing Webmaster: indexing, errors and query performance.
- GBP: profile status, address/pin, views/actions/calls/directions/reviews.
- AI benchmark: fixed query set, date, assistant, NURA mention, citation/source, competitor reference and linked landing page.

Do not claim rank 1, full indexation, SEO completion or guaranteed AI recommendation.

## 7. Acceptance criteria

- No customer-facing live content, checkout, maps, schema or AI endpoint claims Imenti as current location.
- Stanbank building/floor/room is consistent; no fabricated geo/map/hours.
- Delivery/payment/COD/returns/warranty facts match owner-approved policy and transactional behaviour.
- One authoritative business/policy source powers website, schema and AI files.
- One LLMS route per endpoint, complete paginated catalogue, no private data, immediate cache invalidation.
- No duplicate competing schema nodes; graph validated on actual page HTML.
- Product and variant schema matches the current product state.
- Product feed aligns with WooCommerce and merchant settings.
- Sitemap/indexability/internal linking and redirects verified.
- Checkout and business workflows regression-tested.
- All external-account tasks clearly listed as pending until verified.
- Reports distinguish technical implementation from actual rankings, traffic and AI citations.

## 8. External documentation

- Google AI features and website guidance: https://developers.google.com/search/docs/appearance/ai-features
- Google Search generative AI guidance: https://developers.google.com/search/docs/fundamentals/ai-optimization-guide
- Google product structured data: https://developers.google.com/search/docs/appearance/structured-data/product
- Google product variants: https://developers.google.com/search/docs/appearance/structured-data/product-variants
- Google LocalBusiness structured data: https://developers.google.com/search/docs/appearance/structured-data/local-business
- OpenAI bots and crawlers: https://developers.openai.com/api/docs/bots
- Core Web Vitals: https://web.dev/articles/vitals

Google's current guidance does not require special AI schema or an LLMS file for inclusion in AI features. Useful content, crawlability, indexing eligibility, internal links and accurate structured data remain essential.
