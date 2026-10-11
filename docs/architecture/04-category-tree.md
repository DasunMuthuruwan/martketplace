# Proposed Category Tree and Attributes

| Field | Value |
|---|---|
| Status | **Approved as the working tree (CD-08, 2026-10-11). Not final until the owner answers the 4 questions in §5.** |
| Scope rule | Vehicles, land and property, spare parts, mobile phones, electronics, furniture and general buy/sell [CONFIRMED Q11]. **Jobs, Services and Animals are excluded** unless approved. |
| Implementation | Seed data only. Everything is editable in Admin → Categories / Attributes after launch. |

Changes from the requirements spec §4.2.1 draft: mobile phones become their own top-level category (high traffic), "Home & Living" is split so furniture is visible, and Fashion is merged into a general "Other Items" group to keep the MVP tree short.

## 1. Tree (2 Levels)

| # | Top-level | Subcategories (leaf = where listings are posted) | Allowed types |
|---|---|---|---|
| 1 | **Vehicles** | Cars · Vans · SUVs & Jeeps · Motorcycles & Scooters · Three-wheelers · Lorries & Trucks · Buses · Heavy Machinery & Tractors · Bicycles · Boats | Sale, Rent, Wanted |
| 2 | **Property** | Land · Houses · Apartments · Commercial Property · Rooms & Annexes · Holiday & Short-stay Rentals | Sale, Rent, Wanted |
| 3 | **Vehicle Parts & Accessories** | Car Parts · Motorcycle Parts · Three-wheeler Parts · Tyres & Rims · Car Audio & Electronics · Accessories | Sale, Wanted |
| 4 | **Mobile Phones & Tablets** | Mobile Phones · Tablets · Phone & Tablet Accessories · Smart Watches & Wearables | Sale, Wanted |
| 5 | **Electronics & Computers** | Laptops · Desktop Computers · Computer Accessories & Parts · TVs · Audio & Speakers · Cameras & Drones · Video Games & Consoles · Other Electronics | Sale, Wanted |
| 6 | **Home, Furniture & Appliances** | Furniture · Home Appliances (fridges, washing machines, ACs) · Kitchen Items · Home Décor · Garden | Sale, Wanted |
| 7 | **Other Items** | Fashion & Clothing · Watches & Jewellery · Sports & Fitness · Books & Education · Musical Instruments · Baby & Kids · Health & Beauty · Business & Industrial Equipment · Everything Else | Sale, Wanted |

Total: 7 top-level and 48 leaf categories.

## 2. Shared Attributes (Defined Once, Attached Where Needed)

| Key | Type | Unit | Used by |
|---|---|---|---|
| `brand` | select (admin options) + "Other" | — | Electronics, phones, parts, appliances |
| `model_name` | text | — | Phones, electronics |
| `warranty` | select: none / shop / company | — | Phones, electronics, appliances |

`condition` (new / used / reconditioned) and `price` are **core listing columns**, not attributes. Each category turns `condition` on or off.

## 3. Category-Specific Attributes

**Legend:** R = required · F = filterable · C = shown on card.

### 3.1 Vehicles (Cars, Vans, SUVs; similar sets for the other vehicle leaves)

| Attribute | Type | R | F | C |
|---|---|---|---|---|
| Make | select | ✔ | ✔ | ✔ |
| Model | select, depends on Make (+ "Other" text) | ✔ | ✔ | ✔ |
| Year of manufacture | year (1950 to current+1) | ✔ | ✔ range | ✔ |
| Year of registration | year, or "Unregistered" | | ✔ | |
| Mileage | number, km | ✔ (used) | ✔ range | ✔ |
| Fuel type | select: petrol / diesel / hybrid / electric / gas | ✔ | ✔ | ✔ |
| Transmission | select: auto / manual / tiptronic | ✔ | ✔ | ✔ |
| Engine capacity | number, cc | | ✔ range | |
| Body type | select (per leaf) | | ✔ | |
| Colour | select | | | |
| Trim / edition | text | | | |

Motorcycles and three-wheelers drop Body type and Transmission. Bicycles use Make + Type (road/mountain/kids) + Frame size only. Heavy machinery uses Make, Model, Year and Hours used.

### 3.2 Property

