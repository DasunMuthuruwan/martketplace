# Global Marketplace — Impact Assessment and Design

| Field | Value |
|---|---|
| Date | 2026-10-11 |
| Status | **Draft — awaiting owner review. Nothing implemented.** |
| Trigger | Owner update: the marketplace is **not limited to Sri Lanka**. Multiple countries, currencies, location hierarchies and country-aware search from the beginning. |
| Supersedes | The Sri Lanka-only assumption (A1), the Sri Lanka-specific location tables (provinces/districts/cities), "LKR whole rupees" money storage, the `SriLankanPhone` rule, and the perch-only land normalisation in ADR-018 (generalised by ADR-021). Everything else that doesn't conflict remains approved. |

Labels: **[VERIFIED]** checked from source data · **[RECOMMENDED]** proposal · **[OPEN]** owner decision.

## 1. Impact on Approved Work

| Area | Before | After (proposed) | Docs updated |
|---|---|---|---|
| Market | Sri Lanka only | Any number of countries, enabled by the Owner | requirements, all architecture docs |
| Locations | `provinces → districts → cities` (Sri Lanka) | `countries → regions (1–2 levels) → cities → localities`, labels per country | database design §2.2, this doc §4 |
| Money | `price` bigint whole LKR | `price DECIMAL(18,3)` + `currency CHAR(3)` (ISO 4217) on the listing | database design §2.7, ADR-020 |
| Formatting | `Money::lkr()` | `Money` formatter using ICU (PHP intl) with the listing country's locale + per-currency display rules | this doc §3 |
| Land price | Per perch, area stored in perches | Area stored as entered value + unit **and** normalised square metres. Per-unit price unit configurable per country (LK: perch). | category tree §3.2.1, ADR-021 |
| Phone | `+94` regex | International E.164 with libphonenumber (`propaganistas/laravel-phone`), country picker | package assessment, requirements |
| Categories / attributes | Sri Lanka seed (e.g. deed types, three-wheelers) | Same tree, with optional **country scope** on categories, category attributes and options | category tree §6 |
| Search | Single market, price filter in LKR | Country/region/city/locality filters. Price filters and sort **require a single currency**. | this doc §6, routes doc |
| URLs | `/ads/{category}` | Country-prefixed browse pages `/{cc}/ads/...`. Listing URL `/ad/{slug}-{id}` **unchanged** (CD-12). | routes doc, ADR-023 |
| UI | Sri Lanka assumptions in copy | Country switcher, location selectors, currency-aware price input, configurable land units | design docs 02/03, prototype |
| Legal / privacy | PDPA (Sri Lanka) | Per-country obligations (e.g. GDPR/UK GDPR for EU/UK users) before enabling a country | requirements NFR-C, §10 below |
| Search engine | MySQL FULLTEXT | Still MySQL FULLTEXT for space-separated scripts. Languages without spaces (Chinese, Japanese, Thai) need a different engine before those countries are enabled. | §8 |
| Unchanged | Laravel 13 / Livewire 4 stack, moderation, roles, 2FA, images, retention, portability rules, Blade + Livewire UI, own theme, contact via phone/WhatsApp | — | — |

## 2. Countries

### 2.1 `countries` Table (Master Data, Admin-Managed)

| Column | Type | Notes |
|---|---|---|
| id | smallint PK | |
| iso2 | char(2) unique | ISO 3166-1 alpha-2 (`LK`, `US`, `GB`, `IN`). Used in URLs (lower-case). |
| iso3 | char(3) unique | |
| name | json | Translatable (English at launch). Seeded from GeoNames `countryInfo.txt` / ICU. |
| slug | varchar(60) unique | e.g. `sri-lanka` |
| default_currency | char(3) FK → currencies.code | e.g. `LKR` |
| number_locale | varchar(10) | ICU locale for formatting prices, e.g. `en_LK`, `en_IN` (lakh grouping), `en_GB` |
| phone_country_code | varchar(5) | e.g. `94` (for display and the phone picker) |
| timezone | varchar(40) | Default display timezone, e.g. `Asia/Colombo` |
| region_levels | tinyint 0–2 | How many administrative levels the selector shows (0 = none, e.g. city-states) |
| region_label, subregion_label, city_label, locality_label | json | Country-appropriate terms: LK `Province` / `District` / `City or town` / `Area`; US `State` / — / `City` / `Neighborhood`; GB `Country` / `County or area` / `Town or city` / `Area` |
| require_region, require_city | bool | Mandatory where reliable data exists. False for countries with incomplete data (§4.4). |
| land_price_unit_id | FK null → measurement_units | Unit for "price per X" on land (LK `perch`). Null = no per-unit price for that country. |
| default_area_unit_id | FK null → measurement_units | Preselected unit in area inputs |
| is_active | bool default false | Only active countries appear to users. Owner-controlled (`settings.manage`). |
| sort_order | smallint | |
| geonames_id | int null | Source reference for re-sync |
| timestamps | | |

