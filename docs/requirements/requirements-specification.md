# Dev Talk Marketplace — Requirements Specification

| Field | Value |
|---|---|
| SDLC phase | 1 — Requirements analysis |
| Status | **v0.2 — owner answers to Q1–Q16 incorporated. Approval pending together with the Phase 2 design.** |
| Version | 0.4 (v0.3: CD approvals and portability. v0.4: global multi-country marketplace — §4.11 GLB-01–20, location/currency/phone/land-unit changes) |
| Date | 2026-10-11 |
| Working domain | **`marketplace.dev-talk.com`** — approved as the initial domain (CD-02). DNS control, SSL and subdomain setup are [UNVERIFIED]. Always configured through `APP_URL`, never hardcoded (§5.7). |

Owner decisions are recorded in §11 with their verification status. The Phase 2 design is in [`docs/architecture/`](../architecture/README.md).

---

## 1. Repository Inspection Findings

| Item | Finding |
|---|---|
| Application code | None. The repository has only `CLAUDE.md` and `docs/` (phase prompt files `01`–`10`). |
| Git | `main` branch with **no commits**. All files are untracked. |
| Framework / dependencies | None installed yet (no `composer.json`, `package.json`, `.env`). |
| `.qodo/` | Empty tool folders (`agents/`, `workflows/`). Not part of the application. |
| Local toolchain | PHP 8.2.12 (ZTS, likely XAMPP), Composer 2.8.2, Node 22.21.0, npm 10.9.4, Git 2.33. No `mysql` CLI on PATH. |
| Existing Dev Talk site | Separate repository at `C:\php8.2\htdocs\blog-site\blog` (Laravel 12, Livewire 3, Tailwind 4, cPanel on register.lk, domain `dev-talk.com`). Inspected read-only; see [Dev Talk inspection](../architecture/01-existing-devtalk-inspection.md). |

**Implications**

- This is a greenfield build, so there is no existing functionality to regress. It is still a separate application, so the main Dev Talk site must not be touched.
- The PHP version limits the Laravel version. Phase 2 recommends Laravel 13 on PHP 8.3+, because PHP 8.2 security support ends Dec 2026 (ADR-001, CD-01). Evidence suggests the host offers PHP 8.3. The local machine needs PHP 8.3+ (owner approval).
- An initial commit of the docs on a feature branch is recommended before any code is added.

---

## 2. Product Vision and Scope

A secure, fast, mobile-first classifieds marketplace where people in **multiple countries** post items for sale or rent and buyers contact sellers directly [GLOBAL 2026-10-11]. Countries, currencies and locations are configurable data. Sri Lanka is the first market but not an assumption in the design. It runs as a separate application on a Dev Talk subdomain, with its own database, `.env`, uploads directory and deployment process.

**In scope (MVP):** listing publication, browsing, search and filters, seller contact (phone and WhatsApp, seller-controlled visibility), user accounts, moderation, an admin-configurable category and attribute system, and basic reporting and abuse handling.

**Out of scope (MVP):** shopping cart, checkout, online payments, commissions, escrow, delivery or logistics, auctions or bidding, native mobile apps. Paid features are a later phase (§9).

---

## 3. Stakeholders and User Roles

| Role | Description | Key capabilities |
|---|---|---|
| **Guest** | Unauthenticated visitor | Browse, search, filter, view listings and seller profiles, see contact options if the seller allows guest visibility. |
| **Registered user** | One account acts as both buyer and seller | Everything a guest can do, plus post and manage listings, control contact visibility, save favourites, report listings, manage profile. |
| **Moderator** | Trusted staff | Review the listing queue, approve, reject or request changes, handle listing reports, suspend listings, manage the keyword blocklist. Limited user data. |
| **Admin** | Operational staff | Manage users (suspend/ban), listings, categories and attributes, locations, reports, static pages. View the audit log. |
| **Owner / Super-admin** | Project owner | Full control, including site settings, staff roles and permissions [CONFIRMED Q9]. Matrix in [authorization](../architecture/05-authorization.md). |

---

## 4. Functional Requirements

Priority uses MoSCoW: **M** = Must (MVP), **S** = Should (MVP if time allows), **C** = Could (later), **W** = Won't (this release).

### 4.1 Authentication and Accounts (AUTH)

| ID | Requirement | Pri |
|---|---|---|
| AUTH-01 | Register with name, email, password, country, and mobile number (international, with country picker, validated with libphonenumber and stored as E.164) [GLOBAL 2026-10-11]. Terms and privacy consent are required. | M |
| AUTH-02 | Email verification is required before publishing a listing, revealing contacts as a logged-in user, or reporting. | M |
| AUTH-03 | Login and logout, "remember me", and password reset by email. | M |
| AUTH-04 | Rate limiting and lockout on login, registration, password reset and verification resend. | M |
| AUTH-05 | Mobile number verification by SMS OTP. **Not in the MVP** [CONFIRMED Q7]. The schema reserves `phone_verified_at`. No paid SMS provider without approval. | C |
| AUTH-06 | Social login (Google). | C |
| AUTH-07 | Single sign-on with the main Dev Talk site. **Future enhancement** [CONFIRMED Q2]. MVP auth is fully independent (no shared users, passwords, sessions or cookies). | W |
| AUTH-08 | Users can change email, password and phone, and delete their account (data removal per §6.5). | M |
| AUTH-09 | Admin can suspend or ban accounts. Suspended users cannot log in or post, and their listings are hidden. | M |
| AUTH-10 | TOTP two-factor authentication: mandatory for Owner and Admin, optional for Moderator [APPROVED CD-11]. Recovery process in [authentication and 2FA](../architecture/12-authentication-and-2fa.md). | M |

