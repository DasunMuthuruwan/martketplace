# Running the Marketplace Locally on Windows

For the owner's Windows machine: PHP 8.4 (standalone, not on PATH), the project's MariaDB 10.11 on port 3307, and Node for the asset build. Dev Talk keeps PHP 8.2, XAMPP and port 8000; nothing here changes them.

## 1. Requirements (already installed on this machine)

| Tool | Location | Note |
|---|---|---|
| PHP 8.4 | `C:\php8.4\php.exe` | Not on PATH on purpose. Always call it by its full path. |
| MariaDB 10.11 | `C:\mariadb10.11` (127.0.0.1:3307) | Start: `C:\mariadb10.11\start-marketplace-db.bat`, stop: `stop-marketplace-db.bat`. Not XAMPP's MariaDB on 3306. |
| Node 22 + npm | on PATH | Builds the CSS/JS (`npm run build`). |
| Composer | `C:\ProgramData\ComposerSetup\bin\composer.phar` | The `composer` command runs on PHP 8.2 (first on PATH). For this project run it with PHP 8.4: `C:\php8.4\php.exe C:\ProgramData\ComposerSetup\bin\composer.phar install`. |

## 2. One-time Set-up (done on 2026-10-18)

From the project folder (`D:\My Project\martketplace`), in PowerShell or Command Prompt:

```bat
C:\mariadb10.11\start-marketplace-db.bat
C:\php8.4\php.exe artisan migrate
C:\php8.4\php.exe artisan db:seed
C:\php8.4\php.exe artisan storage:link
npm install
npm run build
```

`db:seed` adds reference data only (currencies, countries, units, roles, categories, fields, help pages). It is safe to run again and never creates accounts.

**Places** (regions and towns) come from GeoNames, not the seeders. The files go in `storage\app\geonames` (git-ignored): `admin1CodesASCII.txt`, `admin2Codes.txt` and the country file (for Sri Lanka `LK.zip`, unzipped to `LK.txt`) from https://download.geonames.org/export/dump/. Then:

```bat
C:\php8.4\php.exe artisan geo:import-regions LK
C:\php8.4\php.exe artisan geo:import-cities LK --file=LK.txt
```

This is the full, uncurated GeoNames list (17,743 places for Sri Lanka, many small villages). The curated town list is T1.4 (owner review C1).

**APP_URL:** set `APP_URL=http://localhost:8001` in `.env`. Port 8000 is Dev Talk's, and the marketplace builds links, styles and photo addresses from `APP_URL`, so it must match the address in the browser.

## 3. Every Day

```bat
scripts\windows\serve-local.bat
```

It starts MariaDB when needed, warns when `APP_URL` does not match, and serves the site on **http://localhost:8001** (Ctrl+C stops it). The same by hand:

```bat
C:\mariadb10.11\start-marketplace-db.bat
C:\php8.4\php.exe artisan serve --port=8001
```

Optional, in a second window:

| Command | What for |
|---|---|
| `C:\php8.4\php.exe artisan queue:work` | Queued emails and notifications (ad approved, expiring, account changes). Without it they wait in the `jobs` table. |
| `C:\php8.4\php.exe artisan schedule:work` | Background tasks (expiring ads, reminders, data retention). |
| `npm run dev` | Only when editing CSS/JS: live reload instead of rebuilding. Run `npm run build` again afterwards. |

## 4. Accounts and the Admin Area

No accounts are seeded. To get an Owner:

1. Register at http://localhost:8001/register.
2. Email is not really sent (`MAIL_MAILER=log`): open `storage\logs\laravel.log`, find the newest "Verify Email Address" message and open its link. Tip: `MAIL_LOG_CHANNEL=mail` in `.env` writes emails to `storage\logs\mail.log` instead, apart from errors.
3. Make the account the Owner: `C:\php8.4\php.exe artisan marketplace:make-owner you@example.com`
4. Open http://localhost:8001/admin. Owners and Admins set up two-factor authentication first (any authenticator app).

Then, in the admin area: publish the help pages (Admin → Pages; until then `/page/...` returns 404), review countries (Sri Lanka was activated in the local database on 2026-10-18) and add staff.

## 5. Tests and Checks

| Check | Command |
|---|---|
| PHP tests (SQLite, in memory) | `C:\php8.4\php.exe vendor\bin\phpunit` |
| Same on MariaDB | needs the `marketplace_test` database; see the commit gate in `docs/PROGRESS.md` |
| Code style / static analysis | `C:\php8.4\php.exe vendor\bin\pint`, `C:\php8.4\php.exe vendor\bin\phpstan analyse` |
| Browser + accessibility (ADR-025) | `npm run build`, then `set PHP_BINARY=C:\php8.4\php.exe` and `npm run test:browser` |
| Lighthouse | `npm run lighthouse` (same `PHP_BINARY`) |

The browser checks use their own database and port 8130, so they can run while the site is open on 8001.

## 6. Troubleshooting

| Symptom | Fix |
|---|---|
| "No connection could be made" / SQLSTATE 2002 | MariaDB is not running: `C:\mariadb10.11\start-marketplace-db.bat`. |
| Page without styles, or links to port 8000 | `APP_URL` does not match the port; set it in `.env`. Run `npm run build` if `public\build` is missing. |
| Photos do not show | `C:\php8.4\php.exe artisan storage:link` (creates `public\storage`). |
| A page shows old data after changing settings or categories | The catalogue cache refreshes on admin changes; otherwise `C:\php8.4\php.exe artisan cache:clear`. |
| "Port already in use" | Another server is on 8001: close it, or use another port and match `APP_URL`. |
