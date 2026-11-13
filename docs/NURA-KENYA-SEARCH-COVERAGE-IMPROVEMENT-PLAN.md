# NURA Beauty — Kenya Search Demand, Product Discovery & SEO/AEO/GEO Improvement Plan

**Research snapshot:** 14 November 2026  
**Repository:** https://github.com/moselanto/nurabeauty.co.ke  
**Live website:** https://nurabeauty.co.ke/  
**Working branch:** `seo-r5-search-coverage-2026-11`

## Executive conclusion

NURA already has a substantial discovery system. The current main branch and 14 November handover confirm existing work including:
- /wig-questions/ hub, 12 customer intents and live picks;
- English, Kiswahili and Sheng matching;
- NURA Stylist with live WooCommerce recommendations;
- Wig Finder and quiz;
- 19+ buying/care guides, wig price page and city delivery pages;
- /llms.txt and /llms-full.txt;
- SEO metadata, Product/Offer/variant schema, business/location/service schema;
- Merchant Center progress (roadmap records 105 approved, verify current count);
- product review request workflow and recent care-product catalogue work;
- roadmap R3/R4 noted as shipped, with R5 analytics and inquiry measurement next.

**Do not rebuild these features.** Improve the existing modules and use evidence from live catalogues and analytics.

## Research approach and limitations

Reviewed:
- Current GitHub main branch tree and current docs/AGENTS.md/HANDOVER.md/SEO-AEO-GEO-ROADMAP.md.
- Current NURA homepage, /wig-questions/, /wig-prices-kenya/, /best-wigs-in-kenya-buying-guide/, /best-human-hair-wigs-kenya/ and /where-to-buy-human-hair-wigs-nairobi/.
- Public competitor catalogues: The Nairobi Wigs and Angels Wigs Kenya.
- Google Search Central AI features, AI optimization, product merchant listing, product variants and review structured-data guidance.
- Kenya Google Trends public dashboard.

**No Google Keyword Planner search-volume dataset, Search Console export, GA4 data, Merchant Center export or paid rank tracker was available.** Do not present keyword ideas as verified volume or current positions. Google's Trending Now dashboard did not show wig-specific trend data in retrieved output. Validate demand with actual GSC query impressions and Google Ads Keyword Planner data for Kenya/Nairobi, plus internal site search and WhatsApp inquiry logs.

## 1. Current Kenya commercial search intent

Treat these as query themes to validate and map, not numeric demand claims.

### Core transactional head terms
- wigs in Kenya
- buy wigs online Kenya
- wig shop Nairobi
- wig prices in Kenya
- human hair wigs Kenya
- affordable wigs Kenya
- wigs Nairobi CBD
- best wig shop Nairobi
- wig delivery Kenya
- online wig store Kenya

### Hair type and material
- 100% human hair wigs Kenya
- pure human hair wigs Kenya
- semi human wigs Kenya
- human hair blend wigs Kenya
- synthetic wigs Kenya
- heat resistant fibre wigs Kenya
- affordable human hair wig Kenya
- how to tell genuine human hair wig

Use terminology consistently with real SKU/product attributes. NURA currently has product taxonomy labels Pure Human, Semi Human and Fibre/heat-resistant fibre; create synonyms in content/FAQ only where definitions match. Do not classify a product without confirmed evidence.

### Construction / lace / application
- lace front wigs Kenya
- 13x4 frontal wigs Kenya
- 13x6 frontal wigs Kenya
- 4x4 closure wigs Kenya
- 5x5 closure wigs Kenya
- 6x6 closure wigs Kenya
- HD lace wigs Kenya
- transparent lace wigs Kenya
- glueless wigs Kenya
- wear and go wigs Kenya
- headband wigs Kenya
- pre plucked wigs
- beginner friendly wigs

Only build an indexable collection where sufficient matching products exist. Do not manufacture inventory taxonomy for styles NURA does not sell.

