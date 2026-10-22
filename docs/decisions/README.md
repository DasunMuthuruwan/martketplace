# Architecture Decision Log

Status values: **Accepted** (owner-confirmed) · **Accepted with conditions** (owner-confirmed, verification pending) · **Proposed** (awaiting approval) · **Superseded**.
Add new entries at the bottom. Never rewrite an accepted decision. Supersede it with a new entry instead.

Owner approval of CD-01 to CD-12: 2026-10-11 (conditions recorded in [architecture §12](../architecture/02-system-architecture.md)).

---

### ADR-001 — Laravel 13 on PHP 8.4 (8.3 minimum)
- **Status:** Accepted with conditions (CD-01). Verify that the host offers PHP 8.3/8.4 and the required extensions for the subdomain.
- **Context:** [VERIFIED] Laravel 13 requires PHP ≥ 8.3, security fixes until 17 Mar 2028. PHP 8.3 security ends 31 Dec 2027. PHP 8.4 security ends 31 Dec 2028. PHP 8.2 security ends 31 Dec 2026. Laravel 12 security ends 24 Feb 2027. Host evidence: `ea-php83`.
- **Decision:** Laravel 13. PHP 8.4 preferred, 8.3 accepted. `config.platform.php` pinned to the production minor.
- **Consequences:** A second local PHP install is needed (owner approval). If the host can only run 8.2, the whole package set falls back (see [package assessment](../architecture/10-package-assessment.md) §4). That would need a new decision.

### ADR-002 — Server-rendered monolith: Blade + Livewire 4 + Tailwind
- **Status:** Accepted (owner Q13)
- **Decision:** Blade + Livewire 4 + Alpine, Tailwind 4 via Vite, assets prebuilt. No React, Vue or Inertia.

### ADR-003 — Independent marketplace authentication
- **Status:** Accepted (owner Q2)
- **Decision:** Own `users` table and own database. Fortify backend. Cookie `dtm_session`, host-only. Nothing shared with Dev Talk. SSO is a future module.

### ADR-004 — Typed EAV for category attributes + JSON summary
- **Status:** Accepted (CD-04). Load test pending.
- **Decision:** Typed value tables with composite indexes, schema-driven validation, and `listings.attributes_summary` for cards and search results. Generated hot columns only if load tests require them.

### ADR-005 — MySQL FULLTEXT search behind an interface
- **Status:** Accepted (part of the approved architecture)
- **Decision:** `ListingSearch` interface with a MySQL implementation. Meilisearch can be swapped in later (scaling Stage 3).

### ADR-006 — No dependency on an every-minute cron
- **Status:** Accepted. The host's cron interval is unverified.
- **Decision:** Synchronous image processing, query-time expiry, slow cron + optional web runner (`WEB_RUNNER_ENABLED`), idempotent jobs.

### ADR-007 — spatie/laravel-permission ^8.3 for roles and permissions
- **Status:** Accepted (CD-05, Q9)
- **Decision:** Owner/Admin/Moderator. Code checks permissions only. Policies for ownership. Hierarchy and last-owner guards.

### ADR-008 — Image pipeline: re-encode to 2 variants, never keep originals
- **Status:** Accepted with conditions (CD-10). Verify GD WebP support, memory limit and inode/disk quota.
- **Decision:** intervention/image ^4.3 (GD default). WebP with JPEG fallback. `lg` ≤ 1600 px, `sm` ≤ 480 px. Max 8 images (10 vehicles/property), 8 MB each. Full content, MIME, dimension and pixel validation. Clean failure handling.

### ADR-009 — Email: env-based provider; production sending gated
- **Status:** Accepted with conditions (CD-09)
- **Decision:** Host SMTP for development. Evaluate Brevo for production. `MAIL_SENDING_ENABLED=false` until configuration and deliverability are verified.

