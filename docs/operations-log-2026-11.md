# Operations log (non-code work done in wp-admin), Nov 2026

Code changes are recorded in HANDOVER.md. This file records data work done directly in wp-admin so the next person knows it happened. Customer details are kept in WooCommerce and NURA Desk, not here (this repository is public).

## Orders entered or corrected (13 to 26 Nov)
- Walk-in and till sales from 16 to 26 Nov entered as WooCommerce orders (#2573, #2576 to #2580, #2582 to #2585, #2589), all Completed, no customer emails, with M-Pesa codes as transaction IDs.
- Hidden helper products: 2574 Wig Cap (KSh 50), 2575 Wig Installation (KSh 1,000, virtual). The order-screen "Add fee" button does not work, so installs are entered as product 2575.
- #2582 is a KSh 0 PR order (source Other); stock was not reduced because stock tracking is off.
- Paid dates corrected to the M-Pesa dates on 26 Nov: #2589 (16 Nov), #2577 to #2580 (24 Nov), #2583 and #2584 (25 Nov).
- "Paystack API error" notes on manual M-Pesa orders can be ignored; those were not Paystack payments.

## NURA Desk Accounts
- Vouchers PV-0039 to PV-0054 recorded (stock, delivery, repairs, marketing, prayers, owner drawings, M-Pesa charges KSh 343.40, Paystack fees for #2272, #2520 and #2555).
- M-Pesa statement 13 to 26 Nov reconciled in Accounts > M-Pesa statement. Still open: 4 incoming payments not in the books and 2 amount differences, plus one KSh 730 outgoing payment, awaiting the owner's answers.
- Accounts warnings to resolve: sold items with no buying price; KSh 44,000 prepaid rent with no months set.

## Settings and offers
- Free Nairobi County delivery week (23 to 29 Nov) ended early on 27 Nov at the owner's request (plugin 1.133.0).

## Off-repo files (Town library, private)
- NURA Beauty Business Book v4 (sales, expenses, deliveries workbook).
- M-Pesa reconciliation document 13 to 26 Nov 2026.
- Delivery pricing document (copy in docs/delivery-pricing.md).

## Branches not merged
- `referral-programme` (commit 5a1e276): referral plugin built but not released; must be re-versioned above 1.136.0 and needs three owner decisions before merging.