- **Flags:** rendered from `iso2` using the **flag-icons** SVG set (MIT) bundled with Vite. No flag images are stored. Emoji flags are not used because Windows doesn't render them.
- **Adding a country** = activate it + import its regions/cities (§4.3) + set labels/units. No code change.
- All countries are seeded inactive. Owner activates the launch countries ([OPEN G-1]). **Implemented (T1.1):** 250 countries from the GeoNames `countryInfo.txt` snapshot (`database/data/geonames/`, obsolete `CS`/`AN` excluded), with phone codes from libphonenumber, `number_locale = en_{ISO2}` (ICU falls back to `en` where no such locale exists), and a default timezone (capital-matching zone or `database/data/countries.php` override). Seeders never modify existing rows, so admin changes survive a re-run.

### 2.2 `country_currencies` (Allowed Listing Currencies)

| Column | Notes |
|---|---|
| country_id, currency_code | composite PK |
| is_default | exactly one per country (mirrors `countries.default_currency`) |

MVP proposal: **only the default currency per country** [OPEN G-4]. The table exists so the Owner can later allow e.g. USD alongside a local currency without schema changes.

## 3. Currencies and Money

### 3.1 `currencies` Table

| Column | Type | Notes |
|---|---|---|
| code | char(3) PK | ISO 4217 (`LKR`, `USD`, `GBP`, `INR`) |
| numeric_code | char(3) | ISO 4217 numeric |
| name | json | e.g. "Sri Lankan Rupee" |
| minor_units | tinyint | Decimal places per ISO 4217: LKR 2, JPY 0, KWD 3 [VERIFIED via ICU] |
| symbol_override | varchar(8) null | Used when ICU's symbol isn't what local users expect. **LKR → `Rs`** (ICU gives `LKR` in `en_LK`). |
| display_decimals | enum(`auto`,`always`,`never`) default `auto` | `auto`: hide decimals when the amount is whole (`Rs 12,500,000`, not `Rs 12,500,000.00`) |
| is_active | bool | |

Seeded from ICU data available through PHP `intl` (no extra package). **Implemented (T1.1):** 154 currencies (every currency used by a seeded country), English names, ISO numeric codes and minor units from ICU/CLDR, `LKR → Rs` override in `database/data/countries.php`. Further overrides are an owner/admin decision.

### 3.2 Listing Price Storage (ADR-020)

- `listings.price DECIMAL(18,3) NULL` (up to 999 trillion, 3 decimals covers KWD/BHD/OMR) and `listings.currency CHAR(3) NOT NULL` (FK → currencies).
- **Validation:** price > 0 unless the price type is free/on request; decimals ≤ the currency's `minor_units`; currency must be allowed for the listing's country (`country_currencies`).
- **Source of truth is the listing itself.** If a country's default currency changes later, existing listings keep their original currency and price. Nothing is ever converted or overwritten.
- Arithmetic (per-unit price) uses **bcmath** strings, not floats.

### 3.3 Formatting

One `Money::format(amount, currency, locale)` service using ICU `NumberFormatter`:

