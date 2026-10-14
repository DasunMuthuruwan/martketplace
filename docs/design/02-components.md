# Component Library

| Field | Value |
|---|---|
| Status | **Draft — awaiting owner review** |
| Rule | One component per pattern, reused on public, account and admin screens. New variants are added to the component, never copied. |

Notation: `<x-ui.*>` = generic Blade component, `<x-listing.*>` = marketplace domain component, **[LW]** = Livewire-driven.

## 1. Foundations

| Component | Variants / props | States | Accessibility |
|---|---|---|---|
| `x-ui.button` | `primary` (black/white), `secondary` (outlined), `ghost`, `danger`, `link`. Sizes `sm` 32, `md` 40, `lg` 44. `icon`, `block`, `loading`. | default, hover, active, focus-visible, disabled, loading (spinner + `aria-busy`, text kept for width) | `<button>` or `<a>` semantics preserved. Icon-only needs `label`. |
| `x-ui.icon` | `name`, `size` 16/20/24 | — | `aria-hidden` unless `label` given |
| `x-ui.badge` | `neutral`, `success`, `warning`, `danger`, `info`, `outline`. Optional icon. | — | Text always present (no colour-only meaning) |
| `x-ui.chip` | `filter` (removable ×), `choice` (toggle), `static` | selected, focus | Removable chip: button with "Remove filter: Toyota" |
| `x-ui.avatar` | image or initials, sizes 24/32/40/64 | missing image → initials | `alt` = name |
| `x-ui.spinner` / `x-ui.skeleton` | line, block, card | reduced-motion static | `aria-hidden`. The parent region gets `aria-busy`. |
| `x-ui.divider` | horizontal, with label | — | `role="separator"` |

## 2. Forms

| Component | Notes |
|---|---|
| `x-ui.field` | Wraps a label (always visible), control, hint, error. Error text red with an icon, linked via `aria-describedby`. Sets `aria-invalid`. Required marker "*" plus "(required)" for screen readers. |
| `x-ui.input` | Text, email, tel, number, search, password (show/hide toggle). Prefix/suffix slots (`Rs`, `km`). |
| `x-ui.textarea` | Auto-grow, character counter (`120 / 5000`, announced politely near the limit) |
| `x-ui.select` | Native `<select>` for short lists (best on mobile) |
| `x-ui.combobox` [LW] | Searchable select for long or dependent lists (Make → Model, District → City). Keyboard: ↑ ↓ Enter Esc, type-ahead, "Other…" option opens a text input. ARIA 1.2 combobox pattern. |
| `x-ui.checkbox`, `x-ui.radio`, `x-ui.radio-cards` | Radio cards for big choices (listing type, condition) |
| `x-ui.switch` | Settings toggles (contact channels). `role="switch"`. |
| `x-ui.range` | Min/max pair inputs with presets (price, year, mileage). No sliders (inaccessible, imprecise). |
| `x-listing.price-input` | `Rs` prefix, digit grouping while typing, price-type radio (fixed/negotiable/free/on request, filtered per category), rent period when type = rent |
| `x-listing.area-input` | Number + unit select (perches / roods / acres). Live helper text "= 50 perches". Land only shows the calculated "≈ Rs 250,000 / perch" preview (ADR-018). |
| `x-listing.image-uploader` [LW] | Drop zone + "Add photos" button (camera on mobile). Per-file progress, error and retry. Thumbnails with drag-and-drop **and** "Move left/right" buttons (keyboard accessible). "Set as cover", "Remove". Counter `5 / 8`. Server errors per file (§ states). |
| `x-ui.form-errors` | Error summary at the top of long forms after submit. Links jump to fields. Receives focus. |
| `x-ui.turnstile` | Wrapper for the Turnstile widget. Graceful fallback message if it fails to load. |

## 3. Navigation

| Component | Notes |
|---|---|
| `x-layout.header` | Logo · search (keyword + location) · Post ad · favourites · account menu · theme toggle. Sticky, 64 px desktop / 56 px mobile. |
| `x-layout.category-bar` | Horizontal list of top-level categories under the header (desktop). Scrollable chips on mobile home. |
| `x-layout.bottom-nav` | **Approved Q-UX-3.** Mobile and tablet only (hidden at ≥ 1024 px). See §3.1. |
| `x-ui.breadcrumbs` | Home › Vehicles › Cars › listing. Schema.org BreadcrumbList on public pages. |
| `x-ui.tabs` | Underline tabs (My listings statuses, admin sections). Arrow-key navigation. |
| `x-ui.pagination` | Prev / numbers / Next with total. "Page 2 of 14". Mobile: Prev / Next + page select. |
| `x-ui.dropdown` | Menus (account, sort, row actions). Menu button pattern. |
| `x-layout.footer` | Category links, help/safety/terms/privacy (published pages), theme toggle, language placeholder (hidden until locales ship), © with the configured site name, and "A Dev Talk service" from config (Q-UX-1; hidden when the URL is empty). |
| `x-layout.admin-sidebar` | Grouped admin navigation with badges (pending count, open reports). Collapsible on tablet, drawer on mobile. |