### Style and texture
- bob wigs Kenya / Nairobi
- pixie wigs Kenya
- curly wigs Kenya
- jerry curl wigs Kenya
- water curl wigs Kenya
- body wave wigs Kenya
- deep wave wigs Kenya
- kinky straight / yaki wigs Kenya
- bone straight wigs Kenya
- afro kinky wigs Kenya
- braided wigs Kenya
- coloured wigs Kenya
- ginger / burgundy / piano colour wigs Kenya

Map to real attributes and categories, and include common spelling and language variants naturally.

### Price and budget modifiers
- wigs under KSh 3,000
- wigs under KSh 5,000 Kenya
- wigs KSh 3,000 to 7,000
- wigs KSh 7,000 to 15,000
- premium wigs Kenya
- cheap wigs Nairobi
- best wig for budget
- human hair wig price Kenya
- wig on instalments Kenya / Lipa Mdogo Mdogo wig

Use dynamic prices from the eligible wig catalogue only, never include wig-care products in wig prices or product picks. Display last updated and define whether prices are variation starting prices.

### Care products and accessories
Audit all actual catalogue product brands and product types. Search themes may include:
- wig shampoo Kenya
- wig conditioner Kenya
- wig care products Kenya
- wig detangling brush
- wig glue Kenya
- lace glue / lace adhesive Kenya
- lace glue remover Kenya
- lace tint spray Kenya
- melting spray for wigs Kenya
- wig repair serum Kenya
- curl mousse / curl keeper Kenya
- hair wax stick Kenya
- hair extensions / bundles Kenya (only if present)
- wig stand / wig cap / bonnet (only if present)

Every care product needs accurate brand, size, ingredients/materials where supplied, compatible use, directions, cautions, price, stock and shipping. Do not misrepresent third-party manufacturers as NURA.

### Service/local searches
- wig installation Nairobi
- wig installation Nairobi CBD
- wig revamp Nairobi
- wig repair Nairobi
- wig styling Nairobi
- wig colouring Nairobi
- wig consultation Nairobi
- wig shop near Moi Avenue
- wig shop Nairobi CBD
- where to buy wigs Nairobi
- wig fitting Nairobi

Address and directions must be consistent with Stanbank House, 2nd Floor, Room 20.

### Conversational / AEO questions
- Which wig is best for beginners in Kenya?
- How much should I pay for a human hair wig in Kenya?
- Is semi-human the same as human hair?
- What is the difference between 4x4 and 13x4?
- Is HD lace worth the extra cost?
- Can I wear a wig without glue?
- How do I choose wig length and density?
- How do I wash a human hair wig?
- Which wig can I wear every day?
- Can I see a wig before paying?
- Can I pay with M-Pesa?
- Can I pay on delivery in Nairobi CBD?
- How does Lipa Mdogo Mdogo work?
- Can I return a wig?
- Do you deliver to Mombasa/Kisumu/Eldoret/Nakuru?
- Can I buy wig care products with my wig?

Existing /wig-questions/ and knowledge base should own these answers. Expand coverage based on GSC/WhatsApp search terms, not an arbitrary 120-question count.

## 2. Current live-page findings requiring immediate improvement

### P0: Product-type/category pollution in wig prices and buying-guide recommendations

Retrieved live /best-wigs-in-kenya-buying-guide/ showed a live price table with a KSh 300 “100% human hair” item that is actually a Luodais wig-care serum, and the “Best picks right now” includes serum and conditioner as wig products. The main wig price page showed 25 wigs as of 6 November 2026 and correctly segmented categories, but prices and counts vary across pages and dates.

Likely root cause: the product selector used by price tables/picks relies on a generic hair-type attribute or weak name/category test and includes wig-care products.

