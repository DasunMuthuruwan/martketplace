# Information Architecture, Navigation and Page Layouts

| Field | Value |
|---|---|
| Status | **Draft — awaiting owner review** |
| Visual reference | Clickable prototype: [prototype/index.html](prototype/index.html) (open in a browser; static demo data) |

## 1. Site Map

```
Public                          Account (logged in)               Admin (staff)
──────                          ───────────────────               ─────────────
/                Home           /account            Overview      /admin              Dashboard
/ads             All ads        /account/listings   My listings   /admin/moderation   Review queue
/ads/{cat}       Category       /account/listings/create  Post ad /admin/listings     All listings
/ads/{cat}/{sub} Subcategory    /account/listings/{id}/edit       /admin/reports      Reports
/ad/{slug}-{id}  Listing        /account/favourites Saved         /admin/users        Users
/seller/{id}-…   Seller         /account/notifications            /admin/categories   Categories
/page/{slug}     Help/legal     /account/settings/profile         /admin/attributes   Attributes
/login /register /forgot…       /account/settings/contact         /admin/locations    Locations
/two-factor-challenge           /account/settings/security        /admin/pages        Pages
404 · 410 · 403 · 429 · 500 · maintenance                         /admin/blocklist    Blocklist
                                                                  /admin/settings     Settings (Owner)
                                                                  /admin/staff        Staff (Owner)
                                                                  /admin/audit        Audit log
```

## 2. Global Navigation

| Area | Desktop (≥ 1024) | Mobile (< 1024) |
|---|---|---|
| Header | Logo · search bar (keyword + location + button) · **Post ad** (primary) · ♡ Saved · account menu / Log in · theme toggle | Logo · search icon (opens search sheet) · account icon |
| Categories | Category bar under the header (7 top-level categories, mega-menu with subcategories on hover/focus/click) | Category chips on Home. Category list inside the search sheet. |
| Primary actions | In header | **Bottom nav**: Home · Search · Post (centre) · Saved · Account |
| Account menu | Overview, My listings, Saved, Settings, (Admin panel for staff), Theme, Log out | Account tab page with the same list |
| Footer | Categories · Help (Safety tips, Prohibited items, FAQ, Contact) · Legal (Terms, Privacy) · theme toggle · © · optional "A Dev Talk service" | Same, stacked accordions |

**Post ad entry rules:** a guest who clicks Post ad goes to login/register and returns to the wizard. An unverified user sees the "Verify your email" screen with a resend action.

## 3. Page Layouts

Wireframes use `█` for images, `[ ]` for controls, and `▸` for links.

### 3.1 Home

```
Desktop                                                    Mobile
┌────────────────────────────────────────────────────┐    ┌──────────────────────┐
│ ■ Marketplace  [ Search ads…  ][ All Sri Lanka ▾][⌕]│    │ ■ Marketplace   ⌕  ◯ │
│                         [+ Post ad]  ♡  ◯ Account  │    ├──────────────────────┤
├────────────────────────────────────────────────────┤    │ Buy & sell anything  │
│ Vehicles  Property  Parts  Phones  Electronics  …  │    │ in Sri Lanka         │
├────────────────────────────────────────────────────┤    │ [ Search ads…     ⌕ ]│
│  Buy and sell anything in Sri Lanka                │    │ [ All Sri Lanka   ▾ ]│
│  Cars, land, phones and more — contact sellers     │    ├──────────────────────┤
│  directly.                     [ Post your ad ]    │    │ ⟨chips: Vehicles …⟩  │
├────────────────────────────────────────────────────┤    │ ┌────┐┌────┐┌────┐┌──┐
│ Browse categories                                  │    │ │car ││home││land││… │ 4×2 tiles
│ ┌──────┐┌──────┐┌──────┐┌──────┐                   │    │ └────┘└────┘└────┘└──┘
│ │ 🚗   ││ 🏠   ││ 🔧   ││ 📱   │  4 × 2 tiles       │    ├──────────────────────┤
│ │Vehic.││Prop. ││Parts ││Phones│  with counts       │    │ Latest ads   ▸ See all│
│ └──────┘└──────┘└──────┘└──────┘                   │    │ ┌────────┐┌────────┐ │
├────────────────────────────────────────────────────┤    │ │████████││████████│ │ 2-col compact
│ Latest ads                               ▸ See all │    │ │Rs 7.85M││Rs 125k │ │
│ ┌──────┐┌──────┐┌──────┐┌──────┐                   │    │ └────────┘└────────┘ │
│ │██████││██████││██████││██████│  4 per row, 12    │    ├──────────────────────┤
│ │Rs …  ││Rs …  ││Rs …  ││Rs …  │                   │    │ Stay safe ▸ tips     │
│ └──────┘└──────┘└──────┘└──────┘                   │    ├──────────────────────┤
├────────────────────────────────────────────────────┤    │ ⌂   ⌕   [+]   ♡   ◯  │ bottom nav
│ Safety strip: "Never pay in advance" ▸ Safety tips │    └──────────────────────┘
├────────────────────────────────────────────────────┤
│ Footer                                             │
└────────────────────────────────────────────────────┘
```