### ADR-010 — Cloudflare Turnstile + honeypot for bot protection
- **Status:** Accepted (owner preference Q8). Verify outbound HTTPS.
- **Note:** Works without Cloudflare DNS. No package needed (HTTP client).

### ADR-011 — Contact via phone/WhatsApp only, hidden by default; messaging deferred
- **Status:** Accepted (owner Q6, Q10)

### ADR-012 — Translatable DB labels from day one
- **Status:** Accepted (CD-05: spatie/laravel-translatable ^6.14)

### ADR-013 — Marketplace has its own visual theme
- **Status:** Accepted (owner instruction, 2026-10-11)

### ADR-014 — Domain and hosting portability
- **Status:** Accepted (owner, 2026-10-11)
- **Context:** The owner will move the marketplace to another domain and hosting later.
- **Decision:** Portability rules P1–P11 ([domain migration and portability](../architecture/11-domain-migration-and-portability.md)): `APP_URL` is the only canonical host, no hardcoded domain (CI guard test), no absolute URLs in the DB, `TrustHosts`, host-only cookies, env-driven drivers and media URL, path-preserving 301 redirect strategy, `APP_KEY` preserved, documented runbook and rollback.
- **Consequences:** Small upfront discipline. A domain change becomes a configuration + runbook exercise.

### ADR-015 — TOTP 2FA only; passkeys disabled for now
- **Status:** Accepted (owner, 2026-10-11), including the recovery process and the recommendation to keep two Owner accounts (one offline break-glass)
- **Context:** Fortify supports TOTP and passkeys. Passkeys are bound to the domain (`relying_party_id`), so the planned domain migration would invalidate all registered passkeys.
- **Decision:** TOTP + recovery codes, mandatory for Owner/Admin. Reset by the Owner, or the break-glass CLI for a sole Owner. Revisit passkeys after the final domain is settled.

### ADR-016 — Own sitemap generator instead of spatie/laravel-sitemap
- **Status:** Accepted (owner, 2026-10-11)
- **Context:** spatie/laravel-sitemap v8 requires PHP 8.4. v7.4 works but pulls Guzzle, spatie/crawler and symfony/dom-crawler for features we don't use.
- **Decision:** ~100-line `SitemapBuilder` writing static sitemap index + chunk files from `APP_URL`. *Alternative:* v7.4 if the owner prefers a package.

### ADR-017 — Material listing edits return to moderation
- **Status:** Accepted (owner, 2026-10-11). Q-MOD-1 resolved: price edits apply immediately and appear in the moderation "recent changes" feed.
- **Decision:** Material edits on active listings → `pending_review` (hidden until re-approved). Contact toggles, sold and renew apply immediately. Price edits apply immediately but appear in a moderation "recent changes" feed (owner to confirm, Q-MOD-1).
- **Alternative rejected for the MVP:** pending revisions with the approved version still live (more schema and image versioning).

### ADR-018 — Land price per perch is calculated, never stored as input
- **Status:** Accepted (owner, 2026-10-11). **Generalised by ADR-021** (global units): the calculated-price rule stays, perch-only normalisation is replaced.
- **Context:** Land in Sri Lanka is compared by price per perch. The owner wants both the total price and the price per perch shown for Land, with no risk of inconsistent values.
- **Decision:** Total price and land area (normalised to perches) are the source of truth. The per-perch value is computed at render time by one calculator (`UnitPrice`), with null-safe guards (no division unless area > 0). It is enabled per category through `categories.unit_price_attribute_id` (Land only). Free and on-request price types are disabled for Land. Details: [category tree](../architecture/04-category-tree.md) §3.2.1.
- **Consequences:** No per-perch sorting or filtering in the MVP. A server-maintained derived column can be added later without changing the source of truth.

