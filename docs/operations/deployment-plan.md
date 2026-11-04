# Phase 9 — cPanel Deployment Plan

| Field | Value |
|---|---|
| Status | **Draft for owner review (2026-11-04).** Nothing has been uploaded, configured or changed on any server, DNS zone or mailbox. |
| Builds on | [Hosting design](../architecture/08-hosting-and-deployment.md) (CD-01/02/03/09/10), [portability rules P1–P11 and migration runbook](../architecture/11-domain-migration-and-portability.md), [security report](../security/security-report.md) |
| Approval gates | **G1** hosting account and access · **G2** upload of the check script · **G3** staging subdomain (DNS record) · **G4** staging deploy · **G5** production DNS and SSL · **G6** go-live · **G7** mail sending on. Each gate needs an explicit "approved" from the owner. |

## 1. What the Codebase Already Provides (Verified 2026-11-04)

| Need | In the code | Where |
|---|---|---|
| Production config check (no secrets printed) | `php artisan marketplace:check-production`: fails on debug, wrong env, missing key, non-HTTPS `APP_URL`, unsafe cookies, log mailer, report-only CSP | `app/Console/Commands/Ops/CheckProduction.php` |
| One canonical host, HTTPS | `RootUrl` forces `APP_URL` root and scheme (HTTPS in production); exact trusted hosts (forged Host → 400); HSTS header | `app/Support/RootUrl.php`, `app/Http/Middleware/SecurityHeaders.php` |
| No symlinks needed | `LISTINGS_DISK_DRIVER=uploads` stores images in `public/uploads/listings`, guarded by an `.htaccess` that denies scripts | `config/filesystems.php`, `app/Support/ListingsDisk.php` |
| Vendor built for the server's PHP | `composer.json` `config.platform.php = 8.3.0` (runs on PHP 8.3 and 8.4) | `composer.json` |
| Scheduler on a slow cron | One `schedule:run` entry: expiry, boosts, pruning, sitemap, queue drain (`--stop-when-empty --max-time=50`); optional web runner | `routes/console.php`, `WEB_RUNNER_*` |
| Staging not indexed | `SEO_INDEXING=false` → `noindex` and robots disallow | config |
| Mail safe by default | `MAIL_SENDING_ENABLED=false` routes mail to the log until G7 | config |
| Tests in CI | GitHub Actions workflow | `.github/workflows/ci.yml` |

**Gaps this phase fills** (tasks in §8): a hosting compatibility script, a repeatable release build, an HTTPS redirect in `public/.htaccess` (today only the app and HSTS enforce HTTPS), a way to open the first country on an empty database, and the written runbook and smoke checklist.

**Found while planning:** a fresh install cannot get started. All countries are seeded inactive, registration is closed until one is active ("Registration is not open yet"), and activating a country needs a staff account in Admin → Countries, which needs registration. Locally this was solved by hand. Task D9.3b adds `php artisan marketplace:activate-country <ISO2>` (audited, refuses unknown codes, no other effect).

## 2. Hosting Compatibility (Gate G1 → G2)

Nothing about the host is verified yet; everything in [hosting design §1](../architecture/08-hosting-and-deployment.md) is inferred from Dev Talk's notes.

1. **Owner chooses the account** (CD-03 prefers a **separate cPanel account**; same-account only if the document root can sit outside `public_html`).
2. **Owner gives access** for the deploy: cPanel login used by the owner, or SSH credentials entered by the owner. Credentials are never pasted into the chat, the repo or logs.
3. **Check script** `deploy/hosting-check.php` (new, task D9.1): a single standalone PHP file, no framework. Uploaded to the staging document root only after **G2**, opened once with a random token in the query, then deleted. It reports, without printing any secret or path outside the account:
   - PHP version and SAPI; extensions from hosting design §2 (incl. intl, gd with JPEG/PNG/WebP/FreeType, pdo_mysql, bcmath, fileinfo, opcache)
   - `memory_limit`, `upload_max_filesize`, `post_max_size`, `max_execution_time`
   - MySQL/MariaDB version (connects with credentials the owner types into a form, not stored)
   - writable `storage/` and `bootstrap/cache/`, `symlink()` support, outbound HTTPS (Turnstile, HIBP)
   - `disable_functions` (exec, proc_open: needed by nothing at runtime; reported only)
4. From cPanel the owner reads off: disk and **inode** quota, entry processes/RAM (CloudLinux), minimum cron interval, mail hourly limit, AutoSSL availability. Results go into hosting design §1 and need approval before staging.

**Minimum to proceed:** PHP 8.3+, the required extensions, MariaDB 10.6+ / MySQL 8.0+, cron ≥ every 15 min, AutoSSL, inode headroom ≥ 150k.

