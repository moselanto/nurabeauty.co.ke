# Product Requirements - NURA Beauty online store

## 1. Vision
The most trusted, best-looking wig store in Kenya with an international, professional feel: premium wigs, clear prices, fast delivery, flexible payment and real stylist help - online, on WhatsApp and in the studio at Imenti House, Moi Avenue, Nairobi CBD.

## 2. Users

| User | Needs |
| --- | --- |
| Shoppers in Nairobi | Same-day delivery, cash on delivery, studio fittings |
| Shoppers across Kenya | Courier delivery, M-Pesa, WhatsApp help |
| Diaspora / East Africa | Quoted shipping, trust signals |
| Salons and wholesale | Bulk and corporate orders |
| NURA owner | Full control, sales and stock visibility, staff limits, audit trail |
| NURA staff (shop managers) | Fast till, orders, bookings, plans and reminders without access to owner-only controls |

## 3. Requirements

| # | Requirement | Status |
| --- | --- | --- |
| 1 | Catalogue of human hair, blend and heat-resistant fibre wigs with colour variations, 10-shade colour range and per-colour photos | Live (some colour photos pending from owner) |
| 2 | Clear KES pricing, stock and categories | Live |
| 3 | Checkout with required phone; M-Pesa, card (Paystack), bank transfer; cash on delivery Nairobi only | Live |
| 4 | Delivery: same-day Nairobi before 5:00 PM; rider from KSh 300; bus/shuttle KSh 350; Fargo/G4S KSh 550; free Nairobi delivery over KSh 10,000 | Live |
| 5 | One-tap WhatsApp ordering with product, colour, quantity, county delivery quote and subtotal | Live |
| 6 | AI stylist chat, AI wig finder, wig quiz and in-browser virtual try-on | Live |
| 7 | Studio bookings: mornings only (cut-off 12:00), 50% deposit (minimum KSh 500) by M-Pesa, terms acceptance, 15-minute late rule, slot released if the deposit is not verified within 3 hours, one booking per phone per day; video/WhatsApp consultations free | Live (plugin 1.72.0) |
| 8 | Lipa Mdogo Mdogo payment plans: 50% deposit, balance within 1 or 2 weeks, terms v2.0, reminders, staff cannot go below the deposit floor | Live (plugin 1.71.0+) |
| 9 | NURA Desk back office: overview, point of sale, WhatsApp orders, plans, appointments with "Studio now" board, sourced sales and supplier ledger, reminders, marketing campaigns, social catalogue feeds, team, permissions, activity log, Excel/CSV exports | Live |
| 10 | Customer accounts: registration, order tracking, NURA Circle loyalty, care schedule, warranty certificates, e-mail 2-step verification, new-device alerts | Live |
| 11 | SEO: Kenyan-focused titles and descriptions, schema, sitemap, journal buying guides | Live |
| 12 | Marketing: abandoned-cart recovery, consent-based retargeting, newsletter, unsubscribe | Live |
| 13 | Security: rate limiting, input sanitisation, password policy, environment-based secrets, same-origin CORS, prepared SQL, session expiry, HTTPS + HSTS, security headers, safe uploads, encrypted contacts, staff permissions and audit log | Live (theme 1.64.0 / plugin 1.73.0) |

## 4. Non-functional requirements
- Mobile-first and fast: lazy images, cached pages (LiteSpeed), small scripts.
- Accessible labels, keyboard use and contrast.
- Secure by default (SECURITY-AUDIT.md); no secrets in code.
- Every release versioned, committed to GitHub and shipped as an installable zip.
- No feature removed without the owner's approval.

## 5. Open items
- Colour photos for the remaining colour options (owner).
- Products for Wig Care, Beauty and Hair Extensions categories.
- Two-factor authentication for administrator accounts (plugin install by owner).
- `NURA_DATA_KEY` in `wp-config.php` (host).
- Content-Security-Policy in report-only mode, then enforce.