### ADR-019 — Multi-country marketplace with configurable country master data
- **Status:** Proposed (owner requirement 2026-10-11; design awaiting approval)
- **Context:** The owner clarified the marketplace is global, not Sri Lanka-only. Supersedes assumption A1.
- **Decision:** `countries` table (ISO codes, default currency, number locale, phone code, timezone, region levels and labels, require flags, land units, active flag). All countries seeded inactive. The Owner activates countries. Categories, attributes and options can be scoped to countries. Prohibited terms can be per country. No country-specific logic in code; an automated test guards against hardcoded `LKR`, `Sri Lanka`, `+94`, `perch` outside seed and lang files. Details: [global design](../architecture/13-global-marketplace.md).

### ADR-020 — Listing money: DECIMAL(18,3) + ISO 4217 currency on the listing, ICU formatting
- **Status:** Proposed. **Supersedes** the "whole LKR bigint" storage.
- **Decision:** `listings.price DECIMAL(18,3)`, `listings.currency CHAR(3)` (allowed per country via `country_currencies`; MVP default only). Decimals validated against the currency's minor units. bcmath for arithmetic. Formatting with PHP intl (ICU) using the listing country's locale + `currencies.symbol_override` (`LKR → Rs`) + auto decimals. No silent conversion. Future conversion is display-only from `exchange_rates`, with rate and timestamp.

### ADR-021 — Area units configurable per country; land price per configured unit
- **Status:** Proposed. **Generalises ADR-018** (calculated, never-entered per-unit price stays; perch-only normalisation is replaced).
- **Decision:** `measurement_units` (factor to m²) + `country_measurement_units`. The area attribute stores entered value + unit (source of truth) and normalised m² (filters). Per-unit price in `countries.land_price_unit_id` (LK: perch), hidden when null. Same guards (area > 0, price > 0, sale/rent only).

### ADR-022 — Location data from GeoNames (CC BY 4.0), curated per active country
- **Status:** Proposed
- **Decision:** Countries/regions (admin1/admin2)/cities imported from GeoNames by artisan commands on dev/staging, curated in Admin → Locations, deployed as seed data. Attribution shown on the site. Own primary keys; `geonames_id` kept for re-sync; never delete referenced places (deactivate). ODbL datasets (dr5hn) are avoided because of share-alike obligations.
- **Evidence:** GeoNames covers admin1 for all countries (3,865 rows) and matches the owner's examples (LK province → district; GB England → Greater London). Sri Lankan towns need curation (Nugegoda/Kottawa missing from `cities1000`; data errors in the full dump).

### ADR-023 — Country-prefixed browse URLs; listing URLs unchanged
- **Status:** Proposed
- **Decision:** `/{cc}`, `/{cc}/ads/{category?}/{subcategory?}`, location landing pages `/{cc}/ads[/{category}]/in/{region}[/{city}]`, global `/ads`. The listing URL `/ad/{slug}-{id}` stays as approved (CD-12). Canonicals from `APP_URL`.

### ADR-024 — UI/UX owner decisions Q-UX-1 to Q-UX-6
- **Status:** Accepted (owner, 2026-10-14). The overall Phase 3 design still awaits approval.
- **Decision:** (1) Dev Talk shown only as a configurable footer line "A Dev Talk service" → `https://dev-talk.com`, no Dev Talk branding. (2) Product name from `APP_NAME` / the site-name setting, working name "Marketplace", text wordmark + monogram, replaceable logo files; no hardcoded name; no domain or permanent brand without approval. (3) Mobile bottom navigation Home · Search · Post · Saved · Account, Post emphasised, sign-in where needed, safe-area aware, hidden from 1024 px. (4) Full prices in the listing's own currency and the country's number format, never abbreviated or converted; land shows the price per the country's land unit. (5) Geist self-hosted after licence verification, system UI fallback, one typography system for public, account and admin. (6) Card columns by available width: 2 phones, 3 tablets and desktop with filters, 4 wide pages.
- **Details:** `docs/design/README.md` (owner decisions), design system §2, §4, §6; components §3.1, §5.1.

