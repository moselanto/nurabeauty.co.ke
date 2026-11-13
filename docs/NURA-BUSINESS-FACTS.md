# NURA Beauty: Business Facts Register

Single source for business facts used by the website, schema, `/llms.txt`, checkout, WhatsApp templates, AI stylist and listings. **Only values marked Confirmed may be published in structured data.** Last updated 11 November 2026.

## Confirmed

| Fact | Value | Source |
| --- | --- | --- |
| Trading name | NURA Beauty (use everywhere customers see the brand) | Owner rule, HANDOVER 6 Nov 2026 |
| Registered business name | NURA CROWN & BEAUTY, BN-MJS7ALRV (Registration of Business Names Act, Kenya). Use only where a legal name is needed (schema legalName, Terms, About, Apple org) | Owner rule, HANDOVER 6 Nov 2026 |
| M-Pesa till | Buy Goods Till 1679269. The customer's M-Pesa confirmation message shows **NURA CROWN & BEAUTY** | Owner, 11 Nov 2026 (plugin 1.107.1) |
| Phone / WhatsApp | +254 714 994 898 | Live site |
| E-mail | care@nurabeauty.co.ke | Live site |
| Website | https://nurabeauty.co.ke | - |
| Physical shop / studio | Stanbank House, 2nd Floor, Room 20, Nairobi CBD, Nairobi, Kenya | Owner 1 Nov 2026; Google Business Profile updated 6 Nov 2026 |
| Previous location (do not publish as current) | Imenti House, Moi Avenue, Nairobi CBD | Superseded |
| Opening hours | Mon-Sat 9:00am-7:30pm, Sunday closed | Owner; Google Business Profile, 6 Nov 2026 |
| Walk-ins | Walk-ins and appointments welcome; studio services by morning appointment | Live site |
| Same-day Nairobi delivery | Order before 5 PM | Live site |
| Outside Nairobi | Same-day dispatch once paid; the shuttle/courier sets arrival. Never promise 1-3 days or same-day arrival | Owner rule, 6 Nov 2026 (theme 1.125.0) |
| Pay on delivery | Cash or M-Pesa, within Nairobi CBD only. All other deliveries paid in full, delivery fee included, before dispatch. Pay in person on studio pick-up | Owner, theme 1.112.0 / plugin 1.94.0 |
| Online payments | M-Pesa and Visa/Mastercard via Paystack, bank transfer, M-Pesa Till 1679269 for WhatsApp orders | Live site |
| Returns | Unworn, unaltered wigs within 48 hours of delivery, at the studio or by courier. Worn, cut or installed wigs not returnable | Live site |
| Pick-up | Available at the Stanbank House studio | Live site |
| International shipping | On request | Live site |
| Lipa Mdogo Mdogo | 50% deposit, balance within 1 or 2 weeks, terms v2.0 | PRD |
| Studio bookings | Mornings only, 50% deposit (minimum KSh 500); video and WhatsApp consultations free | PRD |
| Currency | KES | - |

## Pending owner confirmation (do not publish in schema until confirmed)

| ID | Fact | Current website value | Notes |
| --- | --- | --- | --- |
| D1 | Street and postcode | Moi Avenue, 00100 used in HANDOVER | Confirm against the lease or Google Business Profile before publishing in schema |
| D2 | Google Maps pin / coordinates | check schema emitters | Use the Google Business Profile pin for Stanbank House only; never reuse Imenti coordinates |
| D9 | AI training crawler access | all allowed | Search crawlers stay allowed |
| D10 | Product brands | "NURA" on every product | |
| D12 | Registration certificate address | BN certificate still shows Imenti House | Update with BRS (eCitizen) or use a Stanbank lease/utility bill for Apple Business |

Resolved since 1 November: D3 hours, D4 walk-ins, D5 cut-off (5 PM), D6 returns (48 hours), D7 pick-up, D8 international (on request), D11 Google Business Profile (moved, 6 Nov).

When a pending item is confirmed, move it to the Confirmed table with the date and source, update the registry setting, and log it in HANDOVER.md.