## 3. Domain, DNS and Document Root (Gates G3, G5)

| Item | Plan |
|---|---|
| Production host | CD-02 approved `marketplace.dev-talk.com` (CLAUDE.md lists `marketplace.devtalk.lk` as a placeholder). **Owner to confirm the exact host before G5.** Nothing in code depends on it (P1). |
| Staging host | `staging-marketplace.<same domain>` (or a temporary cPanel subdomain), `SEO_INDEXING=false`, own database. Deleted or password-protected after launch. |
| DNS | Separate account: an `A` record for the subdomain → the new server IP, added by the owner in the domain's DNS zone. Same account: cPanel creates it when the subdomain is added. **Either way it is a DNS change → approval.** Lower TTL to 300 s 48 h before go-live. |
| Document root | `/home/<account>/marketplace/public`. **Never** `public_html/marketplace` (it would be reachable under Dev Talk and inherit its rewrite rules). |
| Layout | `~/marketplace/` (app, not web-accessible), `~/marketplace/.env` (chmod 600), `~/marketplace_releases/` (previous zips), `~/marketplace_backups/` (DB dumps). |

## 4. PHP and Dependencies

- cPanel → MultiPHP Manager: **PHP 8.4** for the subdomain (8.3 minimum). Select PHP Version → enable the extensions from §2; set `memory_limit 256M+`, `upload_max_filesize 10M`, `post_max_size 12M`, `max_execution_time 60`, OPcache on.
- **No Composer or Node on the server.** The release zip contains `vendor/` (built with `--no-dev --optimize-autoloader` against the pinned platform) and `public/build/`.
- Image driver GD (default); `IMAGE_FORMAT=jpg` only if GD lacks WebP.

## 5. SSL

- AutoSSL issues the certificate for staging and production subdomains (verify in SSL/TLS Status; the DNS record must resolve first).
- HTTPS enforced three ways: `.htaccess` 301 (task D9.3), `RootUrl` HTTPS in production, HSTS `max-age=31536000` (already sent). HSTS is added only after the certificate is confirmed valid, since browsers then refuse HTTP for a year — this is why the HTTPS check comes before go-live.

## 6. Environment Settings (`~/marketplace/.env`, Never in Git)

Created by the owner on the server from `.env.example`. Production values that differ from local:

| Key | Production value |
|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_KEY` | generated once (`php artisan key:generate --show` locally, pasted by the owner). **Never changes afterwards** (2FA secrets and encrypted fields depend on it). Kept in the owner's password manager. |
| `APP_URL` | `https://<confirmed host>` |
| `DB_*` | the new database and a least-privilege user (all privileges on that database only) |
| `SESSION_DRIVER` / `CACHE_STORE` / `QUEUE_CONNECTION` | `database` / `file` / `database` |
| `SESSION_SECURE_COOKIE` / `SESSION_DOMAIN` | `true` / empty (host-only, P5) |
| `LISTINGS_DISK_DRIVER` | `uploads` if symlinks are not allowed (else `local` + `storage:link`) |
| `LOG_CHANNEL` / `LOG_LEVEL` | `daily` / `warning` |
| `MAIL_*` / `MAIL_SENDING_ENABLED` | SMTP provider (Brevo candidate, CD-09) / `false` until G7 |
| `MAIL_LOG_CHANNEL` | `mail` while sending is off, so emails (with their sign-in links) go to `storage/logs/mail.log` and not to the warning-level app log. That file holds live verification and reset links: outside the document root, deleted once mail sending is on. |
| `TURNSTILE_SITE_KEY` / `_SECRET_KEY` | widget with the staging and production hostnames |
| `SEO_INDEXING` | `false` on staging, `true` at go-live |
| `WEB_RUNNER_ENABLED` | `true` only if the cron interval is longer than 5 minutes |

After every edit: `php artisan marketplace:check-production` must show no FAIL.

## 7. Release, Migration and Rollback

### 7.1 Build (local, task D9.2)

`scripts/release/build-release` runs the gate (Pint, PHPStan, test suite), `composer install --no-dev --optimize-autoloader` into a clean export of the tagged commit, `npm ci && npm run build`, and writes `marketplace-release-YYYYMMDD-<sha>.zip` plus its SHA-256. Excluded: `.env*` (except `.env.example`), `storage/*` contents, `tests/`, `node_modules/`, `.git/`, docs. The local working copy and its `vendor/` are not touched.

### 7.2 First install (staging, then production)

