# Hosting Requirements and cPanel Deployment Design

| Field | Value |
|---|---|
| Status | **Revised 2026-10-11: CD-01/02/03/09/10 approved with verification conditions.** Nothing here has been configured. **No DNS, hosting or production changes are made without explicit approval.** |
| Target | cPanel shared hosting. The register.lk account hosting Dev Talk is the default candidate [INFERRED]. |

## 1. What Is Known vs Unknown

| Item | Status | Evidence / action |
|---|---|---|
| Web server LiteSpeed, PHP handler `ea-php83` available | [INFERRED] | Dev Talk `public/.htaccess`. **Verify** in cPanel → MultiPHP Manager that PHP 8.3/8.4 can be set per domain. |
| Every-minute cron not allowed | [INFERRED] | Dev Talk deployment notes. **Verify** the minimum interval. |
| Mail limit 90/hour | [INFERRED] | Dev Talk notes. **Verify** with the host. |
| SSH access | [UNVERIFIED] | Check cPanel → Terminal / SSH Access. |
| Composer on server | [UNVERIFIED] — assume **no** | Dev Talk uploads `vendor.zip` |
| Node on server | Assume **no** | Assets prebuilt |
| Imagick extension | [UNVERIFIED] | cPanel → Select PHP Version → Extensions |
| GD with WebP + JPEG + FreeType | [UNVERIFIED] | `gd_info()` check (Phase 9 compatibility script) |
| Symlinks allowed (`storage:link`) | [UNVERIFIED], possibly no | Dev Talk has a copy fallback |
| MySQL/MariaDB version | [UNVERIFIED] | cPanel → right sidebar "Server Information" |
| Disk quota, **inode limit**, RAM, CPU, entry processes (CloudLinux LVE) | [UNVERIFIED] | cPanel → Statistics sidebar |
| AutoSSL / Let's Encrypt for subdomains | [UNVERIFIED], usually yes | cPanel → SSL/TLS Status |
| Outbound HTTPS (Turnstile, HIBP) | [UNVERIFIED], probably yes (Dev Talk calls YouTube oEmbed, IndexNow) | Compatibility script |

A **compatibility check script** (a standalone PHP file uploaded temporarily to a test subdomain, then deleted) will report all of the above in Phase 9. It needs approval before upload.

## 2. Minimum and Recommended Hosting Requirements

| Requirement | Minimum | Recommended |
|---|---|---|
| PHP | **8.3** (Laravel 13 minimum; also required by spatie/permission 8, spatie/translatable 6.14, intervention/image 4) | **8.4** (security support to Dec 2028) |
| PHP extensions | ctype, curl, dom, fileinfo, filter, hash, mbstring, openssl, pcre, pdo_mysql, session, tokenizer, xml, bcmath, **intl**, **gd** (JPEG, PNG, WebP, FreeType) | + imagick, exif, opcache, zip |
| PHP settings | `memory_limit` 256M, `upload_max_filesize` 10M, `post_max_size` 12M, `max_execution_time` 60 | `memory_limit` 512M |
| Database | MySQL 8.0 or MariaDB 10.6 (project minimum; Laravel 13 officially supports MySQL 5.7+ / MariaDB 10.3+) | MySQL 8.0+ / MariaDB 10.11 LTS |
| Disk | 5 GB free for the app + images in year one (see §3) | 20 GB |
| Inodes | ≥ 150k available to the marketplace (see §3) | Separate account, or object storage |
| Cron | One job, at least every 15 minutes | Every 1–5 minutes |
| SSL | AutoSSL on the subdomain | |
| Email | SMTP (host) for development | Transactional provider for production |
| Access | cPanel File Manager + phpMyAdmin | SSH (+ Git) |

## 3. Capacity Estimate [ASSUMPTION — sizing only, not a confirmed target]

Per listing: about 6 images on average × 2 variants ≈ 12 files (≈ 13 inodes incl. directory) and about 1.3 MB (sm ≈ 30 KB, lg ≈ 180 KB).

| Active + retained listings | Images disk | Inodes (images) |
|---|---|---|
| 2,000 | ≈ 2.6 GB | ≈ 26k |
| 5,000 | ≈ 6.5 GB | ≈ 65k |
| 15,000 | ≈ 20 GB | ≈ 195k |

App code + `vendor` uses ≈ 15–25k inodes. **Shared plans commonly cap inodes at 100k–300k for the whole account, and Dev Talk already uses part of that.** This is the main reason for the retention purge of expired listings' images, the 2-variant design, and the object-storage path (architecture §7.1).