| Attribute | Applies to | Type | R | F | C |
|---|---|---|---|---|---|
| Land size | Land, Houses, Commercial | number + unit select (units offered by the listing country, e.g. LK perches/roods/acres; US acres/sq ft), stored as entered + m² (§3.2.1) | ✔ (land) | ✔ range | ✔ |
| Floor area | Houses, Apartments, Commercial | number, sq ft | | ✔ range | ✔ |
| Bedrooms | Houses, Apartments, Rooms | select 1–10+ | ✔ | ✔ | ✔ |
| Bathrooms | Houses, Apartments | select 1–10+ | | ✔ | ✔ |
| Floors / storeys | Houses, Commercial | number | | | |
| Furnishing | Houses, Apartments, Rooms | select: unfurnished / semi / fully | | ✔ | |
| Parking | Houses, Apartments, Commercial | boolean | | ✔ | |
| Land type | Land | multiselect: residential / commercial / agricultural / tea / rubber / coconut / paddy | | ✔ | |
| Deed type **(LK only, `scope_countries: ["LK"]`)** | Land, Houses | select: sinnakkara (freehold) / bimsaviya / jayabhoomi / swarnabhoomi / permit / other | | ✔ | |
| Tenure (other countries) | Land, Houses | select: freehold / leasehold / other | | ✔ | |
| Clear deed | Land, Houses | boolean | | | |
| Property type (commercial) | Commercial | select: shop / office / warehouse / building / other | ✔ | ✔ | ✔ |
| Address hint | All | text (street/area; full address optional) | | | |

For property, `price` with type Rent requires `rent_period` (month).

#### 3.2.1 Land: Total Price + Price per Unit [APPROVED 2026-10-11, ADR-018; generalised for global use by ADR-021]

> **Global update (2026-10-11):** perches are the **Sri Lanka** unit. Units and the "price per" unit are now configured per country (`country_measurement_units`, `countries.land_price_unit_id`). The rules below apply to every country. Sri Lanka keeps "price per perch". See [global design](13-global-marketplace.md) §5.

| Rule | Design |
|---|---|
| Scope | Only categories configured with a unit price: initially the **Land** leaf. `categories.unit_price_attribute_id` → `land_size`, `unit_price_label` = `perch`. Houses, Apartments, Commercial, etc. do **not** show it, even though Houses also have a land size. |
| Required fields | For Land: **total price** (`price`, LKR, > 0) and **land size** (> 0) are required. Allowed price types for Land: `fixed`, `negotiable`. `free` and `on_request` are disabled (`categories.allowed_price_types`). |
| Source of truth | `listings.price` + `listings.currency` (total), and the `land_size` attribute stored as **entered value + unit** (`input_value`, `input_unit_id`) with a normalised **m²** copy in `value_number` for filtering. |
| Unit conversion | Via `measurement_units.factor_to_base` (m²): perch 25.29285264, rood 1,011.7141056, acre 4,046.8564224, sq ft 0.09290304, hectare 10,000. Converted server-side with bcmath. Units offered depend on the listing country. |
| Price per unit | **Always calculated, never entered or stored as input.** `UnitPrice::per(price, currency, area, unit)`: computed in the country's `land_price_unit` (LK: perch), rounded per the currency's display rules. Exact when the seller entered that unit. Otherwise converted from m². No form field, so it can't be inconsistent. Hidden when the country has no `land_price_unit_id`. |
| Safety | Returns `null` (nothing displayed) when the price is null or 0, the area is null, ≤ 0 or non-numeric, the price type is not fixed/negotiable, or the listing type is `wanted`. Division only happens when the area is > 0, so there is no division by zero. Validation rejects invalid areas before saving. The calculator still guards on its own for legacy or admin-edited data. |
| Listing types | **Sale:** `Rs 12,500,000 · Rs 250,000 / perch`. **Rent:** per-perch figure with the period (`Rs 2,000 / perch / month`). **Wanted:** total budget only (no per-perch). |
| Where shown | Listing cards, listing detail (price block, with area as `50 perches` or `2 acres (320 perches)`), and Land search/category results. Computed at render time from `price`, `currency` and `attributes_summary.land_size` (value, unit, m²), so there is no extra query. |
| Not in MVP | Sorting or filtering by price per perch. If needed later, add a server-maintained derived column `unit_price` (recalculated on every save, never user-editable) with an index. |
| Tests (Phase 5) | Unit tests for the calculator (normal values, acre/rood conversion, rounding, zero, null, negative, very large values). Feature tests: Land listing without area or price → 422; `on_request` rejected for Land; a Houses listing never shows a per-perch price; card, detail and search output show the same value. |

### 3.3 Vehicle Parts & Accessories

| Attribute | Type | R | F | C |
|---|---|---|---|---|
| Part category | select (engine / body / electrical / suspension / brakes / interior / lights / other) | ✔ | ✔ | ✔ |
| Compatible make | select (reuses Make options) | | ✔ | |
| Compatible model(s) | text | | | |
| Brand | shared | | ✔ | |
| Genuine / aftermarket | select | | ✔ | ✔ |
| Tyre size (Tyres & Rims) | text (e.g. 185/65R15) | | ✔ | ✔ |

### 3.4 Mobile Phones & Tablets

| Attribute | Type | R | F | C |
|---|---|---|---|---|
| Brand | shared select | ✔ | ✔ | ✔ |
| Model | select, depends on Brand (+ Other) | ✔ | ✔ | ✔ |
| Storage | select (GB) | | ✔ | ✔ |
| RAM | select (GB) | | ✔ | |
| Colour | text | | | |
| Network / SIM | select: single / dual / eSIM | | | |
| Warranty | shared | | ✔ | |
| Box & accessories included | boolean | | | |