| Example [VERIFIED with PHP intl / ICU 77.1] | Raw ICU output | Displayed (with rules) |
|---|---|---|
| 12,500,000 LKR, locale `en_LK` | `LKR 12,500,000.00` | **`Rs 12,500,000`** (symbol override, auto decimals) |
| 12,500,000 USD, `en_US` | `$12,500,000.00` | `$12,500,000` |
| 12,500,000 GBP, `en_GB` | `£12,500,000.00` | `£12,500,000` |
| 12,500,000 INR, `en_IN` | `₹1,25,00,000.00` | **`₹1,25,00,000`** (Indian lakh/crore grouping) |
| 12,500,000 JPY, `ja_JP` | `￥12,500,000` | `¥12,500,000` (0 decimals) |
| 12,500.5 KWD, `en_KW` | `KWD 12,500.500` | `KWD 12,500.500` (3 decimals) |

- **Locale used:** the **listing country's** `number_locale` (Indian listings show lakh grouping, which Indian sellers and buyers expect) [OPEN G-5; alternative: the viewer's locale].
- The currency is always visible: symbol or code. Where symbols are ambiguous (`$` = USD/AUD/CAD…), cross-country pages show the ISO code next to the price (`$12,500 USD`).
- Future currency conversion (not built): a separate "≈ €12,000 at 11 Oct 2026 rate" line from an `exchange_rates` table (`base`, `quote`, `rate`, `source`, `fetched_at`). It is never stored on the listing and never replaces the original price.

## 4. Locations

### 4.1 Hierarchy and Tables

```
countries ─< regions (level 1: state / province / nation)
               └─< regions (level 2, optional: district / county / area)
                     └─< cities (city / town)
                           └─< localities (optional curated neighbourhoods)
```

| Table | Key columns | Notes |
|---|---|---|
| `regions` | id, country_id, parent_id null, level (1/2), name json, slug, code (e.g. GeoNames `LK.36`), geonames_id null, is_active, sort_order | **Unique `(country_id, slug)`** (implemented T1.2): location URLs accept a level-1 or level-2 slug, so slugs must be unique per country. This also avoids MySQL treating NULL parents as distinct. Labels come from the country (no global "Province" label). |
| `cities` | id, country_id, region_id (deepest level; **null only for countries with 0 region levels**), name json, ascii_name, slug, latitude/longitude decimal(9,6) null, population int null, geonames_id null, is_active, is_curated | Unique `(country_id, region_id, slug)`. `country_id` is denormalised for fast lookups. |
| `localities` | id, city_id, name json, slug, is_active | Optional, curated. Only countries/cities where the Owner adds them. |
| `location_requests` | id, user_id, country_id, region_id, proposed_city, proposed_locality, status, handled_by | "My town isn't listed": queued for staff to add (§4.5). |

**Configured per country** (`countries.region_levels`), matching the owner's examples [VERIFIED against GeoNames structure]:

| Country | Level 1 | Level 2 | City / town | Locality (optional) | GeoNames coverage |
|---|---|---|---|---|---|
| Sri Lanka | Province (9) | District (25) | Town, e.g. Nugegoda | Area | admin1 = 9 provinces, admin2 = 25 districts. Towns: only 89 in `cities1000`; the full LK dump has 17,856 populated places with mostly no population. **Needs curation.** |
| United States | State (51) | — (counties not shown) | City, e.g. Los Angeles | Neighborhood, e.g. Hollywood | 51 admin1, 17,343 places ≥ 1,000 people |
| United Kingdom | Country (England, Scotland, Wales, NI) | County / area, e.g. Greater London | Town/city or borough, e.g. Westminster | Area | 4 admin1, 185 admin2, 4,644 places ≥ 1,000 |
| India | State / UT (36) | — (763 districts optional later) | City / town | Locality | 36 admin1, 7,113 places ≥ 1,000 |

### 4.2 Listing Location Columns

`listings.country_id` (required), `region_id` (level 1), `subregion_id` (level 2, null), `city_id`, `locality_id` (null), `locality_text` varchar(80) null (free text when no curated locality; shown but not filterable; moderated). All IDs are stored (owner requirement: filtering stays reliable when names change). Server-side validation checks that the chain is consistent (city ∈ subregion ∈ region ∈ country) and active.

**Validation (implemented T1.2):** `App\Validation\LocationChain` is an after-validation hook (`FormRequest::after()` / `Validator::after()`). Errors land on the exact field, and messages use the country's terms ("Select the province.", "The selected district is not in this province."). It checks existence, active state, parent/country membership, the country's region levels and `require_*` flags, rejects a child given without its parent, and rejects malformed IDs before any query.

