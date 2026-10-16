// Browser tests (D-1: Playwright): accessibility scans with axe on the key screens, in light and
// dark themes and at phone size. Run: `npm run build`, then `npm run test:browser`
// (set PHP_BINARY when php is not on PATH). See tests/Browser/environment.mjs.
import { defineConfig, devices } from '@playwright/test';
import { baseURL, channel, root, serverCommand, serverEnv } from './tests/Browser/environment.mjs';

const browser = channel === 'chromium' ? {} : { channel };

export default defineConfig({
    testDir: './tests/Browser',
    globalSetup: './tests/Browser/global-setup.mjs',
    fullyParallel: false,
    workers: 1, // one PHP development server
    retries: 0,
    reporter: [['list'], ['html', { open: 'never', outputFolder: 'storage/framework/testing/playwright-report' }]],
    outputDir: 'storage/framework/testing/playwright-results',
    use: {
        baseURL,
        trace: 'retain-on-failure',
    },
    projects: [
        { name: 'desktop-light', use: { ...devices['Desktop Chrome'], ...browser, colorScheme: 'light' } },
        { name: 'desktop-dark', use: { ...devices['Desktop Chrome'], ...browser, colorScheme: 'dark' } },
        { name: 'phone-light', use: { ...devices['Pixel 7'], ...browser, colorScheme: 'light' } },
    ],
    webServer: {
        command: serverCommand,
        cwd: root,
        env: serverEnv(),
        url: `${baseURL}/up`,
        reuseExistingServer: false,
        timeout: 60_000,
    },
});
