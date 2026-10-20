# Phase 7 — Testing and QA Plan (M8)

Brief: `docs/07-testing-qa.md`. Run every available test layer, test negative cases and authorization boundaries, fix confirmed regressions, report honestly, do not deploy. Each task is one branch and one review, as in Phase 5–6.

## Tasks

| ID | Task | Output |
|---|---|---|
| Q7.1 | **Baseline and findings log.** Run everything that exists: PHPUnit on SQLite and MariaDB, Pint, PHPStan, guard tests, `composer audit`, `npm audit`, production build, axe and Lighthouse. Record results and every finding (with severity) in `docs/qa/findings.md`. Fix quick, confirmed defects found on the way (small, separate commits). | Baseline report, findings log |
| Q7.2 ✓ | **End-to-end journeys** (Playwright, ADR-025), per country (Sri Lanka with 2 region levels; one 1-level country such as US): register → verify email (link read from the mail log) → post an ad with photos → moderator approves → the ad appears in search and on the listing page → a buyer reveals the number, saves and reports it. Negative paths: wrong password, unverified account, incomplete ad, rejected ad fixed and resubmitted. | `tests/Browser/journeys.spec.mjs` (11 journeys, `npm run test:browser`) |
| Q7.3 ✓ | **Global scenarios 1–15** (architecture doc 13 §11): map each to an existing test or add the missing one (feature or browser). | [Traceability table](global-scenarios.md), `tests/Feature/Global/GlobalScenariosTest.php` |
| Q7.4 ✓ | **Authorization boundary sweep:** every route and every admin action, as guest, member, another member, moderator, admin and owner, against an expected-status matrix generated from the route list (nothing left unlisted). | `tests/Feature/Security/AuthorizationSweepTest.php` |
| Q7.5 ✓ | **Performance re-check** at 50,000 listings / 4 countries (`marketplace:perf-seed`, `marketplace:perf-report`) after Phase 6: indexes used, p95 within target. | Updated `docs/operations/performance.md` |
| Q7.6 ✓ | **QA report:** results, open findings, residual risks, what the owner should test by hand (manual keyboard / screen-reader pass, real email, real phone numbers), readiness for Phase 8 (security review). | `docs/qa/qa-report.md` |

## Known Before Starting

| # | Finding | Status |
|---|---|---|
| F-01 | Cached Eloquent objects failed on file/database caches (500 on the second request) | Fixed (`fix/cache-serializable-classes`) |
| F-02 | Approving one's own ad showed a bare 403 page | Fixed (same branch) |
| F-03 | Decision G-3 (Sri Lanka Province → District, Great Britain two levels) is not in the seed data: every country starts with one region level, so the local import created provinces only | Open: Q7.1 proposes seeding the decided levels; the owner's local Sri Lanka places then need a re-import |
| F-04 | Coverage cannot be measured: PCOV/Xdebug is not installed in `C:\php8.4` | Open: needs the owner's approval to add the extension (implementation plan §9) |

## Rules

- Test databases only: `marketplace_test` (MariaDB), in-memory SQLite, the browser-test SQLite. The owner's local database (`marketplace`) is never reset by tests.
- One MariaDB suite at a time (parallel runs on `marketplace_test` corrupt each other's schema).
- Every fix: a failing test first, then the fix, then the full gate.