**No street address** is collected. Location display: `Nugegoda, Colombo District, Western Province, Sri Lanka` on detail pages (configurable display depth: cards show `Nugegoda, Colombo` within one country, plus the flag/country on cross-country results).

### 4.3 Dataset and Import (ADR-022)

| Dataset | Use | Licence | Verdict |
|---|---|---|---|
| **GeoNames** (`countryInfo.txt`, `admin1CodesASCII.txt`, `admin2Codes.txt`, `citiesN.zip`, per-country dumps) | Countries, regions (levels 1–2), cities | **CC BY 4.0** [VERIFIED readme]. Attribution required ("Location data © GeoNames, CC BY 4.0" on About/footer). Files regenerated daily. | **[RECOMMENDED] primary source** |
| ICU via PHP `intl` | Currency minor units, symbols, country names, number formats | Unicode licence (bundled with PHP) | **Use** (no package) |
| `commerceguys/addressing` (MIT) | Country-specific region terms (state/province/county) to pre-fill labels | MIT (library); data from Google's address metadata | Optional **dev-only** helper for generating label seed data |
| dr5hn countries-states-cities-database | Alternative countries/states/cities set | **ODbL-1.0** [VERIFIED GitHub] (share-alike for derived databases) | Not recommended (licence obligations) |
| OpenStreetMap / Nominatim | Geocoding, neighbourhoods | ODbL. Public Nominatim has strict usage limits. | Not for MVP |

**Import process** (artisan, run locally or on staging, never automatically in production):

1. `php artisan geo:import-countries`: all countries from `countryInfo.txt` (inactive), with currency and phone code.
2. `php artisan geo:import-regions {CC}`: admin1 (+ admin2 when `region_levels = 2`).
3. `php artisan geo:import-cities {CC} --source=cities1000|cities5000|country-dump --min-population=N`: then **curation** in Admin → Locations (merge duplicates, fix wrong parents, add missing towns, deactivate villages).
4. Export reviewed data as an idempotent SQL/seed file → deploy (works without SSH).
5. **Updates:** quarterly diff against the latest GeoNames files on staging. Renames update names only (IDs never change). Removed places are **deactivated, never deleted** when referenced by listings.

