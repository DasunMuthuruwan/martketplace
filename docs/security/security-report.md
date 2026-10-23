# Phase 8 — Security Report

| Field | Value |
|---|---|
| Date | 2026-10-21 |
| Scope | The whole application on `main` after Phase 7: public site, accounts, posting and photo uploads, contact reveal, moderation and admin, emails, background jobs, configuration |
| Brief | [08-security-review.md](../08-security-review.md); plan: [security-plan.md](security-plan.md); full evidence: [findings.md](findings.md) |
| Method | Review against the security design ([architecture 06](../architecture/06-security-and-uploads.md), [05](../architecture/05-authorization.md), [12](../architecture/12-authentication-and-2fa.md), [09](../architecture/09-data-retention.md)) and an OWASP ASVS level 1 style checklist. Attacks reproduced as automated tests on local and test databases only; every fix test-first; nothing run against production |
| Verdict | **Ready for Phase 9 (deployment).** 5 findings, all fixed and guarded by tests; no open finding. The remaining items depend on the real host and are listed in §5 for the Phase 9 checklist. |

## 1. Findings and Fixes

| ID | Severity | Finding | Fix | Retest evidence |
|---|---|---|---|---|
| S-01 | Medium | Photo uploads and ad submission had no rate limit, against the design: one account could upload, remove and upload again without limit (each upload is decoded and re-encoded) | `listing-submit` limiter (30/hour per IP) on new drafts and submissions; photo uploads limited to 60 files/hour per account, counted per file | `RateLimitsTest`: fails 4/4 without the fix |
| S-02 | Low | `.env.example` carries local values (debug on, cookie not Secure) and nothing checked a production configuration | `php artisan marketplace:check-production` fails on every unsafe setting and is a deployment step | `ProductionCheckTest`: a safe configuration passes, each of 13 unsafe settings fails |
| S-03 | Low | Several photos chosen together could exceed `post_max_size`: bare 413, the seller saw nothing | Photos sent one per request with progress; oversize photos and failed requests named; 413 page | `UploadAbuseTest`, `PostingPhotosSubmitTest`, browser journey (two photos and a 9 MB file) |
| S-04 | Medium | A password reset left the account's other sessions signed in (an intruder stayed in) | A reset ends every session and cycles the remember token | `PasswordResetTest`: fails without the fix |
| S-05 | Medium | No security emails: password, email or 2FA changes went unnoticed; an intruder could change the email, then the password | `SecurityChange` notices after a password change or reset, 2FA off, new recovery codes, and an email change (sent to the old address) | `SecurityChangeNoticesTest` (12 tests) |

No High findings. S-06 (Low, privacy) was found in the G6 pass after this review and fixed; see §7 and the findings log. Nothing was found that exposes another user's data or allows an account takeover without the victim's credentials.

## 2. What Was Checked

| Area | Result | Tests |
|---|---|---|
| Authorization and IDOR | Every route classified; 42 pages × 6 roles; ownership of ads; staff never decide on their own ads (Q7.4) | `AuthorizationSweepTest`, policy and hierarchy tests |
| Input validation and mass assignment | No `$request->all()`; allow-listed fields; privileged columns ignored | `MassAssignmentTest` |
| XSS | Payloads in 10 user texts, escaped on public, account and admin pages, in 7 emails and static Markdown pages; reflected search input escaped | `StoredXssTest` |
| SQL injection | 11 probes in every search parameter, attribute filter and admin filter, on SQLite and MariaDB (full-text boolean mode) | `SqlInjectionTest` |
| Open redirects | Every redirect target built by the application | Code review |
| Uploads | 900 MP decompression bomb refused from the header; scripts, SVG, HTML refused; polyglot neutralised; client names never used; originals never stored; EXIF removed; many-file requests stop at the photo limit; Livewire upload endpoint unusable | `UploadAbuseTest`, `ListingImageTest` |
| Rate limits | All 9 designed limiters with their values, on the right 17 routes | `RateLimitsTest` and per-feature tests |
| Authentication | Generic errors, timing-safe login, throttling, suspension and bans, password rules, 2FA for Owner/Admin, recovery codes, password confirmation timeout | `LoginTest`, `AccountStatusAndAdminAccessTest` and others |
| Sessions | New session id at login and after 2FA; logout invalidates; staff idle and absolute timeouts; no remember-me for staff; sessions end on password change, reset, suspension, ban, deletion | `SessionSecurityTest`, `StaffSessionLimitsTest`, `PasswordResetTest` |
| CSRF | All 33 state-changing routes refuse a tokenless request (check re-enabled in the test); cross-site and sibling-subdomain requests refused | `CsrfProtectionTest` |
| Host header and URLs | Forged hosts get 400; reset and verification links always use `APP_URL` | `TrustedHostsTest`, `SessionSecurityTest` |
| Headers | CSP with nonces, `nosniff`, frame denial, referrer and permissions policies, HSTS in production without `includeSubDomains` | `SecurityHeadersTest` |
| Privacy | Phone numbers only through the logged, rate-limited reveal endpoint; emails never public; IPs stored only as HMAC hashes; user secrets hidden when serialised; retention jobs | `PrivacyExposureTest`, `ContactRevealTest`, `RetentionTest` |
| Errors and logs | No exception details in HTML or JSON errors with debug off; logs hold ids only | `ErrorPagesTest`, code review |
| Secrets and dependencies | No secrets in any of the 2,682 blobs in the git history; `composer audit` and `npm audit` clean (re-run 2026-10-21) | S8.1 scan |

## 3. Test Results

