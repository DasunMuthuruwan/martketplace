# Project Progress

Source of truth for project status. Update after every task.

## Current Phase

**Phase 5 — Backend development: started.** Phase 4 plan approved 2026-10-11. T0.3 merged (first GitHub CI run pending: push blocked by GitHub credential 403). M0 Foundation complete (T0.1–T0.5 merged). T1.1 merged. T1.2 merged. T1.3 merged. M1 remaining tasks (T1.4–T1.6) wait on G-1, G-2, G-3, G-5, G-6. T2.1 merged. T2.2 merged. Current task: **T2.3** (account settings) ([task checklist](implementation/task-checklist.md)). Also awaiting review: **global marketplace revision** The owner changed scope from Sri Lanka-only to multi-country. Requirements v0.4, architecture doc 13, database/category/routes/packages updates, ADR-019–023, design doc 05 and the prototype were revised. **Phase 3 UI/UX design** is also awaiting approval ([design/README.md](design/README.md)). Phase 2 was approved on 2026-10-11 (global changes to it pending approval).
Implementation (Phase 5) must still not start until Phases 3 and 4 are approved and the hosting conditions are verified.

## Phase Checklist

| # | Phase | Status | Output |
|---|---|---|---|
| 1 | Requirements analysis | Approved (v0.3) | [requirements/requirements-specification.md](requirements/requirements-specification.md) |
| 2 | System architecture & technical design | **Approved 2026-10-11** | [architecture/README.md](architecture/README.md), [decisions/README.md](decisions/README.md) |
| 3 | UI/UX design | Draft, awaiting review | [design/README.md](design/README.md) (docs 01–04 + clickable prototype) |
| 4 | Implementation planning | **Approved 2026-10-11** | [implementation/implementation-plan.md](implementation/implementation-plan.md), [task checklist](implementation/task-checklist.md) |
| 5–10 | Build, test, security, deploy, maintain | Not started | — |

## Completed Tasks

| Date | Task | Notes |
|---|---|---|
| 2026-10-11 | Repository inspection | Greenfield repo. |
| 2026-10-11 | Requirements v0.1 → v0.3 | Q1–Q16 answered. CD approvals and portability requirements (NFR-X1–X8) added. |
| 2026-10-11 | Dev Talk read-only inspection | Domain `dev-talk.com`, register.lk cPanel, LiteSpeed + `ea-php83`. Nothing modified. |
| 2026-10-11 | Phase 2 design rev. 1 and rev. 2 | Docs 01–12, ADR-001–017. Package compatibility verified from official sources. |
| 2026-10-11 | **Owner approved revised Phase 2 design** | ADR-014–017 accepted. Final package list approved. 2FA method + recovery approved. Q-MOD-1: price edits immediate + moderation feed. Category tree approved as listed. |
| 2026-10-11 | T2.2 (merged) | spatie/permission tables; `Permissions` matrix (Owner 20 / Admin 17 / Moderator 8, no blanket bypass) + idempotent seeder; `StaffHierarchy` + `UserPolicy`; `ChangeStaffRole` with concurrency-safe last-Owner guard; `EnsureAccountActive` (mid-session suspension) and `staff.2fa` middleware; Fortify TOTP 2FA with confirm + recovery codes + challenge; 15-min password re-check; `marketplace:make-owner` and `marketplace:reset-2fa`. Bugs found by tests and fixed: model defaults for status (crash before reload), permission cache with muted model events in the seeder. 225 tests / 701 assertions on SQLite and MariaDB. |
| 2026-10-11 | T2.1 (merged) | Users table extended (E.164 phone + phone country, home country, contact visibility default hidden, status/suspension, legal hold, soft deletes) + Fortify 2FA columns (passkeys table removed). Fortify registration/login/verification/reset/confirm with plain views; suspended/banned blocked; timing-safe, enumeration-safe login and reset; rate limits on every auth endpoint; honeypot + time trap + Turnstile (required when enabled, fail closed). 164 tests / 486 assertions on SQLite and MariaDB. |
| 2026-10-11 | T1.3 (merged) | `geo:import-countries` / `geo:import-regions` / `geo:import-cities` with streaming GeoNames reader, re-sync rules (stable IDs/slugs, curated cities protected, filtered ≠ removed, opt-in deactivation, never delete), shared CountryImporter (seeders refactored onto it). Fixture tests (17) + real-data trial on the test DB: LK 34 regions / 17,470 places, GB 189 regions, US 51 states / 16,655 cities. 122 tests / 341 assertions on SQLite and MariaDB. |
| 2026-10-11 | T1.2 (merged) | `regions` (levels 1–2, slug unique per country), `cities` (region nullable for 0-level countries), `localities`, `location_requests` + models, status enum and factories (Country/Currency/Region/City/Locality). `LocationChain` validation with per-field errors in the country's terms. 105 tests / 248 assertions on SQLite and MariaDB 10.11. |
| 2026-10-11 | T1.1 (merged) | `currencies`, `countries`, `country_currencies` migration + models (translatable names/labels, label fallback). Seeders from GeoNames snapshot + ICU + libphonenumber + PHP tz: 250 countries (inactive), 154 currencies, 249 default currency links. Idempotent, keeps admin changes. DatabaseSeeder no longer creates a default-credential test user. 68 tests / 190 assertions on SQLite and MariaDB 10.11. |
| 2026-10-11 | T0.5 (branch) | Installed the 6 approved packages (Livewire 4.4.7, Fortify 1.41.0, permission 8.3.0, translatable 6.14.1, Intervention Image 4.3.5, laravel-phone 6.1.0). Audit clean, licences permissive. Disabled unconfigured Fortify routes (until T2.1) and passkey routes (ADR-015). Package smoke tests incl. GD WebP/JPEG encoding and phone validation for LK/GB/US. 57 tests / 133 assertions. |
| 2026-10-11 | T0.3 + T0.4 | T0.3: Larastan 3.13 level 5, Pint, CI workflow (merged locally; GitHub run pending). T0.4 (branch): SecurityHeaders + nonce CSP, exact trusted hosts (forged Host → 400), configurable trusted proxies, RootUrl (APP_URL root + scheme; HTTPS in production), `dtm_session` cookie defaults, plain error pages (400–503) from lang strings, placeholder home without external assets, hardcoded-value guard test. 41 tests / 107 assertions, Pint + Larastan clean. |
| 2026-10-11 | **Plan approved; T0.1 + T0.2** | T0.1: initial docs commit `a38ecda` on `main`. T0.2 (branch `feature/T0.2-scaffold-laravel-13`): Laravel **13.35.0** scaffolded (skeleton v13.11.0, Symfony 7.4, PHPUnit 12), Composer platform pinned to PHP 8.3, `composer audit` clean, `.env.example` env inventory, `config/marketplace.php`, README. Skeleton Laravel Boost `CLAUDE.md`/`AGENTS.md` not copied (they instruct remote installers). Migrations + 2 default tests pass. App serves HTTP 200. Marketplace-only Composer 2.10.3 installed. |
| 2026-10-11 | Phase 4 implementation plan draft | Gate 0, 11 milestones, ~45 tasks with dependencies/acceptance/tests, defaults for undecided G-/Q-UX- items with gated tasks marked, testing strategy, risks, task checklist |
| 2026-10-11 | Global marketplace impact assessment and design | Verified: GeoNames (CC BY 4.0) coverage per country (LK 9 provinces / 25 districts, curation needed for towns), ICU currency formatting (lakh grouping, 0/3 decimals, Rs override needed), packages (laravel-phone MIT, flag-icons MIT, symfony/intl not needed). Docs: architecture 13, design 05, requirements §4.11, DB/category/routes/packages updates, ADR-019–023, prototype global flows checked in headless Edge. |
| 2026-10-11 | Phase 3 UI/UX design draft | Design system (tokens with verified contrast, Geist, Lucide), components, IA + wireframes, forms/states/accessibility, clickable prototype checked in headless Edge (desktop/mobile, light/dark) |
| 2026-10-11 | Land price per perch approved and designed (LST-14, ADR-018) | Calculated from total price ÷ perches, Land only, null-safe |
| 2026-10-11 | Composer update plan and GitHub token instructions documented | No global change made |
| 2026-10-11 | **Installed PHP 8.4.26 (NTS x64) at `C:\php8.4`** | Separate install, SHA-256 verified. Not on PATH. PHP 7.4/8.1/8.2 untouched; `php` is still 8.2.12. Extensions, GD WebP/JPEG/FreeType, HTTPS and Composer verified. See [hosting](architecture/08-hosting-and-deployment.md) §7. |