Featured listings slot: **reserved** (hidden in the MVP; future paid feature).

### 3.2 Search / Category Results

> **Global update:** the filters gain country (global search only), region → subregion → city → locality, and the single-currency price rule. See [global UX](05-global-ux.md) §5.

```
Desktop
┌──────────────────────────────────────────────────────────────────────┐
│ Header + category bar                                                │
├──────────────────────────────────────────────────────────────────────┤
│ Home › Vehicles › Cars                                               │
│ Cars for sale in Sri Lanka                         (H1, SEO)         │
├───────────────┬──────────────────────────────────────────────────────┤
│ FILTERS  Clear│ 1,234 ads    [Sort: Newest ▾]   [▦ grid][☰ list]     │
│ Category      │ (Toyota ×) (2015–2020 ×) (Colombo ×)   Clear all     │
│  ▸ Vehicles   ├──────────────────────────────────────────────────────┤
│    • Cars     │ ┌──────┐ ┌──────┐ ┌──────┐                           │
│ Location      │ │██████│ │██████│ │██████│  3 columns                │
│ [District ▾]  │ │Rs …  │ │Rs …  │ │Rs …  │                           │
│ [City ▾]      │ └──────┘ └──────┘ └──────┘                           │
│ Price (Rs)    │ … 24 per page                                        │
│ [min] – [max] │                                                      │
│ Make [▾ comb.]│ [‹ Prev] 1 2 3 … 52 [Next ›]                         │
│ Model [▾]     │                                                      │
│ Year  [–]     │                                                      │
│ Mileage [–]   │                                                      │
│ Fuel ☐☐☐☐     │                                                      │
│ Transmission  │                                                      │
│ Condition     │                                                      │
│ (272px sticky)│                                                      │
└───────────────┴──────────────────────────────────────────────────────┘
Mobile: H1 + count · [⚙ Filters (3)] [⇅ Sort] buttons (sticky under the header) ·
2-col compact cards · filter drawer is full screen with "Show 1,234 ads" apply button.
```

Behaviour: filters apply immediately on desktop (debounced 300 ms) and on **Apply** in the mobile drawer. The URL updates (shareable). Back/forward restores the state. The result count is announced politely.

### 3.3 Listing Detail