| Check | Result |
|---|---|
| PHPUnit, SQLite | 892 / 892 |
| PHPUnit, MariaDB 10.11 | 892 / 892 |
| Browser (Playwright, Edge): axe in 3 views and 12 journeys | All passing (last full run in S8.4) |
| Pint, PHPStan (Larastan) | Clean, 0 errors |
| `composer audit` (PHP 8.4), `npm audit` | No advisories |

Phase 8 added 15 security test files (`tests/Feature/Security/`).

## 4. Residual Risks

| Risk | Level | Note |
|---|---|---|
| Registration reveals that an email is already registered | Low | Accepted trade-off (clear message for real users), limited to 5 registrations/hour/IP |
| Bot protection without Turnstile keys falls back to the honeypot and time trap | Low until keys are set | `marketplace:check-production` warns; set keys before launch |
| The IP hash is keyed with `APP_KEY` | Low | Rotating the key only resets reveal-threshold history; never rotate without a reason |
| Security notices ask the user to reply to the email | Low | The from (or reply-to) address must be a monitored mailbox |
| Scraping of public listing pages | Low | Search limited to 120/min per IP; phone numbers are not on the pages |
| Moderators and admins see personal data they need (contact details by permission) | Accepted | Audit log records staff actions; 2FA required for Owner/Admin |
| Data retention periods | Medium (legal) | Implemented; privacy/legal review of the periods pending (owner) |
| Global Composer 2.8.2 on the development machine has advisories | Low (dev only) | Owner decision pending; the project uses Composer through PHP 8.4 and is unaffected |

## 5. To Verify on the Host (Phase 9 Checklist)

1. `php artisan marketplace:check-production` passes with the production `.env` (no FAIL; review every WARN).
2. HTTPS works and the HSTS header is present; the session cookie arrives with `Secure`.
3. Uploads: the `.htaccess` script guard really blocks execution in the uploads folder under LiteSpeed (upload a harmless `.php` test file by hand, request it, expect 403, delete it).
4. PHP limits: `upload_max_filesize` ≥ 10M, `post_max_size` ≥ 12M, `memory_limit` ≥ 256M; `max_file_uploads` ≥ 20.
5. `.env`, `storage/` and `vendor/` are outside the web root, or blocked if they cannot be.
6. Outbound HTTPS to `challenges.cloudflare.com` (Turnstile) and the HIBP password check.
7. Real mail: verification, reset and a security notice arrive (SPF/DKIM aligned).
8. `TRUSTED_PROXIES` matches the real proxy or CDN in front of the site (not `*`), so rate limits see real client addresses.
9. Rate limits and the cache store work across requests on the host (one login throttle check by hand).
10. Error pages: a 404 and a forced 500 on staging show the custom pages without details.

## 6. How to Repeat

| Check | Command |
|---|---|
| All security tests | `C:\php8.4\php.exe vendor\bin\phpunit tests\Feature\Security` |
| Full suites | see [QA report §7](../qa/qa-report.md) |
| Production configuration | `php artisan marketplace:check-production` (on the host) |
| Dependencies | `composer audit` (with PHP 8.4), `npm audit` |

## 7. Addendum: Features Added After Phase 8 (2026-10-23)

Paid boosts (ADR-026) and the audience features (ADR-027) were built after this review, before deployment. Each was reviewed against the same checklist as it was built; G6 added a final pass over the audience features.

| Area | Check | Result | Tests |
|---|---|---|---|
| Boosts: payment amounts | The price is never taken from the form; staff cannot change the amount when marking paid | Amount always from the price table | `BoostSecurityTest` |
| Boosts: bank details and staff notes | Shown to sellers, in emails and admin lists | Escaped everywhere | `BoostSecurityTest` |
| Boosts: authorization | Sellers order only for their own live ads; staff cannot boost their own ads; permissions per screen | Enforced and audited | `AuthorizationSweepTest`, boost feature tests |
| Reviews: text from users | Comments, seller replies and report details on the seller page and the moderation screen | Escaped | `AudienceFeaturesSecurityTest`, `StoredXssTest` |
| Reviews: eligibility | Extra form fields (reviewer, seller, comment status, listing) | Ignored: eligibility comes from the buyer's own contact reveal; comments always wait for moderation | `AudienceFeaturesSecurityTest`, `SellerReviewsTest` |
| Reviews: targets | Banned, unknown or non-seller accounts | 404, nothing stored | `AudienceFeaturesSecurityTest` |
| Reviews: abuse | Limit 10 reviews a day per buyer; reports once per person, never by the author; staff never moderate reviews about or by themselves; blocklist screening of comments and replies | Enforced and audited | `SellerReviewsTest`, `ReviewModerationTest` |
| Search counts: privacy | Counts per country, term and day only (no user, session or IP); bots not counted; pruned after 400 days | As designed | `LiveSearchTest`, `RetentionTest` |
| Search counts: personal data | A term with a phone-length run of digits (7 or more, spaces ignored) | **Was recorded and could appear in popular searches once searched 5+ times (fixed in G6, S-06):** never recorded, never shown in suggestions, popular or trending searches | `AudienceFeaturesSecurityTest` |
| Suggestions | Rare terms (under 5 searches), emails and phone numbers; rate limit 180/min per IP | Never shown; limited | `AudienceFeaturesSecurityTest`, `LiveSearchTest` |
| Share links | Service addresses come from configuration; no phone number in the link | As designed | `ShareLinksTest`, `ListingPageTest` |

Residual risk added: seller ratings depend on unverifiable deals (a buyer who revealed a number may not have bought). Mitigated by the contact requirement, account age, moderation of comments, reporting and the trust rules; the review rules in the Terms need legal review before launch (owner).
