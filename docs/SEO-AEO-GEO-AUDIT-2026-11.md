# NURA Beauty: Search, Answer-Engine and AI Discovery Strategy

**Global SEO, AEO, GEO and Structured Data Programme**

| | |
| --- | --- |
| Version | 2.0 (supersedes the 1 November 2026 draft) |
| Date | 1 November 2026 |
| Owner | Sene, NURA Beauty (NURA CROWN & BEAUTY, BN-MJS7ALRV) |
| Website | https://nurabeauty.co.ke |
| Code | github.com/moselanto/nurabeauty.co.ke (theme `nura-beauty` 1.73.0, plugin `nura-experience` 1.79.2) |
| Status | Strategy approved for planning. No production code changed. Owner decisions in section 10 block the first release. |

---

## 1. Executive summary

NURA's ambition is to be the most trusted wig and beauty retailer in Kenya, then East Africa, then the diaspora, and to be the business that Google, Bing, shopping platforms and AI assistants (ChatGPT, Gemini, Perplexity, Claude, Copilot) describe accurately and recommend with confidence.

The technical foundation is unusually strong for a Kenyan retailer: a custom WooCommerce theme and plugin, Rank Math, connected schema, product-variant markup, buying guides, city pages, an AI stylist and a machine-readable summary for AI systems. The problem is not a lack of SEO features. **The problem is that the website currently tells search engines and AI systems several different stories about the same business.**

Verified on 1 November 2026 (section 3 lists the evidence):

1. The shop has moved to **Stanbank House, 2nd Floor, Room 20, Nairobi CBD**, but the old Imenti House address still appears in **26 source files**, the live homepage, the live `/llms.txt`, schema coordinates, checkout text, the WhatsApp order pop-up and the AI stylist's instructions.
2. The live `/llms.txt` is served by an **older, superseded handler**. It shows the old address and an empty category list, and the newer, richer AI summary never runs.
3. Customer policies conflict across the codebase: same-day cut-off (5 PM vs 2 PM), returns (7 days vs 48 hours), and one buying guide still offers **cash on delivery**, which was withdrawn in release 1.79.0.
4. Product markup assigns the brand "NURA" or "NURA Beauty" to **every** product, and the shipping-rate logic can publish a **KSh 0 delivery charge** by counting free studio pick-up as delivery.

**Strategy in one sentence:** make every channel describe one verified business, then compete on product data quality and genuine expertise, then expand market by market only where NURA can actually deliver.

The first release (R1, section 8) is therefore not "more SEO". It is relocation, one source of truth for business facts and policies, one AI-summary endpoint and a validated schema graph. Everything after that compounds on an accurate base.

> No implementation can guarantee a first-position ranking or an AI recommendation. This programme maximises eligibility and trust, and measures real outcomes against a baseline.

---

## 2. Principles (international standard)

These principles govern every decision in the programme and align with Google Search Essentials, Google's helpful-content and E-E-A-T guidance, Google Merchant Center policies, Bing Webmaster Guidelines and schema.org.

1. **One business, one truth.** Address, hours, policies, payments and contact details come from a single controlled source and feed every page, schema node, AI file, checkout message and WhatsApp template.
2. **Verified or omitted.** Never publish an unverified address detail, coordinate, opening time, brand, identifier, rating or claim. An omitted property is better than a false one.
3. **People first.** Content exists to help a woman choose, buy and care for a wig. Rankings follow usefulness, not keyword density.
4. **Demonstrated expertise.** Original photos, real stylists, real products, real prices. Generic AI-written articles are not a competitive advantage.
5. **Product data is the revenue engine.** Complete, accurate product and variant data serves organic search, Merchant Center, Meta catalogues and AI shopping answers at once.
6. **Earn expansion.** A market gets its own pages only when shipping, payment, currency, returns and support are operational there.
7. **Measured, not assumed.** Every task has an acceptance test, and every claim of success has evidence.
8. **Never break the shop.** Checkout, M-Pesa/Paystack, WhatsApp ordering, bookings, NURA Desk, loyalty and staff permissions are protected throughout (see AGENTS.md).

