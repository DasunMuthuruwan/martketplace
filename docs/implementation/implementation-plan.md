# Implementation Plan

| Field | Value |
|---|---|
| SDLC phase | 4 — Implementation planning |
| Status | **Draft — awaiting owner approval. No code has been written.** |
| Date | 2026-10-11 |
| Inputs | [Requirements v0.4](../requirements/requirements-specification.md), [architecture](../architecture/README.md) (incl. [global design](../architecture/13-global-marketplace.md)), [decision log](../decisions/README.md), [UI/UX design](../design/README.md) |
| Task tracking | [task-checklist.md](task-checklist.md) (updated after every task) |

## 1. Planning Assumptions (Defaults Pending Owner Decisions)

The owner started Phase 4 before answering the global (G-1–G-13) and UI (Q-UX-1–6) questions. This plan uses the **recommended defaults** below. Each one is listed with the first task it affects. Those tasks are **gated**: they don't start until the owner confirms or changes the default.

| Decision | Default used in this plan | First gated task |
|---|---|---|
| G-1 Launch countries | Build multi-country. Activate **Sri Lanka** only at launch. | T1.4 (curated data), T9.x (launch) |
| G-2 Default country | Remembered choice → Cloudflare header (if proxied) → Sri Lanka | T1.6 |
| G-3 Location levels | LK Province → District → Town → Area. US/IN 1 level. GB 2 levels. | T1.2 |
| G-4 Currencies per country | Default currency only | T1.1 |
| G-5 Price formatting locale | Listing country's locale | T1.5 |
| G-6 Land units | LK perch/rood/acre/hectare, price per perch. Others per the global design §5. | T1.5 |
| G-7 Global search | Yes. Price filters need a currency. | T5.1 |
| G-8 URLs | Country-prefixed browse URLs (ADR-023) | T5.2 |
| G-9 Dataset | GeoNames (CC BY 4.0) + curation | T1.3 |
| G-10 / G-12 Legal and moderation per country | Required before activating any country beyond Sri Lanka | Launch checklist |
| G-11 Packages | `propaganistas/laravel-phone`, `flag-icons` approved | T0.5, F1.1 |
| G-13 Country scoping | `scope_countries` on categories/attributes/options | T3.1 |
| Q-UX-1 Dev Talk mention | Footer text only | F1.3 |
| Q-UX-2 Name/logo | `APP_NAME` = "Marketplace" placeholder + monogram | F1.1 |
| Q-UX-3 Bottom nav | Yes | F1.3 |
| Q-UX-4 Card prices | Full figures | F2.2 |
| Q-UX-5 Typeface | Geist | F1.1 |
| Q-UX-6 Card density | 2 / 3 / 4 columns | F2.2 |

## 2. Gate 0 — Before Any Code (Owner Actions + Approvals)

| # | Item | Why it blocks | Who |
|---|---|---|---|
| G0.1 | **Approve this plan** | CLAUDE.md: no coding before approval | Owner |
| G0.2 | **Verify that the host offers PHP 8.3/8.4** for a subdomain (cPanel → MultiPHP Manager) and record the MySQL/MariaDB version | Laravel 13 needs PHP ≥ 8.3. If the host can't, the stack falls back to Laravel 12 (a re-plan, ADR-001). Cheap to check now, expensive to discover later. | Owner (screenshots are enough) |
| G0.3 | **Composer choice:** update global Composer to 2.10.3 (owner runs the documented steps) **or** approve a marketplace-only `C:\php8.4\composer.phar` | Scaffolding needs a Composer free of the known advisories | Owner decision |
| G0.4 | Approve creating a **local database** `marketplace` + user on the existing local MariaDB (no other DB touched) | Feature tests use SQLite, but local runs need MySQL | Owner |
| G0.5 | Approve an **initial commit** of the docs on `main` and the branch workflow (§3) | Version control before code | Owner |
| G0.6 | Confirm or change the defaults in §1 (can be done gradually; only the gated tasks wait) | — | Owner |

Remaining hosting checks (SSH, symlinks, cron interval, inodes, mail limit, outbound HTTPS) are needed by Milestone 9, not before coding.

## 3. Workflow and Definition of Done

**Branching:** `main` stays deployable and only receives reviewed merges. One branch per task: `feature/T2.1-fortify-auth`. Commits are small and imperative ("Add currency formatter with symbol overrides"). The owner reviews each task (diff + test report) before merge. No force-push to `main`.

