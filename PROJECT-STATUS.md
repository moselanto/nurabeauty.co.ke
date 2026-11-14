# NURA Beauty - project status and next steps

Last updated: 14 Nov 2026 by Town assistant (Ray). Read this first, then `HANDOVER.md` (version table, release rows, session log) and `README.md`.

## Live versions
| Component | Version | Notes |
|---|---|---|
| Theme `nura-beauty/` (parent) | **1.149.0** (live since 14 Nov) | Owner slide first; hero fixed for desktop and phone (14 Nov). | Child theme `nura-beauty-child` is the active theme. Always upload the parent with "Replace current with uploaded". |
| Plugin `nura-experience/` | **1.112.1** (live since 14 Nov) | NURA Desk back office, WhatsApp orders, AI stylist, bookings, accounts/P&L, review asks, **Stock tab (1.110.0; fixes 1.110.1-1.110.2)**. |

## How a release is done (the working method)
1. Edit in the repo, run `php -l` on every changed file.
2. Bump the version in two places: theme `functions.php` (`NURA_VERSION`) + `style.css` (`Version:`); plugin header + `NURAX_VERSION`.
3. Update `HANDOVER.md` (version table, release row, previous row's "(this commit)" -> sha, session log row) and `README.md` badges.
4. Commit to `main`, zip the folder (`nura-beauty/` or `nura-experience/` at the zip root), upload in wp-admin, then LiteSpeed Cache > Purge All.
5. Verify live with `curl -A "Mozilla/5.0" "<url>?v=$(date +%s)"` (cache-buster).
6. Data changes ship as **one-time migrations** in the theme: run on the first wp-admin load by an admin, guarded by an option, with a log shown on Tools > NURA images. Never run twice; to re-run, delete the `*_done` option.

| Migration file | Option guard | What it did |
|---|---|---|
| `inc/nura-hair-types-2026.php` | `nura_ht_2026_done` | Hair Type attribute per product, two renames, product text, hair categories |
| `inc/nura-site-words-2026.php` | `nura_sw_2026_done` | Hair-type wording on 32 published pages/posts, Human Blend Wigs category |
| `inc/nura-audit-fixes-2026.php` | `nura_af_2026_done` | Product SEO titles/descriptions, leftover product text, local attributes, links to deleted products |
| `inc/nura-audit-fixes-2026.php` | `nura_inst_2026_done` | Installation price list theme_mod + 'installation from KSh 1,500' -> 1,000 on pages |
| `inc/nura-audit-fixes-2026.php` | `nura_svc_2026_done` | All service prices on pages/posts and Rank Math meta |

## Business rules (do not break)
- **Stock (owner, 14 Nov 2026): nothing goes out of stock by itself.** The shop keeps samples to see the trend; suppliers are on standby, so customers can order any quantity even when 1-2 are in the shop. Only staff 'Stop selling' on NURA Desk > Stock shows an item as Out of stock (plugin 1.111.0). Shoppers never see 'N in stock' text (1.111.1).
- Real reviews, prices, clients and photos only. Never invent anything.
- Delivery wording: same-day Nairobi delivery; **same-day dispatch countrywide** (never "1-3 days").
- Hair types (owner, 13 Nov 2026): **Pure Human** = 100% human hair. **Semi Human** = human hair mixed with good-quality fibre; low heat is fine. **Human Blend** = Bob Wavy only, its own label, no extra definition; low heat is fine. **Luxury Fibre Bone Straight Frontal** (274) and **4x4 Luxury Bone Straight Closure Wig** (251) state no hair type. The product-to-type map is `nura_ht_map()` in `inc/nura-hair-types-2026.php`. Semi Human is now the cheapest tier; do not write "fibre is the cheapest".
- Categories: `human-hair-wigs` = Pure Human Wigs, `human-hair-blend-wigs` = Semi Human Wigs, `human-blend-wigs` = Human Blend Wigs (Bob Wavy), High Fibre = only 274. Slugs never change.
- Care products returns: unopened, original packaging, within 48 hours; opened care products can't be returned (hygiene).
- Product slugs never change on renames (Rank Math follows titles). Old supplier names live in `nura_pai_alt_names()` (schema alternateName, llms.txt).

## Done (11-14 Nov 2026)
- **Book of Accounts (plugin 1.112.0, 14 Nov):** NURA Desk > Accounts > "Book of accounts" downloads a double-entry workbook: Cover (period dates drive every formula), Profit or loss (IAS 1, comparative), Financial position (balance check), Cash flows (IAS 7 direct, agreed to cash), Trial balance, General journal (manual rows picked up automatically), Chart of accounts, Sales register, Unpaid orders, Notes. Code: `includes/class-nura-xlsx-book.php`, `NURAX_Desk_Accounts::book_data()`. "Sold - record payment" asks method, M-Pesa/bank code (duplicate codes refused) and date paid (`_nura_paid_method`, `_nura_paid_ref`, `_nura_paid_on`). Paid WhatsApp orders not billed through the till now count as WhatsApp sales.
- Theme 1.148.0 + plugin 1.111.3 installed 14 Nov, NURA Desk newsletter delay 12 -> 4 s, Purge All. Desktop logged-out: WhatsApp pop-up OK on all 14 categories + Human Hair Bob, product pictures load, menus open. Newsletter bar shows an inline e-mail form and does not open the Join The House pop-up (footer button does) - to fix. Phone checks and PSI not yet re-run (PSI API daily quota hit).
- **NURA Desk > Stock (plugin 1.110.0, 14 Nov):** On shelf / Reserved / Available per wig and colour, owner-named stock places, stock changes with a required reason, stock-take, moves, full history, CSV downloads; WhatsApp orders reserve when placed, come off the shelf when paid, go back when cancelled. Owner installed and tested a website order, a till sale and a WhatsApp order. Stock plan, opening stock register (v4.1, making cost KES 85,375) and data: moselanto/NURA_STOCKS.
- Care product photos and short descriptions (9 products); glue remover rename; short titles without ml (12 products).
- Lace Tint is a variable product with 3 shades.
- Checkout: "Pay on delivery (cash or M-Pesa)", "Studio pickup (free)", totals no longer go blank while delivery recalculates (1.129.2).
- AI/search visibility: brand, descriptions, per-product Q&A, llms.txt, "Where can I buy ... in Kenya?" questions (1.126.0-1.129.3).
- Sharper photos: sharpened quality-86 WebP for every product image, also used in shop cards (1.130.0).
- Hair types Pure Human / Semi Human / Human Blend across products, categories, guides, FAQ, price page, Swahili pages (1.131.0-1.132.0).
- WooCommerce > Ask for reviews: one row per paying customer, opens WhatsApp with per-product review links, records the date (plugin 1.109.0).
- Prices: Luodais Wig Serum (renamed from Wig Repair Serum) KSh 400; CSCR Lace Melting Spray KSh 1,000 (all 4 options).
- Google Merchant Center (account 5874670354, login nurabeautyke@gmail.com): free listings on, site verified, 132 product entries approved, Kenya shipping set, return policy "Standard for Kenya" saved 13 Nov (Google review up to 10 days).
- Service prices (1.134.0-1.135.0), the same on every page, FAQ, schema and Google snippet: installation KSh 1,000, half up half down 1,500, tonging 800, flat ironing 500, revamp 1,500, bridal styling 3,500, colouring 2,500-3,500 (human hair only), re-lacing/repair 2,000. Source of truth: `nura_services()` in inc/nura-services.php and theme_mod `nura_inst_services`.
- Google Business Profile (login nurabeautyke@gmail.com), 13 Nov 2026: services and prices edited to match the banner. Wig Installation KSh 1,000; Tonging KSh 800 (renamed from Wig Styling & Restyle); Wig Colouring price field "From KSh 2,500" with KSh 2,500-3,500, human hair only, in the description (Google has no range field); Wig Wash & Revamp KSh 1,500; Wig Re-lacing & Repair KSh 2,000; added Half up half down KSh 1,500, Flat ironing KSh 500, Bridal styling KSh 3,500. **Status at 20:35 EAT 13 Nov (second check): still pending Google review.** There is one "Your edit is pending review" notice for the whole panel, no service shows "rejected" or "not approved", and all 8 values are saved. Public Search and Maps show no Services menu; Maps has only the Overview, Reviews and About tabs. This was checked while signed in, with personalization off, because no fully signed-out check was possible.
- Full-site audit 13 Nov (131 URLs, 47 internal links, 292 images): no PHP errors, no broken images, valid JSON-LD. Fixed old hair words in product SEO titles, leftover product text, 6 links to deleted products (301 redirects + guide links rewritten), 3 missing meta descriptions (1.133.0).

## Pending - owner input needed
1. **Checkout totals on a phone**: the "updating..." fix (1.129.2) is not yet confirmed by the owner.
2. **New wig photos (1200 px or larger)**: softest originals are Human Hair Bob, Bob Wavy, Latisha Sensational Headband, 12 Inch Jerry Curls, Loose Wave Frontal, Luxury Body Wave, Minky Headband. Two care images are only 680 px (Texture Spray `2026/11/2-3.jpg`, Melting Spray `2026/11/cscr-1.jpg`).
3. **October orders**: most were marked Test, so revenue shows KSh 0; one cash-on-delivery order (#1777, KSh 8,800) and 13 WhatsApp orders (KSh 58,410) are unpaid. Mark real ones as paid in NURA Desk > Accounts; they then appear in Ask for reviews.
4. **Customer reviews**: start asking from WooCommerce > Ask for reviews (only 1 paid customer listed today).
5. Business decisions: Apple Business Connect (certificate still shows old Imenti House address), Bing Places, paid directories, missing colour photos for some options, two-factor login for admin accounts.

## Next for the developer/agent
- Stock (14 Nov): tracking on for 9 colours only (22 pieces: LWF Black 5, Burgundy 2, 33 4, Red Wine 2, 30 1; 4x4 closure C199J 2, Black 2, C33 2; Bone Straight Black 2); everything else stays 'In stock' untracked. Opening buying prices loaded. Test order #2361 cancelled. Both known gaps fixed in 1.110.2 (test order #2363). Stock places: only 'Studio' (owner, all stock at the Studio). Draft products with stock and buying prices, prices blank: Bob Amanda #2364 (Black, 33), 2x2 Negatron Closure #2367 (1/30), LWF #364 colour 2/30 var #2369 (disabled). Stock tab On shelf 26. Prices set 14 Nov: Bob Amanda Black/33 KSh 4,000, Negatron 1/30 KSh 1,800 (both still Draft), LWF 2/30 KSh 3,500 (enabled, live). Waiting on owner: real photos + details for Bob Amanda and Negatron, then publish; the 6 unmatched stock lines (Bob Amanda, Empress, Wonderful Frontal, 2x2 Negatron, LWF 2/30) stay off the website until the owner decides. Further stock work is planned in moselanto/NURA_STOCKS (PRD.md, HANDOVER.md).
0. Google Business Profile: re-check the services panel after 14 Nov. Confirm the "pending review" notice is gone and nothing is rejected. Also check whether a Services menu now shows publicly on Search/Maps; if one shows, confirm the 8 prices match `nura_services()`.
1. Merchant Center (parked by owner until website work is done): check the return policy was approved; consider richer descriptions for the 110 entries Google flagged; review the 5 products Google found on the site (avoid duplicates).
2. Long SEO titles: many product titles are 80-120 characters (Google shows about 60). Shorten the template in `class-nura-desk-catalogue.php::seo_meta()` and the stored `rank_math_title` values.
3. Replace soft wig photos when the owner sends them (Tools > NURA images rebuilds WebP).
4. Turn on admin two-factor login once the owner agrees.
5. Re-run the site audit after big changes: crawl `sitemap_index.xml`, check HTTP codes, PHP notices, JSON-LD, old wording, broken links and images.