**Acceptance criteria**

- An unverified user who tries to publish is redirected to email verification.
- The 6th failed login within 1 minute for the same email and IP gets HTTP 429.
- Password reset links are single-use and expire in 60 minutes.
- A suspended user's active session is invalidated on their next request.

### 4.2 Categories and Dynamic Attributes (CAT)

| ID | Requirement | Pri |
|---|---|---|
| CAT-01 | Hierarchical categories, up to 2 levels for the MVP (e.g. Vehicles → Cars). Each has a name, slug, icon, sort order, active flag and SEO meta. | M |
| CAT-02 | Admins define category-specific **attributes**. Types: text, number (with unit), select, multi-select, boolean, year, range-validated number. Each has required/optional, filterable, shown-on-card and sort-order settings. | M |
| CAT-03 | Subcategories inherit parent attributes and can add their own. | M |
| CAT-04 | Dependent selects, e.g. Make → Model for vehicles and phones. | S |
| CAT-05 | Category-level settings: listing type allowed (sale / rent / wanted), price required or optional or "negotiable", max images, whether moderation is required. | M |
| CAT-06 | Changing or deleting attributes must not corrupt existing listings. Attributes are deactivated, not hard-deleted, once they are in use. | M |
| CAT-07 | Seed the initial category tree and attributes (§4.2.1). | M |

#### 4.2.1 Initial Category Seed (draft — owner to confirm)

| Category | Subcategories | Example attributes |
|---|---|---|
| **Vehicles** | Cars, Vans, SUVs/Jeeps, Motorcycles, Three-wheelers, Lorries/Trucks, Buses, Heavy machinery, Bicycles | Make, Model, Year (manufacture), Year (registration), Mileage (km), Fuel (petrol/diesel/hybrid/electric), Transmission, Engine capacity (cc), Condition (brand new / used / reconditioned), Body type, Colour |
| **Property** | Land, Houses, Apartments, Commercial property, Rooms/Annexes | Listing type (sale/rent), Land size (perches/acres), Floor area (sq ft), Bedrooms, Bathrooms, Floors, Furnished, Parking, Deed type (Q11) |
| **Vehicle Parts & Accessories** | Auto parts, Motorcycle parts, Tyres & rims, Accessories, Audio & electronics | Part type, Compatible make/model, Condition, Brand |
| **Electronics** | Mobile phones, Phone accessories, Computers & laptops, Computer accessories, Tablets, TVs, Audio, Cameras, Gaming | Brand, Model, Condition, Storage, RAM, Warranty |
| **Home & Living** | Furniture, Home appliances, Kitchen items, Garden | Type, Condition, Material, Brand |
| **Fashion & Beauty** | Clothing, Shoes, Watches, Jewellery, Bags, Beauty products | Gender, Size, Condition, Brand |
| **Other** | Admin-configurable | — |

### 4.3 Listings (LST)