Fix:
- Create one explicit product eligibility function, e.g. `nura_is_wig_product(WC_Product $p)`.
- Include products only if they belong to an approved wig product category/type and not any wig-care, beauty, accessories, tools or supplies category.
- Derive hair-type groups only for eligible wig products.
- Exclude draft, private, hidden, non-purchasable and non-catalog products from public picks and live price ranges.
- Product names alone are not sufficient; use category/taxonomy plus explicit product-kind attribute. Report ambiguous SKUs for admin classification.
- Use the same function in price pages, buying guides, homepage picks, city recommendations, LLMS catalogue sections, AI stylist retrieval, ItemLists and product count summaries.
- Ensure live counts and date updated are generated by the same query.
- Add unit tests and product fixture cases: human hair serum, conditioner, lace tint, glue, care brush, human hair wig, fibre wig, variable wig, hidden wig, out-of-stock wig.
- A wig can have out-of-stock status only as configured by owner; do not override stock management rules.

### P0: Pricing facts conflict across pages

Live output observed different current dates/counts and product ranges:
- buying guide: price table included non-wig products and 5 "human hair" styles;
- price page: 25 wigs, 4 human-hair products, 2 blend products, 19 fibre products, prices as of 6 Nov;
- /best-human-hair-wigs-kenya/ snippet reports human hair from KSh 3,500 to 15,000;
- another location page mentions human hair KSh 7,500 to 17,300 and fibre from KSh 1,500;
- category page snippets have other starting prices.
This may be date drift, different query logic or a mix of hair-care products. Reconcile against WooCommerce and show only actual wig products. All pages must use one price aggregation service and a consistent freshness marker.

Do not hard-code range values or count totals in copy/schema/LLMS. Use server-side current data and update cache when price/stock/category/visibility changes.

### P1: Category coverage vs product depth

There are many existing category pages for style, construction and material. Audit each category:
- product count (eligible purchasable product count);
- content quality and uniqueness;
- category attribute consistency;
- internal links and canonical;
- overlap with other categories;
- indexing and impressions;
- whether a dedicated page is justified.

Mark low-inventory pages noindex or merge/redirect only after evidence and user experience review. Do not automatically create thin category pages for every long-tail term.

### P1: Product detail coverage

NURA must surface exact products when someone searches for any SKU's name, brand, material, size, length, colour, texture, model or care use.

Build a data quality matrix for every published product/variation:
- one accurate canonical name with important differentiators;
- a concise first 150 words answering what it is, material/brand, size, shade, compatibility and use;
- accurate short and full description;
- confirmed manufacturer/brand;
- product kind: wig / hair care / accessory / extension / service;
- relevant product categories;
- attributes: hair type, texture, colour, length, density, lace/construction, size, volume/weight, ingredients/materials when relevant;
- accurate SKU/variation SKU;
- real GTIN/MPN only if assigned;
- current price, variation price and stock status;
- image of actual product and variation;
- alt text describing actual visible product, not keyword stuffing;
- FAQs relevant to that SKU;
- related accessories/care products and genuine complementary products;
- schema and feed parity.
Flag missing/conflicting values; do not infer or invent.

## 3. Competitive search-results observations

Two public Kenyan competitor catalogues reviewed:
- The Nairobi Wigs shop had 65 listed results, visible categories for bob, braided, bundles, closure, coloured, curly, frontal, human hair, lace, semi-human and straight wigs, plus price and length filters. Some product cards exposed specific product copy and discounts.
- Angels Wigs Kenya showed a shop catalogue with prominent category filters and product cards containing material, length, colour, sale price, best-seller labels and WhatsApp actions.

Implications:
- NURA's current budget navigation and product filters are good foundations.
- Improve structured product attributes and filter names so users can browse by length, colour, hair type, lace type and texture without creating crawl traps.
- Product card should expose type, price, colour/length where meaningful, ratings only when authentic, and availability consistently.
- Build honest differentiators: a physical Stanbank studio, walk-in/appointment option (current approved source), clear real hair-type labelling, M-Pesa, transparent prices, fitting/installation, local fulfilment and returns terms.
- Avoid competitor bashing or unsupported claims like "best quality in Kenya".
- Market comparison articles must explain method, date and public price source, avoid claiming every other seller is fraudulent, and be reviewed by humans.