### 3.1 Bottom Navigation (`x-layout.bottom-nav`)

```
┌────────────────────────────────────────────┐
│  ⌂        ⌕       ( + )       ♡        ◯   │  56 px + safe area
│ Home    Search    Post      Saved   Account │
└────────────────────────────────────────────┘
```

| Item | Destination | Guest |
|---|---|---|
| Home | Visitor's country home (`/{cc}`), or `/` | Allowed |
| Search | Opens the search sheet (keyword, category, location); from there the results page | Allowed |
| **Post** | Posting wizard | Login/register, then back to the wizard (`url.intended`). Unverified email: the verify screen. |
| Saved | Saved ads | Login, then back |
| Account | Account overview (my ads, settings, admin link for staff) | Login/register page |

- **Post** is the centre item: a 44 px filled circle (`primary` background, `primary-fg` icon) raised slightly above the bar, with the visible label "Post". It is the only filled item.
- Every item has a visible text label under the icon (icons never stand alone), a ≥ 44 × 44 target, and `aria-current="page"` on the active item. The bar is a `<nav aria-label="Main">` landmark.
- Safe areas: `padding-bottom: env(safe-area-inset-bottom)` (viewport meta `viewport-fit=cover`). Pages reserve the space with `padding-bottom: calc(56px + env(safe-area-inset-bottom) + 16px)` on `<main>`, so the last content, toasts and focus outlines are never hidden (WCAG 2.4.11).
- Hidden at ≥ 1024 px (the header carries these actions), in the posting wizard and other step-by-step forms, and while the on-screen keyboard is open. On listing detail pages the sticky contact bar replaces it.
- No badge counts in the MVP (no messaging yet).

## 4. Overlays and Feedback

| Component | Notes |
|---|---|
| `x-ui.modal` | Native `<dialog>`, focus trap, Esc closes, returns focus to the trigger. Sizes sm/md/lg. Used for confirmations, reject-with-reason. |
| `x-ui.drawer` / sheet | Mobile filters (full height), sort sheet (bottom). Same focus rules. |
| `x-ui.toast` | Bottom-centre (mobile) / bottom-right (desktop). Success, error, info. Auto-dismiss 5 s (errors stay until closed). `role="status"` or `alert`. |
| `x-ui.alert` | Inline banners: info, success, warning, danger. Optional action. Used for listing status ("Pending review"), account notices. |
| `x-ui.confirm` | `confirmAction({title, message, confirmText, tone})` helper on top of `x-ui.modal` |
| `x-ui.empty-state` | Icon, title, one sentence, primary action. Variants per context (§ states doc). |
| `x-ui.tooltip` | Desktop hover/focus only. Never the only place for important info. |

## 5. Marketplace Components

### 5.1 `x-listing.card` (Grid Variant — Default)

```
┌──────────────────────────┐
│ [photo 4:3]          ♡   │  favourite toggle (top-right, 40px target)
│ 8 photos ▢               │  photo count (bottom-left)
├──────────────────────────┤
│ Rs 7,850,000             │  price (16/700 tabular)
│ Toyota Aqua 2015 G Grade │  title (2-line clamp)
│ 2015 · 85,000 km · Hybrid│  up to 3 show_on_card attributes
│ Colombo 05 · 2h ago      │  location · relative time (subtle)
└──────────────────────────┘
```

| Detail | Rule |
|---|---|
| Price | **Approved Q-UX-4.** Full figure, never abbreviated (`7.85M` is not used anywhere). Formatted with the listing's stored currency and the listing country's number locale (ICU, `App\Services\Money\Money`): `Rs 7,850,000` (LK), `$7,850,000` (US), `£7,850,000` (GB), `₹78,50,000` (IN, lakh grouping). Never converted. Price types: "Negotiable" small suffix, "Free", "Price on request". Rent: `Rs 85,000 / month`. |
| Long prices | The figure never truncates or shrinks below 16 px. On narrow cards the "Negotiable" / "/ month" suffix wraps to the next line; the number itself wraps only at its own separators as a last resort. |
| Land | Total + a second line with the price per the country's land price unit, only when the country has one: `Rs 12,500,000` / `Rs 250,000 / perch · 50 perches` (LK), per acre in GB (ADR-018, ADR-021). |
| Whole card | A single link (title is the link, stretched to the card). The favourite button sits above the link layer. |
| States | default, hover (border darkens, photo zoom none), focus-visible (ring around the card), sold (grey "Sold" badge over photo), skeleton |
| Missing photo | Neutral placeholder with category icon |
| Variants | `row` (list view: photo 160 px left, details right, used in search list view and My listings), `compact` (seller profile and similar listings) |

