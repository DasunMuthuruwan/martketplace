# Task Checklist

Status keys: `[ ]` not started · `[~]` in progress · `[x]` done (tests green, merged) · `[!]` blocked (reason noted).
Details for each task: [implementation-plan.md](implementation-plan.md). Update this file after every task.

## Gate 0 — Before Code
- [x] G0.1 Owner approves the implementation plan (2026-10-11)
- [!] G0.2 Host PHP 8.3/8.4 availability + DB version — **not yet checked; owner chose to proceed** (risk accepted 2026-10-11). Composer platform pinned to PHP 8.3 meanwhile.
- [x] G0.3 Composer decision: marketplace-only Composer 2.10.3 at `C:\php8.4\composer.phar`, isolated `COMPOSER_HOME` (2026-10-11)
- [x] G0.4 Local database: standalone **MariaDB 10.11.19** at `C:\mariadb10.11`, `127.0.0.1:3307`, database `marketplace` + own user (2026-10-11). XAMPP MariaDB (pre-existing corruption) not used, see docs/operations/developer-tooling.md §3.
- [x] G0.5 Initial commit + branch workflow approved (2026-10-11)
- [ ] G0.6 Defaults confirmed (G-1–G-13, Q-UX-1–6) — can be gradual

## Phase 5 — Backend
### M0 Foundation
- [x] T0.1 Initial docs commit, `.gitignore`, branch workflow (commit a38ecda)
- [x] T0.2 Scaffold Laravel 13 (PHP 8.4), env inventory, `config/marketplace.php` (merged 6f298b8)
- [x] T0.3 Pint, Larastan, PHPUnit, GitHub Actions CI (SQLite + MySQL 8) (merged f681a98; all checks green locally; **first GitHub CI run pending** — push blocked by GitHub credential 403)
- [x] T0.4 Security/portability baseline + guard tests (merged 1942a94)
- [x] T0.5 Install approved packages (merged 748383e) — **Milestone M0 complete**
### M1 Geo and money
- [x] T1.1 Currencies, countries, country_currencies (merged 43ca29f)
- [x] T1.2 Regions, cities, localities, location requests + LocationChain rule (merged 2a76ed3)
- [x] T1.3 GeoNames import commands (merged 52fe7c4)
- [ ] T1.4 Sri Lanka curated data — G-1/G-3 defaults accepted; owner review C1 of the curated lists still required
- [x] T1.5 Money, units, AreaConverter, UnitPrice + area attributes in AttributeSchema (merged a1dc964)
- [x] T1.6 CountryContext (merged d307a13)
### M2 Accounts and roles
- [x] T2.1 Users extension, Fortify auth, rate limits, Turnstile + honeypot (merged eb97d18)
- [x] T2.2 Roles/permissions, policies, guards, staff 2FA, password confirm (merged 80daad3)
- [x] T2.3 Account settings backend (merged 0fbd474)
- [x] T2.4 Staff session limits (follow-up to T2.2: 30-min idle, 8-h absolute, no remember-me for staff) (merged 5b5614a)
### M3 Catalogue
- [x] T3.1 Categories + seed (merged 815cf1b)
- [x] T3.2 Attributes, options, AttributeSchema (merged 4aa86cb)
- [x] T3.3 Attribute/option seed data (merged b3ede9a; C2 approved 2026-10-11)
### M4 Listings core
- [x] T4.1 Listings tables, indexes, public scope (merged 27f3a0a, e0437aa)
- [x] T4.2 Draft actions (location, details, price, units) (merged 7d2e9be)
- [x] T4.3 Image pipeline (merged 9abeae9; `local.serve` disabled)
- [x] T4.4 Lifecycle and moderation actions (T4.4a merged 4574d7b, T4.4b merged ac8a02b; country/category fixed after submission approved)
- [x] T4.5 Contact reveal (merged f78bb63)
- [x] T4.6 Favourites and reports (merged 7c9286a) — M4 complete
### M5 Search and SEO
- [x] T5.1 ListingSearch (MySQL) (merged 8f0697f)
- [x] T5.2 Public routes (merged 7a33762; `/page/{slug}` moves to M6)
- [x] T5.3 SEO, JSON-LD, sitemaps (merged 3937cc8)
- [x] T5.4 Performance check (merged a1c2b44; results in `docs/operations/performance.md`; re-run on the host before launch) — M5 complete
### M6 Admin backend
- [x] T6.1 Moderation + reports (merged d96e8c3)
- [x] T6.2 Users + staff (merged 81e30eb)
- [x] T6.3 Catalogue admin (merged 1d2a082)
- [x] T6.4 Locations admin (merged fdae767)
- [x] T6.5 Settings, pages, blocklist, audit, dashboard (merged af82ab7)
### M7 Background and retention
- [x] T7.1 Scheduler, web runner, mail budget, mail gate (merged f600e8a)
- [x] T7.2 Retention pruning (merged 768938f)

## Phase 6 — Frontend
- [x] F1.1 Tokens, fonts, icons, dark mode (merged 250926a)
- [x] F1.2 Core components (merged 50d893e)
- [x] F1.3 Layouts + style guide (merged bfac7c9)
- [x] F2.1 Home / country home (merged 921ec7f)
- [x] F2.2 Search UI (merged aa842ad)
- [x] F2.3 Listing detail + seller page (merged 93d7a02)
- [x] F3.1 Auth screens (merged f14b9f6)
- [x] F3.2 Account area (merged fae5a52)
- [x] F4.1 Posting wizard (part 1 merged d1e6dc8; part 2 merged 90521af; part 3 merged 4cf407b)
- [x] F5.1 Admin UI (part 1 merged 134eadb; part 2 merged 2946b1b; part 3 merged a1a0656)
- [x] F6.1 Accessibility, responsive, performance pass (part 1 merged 3a787bb; part 2 merged 7c59579)

## Phases 7–10
- [ ] M8 QA (browser tests: D-1 Playwright vs Dusk)
- [ ] M9 Security review
- [ ] M10 Deployment (staging → production, DNS/SSL approval)
- [ ] M11 Operations

## Owner Content
- [ ] C1 Sri Lanka town list review
- [ ] C2 Makes/models and brands review
- [ ] C3 Legal and help pages
- [ ] C4 Moderation reason templates
- [ ] C5 Product name/logo
- [ ] C6 Per-country legal + moderation readiness