---

## 3. Verified baseline (1 November 2026)

Each finding below was checked against the `main` branch and the live website, not inferred.

| # | Severity | Finding | Evidence |
| --- | --- | --- | --- |
| F1 | Critical | Old address (Imenti House) in 26 source files, including checkout, WhatsApp order pop-up, AI stylist prompt, contact page, Customizer default, schema, city pages and four guides. Live homepage and FAQ still show it. | `woocommerce-support.php:704`, `class-nura-wa-order.php:583`, `class-nura-ai.php:231`, `customizer.php:48`, `page-contact.php:23-25`, `nura-city-pages.php:47`, `nura-boost.php:258`, `nura-geo.php:164`; live homepage |
| F2 | Critical | Old map coordinates (-1.2837, 36.8272) hard-coded in two schema emitters; contact-page map query points to Imenti House. | `nura-schema-graph.php:234`, `class-nura-schema.php:106-107`, `page-contact.php:25` |
| F3 | Critical | Live `/llms.txt` is produced by the legacy handler in `nura-seo-plus.php` (runs on `init` priority 1 and exits). The newer GEO handler in `nura-geo.php` (`parse_request`) is never reached, so `/llms.txt` shows the old address, an **empty category list** (product categories are not yet registered at `init` priority 1) and is missing About, Returns and Contact links. | `nura-seo-plus.php:370-405`, `nura-geo.php:135-153`; live `/llms.txt` |
| F4 | Critical | Same-day cut-off conflict: 5 PM in live code and copy (15+ places) vs 2 PM in demo/importer pages (FAQ, delivery, shipping, track order). | `demo/pages/faq.html:8`, `demo/pages/shipping-delivery.html:6,16`, `demo/pages/delivery.html:4` vs `nura-seo.php:51`, `nura-geo.php:54` |
| F5 | Critical | Returns conflict: 7 days in live code and copy vs 48 hours in demo FAQ and returns page; `class-nura-pages.php` header says 48 hours while its body says 7 days. | `demo/pages/returns-refunds.html:8,23`, `demo/pages/faq.html:11`, `class-nura-pages.php:21,108` |
| F6 | Critical | A live buying guide still offers cash on delivery, contradicting the 1.79.0 no-COD policy enforced at checkout. | `content/guides/how-to-buy-wigs-online-in-kenya-safely.html:1,5` |
| F7 | High | Opening hours (Mon-Sat 08:00-20:00) hard-coded in two schema emitters and never confirmed by the owner (HANDOVER section 5). | `nura-schema-graph.php:220-224`, `class-nura-schema.php:109-113` |
| F8 | High | Every product is given a brand in schema, with inconsistent names ("NURA" in two places, "NURA Beauty" in another), regardless of the actual manufacturer. | `nura-seo.php:157`, `seo-schema.php:124`, `nura-growth.php:241` |
| F9 | High | `nura_seo_ke_shipping_rate()` takes the minimum cost across flat-rate **and local pick-up** methods, so product offers can advertise KSh 0 delivery. | `nura-seo-plus.php:39-70, 339` |
| F10 | High | Three to four independent schema emitters enrich or duplicate the Rank Math graph; rendered pages must be checked for duplicate Organization/LocalBusiness/Product nodes. | `nura-seo-plus.php:265`, `nura-schema-graph.php`, `nura-schema-variants.php`, `class-nura-schema.php`, `seo-schema.php` |
| F11 | Medium | `/llms-full.txt` catalogue capped at 400 products; both AI summaries cached 6 hours and only invalidated on product save, not on address or policy changes. | `nura-geo.php:122,148,155` |
| F12 | Medium | Variant URLs use `$variation->get_permalink()`; must be proven in a browser to preselect the right colour/length, image, price and stock. | `nura-schema-variants.php:96,117` |
| F13 | Medium | Google Place ID fallback is hard-coded. Correct only if the existing Business Profile is moved (not duplicated). | `class-nura-community.php:67` |
| F14 | Decision | robots.txt allows both AI **search** crawlers (OAI-SearchBot, ChatGPT-User, PerplexityBot, Claude-SearchBot) and AI **training** crawlers (GPTBot, CCBot, Google-Extended, Applebot-Extended, meta-externalagent, cohere-ai). Training access is a business choice that should be made deliberately. | live `/robots.txt` |
| F15 | Low | `hreflang` (en-KE, x-default) is printed on the homepage only. Acceptable for a single-locale site; must be expanded properly or removed when markets are added. | `nura-seo-plus.php:360-364` |

