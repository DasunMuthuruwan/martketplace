# Global Marketplace – Complete UI/UX Improvement Guide

## 1. Project Overview

Improve the existing Laravel marketplace UI to create a premium, modern, responsive global classified advertisements platform.

The marketplace is a **global multi-country application**, not a Sri Lanka-only website.

Country selection determines which marketplace records are displayed.

Example:

- `/lk` – Sri Lanka marketplace.
- `/us` – USA marketplace.
- `/uk` – UK marketplace.

The application already has country-based routing and data-scoping functionality. Inspect and preserve the existing implementation.

### Main objectives

- Modern and attractive marketplace design.
- Better user experience for buyers and sellers.
- Professional advertisement cards.
- Improved advertisement detail pages.
- Better category and search pages.
- Country-aware data and currency.
- Excellent mobile experience.
- Consistent light and dark themes.
- Future monetization through featured advertisements.

**Important:** Improve the existing application. Do not rebuild it or unnecessarily change its backend architecture.

---

# 2. Existing UI Screens

The following existing screens must be improved.

1. Homepage.
2. Individual Advertisement Details Page.
3. Category / Advertisement Listing Page.

All three screens must use consistent reusable UI components.

## 3. Design System

Keep the existing marketplace brand identity.

### Recommended colors

| Element | Color |
|---|---|
| Main background | #0B1020 |
| Card background | #151C2E |
| Primary accent | #91A8FF |
| Primary button | #FFAE1A |
| Primary text | #F1F3FA |
| Secondary text | #929CB2 |
| Border | #263149 |

These are suggested design tokens. Reuse the existing theme system where possible.

### Design principles

- Premium and modern appearance.
- Consistent spacing.
- Clear typography hierarchy.
- Attractive product photography.
- Subtle borders and shadows.
- Consistent border radius.
- Smooth but lightweight transitions.
- Accessible color contrast.
- Responsive layouts.

Preserve both dark and light theme functionality.

---

# 4. Homepage Improvements

## 4.1 Header and Navigation

Improve the existing header.

Requirements:

- Marketplace logo and branding.
- Large search bar.
- Country selector with flag and country name.
- Favorites.
- Post Ad button.
- Login/profile menu.
- Notifications where supported.
- Sticky navigation when appropriate.

The country selector must remain easily accessible.

The category navigation should highlight the active category.

## 4.2 Hero Section

The existing abstract geometric shapes should be replaced with meaningful marketplace imagery.

Use an attractive composition featuring:

- Vehicles.
- Property.
- Smartphones.
- Electronics.
- Furniture.

Suggested headline:

**Find it. Buy it. Sell it.**

Supporting text:

Discover great deals on vehicles, property, electronics and more. Connect directly with sellers.

Include:

- Prominent search field.
- Search button.
- Category selector where supported.
- Location selector where supported.
- Post Your Ad button.

Do not hard-code Sri Lanka into the global homepage headline.

## 4.3 Browse Categories

Improve category cards.

Requirements:

- Attractive category images or icons.
- Consistent card dimensions.
- Category name.
- Actual listing count.
- Hover interaction.
- Entire card clickable.
- Responsive category grid.

Possible categories:

- Vehicles.
- Property.
- Vehicle Parts & Accessories.
- Mobile Phones & Tablets.
- Electronics & Computers.
- Home, Furniture & Appliances.
- Other Items.

Use actual categories and country-scoped listing counts.

## 4.4 Featured Ads

Introduce a Featured Ads section.

Requirements:

- Display genuinely featured listings.
- Use the reusable listing-card component.
- Clearly identify promoted advertisements.
- Only display the section when featured listings exist.
- Respect featured status and expiry.

This feature can support future paid advertisement promotion.

## 4.5 Latest Ads

Improve the latest advertisements section.

Requirements:

- Consistent listing cards.
- High-quality thumbnails.
- Full prices.
- Negotiable indicator.
- Listing title.
- Location.
- Posting date.
- Favorite button.
- Image count indicator.

Use actual latest advertisements from the selected country.

## 4.6 Other Homepage Sections

Consider:

- Popular Near You.
- Recently Added.
- Popular Categories.
- Featured Listings.

