// Q7.2: end-to-end journeys through the real UI (data: BrowserTestSeeder; emails: the test mail log).
// Sri Lanka (province → district → town): register → verify → profile → post a car with a photo →
// moderator approves → search → a buyer reveals the number, saves and reports. United States (state →
// city): the same seller posts in another country and currency. Negative paths: wrong password,
// unverified account, incomplete ad, rejection and resubmission.
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { expect, test } from '@playwright/test';
import { mailLog, password, root, totp, totpSecret } from './environment.mjs';

// Whole journeys (several pages, an upload): longer than the 30 s default.
test.describe.configure({ mode: 'serial', timeout: 180_000 });

const run = Date.now().toString(36);
const seller = { name: 'Kasun Journey', email: `kasun.${run}@browser.test`, phone: '771234568' };
const buyer = { name: 'Dilini Buyer', email: `dilini.${run}@browser.test` };
const carTitle = `Toyota Axio 2016 journey ${run}`;
const usTitle = `Honda Civic 2018 journey ${run}`;
const photo = resolve(root, 'tests/Browser/fixtures/photo.jpg');

// ---- helpers -------------------------------------------------------------------------------------

async function logIn(page, email) {
    await page.goto('/login');
    await page.locator('input[name="email"]').fill(email);
    await page.locator('input[name="password"]').fill(password);
    await page.locator('form button[type="submit"]').first().click();
    await page.waitForLoadState('networkidle');
}

/** The form containing a field (pages also have the header search form). */
function formWith(page, selector) {
    return page.locator('form', { has: page.locator(selector) });
}

async function logOut(page) {
    await page.context().clearCookies();
}

