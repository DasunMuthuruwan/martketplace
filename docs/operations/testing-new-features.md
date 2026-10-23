# Setting Up and Testing the New Changes Locally

For the owner, on the Windows machine described in [local-development.md](local-development.md). It covers what was built after the last local run (2026-10-18):

- the Phase 8 security fixes;
- paid boosts (B1–B6, [ADR-026](../decisions/README.md));
- the audience features: live search, trends, sharing, make/model pages and seller ratings (G1–G6, ADR-027).

Every command runs from the project folder `D:\My Project\martketplace` in PowerShell or Command Prompt. PHP is always `C:\php8.4\php.exe`.

## 1. Set Up (Once)

The code is already in this folder (the G6 branch until it is merged, then `main`). No new Composer or npm packages and no new `.env` settings are needed.

```bat
C:\mariadb10.11\start-marketplace-db.bat
C:\php8.4\php.exe artisan migrate
C:\php8.4\php.exe artisan db:seed --class=PageSeeder
npm run build
C:\php8.4\php.exe artisan optimize:clear
```

| Step | Why |
|---|---|
| `migrate` | New tables: boosts, the boost products (Bump; Top ad 3, 7, 15 days), prices, orders, bank accounts, daily views, search terms, seller reviews, review reports. It also adds the new staff permissions (boosts, reviews) to the roles. The local database was already migrated through G5b on 2026-10-23, in which case it reports "Nothing to migrate". |
| `db:seed --class=PageSeeder` | Adds the new **Promotions and refunds** page (empty, unpublished). It only adds missing pages. |
| `npm run build` | New scripts: live search, photo upload one at a time, sharing. |
| `optimize:clear` | Clears cached settings and the catalogue cache. |

Then start the site:

```bat
scripts\windows\serve-local.bat
```

In a **second window**, run the background tasks. Emails, ending boosts and lapsing orders depend on them.

```bat
C:\php8.4\php.exe artisan schedule:work
```

Emails are written to `storage\logs\laravel.log` (or `mail.log`), not sent.

### Accounts You Need

| Account | How |
|---|---|
| Owner (you) | Already set up. Otherwise see [local-development.md §4](local-development.md). |
| Seller | Register a second account, verify the email from the log. Add a phone number in Account → Profile and make it visible, then post an ad and approve it as Owner. |
| Buyer | Register a third account (another browser or a private window is easiest) and verify it. |

You cannot approve or boost your own ads, so use a separate seller account for the ads.

## 2. Phase 8 Security Changes

| What | How to test | Expected |
|---|---|---|
| Photos upload one at a time | Seller: edit an ad and choose 3–5 photos at once | "Photo 2 of 5" progress; every photo appears. A photo over 8 MB is listed as too large and the others still upload. |
| Security emails | Change your password; turn two-factor off and on; change your email | An email for each in the log ("Your password was changed", and so on). The email change notice goes to the **old** address. |
| Password reset signs out everywhere | Sign in in two browsers, then reset the password from "Forgot password" in one | The other browser is signed out. |
| Production check | `C:\php8.4\php.exe artisan marketplace:check-production` | Locally it **fails**, as expected (debug on, not HTTPS). It is meant for the live server. |

## 3. Paid Boosts

Full guide: [boosts.md](boosts.md). Selling is off by default. Staff can still grant boosts for free.

### 3.1 Free Grant by Staff

1. Owner: Admin → Sales → **Boosts** → find the seller's ad → grant a **Bump** or a **Top ad** (7 days) with a reason.
2. Open the ad's category in a private window:
   - **Top ad:** the ad appears in the labelled **Top ads** block above the results, and also in its normal place.
   - **Bump:** the ad moves to the top of the newest ads, and the card keeps its real "posted" date.
3. Seller: My ads → **Statistics** on that ad. Views per day and the active promotion are shown.

### 3.2 Selling to a Seller (Bank Transfer)

1. Owner: Admin → Sales → **Boost prices** → choose the country → enter test prices → enter test bank details → switch on "Accept bank transfers in this country" → Save.
2. Owner: Admin → **Settings** → Boosts → switch on "Sell boosts to sellers" (password confirmation).
3. Seller: My ads → **Promote** on a live ad → choose a product → order.
   - Expected: an order reference (for example `BX7K2M9QP`), the bank details, and 7 days to pay.
   - An email with the same details appears in the log.
4. Owner: Admin → Sales → **Orders**, which shows a count of unpaid orders. Open the order → **Mark paid** → Payment received.
   - Expected: the promotion starts at once, and a receipt email appears in the log.
5. Try **Cancel order** on a second order. A reason is required, and the seller gets an email.
6. Afterwards, switch "Sell boosts to sellers" **off** again. It must stay off until the accountant review and real prices are done.

### 3.3 Ending and Lapsing

With `schedule:work` running, `marketplace:end-boosts` runs every hour. To run it at once:

```bat
C:\php8.4\php.exe artisan marketplace:end-boosts
```

| Test | Expected |
|---|---|
| Mark the boosted ad as sold | It leaves the Top ads block at once, and the boost ends. |
| An unpaid order older than 7 days | It lapses (status expired). |
| A paid Top ad with less than a day left | The seller gets one "your Top ad ends tomorrow" email. |

## 4. Live Search, Trends, Sharing, Make/Model Pages

### 4.1 Live Search

Type in the header or home search box, for example "toy" or the start of a town name.

- Suggestions show categories, makes and models, and towns. The arrow keys and Enter work.
- A search shows under **Popular searches** only after it was searched 5 times (each visitor counts once a day). Numbers with 7+ digits are never stored.

To see popular searches without waiting, add test counts (local only):

