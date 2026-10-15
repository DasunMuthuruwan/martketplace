# Accessibility and Performance Audit (F6.1)

Checks against [forms, states and accessibility](04-forms-states-accessibility.md) §3 (WCAG 2.2 AA checklist) and §5 (performance budget). Part 1 was done without new tools; automated axe and Lighthouse runs are part 2 and need the tooling decision below.

Date: 2026-10-15. Build: Vite production build of `main` plus the F6.1 changes.

## 1. Performance Budget

| Item | Budget | Measured | Result |
|---|---|---|---|
| CSS (gzip) | ≤ 35 KB | 22.9 KB | Pass |
| JS (gzip, public pages) | ≤ 90 KB | 5.3 KB (`app.js`). Public pages are server-rendered and load no Livewire or Alpine; Livewire loads only on admin screens. | Pass |
| Fonts | Geist variable, Latin, ≤ 45 KB woff2, `swap`, preloaded | Latin 29.4 KB, `font-display: swap` (Fontsource). Other subsets (Latin Extended, Cyrillic, Vietnamese) load only when a page uses those characters (`unicode-range`). **Now preloaded** (Latin text font). | Pass |
| Listing card image | `sm` WebP ≤ 40 KB, lazy below the fold, width and height | WebP `sm`, width/height set. **Changed:** the first four cards of a results grid load at once (the first with `fetchpriority="high"`), the rest lazily. Before, every card was lazy, which delays the largest image above the fold. | Pass (file size depends on uploads; the encoder settings were checked in T4.3) |
| LCP image (detail page) | `lg` WebP, `fetchpriority="high"` | First gallery photo: `fetchpriority="high"`, the others lazy, width and height from the stored image | Pass |
| Lighthouse mobile | Perf ≥ 85, A11y ≥ 95, BP ≥ 95, SEO ≥ 95 | Not run yet (part 2) | Open |

## 2. WCAG 2.2 AA Checklist

| Area | How it was checked | Result |
|---|---|---|
| Structure | New `Tests\Support\AccessibilityAudit` (DOM checks) on 38 screens: public, sign-in, account, every posting step, owner preview, every admin screen. One `h1`, one `main`, `html lang`. | Pass (all 38) |
| Names and labels | Same audit: every link, button and `summary` has an accessible name; every field has a label (`for`, wrapping `label`, `aria-label` or `aria-labelledby`); no duplicate ids; `aria-labelledby` / `aria-describedby` / `aria-controls` point at real elements. | Pass. The checker has its own unit test proving it reports each kind of problem. |
| Images | Same audit: every `img` has `alt`. Gallery photos: "Title – photo n of m". | Pass |
| Focus | 2 px `primary` ring with 2 px offset on `:focus-visible` (global). | Pass |
| Focus not hidden (2.4.11) | `scroll-padding-top` for the sticky header existed. **Added** `scroll-padding-bottom` on phones for the bottom navigation and the posting / listing action bars. | Fixed |
| Reflow 320 px (1.4.10) | 18 rendered pages measured in a 320 px viewport (script reports the scroll width and the overflowing elements). | **Fixed:** account pages were 2 px too wide (header) and admin pages 163 px too wide (admin header). The wordmark now shortens instead of overflowing; on narrow screens the admin header shows "View site" as an icon (text kept for screen readers), hides the Admin badge and moves the theme choice into the menu drawer. After the fix: no horizontal scrolling on any measured page. Wide admin data tables scroll inside their own box, which 1.4.10 allows for tables. |
| 200 % zoom | A 1280 px window at 200 % is a 640 px viewport; covered by the 320 px check and the existing phone layouts. | Pass (by the reflow check) |
| Target size (2.5.8) | Search for interactive elements below 24 px. Smallest controls: 28 px (locality chip buttons); buttons `sm` are 32 px; primary mobile actions 40–44 px. | Pass |
| Motion | Global `prefers-reduced-motion` rule (animations, transitions, smooth scrolling). Gallery JavaScript scroll uses `auto` when reduced motion is set. No auto-advancing carousels. | Pass |
| Dragging (2.5.7) | Photo order and category order use buttons (earlier / later, up / down). No drag-only actions. | Pass |
| Keyboard | Admin queues: A / R / J / K / Esc shortcuts, ignored while typing and with modifier keys. Dialogs: native `<dialog>`. | Pass (code review). A manual keyboard walk-through is still to do. |
| Other fixes | The gallery zoom image had `src=""` (some browsers request the page again); it now has no `src` until a photo is opened. Brand logo images got a height. | Fixed |

## 3. Still Open

| Item | Needs |
|---|---|
| Automated axe scan (contrast in context, ARIA rules) | A browser test tool. D-1 (Playwright vs Dusk) is open; the plan recommends Playwright with `@axe-core/playwright`. |
| Lighthouse mobile scores | The `lighthouse` npm package (dev dependency or `npx`), run against a local production build. |
| Manual pass | Keyboard and screen reader (NVDA + Chrome, TalkBack) on the key flows: search, listing, contact, post an ad, moderation. |

These are proposed for F6.1 part 2 once the tooling is approved; the manual pass belongs to Phase 7 QA.