### ADR-025 — Browser tests with Playwright (D-1), axe and Lighthouse as dev tools
- **Status:** Accepted (owner, 2026-10-16)
- **Context:** F6.1 needs automated accessibility scans and Lighthouse scores; Phase 7 needs browser end-to-end tests. D-1 was open between Laravel Dusk and Playwright.
- **Decision:** Playwright (`@playwright/test`) with `@axe-core/playwright`, and `lighthouse`, as npm dev dependencies. Tests in `tests/Browser` (`npm run test:browser`), Lighthouse via `npm run lighthouse`. They run against `php artisan serve` with a separate SQLite database seeded by `BrowserTestSeeder` (environment overrides for those processes only; `.env` untouched). Browser: the installed Microsoft Edge by default (`PLAYWRIGHT_CHANNEL=msedge`), or Playwright's Chromium with `PLAYWRIGHT_CHANNEL=chromium` after `npx playwright install chromium` (the download did not complete on the development machine).
- **Consequences:** Dev-only; nothing is deployed. Production assets must be built first. Lighthouse Performance on the single-threaded PHP development server is a lower bound, not the production score. CI can add a browser job later (Chromium download needed there).

### ADR-026 — Paid boosts: products, payments and rules (owner decisions D1–D7)
- **Status:** Accepted (owner, 2026-10-21). **D7 changed the same day: built before deployment** (implementation plan §7a), with selling switched off until the accountant review (D6) and prices (D2).
- **Context:** Seller-paid visibility is the planned revenue model (requirements, later phases). Proposal: `docs/proposals/paid-boosts.md`.
- **Decision:** First products Bump and Top ad, Urgent later (D1). Prices researched against competitors and configured per country as data (D2). Payment by manual bank transfer with admin confirmation first, a gateway later (D3). Refund when the marketplace fails to deliver; published exceptions (D4). At most 2 top ads per results page, labelled, rotated, relevant to country, category and filters (D5). Accountant review of tax and invoices before accepting any payment (D6). Start after launch when buyer activity supports it (D7). Only live, moderated ads can be boosted; payments never skip moderation; card data never reaches the server; a gateway activates boosts only through verified, idempotent callbacks.
- **Consequences:** New `Billing` module and tables (`boost_products`, `boost_prices`, `orders`, `listing_boosts`, `listing_daily_views`, `listings.sorted_at`); payment callbacks become the only CSRF-exempt routes, with signature checks and an explicit test allow-list; Terms and Privacy Policy updates need legal review.

### ADR-027 — Seller ratings, live search, trending and audience features (owner decisions E1–E7)
- **Status:** Accepted (owner, 2026-10-22). Built before deployment: live search, popular/trending/most-marked-sold blocks, share to WhatsApp and Facebook, make/model landing pages with a price guide, seller ratings and trust badges. After launch: Sinhala and Tamil (translator to be named), saved-search and price-drop alerts, PWA/push, SMS verification, messaging and the other ideas in the proposal.
- **Context:** `docs/proposals/engagement-features.md`. The marketplace never records a sale, so ratings and "most sold" cannot rely on a verified transaction.
- **Decision:** Ratings only from verified buyers who revealed the seller's number 2–90 days before, one per buyer and seller, comments moderated, the seller replies once, average shown from 3 reviews (E2). "Most sold" is labelled "most marked as sold by sellers" (E3). Search terms are stored only as counts per country and day, without user or IP; rare terms are never shown (E4). Trusted seller badge: account 90+ days, 5+ ads sold, average 4.5+ from 5+ reviews, no upheld reports in 6 months, values in settings (E6). Review rules in the Terms and the search-count note in the privacy notice need the owner's legal review (E7). Popular lists never include paid placements. Everything per country, with minimum counts before a list shows.
- **Consequences:** New tables for search term counts, reviews and their moderation; scheduled aggregates; new public pages (make/model) in the sitemap; Terms and privacy notice updates (owner content).

