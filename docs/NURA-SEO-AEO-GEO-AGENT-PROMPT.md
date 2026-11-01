# NURA Beauty: Master SEO, AEO, GEO & Structured Data Engineering Prompt

Use this prompt with Codex, Claude Code or another repository-aware coding agent from the repository root.

## Role and objective

Act as NURA Beauty's lead technical SEO engineer, WooCommerce data-quality engineer, structured-data architect, AEO/GEO strategist and QA lead.

Website: https://nurabeauty.co.ke/  
Repository: https://github.com/moselanto/nurabeauty.co.ke  
Branch: seo-aeo-geo-audit-2026-11

The objective is to make NURA discoverable, accurate and trustworthy for relevant searches and recommendations in Kenya and genuinely supported international markets. Do not promise first place, guaranteed indexing, guaranteed AI citations or recommendation. Technical changes are only one part of SEO.

## Mandatory reading

Read in full:
- AGENTS.md
- HANDOVER.md
- README.md
- PRD.md
- ARCHITECTURE.md
- SECURITY-AUDIT.md
- docs/SEO-AEO-GEO-AUDIT-2026-11.md

Then inspect all active theme/plugin code that affects:
- SEO metadata, structured data, robots, sitemaps, canonical and hreflang;
- business address and contact details;
- checkout, pickup, delivery, WhatsApp ordering, payments and bookings;
- products, variations, attributes, brands, feeds and product images;
- AI crawler handling, LLMS files, journal, guides and city pages.

Inspect actual branch/status/diff. Do not assume repository code is deployed to live WordPress: deployment is manual.

## Confirmed move

Owner-confirmed new location:
**Stanbank House, 2nd Floor, Room 20, Nairobi CBD, Kenya.**

Old location: Imenti House.

Replace old Imenti address details wherever they describe the current business or customer visit/pickup location. Do not retain old coordinates or map links. Do not invent street address, coordinates, postal code, Maps pin, Google Business Profile URL or opening hours. Ask owner/leave documented TODO for:
- exact complete address wording / road;
- verified map share URL and coordinates;
- postcode;
- business hours;
- walk-in versus appointment-only;
- pickup process.

Use the confirmed building/floor/room wording in current address copy. Do not invent additional data. Historical references to the move may remain clearly historical.

## Non-negotiable source of truth

Create or use one central, typed NURA business facts and policy configuration. It must be the single source used by:
- visible address/contact/location text;
- organisation and local store schema;
- site header/footer/contact/about/FAQ;
- pickup/payment/checkout and WhatsApp order flows;
- shipping/returns/warranty pages;
- metadata where relevant;
- /llms.txt and /llms-full.txt;
- customer-facing support snippets and catalogue summaries.

Use WooCommerce as the source of truth for product names, variations, attributes, SKU, actual brand/manufacturer, current price and stock. Use owner-approved policy data for delivery promises, returns, warranties, opening hours, payment options and store visit rules.

Never choose an unresolved conflict by majority vote or from whichever old string is repeated most. Record the conflict and require the owner's confirmation. Never fabricate price, SKU, GTIN, MPN, brand, rating, review, testimonial, stock, certification, provenance, warranty, materials, delivery claim, coordinates or business hours.

## Phase 1 — Baseline inventory before changing code

Run comprehensive repository text scans and produce machine-readable reports (CSV and Markdown) with file, line number, current value, impact, recommended change, owner-confirmation requirement and resolution status.

Search for:
- Imenti, Moi Avenue, old building identifiers, old coordinates and map URLs;
- delivery cut-offs and promises;
- return, exchange and warranty deadlines and exclusions;
- cash on delivery, M-Pesa, Till, Paystack, bank, instalments and international payments;
- all email, phone, WhatsApp, social profile and placeholder variants;
- manufacturer/brand, hair type, origin, provenance, certification, guarantee and product performance claims;
- all schema emitters and filter priorities;
- LLMS handlers and endpoint response logic;
- robots, sitemap, canonical, noindex, hreflang, product-feed and analytics configuration.

Distinguish current business facts from historical documentation/demo content. Do not blanket-replace text without context.

## Phase 2 — Reconcile customer-facing facts

Known contradictions include:
- Same-day Nairobi cut-off 5 PM vs 2 PM.
- Returns within 7 days vs 48 hours.
- No delivery COD vs old demo FAQ saying pay on delivery.
- Conflicting warranty/provenance statements.
- Potentially stale About/contact page copy.
- Appointment-only versus walk-in language after relocation.

Make a policy matrix with source locations and owner decision required. Do not silently choose. Where a fact is unresolved, omit it from new schema/AI claims and leave an explicit TODO. Do not publish contradictory answers.

After policy confirmation, update all visible pages, checkout rules, payment/pickup messages, WhatsApp flows, metadata, schema, feeds and AI files from the central source.

## Phase 3 — Location and local business entity

