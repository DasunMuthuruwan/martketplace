// F6.1: axe scan (WCAG 2.0 / 2.1 / 2.2 A and AA) of the key screens, as a guest, a seller and a
// moderator, in each project (desktop light, desktop dark, phone). Data: BrowserTestSeeder.
import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { password } from './environment.mjs';

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

async function logIn(page, email) {
    await page.goto('/login');
    await page.locator('input[name="email"]').fill(email);
    await page.locator('input[name="password"]').fill(password);
    await page.locator('form button[type="submit"]').first().click();
    await page.waitForURL((url) => !url.pathname.startsWith('/login'));
}

/** Readable summary: rule, impact, help and the first few elements. */
function summary(violations) {
    return violations.map((v) => `${v.id} (${v.impact}): ${v.help}\n` + v.nodes.slice(0, 3).map((n) => `    ${n.target.join(' ')}`).join('\n')).join('\n');
}

async function scan(page, path, prepare) {
    await page.goto(path);
    if (prepare) {
        await prepare(page);
    }
    await page.waitForLoadState('networkidle');
    const results = await new AxeBuilder({ page }).withTags(TAGS).analyze();
    expect(results.violations, `${path}\n${summary(results.violations)}`).toEqual([]);
}

/** The first live ad's address, from the country page. */
async function firstAdPath(page) {
    await page.goto('/');
    const href = await page.locator('main a[href*="/ad/"]').first().getAttribute('href');
    return new URL(href, page.url()).pathname;
}

test.describe('guest', () => {
    for (const [name, path] of [
        ['country home', '/'],
        ['countries', '/countries'],
        ['search', '/lk/ads?q=toyota'],
        ['log in', '/login'],
        ['register', '/register'],
        ['forgot password', '/forgot-password'],
        ['help page', '/page/terms'],
    ]) {
        test(name, async ({ page }) => {
            await scan(page, path);
        });
    }

    test('seller page', async ({ page }) => {
        await page.goto(await firstAdPath(page));
        const seller = await page.locator('a[href*="/seller/"]').first().getAttribute('href');
        await scan(page, new URL(seller, page.url()).pathname);
    });

    test('listing', async ({ page }) => {
        await scan(page, await firstAdPath(page));
    });

    test('listing with the number shown', async ({ page }) => {
        await scan(page, await firstAdPath(page), async (p) => {
            const reveal = p.locator('[data-contact-channel="call"]').first();
            if (await reveal.isVisible()) {
                await reveal.click();
            }
        });
    });
});

test.describe('seller', () => {
    test.beforeEach(async ({ page }) => {
        await logIn(page, 'seller@browser.test');
    });

    for (const [name, path] of [
        ['account', '/account'],
        ['my ads', '/account/listings'],
        ['saved', '/account/favourites'],
        ['profile', '/account/settings/profile'],
        ['security', '/account/settings/security'],
        ['contact settings', '/account/settings/contact'],
        ['post: category', '/account/listings/create'],
        ['my reviews', '/account/reviews'],
    ]) {
        test(name, async ({ page }) => {
            await scan(page, path);
        });
    }

    test('ad statistics', async ({ page }) => {
        await scan(page, '/account/listings', async (p) => {
            await p.getByRole('link', { name: 'Statistics' }).first().click();
            await p.locator('#per-day').waitFor();
        });
    });
});

test.describe('moderator', () => {
    test.beforeEach(async ({ page }) => {
        await logIn(page, 'moderator@browser.test');
    });

    test('dashboard', async ({ page }) => {
        await scan(page, '/admin');
    });

    test('moderation with an ad open', async ({ page }) => {
        await scan(page, '/admin/moderation', async (p) => {
            await p.locator('[data-queue-item]').first().click();
            await p.locator('#review-title').waitFor();
        });
    });

    test('moderation reason step', async ({ page }) => {
        await scan(page, '/admin/moderation', async (p) => {
            await p.locator('[data-queue-item]').first().click();
            await p.locator('[data-shortcut="r"]').click();
            await p.locator('#decision-reason').waitFor();
        });
    });

    test('reports with a report open', async ({ page }) => {
        await scan(page, '/admin/reports', async (p) => {
            await p.locator('[data-queue-item]').first().click();
            await p.locator('#report-title').waitFor();
        });
    });

    test('recent changes', async ({ page }) => {
        await scan(page, '/admin/moderation/changes');
    });

    test('reviews', async ({ page }) => {
        await scan(page, '/admin/reviews');
    });
});
