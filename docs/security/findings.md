# Phase 8 — Security Findings Log

Plan: [security-plan.md](security-plan.md). Severity scale in the plan.

## Findings

| ID | Severity | Area | Finding | Status |
|---|---|---|---|---|
| S-01 | Medium | Rate limits | The design (architecture 06 §3) sets `image-upload` 60/hour per user and `listing-submit` 30/hour per IP, but neither limiter exists: the photo routes (`listings.photos.*`) and ad submission have no throttle. Only the daily new-ad limit (LST-09) and the per-ad photo cap apply. One verified account can upload, remove and upload again without limit, and each upload makes the server decode an image of up to 40 MP and 8 MB, so CPU and disk can be exhausted. | **Fixed in S8.2:** limiter `listing-submit` (30/hour per IP, shared by new drafts and submissions) on `listings.store` and `listings.submit`; photo uploads counted per file, 60/hour per account, in `ListingPhotosController` (a file over the limit gets a photo error with the wait time, the others in the request are unaffected). Retest: `RateLimitsTest` fails 4/4 without the fix, passes 4/4 with it. |
| S-02 | Low | Configuration | `.env.example` holds local values (`APP_ENV=local`, `APP_DEBUG=true`, `LOG_LEVEL=debug`, `SESSION_SECURE_COOKIE=false`). A production `.env` copied from it unchanged would show stack traces and send the session cookie without `Secure` (the explicit `false` overrides the production default in `config/session.php`). Nothing checks this at deploy time. | **Fixed in S8.6:** `php artisan marketplace:check-production` fails (exit 1) on every unsafe production setting (debug, environment, key, HTTPS and public host, cookie flags, array session/cache, mail to a log, CSP report-only) and warns on weaker ones; it is a step of the deployment process (hosting doc §5.2). Retest: `ProductionCheckTest` (a safe configuration passes and prints no secrets; each of 13 unsafe settings fails on its own). A production `.env` template follows in Phase 9. |
| S-03 | Low | Uploads (robustness) | Several photos chosen at once can exceed PHP's request limit (`post_max_size`, 12 MB required by the hosting doc; three 5 MB phone photos are 15 MB). PHP then drops the whole request and Laravel answers 413 with a generic page; the upload script reloads the page, so the photos silently do not appear and the seller gets no message. Not exploitable (nothing is processed), but a likely real-world failure. | **Fixed in S8.4 (owner approved 2026-10-21):** `posting.js` sends the photos one per request, in order, with "Photo 2 of 5" progress; a photo over the 8 MB limit is not sent and is listed after the reload (as is any request that fails: 413, server error, connection lost); the server combines the outcome of one batch (`batch_continue`) so the reloaded page shows every photo's result; a too-large plain form gets a 413 page. Retest: `UploadAbuseTest` (combined batch outcome, 413 page), `PostingPhotosSubmitTest` (limits and messages handed to the script), browser journey with two photos and a 9 MB file. |
| S-04 | Medium | Sessions | A **password reset** ("forgot password") did not end the account's other sessions: Fortify only cycles the remember-me token, so anyone already signed in (for example someone who took the account over, the usual reason for a reset) stayed signed in. A password **change** already signed out other devices (authentication doc §3: "logout other sessions on password change"). | **Fixed in S8.5:** `ResetUserPassword` ends every session of the account and cycles the remember token (`UserSessions::endAll`). Retest: `PasswordResetTest::test_a_reset_signs_the_account_out_everywhere` fails without the fix, passes with it. |
| S-05 | Medium | Account security | No security emails: changing the password, resetting it, changing the email address or turning off two-factor authentication sends nothing. A takeover goes unnoticed, and after an email change the old address is never told, so an attacker with a session can change the email, then reset the password, and the owner is locked out without warning. (Authentication doc: "email alerts for password/role changes: not yet implemented".) | **Fixed in S8.5 (owner approved 2026-10-21):** the `SecurityChange` notification (queued, mail budget) after a password change (`UpdateUserPassword`) or reset (`ResetUserPassword`), two-factor turned off or recovery codes regenerated (Fortify events), and after an email change to the **old** address (new address masked, `n***@example.net`). Each says what changed and when, what to do if it was not them; the only link is the public forgot-password page. Retest: `SecurityChangeNoticesTest` (12 tests: each trigger, nothing on failed or unchanged saves, rendering per kind, masking). |
| S-06 | Low | Privacy (G6, after Phase 8) | Search counts (G1) stored any search text. A visitor searching for a phone number (to find a seller, or their own) would have it counted; searched 5+ times, it could appear in suggestions and popular or trending searches. | **Fixed in G6:** `SearchTerms::looksPersonal()` (7+ digits in a row, spaces ignored): such terms are never recorded, and are filtered out of suggestions, popular and trending searches in case any were stored earlier. Retest: `AudienceFeaturesSecurityTest::test_phone_numbers_are_never_recorded_or_shown_as_popular_searches`. See the security report §7. |