What is working and must be preserved: Rank Math as graph owner, connected Organization/OnlineStore + HealthAndBeautyBusiness concept, ProductGroup variant markup, KES pricing, no-COD checkout enforcement, crawler-friendly robots.txt with scraper blocking, city pages, buying guides, LiteSpeed + Redis performance work, and the security controls documented in SECURITY-AUDIT.md.

Not yet measured (requires account access, section 11): Search Console coverage and queries, indexed page count, Core Web Vitals field data, Merchant Center status, Business Profile insights, backlink profile and AI-assistant mention rate.

---

## 4. Target architecture

### 4.1 Business facts registry (single source of truth)

One owner-controlled settings object (NURA Desk > Settings > Business facts, with code defaults only as a last resort) holds:

| Group | Fields |
| --- | --- |
| Identity | Trading name, registered name, business number, logo, official social profiles (`sameAs`) |
| Location | Building, floor, room, street, locality, county, postcode, country, verified map URL, verified coordinates, Place ID |
| Hours | Opening days and hours, walk-in vs appointment, holiday closures |
| Contact | Phone, WhatsApp, e-mail |
| Fulfilment | Same-day cut-off, delivery windows by region, rates by method, pick-up availability, international shipping status |
| Payments | Accepted methods, Till number and name, COD status, Lipa Mdogo Mdogo terms version |
| After-sale | Returns window and exclusions, warranty terms, damage-report window |

Every output reads from this registry: visible pages, footer, contact page and map, checkout and gateway text, WhatsApp order messages, AI stylist instructions, Rank Math filters, custom schema, `/llms.txt`, `/llms-full.txt`, city pages, guides (via shortcodes or placeholders) and receipts. Saving the registry purges the AI-summary caches and LiteSpeed. A relocation becomes one edit.

### 4.2 Entity graph

| Entity | Type | Stable `@id` | Notes |
| --- | --- | --- | --- |
| NURA Beauty | Organization + OnlineStore | `/#organization` | Legal name, logo, contact, `sameAs`, `hasMerchantReturnPolicy` |
| NURA Studio, Stanbank House | Store / HealthAndBeautyBusiness | `/#studio` | `parentOrganization` -> `/#organization`; address, geo and hours **only when verified** |
| Website | WebSite | `/#website` | `publisher` -> `/#organization` |
| Pages | WebPage / CollectionPage / AboutPage / ContactPage | `{url}#webpage` | Breadcrumbs on every page |
| Products | Product or ProductGroup + variant Products | `{url}#product`, `{url}#variant-{id}` | Real brand only; Offer with live KES price and stock; shipping and returns from the registry |
| Services | Service + OfferCatalog | `/services/#service-{slug}` | Installation, revamp, styling, consultation; `provider` -> `/#studio` |
| Guides | Article / BlogPosting | `{url}#article` | Real named author and reviewer (Person), dates |
| FAQs | FAQPage | `{url}#faq` | Only where the Q&A is visible on that page |

Rank Math owns the graph; NURA code only enriches it through one coordinator filter. `seo-schema.php` remains the fallback when Rank Math is inactive. No page emits a second Organization, LocalBusiness or Product node. No Review or AggregateRating is published unless it comes from genuine on-site customer reviews of that product.

### 4.3 AI discovery layer