**Implemented (T1.3) — commands and runbook** (developer machine or staging; download files from https://download.geonames.org/export/dump/ into `GEONAMES_PATH`, default `storage/app/geonames`, never committed):

```bash
php artisan geo:import-countries [--sync]                  # committed snapshot; new countries inactive; --sync updates name/ISO3/GeoNames ID only
# set the country's region_levels (G-3) before importing regions
php artisan geo:import-regions LK [--deactivate-missing]    # admin1 (+ admin2 when region_levels = 2)
php artisan geo:import-cities LK --file=LK.txt [--min-population=N] [--deactivate-missing]
php artisan geo:import-cities US --file=cities1000.txt
```

Rules: matched by GeoNames ID; IDs and slugs never change; renames update names only; curated cities keep name and region (population and coordinates refresh); excluded feature codes `PPLH, PPLQ, PPLW, PPLCH, PPLX` and places below `--min-population` are *filtered* (never deactivated); only places absent from the file are deactivated, and only with `--deactivate-missing`; nothing is deleted. Each run prints created / updated / unchanged / deactivated / skipped / filtered counts with notes.

**Real-data trial (2026-10-11, test database only):** LK regions 34 (9 + 25), GB 189 (4 + 185), US 51. LK full dump → 17,470 places created, 274 skipped (blank/unknown district code), 112 filtered, ~14 s. US `cities1000` → 16,655 created, 688 filtered, ~14 s. This confirms that Sri Lanka needs a curated subset (T1.4).

**Evidence of curation need (Sri Lanka):** Nugegoda and Kottawa are missing from `cities1000`. In the full dump there are two "Kottawa" entries, and Malabe (Colombo District) is assigned to North Western Province. So a curated Sri Lanka town list (~400–700 towns) is proposed, reviewed by the owner.

### 4.4 Countries with Incomplete Data

`require_region` / `require_city` = false → the selector allows "Other / not listed" with `locality_text`. Listings are still filterable by country (and region if chosen). This is documented per country in Admin → Locations → Countries.

### 4.5 Admin Workflow (Admin → Locations)

Countries (activate, labels, currency, locale, units, requirements) · Regions (CRUD, reorder) · Cities (search, add, edit, merge duplicates → listings re-pointed in one transaction, deactivate) · Localities · **Location requests** queue (approve = create city/locality and update the requesting listing) · Import log. Permissions: `locations.manage` (Admin, Owner). Country activation needs `settings.manage` (Owner).

### 4.6 Storage Estimate [VERIFIED row counts, GeoNames 11 Oct 2026]

| Data | Rows | Approx. size incl. indexes |
|---|---|---|
| Countries + currencies | ~250 + ~180 | < 1 MB |
| Regions level 1 (all countries) | 3,865 | ~1 MB |
| Regions level 2 (only 2-level active countries) | LK 25, GB 185 | < 1 MB |
| Cities, active countries only | LK ~600 curated, GB 4,644, IN 7,113, US 17,343 | ~10 MB for these four |
| All countries `cities1000` (not recommended) | 171,188 | ~40–50 MB |

## 5. Measurement Units (Land and Area) — ADR-021

| Table | Columns |
|---|---|
| `measurement_units` | id, code (`sqm`, `sqft`, `perch`, `rood`, `acre`, `hectare`, `cent`, `marla`, `kanal`, `bigha`…), name json, symbol, dimension (`area`), factor_to_base (DECIMAL(20,10), base = m²), is_active |
| `country_measurement_units` | country_id, unit_id, is_default, sort_order (which units a country offers in area inputs) |

| Country (proposal, [OPEN G-6]) | Units offered | Default | Land price shown per |
|---|---|---|---|
| Sri Lanka | perch, rood, acre, hectare | perch | **perch** (approved behaviour kept) |
| India | sq ft, sq m, acre, hectare, cent | sq ft | sq ft |
| United Kingdom | acre, hectare, sq ft, sq m | acre | acre |
| United States | acre, sq ft | acre | acre |

**Storage (attribute `land_size`):** `listing_attribute_values` gains `input_value DECIMAL(18,4)`, `input_unit_id` (what the seller typed, the source of truth) and `value_number` = normalised **m²** (for filtering and sorting across units). 1 perch = 25.29285264 m², 1 acre = 4,046.8564224 m², 1 rood = 1,011.7141056 m².

**Per-unit price:** shown only when the category enables it (`categories.unit_price_attribute_id`, Land) **and** the listing country has `land_price_unit_id`. If the seller typed the area in that unit, it is computed from `input_value` exactly. Otherwise it is converted from m² with bcmath and rounded per the currency's display rules. Guards from ADR-018 still apply (area > 0, price > 0, sale/rent only, no manual entry).

## 6. Search and Filtering Rules

| Rule | Design |
|---|---|
| Scope | Users browse within a country by default (`/{cc}/ads…`). A global search (`/ads?q=`) across all active countries is allowed for keywords and categories. |
| Location filters | Country → region → subregion → city → locality, each narrowing the next (dependent selects, cached lookup endpoints). Changing a parent clears its children. |
| Currency rule | **Prices in different currencies are never compared.** Price min/max filters and price sort are enabled only when **one currency** is in effect: inside a country, its default currency (shown as "Prices in LKR"). In global search, price filters and sort stay disabled until the user picks a currency; results are then limited to that currency. Listings in other currencies (future multi-currency countries) are excluded from price-filtered results, with a notice. |
| Area filters (land) | Entered in any of the country's units, converted to m² for the query. |
| URL state | All filters in the URL (`?region=western-province&city=nugegoda&currency=LKR&min_price=…`). Shareable and bookmarkable. |
| Indexable landing pages | Country home, country + category, country + region (+ city) + category: see the routes doc. Other combinations get `noindex,follow` + canonical. |
| Defaults | Visitor country: remembered choice (cookie) → Cloudflare `CF-IPCountry` header **if** the site is later proxied by Cloudflare (free) → site default country setting. No paid geo-IP service. [OPEN G-2] |

## 7. Listing Creation Flow (Country-Aware)

Wizard steps (replacing the earlier 5-step flow):

1. **Category** (and subcategory)
2. **Location**: country (defaults to the user's last-used/profile country) → region → (subregion) → city/town → optional locality. "My town isn't listed" creates a location request.
3. **Details**: title, description, condition, category attributes (country-scoped options only)
4. **Price**: currency shown automatically from the country (read-only when only one currency is allowed), price input formatted per the currency's decimals. Land: area + unit (country's units) + calculated per-unit preview.
5. **Photos**
6. **Contact and review** → submit for moderation

**Changing the country after entering data:**

- If a price is entered and the currency would change: a confirmation dialog — "Changing the country to India changes the currency from LKR to INR. Your price will be cleared so you can enter it in INR." [Change country] [Cancel]. The price is **never reinterpreted**.
- If a land area uses a unit the new country doesn't offer: the area is cleared, with a notice. If the unit is offered, the value stays (a physical area doesn't change).
- Country-scoped attribute values (e.g. Sri Lankan deed type) that don't exist in the new country are cleared, with a notice listing them.
- Region, city and locality are always reset.