**Definition of Done (every task):**
1. Scope implemented as written, nothing extra.
2. Automated tests for the task pass. The full suite stays green.
3. `vendor/bin/pint --test` and `vendor/bin/phpstan` (Larastan level 5+) are clean.
4. Server-side validation and authorization are covered by tests where the task touches them (positive **and** negative cases).
5. No hardcoded domain, country, currency or unit (guard tests from T0.4 stay green).
6. Docs updated where behaviour or decisions changed. [task-checklist.md](task-checklist.md) and [PROGRESS.md](../PROGRESS.md) updated.
7. Report to the owner: changed files, test output, limitations, next task.

**Sizes:** S ≈ half a day · M ≈ 1–2 days · L ≈ 3–4 days of focused work. Sizes are for comparing and ordering tasks, not commitments.

## 4. Milestone Overview

| Milestone | SDLC phase | Goal | Depends on |
|---|---|---|---|
| **M0** Foundation | 5 | Laravel 13 app, tooling, CI, security and portability baseline, packages | Gate 0 |
| **M1** Geo and money | 5 | Countries, currencies, locations, units, formatting, country context | M0 |
| **M2** Accounts and roles | 5 | Registration/login/verification, roles, 2FA, account settings | M0 |
| **M3** Catalogue | 5 | Categories, attributes, options, country scoping, schema engine | M1 |
| **M4** Listings core | 5 | Draft → submit → moderate lifecycle, images, contact, favourites, reports | M1–M3 |
| **M5** Search and SEO | 5 | Search engine, country/location routes, SEO, sitemaps, performance check | M4 |
| **M6** Admin backend | 5 | Moderation, users/staff, catalogue, locations, settings, audit | M2–M5 |
| **M7** Background and retention | 5 | Scheduler, web runner, mail budget, expiry, pruning | M4 |
| **F1–F6** Frontend | 6 | Design system, public pages, account, wizard, admin UI, a11y/performance | M0–M7 (per area) |
| **M8** QA | 7 | Browser tests, regression, multi-country scenarios, accessibility scans | F6 |
| **M9** Security review | 8 | Review + fixes + retest | M8 |
| **M10** Deployment | 9 | Staging then production on cPanel | M9 + hosting verification |
| **M11** Operations | 10 | Monitoring, backups, runbooks | M10 |

**Phase-order note:** CLAUDE.md requires Phase 5 (backend) before Phase 6 (frontend). With Livewire, component classes are backend and their views are frontend. In Phase 5, Livewire components get **plain, unstyled Blade views** that are good enough for tests and manual checks. Phase 6 replaces them with the designed views. This keeps the order without blocking testing.

**MVP critical path:** M0 → M1 → M3 → M4 → M5 → F1 → F2 → F4 → M8 → M9 → M10. M2, M6 and M7 run alongside where dependencies allow.

## 5. Phase 5 — Backend Tasks

### M0 — Foundation

| ID | Task | Size | Depends | Acceptance criteria | Tests |
|---|---|---|---|---|---|
| T0.1 | Initial commit of docs; `.gitignore`; branch workflow | S | G0.5 | `main` has the docs commit. `.env`, `vendor`, `node_modules`, `storage/*` ignored. | — |
| T0.2 | Scaffold **Laravel 13** with PHP 8.4 (`C:\php8.4`). Pin `config.platform.php` to the confirmed production PHP. `.env.example` with every key from the env inventory. `config/marketplace.php`. UTC app timezone. | M | G0.2–G0.4 | App boots locally. `php artisan about` shows Laravel 13 / PHP 8.4. No secrets committed. | Default test passes |
| T0.3 | Tooling + CI: Pint, Larastan (level 5), PHPUnit, GitHub Actions (SQLite suite + MySQL 8 job + Pint + Larastan + `composer audit`) | M | T0.2 | CI green on the branch | CI run |
| T0.4 | Baseline security and portability: `TrustHosts` from `APP_URL`, `forceRootUrl`/HTTPS in production, `SecurityHeaders` (CSP with nonces), session config (`dtm_session`, host-only, secure), custom error pages (plain), **guard tests** (no hardcoded domain/country/currency/unit, P1 + global rule) | M | T0.2 | Headers present. Wrong Host header rejected. Guard tests fail when a forbidden literal is added. | Feature tests for headers, host, guards |
| T0.5 | Install approved packages: Livewire 4, Fortify, spatie/permission, spatie/translatable, intervention/image 4, propaganistas/laravel-phone (G-11). Publish configs only as needed. | S | T0.3, G-11 | `composer audit` clean. Lock file committed. | Suite green |

