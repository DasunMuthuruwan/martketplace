# Phase 3 — UI/UX Design

**Status: Draft — awaiting owner review and approval.** No application code has been written. The prototype is a static design reference, not production code.

| # | Document | Contents |
|---|---|---|
| 01 | [Design system](01-design-system.md) | Principles, brand placeholders, colour tokens (light/dark) with verified WCAG contrast, typography (Geist), spacing, radius, breakpoints, icons (Lucide), motion, theming |
| 02 | [Components](02-components.md) | Component inventory: foundations, forms, navigation, overlays, listing card, detail blocks, search/filter, account/admin components, states checklist |
| 03 | [Navigation and layouts](03-navigation-and-layouts.md) | Site map, global navigation (header, category bar, mobile bottom nav), wireframes for home, search, listing detail, post wizard, my listings, admin review, auth, error pages |
| 04 | [Forms, states, accessibility](04-forms-states-accessibility.md) | Form patterns, loading/empty/error/status states, WCAG 2.2 AA checklist, microcopy rules, UI performance budget |
| 05 | [Global UX](05-global-ux.md) | **Draft (2026-10-11):** country switcher, location picker, currency-aware price input, country-change warning, global search and currency rule, card/detail location formats, admin location screens |
| — | [Prototype](prototype/index.html) | Clickable static prototype: Home, Search, Listing detail, Land listing (price per perch), Post an ad (steps 2–6), My listings, Admin review, States. Light/dark toggle. Responsive. Open the file in a browser; screens can be linked with `#home`, `#search`, `#detail`, `#land`, `#post`, `#mine`, `#admin`, `#global`, `#empty`. Global additions: header country switcher (flag), country-aware filters ("Prices in LKR"), wizard Location and Price steps with country-specific labels, currencies and land units, the country-change warning, and a mixed-country global search where price filters stay disabled until a currency is chosen. |

## How the Prototype Was Checked

- Rendered with headless Microsoft Edge at 1440 px (light and dark) and 500 px mobile (dark) on 2026-10-11. Fixed during review: mobile detail overflow, oversized photo-count icon, filter spacing, category tile list.
- Token contrast verified by script (design system §3.3).
- Not yet done (Phase 6/7, on the real build): axe accessibility scan, screen-reader pass, 320 px check, Lighthouse.

## Decisions Needed from the Owner

| # | Question | Proposal |
|---|---|---|
| Q-UX-1 | Show the Dev Talk association? | Footer text only, "A Dev Talk service" linking to dev-talk.com. No Dev Talk logo or colours. |
| Q-UX-2 | Product name and logo | Configurable `APP_NAME`. Working name "Marketplace" with a text wordmark + monogram until a name or logo is chosen. |
| Q-UX-3 | Mobile bottom navigation (Home · Search · Post · Saved · Account) | Recommended (fast posting and access to saved ads on phones) |
| Q-UX-4 | Prices on cards | Full figures (`Rs 7,850,000`), not abbreviations (`7.85M`) |
| Q-UX-5 | Typeface | Geist (self-hosted, OFL). Alternative: system UI font stack (zero font download). |
| Q-UX-6 | Card density | 2 columns on phones, 3 on tablet / desktop with filters, 4 on wide home/category pages |