/** The newest verification link for this address from the mail log (quoted-printable decoded). */
function verificationLink(email) {
    const raw = readFileSync(mailLog, 'utf8').replace(/=\r?\n/g, '').replace(/=3D/g, '=');
    const messages = raw.split(/\n(?=\[\d{4}-\d{2}-\d{2})/).filter((m) => m.includes(email));
    const links = (messages.at(-1) ?? '').match(/https?:\/\/[^\s"<>]+\/email\/verify\/\d+\/[0-9a-f]+\?[^\s"<>]+/g) ?? [];
    expect(links.length, `verification email for ${email}`).toBeGreaterThan(0);

    return links.at(-1).replace(/&amp;/g, '&');
}

async function register(page, person) {
    await page.goto('/register');
    await page.locator('input[name="name"]').fill(person.name);
    await page.locator('input[name="email"]').fill(person.email);
    await page.locator('input[name="password"]').fill(password);
    await page.locator('input[name="password_confirmation"]').fill(password);
    await page.locator('select[name="country_id"]').selectOption({ label: 'Sri Lanka' });
    const terms = page.locator('input[name="terms"]');
    if (await terms.count()) {
        await terms.check();
    }
    // The registration form refuses submissions faster than a person (HumanTiming, 2 s).
    await page.waitForTimeout(2200);
    await page.locator('form button[type="submit"]').first().click();
    await page.waitForLoadState('networkidle');
    await expect(page, 'registration accepted').not.toHaveURL(/\/register/);
}

async function verify(page, person) {
    await page.goto(verificationLink(person.email));
    await page.waitForLoadState('networkidle');
}

/** Pick an option of a searchable combobox (x-ui.combobox) by typing part of its label. */
async function pickCombobox(page, inputSelector, text) {
    const input = page.locator(inputSelector);
    await input.click();
    await input.fill(text);
    const option = page.locator(`#${await input.getAttribute('aria-controls')} [role="option"]:not([hidden])`).filter({ hasText: text }).first();
    await option.click();
}

/** Continue on a posting step (the submit button of the step form). */
async function continueStep(page) {
    const main = page.locator('main');
    const next = main.getByRole('button', { name: /^continue$/i }).or(main.getByRole('link', { name: /^continue$/i })).last();
    await Promise.all([page.waitForLoadState('networkidle'), next.click()]);
}

/** Fill every category field of the details step with a valid value (selects, comboboxes, numbers). */
async function fillCategoryFields(page) {
    const form = formWith(page, 'input[name="title"]');
    for (const select of await form.locator('select[name^="attributes["]:not([data-depends-on])').all()) {
        const value = await select.locator('option').evaluateAll((options) => options.map((o) => o.value).find((v) => v !== '' && v !== 'other'));
        if (value) await select.selectOption(value);
    }
    for (const box of await form.locator('[data-combobox]').all()) {
        const input = box.locator('[data-combobox-input]');
        await input.click();
        await box.locator('[role="option"]').first().click();
    }
    for (const select of await form.locator('select[data-depends-on]').all()) {
        const parentName = await select.getAttribute('data-depends-on');
        const parentValue = await form.locator(`[name="${parentName}"]`).first().inputValue();
        const value = await select.locator(`optgroup[data-parent-value="${parentValue}"] option`).first().getAttribute('value');
        if (value) await select.selectOption(value);
    }
    for (const input of await form.locator('input[name^="attributes["][inputmode], input[name^="attributes["][type="number"]').all()) {
        if (!(await input.isVisible()) || (await input.inputValue()) !== '') continue;
        const name = (await input.getAttribute('name')) ?? '';
        const min = Number(await input.getAttribute('min')) || 0;
        const max = Number(await input.getAttribute('max')) || 0;
        let value = name.includes('year') ? 2016 : Math.max(min, 1500);
        if (max && value > max) value = max;
        await input.fill(String(value));
    }
    for (const input of await form.locator('input[type="text"][name^="attributes["]').all()) {
        if ((await input.isVisible()) && (await input.inputValue()) === '' && (await input.getAttribute('required')) !== null) {
            await input.fill('Standard');
        }
    }
}

/** Fill an ad from the category step up to the photos step. */
async function toPhotosStep(page, { categoryPath, country, region, subregion, town, title, price }) {
    await page.goto('/account/listings/create');
    for (const name of categoryPath) {
        await Promise.all([page.waitForLoadState('networkidle'), page.getByRole('link', { name, exact: true }).first().click()]);
    }

    // Location: country, region (and district), town; each choice reloads the step.
    const form = page.locator('form[data-posting-form]');
    const countrySelect = form.locator('select[name="country_id"]');
    const wanted = await countrySelect.locator('option').evaluateAll((options, name) => options.find((o) => o.textContent.includes(name))?.value, country);
    if ((await countrySelect.inputValue()) !== wanted) {
        await Promise.all([page.waitForLoadState('networkidle'), countrySelect.selectOption(wanted)]);
    }
    await Promise.all([page.waitForLoadState('networkidle'), form.locator('select[name="region_id"]').selectOption({ label: region })]);
    if (subregion) {
        await Promise.all([page.waitForLoadState('networkidle'), form.locator('select[name="subregion_id"]').selectOption({ label: subregion })]);
    }
    await pickCombobox(page, 'form[data-posting-form] [data-combobox-input]', town);
    await page.waitForLoadState('networkidle');
    await continueStep(page);

    // Details.
    await expect(page).toHaveURL(/\/edit\/details/);
    await page.locator('input[name="title"]').fill(title);
    await page.locator('textarea[name="description"]').fill('Well maintained, full service history, one careful owner. Viewing welcome on weekends.');
    // Condition is a large choice card (the radio itself is visually hidden): click its label.
    const condition = page.locator('label:has(input[name="condition"][value="used"])');
    if (await condition.count()) await condition.first().click();
    await fillCategoryFields(page);
    await continueStep(page);

    // Price.
    await expect(page).toHaveURL(/\/edit\/price/);
    await page.locator('input[name="price"]').fill(price);
    await continueStep(page);

    await expect(page).toHaveURL(/\/edit\/photos/);
}

/** Post an ad from the category step to "sent for review". */
async function postAd(page, ad) {
    await toPhotosStep(page, ad);
    // Choosing files uploads them with a progress bar, then the step reloads with the photo.
    await page.locator('input[name="photos[]"]').first().setInputFiles(photo);
    await expect(page.locator('img[alt="Photo 1"]')).toBeVisible({ timeout: 30_000 });
    await page.waitForLoadState('networkidle');
    await continueStep(page);

    // Contact and review: send.
    await expect(page).toHaveURL(/\/edit\/review/);
    await Promise.all([page.waitForLoadState('networkidle'), page.getByRole('button', { name: /send my ad|send it again/i }).click()]);
    await expect(page.getByRole('heading', { name: /sent for review|your ad is live/i })).toBeVisible();
}

async function moderate(page, title, decision) {
    await logIn(page, 'moderator@browser.test');
    await page.goto('/admin/moderation');
    await page.locator('[data-queue-item]').filter({ hasText: title }).click();
    await expect(page.locator('#review-title')).toHaveText(title);
    if (decision === 'approve') {
        await page.keyboard.press('a'); // shortcut (resources/js/ui/admin.js)
        await expect(page.getByRole('status')).toContainText(`Approved: ${title}`);
    } else {
        await page.keyboard.press('r');
        await page.locator('#decision-reason input[type="radio"]').first().check();
        await page.locator('#note').fill('Please add a photo of the odometer.');
        await page.getByRole('button', { name: /reject the ad/i }).click();
        await expect(page.getByRole('status')).toContainText(`Rejected: ${title}`);
    }
    await logOut(page);
}

// ---- journeys ------------------------------------------------------------------------------------

test('wrong password is refused with a clear message', async ({ page }) => {
    await page.goto('/login');
    await page.locator('input[name="email"]').fill('seller@browser.test');
    await page.locator('input[name="password"]').fill('not-the-password');
    await page.locator('form button[type="submit"]').first().click();
    await expect(page.getByRole('alert').or(page.locator('[id$="-error"]')).first()).toBeVisible();
    await expect(page).toHaveURL(/\/login/);
});

test('a new seller registers and must verify the email before posting', async ({ page }) => {
    await register(page, seller);
    await page.goto('/account/listings/create');
    await expect(page).toHaveURL(/\/email\/verify/);

    await verify(page, seller);
    await page.goto('/account/listings/create');
    await expect(page).toHaveURL(/\/account\/listings\/create/);
});

test('the seller adds a phone number and shows it to everyone', async ({ page }) => {
    await logIn(page, seller.email);
    await page.goto('/account/settings/profile');
    await pickCombobox(page, '#f-phone_country', 'Sri Lanka');
    await page.locator('input[name="phone"]').fill(seller.phone);
    // The form that holds the field (the header has its own search form).
    await Promise.all([page.waitForLoadState('networkidle'), formWith(page, 'input[name="phone"]').locator('button[type="submit"]').first().click()]);
    await expect(page.locator('input[name="phone"]')).not.toHaveValue('');

    await page.goto('/account/settings/contact');
    await page.locator('input[name="contact_visibility"][value="public"]').check();
    await Promise.all([page.waitForLoadState('networkidle'), formWith(page, 'input[name="contact_visibility"]').locator('button[type="submit"]').first().click()]);
    await expect(page.locator('input[name="contact_visibility"][value="public"]')).toBeChecked();
});

test('an incomplete ad cannot be sent', async ({ page }) => {
    await logIn(page, seller.email);
    await page.goto('/account/listings/create');
    await Promise.all([page.waitForLoadState('networkidle'), page.getByRole('link', { name: 'Vehicles', exact: true }).first().click()]);
    await Promise.all([page.waitForLoadState('networkidle'), page.getByRole('link', { name: 'Cars', exact: true }).first().click()]);
    // Skipping the town and continuing keeps the seller on the location step with errors, or the
    // draft stops at details: either way the review step (and Send) is not reachable yet.
    await continueStep(page);
    await expect(page).not.toHaveURL(/\/edit\/review/);
});

test('Sri Lanka: the seller posts a car with a photo; it waits for review', async ({ page }) => {
    await logIn(page, seller.email);
    await postAd(page, { categoryPath: ['Vehicles', 'Cars'], country: 'Sri Lanka', region: 'Western', subregion: 'Colombo', town: 'Nugegoda', title: carTitle, price: '5,250,000' });

    await page.goto('/account/listings?status=pending');
    await expect(page.getByRole('heading', { name: carTitle }).first()).toBeVisible();
    // Not public yet.
    await logOut(page);
    await page.goto(`/lk/ads?q=${encodeURIComponent(run)}`);
    await expect(page.getByText(carTitle)).toHaveCount(0);
});

test('the moderator approves it with the keyboard shortcut', async ({ page }) => {
    await moderate(page, carTitle, 'approve');
});

test('a guest finds the ad, opens it and reveals the number', async ({ page }) => {
    await page.goto(`/lk/ads?q=${encodeURIComponent(run)}`);
    await Promise.all([page.waitForLoadState('networkidle'), page.getByRole('link', { name: carTitle }).first().click()]);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(carTitle);
    await expect(page.getByText(/Nugegoda/).first()).toBeVisible();
    await expect(page.getByText(/5,250,000/).first()).toBeVisible();
    await expect(page.locator('main')).not.toContainText(seller.phone.slice(-4));

    await page.locator('[data-contact-channel="call"]').first().click();
    await expect(page.locator('main')).toContainText(seller.phone.slice(-4));
});

test('a buyer registers, saves the ad and reports it', async ({ page }) => {
    await register(page, buyer);
    await verify(page, buyer);
    await page.goto(`/lk/ads?q=${encodeURIComponent(run)}`);
    await Promise.all([page.waitForLoadState('networkidle'), page.getByRole('link', { name: carTitle }).first().click()]);

    await page.getByRole('button', { name: /^save/i }).first().click();
    await page.waitForLoadState('networkidle');
    await page.goto('/account/favourites');
    await expect(page.getByRole('heading', { name: carTitle }).first()).toBeVisible();

    await page.goBack();
    await page.getByRole('button', { name: /report/i }).first().click();
    const dialog = page.locator('dialog[open]');
    await dialog.locator('input[type="radio"]').first().check();
    await dialog.locator('textarea').first().fill('Testing the report form.');
    await Promise.all([page.waitForLoadState('networkidle'), dialog.locator('button[type="submit"]').click()]);
    await expect(page.getByRole('status').or(page.getByRole('alert')).first()).toBeVisible();
});

test('the moderator sees the report', async ({ page }) => {
    await logIn(page, 'moderator@browser.test');
    await page.goto('/admin/reports');
    await expect(page.locator('[data-queue-item]').first()).toBeVisible();
    await logOut(page);
});

test('United States: the same seller posts in another country and currency', async ({ page }) => {
    await logIn(page, seller.email);
    await postAd(page, { categoryPath: ['Vehicles', 'Cars'], country: 'United States', region: 'California', subregion: null, town: 'Los Angeles', title: usTitle, price: '18,500' });
    await logOut(page);
    await moderate(page, usTitle, 'approve');

    await page.goto(`/us/ads?q=${encodeURIComponent(run)}`);
    await expect(page.getByRole('heading', { name: usTitle }).first()).toBeVisible();
    await expect(page.getByText(/\$18,500/).first()).toBeVisible();
    // Country separation: not in Sri Lanka's results.
    await page.goto(`/lk/ads?q=${encodeURIComponent(run)}`);
    await expect(page.getByText(usTitle)).toHaveCount(0);
});

test('a rejected ad shows the reason, and the fixed ad goes back to review', async ({ page }) => {
    const title = `Nissan March 2014 journey ${run}`;
    await logIn(page, seller.email);
    await postAd(page, { categoryPath: ['Vehicles', 'Cars'], country: 'Sri Lanka', region: 'Western', subregion: 'Gampaha', town: 'Negombo', title, price: '2,900,000' });
    await logOut(page);
    await moderate(page, title, 'reject');

    await logIn(page, seller.email);
    await page.goto('/account/listings?status=rejected');
    await expect(page.getByRole('heading', { name: title }).first()).toBeVisible();
    await expect(page.getByText(/odometer/)).toBeVisible();

    await page.getByRole('link', { name: new RegExp(`edit|fix`, 'i') }).first().click();
    await page.waitForLoadState('networkidle');
    await page.goto(page.url().replace(/\/edit\/[a-z]+$/, '/edit/review').replace(/\/edit$/, '/edit/review'));
    await Promise.all([page.waitForLoadState('networkidle'), page.getByRole('button', { name: /send it again|send my ad/i }).click()]);
    await expect(page.getByRole('heading', { name: /sent for review/i })).toBeVisible();
});

test('several photos upload one by one; a photo over the size limit is named, not lost silently', async ({ page }) => {
    await logIn(page, seller.email);
    await toPhotosStep(page, { categoryPath: ['Vehicles', 'Cars'], country: 'Sri Lanka', region: 'Western', subregion: 'Colombo', town: 'Nugegoda', title: `Honda Fit 2016 photos ${run}`, price: '4,100,000' });

    // S-03: together these are far over post_max_size; one per request they all get a clear outcome.
    await page.locator('input[name="photos[]"]').first().setInputFiles([
        { name: 'first.jpg', mimeType: 'image/jpeg', buffer: readFileSync(photo) },
        { name: 'second.jpg', mimeType: 'image/jpeg', buffer: readFileSync(photo) },
        { name: 'huge.jpg', mimeType: 'image/jpeg', buffer: Buffer.alloc(9 * 1024 * 1024, 1) },
    ]);

    await expect(page.locator('img[alt="Photo 2"]')).toBeVisible({ timeout: 60_000 });
    await expect(page.getByText(/2 photos added/).first()).toBeVisible();
    await expect(page.getByRole('alert').filter({ hasText: 'huge.jpg' })).toContainText(/larger than 8 MB/);
});

test('a seller orders a Top ad, an admin confirms the bank transfer, and the ad is promoted', async ({ page }) => {
    // Seller: Promote → choose → order → payment instructions with the reference.
    await logIn(page, 'seller@browser.test');
    await page.goto('/account/listings');
    await page.locator('article', { hasText: 'Honda Vezel 2017 hybrid' }).getByRole('link', { name: 'Promote' }).click();
    await page.getByLabel(/Top ad · 7 days/).check();
    await Promise.all([page.waitForLoadState('networkidle'), page.getByRole('button', { name: /order and see payment details/i }).click()]);
    await expect(page.locator('[data-payment-instructions]')).toContainText('0001112223');
    const reference = (await page.locator('[data-order-reference]').textContent()).trim();
    expect(reference).toMatch(/^B[A-Z2-9]{8}$/);
    await logOut(page);

    // Admin: password, then the two-factor code, then Orders → Mark paid.
    await logIn(page, 'admin@browser.test');
    await expect(page).toHaveURL(/two-factor-challenge/);
    await page.locator('input[name="code"]').fill(totp(totpSecret));
    await Promise.all([page.waitForLoadState('networkidle'), page.locator('form button[type="submit"]').first().click()]);
    await page.goto('/admin/orders');
    await page.locator('#o-q').fill(reference);
    await page.getByRole('button', { name: new RegExp(`Mark paid ${reference}`) }).click();
    await page.getByRole('button', { name: 'Payment received' }).click();
    await expect(page.getByText(/promotion has started/)).toBeVisible();
    await logOut(page);

    // Buyers see it in the labelled Top ads.
    await page.goto('/lk/ads');
    await expect(page.locator('[data-top-ads]')).toContainText('Honda Vezel 2017 hybrid');
});