## Decisions

All ADR-001 to ADR-018 are accepted ([decisions/README.md](decisions/README.md)). Some carry hosting verification conditions (architecture §12).

## Open Items

| Item | Where |
|---|---|
| **Global decisions G-1 to G-13** (launch countries, default country, location levels, currencies, formatting locale, land units, global search, URLs, dataset, legal per country, packages, moderation languages, country scoping) | [global design](architecture/13-global-marketplace.md) §12 |
| Phase 3 decisions Q-UX-1 to Q-UX-6 (Dev Talk association, product name, bottom nav, full prices on cards, typeface, card density) | [design/README.md](design/README.md) |
| Approval to update the shared global Composer 2.8.2 → 2.10.3 (11 advisories, 6 high; needs an Administrator terminal; plan + rollback documented). Alternative: marketplace-only Composer phar. Not blocking until Phase 5. | [developer tooling](operations/developer-tooling.md) §1 |
| Invalid GitHub OAuth token in the global Composer config: the owner runs the documented inspect/remove commands (token never read by Claude) | [developer tooling](operations/developer-tooling.md) §2 |

## Unverified Hosting Capabilities (Must Be Verified Before Scaffolding or Deployment)

PHP 8.3/8.4 availability per subdomain · extensions (intl, gd with WebP/FreeType, fileinfo, imagick optional) · `memory_limit` and upload limits · MySQL/MariaDB version · SSH · symlink support · cron minimum interval · mail hourly limit · inode/disk quota · outbound HTTPS · AutoSSL for the subdomain · DNS control for `dev-talk.com` · whether a separate cPanel account will be purchased · hosting/domain renewal after 12 Aug 2026.

## Known Issues / Blockers

- **Registration needs an active country.** All countries are seeded inactive, so `/register` shows "not open yet" until the Owner activates one (G-1 default: Sri Lanka). Admin activation UI arrives in T6.4; until then: `php artisan tinker` → `App\Models\Country::where('iso2','LK')->update(['is_active'=>true])`.
- **Assumption to confirm (AUTH-01):** the mobile number is optional at sign-up and required later before contact options can be shown.

- Repository has no commits yet. An initial docs commit on a feature branch is recommended.

## Next Step

Owner reviews the **Phase 4 plan** and completes **Gate 0** (plan approval, host PHP 8.3/8.4 check, Composer choice, local DB, initial commit). The undecided G-/Q-UX- items use the plan's defaults; only gated tasks wait. After approval, start T0.1 → T0.2.