Avoid repetitive sections displaying the same advertisements.

## 4.7 Buyer Safety

Improve the existing safety banner.

Suggested messages:

- Meet sellers in public places.
- Inspect items before paying.
- Avoid advance payments to unknown sellers.
- Report suspicious advertisements.

---

# 5. Advertisement Details Page Improvements

This section applies to the existing individual advertisement page.

Preserve all existing functionality.

Existing elements include:

- Breadcrumb navigation.
- Main image gallery.
- Image thumbnails.
- Price.
- Advertisement title.
- Contact number.
- WhatsApp.
- Seller information.
- Save.
- Share.
- Report.
- Advertisement specifications.
- Description.
- Location.
- Stay Safe section.
- More advertisements from seller.
- Similar advertisements.

## 5.1 Desktop Layout

Use a balanced two-column layout.

Suggested proportions:

- Main content: 65–70%.
- Sidebar: 30–35%.

The contact and seller panels should be visually aligned with the main gallery.

Consider a sticky contact panel while scrolling.

Do not allow the sidebar to overlap content or footer.

## 5.2 Image Gallery

Improve the existing gallery.

Requirements:

- Large main image.
- Consistent image proportions.
- Previous/next navigation.
- Image thumbnails.
- Active thumbnail indicator.
- Image count.
- Lightbox/fullscreen viewer.
- Keyboard navigation.
- Mobile touch gestures where practical.

Handle:

- Single image.
- Multiple images.
- Missing images.
- Large image collections.

Never distort listing images.

## 5.3 Price and Advertisement Summary

Make the price prominent.

Display:

- Full price.
- Correct currency.
- Negotiable status.
- Advertisement title.
- Posted date.
- Advertisement reference ID where useful.

Do not abbreviate prices.

Do not hard-code one country's currency.

## 5.4 Contact Actions

Improve:

- Show Number.
- WhatsApp.

Requirements:

- Prominent primary contact button.
- Clear labels.
- Accessible icons.
- Loading states.
- Error states.
- Phone number copy option where supported.
- Correct WhatsApp contact number.
- Mobile-friendly buttons.

Respect existing contact privacy and phone-number reveal rules.

Never assume the seller's phone country code from the currently selected marketplace country.

## 5.5 Seller Information

Improve the seller card.

Display available information:

- Seller name.
- Profile image or avatar.
- Member since.
- Genuine verification status.
- Active advertisement count.
- Link to seller's other advertisements.

Never display fake verification badges or invented statistics.

## 5.6 Save, Share and Report

Improve the existing actions.

Requirements:

- Save/unsave.
- Share advertisement.
- Copy advertisement link.
- WhatsApp sharing.
- Facebook sharing where supported.
- Report advertisement.

Use accessible icon buttons and meaningful feedback.

Shared links must preserve the correct country route.

## 5.7 Advertisement Specifications

Improve the existing details table.

Requirements:

- Clear labels.
- Stronger value hierarchy.
- Consistent alignment.
- Subtle row separators.
- Responsive layout.
- Support long values.

Important:

Specifications must depend on the advertisement category.

Examples:

Vehicles:
- Make.
- Model.
- Year.
- Mileage.
- Fuel type.
- Transmission.
- Engine capacity.
- Body type.
- Colour.

Property:
- Property type.
- Bedrooms.
- Bathrooms.
- Land size.
- Property size.
- Location.

Mobile phones:
- Brand.
- Model.
- Storage.
- RAM.
- Condition.

Do not show vehicle-specific fields for unrelated categories.

Use the existing category attribute system.

## 5.8 Description

Improve readability.

- Comfortable line height.
- Clear typography.
- Preserve seller-entered content.
- Preserve useful line breaks.
- Handle long descriptions.
- Optional Show More / Show Less.

Do not rewrite seller descriptions automatically.

## 5.9 Location

Display the listing's actual location.

Use the selected country's location hierarchy.

Do not hard-code Sri Lankan province or district fields into reusable components.

If a map is supported, only display it when valid location data is available and privacy rules permit it.

## 5.10 Safety Information

Improve the existing Stay Safe section.

- Expand/collapse functionality.
- Accessible controls.
- Clear safety icon.
- Practical safety instructions.

