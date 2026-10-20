# Phase 7 — QA Report

| Field | Value |
|---|---|
| Date | 2026-10-21 |
| Scope | The whole application on `main` after Phase 6 (frontend) and the Phase 7 fixes: public site, accounts, posting, moderation, admin, background jobs |
| Brief | [07-testing-qa.md](../07-testing-qa.md); plan: [qa-plan.md](qa-plan.md) |
| Verdict | **Ready for Phase 8 (security review).** Every automated check passes; the 8 defects found during QA are fixed and guarded by tests. Remaining items are manual checks, owner content and hosting verification (§5–§6), none of which blocks the security review. |

## 1. What Was Tested

| Layer | How | Result |
|---|---|---|
| Unit and feature (PHPUnit) | Every route, action, policy, Livewire component, command and job; positive and negative cases | **824 / 824** on SQLite and on MariaDB 10.11 (the production database family) |
| Code coverage | PCOV, SQLite suite | **95.0 % of `app/`**; Actions 96 %, Services 95 %, Livewire 95 %, Controllers 98 %, Policies 95 % (target ≥ 70 %) |
| End-to-end journeys | Playwright in Microsoft Edge, real UI, real server ([journeys.spec.mjs](../../tests/Browser/journeys.spec.mjs)) | **11 / 11**: register + email verification, posting a car (Sri Lanka, province → district → town, photo), approval by keyboard shortcut, search, number reveal, save, report, a US ad in USD, rejection and resubmission, negative paths |
| Accessibility | axe (WCAG 2.0–2.2 A/AA), 22 screens × desktop light / dark / phone; structural checker on 38 screens | **66 / 66** and **38 / 38** |
| Lighthouse (mobile) | Home, category, listing, log in | Performance 96–99, Accessibility 100, Best Practices 100, SEO 100 (targets 85 / 95 / 95 / 95) |
| Authorization | Route sweep: every route classified; 42 pages × 6 roles; changing routes; ownership of ads ([sweep](../../tests/Feature/Security/AuthorizationSweepTest.php)) | All as designed; a deliberate mutation was caught |
| Global scenarios 1–15 | Traced to tests ([global-scenarios.md](global-scenarios.md)) | All covered and passing |
| Performance | 50,000 ads, 4 countries, 20,000 users ([performance.md](../operations/performance.md)) | Every query on its planned index; pages 50–360 ms (median) on a laptop |
| Static checks | Pint, PHPStan (Larastan), hardcoded-value guard, `composer audit`, `npm audit` | All clean; no known vulnerable package |
| Email content | All 12 emails rendered ([test](../../tests/Feature/Notifications/NotificationMailsRenderTest.php)) | Translated, valid links |

## 2. Defects Found and Fixed

Full log: [findings.md](findings.md).

| ID | Severity | Defect | Fix |
|---|---|---|---|
| F-01 | High | Search, country pages and posting failed (500) from the second visit on a real cache store | Exact cache allow-list; test on a real file cache |
| F-02 | Medium | Approving one's own ad (or a report about oneself) showed a bare "Access denied" page | Explained on the pane; no action buttons |
| F-03 | Medium | Decided location levels and labels (G-3) were not in the data: Sri Lanka had no districts | Seeded per country (LK, US, GB, IN); local Sri Lanka places re-imported |
| F-04 | Low | Coverage could not be measured | PCOV added to PHP 8.4 (owner approved) |
| F-05 | Medium | No test rendered the emails | All 12 rendered by a test (no template defect found) |
| F-06 | Low | Errors named database columns ("The country id field…") | Readable field names |
| F-07 | Low | One admin demo action did not check its own permission | Checks it like every other action |
| F-08 | Medium | Result pages ran queries per ad (N+1): category page 81 queries | 18 queries; page time down by ~60 % |

Two problems seen during QA were **not** product defects and are recorded so they are not mistaken for regressions: two MariaDB test runs at the same time corrupt the shared test database (run one at a time), and MariaDB's full-text search cannot see rows inside a test transaction (keyword tests must commit).

