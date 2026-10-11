# Global UX: Countries, Locations and Currencies

| Field | Value |
|---|---|
| Status | **Draft — awaiting owner review** (global-marketplace update, 2026-10-11) |
| Data model | [Global design](../architecture/13-global-marketplace.md) |
| Prototype | [prototype/index.html](prototype/index.html): header country switcher, search location/currency filters, wizard steps "Location" and "Price" (`#post`), country-change dialog |

## 1. Country Context

| Element | Design |
|---|---|
| Country switcher | Header button showing flag + ISO code (`🇱🇰 LK` rendered as an SVG flag) next to the logo. It opens a searchable dialog of **active** countries (flag, name, ad count). Choosing a country navigates to `/{cc}` and remembers the choice (cookie + user profile). On mobile it sits in the account menu and on the home screen. |
| First visit | Remembered choice → Cloudflare header if available → default country setting. If unknown, `/` shows a country chooser grid (flags + names) with global latest ads below. |
| Context display | The country home and search pages say "in Sri Lanka" in the H1 ("Cars for sale in Sri Lanka"). The posting wizard preselects the user's country. |
| Cross-country pages | Global search (`/ads`) and seller profiles with listings in several countries show a flag + country name on each card. |

## 2. Location Selector (`x-geo.location-picker`)

```
Country *        [🇱🇰 Sri Lanka            ▾]
Province *       [Western Province        ▾]     ← label from country (State / Province / Country…)
District *       [Colombo District        ▾]     ← only when the country has 2 region levels
City or town *   [Nug|                      ]     ← searchable combobox (prefix search, 20 results)
                  Nugegoda
                  Nugegoda West
                  My town isn't listed →
Area (optional)  [Kirulapone              ]     ← curated list if available, else free text
                  ⓘ Don't enter your house number or street address.
```

- Each level loads after the previous one (cached lookups). Changing a level clears its children and moves focus to the next field.
- Labels, required markers and the number of levels come from the country's settings (no hardcoded "Province").
- "My town isn't listed" opens a small form (town name). The listing is saved with region + area text, and staff get a location request.
- Countries without complete data: city becomes optional and the area free text is used (shown as a hint).
- Accessibility: native selects for short lists, ARIA combobox for cities, labels always visible, errors per field.

## 3. Price Input with Currency (`x-listing.price-input`, Revised)

```
Price *   ┌──────┬────────────────────────────┐
          │ LKR  │ 12,500,000                 │   ← currency chip from the country (read-only in the MVP)
          └──────┴────────────────────────────┘
          Sri Lankan Rupee (Rs) · no decimals needed
          ( • ) Fixed   (  ) Negotiable
```

- The currency **code** is always shown in the input prefix (codes avoid symbol ambiguity: `$` = USD/AUD/CAD), with the name and symbol as a hint.
- Grouping while typing follows the country's number locale (India: `1,25,00,000`). Decimals are allowed up to the currency's minor units (JPY none, KWD 3).
- Land: area input with **the country's units** (Sri Lanka: perches, roods, acres, hectares). The calculated per-unit preview uses the country's land price unit: "≈ Rs 250,000 per perch (calculated)".
- If a future country allows several currencies, the chip becomes a select (default preselected).

## 4. Changing the Country During Posting (GLB-11)

When the seller has already entered values that depend on the country:

```
┌──────────────────────────────────────────────────────┐
│ Change country to India?                              │
│                                                       │
│ These will change:                                    │
│ • Currency: LKR → INR. Your price (Rs 12,500,000)     │
│   will be cleared so you can enter it in INR.         │
│ • Land size unit "perch" isn't used in India.         │
│   The land size will be cleared.                      │
│ • Deed type isn't available in India and will be      │
│   removed.                                            │
│ • Province, district and town will be reset.          │
│                                                       │
│               [Keep Sri Lanka]  [Change to India]     │
└──────────────────────────────────────────────────────┘
```

Nothing is converted or reinterpreted. Values with no dependency (title, description, photos, condition, other attributes) are kept.

## 5. Search and Filters (Global)

| Element | Design |
|---|---|
| Location filters | Country (on `/ads` only, otherwise fixed by the page) → region → subregion → city → locality, each a dependent select. A "Search this area" scope chip shows the current level ("Nugegoda, Colombo District ×"). |
| Currency | Inside a country: a static note above the price filter, "Prices in LKR". In global search: a currency select **above** the price fields. Price fields and price sort are disabled with the hint "Choose a currency to filter or sort by price" until one is chosen. |
| Other-currency listings | If a price filter is active and some listings in scope use another currency, a notice says "12 ads in other currencies are hidden by the price filter. [Show them]". |
| Area filter (land) | Min/max + unit select (the country's units) |
| Result header | "1,234 ads · Cars · Nugegoda, Colombo District, Sri Lanka" |
| Landing pages | Country home `/lk`, location pages `/lk/ads/in/colombo-district`, category + location `/lk/ads/cars/in/colombo-district/nugegoda`: H1 "Cars for sale in Nugegoda", a short intro, a sub-location list ("Towns in Colombo District" with counts), results. |

## 6. Cards and Detail Pages

| Context | Location format | Flag |
|---|---|---|
| Card inside one country | `Nugegoda, Colombo` (city, subregion or region) + time | No |
| Card in global search / multi-country seller page | `Nugegoda, Sri Lanka` | Yes, 16 px before the location |
| Detail page | `Nugegoda, Colombo District, Western Province, Sri Lanka` (+ area text) | Yes |
| Price | Original currency always: `Rs 12,500,000`, `₹1,25,00,000`, `£12,500,000`. On cross-country pages, ambiguous symbols get the code: `$12,500 USD`. | — |

Mobile: card prices use the 16 px tabular style. Very long prices (e.g. `₹1,25,00,000`, `Rp 1.250.000.000`) wrap the "Negotiable" suffix to the next line instead of truncating the number. Numbers are never abbreviated (Q-UX-4).

## 7. Admin: Locations (New Screens)

- **Countries:** table (flag, name, active toggle [Owner], currency, locale, region levels, labels, units, data completeness note, listing count). Activation checklist: data imported, labels set, legal check done (checkbox, G-10), moderation coverage (G-12).
- **Regions / Cities / Localities:** tree browser per country, search, add/edit, merge duplicates (preview of affected listings), deactivate.
- **Location requests:** queue with the requester's listing, proposed town, nearest existing matches, actions Approve (create) / Map to existing / Reject.

## 8. Microcopy Additions

- "Prices in {currency}" · "Choose a currency to filter or sort by price" · "This ad is in {country}" · "Change country to {country}?" · "My town isn't listed" · "Don't enter your house number or street address."
- All strings in `lang/en/geo.php` with placeholders. Country, region and city names come from the database (translatable later).
