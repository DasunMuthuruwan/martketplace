# Forms, UI States, Accessibility and Content

| Field | Value |
|---|---|
| Status | **Draft — awaiting owner review** |

## 1. Form Patterns

| Rule | Detail |
|---|---|
| Labels | Always visible above the control. Placeholders are examples only ("e.g. Toyota Aqua 2015"), never labels. |
| Required vs optional | Mark required with `*` and add a legend at the top of the form ("* required"). Optional fields can be marked "(optional)" where most fields are required. |
| Validation timing | On blur for format errors (email, phone), on Continue/Submit for completeness. **The server is the source of truth**: every rule is re-checked server-side and errors are mapped to fields. |
| Error display | Red text + icon under the field, `aria-invalid`, `aria-describedby`. After submit: error summary at the top with links, focus moved to it. |
| Messages | Say what is wrong and how to fix it: "Enter a mileage between 0 and 1,000,000 km", not "Invalid value". |
| Input help | `inputmode` (numeric for price, mileage, phone, OTP), `autocomplete` (name, email, tel, new-password, one-time-code), digit grouping for prices while typing, unit suffixes. |
| Phone | `+94` prefix shown. Accepts `07X XXX XXXX` or `+947X…`. Normalised to E.164 on the server. |
| Long forms | Wizard steps, auto-saved drafts, "Saved ✓" status, warning before leaving with unsaved changes (step 1 only; later steps are already saved). |
| Destructive actions | Confirmation modal naming the object ("Delete 'Toyota Aqua 2015'? This can't be undone.") with a danger button |
| Submit buttons | Disabled only while submitting (loading state). Never disabled to signal invalid input (users can't tell why). |
| Bot protection | Turnstile placed right above the submit button. If it fails to load: "Security check couldn't load. Check your connection and try again." |

## 2. UI States (Required on Every Data View)

| State | Pattern | Examples |
|---|---|---|
| **Loading** | Skeletons that match the final layout (cards, rows, detail blocks). Buttons show a spinner. Livewire requests > 400 ms show a 2 px top progress bar. | Search results while filtering, admin tables |
| **Empty — first use** | Friendly explanation + primary action | My listings: "You haven't posted any ads yet" [Post your first ad]. Saved: "Tap ♡ on any ad to save it here." |
| **Empty — no results** | Explain + recovery actions | Search: "No ads match these filters" + [Clear filters] + "Try a nearby city or remove a filter" + chips of removable filters |
| **Validation error** | Field errors + summary (§1) | Posting wizard |
| **Server / network error** | Inline alert with retry. Data already entered is kept. | "We couldn't save your changes. Check your connection and try again. [Retry]" |
| **Partial failure** | Per-item error | Image uploader: one image fails ("Couldn't process this image. Try a JPG or PNG under 8 MB.") while the others succeed |
| **Permission** | 403 page or inline notice. Hidden listings return 404 (not 403). | |
| **Rate limited** | Explain the wait | "Too many attempts. Try again in 5 minutes." Contact reveal: "You've viewed many numbers recently. Try again later." |
| **Status of content** | `x-listing.status-banner` | Pending review (warning), Rejected + reason (danger), Expired + Renew, Sold, Suspended |
| **Success** | Toast for quick actions. Confirmation page for big flows (listing submitted). | "Ad marked as sold" |
| **Offline** | Browser default for the MVP. Forms keep values. | — |

## 3. Accessibility Checklist (WCAG 2.2 AA)

| Area | Requirement |
|---|---|
| Contrast | Tokens verified ([design system](01-design-system.md) §3.3). Text ≥ 4.5:1, UI components and focus ≥ 3:1. |
| Keyboard | Everything reachable and operable by keyboard. Logical tab order. No keyboard traps. A visible **skip to content** link. |
| Focus | 2 px solid `primary` ring + 2 px offset on every focusable element (`:focus-visible`). Never removed. Focus is not hidden by the sticky header or bottom nav (WCAG 2.4.11: scroll padding set). |
| Structure | One `<h1>` per page, ordered headings, landmarks (`header`, `nav`, `main`, `aside`, `footer`), lists for card grids. |
| Images | Listing photos: alt = "Toyota Aqua 2015 — photo 1 of 8". Decorative icons `aria-hidden`. |
| Forms | Labels, grouped radio/checkbox sets with `fieldset/legend`, errors linked, autocomplete attributes (1.3.5). |
| Dynamic updates | Result counts, "Saved ✓" and toasts are announced with `aria-live="polite"`. Errors use `role="alert"`. |
| Dialogs | Native `<dialog>`, focus trap, Esc, focus returned to the trigger. |
| Target size | ≥ 24 × 24 everywhere. ≥ 44 × 44 for primary mobile actions. |
| Motion | `prefers-reduced-motion` respected. No auto-advancing carousels. |
| Zoom/reflow | Works at 320 px width and 200% zoom without loss of content or horizontal scrolling (1.4.10). |
| Dragging | Every drag action (image reorder, category sort) has a button alternative (2.5.7). |
| Authentication | No cognitive tests. Paste allowed in password and OTP fields. Password managers supported (3.3.8). |
| Language | `<html lang="en">`. Future `si` / `ta` per locale. |
| Testing | Automated axe checks in browser tests (Phase 7) + a manual keyboard and screen-reader pass (NVDA + Chrome, TalkBack) on key flows. |

## 4. Content and Microcopy

| Topic | Guideline |
|---|---|
| Tone | Plain, friendly, direct English. Short sentences. No jargon ("ad" in UI copy, "listing" in admin and docs). |
| Prices | Full figure everywhere (cards, detail, search, admin), never abbreviated (Q-UX-4). The listing's own currency and the country's grouping: `Rs 7,850,000`, `$7,850,000`, `₹78,50,000`. Per land unit: `Rs 250,000 / perch`. Rent: `Rs 85,000 / month`. |
| Dates | Relative in lists ("2 hours ago", "Yesterday"). Absolute on hover/detail ("11 Oct 2026, 2:30 pm"). Timezone Asia/Colombo. |
| Numbers | `85,000 km`, `1,500 cc`, `50 perches`, `2 acres (320 perches)` |
| Buttons | Verb + object: "Post ad", "Show phone number", "Mark as sold", "Approve". |
| Status names (seller-facing) | Draft, Under review, Live, Not approved, Expired, Sold, Suspended |
| Safety | Short, repeated tips: "Never pay in advance", "Meet in a safe public place", "Check documents before paying". |
| All strings | Come from `lang/en/*.php` (localisation-ready). No text baked into images. |

## 5. Performance Budget for the UI

| Item | Budget |
|---|---|
| CSS (gzip) | ≤ 35 KB |
| JS (gzip, public pages, incl. Livewire + Alpine) | ≤ 90 KB |
| Fonts | Geist variable, latin subset, ≤ 45 KB woff2, `font-display: swap`, preloaded |
| Listing card image | `sm` WebP ≤ 40 KB. Lazy loading below the fold. Explicit width/height. |
| LCP image (detail page) | `lg` WebP with `fetchpriority="high"` |
| Lighthouse mobile target | Performance ≥ 85, Accessibility ≥ 95, Best Practices ≥ 95, SEO ≥ 95 (MVP release gate) |
