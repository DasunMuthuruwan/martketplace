# Seller Ratings, Live Search and Trends

For the Owner, Admins and Moderators: what the audience features do, the daily moderation work, the settings, and the texts to publish before launch. Decisions: [ADR-027](../decisions/README.md); proposal: [engagement-features.md](../proposals/engagement-features.md).

## 1. What Visitors See

| Feature | Where | How it works |
|---|---|---|
| **Live search** | Header and home search boxes | Suggests categories, makes and models, towns, and popular searches while typing. A search only appears as a suggestion after at least 5 searches, so rare or private searches never show. |
| **Popular and trending** | Country home page, search results | Most viewed ads this week, searches and models rising against last week, and models most often marked as sold (90 days). Nothing shows below the minimum counts (10 views, 5 searches, 3 sold). Lists refresh every hour. Paid promotions never count towards popularity. |
| **Most marked as sold** | Country home page | Counts what sellers marked as sold. We cannot check that a sale happened, so the label says "marked as sold". |
| **Make and model pages** | `…/make/toyota/aqua` style addresses, linked from ads and the sitemap | All ads for a make or model, with a price range once at least 5 are for sale. |
| **Share** | Ad page | Share to WhatsApp or Facebook, or copy the link. No phone number is ever in the shared text. |
| **Seller ratings** | Seller page, the seller card on each ad | 1–5 stars and an optional comment. The average shows from 3 reviews. |
| **Trusted seller badge** | Seller page, the seller card on each ad | Earned automatically (§4). |

## 2. Who Can Rate a Seller

Only a buyer who really contacted the seller:

- signed in, verified and active, account at least 7 days old;
- revealed the seller's phone number on one of their ads 2 to 90 days ago;
- not the seller.

One review per buyer and seller; the buyer can update it later. Stars publish at once. **Comments wait for a moderator.** The seller is emailed and may reply once (the reply is screened like ad text). The public page shows the buyer as first name and initial ("Nimal P.").

## 3. Daily Work: Moderating Reviews

Admin → Work → **Reviews** (the menu shows how many items are waiting). Owner, Admin and Moderator roles have access (permission `reviews.moderate`).

1. **Comments waiting.** Read each comment. **Approve** if it is about the buyer's experience with the seller. **Reject** if it contains insults, personal details (phone numbers, addresses, names of others), adverts, unrelated content or accusations of crimes. A rejected comment disappears; the stars stay.
2. **Reported reviews.** Anyone signed in (except the author) can report a review once: fake, abusive, personal details, or other. **Keep** it if the report is unfounded. **Remove** it, with a reason, if the review is fake (for example, no real contact or a competitor), abusive, or breaks the rules. A removed review no longer shows or counts towards the average.
3. **Removed.** The history of removed reviews and their reasons.

Rules for staff:

- You cannot decide on a review about you or written by you; another moderator must.
- Every decision is recorded in the audit log.
- If a seller says a review is false, ask for evidence (messages, dates). Remove the review only when the evidence is clear. Do not change review text; approve, reject or remove only.
- Legal demands about a review go to the Owner.

Aim to handle waiting comments within 2 working days.

## 4. Trusted Seller Badge

A seller gets the badge automatically when all of these are true:

| Rule | Default | Change it |
|---|---|---|
| Account age | 90 days or more | Owner: Admin → Settings → Seller trust |
| Ads marked as sold | 5 or more | Same |
| Reviews | 5 or more | Same |
| Average rating | 4.5 or more | Code configuration (`marketplace.reviews.trusted_min_average`) |
| Upheld reports | None about the seller or their ads in the last 6 months | — |

The badge updates within an hour. Staff cannot grant or remove it by hand: to remove a badge, act on the cause (remove fake reviews, uphold a report).

## 5. Search Counts and Privacy

Searches are counted per country, search text and day: no account, session, IP address or device is stored, and bots are not counted. A visitor counts once per search text and day. Search text containing a phone-length run of digits (7 or more) is never stored. Counts are deleted after 400 days.

## 6. Before Launch: Texts to Publish

Have these reviewed legally (requirements R9), then add them through Admin → Pages.

**Terms of use, a "Reviews" section (suggested text):**

> Buyers who contacted a seller through the marketplace may rate the seller and leave a comment about their experience. Reviews must be honest, based on your own dealings with the seller, and must not contain insults, personal details, adverts or accusations of crimes. Comments are checked before they appear. We may remove a review that breaks these rules or that we believe is not genuine. Sellers may reply once to each review. We do not verify that a sale took place, and ratings are the opinions of buyers, not ours. Do not offer or ask for anything in exchange for a review, and do not threaten a bad review. Accounts that do so may be suspended.

**Privacy policy, a "Searches" paragraph (suggested text):**

> We count the words people search for, per country and day, to show popular and trending searches and to improve search suggestions. These counts are not linked to you: we do not store your account, IP address or device with them, and searches containing phone numbers are not stored. A search only appears to other visitors after many people have made it. Counts are deleted after 400 days.

**Privacy policy, a "Reviews" paragraph (suggested text):**

> If you review a seller, your rating, comment, first name with the initial of your last name, and the date are shown publicly on the seller's page. We keep a record that you contacted the seller, to check that you may review them. You can change your review at any time.

## 7. Settings Reference

| Setting | Default | Where |
|---|---|---|
| Trusted seller: account days, ads sold, reviews | 90, 5, 5 | Admin → Settings → Seller trust (Owner) |
| Review window after contact | 2 to 90 days | `config/marketplace.php` → `reviews` |
| Minimum account age to review | 7 days | Same |
| Reviews before an average shows | 3 | Same |
| Reviews per buyer per day | 10 | Same |
| Searches before a term shows | 5 | `config/marketplace.php` → `search.min_searches` |
| Suggestion requests per minute per IP | 180 | `search.suggest_per_minute` |
| Views / sold before an item shows in trends | 10 / 3 | `trends` |
| Trend lists refresh | 60 minutes | `trends.cache_minutes` |
| Ads before a price range shows | 5 | `price_guide.min_ads` |
| Share service addresses | WhatsApp, Facebook | `SHARE_WHATSAPP_URL`, `SHARE_FACEBOOK_URL` in `.env` |
