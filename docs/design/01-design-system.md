# Design System

| Field | Value |
|---|---|
| SDLC phase | 3 — UI/UX design |
| Status | **Draft — awaiting owner review** |
| Direction | Premium **White + Black**, modern marketplace portal, light and dark themes (CLAUDE.md). **Not** based on the Dev Talk blog theme (ADR-013). |
| Implementation target | Tailwind CSS 4 `@theme` tokens + CSS variables, Blade components, Livewire 4 + Alpine (ADR-002) |

## 1. Design Principles

1. **Listings first.** Photos, price and key facts carry the page. Chrome stays quiet: monochrome UI, no decorative colour.
2. **Trust through clarity.** Full prices, clear seller status, visible safety tips, honest states (pending, sold, expired).
3. **Fast on mobile.** Mobile-first layouts, thumb-reachable actions, light pages (WebP, no heavy JS).
4. **Controlled density.** Compact cards and tight, consistent spacing. Whitespace separates groups. Padding is never used as decoration.
5. **One system.** Every screen (public, account, admin) uses the same tokens and components. No one-off styles.
6. **Accessible by default.** WCAG 2.2 AA: contrast verified (§3.3), visible focus, labelled controls, motion respects user settings.

## 2. Brand Placeholders

| Item | Proposal | Status |
|---|---|---|
| Product name | Configurable `APP_NAME`. Working name in designs: **"Marketplace"** | [OPEN Q-UX-2] owner to choose a name |
| Wordmark | Text wordmark in Geist Semibold with a small square monogram (black on light, white on dark). Easy to replace with a real logo later. | [RECOMMENDED] |
| Dev Talk association | Optional footer line "A Dev Talk service" linking to `dev-talk.com`, with no Dev Talk logo or colours in the UI | [OPEN Q-UX-1] |
| Favicon / app icons | Monogram on black square (generated in Phase 6) | [RECOMMENDED] |

## 3. Colour Tokens

Defined once as CSS variables (`:root` = light, `.dark` = dark) and exposed to Tailwind through `@theme inline` (e.g. `bg-surface`, `text-fg-muted`, `border-line`). Components never use raw hex values.

### 3.1 Light Theme

| Token | Value | Use |
|---|---|---|
| `--canvas` | `#F7F7F7` | Page background |
| `--surface` | `#FFFFFF` | Cards, panels, inputs, header |
| `--surface-muted` | `#F0F0F0` | Table headers, chips, wells, skeletons |
| `--surface-hover` | `#F0F0F0` | Hovered rows and menu items |
| `--line` | `#E5E5E5` | Dividers, card borders (decorative) |
| `--control` | `#858585` | Input, select, checkbox borders (UI contrast ≥ 3:1) |
| `--fg` | `#0A0A0A` | Primary text, prices, headings |
| `--fg-muted` | `#4D4D4D` | Secondary text, attribute values |
| `--fg-subtle` | `#666666` | Meta (location, time), placeholders |
| `--primary` | `#0A0A0A` | Primary buttons, active states, focus ring |
| `--primary-fg` | `#FFFFFF` | Text on primary |
| `--primary-hover` | `#262626` | |
| `--success` | `#137333` | Approved, verified, success messages |
| `--danger` | `#C5221F` | Errors, destructive actions, rejected |
| `--warning` | `#8A5300` | Pending review, expiring soon |
| `--info` | `#1A56DB` | Informational notices, links in long text |
| `--overlay` | `rgb(10 10 10 / 0.5)` | Modal/drawer backdrop |
| `--whatsapp` | `#25D366` (icon only) + `#0A0A0A` text | WhatsApp button icon; the button itself stays monochrome |

### 3.2 Dark Theme

| Token | Value |
|---|---|
| `--canvas` | `#0A0A0A` |
| `--surface` | `#141414` |
| `--surface-muted` | `#1C1C1C` |
| `--surface-hover` | `#1F1F1F` |
| `--line` | `#2A2A2A` |
| `--control` | `#6E6E6E` |
| `--fg` | `#F5F5F5` |
| `--fg-muted` | `#B3B3B3` |
| `--fg-subtle` | `#949494` |
| `--primary` | `#F5F5F5` (white buttons, black text) |
| `--primary-fg` | `#0A0A0A` |
| `--primary-hover` | `#D9D9D9` |
| `--success` / `--danger` / `--warning` / `--info` | `#4CC38A` / `#FF7A70` / `#F5B83D` / `#7EA6FF` |
| `--overlay` | `rgb(0 0 0 / 0.7)` |

### 3.3 Verified Contrast (WCAG 2.2) [VERIFIED by script, 2026-10-11]

| Pair | Light | Dark | Requirement |
|---|---|---|---|
| `fg` on canvas / surface / muted | 18.5 / 19.8 / 17.4 | 18.2 / 16.9 / 15.6 | ≥ 4.5 text ✔ |
| `fg-muted` on canvas / surface / muted | 7.9 / 8.5 / 7.4 | 9.4 / 8.8 / 8.1 | ✔ |
| `fg-subtle` on canvas / surface / muted | 5.4 / 5.7 / 5.0 | 6.5 / 6.1 / 5.6 | ✔ |
| success / danger / warning / info (worst case, on muted) | 5.2 / 5.1 / 5.6 / 5.4 | 7.7 / 6.7 / 9.6 / 7.1 | ✔ |
| `control` border on canvas / surface / muted | 3.4 / 3.7 / 3.2 | 3.9 / 3.6 / 3.3 | ≥ 3 for UI components ✔ |
| `primary-fg` on `primary` | 19.8 | 18.2 | ✔ |
| `line` | 1.2–1.3 | 1.2–1.4 | Decorative only. Never the sole indicator of a control or state. |

