# Phase 3 — UI/UX Design

**Status: Draft — awaiting owner approval of the whole design.** Q-UX-1 to Q-UX-6 were approved on 2026-10-14 (below, ADR-024). Phase 6 frontend work starts only after the design is approved. The prototype is a static design reference, not production code (it loads Geist and flag icons from a CDN for convenience; the real build bundles them).

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

## Owner Decisions (Approved 2026-10-14, ADR-024)

| # | Decision | Where it is specified |
|---|---|---|
| Q-UX-1 | Footer line only: "A Dev Talk service" linking to `https://dev-talk.com`. No Dev Talk logo, colours or other branding. The text and link are configuration (empty = hidden). | Design system §2, components §3 (footer) |
| Q-UX-2 | Product name from `APP_NAME` (the Admin → Settings site name overrides it), working name "Marketplace", text wordmark + monogram. The name is never hardcoded: titles, metadata, Open Graph, emails and admin all read the configured name. Logo files are replaceable assets. No domain purchase or final brand without approval. | Design system §2 |
| Q-UX-3 | Mobile bottom navigation: Home · Search · **Post** · Saved · Account. Post is the emphasised centre action. Sign-in where needed, accessible, safe-area aware, never covers content, hidden from 1024 px. | Components §3, navigation §2 |
| Q-UX-4 | Full prices, never abbreviated (`7.85M`). Always the listing's own currency and the country's number format; never converted. Land: total plus the price per the country's land price unit (perch in Sri Lanka). Long prices stay readable on mobile cards. | Components §5.1, global UX §5, forms §5 |
| Q-UX-5 | Geist, self-hosted after the licence (OFL-1.1) is verified, with a system UI fallback stack. One typography system for public pages, forms, account and admin. No external font requests. | Design system §4 |
| Q-UX-6 | Card columns from the width actually available: phones 2, tablets 3, desktop with filters 3, wide pages without a sidebar 4. | Design system §6 |

Note on Q-UX-4: the owner's examples are formatting illustrations. Grouping follows each country's convention, so 7,850,000 rupees in India shows as `₹78,50,000` (lakh grouping), not `₹7,850,000`.

- [06 Accessibility and performance audit](06-accessibility-performance-audit.md) (F6.1): budgets measured, WCAG checks and fixes, open items.
