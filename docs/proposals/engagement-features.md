# Proposal — Seller Ratings, Live Search, Trending, and Audience Growth

| Field | Value |
|---|---|
| Status | **Accepted (owner, 2026-10-22: recommendations E1–E7, ratings included before deployment as originally requested). Built (G1–G6, 2026-10-23).** Plan: implementation plan §7b (G1–G6), ADR-027. Owner guide: [audience-features.md](../operations/audience-features.md). |
| Date | 2026-10-22 (requested by the owner before the deployment plan) |
| Builds on | Requirements (SRCH-09 saved searches, MSG-08 messaging, NFR-U5 Sinhala/Tamil ready, GLB-20 translations), global marketplace rules (everything per country, nothing hardcoded), paid boosts (ADR-026: view counts per ad and day already exist) |

## 1. Summary

Three requested features, then further ideas to grow the audience, each with its value, risks and size. The marketplace never handles money or deliveries between buyers and sellers, so features that depend on a confirmed sale (ratings, "most sold") need care: we cannot prove a sale happened.

## 2. Seller Ratings ("Catch Good Sellers")

**Goal:** buyers can tell trustworthy sellers apart; good sellers get credit and sell faster.

**The risk:** without a recorded sale, anyone could rate anyone: fake praise from friends, revenge or extortion ("pay me or I rate you 1 star"), competitors attacking each other. Unmoderated reviews are also a defamation risk (requirements R9).

**Proposal: ratings from real contacts only, plus objective trust signals.**

| Part | Rule |
|---|---|
| Who may rate | A signed-in, verified buyer who **revealed the seller's number for one of their ads** (already logged) between 2 and 90 days ago. One review per buyer per seller (they can update it). Never the seller, staff on their own ads, or accounts younger than 7 days. |
| What | 1–5 stars, optional short comment (plain text, screened by the blocklist like ads), and "Did you complete a deal? yes / no". |
| Before it shows | Comments go to the moderation queue (stars alone show at once); the seller can reply once; anyone can report a review. |
| Display | The average shows only from **3 reviews**; the count always ("4.6 ★ from 12 reviews"); newest first; the seller's page and the ad's seller card. |
| Trust badges (no reviews needed) | "Email verified", "Phone verified" (once SMS check exists), "Member since 2026", "Responds on WhatsApp", and **"Trusted seller"**: account 90+ days, at least 5 ads sold, average 4.5+ from 5+ reviews, no upheld reports in 6 months. Rules in settings. |
| Abuse controls | Rate limit, blocklist screening, moderation of comments, report review, staff remove with audit, a seller cannot see who rated before the review is published (no retaliation), same IP/device clusters flagged. |
| Legal | Terms section on reviews (honest, no personal data, we may remove); takedown process. Needs the owner's legal review. |

**Size:** M–L (reviews, eligibility, moderation, badges, seller page, ad card, tests).

## 3. Live Search

**Goal:** buyers find things faster; fewer empty searches.

| Part | Proposal |
|---|---|
| Suggestions while typing | From 2 letters, after a short pause: matching **categories**, **makes and models** (from the attribute options), **places** (towns, regions) and **popular searches** in the visitor's country, each with its result count where cheap ("Toyota Aqua · 124 ads"). Keyboard and screen-reader accessible (combobox pattern already in the design system). |
| Instant results count | Filters show "Show 86 ads" before applying. |
| Typos and spelling | Simple matching ("toyta" → Toyota) on makes/models and places via stored aliases; full fuzzy search waits for a search engine (scaling stage 4). |
| Performance and abuse | Small JSON endpoint, cached per prefix for a few minutes, rate-limited, no personal data. Works on shared hosting (no external service). |
| Search terms | To show popular searches we store **normalised search words per country and day, counted**, never with a user or IP. Very rare terms are never shown (minimum count), so private searches cannot leak. |

**Size:** M.

## 4. Trending, Popular, Most Sold

All per country, refreshed every hour or day, cached; shown on the country home page and category pages.

| Block | Meaning | Source |
|---|---|---|
| **Popular this week** | Most viewed live ads in the last 7 days | `listing_daily_views` (exists since boosts B1) |
| **Trending searches** | Search terms rising fastest this week against last week | counted search terms (§3) |
| **Trending makes / models** | Makes and models whose ads get the most views growth | views joined to the ad's make/model |
| **Most sold vehicles** | Makes and models most often **marked sold** in the last 90 days | sellers' "mark as sold"; labelled "most marked as sold by sellers" because a sale cannot be verified |
| **Price guide** (optional, strong for SEO) | Typical asking price range for a make/model/year ("Toyota Aqua 2015: Rs 6.2–7.1 M, 48 ads") | live and recent ads; shown only with enough ads; median and range, outliers removed |

