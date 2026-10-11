# Domain Migration, Hosting Portability and Scaling

| Field | Value |
|---|---|
| Status | **Approved 2026-10-11.** Rules P1–P11 are binding for implementation. |
| Requirement | The owner plans to move the marketplace to a different domain and hosting as it grows. A domain or hosting change must not require rewriting business logic. No complex infrastructure or paid services before they are needed. |
| Initial domain | `marketplace.dev-talk.com` [APPROVED CD-02, UNVERIFIED: DNS control, SSL, subdomain setup] |

## 1. Portability Rules (Binding for Implementation)

| # | Rule | How it is enforced |
|---|---|---|
| P1 | **No hardcoded domain** anywhere: controllers, Blade, emails, JavaScript, seeders, config defaults, tests (except a fixture constant). | All URLs come from `route()`, `url()`, `asset()`, `Storage::url()`, `config('app.url')`. An automated test scans `app/`, `resources/`, `routes/`, `config/`, `database/`, `lang/` for `dev-talk`, `marketplace.` and `http://`/`https://` literals (allow-list for third-party endpoints) and fails the build. |
| P2 | **The database never stores absolute URLs** of our own site. | Images store `disk` + relative `directory`/`basename`. Pages store Markdown with relative links. Settings store paths. |
| P3 | **One source of the canonical host:** `APP_URL`. | `URL::forceRootUrl(config('app.url'))` and `URL::forceScheme('https')` in production. Queued emails, CLI-generated sitemaps and scheduled jobs therefore produce correct links (they have no request host). |
| P4 | **Host header safety:** only the configured host is trusted. | `TrustHosts` middleware derived from `APP_URL` (plus an optional `APP_ALIASES` list during migration). This prevents host-header injection in password-reset links. |
| P5 | **Cookies scoped to the active host only.** | `SESSION_DOMAIN=null` (host-only), `SESSION_COOKIE=dtm_session` (env), `SESSION_SECURE_COOKIE=true`, `SameSite=Lax`. No cookie domain assumptions in code. Never shared with Dev Talk. |
| P6 | **Media location is configurable.** | Listing images use the `listings` disk. `LISTINGS_DISK_DRIVER` (local/s3), `LISTINGS_URL` (e.g. `https://media.newdomain.com`). Code only calls `Storage::disk('listings')->url($path)`. |
| P7 | **All infrastructure drivers are env-driven.** | `DB_*`, `MAIL_*`, `CACHE_STORE`, `QUEUE_CONNECTION`, `SESSION_DRIVER`, `FILESYSTEM_DISK`, `IMAGE_DRIVER`, `IMAGE_FORMAT`, `TURNSTILE_*`. Moving to Redis, SES or S3 is configuration only. |
| P8 | **No undocumented cPanel-only behaviour.** | The web runner is optional (`WEB_RUNNER_ENABLED`). On a VPS, a real cron and Supervisor take over without code changes. The `public/uploads` fallback disk is a config choice. |
| P9 | **Database portability.** | Migrations are the only schema source. No triggers, stored procedures, events or views. Enums as varchar. JSON columns via Laravel casts. FULLTEXT isolated in `MysqlListingSearch` (ADR-005). Reference seeders (roles, permissions, locations, categories) are idempotent (`updateOrCreate`). |
| P10 | **Domain-independent signed links where possible.** | Draft-preview and similar app-generated signed links use relative signatures (`signed:relative`). Email verification links (Fortify, absolute signature) expire in 60 minutes. After a cutover, users with an in-flight link use "Resend verification". Password reset uses DB tokens (domain-independent). |
| P11 | **Feature with domain binding avoided:** passkeys (ADR-015). | TOTP only for 2FA. |

**Implementation status (T0.4, 2026-10-11):** P1 guard test (`tests/Unit/Guards/NoHardcodedValuesTest.php`, scanner in `tests/Support/HardcodedValueScanner.php`), P3 (`App\Support\RootUrl`: forces `APP_URL` root **and scheme**, HTTPS in production), P4 (`TrustConfiguredHosts`) and P5 (session defaults) are implemented and tested.

## 2. Environment Variable Inventory

`.env.example` (committed, no secrets) documents every key:

| Group | Keys |
|---|---|
| App | `APP_NAME`, `APP_ENV`, `APP_KEY` (**must never change across migrations**), `APP_DEBUG`, `APP_URL`, `APP_ALIASES` (optional, migration only), `ASSET_URL` (optional CDN) |
| Logging | `LOG_CHANNEL=daily`, `LOG_LEVEL`, `LOG_DAILY_DAYS=14` |
| Database | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` |
| Session / cache / queue | `SESSION_DRIVER`, `SESSION_LIFETIME`, `SESSION_COOKIE`, `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE`, `CACHE_STORE`, `QUEUE_CONNECTION` |
| Storage / images | `FILESYSTEM_DISK`, `LISTINGS_DISK_DRIVER`, `LISTINGS_ROOT`, `LISTINGS_URL`, `IMAGE_DRIVER` (gd/imagick), `IMAGE_FORMAT` (webp/jpg), later `AWS_*` / R2 keys |
| Mail | `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `MAIL_HOURLY_LIMIT`, `MAIL_SENDING_ENABLED` (production safety switch, CD-09) |
| Security | `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY`, `TRUSTED_PROXIES` (needed if Cloudflare proxy is added later) |
| Background | `WEB_RUNNER_ENABLED`, `WEB_RUNNER_INTERVAL`, `WEB_RUNNER_MAX_SECONDS` |
| SEO | `SEO_INDEXING` (false on staging means `noindex` + robots disallow) |
| Marketplace | `MARKETPLACE_LISTING_EXPIRY_DAYS`, `MARKETPLACE_MAX_ACTIVE_LISTINGS`, `MARKETPLACE_RETENTION_*` (defaults in `config/marketplace.php`) |

## 3. SEO and Canonical URLs

- Canonical, `og:url`, sitemap `<loc>`, JSON-LD `url` and `image` are all generated from `APP_URL` / `LISTINGS_URL` (P3, P6). Changing the domain changes them all through config.
- Listing URLs `/ad/{slug}-{id}` are resolved **by id**. A wrong or old slug gets a 301 to the canonical slug. After a domain migration, the same path on the new domain resolves identically, so a path-preserving redirect is enough.
- Image URLs are long-cached. On a media move, old image paths 301 to the new media host (§5 step 9).

### 3.1 Permanent Redirect Strategy (Old → New Domain)

1. The **old host keeps serving** only a redirect: every request → `301` to `https://NEW_HOST{same path}{same query}`. Implemented as a minimal `.htaccess` (or a tiny Laravel "redirector" app) on the old domain's document root:
   ```apache
   RewriteEngine On
   RewriteRule ^ https://NEW_HOST%{REQUEST_URI} [R=301,L]
   ```
2. Keep the old domain registered and the redirect running for **at least 12 months** (ideally permanently, since it is cheap).
3. Redirect 1:1 (same path). Never send everything to the homepage.
4. Google Search Console: verify both properties, use **Change of Address**, and submit the new sitemap. Bing Webmaster Tools: site move.
5. Keep the HTTPS certificate valid on the old domain for the whole redirect period (otherwise browsers error before redirecting).

## 4. Uploads, Email and Background Jobs When Moving

| Area | Portability design |
|---|---|
| Uploads | Relative paths + disk config. Moving hosts = copy the `listings` directory tree (rsync, or zip in File Manager). Moving to object storage = `php artisan listings:migrate-media --to=s3` (planned command), then switch `LISTINGS_DISK_DRIVER`. |
| Email | Provider switched in `.env`. On a domain change: add SPF/DKIM/DMARC for the new sending domain at the provider **before** cutover, and update `MAIL_FROM_ADDRESS`. |
| Queue | `database` queue drains before cutover (maintenance mode + `queue:work --stop-when-empty`), so no jobs are lost. |
| Cache | Disposable. Cleared on the new host. |
| Sessions | Users log in again on the new domain (host-only cookies). Announce beforehand. |
| Turnstile | Add the new hostname to the Turnstile widget **before** cutover (keep the old one until the redirect is in place). |

## 5. Domain and Hosting Migration Runbook

**Roles:** Owner approves each gate. **Nothing in this runbook runs without explicit approval** (DNS, production and email are involved).

### Phase A — Prepare (1–2 weeks before)

1. Provision the new hosting. Run the compatibility check script (PHP version/extensions, DB version, writable dirs, cron, outbound HTTPS, symlink support).
2. Create the new DB and least-privilege user. Set up the new `.env` from the inventory (§2). **Copy `APP_KEY` exactly.** New `APP_URL`. `APP_ALIASES` = old host (temporary).
3. Deploy the current release to the new host. Restore a **recent DB backup** and an **uploads copy**.
4. Test on a temporary hostname (e.g. `new-staging.<new domain>` with `SEO_INDEXING=false`, or a hosts-file override):
   - [ ] Home, category, search, filters, listing pages, seller pages
   - [ ] Images load from the configured `LISTINGS_URL`
   - [ ] Register, verify email, login, password reset, staff 2FA login (proves `APP_KEY` is correct)
   - [ ] Post a listing with images. Approve as moderator.
   - [ ] Emails sent from the new domain pass SPF/DKIM (mail-tester score)
   - [ ] Canonical, OG, sitemap URLs show the new domain
   - [ ] Cron/scheduler runs. Queue drains.