## 4. Product and category relevance engine

Create a common domain service used by all recommendation surfaces:
- shop search;
- category recommendations;
- homepage feature picks;
- city delivery page picks;
- /wig-questions/ live picks;
- AI Stylist;
- Wig Finder;
- article "shop this look" blocks;
- LLMS catalogue.
It should return only products matching the page intent and current eligibility. Provide optional filters by product kind, hair type, style/texture, construction, colour, length, price band, status, visibility and stock rule.

For each content group, use the exact applicable category/product set. Never show conditioner as human hair wig or glue as wig. If no matching products exist, say no matching current product and link to a broader honest option.

Recommendations must explain why a product fits the user's selected intent, not simply say "best". E.g. "headband, no lace or glue; suitable for a beginner who prefers quick application" only if product attributes support it.

## 5. Analytics and R5 measurement

Roadmap says R5 is next. Ship a privacy-conscious event model:
- organic landing page;
- internal site search query (sanitize and avoid PII);
- product view;
- category view;
- view_item_list and select_item;
- add_to_cart;
- begin_checkout;
- purchase and value/currency;
- WhatsApp CTA click (header/footer/product/FAQ/article/city pages);
- phone click;
- booking start/complete;
- AI Stylist started/recommendation click;
- Wig Finder started/completed and product click;
- FAQ expand (optional, do not overtrack);
- email click.
Use GA4 recommended ecommerce event format and consistent event parameters. Do not send name, phone, email, order notes or other PII to GA4. Consent/privacy policy must be followed.

UTM/source attribution:
- WhatsApp links can carry non-PII content IDs/source placement.
- Record content_id, placement, product_id (if appropriate), page path, event ID and timestamp; do not include customer identifiers.
- Track external referrals from ChatGPT, Perplexity, Gemini, Claude, Copilot when visible in referrer data; preserve raw source and normalise to a channel grouping. Direct/hidden referrers make AI referrals underreported; make this limitation clear.

Dashboards:
- Organic clicks/impressions/CTR/position by query/page/country/device from Search Console.
- Organic sessions and revenue from GA4.
- Search-to-product click and conversion path.
- Calls, WhatsApp inquiries, bookings and paid orders.
- AI Stylist and Finder starts, useful recommendations, clicks and completed orders.
- Product feed approved/disapproved items from Merchant Center.
- AI search benchmark across 20-30 fixed prompts (manual/automated permitted by platform terms), recording exact date, platform, query, citations and linked URL. Not a complete share-of-voice metric.
- Do not report an AI response as a recommendation unless the system actually named/cited NURA.

## 6. Search Console-led keyword mapping (must be data-driven)

Use GSC export/API from a verified property for at least last 16 months where available:
- query, page, clicks, impressions, CTR, average position, country, device, search appearance, date.
Create query clusters:
1. wig + commercial + Kenya/Nairobi;
2. product type/material;
3. construction/style;
4. product/brand/model names;
5. price/budget;
6. care/accessory searches;
7. installation and local;
8. delivery/payment/returns;
9. Kiswahili/Sheng queries.
Map each cluster to one existing landing page. Identify:
- high impression + low CTR: improve titles/snippets;
- positions 4-20: improve content, internal links and product match;
- cannibalisation: multiple pages competing for same phrase;
- no landing page: propose only where real demand and catalogue support;
- branded vs non-branded;
- device/county trends;
- seasonal spikes from real historic data.
No invented search volume. If Keyword Planner export is available, include monthly average and competition; state exact location/network/date.

## 7. Priority content upgrades (prefer update before create)