## 8. Global Search Engine Note

InnoDB FULLTEXT tokenises on spaces and punctuation. This works for English, Sinhala, Tamil, Hindi and most European languages, but **not** for Chinese, Japanese or Thai (no spaces). Rule: before activating such a country, either enable MySQL's `ngram` parser on a separate index (needs DB-level config, may be unavailable on shared hosting) or move to Meilisearch (scaling Stage 3). This is documented as a country activation checklist item.

## 9. Indexing and Query-Performance Strategy

| Query | Index |
|---|---|
| Country browse, newest | `listings (status, country_id, published_at)` |
| Country + category | `listings (status, country_id, category_id, published_at)` |
| Region / subregion / city browse | `listings (status, region_id, published_at)`, `(status, subregion_id, published_at)`, `(status, city_id, published_at)` |
| Locality | `listings (locality_id)` (sparse) |
| Price filter/sort in one currency | `listings (status, country_id, currency, price)`, `(status, category_id, currency, price)` |
| Expiry, ownership, moderation | as before (database design §4) |
| City search-as-you-type | `cities (country_id, ascii_name)` with prefix `LIKE 'nug%'` (no leading wildcard), limit 20 |
| Dependent selects | `regions (country_id, level, parent_id, sort_order)`, `cities (region_id, is_active, name)` |
| Attribute filters | unchanged typed-EAV indexes. Area filters use `value_number` (m²). |

- The listing public scope always starts with `status = active AND expires_at > now()`. The optimiser picks the most selective location/category index. Validated with `EXPLAIN` on **50k seeded listings across 4 countries** (Phase 5/7), adding covering composites only where measured.
- Lookup endpoints (`/lookup/countries/{cc}/regions`, `/lookup/regions/{id}/cities`) are cached in the file cache and served with `Cache-Control: public, max-age=86400`.
- Location and category counts for landing pages are cached for 15 minutes per country.
- Location names are joined from small, cached tables (never duplicated per listing). `attributes_summary` JSON keeps card rendering join-free.

## 10. Legal, Privacy and Operations by Country

| Topic | Impact | Proposal |
|---|---|---|
| Privacy law | Users in the EU/UK bring GDPR/UK GDPR (lawful basis, cookie consent for non-essential cookies, data subject rights, possibly an EU/UK representative). India: DPDP Act 2023. US: state laws (e.g. CCPA). | **Enable a country only after the owner's legal check for that country** [OPEN G-10]. The MVP uses no tracking cookies, which keeps consent needs minimal. |
| Prohibited items | Differ by country (weapons, medicines, wildlife) | Global prohibited list + per-country additions (`blocked_terms.country_id` nullable, Prohibited Items page per country) |
| Moderation language | Ads may be written in local languages | Moderators need language coverage per enabled country, or launch countries are limited to languages the team reads [OPEN G-12] |
| Phone numbers | International | libphonenumber validation, E.164 storage, WhatsApp `wa.me/{E.164 digits}` |
| Time display | Multiple timezones | Relative times in lists. Absolute times in the viewer's country timezone. Stored in UTC. |
| Email | Global recipients | Unchanged (provider via env) |