### M1 — Geo and Money

| ID | Task | Size | Depends | Acceptance criteria | Tests |
|---|---|---|---|---|---|
| T1.1 | `currencies`, `countries`, `country_currencies` migrations, models, seeders (ICU minor units, `LKR → Rs` override, all countries inactive) | M | T0.5 | ~250 countries, ~180 currencies seeded idempotently. Seeder re-run changes nothing. | Seeder idempotency, model tests |
| T1.2 | `regions` (levels 1–2), `cities`, `localities`, `location_requests` migrations/models + `LocationChain` validation rule | M | T1.1 | Rejects mismatched or inactive chains. Honours `require_region`/`require_city`. | Unit + feature tests for the rule (valid, mismatched, inactive, optional levels) |
| T1.3 | GeoNames import commands (`geo:import-countries`, `geo:import-regions {CC}`, `geo:import-cities {CC}`) with fixture-based tests. Re-sync rules (rename keeps ID, removed → deactivated). Attribution text in config. | L | T1.2 | Importing fixtures creates the expected rows. Re-import is idempotent. Referenced cities are never deleted. | Command tests with small fixture files |
| T1.4 | **Sri Lanka curated data:** import 9 provinces / 25 districts, generate a candidate town list, owner reviews it, commit the seed (~400–700 towns) | M (+ owner review) | T1.3, **G-1, G-3** | Owner-approved list. Nugegoda, Kottawa, Malabe correctly placed. | Seeder test (counts, sample places) |
| T1.5 | `Money` service (ICU formatting, symbol override, auto decimals, listing-country locale), `measurement_units` + `country_measurement_units`, `AreaConverter`, `UnitPrice` (bcmath) | M | T1.1, **G-5, G-6** | Formats match the global design §3.3 table. Per-unit price matches the ADR-018/021 examples. Null-safe. | Unit tests: LKR/USD/GBP/INR/JPY/KWD formats, conversions, rounding, zero/null/negative |
| T1.6 | `CountryContext` (route `{cc}` → cookie → `CF-IPCountry` if trusted proxy → default setting). Inactive country → 404. | S | T1.1, **G-2** | Resolution order works. Inactive codes 404. | Feature tests |

### M2 — Accounts and Roles

| ID | Task | Size | Depends | Acceptance criteria | Tests |
|---|---|---|---|---|---|
| T2.1 | Users table extension (phone E.164 + phone_country, country, contact visibility, status, legal hold, soft deletes). Fortify: register, login, verify email, reset, password confirm. Rate limiters. Turnstile service + honeypot. | L | T0.5, T1.1 | AUTH-01–04, AUTH-08 behaviours. No account enumeration. | Feature tests incl. throttling, invalid phones per country, unverified users blocked |
| T2.2 | Roles/permissions seeder (matrix), policies skeleton, hierarchy and last-owner guards, `EnsureAccountActive`, staff 2FA enforcement + 15-min password confirm for sensitive actions, `marketplace:reset-2fa` CLI | L | T2.1 | Matrix in authorization doc §3 enforced. Owner/Admin blocked from admin until 2FA is confirmed. | Permission matrix tests (each role × permission), hierarchy tests, 2FA gate tests |
| T2.3 | Account settings backend: profile, contact visibility (default hidden), WhatsApp number, delete account (soft delete + sessions killed) | M | T2.1 | MSG-01, AUTH-08, retention step 1 | Feature tests |

### M3 — Catalogue

| ID | Task | Size | Depends | Acceptance criteria | Tests |
|---|---|---|---|---|---|
| T3.1 | `categories` (2 levels, translatable, settings, `allowed_price_types`, `unit_price_attribute_id`, `scope_countries`) + seed of the approved tree (7 / 48) | M | T1.1, **G-13** | Tree seeded. Leaf rules enforced. Deleting a category with listings is blocked. | Model and seeder tests |
| T3.2 | `attributes`, `attribute_options` (dependent, scoped), `category_attribute` + **`AttributeSchema`** (merged parent+child attributes, per-country filtering, validation rules + form schema, cached per category+country) | L | T3.1 | Same schema drives rules and form. Out-of-scope options rejected. Cache busts on admin change. | Unit tests per attribute type, inheritance, scoping, dependent selects |
| T3.3 | Attribute and option seed data (vehicle makes/models, phone brands/models, property, electronics, furniture). Owner reviews the makes/models list. | M (+ review) | T3.2 | Seeds load. Sri Lanka-only options scoped to LK. | Seeder test |

