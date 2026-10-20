# QA Findings Log (Phase 7)

Severity: **High** (data loss, security, a main flow broken), **Medium** (a flow broken in some conditions, wrong data shown), **Low** (confusing UX, cosmetic, docs).

## Baseline (Q7.1, 2026-10-18, `main` at 154494c)

| Check | Result |
|---|---|
| PHPUnit, SQLite | 795 / 795 pass; 796 / 796 after Q7.1 |
| PHPUnit, MariaDB 10.11 (`marketplace_test`) | 795 / 795 pass; 796 / 796 after Q7.1 (run alone; two MariaDB runs at once corrupt each other's schema, see note) |
| Pint | pass |
| PHPStan (Larastan) | 0 errors |
| Hardcoded-value guard (rule P1 / scenario 15) | pass (part of PHPUnit) |
| `composer audit` (run with PHP 8.4) | no advisories |
| `npm audit` | 0 vulnerabilities |
| Production build | pass (CSS 22.9 KB, JS 5.3 KB gzip) |
| axe (66 checks) | 66 / 66 pass (re-run after the Q7.1 changes) |
| Lighthouse | pass (F6.1 part 2, 2026-10-16; no public page changed since) |
| Coverage (PCOV, SQLite suite, 2026-10-20) | **95.0 % of `app/`** (5,187 / 5,461 lines): Actions 96.4 %, Services 95.0 %, Livewire 95.1 %, Http/Controllers 98.0 %, Http/Middleware 94.9 %, Policies 94.7 %, Models 95.8 %, View 90.0 %; Notifications 24.6 % before F-05 |

**Note (test process):** two MariaDB suites started at the same time on `marketplace_test` produced 141 false failures (missing tables, deadlocks, "table definition has changed"). Not a product defect: run one MariaDB suite at a time.

## Findings

| ID | Severity | Found | Finding | Status |
|---|---|---|---|---|
| F-01 | High | Owner's first local run | Search, country pages and the posting form returned 500 from the second request on any real cache store: Laravel 13's `cache.serializable_classes => false` turned cached Eloquent objects into `__PHP_Incomplete_Class`. Tests use the array store, which never serializes. | **Fixed** (e7f525a): exact allow-list (Eloquent Collection, Category, Country) and `CacheSerializationTest` on a real file cache |
| F-02 | Medium | Owner's local test | Approving one's own ad showed a bare 403 page (refusal is intended, CD-06). Same for reports about oneself. | **Fixed** (7dea89e): explained on the pane, no action buttons, own ads marked in the queue |
| F-03 | Medium | Q7.1 | Decision G-3 (location levels and labels per country) was never put into data: every country was created with 1 region level and generic labels, so Sri Lanka imported provinces only, without districts, and showed "Region" / "City or town" instead of Province / District / Town. | **Fixed** (Q7.1): `location_overrides` in `database/data/countries.php` (LK, US, GB, IN), applied when a country is created; seeding test added, and the location-chain test now uses province → district → town (a town under the wrong district is refused). Existing databases keep their rows (seeders never overwrite admin data): the owner's local Sri Lanka places need a re-import (owner decision, see the Q7.1 report). |
| F-04 | Low | Q7.1 | Code coverage cannot be measured: no PCOV / Xdebug in `C:\php8.4` (target ≥ 70 % for Actions, Services, Policies, Http, Livewire). | **Fixed** (owner approved 2026-10-20): PCOV 1.0.12 (PHP 8.4 NTS x64) in `C:\php8.4\ext`, `extension=pcov` with `pcov.enabled=0` (off by default; coverage runs pass `-d pcov.enabled=1`); `php.ini` backed up as `php.ini.bak-before-pcov-2026-10-20`. All target areas above 70 %. |
| F-05 | Medium | Q7.2 (coverage) | No test rendered the emails (notifications are faked), so a broken template or missing translation would only show in a user's inbox: Notifications 24.6 % covered. | **Fixed** (Q7.2): `NotificationMailsRenderTest` renders all 12 emails (subject, HTML, no untranslated keys or placeholders, links from APP_URL); all passed, no template defect found. |
| F-06 | Low | Q7.2 (journey) | Validation messages named database columns: "The country id field is required." (also city id, region id, category id…). | **Fixed** (Q7.2): `lang/en/validation.php` field names merged over the framework messages ("The country field is required."); test added. |
| F-07 | Low | Q7.4 | The design-system screen's demo action `toast()` did not check its permission itself (only `mount()` did). Not reachable without access (a component cannot be mounted without the permission), but every other admin action checks its own permission. | **Fixed** (Q7.4): `requirePermission(settings.manage)` in the action; test added. |
| F-08 | Medium | Q7.5 | Result pages ran one or more queries per ad (N+1): the cover-photo fallback loaded each ad's photos when it had no cover, the filter sidebar looked every category link up by slug, and My ads asked "is this ad public?" row by row. On 50,000 ads: category page 81 queries / 536 ms, My ads 55 / 599 ms. | **Fixed** (Q7.5): no photo lookup when the count is zero, category links from the cached tree, public IDs in one query per page; `QueryCountTest` guards it. After: category 18 queries / 197 ms, My ads 16 / 188 ms (docs/operations/performance.md). |