Rules: status is never shown by colour alone (always text and/or icon). Links in body text are underlined.

## 4. Typography

| Item | Value |
|---|---|
| UI typeface | **Geist** variable (OFL-1.1, self-hosted via `@fontsource-variable/geist`, no Google Fonts request) |
| Numbers | `font-variant-numeric: tabular-nums` for prices, counts and tables |
| Monospace (rare: IDs, codes in admin) | Geist Mono (OFL-1.1) |
| Future Sinhala / Tamil | Fallback stack `"Geist", "Noto Sans Sinhala", "Noto Sans Tamil", system-ui, sans-serif`. Noto fonts (OFL) are loaded only when those locales ship. Line heights below already allow taller scripts. |

| Style | Size / line height | Weight | Use |
|---|---|---|---|
| Display | 32 / 40 (mobile 26 / 32) | 650 | Home hero heading only |
| H1 | 26 / 32 (mobile 22 / 28) | 650 | Page titles, listing title |
| H2 | 20 / 28 | 600 | Section titles |
| H3 | 16 / 24 | 600 | Card group titles, panel headings |
| Price XL | 28 / 34 | 700 tabular | Listing detail price |
| Price | 16 / 22 | 700 tabular | Card price |
| Body | 15 / 24 | 400 | Descriptions, paragraphs |
| UI | 14 / 20 | 450–500 | Buttons, inputs (16px on mobile inputs to prevent iOS zoom), menus |
| Small | 13 / 18 | 400 | Meta, hints, attribute chips |
| Micro | 12 / 16 | 500 | Badges, table headers (uppercase only in badges) |

## 5. Spacing, Sizing and Shape

| Token | Value |
|---|---|
| Spacing scale (4 px base) | 2, 4, 8, 12, 16, 20, 24, 32, 40, 48, 64 |
| Card padding | 12 (cards) · 16 (panels) · 20 (forms on desktop) |
| Section gap | 24 mobile · 40 desktop |
| Grid gap | 12 mobile · 16 desktop |
| Control height | 40 default · 36 compact (admin tables) · 44 mobile primary actions |
| Touch targets | ≥ 44 × 44 for primary mobile actions. ≥ 24 × 24 minimum everywhere (WCAG 2.5.8). |
| Radius | 6 controls · 10 cards/panels · 14 modals/sheets · full for pills/avatars |
| Borders | 1 px `line` for cards. 1 px `control` for inputs. 2 px `primary` focus ring with 2 px offset. |
| Elevation | Borders, not shadows. One shadow for overlays only: `0 12px 32px -8px rgb(0 0 0 / .18)` (light), `0 16px 40px -8px rgb(0 0 0 / .7)` (dark). |
| Image ratios | Listing card 4:3 · gallery 4:3 (contain on dark backdrop for odd ratios) · avatar 1:1 · category tile icon 24 px |

## 6. Layout and Breakpoints

| Breakpoint | Min width | Layout |
|---|---|---|
| base | 0 | 1 column, 16 px gutters, bottom navigation bar |
| `sm` | 640 | 2-column card grid stays, wider forms |
| `md` | 768 | 3-column cards, filter drawer still used |
| `lg` | 1024 | Filter sidebar (272 px) + 3-column results. Header search inline. No bottom nav. |
| `xl` | 1280 | 4-column cards on home/category pages. Container max 1240 px, 24 px gutters. |

- Container: `max-width: 1240px; padding-inline: 16px (base) / 24px (lg+)`.
- Card grid: 2 columns from 360 px (compact cards), 3 at `md`, 4 at `xl` (3 when the filter sidebar is shown).
- The page never scrolls horizontally. Long words and URLs wrap (`overflow-wrap: anywhere`) in user content.

## 7. Iconography

- **Lucide** (ISC licence), inline SVG through one `<x-ui.icon name="…">` component with a registry. 1.75 px stroke, 16/20/24 px sizes, `currentColor`.
- Category icons come from the same set (car, home, land-plot, wrench, smartphone, laptop, sofa, package).
- Icon-only buttons always have an accessible label (`aria-label`) and a tooltip on desktop.

## 8. Motion

- Durations: 120 ms (hover, press), 180 ms (menus, drawers), 220 ms (modals). Easing `cubic-bezier(.2,.8,.2,1)`.
- Only transform/opacity transitions. No parallax, autoplaying carousels or decorative animation.
- `prefers-reduced-motion: reduce` turns all transitions into instant changes.
- Skeleton shimmer is replaced by a static tone under reduced motion.

## 9. Theming Behaviour

- Default follows the OS (`prefers-color-scheme`). A toggle in the header/account menu offers Light / Dark / System.
- The choice is stored in `localStorage` (per-browser convenience). An inline script in `<head>` applies the `dark` class **before first paint** (no flash).
- Listing photos are not altered in dark mode. Card borders are slightly stronger to separate photos from the canvas.
- Emails are always light (email-client support).

## 10. Implementation Notes (for Phases 5–6)

- `resources/css/app.css`: tokens → `@theme inline` mappings. Component classes (`.btn`, `.input`, `.card`, …) built with `@apply` only where Blade components need them.
- Blade components in `resources/views/components/ui/*`. Domain components in `components/listing/*`, `components/admin/*`.
- Fonts and icons are bundled by Vite. No external font or icon CDN (privacy, performance, CSP).
- A living style-guide page (`/admin/design-system`, Owner only) renders every component in both themes for visual regression checks.
