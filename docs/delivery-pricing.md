# NURA Beauty delivery pricing (WhatsApp order form and checkout)

Current as of 27 Nov 2026, plugin 1.136.0. The WhatsApp order form and checkout use the same rules and the same price table. The free Nairobi delivery week (23 to 29 Nov) was ended early on 27 Nov (plugin 1.133.0).

## Where prices are set
- Rider table: NURA Desk > Rider fees (base KSh 300 within 5 km of the CBD, KSh 50 per km beyond, fixed area fees in the 4th column, free minimum KSh 10,000).
- Bus & shuttle, Fargo / G4S and pickup: WooCommerce > Settings > Shipping zones.

## Rider delivery
| Where | Fee |
|---|---|
| Nairobi CBD and within 5 km (e.g. Westlands) | KSh 300, free on orders of KSh 10,000+ |
| Kilimani (fixed) | KSh 500 |
| Karen (fixed) | KSh 800 |
| Other areas | KSh 300 + KSh 50 per km beyond 5 km (e.g. Kasarani 700, Embakasi 700, Ruaka 800, Ruiru 1,250, Juja 1,650, Kitengela 1,650, Thika 2,300) |
| Area not recognised | "From KSh 300", customer asked to type the estate |

The fee follows the typed area, not the county chosen.

## Other options
- Nairobi CBD delivery, pay on delivery (cash or M-Pesa): KSh 300, free on orders of KSh 10,000+. Pay on delivery only within 5 km of the CBD.
- Bus & shuttle parcel: KSh 350, free on orders of KSh 10,000+. Offered in every county; default outside Nairobi.
- Shuttle towns (Thika, Juja, Ruiru, Kitengela): Bus & shuttle is listed first and pre-selected on both the form and checkout (1.136.0); rider stays available. Filter `nurax_shuttle_first_areas`.
- Fargo Courier / G4S: KSh 550 at any order value.
- Studio pickup: free, Stanbank House, 2nd Floor, Room 20, Nairobi CBD.

## M-Pesa prompt (WhatsApp form)
The button reads "Send M-Pesa prompt - KSh X". The server charges the delivery option and typed area the customer selected (1.133.0 to 1.135.0).

## Known gap
Nairobi orders under KSh 10,000 with no area typed: checkout also lists "Nairobi CBD delivery - pay on delivery" at KSh 300; the form shows "Rider from KSh 300" and offers the CBD option only at KSh 10,000+. Same price either way.

## Pending owner decisions
- Fixed Thika rider fee (amount not given yet).
- Shuttle fee for Juja, Ruiru and Kitengela if different from KSh 350.