**Rules:** minimum counts before anything shows (no tiny, misleading lists); paid Top ads are never mixed into "Popular" (it must stay honest); sold/expired ads excluded from "Popular".

**Size:** M (blocks, scheduled aggregates, caching); price guide +M.

## 5. More Ideas to Grow the Audience

Ordered by expected impact for a Sri Lankan launch that will expand to other countries.

| # | Feature | Why it helps | Size |
|---|---|---|---|
| 1 | **Sinhala and Tamil interface** | Most Sri Lankan buyers prefer their own language; the code is ready (all texts translatable). Needs translations, a language switch and search tuning for Sinhala script. | M + translation work |
| 2 | **Saved searches with alerts** (SRCH-09) | "Tell me when a Toyota Aqua under Rs 7 M appears in Colombo": brings buyers back daily; standard in classifieds. Email first, web push later. | M |
| 3 | **Make/model landing pages for search engines** | Pages like "Toyota Aqua for sale in Sri Lanka" with live ads, price guide and specs: free traffic from Google, the biggest source for classifieds. | M |
| 4 | **Installable app (PWA) and web push** | "Add to home screen", fast repeat visits, push alerts without app stores. | S–M |
| 5 | **Price drop alerts on saved ads** | Buyers who saved an ad hear when it gets cheaper; sellers sell faster. | S |
| 6 | **Share and WhatsApp-first sharing** | One tap to share an ad to WhatsApp groups and Facebook with a good preview (most local sharing happens there). | S |
| 7 | **Phone number verification by SMS** | "Phone verified" badge, fewer fake sellers. Needs an SMS provider per country (cost per message). | M |
| 8 | **Internal messaging** (MSG-08) | Buyers who do not want to call can ask questions; protects numbers; enables deal confirmation for reviews. Moderation and spam control needed. | L |
| 9 | **Compare ads** (vehicles) | Side by side up to 3 cars: price, year, mileage, fuel. | S–M |
| 10 | **Loan and lease calculator** | Monthly payment estimate on vehicle and property ads; later a partner bank could sponsor it. | S |
| 11 | **Recently viewed and "near me"** | Quick return to ads seen; sort by distance from the visitor's town. | S |
| 12 | **Dealer and agent shop pages** | Branded page with all their ads; pairs with the planned dealer plans (boosts phase 5). | M |
| 13 | **Weekly email digest** | "New in your area this week" for opted-in users. | S |
| 14 | **Privacy-friendly analytics** | To measure what works (searches, conversion to contact). Self-hosted or cookie-less; affects the privacy notice. | S |

## 6. Recommended Order

Launching sooner builds the audience that ratings and trending need; their data starts at zero on launch day anyway. Suggested split:

| When | What | Why |
|---|---|---|
| **Before launch** | Live search (§3), popular and trending blocks with minimum thresholds (§4, they appear as data grows), share to WhatsApp (5.6), make/model landing pages (5.3) | Immediate value for every visitor; SEO pages need time to be indexed, so start early |
| **Soon after launch** | Seller ratings and trust badges (§2), saved searches with alerts (5.2), price drop alerts (5.5), Sinhala and Tamil (5.1) | Ratings need real contacts to exist; translations need the owner's translators |
| **Later** | Price guide, PWA/push, SMS verification, messaging, compare, calculator, dealer pages, digest, analytics | By demand and budget |

## 7. Owner Decisions (2026-10-22: recommendations accepted)

| # | Decision | Recommendation |
|---|---|---|
| E1 | Which features before launch | Live search, popular/trending, WhatsApp share, make/model pages |
| E2 | Ratings model | Only buyers who revealed the seller's number; comments moderated; average shown from 3 reviews |
| E3 | "Most sold" wording | Label it "most marked as sold by sellers" (sales cannot be verified) |
| E4 | Storing search terms | Yes: counts per country and day, no user or IP, rare terms never shown |
| E5 | Sinhala and Tamil | Yes, soon after launch; who translates? |
| E6 | Trusted seller badge rules | 90+ days, 5+ sold, 4.5+ from 5+ reviews, no upheld reports in 6 months |
| E7 | Legal review | Review and Terms wording for reviews, privacy notice for search terms |