## 11. Testing Scenarios (Added to Phase 5/7 Plans)

1. A listing in LK uses LKR by default; one in IN uses INR; one in GB uses GBP. Each shows correctly formatted prices on card, detail, search, favourites and dashboard (`Rs 12,500,000`, `₹1,25,00,000`, `£12,500,000`).
2. A price with 3 decimals in LKR → 422. In KWD → accepted. JPY with decimals → 422.
3. Changing the country in the wizard after entering a price shows the warning. The price is cleared, never converted.
4. A location chain mismatch (city not in region, region not in country, inactive city) → 422.
5. Deep filters: country → region → city → locality return only matching listings. A renamed city still filters correctly (IDs).
6. Price filter in global search without a currency → disabled (UI) and ignored or rejected (server). With `currency=USD` → only USD listings.
7. Price sort never mixes currencies (feature test with LKR + INR listings).
8. Land: 50 perches in LK → `Rs 250,000 / perch`. The same land entered as 1 rood 10 perches → same value. A GB land listing in acres → per-acre price. A country without `land_price_unit_id` → no per-unit price. Area in sq ft filtered by an acre range → correct m² conversion.
9. A country-scoped option (LK deed type) is not offered for a GB listing and is rejected if posted.
10. Owner deactivates a country → its browse pages return 404 (or 410 for listings, configurable), new listings are blocked, existing listings are hidden.
11. GeoNames re-import: renamed city keeps its ID and listings. A removed city is deactivated, not deleted.
12. Phone: valid numbers for LK/US/GB/IN accepted and normalised to E.164. Invalid → 422. WhatsApp link uses E.164 digits.
13. SEO: canonical/OG/JSON-LD use `APP_URL`. `Offer.priceCurrency` = listing currency. `addressCountry` = iso2. Region and locality are present. No street address.
14. Performance: `EXPLAIN` for the main location/category/price queries on 50k listings across 4 countries uses the intended indexes. p95 under target.
15. No hardcoded country: an automated test greps code for `LKR`, `Sri Lanka`, `+94`, `perch` outside seeders, lang files and tests (extends portability rule P1).

## 12. Owner Decisions Needed (Global)

> **Decided 2026-10-11:** the owner accepted every recommendation below as the decision ("use the defaults"). G-1 to G-13 are therefore settled as recommended. Owner actions that remain are part of those recommendations: legal review before activating each country (G-10), moderation-language coverage per country (G-12), and review of the curated location lists (T1.4, review C1).

| # | Decision | Recommendation |
|---|---|---|
| G-1 | Which countries are **active at launch**? | Build for all; **activate Sri Lanka first**, then add countries one at a time after data curation, legal check and moderation capacity (G-10, G-12). Or name the launch set. |
| G-2 | Default country for new visitors | Remembered choice → Cloudflare header (if used) → site default **Sri Lanka**. Root `/` shows a country chooser when nothing is known. |
| G-3 | Location levels per country | LK: Province → District → Town → Area. US: State → City → Neighborhood. GB: Country → County/area → Town/borough → Area. IN: State → City → Locality. |
| G-4 | Multiple currencies per country? | MVP: default currency only (table ready for more) |
| G-5 | Price formatting locale | Listing country's locale (lakh grouping for India) |
| G-6 | Land units and "price per" unit per country | Table in §5 |
| G-7 | Global (all countries) search page | Yes, for keyword/category. Price filters only with a chosen currency. |
| G-8 | Country-prefixed browse URLs (`/{cc}/ads/...`) | Yes (ADR-023). Listing URLs unchanged. |
| G-9 | Location dataset | GeoNames (CC BY 4.0, attribution in footer/About) + curated city lists for active countries |
| G-10 | Legal review per country before activation | Required (owner arranges) |
| G-11 | New package: `propaganistas/laravel-phone` (+ libphonenumber) and npm `flag-icons` | Approve |
| G-12 | Moderation language coverage | Launch only countries whose ad languages the team can moderate |
| G-13 | Country-specific categories/options | Keep one global tree with country scoping (e.g. Three-wheelers: LK, IN; deed types: LK only) |
