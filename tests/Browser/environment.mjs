// Shared set-up for the browser tests (Playwright + axe) and the Lighthouse run: a separate SQLite
// database seeded by BrowserTestSeeder, served by `php artisan serve` with environment overrides for
// that process only (.env is never changed). PHP_BINARY points at PHP when it is not on PATH.
import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

export const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
export const php = process.env.PHP_BINARY || 'php';
export const port = Number(process.env.BROWSER_TEST_PORT || 8130);
export const baseURL = `http://127.0.0.1:${port}`;
export const password = process.env.BROWSER_TEST_PASSWORD || 'browser-test-password';
export const database = resolve(root, 'storage/framework/testing/browser.sqlite');
// Emails of the test server (log mailer): the journeys read verification links from here.
export const mailLog = resolve(root, 'storage/framework/testing/browser-mail.log');

// Browser: the installed Microsoft Edge by default (Chromium-based, no download); set
// PLAYWRIGHT_CHANNEL=chromium after `npx playwright install chromium` (e.g. in CI).
export const channel = process.env.PLAYWRIGHT_CHANNEL || 'msedge';

/** Executable for Lighthouse (chrome-launcher): CHROME_PATH, else the browser of `channel`. */
export async function browserPath() {
    if (process.env.CHROME_PATH) {
        return process.env.CHROME_PATH;
    }
    if (channel === 'chromium') {
        const { chromium } = await import('@playwright/test');

        return chromium.executablePath();
    }
    const candidates = [
        join(process.env['ProgramFiles(x86)'] || '', 'Microsoft', 'Edge', 'Application', 'msedge.exe'),
        join(process.env.ProgramFiles || '', 'Microsoft', 'Edge', 'Application', 'msedge.exe'),
        '/usr/bin/microsoft-edge',
        '/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge',
    ];
    const found = candidates.find((path) => existsSync(path));
    if (!found) {
        throw new Error('Microsoft Edge not found: set CHROME_PATH or PLAYWRIGHT_CHANNEL=chromium.');
    }
    return found;
}

/** Process environment for artisan commands and the server (overrides .env for these processes). */
export function serverEnv() {
    return {
        ...process.env,
        APP_ENV: 'local',
        APP_DEBUG: 'false',
        APP_URL: baseURL,
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: database,
        CACHE_STORE: 'array',
        SESSION_DRIVER: 'file',
        QUEUE_CONNECTION: 'sync',
        MAIL_MAILER: 'log',
        MAIL_LOG_CHANNEL: 'mail',
        MAIL_LOG_PATH: mailLog,
        TURNSTILE_SITE_KEY: '',
        TURNSTILE_SECRET_KEY: '',
        // Indexing on, as in production, so SEO checks see the real robots tags (sign-in pages stay noindex).
        SEO_INDEXING: 'true',
        MARKETPLACE_DEFAULT_COUNTRY: process.env.BROWSER_TEST_COUNTRY || 'LK',
        BROWSER_TEST_PASSWORD: password,
    };
}

/** Fresh database with the browser-test data. Needs a production build (npm run build). */
export function prepare() {
    if (!existsSync(resolve(root, 'public/build/manifest.json'))) {
        throw new Error('No asset build found: run `npm run build` first.');
    }
    mkdirSync(dirname(database), { recursive: true });
    writeFileSync(database, '');
    writeFileSync(mailLog, '');
    execFileSync(php, ['artisan', 'migrate:fresh', '--seed', '--seeder=Database\\Seeders\\BrowserTestSeeder', '--force'], {
        cwd: root,
        env: serverEnv(),
        stdio: 'inherit',
    });
}

/** The command line that serves the app for the tests. */
export const serverCommand = `"${php}" artisan serve --host=127.0.0.1 --port=${port} --no-reload`;