## Baseline Checks (S8.1, 2026-10-21)

| Check | Result |
|---|---|
| `composer audit` (PHP 8.4) | No advisories |
| `npm audit` | 0 vulnerabilities |
| Secrets scan of every blob in the git history (2,682 blobs, all branches): app keys, GitHub and AWS tokens, private keys, Slack, Stripe, Turnstile secrets, assigned passwords/secrets/tokens | No secrets. One match was a false positive: the empty `AWS_SECRET_ACCESS_KEY=` in `.env.example` (the pattern ran into the next line). Every committed `TURNSTILE_*` and `AWS_SECRET_ACCESS_KEY` value is empty. |
| Tracked files | Only `.env.example`; `.env`, `.env.*`, `auth.json`, keys, logs and build output are ignored |
| Raw Blade output (`{!! !!}`) | 3 places, all safe: the 2FA QR code SVG (generated by Fortify), icons (built-in SVGs, class attribute escaped), JSON-LD (`JSON_HEX_TAG`/`AMP`/`APOS`/`QUOT`, so `</script>` cannot appear) |
| Raw SQL | All constant strings or bound parameters (`whereRaw` with `?`); the `EXPLAIN` in `PerformanceReport` is a developer command on its own query. Full-text terms are reduced to letters and digits before boolean mode. |
| Mass assignment | Every model declares `$fillable` except two pivot models (`CategoryAttribute`, `CountryMeasurementUnit`), unguarded by Laravel's default; verify in S8.3 that only validated admin data reaches them. `forceFill` is used only inside actions with fixed keys. |
| Livewire file uploads | No component uses `WithFileUploads`; photos go through a plain controller (`ListingPhotosController`) behind `auth`, `verified` and the listing policy. |
| Configuration defaults | `config/app.php` debug defaults to `false`; session cookie HttpOnly, SameSite=Lax, Secure by default in production; see S-02 for the example file. |
| Rate limiters defined | `register`, `login`, `password-reset`, `two-factor`, `verification`, `contact-reveal`, `report`, `search`. Missing against the design: `image-upload`, `listing-submit` (S-01). |

## S8.2 Rate Limits (2026-10-21)

| Check | Result |
|---|---|
| Every limiter in architecture 06 §3 exists with the designed values | Yes, asserted per limiter (login 5/min per email+IP and 30/min per IP, two-factor 5/min, verification 6/min, register 5/hour, password reset 3/min and 10/hour, listing-submit 30/hour, contact reveal 30/hour per user, report 10/day, search 120/min, photos 60/hour per account) |
| Every throttled route uses its limiter, nothing extra or missing | Yes: an exact route → limiter table (17 routes), including registration and reset, which are throttled by `ThrottleAuthEndpoints` by route name |
| The new limits stop requests | Ad submission: 429 after the limit, per IP, shared by drafts and submissions. Photos: files over the limit rejected, removing a photo does not give the allowance back, other accounts unaffected |
| Search caps | Page ≤ `max_page` (100), keyword ≤ 100 characters, ≤ 10 attribute filters, at most 10 words in the full-text query (already enforced by `SearchCriteriaFactory` / `MysqlListingSearch`) |
| Livewire's own upload endpoint | Throttled by Livewire (60/min) and unusable without a component that uses file uploads (none does); checked again in S8.4 |
| Not throttled, accepted | Wizard step saves, favourites, account settings and admin actions: cheap, signed-in and (admin) staff-only; the daily new-ad limit and per-ad photo cap bound the expensive parts |

## S8.3 Input Handling (2026-10-21)

No new findings. Evidence:

| Check | How | Result |
|---|---|---|
| Stored XSS | `StoredXssTest`: a payload with a script tag, an image error handler, an attribute break-out, `javascript:` / `data:` links and Markdown links, written straight to the database (bypassing input validation) into user name, status reason, ad title, description, area text, moderator note, report details, region and town names; then the listing, seller, country home, search, category and global pages, the account pages and the posting review step, the moderation, reports and users admin screens, and 7 emails | Always escaped; the test first checks the payload is actually on the page, so a passing check cannot be a page that simply omits it |
| Reflected XSS | Payload in `q`, `sort`, `view`, `region` on global and country search | Escaped; any redirect stays on the site |
| Static pages | Markdown with raw HTML, `javascript:` and `data:` links | HTML stripped, unsafe links dropped, `https:` links kept |
| SQL injection | `SqlInjectionTest`: 11 probes (tautologies, comments, `UNION`, stacked `DROP`, `SLEEP`, LIKE wildcards, full-text boolean operators, a null byte) in 14 search parameters and attribute filter keys and values on 3 search pages, and in every admin search and `#[Url]` filter (users, blocklist, countries, attributes, audit log, moderation, reports); runs on SQLite and MariaDB (full-text boolean mode) | No error, no extra rows (a keyword tautology finds nothing, `%` in user search matches nobody), tables intact |
| Code review | Raw SQL inventory (S8.1); `LIKE` searches escape `%`, `_`, `\`; sort columns are fixed choices, never input | Bound parameters throughout |
| Mass assignment | `MassAssignmentTest`: registration with status, verification date, roles, 2FA, legal hold; posting details and price steps with owner, status, publication, moderation, country, currency, cover | All ignored; the forms themselves still saved. Controllers pass `$request->only([...])`; the two unguarded pivot models are written only from validated admin input (`AttributesAdmin::saveLink`) |
| Open redirects | Code review of every `redirect()->to()` | Targets are built from routes, the matched country or the listing's own URL; `back()` and `intended()` are Laravel's same-site values |
| Server validation | Inventory: no `$request->all()`; actions validate with explicit rules; Livewire admin actions validate and check permissions (Q7.4 sweep) | Covered |

## S8.4 Upload Abuse (2026-10-21)

Through the real endpoint (`UploadAbuseTest`, signed-in seller, JSON like the upload script):

| Attack | Result |
|---|---|
| Decompression bomb: a valid 70-byte PNG whose header claims 30,000 × 30,000 (900 MP) | Rejected from the header before decoding (peak memory grows < 64 MB); nothing stored |
| Path traversal in the file name (`../../../public/evil.php.jpg`) | Stored as `YYYY/MM/{listing}/{32 hex}-lg/sm.webp`; the client name is never used |
| PHP file, PHP renamed `.jpg`, real JPEG named `.phtml`, SVG with `onload`, HTML page | All 5 rejected |
| JPEG with PHP appended (polyglot) | Accepted as a photo; the stored variants are re-encoded and contain no `<?php` |
| 12 files in one request, category limit 3 | 3 processed, 9 refused before processing |
| Odd shapes: a string, nested arrays, empty list, a single file instead of a list | Handled (422 or redirect), nothing unexpected stored |
| Someone else's draft / guest / unverified account | 403 / login / verification notice; nothing stored |
| Livewire's upload endpoint without a signed URL | 401/403 (no component uses file uploads, so no signed URL is ever issued) |
| Request over `post_max_size` | 413, no processing; was a silent failure for sellers (**S-03**, fixed: one photo per request, clear messages, 413 page) |

Covered earlier and still valid: EXIF/GPS stripped, GIF/SVG/double extensions, content-type mismatch, size and dimension limits, corrupt files leave nothing behind, per-category photo limit under a row lock, script guard `.htaccess` in `uploads` mode (ListingImageTest, T4.3); hourly photo limit (S8.2). To verify on the host (Phase 9): the guard actually blocks execution under LiteSpeed, and `upload_max_filesize` ≥ 10M, `post_max_size` ≥ 12M, `memory_limit` ≥ 256M (local: 10M, 12M, 512M).

## S8.5 Authentication, Sessions and CSRF (2026-10-21)

| Check | How | Result |
|---|---|---|
| CSRF on every state-changing route | `CsrfProtectionTest` switches Laravel's check back on (it is skipped in unit tests) and sends all 33 POST/PUT/PATCH/DELETE routes (forms, Fortify, Livewire) a request without a token, as a guest and signed in | 419 for every one; every such route is in the `web` group; no exceptions configured |
| Cross-site and sibling-subdomain requests | `Sec-Fetch-Site: cross-site`, `same-site` (another site on the parent domain), `none`, a forged token | Refused (same-site is **not** trusted, so other sites on the parent domain cannot post here); same-origin and the page token accepted |
| Session fixation | `SessionSecurityTest`: a session id obtained before login | Replaced at login, CSRF token too; a failed login signs nothing in; Fortify also regenerates after the 2FA challenge (code review) |
| Logout | Same test | Session invalidated, token replaced, session data gone |
| Password reset and change | `PasswordResetTest`, `PasswordUpdateTest` | Change: other sessions end (existing). Reset: **did not** (S-04, fixed) |
| Security notices | `SecurityChangeNoticesTest` | **Were missing** (S-05, fixed): password change and reset, email change (old address), 2FA off, new recovery codes |
| Host header injection | Reset requested through an alias host; through a forged host | Link always uses `APP_URL`; forged host gets 400 and no email |
| Account enumeration | Existing tests | Login and reset answers are identical for known and unknown emails; registration reveals a taken email (accepted trade-off, rate-limited 5/hour/IP) |
| Throttling, suspension, bans, staff idle/absolute timeouts, no remember-me for staff, 2FA enforcement and recovery codes, password confirmation timeout | Existing tests (`LoginTest`, `AccountStatusAndAdminAccessTest`, `StaffSessionLimitsTest`, `UsersStaffAdminTest`, `RateLimitsTest`) | Pass |
| Password rules | Code review | Minimum 10, maximum 255; `uncompromised()` (HIBP k-anonymity) in production only, so tests and local work need no network |
| Privilege changes | Code review | Permissions are read per request (Spatie cache flushed on role change); suspension, ban, deletion and staff 2FA reset end all sessions |

## S8.6 Secrets, Privacy and Errors (2026-10-21)

| Check | How | Result |
|---|---|---|
| Unsafe production configuration | `marketplace:check-production`, `ProductionCheckTest` | **S-02 fixed** (see above) |
| Error pages | `ErrorPagesTest` with debug off | HTML and JSON 500 show no exception details (JSON: only `Server Error`); custom pages for 400–503 including the new 413 |
| Security headers in production | `SecurityHeadersTest` | HSTS `max-age=31536000` over HTTPS in production, **without** `includeSubDomains` (other sites on the parent domain unaffected); nosniff, frame denial, referrer and permissions policies and a nonce CSP on every response including errors (existing) |
| Phone numbers and email addresses | `PrivacyExposureTest`: seller with phone, WhatsApp number and email, most open visibility; listing, seller, home, search, category and global pages as guest and signed-in visitor | Never in the HTML (text, links, data attributes, JSON-LD, Livewire snapshots); a number comes only from the rate-limited, logged reveal endpoint |
| Serialised user records | Same test | Password, remember token, 2FA secret and recovery codes hidden |
| IP addresses | Code review, `ContactRevealTest` | Stored only as HMAC hashes (keyed with the app key) in contact reveals and the audit log |
| Logs | Code review of every `Log::` call | Only ids, statuses and error classes; no passwords, tokens, emails, phone numbers or raw IPs. Laravel keeps `password`, `password_confirmation` and `current_password` out of flashed input. Logs live in `storage/`, outside the web root |
| Secrets in the repository | S8.1 scan | None; `.env` and keys ignored |
| Debug tools in production | `composer.json` | Only development dependencies (Pail, Collision, PHPUnit, Larastan, Pint) under `require-dev`; the release is built with `--no-dev` |
| Public folder | Listing | `index.php`, `.htaccess`, `build/`, `favicon.ico`, `licenses/`, `storage` link, `uploads/` (script guard, S8.4); no configuration or source files |
| Retention | `RetentionTest` (M7) | Deletion and anonymisation periods enforced daily; legal review of the periods still pending (owner) |
| Background runner | Code review | `RunBackgroundTasks` is middleware on normal requests with a lock and minimum interval, not a public endpoint |