### M4 — Listings Core

| ID | Task | Size | Depends | Acceptance criteria | Tests |
|---|---|---|---|---|---|
| T4.1 | `listings`, `listing_attribute_values` (incl. `input_value`/`input_unit_id`), `listing_images` migrations + indexes (DB doc §4, global §9). Enums. **Public scope.** | M | T1.2, T3.2 | Public scope = active + not expired + not deleted + owner not suspended + country active | Scope tests (each exclusion case) |
| T4.2 | Draft actions: create/update location (chain, country change rules GLB-11), details (schema validation, typed EAV writer, `attributes_summary`), price (currency from country, decimals per currency, Land area + unit). Limits (LST-09). | L | T4.1, T1.5 | LST-01, LST-03, LST-14, GLB-04/11/14 | Feature tests incl. the multi-country scenarios 1–4, 8, 9 (global §11) |
| T4.3 | Image pipeline: `listings` disk (symlink or `public/uploads` fallback via env), validation (MIME, magic bytes, dimensions, pixels, 8 MB), re-encode 2 variants (WebP/JPEG fallback), failure handling, per-category max (8/10), orphan cleanup | L | T4.1 | LST-02, CD-10. A PHP file disguised as JPG is rejected. No originals kept. | Feature tests with fixture images (valid, polyglot, oversize, too small, too many, corrupt) |
| T4.4 | Lifecycle actions: submit (Turnstile, keyword screener, duplicate detector), approve/reject/request changes, material edit → pending, price edit → "recent changes" feed, renew, sold, delete, expire. Audit logs. Queued notifications. | L | T4.2, T2.2 | State diagram in architecture §4.2. CD-06 (every new listing approved first). | State transition tests (allowed/forbidden), audit rows, notifications faked |
| T4.5 | `ContactResolver` + `POST /listings/{id}/contact` (visibility × listing toggles, guest threshold Turnstile, rate limits, `contact_reveals` with IP HMAC, WhatsApp `wa.me/{E.164}`) | M | T4.1, T2.3 | MSG-01–06 acceptance criteria. Number never in HTML. | Feature tests (401/403/404/428/429 paths) |
| T4.6 | Favourites + reports (one open report per target, reasons) | S | T4.1 | SRCH-08, MOD-02 | Feature tests |

### M5 — Search and SEO

| ID | Task | Size | Depends | Acceptance criteria | Tests |
|---|---|---|---|---|---|
| T5.1 | `ListingSearch` interface + `MysqlListingSearch`: keyword (FULLTEXT + short-term LIKE), location levels, category tree, attribute filters (whitelisted, max 10), area filters in m², **single-currency price rule**, sorts, pagination cap | L | T4.1, T3.2, **G-7** | SRCH-02–05, GLB-12/13 | Feature tests incl. global scenarios 5–7. MySQL CI job for FULLTEXT. |
| T5.2 | Routes: `/`, `/countries`, `/{cc}`, `/{cc}/ads…`, location landing pages, global `/ads`, `/ad/{slug}-{id}` (301 on slug mismatch, sold/expired page, 410), seller page, static pages | M | T5.1, T1.6, **G-8** | Routes doc §1 | Feature tests per route (incl. inactive country 404, slug 301, 410) |
| T5.3 | SEO: `PageSeo` (titles with location/country, canonical from `APP_URL`, OG), JSON-LD (Product/Offer with currency, PostalAddress without street, BreadcrumbList), `SitemapBuilder` (per-country chunks, static files), robots | M | T5.2 | SEO doc rules. No hardcoded domain. | Feature tests for meta/JSON-LD/sitemap output (global scenario 13) |
| T5.4 | Performance check: factory seeding of 50k listings across 4 countries. `EXPLAIN` the main queries. Add indexes where measured. Record results in docs. | M | T5.1 | Main queries use the intended indexes. p95 locally within target. | Benchmark script + recorded `EXPLAIN` output |

### M6 — Admin Backend (Livewire Classes with Plain Views)