| ID | Requirement | Pri |
|---|---|---|
| LST-01 | Create a listing: category, title (10–100 chars), description (20–5,000 chars, plain text with line breaks and no HTML), price in the listing country's currency (ISO 4217, decimals per currency) or "negotiable" / "free" / "on request" per category, condition, location (country → region → [subregion] → city/town → optional locality) [GLOBAL 2026-10-11], category attributes, images, and contact preferences. | M |
| LST-02 | Image upload: 1–10 images per listing (admin-configurable), each JPEG/PNG/WebP ≤ 8 MB. Images are re-encoded server-side, resized (thumbnail / medium / large), EXIF stripped (removes GPS), stored as WebP, reorderable, with one cover image. | M |
| LST-03 | Save as draft, preview, then submit. | M |
| LST-04 | **Listing lifecycle:** `draft → pending_review → active → (sold \| expired \| removed_by_user)`, plus `rejected` (with reason) and `suspended` (by staff). Only `active` listings are public. | M |
| LST-05 | **At launch every new listing must be approved by a Moderator or Admin before it is public** [APPROVED CD-06]. The moderation mode stays configurable (all / new users only / none) for later relaxation by the Owner. | M |
| LST-06 | Edit a listing. Material edits (title, description, category, images, attributes, location, type) on an active listing send it back to `pending_review`. Contact toggles, sold and renew apply immediately. Price edits: immediate + moderation feed [APPROVED, Q-MOD-1]. The same ownership and permission checks apply to every update. | M |
| LST-07 | Mark as sold, deactivate, delete (soft delete) and repost or renew. | M |
| LST-08 | Automatic expiry after N days (default 60, configurable), with an email reminder 3 days before. Renewal extends expiry. | M |
| LST-09 | Per-user limits: max active listings (default 50), max new listings per day (default 10). Admin-configurable. | M |
| LST-10 | Duplicate-listing detection (same user, same title and category within 7 days) shows a warning to moderators. | S |
| LST-11 | Unique, SEO-friendly URL: `/ad/{listing-slug}-{id}` [APPROVED CD-12]. Outdated slugs 301 to the canonical URL. Canonical URLs are built from the configured `APP_URL`. Category pages `/ads/{category}/{subcategory}`. | M |
| LST-12 | View counter, de-duplicated per visitor per day and excluding the owner. | S |
| LST-13 | Listing types: For sale, For rent, Wanted (per category setting). | S |
| LST-14 | **Land price per unit** [APPROVED 2026-10-11, generalised [GLOBAL 2026-10-11]]: Land listings require a total price (> 0, fixed or negotiable) and a land size (> 0, entered in one of the listing country's units, stored as entered + m²). The price per unit (Sri Lanka: per perch; other countries per their configured unit, or none) is calculated by the system (total ÷ area, rounded per currency) and shown on cards, the detail page and Land search results. It is never entered manually, applies only to categories configured for it (Land), and is hidden when inputs are missing or invalid. Design in [category tree](../architecture/04-category-tree.md) §3.2.1. | M |

**Acceptance criteria**

- A listing missing a required category attribute is rejected server-side with field-level errors.
- A Land listing with total price Rs 12,500,000 and 50 perches shows `Rs 250,000 / perch`. The same land entered as 1 rood 10 perches (50 perches) shows the same value.
- A Land listing without a land size, with a size ≤ 0, or with price type "on request" is rejected (422). No page ever shows a per-perch value for a zero, missing or invalid area.
- A Houses listing never shows a price per perch.
- An uploaded file with a `.jpg` extension that is actually a PHP script is rejected.
- Uploaded images are never executable or served from a path where PHP runs.
- A non-owner gets HTTP 403 when trying to edit, delete or view a draft of someone else's listing.
- Expired listings disappear from search within the next scheduled run (≤ 1 hour).

### 4.4 Browse, Search and Discovery (SRCH)

| ID | Requirement | Pri |
|---|---|---|
| SRCH-01 | Home page: search bar, category grid, latest listings and featured slot placeholder (inactive until Phase 2 of the business model). | M |
| SRCH-02 | Keyword search across title and description, using MySQL FULLTEXT for the MVP. | M |
| SRCH-03 | Filters: category, country, region, subregion, city, locality (where available), price range **with a single currency**, currency, condition, and category-specific filterable attributes [GLOBAL 2026-10-11]. Each location level narrows the next. | M |
| SRCH-04 | Sort: newest (default), price low→high, price high→low. Price sort only within one currency; prices in different currencies are never compared [GLOBAL 2026-10-11]. | M |
| SRCH-05 | Paginated results (24 per page) with shareable, indexable filter URLs (query string). | M |
| SRCH-06 | Listing detail page: gallery, price, attributes, description, location, posted date, seller summary, contact actions, report button, similar listings. | M |
| SRCH-07 | Public seller profile: name, member since, verified badges, active listings. | M |
| SRCH-08 | Favourites (saved listings) for logged-in users. | M |
| SRCH-09 | Saved searches with email alerts for new matches. | C |
| SRCH-10 | Recently viewed listings (client-side). | C |
| SRCH-11 | Social share buttons (WhatsApp, Facebook, copy link). | S |

**Acceptance criteria**

- A filtered search returns only `active`, non-expired listings from non-suspended users.
- Filter combinations produce stable URLs that reproduce the same results.
- Search response time is p95 < 800 ms on the target host with 50k listings (target; to be validated).

### 4.5 Seller Contact (MSG)

[CONFIRMED Q6, Q10] Contact is by **phone call and WhatsApp only**, controlled by seller privacy settings. Internal messaging is excluded from the MVP and kept as a separate future module. No checkout, orders, escrow or commissions.

| ID | Requirement | Pri |
|---|---|---|
| MSG-01 | Account-level contact visibility: `hidden` (default), `registered` (logged-in users only) or `public`. Phone numbers stay hidden until the seller explicitly chooses. | M |
| MSG-02 | Per-listing toggles: show call, show WhatsApp. The effective visibility is the stricter of the account setting and the listing toggle. Optional separate WhatsApp number. | M |
| MSG-03 | A listing cannot be submitted unless at least one contact channel is visible to someone. The posting wizard asks the seller to choose. | M |
| MSG-04 | Phone numbers are never printed in the HTML. They are revealed with a "Show number" action (rate-limited, logged, Turnstile for guests after a threshold) to reduce scraping. | M |
| MSG-05 | The WhatsApp button opens `https://wa.me/94XXXXXXXXX` with a pre-filled message containing the listing title and URL. It follows the same visibility as the phone number. | M |
| MSG-06 | Guests see "Log in to contact seller" when visibility is `registered`. | M |
| MSG-07 | Site-wide admin switches to disable the call or WhatsApp channel. | S |
| MSG-08 | Internal messaging (conversations, inbox, email alerts, block and report). **Future module.** | W |
| MSG-09 | Real-time chat (WebSockets). | W |

**Acceptance criteria**

- With default settings, a new seller's phone number is not shown to anyone until they choose a visibility level.
- The page source of a listing never contains the seller's phone number.
- A guest calling the reveal endpoint on a `registered`-visibility listing gets 401. On a `hidden` one they get 403.
- The 11th guest reveal within an hour from the same IP gets 429.

### 4.6 Trust, Safety and Moderation (MOD)

| ID | Requirement | Pri |
|---|---|---|
| MOD-01 | Moderation queue with filters (pending, reported, rejected). Approve, reject with a reason template, or request changes. | M |
| MOD-02 | Users report listings and users with a reason (scam, prohibited item, wrong category, duplicate, offensive, sold, other) and an optional note. | M |
| MOD-03 | Prohibited items policy page and a keyword blocklist that flags or blocks listings. | M |
| MOD-04 | Staff actions (approve, reject, suspend, ban, setting changes) are written to an audit log with actor, target, action, reason and timestamp. | M |
| MOD-05 | User notifications (email and in-app) on approval, rejection with reason, suspension and expiry. | M |
| MOD-06 | Safety tips shown on listing pages and before contact (meet in public, don't pay in advance, etc.). | M |
| MOD-07 | "Verified phone" and "Verified email" badges. | S |
| MOD-08 | Image hash matching to detect re-uploaded banned images. | C |

### 4.7 User Dashboard (DASH)

| ID | Requirement | Pri |
|---|---|---|
| DASH-01 | My listings, grouped by status, with actions (edit, renew, mark sold, delete) and per-listing view counts. | M |
| DASH-02 | Contact settings (visibility, WhatsApp number). Messages inbox is deferred with MSG-08. | M |
| DASH-03 | Favourites. | M |
| DASH-04 | Profile and account settings, and notification preferences. | M |

### 4.8 Administration (ADM)

| ID | Requirement | Pri |
|---|---|---|
| ADM-01 | Admin dashboard: counts of pending listings, open reports, new users, and active listings by category. | M |
| ADM-02 | Category and attribute manager (CRUD, reorder, activate/deactivate). | M |
| ADM-03 | Location manager [GLOBAL 2026-10-11]: countries (activate, labels, currency, locale, units), regions (1–2 levels), cities, localities, location requests, merge duplicates. Seeded from GeoNames (CC BY 4.0) and curated. | M |
| ADM-04 | User manager: search, view, suspend or ban, verify, assign staff roles. | M |
| ADM-05 | Site settings: site name, logo, contact email, listing expiry days, limits, moderation mode, contact channel toggles, max images, SEO defaults. | M |
| ADM-06 | Static page manager: About, Terms, Privacy, Safety tips, Prohibited items, FAQ, Contact. | M |
| ADM-07 | Audit log viewer. | M |
| ADM-08 | Data export (CSV) of listings and users. | C |

### 4.9 SEO and Sharing (SEO)

| ID | Requirement | Pri |
|---|---|---|
| SEO-01 | Server-rendered, crawlable HTML for home, category, search and listing pages. | M |
| SEO-02 | Per-page title, meta description, canonical URL, Open Graph and Twitter cards (listing cover image). | M |
| SEO-03 | XML sitemap (categories and active listings), regenerated on a schedule, plus `robots.txt`. | M |
| SEO-04 | Structured data (`schema.org/Product`/`Offer`, `BreadcrumbList`) on listing pages. | S |
| SEO-05 | Expired or sold listing URLs return a helpful page (HTTP 410 or a soft "no longer available" page with similar items). The approach is decided in Phase 2. | M |

### 4.10 Notifications (NTF)

| ID | Requirement | Pri |
|---|---|---|
| NTF-01 | Transactional email: verification, password reset, listing approved/rejected/expiring/expired, account suspended. | M |
| NTF-02 | In-app notification list with unread badge. | S |
| NTF-03 | Email is sent through a queue processed by cron (cPanel-compatible). | M |
| NTF-04 | SMS notifications. | C |

---

### 4.11 Global Marketplace (GLB) [Owner update 2026-10-11]

| ID | Requirement | Pri |
|---|---|---|
| GLB-01 | Configurable `countries` master data (name, ISO 3166 codes, flag from ISO code, default currency, number locale, phone code, timezone, active flag). New countries are added without code changes. | M |
| GLB-02 | The Owner enables or disables countries. Inactive countries are invisible to users and can't receive new listings. | M |
| GLB-03 | ISO 4217 `currencies` with minor units, symbol override and display rules. The listing stores `price DECIMAL(18,3)` + `currency CHAR(3)`. | M |
| GLB-04 | Selecting the listing country sets its default currency automatically. The currency is clearly shown in the price input. | M |
| GLB-05 | Prices display in their original currency everywhere (cards, detail, search, favourites, dashboards, admin). Never silently converted. Future conversion is shown separately with the target currency and rate timestamp. | M |
| GLB-06 | Location hierarchy country → region (1–2 levels per country) → city/town → optional locality, with country-specific labels (state, province, county, region…). | M |
| GLB-07 | Country is mandatory. Region and city are mandatory where `require_region` / `require_city` is set (reliable data). Exceptions are documented per country. | M |
| GLB-08 | Optional locality (curated list or free text, max 80 chars). No street address is collected or shown. | M |
| GLB-09 | The listing stores all selected location IDs. Filtering uses IDs, so renamed places keep working. | M |
| GLB-10 | Dependent, searchable selectors: country → regions → cities → localities. | M |
| GLB-11 | Changing the country in the posting form warns before clearing price, currency-dependent values, incompatible units and country-scoped attributes. Nothing is reinterpreted silently. | M |
| GLB-12 | Search by country, region, subregion, city, locality. Country and location landing pages with SEO metadata. Shareable filter URLs. | M |
| GLB-13 | Price filters and price sort require one currency. Global (multi-country) search disables them until a currency is chosen. | M |
| GLB-14 | Land/area units configurable per country. Price per unit uses the country's configured unit (Sri Lanka: perch) or is hidden. | M |
| GLB-15 | Categories, attributes and options can be scoped to countries. | M |
| GLB-16 | Admin location workflow: import (GeoNames), curate, merge duplicates, deactivate (never delete when referenced), handle "town not listed" requests. | M |
| GLB-17 | International phone numbers (E.164) with country picker. WhatsApp links use the E.164 number. | M |
| GLB-18 | Per-country prohibited terms in addition to the global blocklist. | S |
| GLB-19 | Display-only currency conversion with rate source and timestamp. | C |
| GLB-20 | Interface translations per country language. English stays the only UI language unless approved. | C |

**Acceptance criteria:** see the testing scenarios in [global design](../architecture/13-global-marketplace.md) §11 (15 scenarios covering multiple countries, currencies, decimals, location chains, country changes, price filters, land units, scoping, deactivation, re-import, phones, SEO, performance and hardcoding checks).

## 5. Non-Functional Requirements

### 5.1 Performance and Scalability

| ID | Requirement |
|---|---|
| NFR-P1 | Largest Contentful Paint < 2.5 s on 4G mobile for home, search and listing pages. |
| NFR-P2 | Server response (TTFB) p95 < 500 ms for cached pages and < 800 ms for search, on the target host. |
| NFR-P3 | Images served as WebP in responsive sizes with lazy loading. Thumbnails ≤ 40 KB. |
| NFR-P4 | Database indexes on all filter, sort and foreign-key columns. No N+1 queries on list pages (verified in tests or by query logging). |
| NFR-P5 | Initial capacity target: 50,000 active listings, 20,000 users, 200 concurrent visitors on shared cPanel hosting. The design must allow moving to a VPS, Redis, or a dedicated search engine (Meilisearch) without a rewrite. |
| NFR-P6 | Caching of category trees, settings and home page fragments, using the file or database cache driver on cPanel. |

### 5.2 Security

| ID | Requirement |
|---|---|
| NFR-S1 | All input validated server-side (Laravel Form Requests). Client-side validation is for usability only. |
| NFR-S2 | Authorization enforced on the backend through Policies and Gates for every listing, report, contact reveal and admin action. |
| NFR-S3 | Protection against the OWASP Top 10: CSRF tokens, output escaping (XSS), parameterized queries (Eloquent / query builder), mass-assignment guards, secure headers (CSP, HSTS, X-Frame-Options, Referrer-Policy). |
| NFR-S4 | Secure uploads: MIME and magic-byte validation, re-encoding through GD/Imagick, random filenames, storage outside the web root or in a non-executable directory, size limits, EXIF stripping. |
| NFR-S5 | HTTPS only. Secure, HttpOnly, SameSite=Lax cookies. Session regeneration on login. Session cookie scoped to the marketplace subdomain only (host-only cookie `dtm_session`, never shared with `dev-talk.com`). |
| NFR-S6 | Passwords hashed with bcrypt or argon2. Breached-password check is optional (S). |
| NFR-S7 | Rate limiting on auth, contact reveal, listing creation, reports and search. |
| NFR-S8 | Secrets live only in `.env`, never committed. `.env.example` is provided. `APP_DEBUG=false` in production. |
| NFR-S9 | Bot protection on registration, listing submission, reports and guest contact reveals: Cloudflare Turnstile preferred [CONFIRMED Q8] plus a honeypot. Turnstile does not require Cloudflare DNS. Outbound HTTPS from the host is [UNVERIFIED]. Alternatives in [security](../architecture/06-security-and-uploads.md) §6. |
| NFR-S10 | Audit log for staff actions. Security events (failed logins, lockouts) are logged. |
| NFR-S11 | Dependencies kept up to date. `composer audit` and `npm audit` run before each release. |

### 5.3 Privacy and Compliance

| ID | Requirement |
|---|---|
| NFR-C1 | [GLOBAL 2026-10-11] Comply with the privacy law of **each enabled country** (e.g. GDPR/UK GDPR, India DPDP Act 2023, US state laws). A country is enabled only after the owner's legal check. For Sri Lanka, follow the principles of the **Personal Data Protection Act No. 9 of 2022** (lawful purpose, minimisation, consent, access, correction and erasure). Legal review is the owner's responsibility. |
| NFR-C2 | Privacy Policy and Terms of Use pages. Explicit consent at registration. |
| NFR-C3 | Phone numbers shown only according to seller preference. Email addresses are never shown publicly. |
| NFR-C4 | Account deletion removes or anonymises personal data. Listings are deleted. Retention periods are proposed in [data retention](../architecture/09-data-retention.md) (CD-07). |
| NFR-C5 | Cookie notice if analytics or marketing cookies are used. |

### 5.4 Usability and Accessibility

| ID | Requirement |
|---|---|
| NFR-U1 | Mobile-first responsive layout (≥ 320 px) and a premium White + Black visual design with light and dark themes. The theme follows the system setting and can be toggled, with the choice remembered. The marketplace has **its own modern marketplace-portal theme** and does not reuse Dev Talk's theme [CONFIRMED 2026-10-11]. |
| NFR-U2 | WCAG 2.2 AA target: colour contrast, keyboard navigation, focus states, labelled form fields, alt text for listing images (defaulting to the listing title). |
| NFR-U3 | Consistent loading, empty, validation-error and server-error states on every screen. |
| NFR-U4 | Listing creation is a guided multi-step form with saved progress (draft). |
| NFR-U5 | Language: English for the MVP [CONFIRMED Q5]. Designed for later Sinhala and Tamil: every UI string goes through Laravel localisation, and DB labels are translatable. |
| NFR-U6 | [GLOBAL 2026-10-11] Currency per listing (ISO 4217), formatted with ICU for the listing country's locale and per-currency rules (e.g. `Rs 12,500,000`, `₹1,25,00,000`, `£12,500,000`). Times stored in UTC, shown in the viewer's country timezone. Dates in `DD MMM YYYY`. |

### 5.5 Hosting and Operations (cPanel Constraints)

| ID | Requirement |
|---|---|
| NFR-O1 | Must run on cPanel shared or reseller hosting: PHP-FPM, MySQL/MariaDB, no root access, and no persistent daemons assumed. |
| NFR-O2 | Queue and scheduler run through a single cPanel cron entry at the shortest interval the host allows (every-minute cron appears to be blocked [INFERRED]), plus a web-runner middleware. Nothing user-critical waits on the queue. No Supervisor or Horizon is required. |
| NFR-O3 | The subdomain document root points to Laravel's `public/` directory only. App code sits outside the web root. |
| NFR-O4 | Own database and DB user with least privilege, separate from the Dev Talk main site. |
| NFR-O5 | Daily automated database and uploads backups, with a documented restore procedure (detailed in Phase 10). |
| NFR-O6 | Error logging to daily rotated files. Optional external error tracking (Sentry free tier) needs approval. |
| NFR-O7 | Build assets (Vite) locally or in CI, then deploy compiled assets. Node.js on the server is not assumed. |
| NFR-O8 | Zero-downtime-ish deploy procedure with maintenance mode, migration, cache rebuild and a rollback plan (Phase 9). |

### 5.6 Maintainability

| ID | Requirement |
|---|---|
| NFR-M1 | Follow Laravel conventions: Form Requests, Policies, Eloquent, Actions/Services for domain logic, Events and Notifications. |
| NFR-M2 | Automated tests: feature tests for every endpoint and authorization rule, unit tests for domain logic. Target ≥ 70% coverage on the domain and HTTP layers. |
| NFR-M3 | Code style enforced (Laravel Pint). Static analysis (Larastan level ≥ 5). |
| NFR-M4 | Reusable UI components (Blade components or framework components). One design-token source for colours, spacing and typography. |
| NFR-M5 | Architecture decisions recorded in `docs/decisions/` (ADR format). |

### 5.7 Portability and Future Migration (owner requirement, 2026-10-11)

| ID | Requirement |
|---|---|
| NFR-X1 | The domain is configured only through `APP_URL`. No hardcoded domain in code, templates, emails, JavaScript, seeders or business logic (enforced by an automated test). |
| NFR-X2 | Canonical, sitemap, Open Graph and structured-data URLs are generated from the configured domain. A path-preserving 301 strategy is documented for domain moves. |
| NFR-X3 | Session cookies are host-only for the active domain and never shared with Dev Talk by default. |
| NFR-X4 | Image storage location and public URL are configurable (local, object storage, media domain) without changing listing logic. |
| NFR-X5 | Migrations, idempotent seeders, backups and restore procedures are documented. No hosting-specific database features beyond FULLTEXT (isolated). |
| NFR-X6 | Mail, cache, queue, session and storage drivers are env-configurable for managed services later. |
| NFR-X7 | Deployment requirements, env vars, cron, permissions, SSL, DNS, backup and rollback are documented. No undocumented cPanel-only behaviour. |
| NFR-X8 | A staged scaling plan and a domain/hosting migration runbook exist ([domain migration and portability](../architecture/11-domain-migration-and-portability.md)). |

---

## 6. Key Business Rules

1. Users must be logged in and email-verified to post listings or report.
2. Only `active` listings are publicly visible. Drafts, pending, rejected and suspended listings are visible only to the owner and staff.
3. Listings expire automatically after the configured period. Sellers may renew without limit, with a cool-down that stops renewal from being used as "bumping" (renewal only allowed within 7 days of expiry; to confirm).
4. One account can hold both buyer and seller roles. There are no separate seller accounts in the MVP. A business or dealer account type is a later phase.
5. The platform does not handle payments between buyers and sellers. The Terms state that the marketplace is only a venue.
6. Staff decisions (rejections, suspensions) always include a reason that is visible to the affected user.
7. [GLOBAL 2026-10-11] Each listing has one currency, allowed for its country (MVP: the country's default currency). The seller's original price is never converted or overwritten. A price of 0 is allowed only if the category allows "free".
8. Deleting a category that has listings is blocked. It must be deactivated or its listings reassigned first.

---

## 7. MVP Definition

The MVP is releasable when a guest can find a listing and contact the seller, a registered user can publish and manage listings, and staff can moderate content and configure categories, all running securely on cPanel.

**MVP = all "M" requirements above.** In summary:

1. Accounts: registration, email verification, login, password reset, profile, account deletion, suspension.
2. Category and attribute system configurable in admin, with the seeded global category tree and country scoping. Countries, currencies, regions and cities as configurable data [GLOBAL 2026-10-11].
3. Listing create/edit/draft/submit/renew/sold/delete, secure image uploads, expiry.
4. Home, category browse, keyword search, filters, sort, listing detail, seller profile, favourites.
5. Contact: seller-controlled visibility, phone reveal, WhatsApp deep link (no internal messaging).
6. Moderation queue, reports, keyword blocklist, audit log, Owner/Admin/Moderator roles.
7. Admin: dashboard, categories, locations, users, settings, static pages.
8. SEO basics, sitemap, light/dark theme, responsive UI.
9. Cron-driven queue and scheduler, backups documented, cPanel deployment.

### 7.1 MVP-level Acceptance Criteria (release gate)

- [ ] Every "M" requirement implemented and covered by at least one passing automated test or a documented manual test.
- [ ] All authorization boundaries tested: guest vs user vs owner vs moderator vs admin.
- [ ] Security review (Phase 8) completed with no open High or Critical findings.
- [ ] Lighthouse mobile score ≥ 85 for Performance, ≥ 95 for Accessibility, SEO and Best Practices on home, search and listing pages.
- [ ] Production deployment rehearsed on a staging subdomain, with a tested restore from backup.
- [ ] Terms, Privacy, Safety and Prohibited Items pages published (owner-supplied content).

---

## 8. Constraints

- **Hosting:** cPanel (shared or reseller). No root, no guaranteed Redis or Node, cron-based background work.
- **Stack:** Laravel (PHP) required by the project instructions. The frontend stack is decided in Phase 2 and must give server-rendered, SEO-friendly pages.
- **Separation:** separate codebase, database, `.env`, uploads and deployment from the Dev Talk main site.
- **Process:** SDLC phases in order, small reviewable tasks, approval at major decision points.
- **Budget:** no paid services are configured without owner approval (SMS, CDN, error tracking, email provider).

---

## 9. Future Phases (Post-MVP Backlog)

| Item | Notes |
|---|---|
| Featured or boosted listings (paid) | Needs payment gateway(s) per market (e.g. PayHere for Sri Lanka, plus international options). Placement rules. |
| Advertisement subscriptions / dealer accounts | Shop pages, bulk listing limits, subscription billing. |
| Banner ad slots | Admin-managed ad placements. |
| Internal messaging | Separate module (conversations, inbox, alerts, block and report). |
| Saved search alerts | Email digests. |
| SMS / OTP verification | Needs provider and budget approval. |
| Sinhala / Tamil localisation | Codebase prepared from day one. |
| Real-time chat | Needs WebSocket hosting (Reverb/Pusher) beyond cPanel. |
| Dedicated search (Meilisearch) | When MySQL FULLTEXT stops being enough. |
| SSO with Dev Talk | Depends on Q2. |
| Progressive Web App / mobile app | Install prompt, push notifications. |
| Analytics dashboard for sellers | Views, contacts, conversion. |

---

## 10. Assumptions

| # | Assumption |
|---|---|
| A1 | ~~The target market is Sri Lanka.~~ **Withdrawn [GLOBAL 2026-10-11]:** multi-country marketplace. See [global design](../architecture/13-global-marketplace.md). |
| A2 | Laravel is the backend framework (per CLAUDE.md). The main Dev Talk site's stack does not constrain this app. |
| A3 | ~~Host provides PHP ≥ 8.2, …~~ **Withdrawn (owner Q4):** no hosting specification is assumed. Minimum requirements and no-SSH/no-Composer/no-Imagick alternatives are in [hosting](../architecture/08-hosting-and-deployment.md). |
| A4 | Now a confirmed decision (Q2). |
| A5 | Now a confirmed decision (Q14). Production provider [OPEN]. |
| A6 | Pre-moderation of all new listings is the default at launch and can be relaxed later (CD-06). |
| A7 | Now a confirmed decision (Q5). |
| A8 | The owner supplies legal page content (Terms, Privacy, Prohibited items). |
| A9 | Staging is available as a second subdomain (e.g. `staging-marketplace.<confirmed domain>`) on the same host. |

---

## 11. Owner Decisions on Open Questions (2026-10-11)

Status: **CONFIRMED** = owner decision · **VERIFY** = decided but depends on an unverified fact · **OPEN** = still needs a decision. Source: owner answers in `docs/02-system-design.md`.

| # | Topic | Owner decision | Status | Remaining action / unverified detail |
|---|---|---|---|---|
| Q1 | Subdomain | `marketplace.dev-talk.lk` proposed for planning only. No DNS changes. | VERIFY | The domain on record is **`dev-talk.com`** (register.lk invoice), not `.lk`. Owner to confirm ownership and the final host name (CD-02). |
| Q2 | Dev Talk integration | Independent registration and auth. Nothing shared with the blog. SSO is future. | CONFIRMED | Blog inspected (Laravel 12, custom auth, host-only `dev_talk_session` cookie). See [Dev Talk inspection](../architecture/01-existing-devtalk-inspection.md). |
| Q3 | cPanel account | Separate subdomain + separate DB. Same account or not is undecided. | OPEN (CD-03) | Both options documented in [hosting](../architecture/08-hosting-and-deployment.md) §4. Verify account resource limits. |
| Q4 | Hosting specs | Nothing assumed. Document minimums and alternatives. | VERIFY | Evidence of PHP 8.3 (`ea-php83`) and LiteSpeed on Dev Talk's account. SSH, Composer, Imagick, DB version, inodes, cron interval are unverified. Laravel version depends on this (CD-01). |
| Q5 | Languages | English MVP, localisation-ready for Sinhala/Tamil. | CONFIRMED | — |
| Q6 | Phone visibility | Seller chooses public or logged-in only. Hidden by default until chosen. WhatsApp follows privacy settings. | CONFIRMED | Implemented as MSG-01 to MSG-06. |
| Q7 | Phone OTP | Not in the MVP. Email verification + anti-spam. Design for later. | CONFIRMED | `phone_verified_at` reserved. |
| Q8 | Bot protection | Prefer Cloudflare Turnstile if supported. | VERIFY | Turnstile does not need Cloudflare DNS. Verify outbound HTTPS from the host. Alternatives documented. |
| Q9 | Staff roles | Owner/Super-admin, Admin, Moderator, permission-based, server-enforced. | CONFIRMED | Matrix in [authorization](../architecture/05-authorization.md) for review. |
| Q10 | Messaging | Excluded from the MVP. Phone + WhatsApp only. Future separate module. | CONFIRMED | — |
| Q11 | Categories | Configurable categories and attributes. No Jobs/Services/Animals without approval. | OPEN (CD-08) | Proposed tree in [category tree](../architecture/04-category-tree.md) awaits approval. |
| Q12 | Retention | Propose a configurable policy and flag periods for approval. | OPEN (CD-07) | Proposal in [data retention](../architecture/09-data-retention.md). |
| Q13 | Frontend | Blade + Livewire unless constraints justify otherwise. | CONFIRMED | Dev Talk proves Livewire + Tailwind on the same host. |
| Q14 | Email | Host SMTP for development. Evaluate a dedicated provider for production. | VERIFY / OPEN (CD-09) | Host limit appears to be 90 emails/hour [INFERRED]. Brevo recommended for evaluation. |
| Q15 | Brand | Premium White + Black, light/dark. Inspect Dev Talk assets before creating new ones. **Update 2026-10-11: do not reuse the Dev Talk theme; use a modern marketplace-portal theme.** | CONFIRMED | Dev Talk logo files found in `D:\Site logo\`. Whether to show Dev Talk branding on the marketplace is open (Q-UX-1, Phase 3). |
| Q16 | Volume | Not confirmed. Propose a small-marketplace plan with stated assumptions. | CONFIRMED (no target) | Sizing assumptions in [architecture](../architecture/02-system-architecture.md) §7 and [hosting](../architecture/08-hosting-and-deployment.md) §3 are planning aids only. |

New critical decisions raised by Phase 2 (CD-01 to CD-12) are listed in [architecture §12](../architecture/02-system-architecture.md#12-critical-decisions-needing-approval).

## 12. Risks

| # | Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| R1 | **Shared cPanel limits** (CPU/RAM/inodes/processes) cause slowness under load or with image processing. | Med | High | Queue image resizing, cap upload sizes, cache aggressively, monitor resources, keep a VPS migration path. |
| R2 | **No persistent workers** delay emails and image jobs. | Med | Med | Cron every minute with `--stop-when-empty`. Process critical images synchronously with a fallback. |
| R3 | **Spam, scams and fraudulent listings** damage trust. | High | High | Pre-moderation, email (and optionally phone) verification, rate limits, CAPTCHA, keyword blocklist, reports, safety tips. |
| R4 | **Phone number scraping** by bots. | High | Med | Hidden by default, reveal-on-click, rate limiting, login-gated option, no numbers in HTML. |
| R5 | **Malicious file uploads.** | Med | Critical | Strict validation, re-encoding, non-executable storage, random names. |
| R6 | **PHP version mismatch** between local (8.2) and production. | Med | Med | Confirm Q4 first and pin the Laravel version and `config.platform.php` in Composer. |
| R7 | **Scope creep** (payments, real-time chat, multi-language) delays the MVP. | High | Med | Strict MoSCoW, owner approval for scope changes. |
| R8 | **Dynamic attribute filtering** (EAV) gets slow at scale. | Med | Med | Phase 2 design: typed value columns with indexes, or JSON with generated indexed columns for hot filters. Load-test with seeded data. |
| R9 | **Legal and privacy exposure** (PDPA, prohibited goods, defamation in listings). | Med | High | Terms, privacy policy, prohibited items list, moderation, takedown process, owner legal review. |
| R10 | **Email deliverability** (host SMTP flagged as spam). | Med | Med | SPF/DKIM/DMARC records, reputable provider (Q14). |
| R11 | **Accidental impact on the main Dev Talk site** (shared account, DNS, cookies). | Low | High | Separate DB and user, separate document root, subdomain-scoped cookies, no DNS changes without approval. |
| R12 | **Search quality** with MySQL FULLTEXT (minimum word length, stopwords, Sinhala text). | Med | Med | Configure FULLTEXT parameters if possible, fall back to `LIKE` for short terms, and plan a Meilisearch upgrade path. |
| R13 | **Data loss** with no tested backups. | Low | Critical | Daily backups plus off-site copy, and restore drill before launch. |
| R14 | **Framework/runtime end of life:** PHP 8.2 security support ends Dec 2026, Laravel 12 security fixes end Feb 2027. | High | High | Laravel 13 on PHP 8.3+ (CD-01). Confirm the host's MultiPHP options first. |
| R15 | **Inode and disk quota** on shared hosting, especially if shared with Dev Talk. | Med | High | 2 image variants, retention purge, quota monitoring, object-storage path. |
| R16 | **Cron restriction** (no every-minute cron) delays emails and expiry. | High | Med | Query-time expiry, synchronous images, web-runner middleware. |
| R17 | **Domain mismatch:** the proposed `.lk` domain is not the registered `dev-talk.com`. | Med | Med | Confirm the domain before any config, SEO or deployment (CD-02). |
| R18 | **Unverified phone numbers** (no OTP) let scammers post fake contacts. | Med | Med | Email verification, pre-moderation, reports, bans with hashed phone blocklist, OTP as a future option. |

---

## 13. Glossary

| Term | Meaning |
|---|---|
| Listing | An advertisement for an item or property posted by a user. |
| Attribute | A category-specific field (e.g. Mileage for cars). |
| Pre-moderation | Listings are reviewed before becoming public. |
| Contact reveal | The action of displaying a seller's phone number on request. |
| MVP | Minimum viable product: all "Must" requirements. |

---

## 14. Approval

| Role | Name | Decision | Date |
|---|---|---|---|
| Project owner | | ☐ Approved ☐ Approved with changes ☐ Rejected | |

After approval, Phase 2 (system architecture and technical design) begins per `docs/02-system-design.md`.