Do not imply that the marketplace guarantees transactions.

## 5.11 More Ads From Seller

Display other active advertisements from the same seller.

- Exclude the current advertisement.
- Respect country context.
- Respect moderation and visibility rules.
- Hide the section when there are no relevant advertisements.

## 5.12 Similar Ads

Display relevant advertisements.

Prioritize:

1. Same country.
2. Same category.
3. Similar attributes.
4. Relevant price range.

Exclude the current advertisement.

**Important existing issue:** The supplied screenshot shows Laravel article thumbnails inside Similar Ads for a vehicle listing.

Investigate the backend query and relationships. This is a content relevance/data-query problem, not just a styling problem.

Ensure Similar Ads only returns actual marketplace advertisements.

---

# 6. Category / Advertisement Listing Page Improvements

This section applies to the existing category listing screen.

The supplied example is the Vehicles category.

Existing elements:

- Breadcrumb.
- Category heading.
- Advertisement count.
- Category sidebar.
- Location filter.
- Price filter.
- Sorting.
- Grid/list view controls.
- Advertisement banner.
- Listing cards.

Preserve existing filters and search functionality.

## 6.1 Category Heading

Improve the heading area.

Display:

- Breadcrumb.
- Category title.
- Selected country.
- Actual advertisement count.
- Optional category description.

Example:

Vehicles in [Selected Country]

The country name must be dynamic.

## 6.2 Sidebar Filters

Improve the existing sidebar.

### Categories

Display available subcategories.

Examples:

- Cars.
- Vans.
- Motorcycles & Scooters.
- Three-wheelers.
- Lorries & Trucks.
- SUVs & Jeeps.
- Buses.
- Heavy Machinery & Tractors.
- Bicycles.
- Boats.

Use the actual category hierarchy.

### Location filters

Provide country-aware location filters.

Possible hierarchy:

- State / Province.
- District.
- City / Town.

Not every country uses the same location hierarchy.

Use the existing country-specific location configuration.

### Price filters

Provide:

- Minimum price.
- Maximum price.
- Correct currency label.
- Apply filter.
- Clear filters.

Validate minimum and maximum values.

Do not hard-code LKR.

### Additional filters

Where supported by the category:

Vehicles:
- Make.
- Model.
- Year.
- Fuel.
- Transmission.
- Condition.
- Mileage.

Property:
- Property type.
- Bedrooms.
- Bathrooms.
- Land size.

Mobile Phones:
- Brand.
- Storage.
- Condition.

Do not show irrelevant filters for unrelated categories.

## 6.3 Mobile Filters

On mobile:

- Convert the sidebar into an accessible filter drawer or modal.
- Provide Apply and Clear buttons.
- Display the number of active filters.
- Keep selected filters visible.
- Prevent background scrolling when the drawer is open.
- Ensure the filter interface fits the viewport.

## 6.4 Sorting

Improve sorting controls.

Possible options:

- Newest First.
- Oldest First.
- Price: Low to High.
- Price: High to Low.

Only display options supported by the existing backend.

Preserve selected sorting when navigating pages.

## 6.5 Grid and List Views

Keep both view modes if supported.

Grid view:
- Attractive image cards.
- Consistent card dimensions.
- Full price.
- Title.
- Location.
- Posted date.

List view:
- Larger thumbnail.
- More descriptive information.
- Price and title clearly visible.
- Better use of horizontal space.

Persist the user's selected view if appropriate.

## 6.6 Advertisement Banner

Improve the existing advertisement placement.

Requirements:

- Responsive dimensions.
- Proper aspect ratio.
- Clearly identify it as Advertisement.
- Avoid excessive vertical space.
- Avoid disrupting the listing experience.
- Handle different banner sizes.
- Prevent layout shifts while loading.

The banner must not be confused with an organic marketplace listing.

## 6.7 Listing Cards

Reuse the same listing-card component used on the homepage.

Maintain consistency across all marketplace pages.

Requirements:

- Listing image.
- Image count.
- Favorite button.
- Featured badge where applicable.
- Full price.
- Negotiable indicator.
- Listing title.
- Location.
- Posting date.

Ensure the card links to the correct country-aware advertisement detail URL.

