# Package Compatibility Assessment (CD-05)

| Field | Value |
|---|---|
| Date | 2026-10-11 |
| Status | **Final package list approved 2026-10-11.** Nothing has been installed in the project yet (packages are added at scaffolding, Phase 5). |
| Sources | Packagist metadata (`repo.packagist.org/p2/*.json`, fetched 2026-10-11), laravel.com/docs/13.x (releases, database, fortify), php.net/supported-versions |
| Rule (owner) | Add only packages with a clear benefit. Verify Laravel 13 compatibility, maintenance and licence. Document alternatives. |

## 1. Platform Baseline [VERIFIED from official sources]

| Item | Fact | Source |
|---|---|---|
| Laravel 13 | Released 17 Mar 2026. **Requires PHP ≥ 8.3** (supports 8.3–8.5). Bug fixes until Q3 2027, **security fixes until 17 Mar 2028**. Latest tag v13.35.0 (6 Oct 2026). | laravel.com/docs/13.x/releases, Packagist |
| Laravel 12 (fallback) | PHP 8.2–8.5. Bug fixes ended 13 Aug 2026. Security fixes until 24 Feb 2027. | same |
| PHP 8.3 | Security support until **31 Dec 2027**, which ends ~2.5 months **before** Laravel 13's security window | php.net |
| PHP 8.4 | Active support until 31 Dec 2026. Security until **31 Dec 2028** (covers Laravel 13's whole life). | php.net |
| PHP 8.2 | Security support ends **31 Dec 2026** | php.net |
| Laravel 13 databases | MariaDB 10.3+, MySQL 5.7+ (official minimum). Project recommendation stays MySQL 8.0+ / MariaDB 10.6+. | laravel.com/docs/13.x/database |
| Laravel 13 PHP extensions | ctype, filter, hash, mbstring, openssl, session, tokenizer (from `composer.json`), plus pdo_mysql, fileinfo, xml/dom, curl in practice | Packagist |

**Conclusion for CD-01:** Laravel 13 is confirmed. Target **PHP 8.4** if the host offers it (support covers Laravel 13's full life), and accept **8.3** as the minimum (would need a PHP minor upgrade before Jan 2028). Host support is [UNVERIFIED]: evidence of `ea-php83` only.

## 2. Assessment

| Package | Version to use | PHP / Laravel requirement | Licence | Maintenance | Benefit | Verdict |
|---|---|---|---|---|---|---|
| **livewire/livewire** | **^4.4** (v4.4.7, 28 Sep 2026) | PHP ^8.1. Laravel 10–13. | MIT | Very active (v4 released Jan 2026; v3 still patched) | Core UI framework (approved ADR-002) | **Use** |
| **laravel/fortify** | **^1.41** (28 Sep 2026) | PHP ^8.2. Laravel 11–13. Pulls `pragmarx/google2fa`, `bacon/bacon-qr-code`, `laravel/passkeys` ^0.2. | MIT | First-party, active | Hardened registration, verification, reset, password confirmation, TOTP 2FA + recovery codes | **Use.** Passkeys feature **disabled** (domain-bound, see ADR-015). |
| **spatie/laravel-permission** | **^8.3** (3 Jul 2026) | PHP ^8.3. Laravel 12–13. | MIT | Very active, widely used | Roles + permissions with caching, `can()` integration, seeding. Hand-rolling equivalent tables/caching is error-prone. | **Use** |
| **spatie/laravel-translatable** | **^6.14** (23 Apr 2026) | PHP ^8.3. Laravel 11–13. | MIT | Active | JSON translatable labels for categories, attributes, options, locations, pages (Q5) | **Use** — small, no runtime deps beyond Laravel. *Alternative:* plain `json` casts + a 20-line accessor (viable if the owner prefers fewer packages). |
| **intervention/image** | **^4.3** (v4.3.5, 10 Oct 2026) | PHP ^8.3, ext-mbstring. Suggests ext-exif. Drivers: GD or Imagick. | MIT | Active (v4 since Mar 2026; v3.11 still patched) | Safe decode, orientation, resize and WebP/JPEG encoding over GD/Imagick | **Use** v4 directly. The Laravel bridge `intervention/image-laravel` is **not needed** (one service class). |
| **spatie/laravel-sitemap** | — | **v8 requires PHP ^8.4.** v7.4 supports PHP 8.2+ / Laravel 13. Both pull **guzzle, spatie/crawler, symfony/dom-crawler**. | MIT | Active | Sitemap XML generation. Its crawler features are not needed. | **Do not install.** A ~100-line `SitemapBuilder` writing static XML (sitemap index + chunks) gives the same result with no dependencies. *Alternative:* v7.4 if the owner prefers the package. |

### 2.1 No Package Needed

| Need | Approach |
|---|---|
| Cloudflare Turnstile | One `Turnstile` service using Laravel's HTTP client (`POST /siteverify`) + a validation rule |
| SEO meta / JSON-LD | Own `PageSeo` + `StructuredData` services (Blade partial) |
| Markdown for static pages | `league/commonmark` (already a Laravel dependency) |
| Phone validation | ~~Own `SriLankanPhone` rule~~ **Superseded (global):** `propaganistas/laravel-phone` + libphonenumber (§5). |
| Search | Own `MysqlListingSearch` (ADR-005). Laravel Scout is not needed for the MVP. |

### 2.2 Development-Only (Not Deployed)

| Package | Purpose | Licence |
|---|---|---|
| laravel/pint | Code style | MIT |
| larastan/larastan | Static analysis | MIT |
| phpunit/phpunit or pestphp/pest | Tests (choice made in Phase 4) | BSD-3 / MIT |
| barryvdh/laravel-debugbar (optional) | Local query inspection | MIT |

### 2.3 Front-End (npm, Build-Time Only)

`tailwindcss` 4 + `@tailwindcss/vite`, `vite`, `laravel-vite-plugin`, self-hosted variable font (choice in Phase 3). Alpine comes with Livewire. All MIT/OFL. No runtime npm dependencies are deployed (prebuilt assets).

## 3. Final Proposed Production Package Set

```
laravel/framework ^13
livewire/livewire ^4.4
laravel/fortify ^1.41          (features: registration, resetPasswords, emailVerification,
                                updateProfileInformation, updatePasswords, twoFactorAuthentication
                                with confirm + confirmPassword; passkeys OFF)
spatie/laravel-permission ^8.3
spatie/laravel-translatable ^6.14
intervention/image ^4.3
```

5 direct packages beyond the framework. Composer `config.platform.php` is pinned to the production PHP minor (8.3 or 8.4) so `composer.lock` stays installable on the host.

## 4. Risks and Notes

- **PHP 8.3 floor:** spatie/permission 8, spatie/translatable 6.14 and intervention/image 4 all require PHP ≥ 8.3, the same as Laravel 13. If the host can only offer 8.2, the **whole stack** falls back (Laravel 12, permission 6.x, image 3.x). That is why host verification comes first.
- **Fortify → laravel/passkeys ^0.2** (pre-1.0) is installed as a transitive dependency even when the feature is off. Accepted: it is first-party and inactive.
- Versions are re-checked with `composer outdated` / `composer audit` when scaffolding (Phase 5). The constraints above are minimums.

## 5. Global Marketplace Additions (2026-10-11) — Awaiting Approval (G-11)

Sources: Packagist / npm metadata fetched 2026-10-11, plus GeoNames and PHP intl checks ([global design](13-global-marketplace.md)).

| Package | Version | Requirement | Licence | Benefit | Verdict |
|---|---|---|---|---|---|
| **propaganistas/laravel-phone** | ^6.1 (2026-10-02) | PHP ^8.2, Laravel 11–13 | MIT | Laravel validation rule + cast for international phone numbers | **Use** (replaces the planned `SriLankanPhone` rule) |
| giggsey/libphonenumber-for-php (dependency) | ^9.0 (2026-10-08) | PHP ^8.1 | Apache-2.0 | PHP port of Google's libphonenumber: parsing, validation, E.164/national formatting for every country | Comes with the above |
| **flag-icons** (npm, build-time) | ^7.5 | — | MIT | SVG country flags (Windows doesn't render emoji flags). Only the flags of active countries are bundled. | **Use** |
| symfony/intl | v8.1 needs PHP ≥ 8.4.1 (v7.x for 8.3) | | MIT | Country/currency names and fraction digits | **Not needed.** PHP's `intl` extension (ICU 77.1, already enabled) gives currency formatting, symbols, minor units and country names [VERIFIED]. |
| brick/money | 0.15 | PHP ^8.2 | MIT | Money value objects | **Not needed** for the MVP. Prices are stored as DECIMAL, arithmetic is limited (per-unit price) and uses bcmath. Reconsider if payments arrive. |
| commerceguys/addressing | v2.3 | PHP ≥ 8.0 | MIT | Per-country administrative-area terms (state/province/county) and subdivision lists | **Optional, dev-only:** used once to generate the region-label seed file. Not deployed. |

**Data (not packages):** GeoNames dumps (CC BY 4.0, attribution required) are downloaded by an artisan import command on a developer machine or staging. The curated result is committed as seed data. Nothing is fetched from GeoNames at runtime.

Updated proposed production package set: the §3 list **+ `propaganistas/laravel-phone`**.
