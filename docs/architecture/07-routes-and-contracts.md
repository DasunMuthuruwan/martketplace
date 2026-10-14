# Routes and Interface Contracts

| Field | Value |
|---|---|
| Status | **Revised 2026-10-11: URL scheme approved (CD-12). Awaiting approval of the revised design.** All absolute URLs (canonical, OG, sitemap, emails) are generated from `APP_URL` through `route()`/`url()`. No domain is hardcoded. |
| Style | Server-rendered web routes + Livewire components. A few small JSON endpoints. **No public REST API in the MVP** (a future mobile app would add `/api/v1` with Sanctum). |

Middleware shorthand: `auth` = logged in, `verified` = email verified, `active` = EnsureAccountActive, `staff` = `can:access-admin` + 2FA for Owner/Admin.

## 1. Public Pages (Revised for the Global Marketplace, ADR-023)

`{cc}` = lower-case ISO 3166-1 alpha-2 of an **active** country (`lk`, `us`, `gb`, `in`). Inactive or unknown codes → 404. Category and location slugs come from the database. `in` is a reserved path segment that separates category from location.

| Method | URI | Name | Handler | Notes |
|---|---|---|---|---|
| GET | `/` | home | `HomeController` | Redirect (302) to the remembered/default country home. With no known country, a country chooser + global latest. |
| GET | `/countries` | countries | `CountryController@index` | All active countries with flags and ad counts |
| GET | `/{cc}` | country.home | `HomeController@country` | Country home: search, categories, latest in that country |
| GET | `/{cc}/ads` | ads.country | `Livewire\Public\SearchPage` | All ads in the country + filters |
| GET | `/{cc}/ads/{category}` | ads.category | SearchPage | Top-level category in the country |
| GET | `/{cc}/ads/{category}/{subcategory}` | ads.subcategory | SearchPage | Leaf category, attribute filters |
| GET | `/{cc}/ads/in/{region}` · `/{cc}/ads/in/{region}/{city}` | ads.location | SearchPage | **Location landing pages** (indexable). The region slug can be level 1 or level 2. |
| GET | `/{cc}/ads/{category}/in/{region}` · `…/in/{region}/{city}` | ads.category.location | SearchPage | Category + location landing pages (indexable), e.g. `/lk/ads/cars/in/colombo-district/nugegoda` |
| GET | `/ads` | ads.global | SearchPage | Global keyword/category search across active countries. Price filters need `currency`. |
| GET | `/ad/{slug}-{listing}` | listing.show | `ListingController@show` | **Unchanged (CD-12).** Country-independent. The id resolves the listing. Slug mismatch → 301. |
| GET | `/seller/{user}-{slug}` | seller.show | `SellerController@show` | Active listings only |
| GET | `/page/{page:slug}` | page.show | `PageController@show` | Terms, privacy, safety… (`?country=lk` shows country-specific sections such as prohibited items) |
| GET | `/sitemap.xml`, `/sitemaps/{file}` | | Static files | Index → per-country chunks |
| GET | `/robots.txt` | | Static file | |

**Canonical rules:** a canonical URL is built from `APP_URL` + the path. Filters other than category/region/city are `noindex,follow` with a canonical to the nearest landing page. Listing pages: `<title>` = "{title} – {city}, {country} | {APP_NAME}". JSON-LD `Offer.priceCurrency` = listing currency, `PostalAddress` with `addressLocality`, `addressRegion`, `addressCountry` (iso2). Never a street address.


