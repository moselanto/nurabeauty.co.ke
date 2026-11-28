# Operations log (non-code work done in wp-admin), Nov 2026

Code changes are recorded in HANDOVER.md. This file records data work done directly in wp-admin so the next person knows it happened. Customer details are kept in WooCommerce and NURA Desk, not here (this repository is public).

## Orders entered or corrected (13 to 26 Nov)
- Walk-in and till sales from 16 to 26 Nov entered as WooCommerce orders (#2573, #2576 to #2580, #2582 to #2585, #2589), all Completed, no customer emails, with M-Pesa codes as transaction IDs.
- Hidden helper products: 2574 Wig Cap (KSh 50), 2575 Wig Installation (KSh 1,000, virtual). The order-screen "Add fee" button does not work, so installs are entered as product 2575.
- #2582 is a KSh 0 PR order (source Other); stock was not reduced because stock tracking is off.
- Paid dates corrected to the M-Pesa dates on 26 Nov: #2589 (16 Nov), #2577 to #2580 (24 Nov), #2583 and #2584 (25 Nov).
- 27 Nov: order #2597 created for the 20 Nov till payment UKKM42M8SP (KSh 3,200): 4x4 Loose Wave Hair Closure, Black (#2084) KSh 2,500 + Rider delivery shipping line KSh 700, Completed, paid 20 Nov. Statement row now matches #2597. Voucher PV-0053 (rider KSh 700) description now references #2597. A temporary placeholder product (#2596) was used and then moved to Trash.
- Stock: 4x4 Loose Wave Hair Closure Black (#2084) went from 0 to -1 after #2597; a physical count is needed (order #2573 also used Black, colour unconfirmed).
- "Paystack API error" notes on manual M-Pesa orders can be ignored; those were not Paystack payments.

- 28 Nov: 26 Nov sales entered: #2609 Grace Kelly install 1,000 (UKQE24S6LA), #2610 Alice Njoki install 1,000 (UKQ883LWRG), #2611 Serena Otieno 2x2 install 500 (UKQK53M5X2), #2612 Angel Wandia 2x2 install 500 (UKQ033TAX2), #2613 Joseph Muthama 2x2 Minky Closure colour 33, 2,000 (UKQMP3ZJIY). All Completed, source Studio walk-in, paid dates = M-Pesa dates.
- 28 Nov: 27 Nov sales entered: #2614 Nancy Murugami install 1,000 + Leonorah token 100 (UKRR83U5AM, 1,100); #2615 Weddy Gituma install 1,000 + band 200 + token 300 on one 500 fee line (UKRJR3TZPH, 1,500); #2616 Weddy Gituma Latisha Sensational Body Wave black 4,500 (UKRTL4ZGL5, paid from business till 3030259 STIMA DT); #2617 Noel Kinuthia 613 Bone Straight 4,500 + install 1,000 (UKR6I3WC7I); #2618 Serah Njenga glueless install 800 (UKRDY3P5KP); #2619 Lavenda Anyango 2x2 install 500 (UKR133IUKR).
- Order #2585 (26 Nov 10:25, cash install 1,000, no name) checked 28 Nov: not a duplicate of #2609/#2610 on the records; left unchanged pending staff confirmation.
- Vouchers PV-0039 to PV-0054 recorded (stock, delivery, repairs, marketing, prayers, owner drawings, M-Pesa charges KSh 343.40, Paystack fees for #2272, #2520 and #2555).
- M-Pesa statement 13 to 26 Nov reconciled in Accounts > M-Pesa statement. As of 27 Nov: money in 14 of 19 matched (KSh 7,500 not in the books), money out 11 of 12 matched (KSh 730 not in the books).
- Owner answers received 27 Nov, not yet recorded (Town credits ran out mid-task): Grace's KSh 1,800 (UKO2C3JB5K, 24 Nov) was a 2x2 closure colour 1/27 (needs its own order; her glue is #2580, UKO2C3JMNV); Alice's #2576: UKN8836XW4 KSh 3,500 was the Loose Wave Frontal (Black) and the KSh 50 wig cap was cash; Leonorah's KSh 730 (UKL5Q3TP2H, 21 Nov) was for the delivery of the KSh 3,200 order (conflicts with PV-0053, KSh 700 on 23 Nov for the same delivery; to clarify). Weddy KSh 300 and Joy KSh 900: owner will confirm. Esther #2573: owner asked which payment covers the KSh 2,500 closure (UKKKQ3K9T3 KSh 1,000 was the install).
- 28 Nov vouchers: PV-0055 (27 Nov, 820, UKR5Q4P3MV: delivery 100, wipes 155, band 55, wig cap 240, wipes 170, Mary Bingo delivery of 26 Nov 100); PV-0056 (27 Nov, phone repair for Leonorah, 2,000 to Charles Maina, UKR5A4JKPD); PV-0057 (27 Nov, same repair, 1,000 office cash); PV-0058 (26 Nov, sales/expense books from Mart Enterprise Ltd, 1,000 till, UKQFX3ZURP); PV-0059 (26 Nov, same books, 1,500 paid by the owner, category Owner capital in, not an expense); PV-0060 (27 Nov, Leonorah tokens 400 paid out, UKR5Q4O4SG, Salaries & wages); PV-0061 (27 Nov, withdrawal charges 28); PV-0062 (27 Nov, receipt charges 48.95: UKR6I3WC7I 30.25, UKRJR3TZPH 8.25, UKRR83U5AM 6.05, UKRDY3P5KP 4.40); PV-0063 (26 Nov, charges 29.00: UKQMP3ZJIY 11, UKQE24S6LA 5.50, UKQFX3ZURP 7, UKQ883LWRG 5.50). Already in the books, not re-entered: UKP5Q4F5TR (PV-0042), UKQ5Q4FI7V (PV-0043).
- PV-0049 (343.40) covers statement charges only up to 26 Nov 00:53 (UKQ5Q4FI7V); later 26 Nov charges are PV-0063. Statement 11-28 Nov charges (449.35) are fully booked (plugin 1.138.0 card green). The statement has no 28 Nov transactions; 28 Nov charges need a new export.
- NURA Desk daily totals after these entries: 26 Nov sales 6,000 (incl. #2585 cash), 27 Nov sales 13,900, expenses 4,296.95, net 9,603.05 (provisional: items sold without buying prices). Closing till balance per statement: 26 Nov 28,489.60; 27 Nov 39,092.65.
- Accounts warnings to resolve: sold items with no buying price; KSh 44,000 prepaid rent with no months set.

## Settings and offers
- Free Nairobi County delivery week (23 to 29 Nov) ended early on 27 Nov at the owner's request (plugin 1.133.0).

## Off-repo files (Town library, private)
- NURA Beauty Business Book v4 (sales, expenses, deliveries workbook).
- M-Pesa reconciliation document 13 to 26 Nov 2026.
- Delivery pricing document (copy in docs/delivery-pricing.md).

## Security settings
- 28 Nov: private staff login address changed in NURA Desk > Settings > Staff sign-in (the address is intentionally not written here). Only staff account: moses (admin), authenticator app set up.

## Branches not merged
- `referral-programme` (commit 5a1e276): referral plugin built but not released; must be re-versioned above 1.136.0 and needs three owner decisions before merging.