## 6.8 Empty Results

Create an attractive empty state.

Example:

No advertisements found.

Try changing your filters or searching another category.

Provide a Clear Filters action.

Do not display fake listings when there are no results.

## 6.9 Pagination

Improve existing pagination or load-more functionality.

Requirements:

- Clear current page.
- Previous/next controls.
- Mobile-friendly layout.
- Preserve active filters and sorting.
- Avoid fetching all advertisements at once.

---

# 7. Global Country-Based Marketplace

This is one of the most important requirements.

The marketplace is global.

Country selection controls which marketplace records are displayed.

Example:

`http://localhost:8001/lk`

This loads Sri Lanka's marketplace.

Other enabled country routes should load their respective marketplace data using the existing implementation.

## 7.1 Country Selector

Improve the existing country selector.

- Country flag.
- Country name.
- Searchable country list if needed.
- Selected country indicator.
- Accessible dropdown.
- Mobile-friendly selector.

When a user selects another country, navigate to the correct country route.

## 7.2 Country Data Isolation

Ensure the selected country controls:

- Homepage listings.
- Latest advertisements.
- Featured advertisements.
- Category counts.
- Search results.
- Advertisement listing pages.
- Location filters.
- Similar advertisements.
- More advertisements from seller.
- Currency display.

Never accidentally mix country-specific listings.

## 7.3 Currency

Use the correct currency for the selected country/listing according to the existing domain model.

Examples:

- Sri Lanka: LKR.
- USA: USD.
- UK: GBP.

These are examples only.

Do not hard-code currency values into reusable components.

Do not silently convert currencies unless currency conversion is an existing product feature.

## 7.4 Country-Specific Locations

Different countries can have different location hierarchies.

Do not assume every country has:

Province → District → City.

Use the existing country configuration and location data.

## 7.5 Preserve Country Context

Country context must remain correct when navigating:

- Homepage.
- Category.
- Search.
- Listing details.
- Seller listings.
- Favorites.
- Post Ad.
- Shared advertisement links.

Inspect the existing route implementation before making changes.

Do not independently hard-code country URL prefixes in multiple components.

---

# 8. Mobile UI Improvements

Mobile is a first-class marketplace experience.

### Homepage

- Compact header.
- Accessible country selector.
- Search functionality.
- Horizontal category browsing.
- Responsive listing grid.
- Bottom navigation.

### Listing page

- Collapsible filters.
- Compact sorting controls.
- Responsive listing cards.
- Grid/list toggle.

### Advertisement details

- Full-width image gallery.
- Stacked listing summary.
- Seller details.
- Sticky bottom contact bar where appropriate.
- Call and WhatsApp actions.

Ensure sticky controls never cover page content.

### Responsive testing

Test:

- Small mobile.
- Standard mobile.
- Tablet.
- Laptop.
- Large desktop.

Avoid horizontal overflow.

---

# 9. Theme Improvements

Preserve existing theme functionality.

Both themes must look professionally designed.

Dark theme:
- Navy background.
- Dark cards.
- Blue accents.
- Orange CTA.
- Readable secondary text.

Light theme:
- Clean white or light background.
- Clearly separated cards.
- Proper borders.
- Accessible text contrast.
- Consistent blue and orange brand accents.

Do not implement dark mode at the expense of light mode.

---

# 10. Performance and Accessibility

Performance:

- Optimized listing thumbnails.
- Lazy loading below the fold.
- Responsive images.
- Reserved image dimensions.
- Avoid unnecessary JavaScript.
- Avoid unnecessary dependencies.
- Preserve existing pagination.
- Minimize layout shifts.

Accessibility:

- Semantic HTML.
- Keyboard navigation.
- Accessible form labels.
- Visible focus states.
- Useful image alt text.
- Proper contrast.
- Accessible dropdowns and modals.
- Reduced-motion support.

---

# 11. Laravel Implementation Instructions

Before changing any code, inspect the existing project.

Identify:

- Laravel version.
- PHP version.
- Frontend framework.
- Blade / Livewire / Alpine implementation.
- CSS framework.
- Existing components.
- Existing layout files.
- Theme system.
- Country routing.
- Country middleware or resolution logic.
- Country models/configuration.
- Listing models and relationships.
- Category models.
- Location relationships.
- Currency configuration.
- Search implementation.
- Filter implementation.
- Advertisement banner implementation.
- Featured advertisement logic.
- Existing tests.