1. /wig-prices-kenya/ as the one authoritative price hub; exclude non-wig products, display live range per hair type, product count, update time and related categories.
2. /best-wigs-in-kenya-buying-guide/ as a genuinely editorial guide, not a generic product list. Include best by budget/use case with each product checked to be a wig, price timestamp and selection reason.
3. /best-human-hair-wigs-kenya/ live prices and products from only verified human hair products.
4. /where-to-buy-human-hair-wigs-nairobi/ studio directions and visit facts from central registry, not duplicated manual details.
5. /wig-questions/ expand missing intent groups from GSC + WhatsApp data, not arbitrary word count.
6. Product category descriptions for human hair, semi-human, fibre, bob, glueless, frontal, closure, headband, curls, bone straight and wig care.
7. Care-product landing pages by real brand/product function (shampoo, conditioner, serum, adhesive, remover, tint, styling tools).
8. Kiswahili/English query pairs. Sheng wording should be natural and reviewed by fluent Kenyan speakers; do not machine-publish unnatural phrases.
9. Relevant event/occasion content: work/office, weddings/bridal, graduations, parties, everyday, protective styling. Tie pages to real product/service selection and original imagery.

Each article/page must:
- state audience and search intent;
- answer the question within the first 1-2 paragraphs;
- show author/reviewer identity that actually exists (NURA Beauty stylist team if genuinely accountable; do not create a fake named expert);
- show updated date only when reviewed;
- use original NURA product photos and practical experience;
- include useful comparison criteria, limitations and care instructions;
- link to exact product/category and relevant service;
- avoid repetition/cannibalisation;
- pass fact check before publication.

## 8. AEO / GEO principles

- Keep the /wig-questions/ knowledge base as the central conversational intent hub.
- Ensure answer sources match product categories and live stock/prices.
- Add factual citations/sources for hair-care and material claims when appropriate.
- Provide transparent "how we verify product hair type" process; only state tests/certification actually performed.
- Build a NURA brand/entity page with legal/trading identity, physical shop, official profiles, services, customer support and exact policies. Use same facts in schema and feeds.
- Allow relevant discovery crawlers where the owner has decided policy; inspect actual server-level robots/WAF not just source.
- Keep /llms.txt and /llms-full.txt only as optional, accurate machine-readable conveniences. Do not invest in these over product data, content quality and indexing; Google says it doesn't need special AI files/schema for AI Overviews.
- Use OpenAI OAI-SearchBot documentation for ChatGPT Search crawler policy. Do not conflate search retrieval crawlers with model-training crawlers.
- Do not buy fake mentions, create fake Reddit accounts, fabricate citations, create fake reviews or ask agents to seed inauthentic recommendations.
- Earn third-party mentions through stylist partnerships, creator demos, Kenyan beauty media, real customer stories, salon collaborations and product education.
- AI recommendations are not guaranteed. Measure observable mentions/citations only.

## 9. Schema and Merchant data improvements

Preserve Rank Math as primary graph owner. Do not add an additional SEO plugin/parallel graph.

For products:
- Product/Offer data on individual purchasable product pages in initial HTML.
- ProductGroup only for true variation groups; unique group/variant IDs.
- Ensure variant has correct URL and attributes.
- price and availability match selected variation and page state.
- brand = real manufacturer/brand; NURA retailer as seller.
- AggregateRating/Review only for genuine individual product reviews visibly shown for that same product. Never use Google Business Profile/business-level aggregate review ratings on NURA-owned business pages to force star snippets.
- MerchantReturnPolicy and OfferShippingDetails only when policy matches actual current business terms and can be applied accurately.
- Merchant Center feed should carry correct brand/GTIN/MPN where valid, product title, condition, colour, size, image, price, sale price, availability and shipping/returns settings.
- Don't duplicate product schema across Rank Math, WooCommerce and custom theme. Inspect final rendered HTML.
- Add VideoObject only for genuine public product/how-to videos with accessible video content and accurate thumbnails/transcripts where appropriate.

Google's latest guidance: AI Overviews/AI Mode require ordinary eligibility and helpful content, not special schema/LLMS. Product structured data and feed remain important to Shopping/merchant listing eligibility.

## 10. Technical SEO and crawl management

