# Proposal — Paid Boosts for Sellers

| Field | Value |
|---|---|
| Status | **Direction accepted, owner decisions D1–D7 recorded 2026-10-21 (§11, ADR-026).** Implementation after launch; no code before then. |
| Date | 2026-10-21 (agreed in principle by the owner the same day) |
| Timing | Implementation is a Phase 10 enhancement, **after launch**: boosts are worth paying for only once there are buyers. |
| Builds on | Requirements §"Later phases" (featured listings, dealer subscriptions), SRCH-01 (home featured slot placeholder), the renewal cool-down (renewing is not free bumping), architecture 02 (reserved `Billing/` module), database design (reserved `featured_listings` / `orders` / `subscriptions`), global marketplace rules (prices and gateways per country, never hardcoded) |

## 1. Summary

Sellers pay to make an ad more visible: move it back to the top, pin it above the normal results for a few days, or mark it "Urgent". Buyers still pay nothing and still contact sellers directly; the marketplace never handles money between buyers and sellers. Paid placements are clearly labelled, limited in number and never skip moderation, so buyers keep trusting the results.

## 2. Goals and Non-Goals

| Goals | Non-goals |
|---|---|
| Revenue from sellers who want to sell faster | Payments between buyers and sellers, escrow, commissions |
| Visible value for the seller (statistics before and during a boost) | Auctions, paid search ranking without a label |
| Fair results for buyers (labels, few slots, rotation) | Storing card details (hosted checkout only) |
| Works per country: own prices, currency and payment method | One global price list in one currency |

## 3. Products

First set decided by the owner (**bold**, D1), later additions in normal text.

| Product | What the seller gets | Duration | Notes |
|---|---|---|---|
| **Bump** | The ad moves back to the top of the "newest" order once | One-off | Cheapest; uses a separate sort time, so the original publication date stays honest |
| **Top ad** | Shown in a labelled block above the normal results of its category (and parent category) in its country, rotating with other top ads; also keeps its normal place | 3, 7 or 15 days | The main product |
| Urgent | "Urgent" badge on the card and the ad page | 7 days | Cheap add-on; follows the first set (D1) |
| Home page featured | One of a few slots on the country home page (the SRCH-01 placeholder) | 7 days | Limited slots; later |
| Bundles | e.g. Top ad 7 days + Urgent + one Bump, discounted | — | Later |
| Dealer / agent plans | Monthly: more active ads, included bumps, a shop page with logo | Monthly | Recurring revenue; needs subscriptions; later |

## 4. Placement and Trust Rules

1. **Labelled:** "Top ad" on the block, "Urgent" on the badge, and a short "Why am I seeing this?" note. Never presented as normal results.
2. **Limited:** at most 2 top ads per results page (owner setting), shown only with the default order ("newest"). Sorting by price shows normal results only.
3. **Relevant:** a top ad appears only in its own country and its category or parent category, and only when it matches the active filters (location, price, attributes).
4. **Rotating:** when more top ads exist than slots, the least recently shown go first, so every paying seller is seen.
5. **Moderation first:** only live (approved, public) ads can be boosted. Payment never skips review; a pending ad cannot be boosted yet.
6. **Ends with the ad:** if the ad is sold, deleted, expires or is removed by a moderator, the boost ends. Refunds follow D4: a refund when the marketplace fails to deliver the boost; the published exceptions (rule-breaking removal, the seller selling or deleting the ad early) are shown before purchase. A boost cannot run past the ad's expiry unless the ad is renewed first.
7. **Staff rules unchanged:** moderators still cannot decide on their own ads (CD-06); a boost does not change how an ad is moderated or reported.

## 5. Prices and Countries

- Prices are **data**, set per country in its currency by the Owner in the admin area (products × countries), following the global-marketplace rules. No price, currency or country appears in code.
- A country without prices simply does not offer boosts.
- Launch prices are an owner decision (D2); a small survey of what local classifieds charge is recommended first.

## 6. Payments