```bat
C:\php8.4\php.exe artisan tinker
```
```php
$c = App\Models\Country::where('is_active', true)->first();
DB::table('search_terms')->insert(['country_id' => $c->id, 'term' => 'toyota aqua', 'date' => now()->toDateString(), 'searches' => 8]);
exit
```

Then `C:\php8.4\php.exe artisan cache:clear` and type "toy" again.

### 4.2 Popular, Trending, Most Marked as Sold

These need real activity: 10+ views of an ad in a week (one per visitor and day), 5+ searches, or 3+ ads of a model marked as sold. Nothing shows below these counts, so an empty home page locally is normal. To fake views for one ad (local only, in tinker):

```php
DB::table('listing_daily_views')->insert(['listing_id' => 1, 'date' => now()->toDateString(), 'views' => 15]);
```

Replace `1` with a live ad's id. Then run `cache:clear`, because the lists refresh hourly. The ad appears under **Popular** on the country home page.

### 4.3 Sharing

Open an ad. The available actions:

- **Share** copies the link, or opens the phone's share menu.
- **WhatsApp** opens WhatsApp with the title and link. No phone number is included.
- **Facebook** opens Facebook's share page.

### 4.4 Make and Model Pages

Open a vehicle ad that has a make and model, then follow "More Toyota Aqua ads" (or similar) near the bottom.

- Expected: a page titled like "Toyota Aqua for sale in …" with the matching ads.
- A **price range** appears once 5 or more of those ads are for sale.
- An unknown make in the address gives a 404 page.

## 5. Seller Ratings and Reviews

Full guide: [audience-features.md](audience-features.md).

### 5.1 Make the Buyer Eligible

A buyer may rate a seller only if all of these are true:

- the buyer's account is at least 7 days old;
- they revealed the seller's number on one of the seller's ads 2 to 90 days ago.

Locally you have to backdate both:

1. Buyer: open the seller's ad and click **Call** (or WhatsApp) to reveal the number.
2. Backdate in tinker (replace the email):

```php
$buyer = App\Models\User::where('email', 'buyer@example.com')->first();
$buyer->forceFill(['created_at' => now()->subDays(30)])->save();
App\Models\ContactReveal::where('user_id', $buyer->id)->latest('id')->first()->forceFill(['created_at' => now()->subDays(5)])->save();
exit
```

### 5.2 Test Reviews

| Step | Expected |
|---|---|
| Buyer: open the seller's page (link on the ad's seller card) | A rating form: 1–5 stars, "Did you complete a deal?", an optional comment. |
| Send 5 stars and a comment | "Thank you. Your rating is published; a comment appears after a quick check." The stars show at once, but the comment does not yet. The seller gets an email (log). |
| Owner or Moderator: Admin → Work → **Reviews** | The comment is waiting → **Approve comment**. It now shows on the seller page as "Firstname L.". |
| Seller: Account → **Reviews** | Reply once. The reply shows under the review. |
| A third account: **Report** the review | It appears under "Reported" in Admin → Reviews → **Keep**, or **Remove** with a reason (it then no longer counts). |
| A non-eligible account (new, or no reveal) | No rating form, only "You can rate a seller from 2 to 90 days after contacting them about an ad." |
| You as staff on a review about yourself | You cannot decide on it. |

The average rating shows from 3 reviews.

### 5.3 Trusted Seller Badge

The badge appears automatically when the seller's account is 90+ days old, they have 5+ ads marked as sold, 5+ reviews averaging 4.5+, and no upheld report in 6 months. To see it locally, lower the rules: Admin → **Settings** → Seller trust (for example 0 days, 0 sold, 1 review), then `cache:clear` and open the seller page. **Set the values back** afterwards (90, 5, 5).

## 6. Automated Checks

These are the same checks run before every commit. Run them one at a time.

| Check | Command | Last result (2026-10-23) |
|---|---|---|
| PHP tests (SQLite) | `C:\php8.4\php.exe vendor\bin\phpunit` | 1005 / 1005 |
| PHP tests (MariaDB) | `set DB_CONNECTION=mariadb` and `set DB_DATABASE=marketplace_test`, then the same command. Never run two at once. | 1005 / 1005 |
| Only the new security tests | `C:\php8.4\php.exe vendor\bin\phpunit tests\Feature\Security` | Pass |
| Code style, static analysis | `C:\php8.4\php.exe vendor\bin\pint --test`, `C:\php8.4\php.exe vendor\bin\phpstan analyse --memory-limit=1G` | Clean |
| Browser and accessibility | `npm run build`, `set PHP_BINARY=C:\php8.4\php.exe`, `npm run test:browser` (about 10 minutes) | 90 tests; see PROGRESS (G6) |

The browser tests use their own database and port 8130, so the site on 8001 can stay open.

## 7. Clean Up After Testing

- Switch **"Sell boosts to sellers"** off (Admin → Settings → Boosts).
- Reset **Seller trust** settings to 90 / 5 / 5.
- Test accounts, ads, reviews and fake counts can stay in the local database: it is never copied to the live site.

## 8. If Something Goes Wrong

| Symptom | Fix |
|---|---|
| 500 error after `git pull` | Run `migrate`, then `optimize:clear`. |
| No **Promote** button on an ad | Selling is off (§3.2 step 2), the country has no prices, or the ad is not live. |
| No **Reviews** in the admin menu | Run `migrate` (it adds the permission), then sign out and in. |
| Promotions page link missing | Publish it: Admin → Pages → Promotions and refunds. |
| Lists or badge do not update | `C:\php8.4\php.exe artisan cache:clear` (they are cached for up to an hour). |
| Emails not in the log | `schedule:work` is not running (it processes the email queue every minute). |
