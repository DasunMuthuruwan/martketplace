# Performance Check (T5.4)

| Field | Value |
|---|---|
| Date | 2026-10-13 |
| Purpose | Validate the index strategy (database design §4, global design §9) before launch, and measure the main listing queries at the capacity target (NFR-P5: 50,000 listings, 20,000 users). |
| Environment | Local MariaDB 10.11.19, default `innodb_buffer_pool_size` = 128 MB, `innodb_ft_min_token_size` = 3; PHP 8.4. A laptop, not the host: **re-run on the target host before launch** (open hosting item). |
| Data | `marketplace:perf-seed --listings=50000` on a scratch database after `migrate:fresh --seed`: 50,000 listings in LK 40% / IN 25% / GB 20% / US 15%, all leaf categories, 85% active, 5% pending, 5% sold, 5% expired, published over the last 58 days; 9 regions per country (LK with 3 districts each) and 20 cities per lowest level; 1,000 sellers; typed values for car make/model/year/mileage, land size and phone brand (14,871 rows). Then 19,000 more accounts (2% banned, 2.5% suspended) to reach 20,000 users. The text uses a 24-word vocabulary, so every word appears in most ads: a **worst case** for keyword search. |
| How to repeat | `php artisan migrate:fresh --seed` then `php artisan marketplace:perf-seed --force` and `php artisan marketplace:perf-report` (both refuse to run in production). The report EXPLAINs and times the exact SQL the site runs (first page of 24 + the result count, 20 runs). |

## Results

Times in ms; "Count" is the result count, "Page" the first 24 rows; median and p95 of count + page.

| Query | Before: median / p95 | After: count, page | After: median / p95 | Index used (after) |
|---|---|---|---|---|
| Country browse, newest (LK) | 121.7 / 166.1 | 19.2, 7.4 | **26.6 / 35.4** | `(status, country_id, published_at)` |
| Country + top category (LK vehicles) | 31.7 / 52.4 | 9.5, 2.5 | 12.1 / 15.9 | `(status, country_id, published_at)` |
| Country + leaf category (LK cars) | 10.6 / 14.0 | 2.1, 1.5 | 3.6 / 5.2 | `(status, country_id, category_id, published_at)` |
| Region (LK region 3) | 34.5 / 56.1 | 11.3, 2.3 | 13.5 / 17.5 | `(status, country_id, published_at)` |
| City (LK) | 11.2 / 14.0 | 4.6, 1.9 | 6.3 / 12.9 | `(status, city_id, published_at)` |
| Price range + sort (GB, GBP) | 31.4 / 49.4 | 5.5, 6.4 | 11.8 / 13.6 | `(status, country_id, currency, price)` |
| Cars: make + year range (LK) | 7.9 / 9.4 | 7.3, 7.4 | 14.9 / 21.9 | EAV `lav_attribute_option_listing_index` |
| Cars: make + model + mileage (LK) | 7.2 / 8.8 | 1.2, 1.2 | 2.4 / 2.7 | EAV option + number indexes |
| Land: area range in the default unit (LK) | 10.0 / 12.4 | 7.0, 7.7 | 14.7 / 19.7 | EAV `lav_attribute_number_listing_index` (m²) |
| Keyword, rare words (LK) | 5.2 / 8.1 | 1.0, 0.9 | 1.9 / 3.3 | FULLTEXT `(title, description)` |
| Keyword, very common word + category (LK cars "owner") | 292.2 / 316.1 | 131.7, 136.0 | 273.6 / 294.5 | FULLTEXT |
| Global keyword, very common word ("garden") | 394.3 / 421.0 | 50.6, 199.0 | 256.7 / 278.4 | FULLTEXT |
| Expiry job | 0.8 / 1.1 | –, 1.3 | 1.3 / 1.7 | `(status, expires_at)` |
| My listings | 2.5 / 3.2 | –, 2.6 | 2.6 / 3.0 | `(user_id, status, updated_at)` |
| Moderation queue | 2.5 / 2.9 | –, 2.9 | 2.9 / 3.3 | `(status, submitted_at)` |

"Before" was measured with 1,000 users; "after" with 20,000 users and all changes below. Every query uses one of the planned indexes. **No index was added**: the planned set was enough once the queries let MariaDB use it.

## What was changed, and why

1. **Public scope, category check** (`Listing::public()`). "Category and its parent active" was an `EXISTS` on categories. MariaDB flattened it into a join that started from the categories table, so it could no longer read the newest ads in index order: it sorted all 17,000 LK ads to show 24 (country browse page: 60 ms). It is now an exclusion list of switched-off categories (`Category::hiddenIds()`, cached in the catalogue cache, renewed by every category change), which is normally empty, so no condition is added at all.
2. **Public scope, owner check.** "Owner may act" was an `EXISTS` on users; MariaDB then drove the query from the users table (same loss of order, 95 ms). It is now `NOT EXISTS (deleted, banned or still-suspended owner)`, a per-row primary-key lookup. Semantics unchanged; the existing public-scope tests (each exclusion case) pass.
3. **Result count capped** (`MysqlListingSearch::total()`). Only `max_page × per_page` (2,400) results can be browsed, so counting stops at 2,401 and the page shows "2,400+ ads". Broad searches no longer count every match (LK browse count: 59 → 19 ms; global "garden": 233 → 51 ms).

## Remaining risk and next steps

- **Very common keywords** remain the slowest query (p95 ≈ 300 ms in this worst-case data): FULLTEXT returns every match, which is then filtered and sorted by date. Within the search target (p95 < 800 ms for the whole page, NFR-P2) but the first candidate for scaling stage 4 (architecture §7: Meilisearch behind the same `ListingSearch` interface). Cheap options before that: ordering keyword results by relevance, or a custom FULLTEXT stopword list for words like "sale".
- The owner check reads the small users index per query plan (`users:ALL` in EXPLAIN = an anti-join over users); with 20,000 users it costs a few ms. Re-check if users grow far beyond the NFR target.
- **Re-run on the target host** (buffer pool, CPU and disk differ) and record the numbers here. Hosting capabilities are still unverified (PROGRESS).
