# Product Requirements - NURA Beauty online store

## Vision
The most trusted, best-looking wig store in Kenya, with an international, professional feel: premium wigs, clear prices, fast delivery and real stylist help.

## Users
- **Shoppers in Nairobi** who want same-day delivery and cash on delivery.
- **Shoppers across Kenya** who need countrywide courier delivery and M-Pesa.
- **Diaspora and East Africa buyers** (shipping quoted).
- **Salon and wholesale buyers** (bulk and corporate orders).
- **NURA staff** managing products, orders, leads, appointments and content.

## Core requirements

| # | Requirement | Status |
| --- | --- | --- |
| 1 | Catalogue of human hair, human hair blend and heat-resistant fibre wigs with colour variations and per-colour photos | Live (some colour photos pending from owner) |
| 2 | Clear KES pricing, stock and categories (headband, closure, lace front, bob, curly, jerry curl, etc.) | Live |
| 3 | Checkout with required phone, M-Pesa, card, bank transfer; cash on delivery Nairobi only | Live |
| 4 | Delivery: same-day Nairobi before 5:00 PM; rider from KSh 300; bus/shuttle KSh 350; Fargo/G4S KSh 550; free Nairobi delivery over KSh 10,000 | Live |
| 5 | One-tap WhatsApp ordering with product, colour, quantity and subtotal | Live |
| 6 | AI stylist chat, AI wig finder and virtual try-on | Live |
| 7 | Appointments for consultation, fitting and installation at Imenti House, Moi Avenue, Nairobi CBD | Live |
| 8 | Customer accounts with registration, order tracking, NURA Circle loyalty, care schedule and warranty certificates | Live |
| 9 | SEO: Kenyan-focused titles and descriptions, schema, sitemap, buying guides in the Journal | Live |
| 10 | Branded, professional login screen | Live (theme 1.52.0+) |
| 11 | Security: login lockout, bot traps, rate limits, password policy, session expiry, HTTPS, headers, CORS | Theme 1.53.0 / plugin 1.55.0 |
| 12 | Marketing: abandoned-cart recovery, consent-based retargeting, unsubscribe | Live |

## Non-functional requirements
- Mobile-first, fast (lazy images, cached pages), accessible labels and contrast.
- Secure by default (see SECURITY-AUDIT.md); no secrets in code.
- Every release versioned, committed to GitHub and shipped as an installable zip.

## Open items
- Colour photos for the remaining colour options (owner to supply).
- Products for the Wig Care, Beauty and Hair Extensions categories.
- Phone-size visual audit.
- Content-Security-Policy in report-only mode, then enforce.
- Two-factor authentication for admin accounts.