| Topic | Proposal |
|---|---|
| Approach | A **payment gateway interface** with one implementation per provider (create a hosted checkout, verify the provider's callback). Each country is linked to one provider in the admin area. |
| Card data | Never touches the server: the buyer pays on the provider's hosted page (keeps PCI scope to the simplest level). |
| Confirmation | Only a **verified callback (webhook)** from the provider activates a boost: signature checked, amount and currency compared with the order, each provider reference processed once (a repeated callback cannot activate twice). The browser's return page only shows the status. |
| Order states | pending → paid → active → ended; or pending → failed / expired; paid → refunded (the boost ends). Every change is written to the audit log. |
| First step option | **Bank transfer with manual confirmation**: the seller gets a reference, an admin marks the order paid after seeing the money. No gateway needed, good for testing demand. |
| Sri Lanka | A local gateway is needed (international providers often do not pay out to Sri Lankan merchants). Candidates such as PayHere must be checked by the owner: merchant requirements, fees, payout, test mode, callback signing, and that it works from this host (D3). |
| Other countries | Chosen when each country is activated (e.g. Stripe where it is available). |
| Receipts and tax | An emailed receipt with an order number for every payment. Whether VAT or another tax applies, and what an invoice must contain, is an owner and accountant question per country (D6). Payment records are kept for the legally required period (retention review). |

## 7. Seller Experience

1. A **"Promote"** button on the seller's live ads (My ads, the ad page when viewing one's own ad) and in the "your ad is live" email.
2. A simple page: the products with prices in local currency, what each does, the rules of §4 in plain words, then "Pay".
3. After payment: success, pending (bank transfer, or callback not yet received) or failed, with a clear next step.
4. In My ads: the active boost with its end date, and **statistics**: views per day before and during the boost.
5. Emails: receipt, boost started, boost ends tomorrow (with "Promote again"), boost ended.

## 8. Admin

- Products and prices per country; switch products on or off.
- Orders: search by number, seller or reference; filter by status, country and date; mark a bank transfer paid; mark refunded (ends the boost); every action audited.
- Grant a boost without payment (goodwill, testing), audited.
- Simple revenue report per country and month.
- Settings: top-ad slots per page, which products are on sale.

## 9. Data Model (Outline)

| Table | Purpose |
|---|---|
| `boost_products` | Product code, type (bump, top, urgent, featured), duration, active |
| `boost_prices` | Product × country: currency, amount, active |
| `orders` | Seller, listing, product, country, currency, amount, status, provider, provider reference (unique), timestamps for paid, refunded, expired |
| `listing_boosts` | Listing, type, order (nullable for granted boosts), starts and ends; indexed by type and end time for the search query |
| `listing_daily_views` | Listing, date, views: the before/during statistics |
| `listings.sorted_at` | New sort time for the "newest" order: equals the publication time until a Bump sets it to now |

Search: the top-ad block is a separate small query (country, category subtree, filters, active `top` boost, public ad), rotated by last shown time; the normal results are unchanged apart from ordering by `sorted_at`. A scheduled job ends expired boosts. Categories and pages that are cached are refreshed when a boost starts or ends.

## 10. Security and Abuse

- Callback endpoints are the **only** routes exempt from CSRF; they verify the provider's signature instead, and `CsrfProtectionTest` keeps an explicit allow-list so no other route can slip out.
- Checkout creation is rate-limited per account; amounts and currencies always come from the server's price table, never from the browser.
- Chargebacks and refunds end the boost; repeated chargebacks can suspend paid features for the account.
- Boosted ads are moderated and reportable like any other; a report-driven removal ends the boost.
- New rules in the Terms (paid services, refunds) and the Privacy Policy (the payment provider as a processor) need the owner's legal review.

## 11. Owner Decisions (2026-10-21)

| # | Decision | Owner's decision |
|---|---|---|
| D1 | Initial products | **Bump and Top ad**; Urgent can follow |
| D2 | Pricing | Research competitors, then configure per country in the admin area |
| D3 | Payments | **Manual bank transfer first**, a gateway later |
| D4 | Refunds | Refund when the marketplace fails to deliver the boost; publish clear exceptions (rule-breaking removal, early sale or deletion by the seller) |
| D5 | Top-ad slots | Maximum **2 per results page** |
| D6 | Tax and invoices | **Accountant review before accepting payments** (a gate before step 2 goes live) |
| D7 | Timing | **After launch**, when buyer activity supports paid visibility |

## 12. Phasing

| Step | Content | Size |
|---|---|---|
| 1 | Admin-granted Bump and Top ad (no payments): `listing_boosts`, top-ad block (2 slots), bump sort time, admin grant, statistics table | M |
| 2 | Products and prices per country, orders, **bank transfer with manual confirmation**, seller "Promote" flow, receipts and boost emails, published refund rules. **Goes live only after the accountant review (D6)** and the owner's competitor price research (D2) | L |
| 3 | Urgent badge; first payment gateway (callbacks, signatures, refunds), revenue report | M–L per gateway |
| 4 | Home featured slots, bundles | S–M |
| 5 | Dealer plans (subscriptions, shop pages, limits) | L |

Each step follows the usual workflow: one branch per task, tests (including the trust rules of §4 and the payment callback checks), owner review.

## 13. Measuring Success

Share of live ads boosted; repeat purchases; views and contact reveals during boosts compared with before; buyer complaints or reports about promoted ads; refunds and chargebacks.
