// U1: the pages fit every screen, from a small phone to a wide monitor: nothing scrolls sideways
// (WCAG 1.4.10). Screenshots land in storage/framework/testing/responsive for a look by eye.
import { expect, test } from '@playwright/test';
import { password } from './environment.mjs';

const WIDTHS = [320, 390, 768, 1024, 1440, 1920];
const GUEST = [['home', '/lk'], ['search', '/lk/ads?q=toyota'], ['login', '/login'], ['countries', '/countries']];
const SELLER = [['my-ads', '/account/listings'], ['orders', '/account/orders'], ['post', '/account/listings/create']];

async function fits(page, name, width, path) {
    await page.goto(path);
    await page.waitForLoadState('networkidle');
    await page.screenshot({ path: `storage/framework/testing/responsive/${name}-${width}.png`, fullPage: false });
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow, `${path} at ${width}px scrolls sideways`).toBeLessThanOrEqual(0);
}

for (const width of WIDTHS) {
    test(`guest pages fit ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        for (const [name, path] of GUEST) {
            await fits(page, name, width, path);
        }
        await page.goto('/lk/ads?q=toyota');
        const ad = await page.locator('article a').first().getAttribute('href');
        await fits(page, 'ad', width, ad);
    });

    test(`seller pages fit ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/login');
        await page.locator('input[name="email"]').fill('seller@browser.test');
        await page.locator('input[name="password"]').fill(password);
        await page.locator('form button[type="submit"]').first().click();
        await page.waitForURL((url) => !url.pathname.startsWith('/login'));
        for (const [name, path] of SELLER) {
            await fits(page, name, width, path);
        }
    });
}