- One route serves `/llms.txt` (concise business summary, policies, key links) and `/llms-full.txt` (full catalogue, paginated, no cap), both rendered from the registry and live WooCommerce data.
- Public, catalogue-visible products only. No account, order, customer, hidden or draft data.
- Accurate "last updated" timestamp; cache purged on product, registry and policy changes.
- Treated as a convenience for systems that choose to read it, not as a ranking factor.

---

## 5. SEO: search intent and product excellence

### 5.1 Intent map (one best page per intent)

| Intent cluster | Example searches (Kenya) | Target page | Supporting content |
| --- | --- | --- | --- |
| Buy online | buy wigs online Kenya, wig shop Kenya | Homepage / Shop | How to buy wigs online safely |
| Local shop | wig shop Nairobi CBD, wigs Moi Avenue | Contact / Studio page | Visit guide, directions, Business Profile |
| Hair type | human hair wigs Kenya, semi-human wigs, synthetic wigs | Hair-type categories | Human vs blend vs fibre guide |
| Construction | HD lace front Kenya, 13x4 frontal, 4x4 closure, 5x5 closure | Lace categories | Frontal vs closure guide |
| Convenience | glueless wigs Kenya, headband wigs | Glueless / Headband categories | Beginner guide |
| Style | bob wigs, curly wigs, body wave, bone straight | Style categories | Face-shape guide |
| Price | wig prices Kenya, affordable wigs | Price guide with live price table | Category price ranges |
| Services | wig installation Nairobi, wig revamp, bridal wigs | Service pages + booking | Installation and revamp guides |
| Delivery | wigs delivered to Mombasa / Kisumu / Nakuru / Eldoret | City pages (distinct delivery facts) | Delivery page |
| Business | wholesale wigs Kenya, salon supply | Bulk and corporate orders | Wholesale terms |

Keyword themes are research targets, validated against Search Console and inventory before content is written. No two pages target the same primary intent.

### 5.2 Product pages (the revenue layer)

Every product and variation should carry: descriptive title (type, construction, texture, length), unique description, hair type, texture, length, density, lace type and size, cap size, colour per variation, unique SKU, actual brand (or none), current KES price, real stock, original images per variation with descriptive alt text, care and heat guidance, what is included, and delivery/returns summary from the registry.

A catalogue-wide data audit (CSV) lists missing or conflicting fields per product and variation, with an editor-friendly correction workflow in wp-admin.

### 5.3 Category pages

Unique title and meta description, one H1, a short decision-focused introduction, live product count and price range, links to the relevant guides, breadcrumbs, an optimised category image and CollectionPage/ItemList markup. Filter combinations stay `noindex` to avoid crawl traps; only collections with real demand and inventory become indexable landing pages.

### 5.4 Technical standards

| Area | Standard |
| --- | --- |
| Core Web Vitals (mobile, field data) | LCP <= 2.5 s, INP <= 200 ms, CLS <= 0.1 at the 75th percentile |
| Indexing | Every important product, category, guide and service page indexable, canonical, in the sitemap and internally linked |
| Crawl control | Cart, checkout, account, search and filter URLs excluded; no accidental blocks |
| Redirects | 301 for permanent changes, no chains, no blanket redirects to the homepage |
| Images | Responsive sizes, WebP, compressed, discoverable in the image sitemap |
| Rendering | Prices, stock and key content present in the initial HTML |
| Security | HTTPS everywhere, no mixed content, existing headers preserved |

---

## 6. AEO: becoming the answer

Goal: when a customer asks a direct question, NURA has the clearest, most accurate answer on a relevant indexable page, and every page gives the same answer.

- **One canonical answer per question**, written once in the registry or a central FAQ source and reused, so the homepage, FAQ page, product pages and AI files never disagree.
- **Answer first, then explain.** A one or two sentence direct answer, followed by detail, comparisons and links to products.
- **Priority questions:** where to buy quality human hair wigs in Kenya; affordable wigs in Nairobi; human hair vs semi-human vs fibre; 13x4 vs 5x5 lace price and differences; best wig for beginners; glueless wigs in Nairobi; wig installation in Nairobi CBD; delivery to Mombasa and other towns; paying with M-Pesa; Lipa Mdogo Mdogo; returns and warranty.
- **First-hand evidence:** stylist-authored guides with named authors and review dates, original product and installation photography, short videos of lace, texture and fit, and comparisons using NURA's own stock.
- **FAQ markup** only where the Q&A is visible on that page. Google shows FAQ rich results only to a limited set of sites; the value is clarity for users and machines, not a snippet guarantee.