### Implementation rules

1. Preserve existing Laravel architecture.
2. Reuse existing components.
3. Avoid unnecessary dependencies.
4. Do not replace the frontend framework.
5. Preserve existing route names and URLs.
6. Preserve authentication and authorization.
7. Preserve existing advertisement workflows.
8. Preserve country routing and data isolation.
9. Do not change database schema for visual improvements.
10. Do not add fake data.
11. Keep business logic outside presentation templates.
12. Keep UI components reusable.

If the project uses Blade and Livewire, continue using them unless inspection demonstrates a strong reason to change.

---

# 12. Claude Code Implementation Workflow

Act as a senior Laravel engineer and professional UI/UX designer.

## Phase 1: Inspect

First inspect the entire relevant project structure.

Identify the existing implementation of:

- Homepage.
- Advertisement details.
- Category listing.
- Shared listing cards.
- Header and footer.
- Search.
- Filters.
- Country selector.
- Theme switching.

Trace the country flow from URL to backend data queries.

Verify how `/lk` loads Sri Lanka's marketplace.

Do not assume the implementation.

## Phase 2: Prepare an Implementation Plan

Before editing files, prepare a file-level plan.

Include:

- Files to modify.
- Components to reuse.
- New components required.
- CSS changes.
- Backend changes, if genuinely necessary.
- Potential regression risks.
- Testing plan.

Separate visual improvements from functional/backend changes.

## Phase 3: Implement

Implement incrementally.

Recommended order:

1. Design tokens and shared styles.
2. Header and country selector.
3. Shared listing card.
4. Homepage.
5. Category listing page.
6. Filters and sorting.
7. Advertisement details page.
8. Responsive layouts.
9. Light and dark themes.
10. Interaction and accessibility improvements.

Do not change all files blindly.

## Phase 4: Verify

Run available checks:

- Laravel tests.
- Frontend production build.
- Formatting checks.
- Relevant route tests.

Manually verify:

- Homepage.
- Category listing.
- Advertisement detail page.
- Search.
- Filters.
- Sorting.
- Favorites.
- Post Ad.
- Contact actions.
- Light theme.
- Dark theme.
- Mobile layouts.
- Country switching.
- Country-specific currency.
- Country-specific locations.
- Similar advertisement relevance.

Test `/lk` and at least one other enabled country if available.

Investigate and fix the unrelated article thumbnails appearing in vehicle advertisements.

Do not claim features are tested unless they were actually tested.

## Phase 5: Report

After implementation, provide:

- Summary of improvements.
- Files modified.
- Components created or reused.
- Backend changes.
- Dependencies added.
- Build results.
- Test results.
- Remaining limitations.

---

# 13. Scope Restrictions

Unless explicitly requested, DO NOT:

- Rebuild the application.
- Turn the marketplace into a Sri Lanka-only platform.
- Hard-code country names.
- Hard-code currencies.
- Mix listings from different countries.
- Change the database schema.
- Replace the frontend framework.
- Break existing routes.
- Change authentication logic.
- Change advertisement moderation.
- Change payment or seller contact behavior.
- Add fake listings.
- Add fake verification badges.
- Add non-functional buttons.
- Remove existing features.
- Perform unrelated refactoring.

---

# 14. Definition of Done

The implementation is complete when:

- Homepage has a professional visual hierarchy.
- Hero section communicates the marketplace purpose.
- Category cards are attractive and functional.
- Listing cards are consistent across all screens.
- Advertisement detail page has an improved gallery and contact section.
- Specifications adapt to advertisement categories.
- Category filters are responsive.
- Sorting and grid/list controls work.
- Advertisement banners are clearly identified.
- Similar Ads display relevant marketplace advertisements.
- Seller information is presented accurately.
- Mobile experience is polished.
- Light and dark themes are complete.
- Country switching works correctly.
- Currency and locations are country-aware.
- No cross-country data leakage occurs.
- Existing Laravel functionality remains intact.
- Production build succeeds or existing failures are documented.

---