> **Implementation status (T5.2, plain views until Phase 6):** all routes in the table above except `/page/{slug}` (pages arrive with page management in M6) and the sitemap/robots files (T5.3). Controllers: `HomeController` (`/` → 302 to the visitor's country from `CountryContext`, otherwise the country chooser + latest ads from every country; `/{cc}` country home), `CountryController@index` (ad counts cached 15 min), `SearchController` (all `ads.*` routes), `ListingController@show`, `SellerController@show`. Rules as built:
> - `{cc}` is exactly two letters, so it never captures `/admin`, `/login` etc.; inactive or unknown countries are 404 and upper case 301s (T1.6 middleware). The `in` segment is reserved by the route patterns.
> - Category path: a leaf given alone (`/lk/ads/cars`) and a renamed slug answer 301 to the canonical `/{parent}/{leaf}` path (query kept); a subcategory under the wrong parent, an unknown slug or a category not offered in the country is 404. A location in the path (`/in/{region}/{city}`, region of level 1 or 2) replaces any location in the query; an unknown region or a city outside it is 404.
> - Query filters follow §1.1 (`a[key]`, comma-separated values; unknown or non-filterable keys ignored). An invalid query value (e.g. `min_price=abc`, `page=500`) answers 302 to the same page without it, never an error page. `/ads?country=xx` 301s to `/xx/ads`. `throttle:search` = 120/min per IP.
> - `/ad/{slug}-{id}`: public → 200 (contact buttons from `ContactResolver::buttons()`, never a number in the HTML; the reveal requests are wired in F2.3); different slug → 301; sold within `listings.sold_page_days` (30) → 200 "sold" page, `noindex`; expired, sold longer ago or deleted → 410; draft, pending, rejected, suspended, owner not active, inactive country or category → 404.
> - `/seller/{id}-{slug}`: active accounts only (404 otherwise), slug mismatch 301, public ads only, never the email or phone.
> - Robots: `noindex,nofollow` everywhere until `SEO_INDEXING=true`; then landing pages without a query are `index,follow` and filtered pages `noindex,follow` (canonical and structured data: T5.3).
> - Found by the tests: Laravel passes route parameters to controller methods by position, so `/lk/ads/in/western` handed the region to the category argument; `SearchController` now reads route parameters by name.
### 1.1 Search Query Contract

| Param | Example | Rule |
|---|---|---|
| `q` | `aqua 2015` | ≤ 100 chars |
| `country` | `lk` | Only on `/ads` (global). Implied by `{cc}` on country routes. |
| `region` / `subregion` / `city` | `western-province` / `colombo-district` / `nugegoda` | Slugs, validated against the parent chain. Changing a parent drops children. |
| `locality` | `kirulapone` | Curated locality slug (where available) |
| `type` | `sale` | `sale`, `rent`, `wanted` |
| `currency` | `LKR` | ISO 4217. On country routes it defaults to the country currency. **Required** for `min_price`, `max_price`, `price_asc`, `price_desc` on `/ads` (global). Without it those params are ignored and the UI shows "Choose a currency to filter by price". |
| `min_price` / `max_price` | `1000000` | Decimal, ≤ the currency's minor units |
| `condition` | `used` | Enum |
| `sort` | `newest` | `newest`, `price_asc`, `price_desc` (price sorts within one currency only) |
| `page` | `2` | 1–100 |
| `a[{key}]` | `a[make]=toyota` | Select: option value(s), comma-separated for multiple. Only options in scope for the country. |
| `a[{key}][min]`, `a[{key}][max]` | `a[year][min]=2015` | Number/year ranges |
| `a[land_size][min]` + `a[land_size][unit]` | `a[land_size][min]=10&a[land_size][unit]=perch` | Area ranges in any unit offered by the country, converted to m² |
| `a[{key}]=1` | `a[parking]=1` | Boolean |

Unknown keys, or attributes that aren't filterable for the category/country, are ignored (never passed to SQL). Max 10 attribute filters per request.

## 2. Authentication (Fortify, Own Views)

| Method | URI | Notes |
|---|---|---|
| GET/POST | `/register` | Turnstile, honeypot, `throttle:register` |
| GET/POST | `/login` | `throttle:login` |
| POST | `/logout` | |
| GET/POST | `/forgot-password`, `/reset-password/{token}` | |
| GET | `/email/verify`, `/email/verify/{id}/{hash}` (signed); POST `/email/verification-notification` | |
| GET/POST | `/user/confirm-password` | Before sensitive actions |
| GET/POST | `/two-factor-challenge`; `/user/two-factor-*` | Staff 2FA |

## 3. Account Area (`auth`, `verified`, `active`)

| URI | Handler | Purpose |
|---|---|---|
| `/account` | `Account\Dashboard` | Summary |
| `/account/listings` | `Account\MyListings` | Tabs by status. Actions: edit, renew, mark sold, delete. |
| `/account/listings/create` | `Account\PostListingWizard` | Multi-step create |
| `/account/listings/{listing}/edit` | `Account\PostListingWizard` | Policy `update` |
| `/account/favourites` | `Account\Favourites` | |
| `/account/settings/profile` | `Account\ProfileSettings` | Name, email, phone |
| `/account/settings/contact` | `Account\ContactSettings` | Contact visibility, WhatsApp |
| `/account/settings/security` | `Account\SecuritySettings` | Password, 2FA (staff), delete account (password confirm) |
| `/account/notifications` | `Account\Notifications` | In-app list (S) |

> **Implementation note (T2.3):** the settings pages exist as plain views served by `Account\SettingsController` (`account.settings.profile|contact|security`, `PUT account.settings.contact.update`, `DELETE /account` = `account.destroy`). Profile and password forms post to Fortify (`PUT /user/profile-information`, `PUT /user/password`). Profile and security need only `auth` (an unverified user can fix a mistyped email or delete the account); contact settings need `verified`. Deletion asks for the password in the form itself instead of the 15-minute `password.confirm` window. F3.2 replaces the views with the Livewire components named above and reuses the same actions.

### 3.1 PostListingWizard — Livewire Contract

| Action / property | Input | Server behaviour |
|---|---|---|
| `selectCategory(int $categoryId)` | leaf category id | Loads `AttributeSchema` (per category + country), resets attribute state |
| `setCountry(string $iso2, bool $confirmed = false)` | active country | If price, currency-dependent values, incompatible area units or country-scoped attributes are already entered and `confirmed` is false → returns a **confirmation** listing what will be cleared (GLB-11). On confirm: sets the currency to the country default, clears those values and resets region/city/locality. Never converts values. |
| `saveLocation()` | country_id, region_id, subregion_id, city_id, locality_id, locality_text | Validates the chain (city ∈ subregion ∈ region ∈ country, all active) and the country's require flags. Creates or updates the **draft**. |
| `requestLocation()` | proposed_city / proposed_locality | Creates a `location_requests` row. The listing can still be saved with region + locality text. |
| `saveDetails()` | title, description, type, condition, `attributes[key]` (area: value + unit) | Validates with FormRequest-equivalent rules + schema rules for the listing's country |
| `savePrice()` | price, price_type, rent_period (currency is server-set from the country; only an allowed currency is accepted) | Decimals ≤ the currency's minor units. Land: area required and per-unit preview returned (calculated, read-only). |
| `updatedPhotos()` | `TemporaryUploadedFile[]` | `ListingImageProcessor` per file (security doc §4). Returns image ids + thumbnail URLs. |
| `reorderImages(array $ids)` / `removeImage(int $id)` / `setCover(int $id)` | ids owned by the listing | Policy check on every call |
| `saveContact()` | show_call, show_whatsapp (+ prompt to set account visibility) | Requires at least one effective channel |
| `submit(string $turnstileToken)` | | `SubmitListing` action → `pending_review` or `active` |
| Locked properties | `listingId`, `userId` | `#[Locked]`, so they can't be tampered with from the browser |

> **Implementation status (T4.2):** the server side of `selectCategory` / `setCountry` / `saveLocation`, `saveDetails` and `savePrice` exists as UI-free actions the wizard (F4.1) will call: `App\Actions\Listings\SaveListingLocation` (creates the draft on first save: verified + active owner, rolling 24-hour limit incl. deleted drafts, currency = country default; on an existing draft a country change throws `CountryChangeNeedsConfirmation` with the list of what would be cleared until `confirm_country_change` is sent, then clears price, location and incompatible attribute values via `ListingCountryChange` without converting anything; a category change clears the old details), `SaveListingDetails` (title 10–100 and description 20–5,000 plain text with whitespace normalised, type allowed by the category, condition when the category shows it and the ad is not "wanted", attribute rules from `AttributeSchema`; values written by `AttributeValueWriter`, which also builds `attributes_summary`) and `SaveListingPrice` (price option allowed by the category, amount for fixed/negotiable unless "wanted" or price optional, decimals ≤ currency minor units, rent period for rent ads, area required first where a unit price applies; currency never read from input). `ListingUnitPrice` gives the per-unit preview. These actions edit drafts only (`ListingNotEditable` otherwise); editing published ads is T4.4. Draft rows may have null `type`, `title`, `slug`, `description`, `price_type` until their step (migration `2026_10_12_000001`); submit (T4.4) requires them.

## 4. JSON Endpoints

All return `application/json`. Errors use Laravel's standard shape `{ "message": "...", "errors": { "field": ["..."] } }` with 422, 403/404 and 429 (`Retry-After` header).

### 4.1 Reveal Contact

`POST /listings/{listing}/contact` — `throttle:contact-reveal`, CSRF

Request:
```json
{ "channel": "call", "turnstile_token": "optional-unless-challenged" }
```
200 response:
```json
{ "channel": "call", "phone": "+94771234567", "display": "077 123 4567" }
```
```json
{ "channel": "whatsapp", "url": "https://wa.me/94771234567?text=Hi%2C%20I%27m%20interested%20in%20%22Toyota%20Aqua%202015%22%20https%3A%2F%2F..." }
```
Errors: 401 `{"message":"login_required"}` when visibility is `registered`; 403 when hidden or the channel is disabled; 404 when the listing is not public; 428 `{"message":"challenge_required"}` when Turnstile is needed.
> **Implementation status (T4.5):** `POST /listings/{listing}/contact` (`listings.contact`) via `ContactRevealController` → `App\Actions\Listings\RevealContact`. Order: `channel` validated (422) → listing must be public (404, incl. drafts, pending, sold, suspended, expired by date, deleted, non-numeric id) → `ContactResolver` (403 `unavailable` when the account is hidden, the listing toggle is off, the channel is switched off site-wide (`CONTACT_CALL_ENABLED`, `CONTACT_WHATSAPP_ENABLED`, MSG-07) or there is no number; 401 `login_required` for guests when visibility is `registered`) → guests over `contact.guest_challenge_after` reveals per hour need a Turnstile token when Turnstile is configured (428 `challenge_required`; fails open if Cloudflare is unreachable) → `contact_reveals` row (IP as HMAC; the seller's own reveals are not logged). `throttle:contact-reveal`: 30/hour per account, 10/hour per guest IP (429 with `Retry-After`). Call returns E.164 + display (national format when the number belongs to the listing country, international otherwise). WhatsApp returns `https://wa.me/{digits}?text=…` with the title and `Listing::publicUrl()` (`/ad/{slug}-{id}` from `APP_URL`; the page itself comes in T5.2). `ContactResolver::buttons()` gives the listing page its button states without numbers. Responses are `no-store`. `contact_reveals` are pruned by `model:prune` (daily) after `retention.contact_reveal` days.

### 4.2 Location and Option Lookups (Cacheable, Public)

| Method | URI | Response |
|---|---|---|
| GET | `/lookup/countries` | `[{ "iso2": "LK", "name": "Sri Lanka", "currency": "LKR", "region_levels": 2, "labels": {"region": "Province", "subregion": "District", "city": "City or town", "locality": "Area"}, "area_units": ["perch","rood","acre","hectare"], "land_price_unit": "perch" }]` (active only) |
| GET | `/lookup/countries/{cc}/regions?level=1` | `[{ "id": 36, "slug": "western-province", "name": "Western Province" }]` |
| GET | `/lookup/regions/{region}/children` | Level-2 regions of a level-1 region |
| GET | `/lookup/regions/{region}/cities?q=nug` | `[{ "id": 812, "slug": "nugegoda", "name": "Nugegoda" }]` (prefix search on `ascii_name`, max 20) |
| GET | `/lookup/cities/{city}/localities` | Curated localities (may be empty) |
| GET | `/lookup/attribute-options/{option}/children` | `[{ "id": 301, "value": "aqua", "label": "Aqua" }]` (e.g. models of a make) |

`Cache-Control: public, max-age=3600`. Used by both Livewire and plain JS.

### 4.3 Favourite Toggle

`POST /listings/{listing}/favourite` (`auth`) → `{ "favourited": true, "count": 12 }`

### 4.4 Report

`POST /reports` (`auth`, `verified`, `throttle:report`)
```json
{ "reportable_type": "listing", "reportable_id": 1234, "reason": "scam", "details": "Asked for advance payment" }
```
→ 201 `{ "id": 88, "status": "open" }`. Duplicate open report → 409.
> **Implementation status (T4.6):** `POST /listings/{listing}/favourite` (`auth`) → `ToggleFavourite`: saving needs a public listing (404 otherwise), removing always works; returns `{favourited, count}`. `POST /reports` (`auth`, `verified`, `throttle:report` = 10/day) → `SubmitReport`: `reportable_type` is `listing` or `user` (short stable names, never class names), the target must be a public listing that is not the reporter's own or another (not deleted) account; reasons per MOD-02, details required for `other`, plain text ≤ 1,000; Turnstile when configured (fails open); one open report per reporter + target, checked under a lock (409). `ResolveReport` (staff with `reports.resolve`, not about themselves or their own ads) closes a report as `actioned` or `dismissed` with an optional note and an audit entry; the action on the target itself (suspend, ban) stays separate. Account deletion now removes the user's favourites at once (retention §2).

## 5. Admin Area (`/admin`, `auth`, `verified`, `active`, `staff`)

| URI | Livewire component | Permission |
|---|---|---|
| `/admin` | `Admin\Dashboard` | access-admin |
| `/admin/moderation` | `Admin\ModerationQueue` | listings.moderate |
| `/admin/listings` | `Admin\Listings` | listings.view-any |
| `/admin/reports` | `Admin\Reports` | reports.view |
| `/admin/users` | `Admin\Users` | users.view |
| `/admin/categories` | `Admin\Categories` | categories.manage |
| `/admin/attributes` | `Admin\Attributes` | attributes.manage |
| `/admin/locations` | `Admin\Locations` | locations.manage |
| `/admin/pages` | `Admin\Pages` | pages.manage |
| `/admin/blocklist` | `Admin\Blocklist` | blocklist.manage |
| `/admin/settings` | `Admin\Settings` | settings.manage |
| `/admin/staff` | `Admin\Staff` | staff.manage |
| `/admin/audit` | `Admin\AuditLog` | audit.view |

Each component checks its permission in `mount()` **and** inside every action (`approve(id)`, `reject(id, reason)`, `suspend(id, reason)`, …). Actions write `audit_logs`.

> **Implementation status (T6.1, plain views until F5.1):** Livewire full-page components in `App\Livewire\Admin` with the `layouts.admin` layout: `/admin/moderation` (`admin.moderation`, `ModerationQueue`), `/admin/moderation/changes` (`admin.changes`, `RecentChanges`), `/admin/reports` (`admin.reports`, `ReportsQueue`). Livewire runs its **CSP-safe build** (`config/livewire.php` → `csp_safe`), so the strict CSP stays without `'unsafe-eval'`. Every component checks its permission on mount and before each action (`RequiresPermission`), in addition to the route middleware and the domain actions' own checks; the selected item id is `#[Locked]`. Moderation queue: pending ads, flagged first then oldest; filters flagged-only and country; review pane with photos, details, the screener's reasons (re-run on display) and a seller summary (member since, verified, published / rejected ads, open reports); approve, reject or ask for changes with reason templates (`lang/en/admin.php`) plus own words, through `ModerateListing`. Recent changes: `listing.price_changed` audit entries. Reports: open first (oldest first), filters by status and type, dismiss / mark actioned (`reports.resolve`) and "take the ad down and close" (`listings.suspend`, one transaction; a reason is required). Account actions (suspend, ban) arrive with the users screen in T6.2.

> **Implementation status (T6.2):** `/admin/users` (`admin.users`, `UsersAdmin`, `users.view`) and `/admin/staff` (`admin.staff`, `StaffAdmin`, `staff.manage` = Owner) both behind `password.confirm`; ban, delete, role changes and 2FA resets also re-check the 15-minute window inside the action (an expired window sends the user to confirm and changes nothing). Users: search by name, email or phone (local format such as `077…` matches the stored E.164), status filter; email and phone shown only when `UserPolicy::viewContactDetails` allows; suspend (reason, optional end date), ban, reinstate, mark email verified, delete, all through `App\Actions\Staff\ManageAccount` (policy with hierarchy and last-Owner guard, audit `account.*`, all sessions ended when access is removed, `AccountStatusChanged` email). **Ban blocklist:** `banned_identities` keeps HMAC-SHA256 hashes (app key) of the banned email and phone, never the values; registration and profile email/phone changes are refused with a neutral message; lifting the ban removes the hashes; `model:prune` deletes them after `retention.banned_identity_hash` days (730). Rotating `APP_KEY` invalidates the list. Staff: appoint a verified account by email, change or remove a role (`ChangeStaffRole`, now audited as `staff.role_changed` with before/after), reset another staff member's 2FA (`ResetStaffTwoFactor`, also used by the CLI `marketplace:reset-2fa`; audited `staff.two_factor_reset`).

> **Implementation status (T6.3–T6.5):** `/admin` is the `Dashboard` component. `/admin/categories` (`CategoriesAdmin`), `/admin/attributes` (`AttributesAdmin`), `/admin/locations` (`LocationsAdmin`) with `/admin/locations/countries` (`CountriesAdmin`; activation needs `settings.manage`) and `/admin/locations/requests` (`LocationRequestsAdmin`), `/admin/pages` (`PagesAdmin`), `/admin/blocklist` (`BlocklistAdmin`), `/admin/audit` (`AuditLogViewer`), `/admin/settings` (`SettingsAdmin`, also `password.confirm`). `/admin/listings` is not built yet. Public `/page/{slug}` (`page.show`, `PageController`) shows published pages only.

## 6. Notifications Contract (Email Templates)

| Notification | Variables |
|---|---|
| VerifyEmail, ResetPassword | Fortify defaults (own templates) |
| ListingSubmitted | title, review ETA text |
| ListingApproved | title, URL, expires_at |
| ListingRejected | title, reason, edit URL |
| ListingExpiringSoon | title, expires_at, renew URL |
| ListingExpired | title, renew URL |
| AccountSuspended / Reinstated | reason, until |

All text comes from `lang/en`.