---

## 7. GEO: trusted by AI assistants

AI assistants recommend businesses they can verify across several independent sources. The levers are accuracy, accessibility and reputation.

1. **Consistent facts everywhere:** website, schema, `/llms.txt`, Google Business Profile, Bing Places, Apple Business Connect, Instagram, TikTok, Facebook and directories all show the same name, address, phone, hours and policies.
2. **Crawl access, chosen deliberately:** keep search and user-triggered crawlers allowed (Googlebot, Bingbot, OAI-SearchBot, ChatGPT-User, PerplexityBot, Perplexity-User, Claude-SearchBot, Claude-User, Applebot). Decide separately whether to allow training crawlers (GPTBot, Google-Extended, CCBot, Applebot-Extended, ClaudeBot, meta-externalagent). Verify at robots.txt **and** firewall/CDN level.
3. **Complete machine-readable catalogue** via `/llms-full.txt`, Merchant Center and the Meta catalogue, all from one WooCommerce source.
4. **Earned authority:** genuine customer reviews on Google, features by Kenyan beauty and lifestyle media, collaborations with stylists, salons, bridal vendors and creators, and listings in reputable directories. No paid link schemes, fake reviews or doorway pages.
5. **Measured monthly:** a fixed set of 25 to 30 buyer prompts tested in Google AI Overviews/AI Mode, ChatGPT Search, Perplexity, Gemini and Copilot, logging date, prompt, whether NURA is mentioned, cited sources and landing URL. Reported as directional evidence.

---

## 8. Roadmap and release gates

| Release | Window | Scope | Exit criteria (all must pass) |
| --- | --- | --- | --- |
| **R1 Facts and relocation** | Weeks 1-2 | Business facts registry; replace Imenti House in all 26 files; remove old coordinates and map query; resolve cut-off, returns and COD conflicts; correct demo/importer content; AI stylist and WhatsApp text from registry | Zero "Imenti" in source and rendered pages (except an optional dated move notice); visible policy text identical on homepage, FAQ, delivery, returns, checkout, WhatsApp and AI files; checkout regression test passed |
| **R2 AI endpoint and schema** | Weeks 2-4 | One `/llms.txt` + `/llms-full.txt` route; paginated catalogue; cache purge on registry save; one schema coordinator; correct brand handling; fix shipping-rate logic; verify variant URLs | Both endpoints 200 with correct facts; no duplicate entity nodes on 10 representative pages; Rich Results Test and Schema Validator clean; offer shipping matches WooCommerce |
| **R3 Local and platforms** | Weeks 3-5 | Move (not duplicate) Google Business Profile; Bing Places, Apple Business Connect, social profiles, directories; Search Console, Bing Webmaster, GA4 checks; Merchant Center feed | Profile verified at Stanbank House; citations updated; baseline report captured |
| **R4 Product data** | Weeks 4-8 | Full catalogue audit; titles, attributes, SKUs, variation images, alt text; category page upgrades | 100% of catalogue-visible products have hair type, length, lace type, colour, SKU, price, stock and image; Merchant Center disapprovals under 2% |
| **R5 Content authority** | Months 2-4 | Intent map content; stylist-authored guides with original media; review programme; PR and partnerships | Each priority intent has one page; guides carry named authors and update dates |
| **R6 Regional and international** | Month 4+ | East Africa first, then UK/US/UAE diaspora, only where fulfilment, payment and returns are operational | Market readiness checklist signed off before any localised page or hreflang |

Every release follows AGENTS.md: feature branch, version bumps, `php -l`, minified JS regenerated, staging or careful live test, owner approval, zip upload, LiteSpeed purge, live verification and a HANDOVER.md entry.

