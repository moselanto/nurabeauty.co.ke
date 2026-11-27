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

## NURA Desk Accounts
- Vouchers PV-0039 to PV-0054 recorded (stock, delivery, repairs, marketing, prayers, owner drawings, M-Pesa charges KSh 343.40, Paystack fees for #2272, #2520 and #2555).
- M-Pesa statement 13 to 26 Nov reconciled in Accounts > M-Pesa statement. As of 27 Nov: money in 14 of 19 matched (KSh 7,500 not in the books), money out 11 of 12 matched (KSh 730 not in the books).
- Owner answers received 27 Nov, not yet recorded (Town credits ran out mid-task): Grace's KSh 1,800 (UKO2C3JB5K, 24 Nov) was a 2x2 closure colour 1/27 (needs its own order; her glue is #2580, UKO2C3JMNV); Alice's #2576: UKN8836XW4 KSh 3,500 was the Loose Wave Frontal (Black) and the KSh 50 wig cap was cash; Leonorah's KSh 730 (UKL5Q3TP2H, 21 Nov) was for the delivery of the KSh 3,200 order (conflicts with PV-0053, KSh 700 on 23 Nov for the same delivery; to clarify). Weddy KSh 300 and Joy KSh 900: owner will confirm. Esther #2573: owner asked which payment covers the KSh 2,500 closure (UKKKQ3K9T3 KSh 1,000 was the install).
- Accounts warnings to resolve: sold items with no buying price; KSh 44,000 prepaid rent with no months set.

## Settings and offers
- Free Nairobi County delivery week (23 to 29 Nov) ended early on 27 Nov at the owner's request (plugin 1.133.0).

## Off-repo files (Town library, private)
- NURA Beauty Business Book v4 (sales, expenses, deliveries workbook).
- M-Pesa reconciliation document 13 to 26 Nov 2026.
- Delivery pricing document (copy in docs/delivery-pricing.md).

## Branches not merged
- `referral-programme` (commit 5a1e276): referral plugin built but not released; must be re-versioned above 1.136.0 and needs three owner decisions before merging.