## 3. Not Covered by Automation

| Area | Why | Covered by |
|---|---|---|
| Real email delivery (inbox, spam folders, links in mail clients) | Mail goes to a log file locally | Manual check on staging (§5) |
| Turnstile bot check | Needs real Cloudflare keys | Unit-tested with fakes; manual check on staging |
| Screen readers and keyboard-only use | axe and the structural checks cannot judge the experience | Manual pass (§5) |
| Real phones and networks (slow 3G, small screens) | Lighthouse simulates; phone layouts checked at 320 px | Manual check on real devices (§5) |
| The production host (PHP, MariaDB, cron, uploads, HTTPS) | Hosting is not verified yet | Phase 9 checklist; re-run the performance check there |

## 4. Residual Risks

| Risk | Level | Note |
|---|---|---|
| Very common keywords are the slowest search (p95 ≈ 480 ms on 50k worst-case ads) | Low | Within the 800 ms target; the dedicated search engine is planned for scaling stage 4 |
| Performance measured on a laptop, not the host | Medium | Repeat `marketplace:perf-report` on staging before launch |
| Sri Lanka town list is the uncurated GeoNames list (17,743 places locally) | Medium (UX) | Owner review C1 (curated list) before launch |
| Legal and help page texts are placeholders | High for launch, none for testing | Owner content C3, legal review recommended |
| Data retention periods need a privacy/legal review | Medium | Before going live |
| Global Composer 2.8.2 on this machine has known advisories | Low (dev machine only) | Owner decision pending (developer tooling §1); the project runs Composer with PHP 8.4 |

## 5. Please Test by Hand (Owner)

On your local copy (http://localhost:8001) or later on staging:

1. **Keyboard only:** sign in, post an ad, and in the admin approve it, using only Tab, Shift+Tab, Enter, Space and the arrow keys. Every focused element should be clearly outlined.
2. **Screen reader:** with NVDA (free) and Edge or Chrome, search for an ad and open it; check that headings, buttons and the price are read sensibly.
3. **Phone:** quickest is Edge's phone view (F12, then the "Toggle device emulation" button). For a real phone on the same Wi-Fi, the site must listen on the network: serve with `--host=0.0.0.0 --port=8001`, set `APP_URL=http://<your PC's IP>:8001` in `.env` and allow PHP through Windows Firewall when asked; set `APP_URL` back afterwards. Then search, open an ad and post one with a photo from the camera.
4. **Real email (staging):** register with a real address; the verification email should arrive, look right and work.
5. **Real phone numbers:** add your own Sri Lankan number; check the Call and WhatsApp buttons on your ad open the right number.
6. **Words and wording:** read the main screens as a buyer and a seller; note anything unclear (all texts are in `lang/en`).

## 6. Before Launch (Not Phase 7)

Owner content C1–C6 (town list, makes/models, legal pages, moderation templates, name/logo, per-country readiness), hosting verification, staging deployment with real mail and Turnstile keys, the performance and Lighthouse re-runs on the host. Phase 8 (security review) comes next.

## 7. How to Repeat

| Check | Command |
|---|---|
| PHP tests (SQLite) | `C:\php8.4\php.exe vendor\bin\phpunit` |
| PHP tests (MariaDB) | the same with `DB_CONNECTION=mariadb`, `DB_DATABASE=marketplace_test` (one run at a time) |
| Coverage | `C:\php8.4\php.exe -d pcov.enabled=1 vendor\bin\phpunit --coverage-text` |
| Browser (axe + journeys) | `npm run build`, then `PHP_BINARY=C:/php8.4/php.exe npm run test:browser` |
| Lighthouse | `npm run lighthouse` |
| Performance | on a scratch database: `migrate:fresh --seed`, `marketplace:perf-seed --force`, `marketplace:perf-report` |
| Static | `vendor\bin\pint --test`, `vendor\bin\phpstan analyse`, `composer audit` (with PHP 8.4), `npm audit` |