- Centralize address/location values.
- Update live theme and plugin output, pages, footer, FAQs, location pages, guides, pickup messages and customer communications.
- Correct map URLs only using owner-supplied verified Google Maps link.
- Remove Imenti-specific lat/long and hasMap information.
- Use one coherent online Organization/OnlineStore entity and a separate physical Store or appropriate HealthAndBeautyBusiness node.
- Connect using stable @id and parentOrganization relationships.
- Do not attach the physical shop address to the online entity in misleading ways.
- Update docs and handover with confirmed facts and outstanding verification.
- Existing Google Business Profile should be edited/moved, not duplicated. External account changes require owner/admin access and must be recorded pending until actually done.

## Phase 4 — LLMS/AEO/GEO architecture

Currently `nura-seo-plus.php` and `nura-geo.php` both handle `/llms.txt` on different hooks. Consolidate to one authoritative endpoint/router and one business-facts source.

Requirements:
- /llms.txt and /llms-full.txt each return 200 with text/markdown.
- Both publish the same business address, phone, email, hours and policy truths.
- Full catalogue export handles pagination; remove arbitrary 400 product cap.
- Only public, published and catalog-visible products are included.
- Never expose hidden products, private posts, orders, customer/account data, staff URLs, API keys or support logs.
- Product/variation price, currency, stock, SKU, actual manufacturer, attributes and canonical URLs must match WooCommerce.
- Product URLs should be absolute canonical URLs.
- Clear cache immediately on business/policy and product/catalogue updates.
- Provide appropriate content type, cache headers and safe escaping.
- LLMS is optional convenience for systems that choose to read it, not a Google ranking factor or guarantee of AI visibility.

Audit actual public robots.txt. A physical static robots.txt may supersede PHP robots filters. Verify server/WAF handling as well as WordPress source.

Allow search/discovery crawlers appropriately, including OAI-SearchBot, Googlebot, Bingbot, PerplexityBot, Claude search crawlers and others where deliberate. Distinguish search crawlers from training crawlers: do not infer that allowing one means allowing the other. Respect the owner's policy.

Build a repeatable GEO benchmark with exact user-intent prompts across ChatGPT Search, Gemini/Google AI experiences, Perplexity and Bing/Copilot. Record date, platform, prompt, NURA mention, cited URLs, competitors and result click path. This is directional sampling, not a complete ranking metric.

## Phase 5 — Schema architecture

Rank Math owns the main graph when enabled. Avoid additional competing Organization/Product/WebSite nodes. Use one thoroughly tested fallback only when SEO plugin absent.

Inspect actual rendered page HTML, not only PHP schema arrays.

Appropriate types when supported by visible facts:
- Organization / OnlineStore
- physical Store / HealthAndBeautyBusiness
- WebSite / WebPage
- BreadcrumbList
- Product / ProductGroup / variant Product
- Offer / AggregateOffer
- OfferShippingDetails / MerchantReturnPolicy
- CollectionPage / ItemList
- Article / BlogPosting
- Service / OfferCatalog
- FAQPage when questions and answers are visibly displayed.

Requirements:
- Stable @id references and connected graph.
- One business identity, one physical location entity.
- No duplicate nodes or stale values.
- Prices, priceCurrency, availability, product URL, image, SKU and actual brand match displayed product and live WooCommerce values.
- Brand/manufacturer: distinguish NURA retailer identity from true third-party manufacturer. Do not say NURA made every wig.
- Product variants: unique accurate identifiers, product group IDs, variation URLs, attributes, images, price and stock. Only use ProductGroup where meaningful and compliant with current Google requirements.
- Use AggregateRating/Review only from real visible, eligible review data. Never fabricate.
- Use MerchantReturnPolicy and shipping details only when policies/rates are confirmed and match visible terms.
- Use Service only for actually offered visible services.
- FAQPage only for visible accurate FAQ. Do not claim FAQ schema guarantees rich results.
- Do not add unsupported properties to force rich snippets.
- Validate with Schema.org Validator and Google Rich Results Test on representative page types.

## Phase 6 — Product and category quality

Audit every published product and variation. Generate an exception report; do not silently invent missing values.

Fields to review:
- title and unique useful description;
- correct product category and attributes;
- hair type (human / blend / heat-resistant fibre);
- texture, length, density, lace type, colour and cap fit where applicable;
- actual maker/brand;
- SKU and real GTIN/MPN only if actually assigned;
- current price and sale price;
- stock and variation availability;
- canonical URL;
- main, gallery and variation-specific image;
- accurate alt text;
- accurate schema and Merchant feed.
Flag conflicting hair-type claims, e.g. product attribute says synthetic/blend while copy says 100% human hair. Do not auto-resolve without evidence.
Ensure brand data is not wrongly set to NURA for all products.
Improve titles and descriptions for usefulness and search intent, not keyword stuffing.
Create original product photos, exact variation images and helpful content.

## Phase 7 — Technical SEO

Verify live and repository:
- robots.txt and static override;
- XML sitemap currentness, response and inclusion;
- canonical and meta robots;
- indexability of products, categories, guides, brand pages and genuine location pages;
- noindex for internal search and faceted/filter duplicate URLs;
- canonical redirects, www/non-www, HTTP/HTTPS, trailing slash;
- 404/5xx and redirect chains;
- orphan pages and internal linking;
- pagination and variation URLs;
- product schema in initial HTML;
- images and image sitemap;
- mobile usability and real Core Web Vitals.

