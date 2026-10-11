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
- [~] T0.4 Security/portability baseline + guard tests — done on `feature/T0.4-security-portability-baseline`, **awaiting owner review**
- [ ] T0.5 Install approved packages
### M1 Geo and money
- [ ] T1.1 Currencies, countries, country_currencies
- [ ] T1.2 Regions, cities, localities, location requests + LocationChain rule
- [ ] T1.3 GeoNames import commands
- [!] T1.4 Sri Lanka curated data — gated by G-1, G-3 and owner review C1
- [!] T1.5 Money, units, AreaConverter, UnitPrice — gated by G-5, G-6
- [!] T1.6 CountryContext — gated by G-2
### M2 Accounts and roles
- [ ] T2.1 Users extension, Fortify auth, rate limits, Turnstile + honeypot
- [ ] T2.2 Roles/permissions, policies, guards, staff 2FA, password confirm
- [ ] T2.3 Account settings backend
### M3 Catalogue
- [!] T3.1 Categories + seed — gated by G-13
- [ ] T3.2 Attributes, options, AttributeSchema
- [ ] T3.3 Attribute/option seed data (owner review C2)
### M4 Listings core
- [ ] T4.1 Listings tables, indexes, public scope
- [ ] T4.2 Draft actions (location, details, price, units)
- [ ] T4.3 Image pipeline
- [ ] T4.4 Lifecycle and moderation actions
- [ ] T4.5 Contact reveal
- [ ] T4.6 Favourites and reports
### M5 Search and SEO
- [!] T5.1 ListingSearch (MySQL) — gated by G-7
- [!] T5.2 Public routes — gated by G-8
- [ ] T5.3 SEO, JSON-LD, sitemaps
- [ ] T5.4 Performance check (50k listings, 4 countries)
### M6 Admin backend
- [ ] T6.1 Moderation + reports
- [ ] T6.2 Users + staff
- [ ] T6.3 Catalogue admin
- [ ] T6.4 Locations admin
- [ ] T6.5 Settings, pages, blocklist, audit, dashboard
### M7 Background and retention
- [ ] T7.1 Scheduler, web runner, mail budget, mail gate
- [ ] T7.2 Retention pruning

## Phase 6 — Frontend
- [!] F1.1 Tokens, fonts, icons, dark mode — gated by Q-UX-2, Q-UX-5, G-11
- [ ] F1.2 Core components
- [!] F1.3 Layouts + style guide — gated by Q-UX-1, Q-UX-3
- [ ] F2.1 Home / country home
- [!] F2.2 Search UI — gated by Q-UX-4, Q-UX-6
- [ ] F2.3 Listing detail + seller page
- [ ] F3.1 Auth screens
- [ ] F3.2 Account area
- [ ] F4.1 Posting wizard
- [ ] F5.1 Admin UI
- [ ] F6.1 Accessibility, responsive, performance pass

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
