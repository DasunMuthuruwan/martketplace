# UI improvement plan (U2): implementation of the UI/UX improvement guide

Source: `docs/implementation/marketplace-ui-improvement-guide.md` (owner, 2026-11-04). Builds on U1 (ADR-028 palette,
branch `feature/U1-theme-and-shared-ui`, not merged). Every point below was checked against the code on 2026-11-04.

## 1. What inspection found

| Guide item | Current code | Gap |
|---|---|---|
| Country flow `/lk` | `routes/web.php` `{cc}` group → `ResolveCountry` middleware → `CountryContext` → every public query filters `country_id` (home, search, similar, top ads) | None. Keep as is. |
| Header (§4.1) | `partials/layout/header.blade.php`: sticky, logo, search, country switcher (flag; name at xl), Saved, Post ad, account | Country name hidden below 1280 px; category bar highlights only an exact URL, not a subcategory page. No notifications feature exists, so none is added. |
| Hero (§4.2) | `public/country.blade.php`: headline with `:country`, search, Post CTA, two decorative circles (pale in forced dark) | Replace circles with a category illustration; add category and location selectors; pill search like the header. |
| Category tiles (§4.3) | `components/category/tile.blade.php`, real country-scoped counts (`HomeController::categoryCounts`) | Phones: 2-column grid; guide asks for horizontal browsing. Visual polish. |
| Featured ads (§4.4) | Top-ad boosts exist (`TopAds`, search only); home has none | New home section from running Top-ad boosts of the country, labelled, hidden when empty. |
| Latest ads / card (§4.5, §6.7) | `components/listing/card.blade.php` used everywhere: photo, image count, full price, negotiable, title, place, time, Urgent / Top-ad badges | No favourite button on cards. |
| Safety (§4.7) | `public/partials/safety-strip.blade.php`, one line | Show the four tips with icons. |
| Ad page (§5) | Gallery (swipe, ←/→, counter, thumbnails, full-screen), contact reveal (POST, no number in HTML), save/share/WhatsApp/Facebook/report, category-schema details, show more, collapsible safety, similar ads (same country + category, public) | Sidebar 360 px (≈26 %): widen to ≈32 %. No "More from this seller" ads section (only a link). Gallery background hard-coded `#0a0a0a`. Visual polish of details table. |
| "Laravel article thumbnails" in Similar Ads (§5.12) | Query is correct. Local ads #5 and #6 (Cars, LK) have Laravel article graphics uploaded as their photos. | Data, not code. Owner deletes or edits those test ads. No code change. |
| Category page (§6) | `public/search.blade.php` + `partials/search-filters.blade.php`: title "Vehicles in Sri Lanka" (dynamic), count, branch categories, country location labels, currency label, category-schema filters, sort, grid/list, chips, clear, top ads, banner, empty state, pagination | Mobile drawer does not stop the page behind it from scrolling. Banner can be very tall. Visual polish. |
| Banner (§6.6) | `components/banner/slot.blade.php`, width/height set (no layout shift), "Advertisement" label under it | Cap the height, label above. |
| Themes (§9) | Tokens in `resources/css/app.css` (ADR-028) | Check both themes on every changed screen. |

## 2. Changes by file

### Visual only (no behaviour change)
- `resources/css/app.css`: page behind an open modal or drawer does not scroll (`html:has(dialog[open])`).
- `resources/views/public/country.blade.php`: new hero (illustration from the country's real top-level categories, aria-hidden), pill search, horizontal category strip on phones, featured section, sections order: hero → banner → categories → featured → trending → popular → latest → safety.
- `resources/views/components/category/tile.blade.php`: icon panel, count, consistent height.
- `resources/views/components/listing/card.blade.php`: favourite button (above the stretched link), "Featured" label when promoted.
- `resources/views/components/banner/slot.blade.php`: height cap with `object-contain`, label above.
- `resources/views/partials/layout/category-bar.blade.php`: active when the current page is in that category.
- `resources/views/partials/layout/country-switcher.blade.php`: country name from 1024 px.
- `resources/views/public/partials/safety-strip.blade.php`: four tips.
- `resources/views/public/listing.blade.php` and partials `listing-gallery`, `listing-seller`: ≈32 % sidebar, `bg-ink` gallery, details table, seller-ads section.
- `resources/views/public/search.blade.php`: heading area, toolbar polish.
- `lang/en/public.php`: new strings (no country, currency or unit values).

### Functional / backend (small, tested)
1. `HomeController::country`: `featured` (running Top-ad boosts of the country, public ads only, at most 8) and `regions` (top level of the country for the hero selector).
2. `SearchController::country`: `?category=slug` on `/{cc}/ads` without a path category redirects (302) to the category path, so the hero category selector works without JavaScript. Other query values kept.
3. `ListingController`: `sellerMore`: other public ads of the same seller in the same country, current ad excluded, at most 4.
4. Card favourite state: one query per page for the signed-in user's favourite ids among the cards shown (view composer), never per card. Uses the existing `listings.favourite` route and `listing.js` handler.

No schema changes, no new dependencies, no route name or URL changes.

## 3. Risks
- Card favourite button sits inside a stretched-link card: needs `relative z-10` and its own focus ring; the card stays one link.
- Featured section must not show expired or non-public ads: filtered by `ListingBoost::running()` and `Listing::public()`.
- Category redirect must not loop and must ignore unknown or inactive slugs (shows results unfiltered, as today).
- Hero illustration must not look like listings: no prices, no fake ads.

## 4. Tests
- New feature tests: featured section (shown, hidden, other country excluded, expired boost excluded); category redirect; seller-more (excludes current, other country, non-public); card favourite state for guest and signed-in user.
- Existing gate: Pint, PHPStan, SQLite and MariaDB suites, build, browser tests (axe both themes, journeys, responsive 320–1920 px). Screenshots checked by eye, light and dark.

## 5. Limitations
- The hero uses an illustration made from icons, not photos: the repository has no licensed photos, and the CSP only allows images from this site. Owner can supply licensed hero photos later.
- No notifications in the header: the feature does not exist yet.
- The list/grid choice stays in the URL (kept across pages and filters), not remembered between visits.