### 5.2 Listing Detail Blocks

| Component | Notes |
|---|---|
| `x-listing.gallery` [Alpine] | Main 4:3 image, thumbnail strip, counter `3 / 8`. Swipe on touch. ← → keys. Fullscreen lightbox (dialog). Lazy loading beyond the first image. Alt = listing title + index. |
| `x-listing.price-block` | Price XL, price type, land per-perch line, "Posted 2 days ago · ID 1234" |
| `x-listing.attributes` | Two-column definition list (`<dl>`) of attributes grouped by section (Specs, Details). Booleans as "Yes/No". |
| `x-listing.description` | Plain text with preserved line breaks, "Show more" after ~12 lines |
| `x-listing.contact-panel` [LW] | Buttons: **Show phone number** (reveals → `077 123 4567` with Call link) and **WhatsApp**. States: available, login required ("Log in to contact seller"), hidden by seller (panel shows "Seller hasn't shared contact details" — such listings can't be published, but this handles edge cases), rate-limited, challenge (Turnstile). Safety tip line under the buttons. |
| `x-listing.seller-card` | Avatar, name, "Member since 2026", verified email badge, active listings count, link to seller profile |
| `x-listing.safety-tips` | Collapsible box: meet in public, inspect before paying, never pay in advance, check documents (vehicles/land deeds) |
| `x-listing.report-button` | Opens report modal (reason radios + details) |
| `x-listing.mobile-contact-bar` | Sticky bottom bar on mobile detail pages: price + [Call] [WhatsApp] |
| `x-listing.status-banner` | Owner/staff view: Pending review, Rejected (reason + Edit), Expired (Renew), Sold, Suspended |

### 5.3 Search and Discovery

| Component | Notes |
|---|---|
| `x-search.bar` | Keyword input + location combobox + Search button. Mobile: a full-screen search sheet. |
| `x-search.filters` [LW] | Category tree (current branch only), location, price range, type, condition, dynamic attribute filters from the category schema (select → checkbox list with counts later; number → range). "Clear all". Updates results and the URL. Result count announced via `aria-live`. |
| `x-search.active-filters` | Chips row with Clear all |
| `x-search.toolbar` | `1,234 ads` · sort dropdown · grid/list toggle |
| `x-category.tile` | Icon + name + optional count. Home grid (4 × 2 desktop, 4 × 2 small on mobile). |

### 5.4 Account and Admin

| Component | Notes |
|---|---|
| `x-account.listing-row` | Row card with status badge, views, expires in N days, actions menu (Edit, Renew, Mark sold, Delete) |
| `x-ui.stepper` | Posting wizard progress: numbered steps with labels, current step announced ("Step 2 of 5: Details") |
| `x-admin.stat` | Stat tile: label, number, delta/link |
| `x-ui.table` | Sortable headers, row hover, sticky header, responsive (stacked cards below `md`), bulk select where needed |
| `x-admin.review-pane` | Moderation review: images, fields, flags (keyword hits, phone in text, possible duplicate), seller summary (account age, listings, past rejections, reports), actions Approve / Reject (reason templates) / Request changes. Keyboard shortcuts A / R / J / K with a help popover. |
| `x-admin.audit-entry` | Actor, action, target, reason, time |

## 5.5 Global Components (2026-10-11)

| Component | Notes |
|---|---|
| `x-geo.country-switcher` | Header button (SVG flag + ISO code) opening a searchable dialog of active countries |
| `x-geo.location-picker` [LW] | Dependent selects/combobox for country → region → subregion → city → locality, with labels and required flags from the country |
| `x-geo.flag` | flag-icons SVG by ISO2, decorative (`aria-hidden`) next to a visible country name |
| `x-listing.price-input` (revised) | Currency code chip from the country, grouping per number locale, decimals per currency |
| `x-listing.area-input` (revised) | Units from the country, normalised to m², per-unit preview in the country's land price unit |
| `x-geo.country-change-dialog` | Lists what will be cleared before changing the country (GLB-11) |
| `x-ui.money` | Renders `Money::format()` output, adds the ISO code on cross-country pages |

## 6. Component States Checklist (Applies to All Interactive Components)

`default · hover · active · focus-visible · disabled · loading · error · empty · read-only` in **both themes**, at 320 px and 1440 px. Verified on the Owner-only style-guide page (design system §10) before each release.