| ID | Task | Size | Depends | Acceptance criteria | Tests |
|---|---|---|---|---|---|
| T6.1 | Moderation queue + review actions (approve/reject with templates/request changes, flags, seller summary), "recent changes" feed, reports queue | L | T4.4, T2.2 | MOD-01/02/04/05 | Livewire tests: each action authorised + unauthorised (called directly) |
| T6.2 | Users (search, suspend/ban with hashed blocklist, delete) and Staff (roles, 2FA reset — Owner only) | M | T2.2 | ADM-04, hierarchy rules | Livewire tests |
| T6.3 | Catalogue admin: categories (tree, reorder, settings, scope), attributes, options (dependent, scope), attach to categories | L | T3.2 | ADM-02, CAT-01–06 | Livewire tests incl. "in use → deactivate only" |
| T6.4 | Locations admin: countries (activate — Owner, labels, currency, locale, units, require flags, checklist), regions/cities CRUD, **merge duplicates** (re-point listings in one transaction), localities, location requests | L | T1.3, T4.1 | ADM-03, GLB-02/16 | Livewire tests incl. merge integrity, global scenario 10 |
| T6.5 | Settings (typed, Owner), static pages (sanitised Markdown), blocklist (global + per country), audit log viewer, dashboard stats | M | T2.2 | ADM-01/05/06/07, GLB-18 | Livewire tests |

### M7 — Background and Retention

| ID | Task | Size | Depends | Acceptance criteria | Tests |
|---|---|---|---|---|---|
| T7.1 | Scheduler (expire hourly, expiry reminders, sitemap daily, orphan cleanup, model prune), `RunBackgroundTasks` web runner (env toggle, atomic lock, post-response), `MailBudget`, `MAIL_SENDING_ENABLED` gate | M | T4.4 | ADR-006, CD-09 | Tests for each scheduled command, runner lock, mail gate |
| T7.2 | `marketplace:prune` per the retention doc (configurable periods, legal hold, anonymisation, image deletion) | M | T7.1 | Retention doc §1–§3 | Tests per data type with time travel |

## 6. Phase 6 — Frontend Tasks

| ID | Task | Size | Depends | Acceptance criteria | Tests |
|---|---|---|---|---|---|
| F1.1 | Design tokens in Tailwind 4 (`@theme`), Geist + flag-icons + Lucide via Vite, dark mode (no flash), base CSS | M | T0.2, **Q-UX-2/5, G-11** | Tokens match design system §3. Contrast pairs unchanged. | Build check. Token snapshot test. |
| F1.2 | Core Blade components (button, field, input, select, combobox, checkbox/radio/switch, badge, chip, alert, modal, drawer, toast, skeleton, empty state, pagination, tabs, dropdown, money, flag) | L | F1.1 | Component doc §1–§4, all states | Blade component tests (rendering, ARIA attributes) |
| F1.3 | Layouts: public (header, country switcher, category bar, bottom nav, footer + GeoNames attribution), account, admin (sidebar), auth, error. Owner-only style-guide page. | M | F1.2, **Q-UX-1/3** | Layout doc §2 | Feature tests (render, landmarks, one h1) |
| F2.1 | Home (country chooser at `/`, country home), category tiles, latest | M | F1.3, T5.2 | Layout doc §3.1 | Feature tests |
| F2.2 | Search page UI: filters (location dependent selects, currency rule UI, dynamic attributes), chips, sort, grid/list, mobile drawer, URL sync, empty/loading states | L | F1.3, T5.1, **Q-UX-4/6** | Layout doc §3.2, global UX §5 | Livewire tests + manual responsive check |
| F2.3 | Listing detail (gallery, price block incl. per-unit, attributes, contact panel, seller card, safety, report modal, similar ads, mobile contact bar, status banners, sold/410 pages) + seller page | L | F1.3, T4.5 | Layout doc §3.3 | Feature/Livewire tests |
| F3.1 | Auth screens (login, register with country + phone picker, verify, forgot/reset, 2FA challenge) | M | F1.3, T2.1 | Layout doc §3.7 | Feature tests |
| F3.2 | Account area: overview, my listings (tabs, actions), favourites, settings (profile, contact, security + 2FA enrolment with recovery codes), notifications | L | F1.3, T2.3, T4.4 | Layout doc §3.5, 2FA doc §3 | Livewire tests |
| F4.1 | **Posting wizard** (6 steps): category picker, location picker, details (schema-driven fields), price (currency chip, area units, per-unit preview), photos uploader (progress, reorder with buttons, errors), contact & review, country-change dialog, draft autosave, submit confirmation | L+ | F1.2, T4.2–T4.4 | Layout doc §3.4, global UX §2–§4 | Livewire tests per step + manual run-through per country |
| F5.1 | Admin UI for M6 screens (moderation review pane with shortcuts, reports, users, staff, catalogue, locations incl. merge preview, settings, pages, blocklist, audit, dashboard) | L+ | F1.3, M6 | Layout doc §3.6 | Livewire tests remain green with the designed views |
| F6.1 | Accessibility and responsive pass (keyboard, focus, 320 px, 200% zoom, reduced motion), performance budget (CSS/JS/fonts/images), Lighthouse | M | F2–F5 | Forms/a11y doc §3, §5 budgets | axe scan (via browser tests), Lighthouse report |