```
Desktop                                                     Mobile
┌─────────────────────────────────────────────────────┐    ┌──────────────────────┐
│ Home › Vehicles › Cars › Toyota Aqua 2015            │    │ ‹ Back        ♡  ⇪   │
├───────────────────────────────┬─────────────────────┤    │ ████████████████████ │ swipe gallery
│ ███████████████████████████   │ Rs 7,850,000        │    │ ██████  3/8  ███████ │
│ ███████  main photo 4:3  ███  │ Negotiable          │    ├──────────────────────┤
│ ███████████████████████████   │ Toyota Aqua 2015 G  │    │ Rs 7,850,000         │
│ [▪][▪][▪][▪][▪][▪] thumbs     │ ◷ 2 days ago · ID…  │    │ Toyota Aqua 2015 G   │
├───────────────────────────────┤ ─────────────────── │    │ Colombo 05 · 2d ago  │
│ Key facts (chips)             │ [☎ Show phone number]│    ├──────────────────────┤
│ 2015 · 85,000 km · Hybrid ·   │ [◉ WhatsApp       ] │    │ Key facts            │
│ Auto · 1500cc                 │ ⓘ Never pay in      │    │ Details (dl)         │
│ Details                       │   advance           │    │ Description          │
│ Make        Toyota            │ ─────────────────── │    │ Location             │
│ Model       Aqua              │ ◯ Nimal P.          │    │ Seller card          │
│ Year        2015              │ Member since 2026   │    │ Safety tips          │
│ …                             │ ✓ Email verified    │    │ ⚑ Report             │
│ Description                   │ ▸ 12 active ads     │    │ Similar ads          │
│ (plain text, Show more)       │ ─────────────────── │    ├──────────────────────┤
│ Location: Colombo 05, Colombo │ ♡ Save   ⇪ Share    │    │ Rs 7.85M [☎][WhatsApp]│ sticky
│ Safety tips (collapsible)     │ ⚑ Report this ad    │    └──────────────────────┘
├───────────────────────────────┴─────────────────────┤
│ Similar ads (4 compact cards)                         │
└─────────────────────────────────────────────────────┘
Right panel: sticky (top 80px) on desktop.
```

Land variant of the price block: `Rs 12,500,000` / `Rs 250,000 per perch · 50 perches`.
Sold/expired: grey banner "This ad is no longer available", contact panel hidden, similar ads promoted.

### 3.4 Post an Ad (Wizard)

> **Global update (2026-10-11):** the wizard now has 6 steps: **Category → Location (country, region, [subregion], city, area) → Details → Price (currency from country, land units per country) → Photos → Contact & review**. See [global UX](05-global-ux.md) §2–§4. The wireframe below shows the earlier 5-step layout. Location fields move to their own step.

```
┌────────────────────────────────────────────────────────────┐
│ ‹ Exit (saved as draft)        Post an ad        Saved ✓ 2s │  minimal header, no bottom nav
│ ①Category ─ ②Details ─ ③Price & location ─ ④Photos ─ ⑤Contact & review │
├────────────────────────────────────────────────────────────┤
│ Step 1  What are you selling?                              │
│ [ Search categories… ]                                     │
│ ┌──────────┐┌──────────┐┌──────────┐   top-level tiles     │
│ │ Vehicles ││ Property ││ Parts    │   → then leaf list    │
│ └──────────┘└──────────┘└──────────┘                       │
│                                                            │
│ Step 2  Details      Type (•) For sale ( ) For rent ( ) Wanted
│   Title*        [                                ] 0/100   │
│   Condition*    (Brand new)(Used)(Reconditioned)  radio cards
│   Specs         Make* [Toyota ▾] Model* [Aqua ▾] Year* [2015]
│                 Mileage* [85,000] km  Fuel* [Hybrid ▾] …   │
│   Description*  [                                ] 0/5000  │
│                                                            │
│ Step 3  Price* [Rs 7,850,000] (•)Fixed ( )Negotiable       │
│         Land: Size* [50][perches ▾] = 50 perches           │
│               ≈ Rs 250,000 / perch (calculated)            │
│         District* [Colombo ▾]  City* [Colombo 05 ▾]        │
│ Step 4  Photos  [ + Add photos ]  5 / 8   drag to reorder  │
│ Step 5  Contact visibility: (•) Logged-in users ( ) Everyone
│         Show: [✓] Phone call [✓] WhatsApp   Preview card   │
│         [✓] I agree to the posting rules   [Turnstile]     │
├────────────────────────────────────────────────────────────┤
│ [‹ Back]                                  [Continue ›]     │  sticky footer (mobile)
└────────────────────────────────────────────────────────────┘
After submit: confirmation screen "Submitted for review — usually within 24 hours"
(review time text is configurable) + links to My listings / Post another.
```