**Global marketplace (2026-10-11):** location tables add about 1 MB for countries/regions plus about 10 MB of cities for four active countries (LK curated, GB, IN, US). Importing every country's `cities1000` would be about 40–50 MB, so only active countries are imported ([global design](13-global-marketplace.md) §4.6). Images and inodes remain the main constraint. Country count doesn't change the per-listing estimate.

## 4. Account Options (Q3)

| | **Option A: Same cPanel account as Dev Talk** | **Option B: Separate cPanel account** |
|---|---|---|
| Setup | Add subdomain in Dev Talk's cPanel | New hosting account (same or another provider). DNS A/CNAME record for the subdomain in the Dev Talk zone (needs approval). |
| Isolation | Shared OS user, shared PHP limits, inode and disk quota, mail limit. A compromise of one app can reach the other's files. | Full separation of files, limits, credentials, mail quota and backups |
| Cost | No extra hosting fee | Additional hosting fee |
| Risk to Dev Talk | Medium: resource contention, quota exhaustion, shared mail limit | Low |
| Backups / restore | Mixed with Dev Talk's | Independent |
| Decision | Fallback only. Allowed **only if** it is verified that the app files can sit outside the web-accessible document root (document root outside `public_html`), DB users are separate, and inode/disk/mail limits are documented and accepted | **[APPROVED CD-03] Preferred** |

**CD-03 approved:** separate account preferred. Before any hosting purchase or setup, the limits (PHP versions, extensions, inodes, disk, cron interval, mail limit, SSH) of the chosen account must be documented in this file and approved.

### 4.1 Directory Layout (Option A)

```
/home/devtalkc/
├── public_html/                 ← Dev Talk (UNCHANGED)
├── marketplace/                 ← Laravel app root (NOT web-accessible)
│   ├── app/ bootstrap/ config/ database/ lang/ resources/ routes/ storage/ vendor/
│   ├── .env                     ← marketplace secrets (chmod 600)
│   └── public/                  ← subdomain DOCUMENT ROOT
│       ├── index.php  .htaccess  build/  uploads/ (fallback disk)
└── marketplace_releases/        ← optional: previous release zips for rollback
```

- In cPanel → Domains, create the subdomain with document root `/home/devtalkc/marketplace/public`. cPanel allows any path inside the home directory [verify on this host].
- **Do not** use `public_html/marketplace/` as the document root. The marketplace would then also be reachable at `dev-talk.com/marketplace/`, and the blog's `.htaccess` rewrite rules would apply to it. Fixing that would need changes to Dev Talk.

Option B uses the same layout under the new account's home directory.

## 5. Deployment Process (Detail Finalised in Phase 9)

### 5.1 Build (Local or GitHub Actions)

1. Run tests, Pint and Larastan, all green.
2. `composer install --no-dev --optimize-autoloader` (with `config.platform.php` pinned to the production PHP version).
3. `npm ci && npm run build` → `public/build`.
4. Package `marketplace-release-YYYYMMDD-<sha>.zip` (code + `vendor` + `public/build`, excluding `.env`, `storage/*` content, tests and `node_modules`).

### 5.2 Deploy — With SSH [preferred]

`php artisan down --secret=…` → extract into a new release folder → swap (or overwrite) → `php artisan migrate --force` → `php artisan optimize` → `php artisan up`. Back up the DB first.

### 5.3 Deploy — Without SSH or Composer (Alternatives)

| Need | Alternative |
|---|---|
| Composer | `vendor/` is included in the release zip (built locally against the production PHP version) |
| Upload | cPanel File Manager: upload the zip and extract |
| `artisan migrate` / `optimize` | **One-off cron job** in cPanel running `cd ~/marketplace && php artisan migrate --force && php artisan optimize`, removed after it runs once. Output is mailed or logged. This avoids hand-written SQL, which caused schema drift on Dev Talk. Fallback: a SQL file generated by `php artisan schema:dump` / `migrate --pretend`, imported in phpMyAdmin. |
| `storage:link` (no symlink support) | `listings` disk rooted in `public/uploads` (security doc §4) |
| Imagick missing | GD driver (default) |
| GD without WebP | JPEG variants (`IMAGE_FORMAT=jpg`). Slightly larger files. |
| Maintenance mode | `php artisan down` via one-off cron, or a pre-built `storage/framework/down` file |

### 5.4 Rollback

The previous release zip and a DB backup are taken before each deploy. Migrations are additive and backward-compatible, so code can roll back without a DB restore in most cases. A DB restore is used only when a migration fails midway.

### 5.5 Scheduler and Queue on This Host

- One cron entry at the shortest allowed interval: `cd ~/marketplace && php artisan schedule:run >> /dev/null 2>&1`
- The web runner middleware covers busy periods (architecture §6).

