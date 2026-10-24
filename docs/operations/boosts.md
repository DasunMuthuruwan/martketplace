# Running Paid Promotions (Boosts)

For the Owner and Admins. What sellers can buy, how they pay, and what staff do each day. Decisions: [ADR-026](../decisions/README.md); proposal: [paid-boosts.md](../proposals/paid-boosts.md).

## 1. What Sellers Can Buy

| Product | What it does |
|---|---|
| **Bump** | Moves the ad back to the top of the newest ads, once. The card keeps its real "posted" date. |
| **Top ad** (3, 7 or 15 days) | Shows the ad in the labelled **Top ads** block above the results of its category, in its country, matching the visitor's search and filters. At most 2 per page (Settings → Ads), rotated so every paying seller is seen. The ad also keeps its normal place. |

Only live ads can be promoted. A promotion ends with its ad (sold, deleted, expired, removed by a moderator, seller suspended) and does not come back.

## 2. Before Selling (One-Off)

1. **Accountant review (D6).** Confirm whether tax applies and what a receipt must contain in each country. Selling must stay off until this is done.
2. **Prices (D2).** Admin → Sales → **Boost prices** → choose the country → enter a price per product in its currency (empty = not offered there) → Save prices. Each product shows **On sale** or **Off**; **Switch off** stops selling that product in every country. The box at the top lists exactly what is still missing before sellers in the chosen country can buy.
3. **Bank account (D3).** Same screen: bank, branch, account name, account number, optional SWIFT and instructions → switch on "Accept bank transfers in this country" → Save. Sellers see these details with their order reference.
4. **Rules page (D4).** Admin → Pages → **Promotions and refunds**: write the rules (suggested text in §6, review it legally) and publish. New installs create this page (empty, unpublished) with the other standard pages; on an existing database run `php artisan db:seed --class=PageSeeder` once (it only adds missing pages), or create a page with the slug `promotions`. Links to it appear on the promote page and next to the Top ads once it is published.
5. **Switch selling on.** Owner: Admin → Settings → Boosts → "Sell boosts to sellers". The Boost prices screen then says, per country, whether sellers can buy.

Until then, staff can still **grant** promotions without payment: Admin → Sales → **Boosts** (goodwill, testing). Grants are audited; staff cannot boost their own ads.

## 3. Daily Work: Confirming Payments

1. Sellers order on their ad (**Promote**), get an order reference such as `BX7K2M9QP` and 7 days to pay by bank transfer.
2. Check the bank statement. For each incoming transfer, find the reference in Admin → Sales → **Orders** (the menu shows how many orders are unpaid).
3. Check the amount and currency against the order, then **Mark paid** (optional payment note, e.g. the statement line; the seller does not see it) → **Payment received**. The promotion starts at once and the seller gets a receipt.
4. If a promotion cannot start (the ad was sold or expired meanwhile), the order stays unpaid and the screen says why: refund the transfer and **Cancel order** with a reason.

Unpaid orders lapse automatically after 7 days (the seller is emailed). Sellers can also cancel their own unpaid orders. A seller can have at most 3 unpaid orders.

### Proof of Payment (B7)

Sellers can attach proof of their transfer to an unpaid order: a photo of the bank slip, a screenshot, or a PDF (JPG, PNG, WebP or PDF, up to 5 MB each, at most 3 files per order), with the transfer date and a note such as the bank's transaction reference. They can remove a file and attach another until the order is paid or closed; after that the proof is read-only.

- In Orders, unpaid orders **with proof are listed first** and marked **Proof received**. Use the **Proof** filter to see only orders with (or without) proof.
- **Proof (n)** opens the files: images are shown on the screen, PDFs are downloaded. The seller's transfer date and note are shown above them.
- **A slip or screenshot can be faked.** Mark an order paid only when the amount and the reference appear on the bank statement. The proof only helps you find the transfer.
- Open PDFs with an up-to-date PDF reader. PDFs containing scripts or attached files are refused at upload, but the check cannot see inside every compressed PDF.
- Files hold bank details: they are stored privately (never at a public address), only staff with the boosts permission can open them, and each view is recorded in the audit log.
- Files are deleted **90 days after the order is paid or closed** (`retention.payment_proof`); the order record stays.

## 4. Refunds (D4)

- **Refund** when the marketplace failed to deliver the promotion (for example, it was not shown because of a fault on our side).
- **No refund** for the published exceptions: the ad was removed for breaking the rules, or the seller sold or deleted it early.
- To refund: Orders → filter **Paid** → **Refund** → reason (the seller receives it) → **Refund and end**. The promotion ends. Then send the money back by bank transfer; the system does not move money.

## 5. Records

- Every grant, payment confirmation, cancellation, refund, price change and bank-account change is in the audit log (the account number itself is not copied there), as are proofs attached, removed and viewed by staff.
- Orders are financial records: they keep the reference, product, amount and currency even if the ad or the account is later deleted. **How long to keep them is a legal question** (often several years for accounting); it is listed with the other retention periods for the legal review. Nothing deletes orders yet.

## 6. Suggested Text for "Promotions and Refunds" (Review Before Publishing)

> **Promotions**
>
> You can promote a live ad. A **Bump** moves your ad back to the top of the newest ads once. A **Top ad** shows your ad, labelled "Top ad", above the results of its category for the days you choose, together with at most one other top ad, in rotation. Your ad also keeps its normal place.
>
> **Paying**
>
> You pay by bank transfer to the account shown with your order. Write your order reference in the transfer. Your promotion starts when we have seen your payment. Unpaid orders are cancelled after 7 days.
>
> **When a promotion ends early**
>
> A promotion ends with your ad: if it is sold, deleted, expires, or is removed for breaking our rules. It does not restart if the ad comes back.
>
> **Refunds**
>
> We refund your payment if we fail to show your promotion. We do not refund when your ad is removed for breaking our rules, or when you sell or delete it before the promotion ends.
>
> Questions: contact us.

## 7. Later (Not Built Yet)

Urgent badge, home page featured slots, bundles, dealer plans and payment gateways (D1, D3): see the proposal's phasing.