- Ensure sitemap lists canonical published indexable products/pages and excludes private/draft/noindex/filter URLs.
- Avoid internal search and filter combinations being crawled as thousands of duplicate pages; allow useful category landing pages to be indexable.
- Keep important product/category text in initial HTML.
- Validate robots.txt on the live server and CDN/WAF.
- Validate breadcrumbs, canonicals, pagination and redirects.
- Check image discovery, WebP/AVIF, width/height, alt text and product gallery variants.
- Verify mobile performance and field Core Web Vitals.
- Use IndexNow/Bing where correctly implemented; use GSC inspection/indexing only where justified and within platform limits.
- Don't report submitted URLs as indexed.

## 11. Product data and brand integrity

Every product and variation must have its true brand/manufacturer. Use existing NURA product brands taxonomy/attribute mapping. Product title should not invent brand when absent.
- Current product type categories must distinguish wigs from wig care/beauty/accessories.
- Hair type vocabulary should be canonical and mutually understandable (Pure Human/100% Human Hair; Semi Human/Human-hair blend; Fibre/heat-resistant synthetic fibre) without using a label unsupported by supplier evidence.
- Product origin, Remy, Virgin, cuticle, density and grade claims require supplier/manufacturer evidence. Do not infer from image or name.
- The product title should prioritise the buyer's query terms, e.g. "12-Inch Jerry Curl Human Hair Wig" only if material and length are verified.
- Keep product slugs stable unless the benefit outweighs the redirect/canonical risk.

## 12. Implementation process and release acceptance

Follow AGENTS.md and HANDOVER.md:
- Read current versions and session status first.
- Make an inventory before editing.
- Work on `seo-r5-search-coverage-2026-11`; do not overwrite main.
- Preserve all NURA Desk, POS, stock, bookings, loyalty, WhatsApp bot, AI Stylist, Finder and checkout functionality.
- Do not change stock policy (owner rule: no item marked out of stock except admin-controlled, and customers may order quantities according to current workflow).
- Do not use outside product photos unless they depict the exact SKU from the supplier and owner approves. Real NURA photos are preferred.
- Bump theme/plugin versions and update minified JS twins when needed.
- PHP lint, JS syntax, unit/integration tests, regression test full business workflow.
- Build installable theme/plugin zips if release is approved. Do not claim live install unless owner/agent actually installed and verified.
- Update HANDOVER.md, README release table, PRD and session log per AGENTS.md.
- Provide rollback plan and evidence (URLs/screenshots/logs) in PR.
- No invented analytics or search metrics.

Acceptance tests:
1. Wig price lists never include wig-care products or non-wig goods.
2. Buying-guide recommendations are all real wig products matching hair-type and category.
3. Human-hair page includes only verified products with true attribute evidence.
4. AI Stylist/Questions/Wig Finder/city pages return products matching user intent.
5. Prices, counts, stock and updated dates match WooCommerce.
6. Product/variant schema and Merchant Center values match actual selected variation and feed.
7. All product brands are truthful.
8. Analytics events have no PII and purchase revenue reconciles.
9. Search console query/page mapping and cannibalisation report produced from actual data.
10. All existing checkout, M-Pesa, Paystack, COD rules, pickup, booking, POS, stock, WhatsApp, account and staff access flows pass.
11. All live changes are recorded and no unverified fact is published.

## 13. Final deliverables

Provide:
- repo-wide current SEO/AEO/GEO/schema architecture map;
- Kenya keyword cluster file with intent/page mapping and evidence source;
- product and category eligibility/audit CSV;
- duplicate/cannibalised URL report;
- live product brand/attribute completeness report;
- structured-data validation log by page type;
- technical SEO crawl report;
- Merchant Center parity report (when export available);
- content refresh/create queue with priority and human reviewer;
- GA4/GSC instrumentation and event spec;
- AI visibility benchmark baseline;
- completed/blocked items list;
- PR and deploy/rollback guide.

Do not call the project complete until evidence validates each acceptance criterion.