## 7. Phase 7–10 Outline (Detailed When Reached)

| Milestone | Content |
|---|---|
| **M8 QA (Phase 7)** | Browser end-to-end tests (D-1 decided: **Playwright**, ADR-025; set up in F6.1 with axe and Lighthouse). Key journeys per country (LK, plus one 1-level country such as US): register → post → moderate → search → contact. Global scenarios 1–15. Authorization boundary sweep. Regression of all feature tests on MySQL. |
| **M9 Security (Phase 8)** | Security-doc checklist review, OWASP ASVS L1-style pass, upload abuse tests, rate-limit verification, dependency audit, secrets scan. Fix + retest evidence. |
| **M10 Deployment (Phase 9)** | Hosting verification script (approved upload), account decision (CD-03), staging subdomain, release packaging (vendor included, prebuilt assets), migrations via SSH or one-off cron, DNS/SSL (**approval**), production launch with Sri Lanka active, smoke tests, rollback rehearsal. |
| **M11 Operations (Phase 10)** | Backups + restore drill, monitoring (logs, disk/inodes, queue backlog), runbooks (2FA break-glass, location curation, country activation checklist, domain migration), update cadence. |

## 7a. Paid Boosts (M9b, Before Deployment)

Owner decision 2026-10-21: build the paid boosts of `docs/proposals/paid-boosts.md` (ADR-026) **before deployment** (D7 changed). Scope: proposal steps 1–2 (Bump and Top ad, products and prices per country, manual bank transfer). **Selling stays switched off** (`boosts.sales_enabled`, default off) until the accountant review (D6) and the owner's prices (D2); admin-granted boosts work without it. Urgent and payment gateways stay later (D1, D3).