1. Create the database and user (cPanel → MySQL Databases).
2. Upload and extract the zip into `~/marketplace/`; create `.env` (§6); `chmod 600 .env`.
3. With SSH: `php artisan migrate --force`, `php artisan db:seed --force` (reference data only: currencies, countries, units, roles and permissions, categories, attributes, empty pages; no demo data), upload the GeoNames files for the launch country to `storage/app/geonames` and run `geo:import-regions <ISO2>` then `geo:import-cities <ISO2>` ([local development](local-development.md)), `php artisan optimize`. Without SSH: the same commands as a **one-off cron job**, removed after it runs (hosting design §5.3).
4. `php artisan marketplace:activate-country <ISO2>` for the launch country (D9.3b), which opens registration.
   - Owner account: the owner registers on the site, opens the verification link (written to `storage/logs/mail.log` while mail is off; needs `MAIL_LOG_CHANNEL=mail`), then `php artisan marketplace:make-owner <email> --force` and turns on 2FA.
   - The owner then sets pages, prices and the Urgent price in Admin; selling stays off.
5. Cron: `cd ~/marketplace && php artisan schedule:run >> /dev/null 2>&1` at the shortest allowed interval.
6. Smoke checklist (§7.5).

### 7.3 Updates

`php artisan down --secret=<random>` → **DB dump** to `~/marketplace_backups/` and keep the previous release zip → extract the new release over the app (`.env`, `storage/`, `public/uploads/` untouched) → `marketplace:check-production` → `migrate --force` → `optimize` → `up` → smoke checklist.

### 7.4 Rollback

- **Code only** (most cases; migrations are additive): `down`, extract the previous release zip, `optimize:clear` then `optimize`, `up`.
- **Failed migration:** `down`, restore the pre-deploy dump, previous release, `up`.
- Trigger: a failed smoke test not fixed within 1 hour. Decision: owner. Rollback is rehearsed once on staging before go-live (task D9.5).

### 7.5 Smoke Checklist (Staging and Production)

- [ ] `check-production` no FAIL; HTTPS valid; `http://` → 301 → `https://`; forged Host header → 400
- [ ] Home, country page, category, search with filters, an ad page, seller page; images load
- [ ] Register, verify email (staging: log mailer), log in, password reset; staff log in with 2FA
- [ ] Post an ad with photos; approve as moderator; it appears; reveal the number
- [ ] Upload of a `.php` file renamed `.jpg` is refused; `/uploads/listings/x.php` is not executed
- [ ] Cron ran (`storage/logs`, expired ads, sitemap regenerated); queue drains
- [ ] Error page for 404 and 500 shows no stack trace
- [ ] Lighthouse and performance report re-run on the host (PROGRESS "Next step")

### 7.6 Backups

Daily database dump and incremental `public/uploads` copy off the server (cPanel backup or cron `mysqldump --single-transaction --no-tablespaces`); restore drill on staging before launch (portability doc §7).

## 8. Tasks (One Branch Each, After Owner Approval of This Plan)

| Task | Work | Needs |
|---|---|---|
| D9.1 | `deploy/hosting-check.php` + test that it prints no secrets | — (upload needs G2) |
| D9.2 | Release build script, excludes, checksum; documented | — |
| D9.3 | HTTPS redirect in `public/.htaccess` (skipped for local `http://` hosts), test | — |
| D9.3b | `marketplace:activate-country` command for the first country on an empty database, tests | — |
| D9.4 | Runbook `docs/operations/deployment-runbook.md` (§7 commands, one-off cron variants, smoke checklist) | — |
| D9.5 | Staging: run check script, record results, deploy, smoke test, rollback rehearsal | G1–G4, owner at the controls |
| D9.6 | Production: DNS/SSL, deploy, launch country active, smoke test, backups on | G5, G6 |
| D9.7 | Mail provider: SPF/DKIM/DMARC, test deliveries, `MAIL_SENDING_ENABLED=true` | G7 |

D9.1–D9.4 (incl. D9.3b) are local work and need no hosting access. D9.5 onward happen only with the owner present and approving each gate.

## 9. Owner Decisions and Inputs Needed

1. Hosting: separate cPanel account (recommended, CD-03) or Dev Talk's account; provider and plan.
2. The exact production hostname (`marketplace.dev-talk.com` per CD-02, or another).
3. Access method: SSH available? Who runs the commands (owner, with commands supplied).
4. Mail provider for production (Brevo evaluation, CD-09) — can follow after launch with mail in the log.
5. Turnstile widget (Cloudflare account) with both hostnames.
6. Launch content: C1–C6 in the task checklist (town list, legal pages, logo, moderation templates).
7. Delete or edit local test ads #5 and #6 (local only; not deployed — production starts with an empty database).