---

## 9. KPIs and reporting

Baselines are captured in R3; targets are set once the baseline is known rather than guessed.

| KPI | Source | Cadence |
| --- | --- | --- |
| Indexed vs submitted URLs (products, categories, guides, services) | Search Console | Monthly |
| Organic clicks, impressions, CTR, average position for priority intents | Search Console | Monthly |
| Organic sessions, product views, add-to-cart, revenue | GA4 | Monthly |
| Core Web Vitals pass rate (mobile) | Search Console / CrUX | Monthly |
| Merchant Center approved vs disapproved products | Merchant Center | Weekly in R4, then monthly |
| Business Profile calls, direction requests, website clicks, review count and rating | Business Profile | Monthly |
| AI assistant mention rate on the fixed prompt set | Manual log | Monthly |
| Referring domains from relevant, reputable sites | Backlink tool / Search Console links | Quarterly |
| Facts consistency (automated stale-string and schema tests) | Repository test script | Every release |

---

## 10. Owner decisions required before R1 ships

| # | Decision | Current state |
| --- | --- | --- |
| D1 | Exact street name for Stanbank House (and postcode, if used) | Building, floor and room confirmed; street and postcode unconfirmed |
| D2 | Google Maps pin or share link for the new shop | Not provided |
| D3 | Opening days and hours | Site shows Mon-Sat 8:00-20:00, never confirmed |
| D4 | Walk-ins, appointments only, or both | Site says "by appointment" |
| D5 | Same-day Nairobi cut-off | Live site says 5 PM; old content says 2 PM |
| D6 | Returns window | Live site says 7 days; old content says 48 hours |
| D7 | Studio pick-up available at the new shop, and how it works | Assumed yes, unconfirmed |
| D8 | International shipping: not available, quote on request, or named countries | Site says "worldwide shipping available" |
| D9 | Allow AI training crawlers, or search crawlers only | Currently all allowed |
| D10 | Product brands: own NURA brand, supplier brands, or unbranded | Every product currently labelled NURA |

---

## 11. Access and security

Implementation needs least-privilege access, granted by the owner and revocable at any time:

- **WordPress:** a dedicated named account for the assistant (not the owner's personal login), strong unique password, two-factor authentication, removed or downgraded after each phase. Every change is logged in HANDOVER.md.
- **Google:** add the working account as a user (not owner) in Search Console, Business Profile (Manager), Merchant Center and GA4 (Viewer or Analyst), rather than sharing an owner password.
- **Credentials are never pasted into chat, documents, code or the repository.** Sign-in happens only through a secure sign-in prompt.

---

## 12. Risk register

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Relocation edits break checkout or WhatsApp ordering | Lost sales | Registry-driven text only; full checkout and WhatsApp regression test before release |
| Duplicate Business Profile created for the new shop | Split reviews, ranking loss | Move the existing listing; never create a second |
| Conflicting facts persist in caches or third-party listings | AI assistants repeat old data | Cache purge on registry save; citation clean-up checklist |
| Incorrect brand or identifiers in feeds | Merchant Center disapprovals | Brand only from verified data; omit GTIN where none exists |
| Schema changes introduce duplicates | Rich result loss | Validate rendered HTML on 10 representative pages each release |
| International pages without real fulfilment | Trust and policy issues | Market readiness checklist gate |

---

## 13. Repository deliverables

| File | Purpose |
| --- | --- |
| `docs/SEO-AEO-GEO-AUDIT-2026-11.md` | This strategy and audit |
| `docs/NURA-BUSINESS-FACTS.md` | Confirmed facts and open owner decisions (source for the registry) |
| `docs/NURA-SEO-AEO-GEO-AGENT-PROMPT.md` | Implementation brief for coding agents |
| `HANDOVER.md` | Session log and current status, updated after every session |

*Prepared for NURA Beauty. This document distinguishes verified findings from recommendations and targets. It makes no ranking or recommendation guarantees.*
