# Existing Dev Talk Project — Inspection Findings

| Field | Value |
|---|---|
| Date | 2026-10-11 |
| Method | Read-only inspection of local files. Nothing was changed, installed, run or deployed. |
| Source | `C:\php8.2\htdocs\blog-site\blog` (GitHub `DasunMuthuruwan/blog`, latest commit `ee78b23`), plus the project's own memory docs in `docs/memory/`, the hosting invoice in `D:\Domain\` and brand files in `D:\Site logo\`. |

Labels: **[VERIFIED]** = seen directly in files. **[INFERRED]** = strong evidence but not confirmed on the server. **[UNVERIFIED]** = must be checked with the host or owner.

> Note: `D:\My Project\blog-site\blog` is an older Laravel 12 skeleton (one "Initial commit"), not the live site.

## 1. Domain and Hosting

| Finding | Label | Impact on marketplace |
|---|---|---|
| The registered domain is **`dev-talk.com`** (register.lk invoice, Aug 2025, 1-year term to 12 Aug 2026). The Dev Talk `.env.example` uses `APP_URL=https://dev-talk.com`. | [VERIFIED] | The owner's proposed `marketplace.dev-talk.lk` does **not** match the domain on record. `marketplace.dev-talk.com` is the natural candidate. Must be confirmed (CD-02). |
| The hosting plan term shown ended 12 Aug 2026. The site is presumably renewed, but this was not checked. | [UNVERIFIED] | Confirm the hosting and domain renewal dates before planning deployment. |
| Host: register.lk "Unmetered Hosting SSDG1" shared cPanel. Home `/home/devtalkc`, web root `public_html`. | [INFERRED] from Dev Talk docs | Same provider is the default deployment target. |
| Web server is **LiteSpeed** with EasyApache PHP handler `ea-php83___lsphp` in `public/.htaccess`. | [INFERRED] | The host can run **PHP 8.3** (MultiPHP). This makes Laravel 13 feasible (ADR-001). |
| The host **does not allow every-minute cron jobs**. Dev Talk works around this with a `RunBackgroundTasks` middleware that runs the queue after responses. | [INFERRED] from Dev Talk docs | The marketplace must not depend on a 1-minute cron (see architecture §6). |
| Outgoing mail is limited to **90 emails/hour** (Dev Talk throttles to 80/hour). | [INFERRED] | If on the same account, the marketplace shares this limit. A dedicated email provider is needed for production (ADR-009). |
| `vendor/` is uploaded manually as a zip. Composer is not run on the server, and SSH is uncertain ("Without SSH: import SQL in phpMyAdmin"). | [INFERRED] | The deployment design must work without SSH or Composer (see hosting doc §5). |
| Symlinks may not be supported. Dev Talk has a fallback that copies files when `public/storage` is missing. | [INFERRED] | The uploads design must not depend on `storage:link` (see security doc §4). |
| Production DB schema has drifted from the migrations through hand edits. | [VERIFIED] (Dev Talk docs) | Lesson: the marketplace DB is changed **only** through migrations, never by hand. |
| Local DB: MariaDB 10.4 (XAMPP at `C:\php8.2`). Production DB version unknown. | [VERIFIED] local / [UNVERIFIED] production | Confirm the production MySQL/MariaDB version (hosting doc §2). |

## 2. Application Stack

| Item | Dev Talk | Label |
|---|---|---|
| Framework | Laravel 12, PHP `^8.2`, Composer `config.platform.php` pinned to 8.2 | [VERIFIED] |
| UI | Blade + **Livewire 3** + Alpine; **Tailwind CSS 4** + Vite 6; assets prebuilt and committed in `public/build` (no Node on server) | [VERIFIED] |
| Images | `intervention/image` v3 (GD) | [VERIFIED] |
| SEO | `artesaos/seotools`, `spatie/laravel-sitemap`, IndexNow | [VERIFIED] |
| Search | MySQL FULLTEXT with `LIKE` fallback | [VERIFIED] |
| Queue / cache / session | `database` drivers | [VERIFIED] |
| Tests | PHPUnit 11, SQLite in-memory, GitHub Actions `tests.yml` | [VERIFIED] |

**Conclusion:** the marketplace's proposed stack (Laravel + Blade + Livewire + Tailwind + Vite, prebuilt assets) is proven on this host. This supports Q13. Only technical and deployment lessons are carried over. The **visual theme is not reused** (owner instruction, 2026-10-11): the marketplace gets its own modern marketplace-portal design in Phase 3.

## 3. Authentication and Users

| Finding | Label |
|---|---|
| Custom session auth in `AuthController` and `CommunityAuthController`. No Fortify, Breeze or Jetstream. | [VERIFIED] |
| A single `users` table with `type` enum: `admin` (author), `superAdmin`, `member` (community, GitHub OAuth). `status`: pending, active, inactive, rejected. | [VERIFIED] |
| Session cookie name `dev_talk_session` (from `APP_NAME="Dev Talk"`). `SESSION_DOMAIN=null` means the cookie is **host-only** for `dev-talk.com` and is **not sent to subdomains**. | [VERIFIED] |
| HSTS is `max-age=15552000` **without** `includeSubDomains`. | [VERIFIED] |
| Laravel Sanctum is installed. | [VERIFIED] |

**Implications (Q2 — confirmed independent auth):**

- The marketplace uses its own `users` table in its own database, its own cookie name (`dtm_session`) and `SESSION_DOMAIN=null`. There is no cookie overlap with the blog.
- Do not set `SESSION_DOMAIN=.dev-talk.com` on either app. That would share or collide sessions across subdomains.
- **Future SSO (not MVP):** the best fit is an OAuth2/OIDC flow where Dev Talk acts as the identity provider (e.g. Laravel Passport on the blog), or "Sign in with Dev Talk" through signed redirects. Accounts would be linked by verified email, with no shared password table. This needs changes to the blog and its own approval.

## 4. Brand Assets

| Asset | Location | Label |
|---|---|---|
| Logo: rounded rectangle with white "DEV" wordmark and microphone. Two colourways: deep purple (`DEV-TALKS.png`) and blue (`new/DEV_TALKS.png`). | `D:\Site logo\` | [VERIFIED] |
| Favicon (`.ico`, `.png`) and source PSDs | `D:\Site logo\` | [VERIFIED] |

**Decision (owner, 2026-10-11):** the marketplace does **not** reuse Dev Talk's theme. Whether the Dev Talk logo or name appears on the marketplace (e.g. "Marketplace by Dev Talk" in the footer) is an open question for Phase 3 (Q-UX-1). Any marketplace logo is new work for owner approval.

## 5. Things That Must Not Be Touched

- Dev Talk code, database, `public_html`, `.htaccess`, cron entries, `.env` and uploads.
- Dev Talk's DNS zone. No records are created or changed without explicit approval.
- Dev Talk's mail quota: avoid sharing it in production (ADR-009).