5. Lower the DNS TTL of the new and old records to 300 s at least 48 h before cutover.
6. Issue SSL for the new domain (AutoSSL, or DNS-validated Let's Encrypt).
7. Add the new hostname to the Turnstile widget and the email provider's domain authentication.

### Phase B — Cutover (low-traffic window, ~1–2 hours)

8. Old site: `php artisan down` (maintenance page with notice). Drain the queue.
9. Final **DB dump** from the old host → import on the new host. **Delta-sync uploads.** Run `php artisan migrate --force` (no-op if same release), `optimize`, `sitemap:generate`.
10. Point DNS for the new domain at the new host (if not already). Bring the new site up.
11. Replace the old site's document root content with the **301 redirect** (§3.1). Keep the old app files and DB **untouched but offline** for rollback.
12. Smoke test from outside: old URL → 301 → new URL with the same listing id. Login. Post. Image upload. Email.

### Phase C — After Cutover

13. Search Console Change of Address. Submit sitemap. Watch crawl errors daily for 2 weeks.
14. Remove `APP_ALIASES` after 7 days. Keep the redirect and old SSL ≥ 12 months.
15. Update external links you control (social profiles, Dev Talk footer link) and documentation (`CLAUDE.md`, PROGRESS).
16. Raise DNS TTL back to normal.

### Rollback (Within the Rollback Window, Default 24 h)

- **Before writes accumulate:** restore the old site's document root (remove the redirect), `php artisan up` on the old host, revert DNS. The old DB is untouched.
- **After new data was written on the new host:** dump the new DB → import into the old host → copy new uploads back → then the steps above. This is why the window is short and announced.
- Decision owner: the Owner. A rollback is triggered by a failed smoke test (step 12) that cannot be fixed within 1 hour.

## 6. Staged Scaling Plan

Move to the next stage only when **measured** triggers are reached. Measurements: cPanel resource usage (CPU/RAM/entry processes/inodes), slow-query log or `DB::whenQueryingForLongerThan`, p95 response times from a simple request-timing log, and queue backlog.

| Area | Stage 1 — Shared cPanel (MVP) | Stage 2 — Optimised shared / larger plan | Stage 3 — VPS or cloud | Stage 4 — Growth |
|---|---|---|---|---|
| **Trigger (indicative)** | Launch | Inodes or disk > 70%, p95 > 800 ms, or entry-process limit warnings | CPU/RAM limits hit repeatedly, need real cron/workers, > ~50k active listings | Search relevance or latency issues, multi-server needs |
| **Database / indexing** | Composite indexes per query (database doc §4), `EXPLAIN` review on 50k seeded rows | Generated "hot" columns for top attribute filters, slow-query review | Tuned MySQL (`innodb_buffer_pool_size`), daily automated `ANALYZE` | Managed MySQL, read replica (Laravel read/write config) |
| **Caching** | File cache: category tree, settings, counts, home blocks | Cache search result pages for anonymous users (short TTL) | Redis for cache + sessions | Full-page cache at the edge for anonymous pages |
| **Images** | 2 WebP variants, lazy loading, explicit dimensions, retention purge | Move `listings` disk to S3-compatible storage (e.g. Cloudflare R2 — free tier exists, needs approval) | + CDN on a media subdomain (`LISTINGS_URL`) | Image CDN resizing |
| **Queue processing** | Database queue, slow cron + web runner | Shorter cron if the host allows | Redis queue + Supervisor workers, real every-minute cron. Web runner disabled. | Horizon, separate queues per job type |
| **Search** | MySQL FULLTEXT + indexed filters | FULLTEXT tuning, result caching | Meilisearch on the VPS (self-hosted, free) via `ListingSearch` | Managed search cluster |
| **Rate limiting** | Laravel limiters on file/DB cache | Tune limits from logs | Redis-backed limiters. Cloudflare proxy (free) with `TRUSTED_PROXIES` | WAF rules, bot management |
| **Hosting** | Shared cPanel, separate account preferred | Higher plan / separate account | VPS (e.g. Laravel Forge-managed or manual) | Load balancer + multiple app servers, shared storage on S3 |

All Stage 2–4 changes are **configuration or additive code**, following §1 rules P6–P9. No paid service is introduced without owner approval.

## 7. Backups and Restore (Portable)

| Item | Method |
|---|---|
| Database | Daily `mysqldump --single-transaction --no-tablespaces` (or cPanel backup) → off-site copy. Retention per [data retention](09-data-retention.md) §1. |
| Uploads | Daily incremental copy of the `listings` directory (or object-storage versioning later) |
| Config | `.env` stored securely offline (password manager). Never in git. |
| Restore drill | Before launch and then quarterly: restore DB + uploads to staging, run the smoke checklist (§5 step 4) |