Core Web Vitals recommended thresholds at 75th percentile: LCP <= 2.5 s, INP <= 200 ms, CLS <= 0.1. Measure actual field data where available and lab performance as diagnostic only.

Never claim that a sitemap submission equals indexing. Handover's 97 URLs on 17 October 2026 is historical only; verify current Search Console.

## Phase 8 — AEO content and topical authority

Preserve useful existing buying/care guides and improve them with NURA stylist expertise, first-hand product experience, actual photos, accurate examples and relevant links.

Clusters:
- human hair vs blends vs fibre;
- frontal / closure / lace types;
- bob, curly, straight, kinky and textures;
- glueless, headband and beginner wigs;
- price and budget guides;
- face shape, cap size and density;
- washing, conditioning, styling, storage and revamp;
- bridal and occasion;
- Nairobi shop / fitting / installation;
- delivery/payment/returns;
- wholesale and salon supply.

Do not create mass-produced generic AI pages, duplicate city pages or thin pages targeting every county. Use genuine authors/reviewers and evidence only where known. Cite reliable sources for technical hair care claims. Keep advice accessible and helpful.

## Phase 9 — Merchant Center and multi-channel feeds

Audit Merchant Center account diagnostics and feed:
- product identifiers, brand and GTIN/MPN accuracy;
- title and description quality;
- image requirements;
- current price, sale price and availability parity;
- shipping and returns settings;
- feed destinations and landing-page accessibility;
- item disapprovals and account-level warnings.

Maintain one WooCommerce product source feeding Google and Meta catalogues. Do not assume Product schema alone replaces Merchant Center feed. Changes in external Google/Meta accounts require owner access.

## Phase 10 — Local and international authority

Update verified existing Google Business Profile. Do not create a duplicate for the new address. Update legitimate directory citations and official social profiles. Build review acquisition transparently; no fabricated reviews or incentives violating platform policies.

Expand by market only where NURA can really fulfil:
- first Kenya;
- then East African countries with supported logistics/payment;
- then UK/USA/UAE and diaspora markets only where policies, delivery, taxes/duties, currency, returns and service are clear.

Do not add hreflang/country pages until locale architecture genuinely exists. Avoid doorway pages.

## Phase 11 — Baseline and reporting

Do not claim actual rankings or business impact without platform data. Request or use authorized exports:
- Search Console: query/page/country/device clicks, impressions, CTR, average positions, indexing and enhancements.
- GA4: organic landing sessions, product views, add-to-cart, checkout, purchases, revenue and ecommerce conversion.
- Merchant Center: item/account issues, approved/disapproved, price/stock mismatch and free listing data.
- Bing Webmaster Tools: index coverage, crawl errors, search queries.
- Google Business Profile: views, actions, calls, direction requests, reviews and current location.
- AI benchmark: date/platform/query/answer/citations/mentions/competitors.
Build a monthly dashboard and annotate releases. Separate measured results from estimates.

## Phase 12 — Release and handover

- Work on branch `seo-aeo-geo-audit-2026-11`, not direct main deployment.
- Preserve user experience and all existing features.
- Do not break checkout, M-Pesa/Paystack handoff, WhatsApp orders, pickup, delivery, bookings, loyalty, POS, My Account or staff permissions.
- Use backward-compatible code and safe cache invalidation.
- PHP lint all changed PHP files.
- JavaScript syntax/build; regenerate .min.js twins when source edited.
- Run available tests/static scans.
- Bump theme/plugin versions when code changes, update README/PRD/HANDOVER and session log.
- Make reviewable commits and a pull request with changed-file summary, tests, screenshots/results, unresolved owner decisions, external admin steps and rollback plan.
- Never deploy without explicit approval and valid admin access.
- Record actual status: code written, tests passed, PR created, installed on live, validated by external platform. Do not conflate these.

## Mandatory release acceptance criteria

- No current customer-facing content/schema/maps/AI endpoint says Imenti House is current.
- No old coordinates/map links remain in current business facts.
- Stanbank address is consistent and no unverified details are invented.
- Delivery, returns, warranty and payments align with owner-approved policy and checkout behavior.
- LLMS route has no conflict, catalogue pagination is complete and private data is excluded.
- Schema graph is coherent, duplicate-free and validated using rendered HTML.
- Product and variation data matches WooCommerce and Merchant Center.
- Important pages are crawlable/indexable with deliberate canonical and sitemap handling.
- Checkout, WhatsApp ordering, payment handoff, pickup, bookings, POS and staff permissions pass regression.
- GSC/GA4/Merchant/GBP/Bing tasks are marked pending until independently verified.
- Monthly SEO/AEO/GEO metrics are baselined.

## Final report format

Return:
1. Executive summary.
2. Files changed and why.
3. All confirmed corrections.
4. Tests actually run and pass/fail evidence.
5. Owner decisions still pending.
6. External account work requiring owner access.
7. Deployment instructions and rollback steps.
8. Search/AI measurement baseline status.
9. Remaining risks.

Never say "SEO complete", "rank number one", "Google indexed everything" or "AI will recommend NURA" without evidence.