### 3.5 Electronics & Computers

| Attribute | Applies to | Type | F | C |
|---|---|---|---|---|
| Brand | all | shared | ✔ | ✔ |
| Model | all | text | | |
| Processor | Laptops, Desktops | select (i3/i5/i7/i9/Ryzen 3–9/Apple M-series/other) | ✔ | ✔ |
| RAM | Laptops, Desktops | select GB | ✔ | ✔ |
| Storage | Laptops, Desktops | select (GB/TB, SSD/HDD) | ✔ | |
| Screen size | Laptops, TVs | number, inches | ✔ range | ✔ |
| Graphics | Laptops, Desktops | text | | |
| TV type | TVs | select: LED / OLED / QLED / smart | ✔ | |
| Warranty | all | shared | ✔ | |

### 3.6 Home, Furniture & Appliances

| Attribute | Applies to | Type | F | C |
|---|---|---|---|---|
| Item type | Furniture | select: sofa / bed / table / chair / wardrobe / cabinet / other | ✔ | ✔ |
| Material | Furniture | select: wood / metal / plastic / glass / fabric / other | ✔ | |
| Appliance type | Home Appliances | select: refrigerator / washing machine / AC / fan / microwave / other | ✔ | ✔ |
| Brand | Appliances | shared | ✔ | |
| Capacity | Appliances | text (e.g. 300 L, 8 kg, 12,000 BTU) | | |
| Warranty | Appliances | shared | ✔ | |

### 3.7 Other Items

Most leaves use only core fields plus an optional `brand`. Fashion adds Gender (men / women / unisex / kids) and Size (text).

## 4. Seed Data Sources

- **Vehicle makes and models:** start with the makes common in Sri Lanka (Toyota, Suzuki, Honda, Nissan, Mitsubishi, Mazda, Micro, Perodua, Kia, Hyundai, BMW, Mercedes-Benz, Audi, Tata, Mahindra, Bajaj, TVS, Hero, Yamaha, plus "Other"), each with about 10–30 popular models. These are compiled in Phase 5 and reviewed by the owner.
- **Phone brands and models:** Apple, Samsung, Xiaomi/Redmi/POCO, Huawei, Oppo, Vivo, Realme, Nokia, Google, OnePlus, Infinix, Tecno, plus "Other".
- **Locations (global):** GeoNames countries, regions and cities (CC BY 4.0), curated per active country. Sri Lanka: 9 provinces, 25 districts and a curated town list. See [global design](13-global-marketplace.md) §4.

"Other" with free text is always available, so missing options never block a seller. Moderators can promote common "Other" values to real options.

## 5. Questions for the Owner

Status after owner approval of the tree as documented (2026-10-11). Phase 3 will raise any remaining point again before the seed data is finalised.

1. Approve the 7 top-level categories and the leaf list above, or edit them. — **Approved as listed.**
2. Keep "Boats" and "Holiday & Short-stay Rentals" in the MVP? — **Kept** (part of the approved tree).
3. Is a price-per-perch display needed for land? — **Approved 2026-10-11:** show the total price and a calculated price per perch for Land (§3.2.1).
4. Should "Wanted" (buyer requests) be enabled at launch, or only Sale and Rent? — **Enabled as listed** in the "Allowed types" column (admin can switch it off per category).

## 6. Country Scoping (Global Marketplace, 2026-10-11)

One global category tree is used for every country. Items that only make sense in some countries are scoped with `scope_countries` (ISO2 list, null = all countries). Admins edit it in Admin → Categories / Attributes. The posting form and filters only show items in scope for the listing's or search's country.

| Item | Proposed scope |
|---|---|
| Vehicles › Three-wheelers | LK, IN (and other countries where common, admin-editable) |
| Deed type options (sinnakkara, bimsaviya, jayabhoomi, swarnabhoomi, permit) | LK |
| Tenure (freehold / leasehold) | All except LK (which uses deed type) |
| Land size units | Per country (`country_measurement_units`), not per category |
| Vehicle makes/models | Global. Options can be scoped later if a market needs local brands (e.g. Micro, Perodua). |
| Everything else | Global |

> **Seeded in T3.1 (`database/data/categories.php`, review welcome):** the tree in §1 with these per-leaf refinements, all editable later in Admin: **Land** allows only `fixed` / `negotiable` prices; **Rooms & Annexes** and **Holiday & Short-stay Rentals** allow Rent and Wanted only (a room or holiday stay is not sold); **Three-wheeler Parts** is scoped like Three-wheelers (IN, LK); Vehicles and Property allow 10 images, other groups 8; Home/Furniture and Other Items allow "free" giveaways; Property hides the item condition. The seeder only creates missing categories (matched by slug), so admin edits survive a re-run.

Rules:
- Validation rejects a scoped category, attribute or option posted for a country outside its scope.
- Attribute schemas are cached **per category + country**.
- Owner decision G-13 confirms this approach.