- One step per screen on mobile. On desktop the same steps show a step sidebar with completion ticks.
- Validation per step on Continue. Server errors are mapped back to the right step. An error summary is shown at the top.
- The draft auto-saves after step 2 (status text "Saved ✓", announced politely).

### 3.5 My Listings

```
┌──────────────────────────────────────────────────────────────┐
│ My listings                                     [+ Post ad]  │
│ [Active 4] [Pending 1] [Drafts 2] [Rejected 1] [Expired 3] [Sold 6] │ tabs
├──────────────────────────────────────────────────────────────┤
│ ┌────┐ Toyota Aqua 2015            Rs 7,850,000   [Active]   │
│ │████│ 👁 312 views · Expires in 41 days          [⋯ Actions]│ Edit · Mark sold · Delete
│ └────┘                                                       │
│ ┌────┐ Land in Kottawa              Rs 12,500,000 [Rejected] │
│ │████│ Reason: "Photos must show the land" [Edit & resubmit] │
│ └────┘                                                       │
└──────────────────────────────────────────────────────────────┘
```

### 3.6 Admin — Moderation Queue

```
┌──────────────┬───────────────────────────────────────────────────────────┐
│ Admin        │ Review queue (14 pending)        [Category ▾] [Oldest ▾]  │
│ ▸ Dashboard  ├──────────────────────┬────────────────────────────────────┤
│ ▸ Review  14 │ ▣ Toyota Aqua 2015   │ ████ ████ ████ ████  (photos)       │
│ ▸ Listings   │   Cars · 12m ago  ⚑1 │ Toyota Aqua 2015 G · Rs 7,850,000  │
│ ▸ Reports  3 │ □ Land in Kottawa    │ Flags: ⚠ phone number in description│
│ ▸ Users      │   Land · 40m ago     │ Fields (dl) · Description           │
│ ▸ Categories │ □ iPhone 13 128GB    │ Seller: Nimal P. · joined 3 days ago│
│ ▸ Attributes │   Phones · 1h ago    │   2 listings · 0 rejections · 0 reports
│ ▸ Locations  │ …                    │ ──────────────────────────────────  │
│ ▸ Pages      │                      │ [✓ Approve  A] [✕ Reject  R]        │
│ ▸ Blocklist  │                      │ [↺ Request changes]   J/K next/prev │
│ ▸ Settings   │                      │                                     │
│ ▸ Staff      │                      │                                     │
│ ▸ Audit log  │                      │                                     │
└──────────────┴──────────────────────┴────────────────────────────────────┘
Reject → modal with reason templates (Wrong category, Poor photos, Prohibited item,
Missing details, Duplicate, Other + note); the reason is shown to the seller.
```

The admin uses the same tokens, at denser sizes (36 px controls, 13 px tables).

### 3.7 Auth Screens

Centred card (max 420 px) on canvas, wordmark on top, a single column. Login: email, password (show/hide), remember me, Forgot password, Log in, "New here? Create account". Register adds name, mobile (optional, +94 format helper), terms checkbox, and Turnstile. 2FA challenge: 6-digit code input (`inputmode=numeric`, `autocomplete=one-time-code`) and a "Use a recovery code" toggle.

### 3.8 Error Pages

One layout (no DB queries): 404 "We can't find that page" + search; 410 "This ad has been removed" + similar category link; 403; 429 "Too many attempts, try again in N minutes"; 500; maintenance (with expected time if set).