| ID | Task | Size | Output |
|---|---|---|---|
| B1 | **Foundations:** `listings.sorted_at` (the "newest" order; equals publication until a Bump; renewal leaves it alone), indexes moved to it; `listing_boosts` (type, starts, ends, order, granted by); `listing_daily_views` and view counting on the ad page (once per visitor session per day; owner, staff and bots not counted) | M | Migrations, models, ordering on `sorted_at`, view counter, tests |
| B2 | **Top ads and bumps in results:** labelled top-ad block above results (max 2, setting; only the default order; country, category subtree and active filters must match; rotation by last shown), "Why am I seeing this?", Bump moves `sorted_at`; boosts end when the ad stops being public; scheduler ends expired boosts | M | Search and category pages, card label, tests incl. trust rules |
| B3 | **Admin grant and seller view:** admin grants or ends a Bump / Top ad on an ad (audited); My ads shows the active boost and views per day before and during | M | Admin action, account UI, tests |
| B4 | **Products and prices:** `boost_products`, `boost_prices` per country (admin), per-country bank transfer details (admin), `boosts.sales_enabled` switch | M | Admin screens, seeds (no prices), tests |
| B5 | **Orders by bank transfer:** seller "Promote" flow (live own ads only; price from the server), order with reference and instructions (page + email), admin orders (mark paid → boost starts, cancel, refund → boost ends), unpaid orders expire, emails (receipt, started, ending soon, ended), rate limit | L | Flow, admin, emails, tests |
| B6 | **Rules, QA and docs:** boost rules and refund page (owner text, D4), browser journey (order → admin paid → top ad visible), security checks (others' ads, non-live ads, tampered amounts), docs | M | Journey, security tests, docs |

## 7b. Audience Features (M9c, Before Deployment)

Owner decision 2026-10-22 (ADR-027, proposal `docs/proposals/engagement-features.md`).

| ID | Task | Size | Output |
|---|---|---|---|
| G1 | **Search terms and live search:** counted search terms per country and day (no user or IP); suggestion endpoint (categories, makes/models, places, popular searches, with counts; cached, rate-limited); suggestions under the header and search-page search boxes (accessible combobox) | M | Table, endpoint, UI, tests |
| G2 | **Popular, trending, most marked as sold:** per-country blocks on the country home and category pages (popular ads this week, trending searches, trending makes/models, most marked as sold in 90 days) from scheduled, cached aggregates with minimum counts; never paid placements | M | Aggregates, blocks, tests |
| G3 | **Share:** WhatsApp and Facebook share links on the ad page beside the existing share button; good link previews (Open Graph already present: checked) | S | Links, tests |
| G4 | **Make/model pages and price guide:** `/{cc}/ads/{category}/{subcategory}/make/{make}[/{model}]` style landing pages listing matching ads with a price guide (median and range from enough ads, outliers removed), linked from ads and in the sitemap | M | Routes, pages, sitemap, tests |
| G5 | **Seller ratings and trust badges:** eligibility from contact reveals, reviews with moderated comments, seller reply, report, admin moderation queue, averages from 3, trusted-seller badge rules in settings; seller page and ad seller card | L | Tables, flows, admin, tests |
| G6 | **QA and docs:** browser journeys (live search, rating), accessibility scans, security checks (eligibility, XSS, rate limits), docs and owner guide (Terms text for reviews) | M | Tests, docs |

## 8. Owner Content Tasks (Parallel, Not Code)

| ID | Content | Needed by |
|---|---|---|
| C1 | Review the curated Sri Lanka town list (T1.4) | M1 |
| C2 | Review vehicle makes/models and phone brands lists (T3.3) | M3 |
| C3 | Terms, Privacy Policy, Safety tips, Prohibited items, FAQ, About texts (legal review recommended) | F2 / launch |
| C4 | Moderation reason templates and review-time wording | M6 |
| C5 | Product name/logo (Q-UX-2) | F1 |
| C6 | Legal and moderation readiness per extra country (G-10, G-12) | Before activating each country |

## 9. Testing Strategy Summary

| Layer | Tool | Scope | Where |
|---|---|---|---|
| Unit | PHPUnit | Money, units, schema engine, resolvers, rules | SQLite, every commit |
| Feature / HTTP | PHPUnit + Laravel testing | Routes, validation, authorization (positive and negative), JSON endpoints, SEO output | SQLite + **MySQL 8 job** (FULLTEXT, JSON, indexes) |
| Livewire | Livewire test utilities | Every action authorised and unauthorised (direct calls), state changes | SQLite |
| Commands / scheduler | PHPUnit + time travel | Import, expire, prune, sitemap, runner | SQLite |
| Browser E2E | Playwright (D-1) | Key journeys, responsive, axe accessibility | Phase 7 |
| Performance | Seeder + `EXPLAIN` + timing script | Main search queries at 50k listings / 4 countries | T5.4, Phase 7 |
| Static | Pint, Larastan, guard tests, `composer audit`, `npm audit` | Every commit (CI) | CI |

Coverage target: ≥ 70% for `app/Actions`, `app/Services`, `app/Policies`, `app/Http`, `app/Livewire` (measured once PCOV/Xdebug is added; owner approval for adding the extension to `C:\php8.4`).

## 10. Implementation Risks

| Risk | Mitigation |
|---|---|
| The host can't run PHP 8.3+ | Gate G0.2 before scaffolding |
| Shared-hosting limits (inodes, cron, mail) | Designed in (ADR-006/008/009). Verified in M10 before launch. |
| Typed-EAV search slower than expected | T5.4 measures early. Generated hot columns as a planned fallback. |
| Curated location data takes longer than code | Starts at T1.4 in parallel. Only Sri Lanka is needed for launch. |
| Livewire 4 / Fortify integration surprises | Small tasks with tests. Fortify is headless (own views). |
| Scope creep (messaging, payments, more countries) | Out of MVP per requirements. New scope goes through the owner. |
| Undecided defaults change late | Gated tasks listed in §1. Defaults are data/config-driven where possible. |

## 11. Next Step After Approval

1. The owner completes Gate 0 (G0.1–G0.5) and confirms the defaults for M0–M1 (G-11, G-1, G-2, G-3, G-4, G-5, G-6).
2. Start **T0.1** (initial commit), then T0.2 on branch `feature/T0.2-scaffold-laravel-13`.
3. Report after each task. Merge on owner approval.