### 5.6 DNS and SSL (Approval Required, Phase 9)

- Option A: cPanel creates the subdomain DNS record automatically when the zone is hosted on the same server. **This counts as a DNS change: approval needed.**
- Option B: add an `A` record for `marketplace` → the new server IP in Dev Talk's DNS zone (register.lk DNS management).
- AutoSSL issues the certificate. HTTPS is enforced in `.htaccess` and with `URL::forceHttps()`.
- Email deliverability: SPF/DKIM/DMARC for the sending domain/provider.

## 6. Transactional Email (Q14)

| Option | Fit | Notes |
|---|---|---|
| Host SMTP | Development / staging | 90/hour limit [INFERRED], shared with Dev Talk on Option A, deliverability uncertain |
| **Brevo** | Production candidate | Free tier ≈ 300 emails/day, SMTP relay (no SDK needed), EU-based |
| Mailgun | Production candidate | Trial then paid. Good deliverability tooling. |
| Amazon SES | Production at scale | Cheapest per email, but needs an AWS account and sandbox exit |

[RECOMMENDED] Host SMTP for development. Evaluate Brevo for production (no code difference: SMTP env values only). The final provider and budget need owner approval (CD-09). `MailBudget` limits hourly sends whichever provider is used.

**CD-09 approved conditions:** Brevo is evaluated for verification emails, password resets and moderation notifications. The provider stays configurable (`MAIL_*`), and credentials live only in `.env`. Production sending stays **off** (`MAIL_SENDING_ENABLED=false`, which routes mail to the `log` mailer) until SMTP configuration, SPF/DKIM/DMARC and test deliveries to Gmail/Outlook are verified and the owner approves.

## 7. Local Development Environment

- **Installed 2026-10-11 (owner approved): PHP 8.4.26 NTS x64 (VS17) at `C:\php8.4`**, a standalone folder alongside `C:\php7.4`, `C:\php8.1` and `C:\php8.2`. The official zip from downloads.php.net was SHA-256 verified.
  - **Not on PATH.** System and user PATH still point to `C:\php8.2\php`, so `php` remains 8.2.12 for Dev Talk and other projects. Nothing in the other PHP folders was changed.
  - `C:\php8.4\php.ini` (from `php.ini-development`) enables: curl, fileinfo, gd, intl, mbstring, exif, openssl, pdo_mysql, pdo_sqlite, sodium, sqlite3, zip, opcache. Built in: bcmath, ctype, dom, filter, tokenizer, xml. Settings: `memory_limit=512M`, `upload_max_filesize=10M`, `post_max_size=12M`, `max_execution_time=60`, `date.timezone=UTC`.
  - Own CA bundle: `C:\php8.4\extras\ssl\cacert.pem` (curl.se, SHA-256 verified). Independent of `C:\php8.2`.
  - Verified: `php -m` lists all of the above. GD has WebP, JPEG and FreeType. ICU 77.1. HTTPS to Packagist works. `composer diagnose` passes platform and connectivity checks under PHP 8.4.
- **How to use it for the marketplace only:**
  - Direct: `C:\php8.4\php.exe artisan …` and `C:\php8.4\php.exe C:\ProgramData\ComposerSetup\bin\composer.phar …`
  - Per terminal session (Git Bash): `export PATH="/c/php8.4:$PATH"`. This affects only that terminal.
  - Per terminal session (PowerShell): `$env:Path = "C:\php8.4;$env:Path"`
  - Once the app is scaffolded, `composer.json` gets `config.platform.php` pinned to the production PHP version.
- Not installed: Imagick (GD is the default driver), Xdebug/PCOV (coverage; can be added to `C:\php8.4\ext` later if needed).
- An older Herd Lite PHP 8.4.0 exists at `C:\Users\ASUS\.config\herd-lite\bin`. It is unused and was not modified.
- Local DB: a separate `marketplace` database (and user) on the existing MariaDB/MySQL server. **Never** reuse Dev Talk's database. Local MariaDB 10.4 is below the recommended 10.6 minimum; tests use SQLite and a CI MySQL 8 job covers FULLTEXT.
- Node 22 is available locally for Vite builds.
- **Known tooling issue:** the global Composer is 2.8.2, which has published security advisories (2.10.3 is current). Updating it (`composer self-update`) affects all projects, so it needs owner approval. `composer diagnose` also reports an invalid stored GitHub OAuth token. Plan, rollback and token instructions: [developer tooling](../operations/developer-tooling.md).

## 8. Portability and Future Migration

The deployment must use only documented, portable steps. Environment variables, the migration runbook, redirect strategy, backups and the staged scaling plan are in [domain migration and portability](11-domain-migration-and-portability.md).
