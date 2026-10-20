# Phase 8 — Security Review Plan (M9)

Brief: [08-security-review.md](../08-security-review.md). Review the built marketplace against the security design ([architecture 06](../architecture/06-security-and-uploads.md), [authorization](../architecture/05-authorization.md), [authentication and 2FA](../architecture/12-authentication-and-2fa.md), [data retention](../architecture/09-data-retention.md)) and an OWASP ASVS level 1 style checklist. Reproduce issues safely (local and test databases only), fix approved findings with a failing test first, and keep retest evidence. Each task is one branch and one review.

## Tasks

| ID | Task | Output |
|---|---|---|
| S8.1 | **Plan and baseline:** dependency audits, secrets scan of the whole git history, rate-limiter inventory against the design, inventories of raw HTML output, raw SQL and mass assignment, configuration defaults. Findings log started. | This plan, [findings.md](findings.md) |
| S8.2 | **Rate limits and abuse:** every limiter in architecture 06 §3 exists, is applied to its routes and returns 429; fix S-01 (photo uploads, ad submission). Pagination and filter-count caps. | Feature tests per limiter |
| S8.3 | **Input handling:** server validation on every form and Livewire action, stored-XSS sweep (a script payload saved through every user-editable text field, then every page that shows it checked for escaping), SQL injection probes on search, sort, filter and attribute keys, open redirects, mass assignment (including the two pivot models). | `tests/Feature/Security/InputHandlingTest.php` |
| S8.4 | **Uploads:** re-run the T4.3 abuse cases against the HTTP endpoint (polyglots, renamed scripts, SVG, decompression bomb, oversized and many-file requests), Livewire temporary upload endpoint unusable, uploaded paths never executable, no original stored. | Upload abuse tests |
| S8.5 | **Authentication, sessions, CSRF:** CSRF enforced on every state-changing route (including Livewire), session regeneration on login and privilege change, logout and ban end sessions, staff timeouts, 2FA enforcement for Owner/Admin, password reset and verification links immune to Host header injection, no account enumeration, password rules. | Feature tests |
| S8.6 | **Secrets, privacy and errors:** production configuration check (S-02), error pages without stack traces or secrets, logs without passwords, tokens or raw IPs where hashing is required, phone numbers absent from HTML, IP hashing, retention jobs, security headers and CSP in production mode. | Tests and a production checklist for Phase 9 |
| S8.7 | **Security report:** what was checked, findings and fixes with retest evidence, residual risks, items to verify on the host (Phase 9). | `docs/security/security-report.md` |

## Rules

- Local machine and test databases only (`marketplace_test`, SQLite). Nothing against production, no denial-of-service against any shared service, no real secrets printed or committed.
- Every fix: a failing test first, then the fix, then the full gate (Pint, PHPStan, SQLite and MariaDB suites one at a time, browser tests when the UI changes).
- A finding that changes behaviour users see, or the design, waits for the owner's approval before the fix.
- Severity: **High** (exploitable for data access or account takeover), **Medium** (abuse or exposure needing effort or a precondition), **Low** (hardening, defence in depth), **Info** (checked, no issue).
