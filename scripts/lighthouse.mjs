// Lighthouse mobile run on the main public pages (forms/a11y doc §5 targets) against a local
// server with the browser-test data. Run: `npm run build`, then `npm run lighthouse`
// (PHP_BINARY when php is not on PATH; --no-fail to report without failing).
// Reports: storage/framework/testing/lighthouse/*.html. Note: the PHP development server is
// single-threaded and unoptimised, so Performance here is a lower bound, not the production score.
import { spawn, spawnSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import * as chromeLauncher from 'chrome-launcher';
import lighthouse from 'lighthouse';
import { baseURL, browserPath, prepare, root, serverCommand, serverEnv } from '../tests/Browser/environment.mjs';

const TARGETS = { performance: 85, accessibility: 95, 'best-practices': 95, seo: 95 };
const fail = !process.argv.includes('--no-fail');
const out = resolve(root, 'storage/framework/testing/lighthouse');

async function waitFor(url, seconds = 60) {
    for (let i = 0; i < seconds * 2; i++) {
        try {
            if ((await fetch(url)).ok) return;
        } catch {
            // not up yet
        }
        await new Promise((r) => setTimeout(r, 500));
    }
    throw new Error(`Server did not start: ${url}`);
}

// Never measure a server left over from another run (it may have other settings).
try {
    await fetch(`${baseURL}/up`);
    console.error(`Something is already listening on ${baseURL}; stop it first.`);
    process.exit(1);
} catch {
    // free
}

prepare();
const server = spawn(serverCommand, { cwd: root, env: serverEnv(), shell: true, stdio: 'ignore' });
let chrome;
let exitCode = 0;

try {
    await waitFor(`${baseURL}/up`);
    const home = await (await fetch(`${baseURL}/`)).text();
    const ad = home.match(/href="([^"]*\/ad\/[^"]+)"/)?.[1];
    // A category page: keyword searches are noindex by design, category pages are what gets indexed.
    const category = home.match(/href="([^"]*\/[a-z]{2}\/ads\/[a-z0-9-]+)"/)?.[1];
    const pages = {
        home: `${baseURL}/`,
        ...(category ? { category: new URL(category, baseURL).href } : {}),
        ...(ad ? { listing: new URL(ad, baseURL).href } : {}),
        login: `${baseURL}/login`,
    };

    chrome = await chromeLauncher.launch({ chromePath: await browserPath(), chromeFlags: ['--headless=new', '--no-sandbox'] });
    mkdirSync(out, { recursive: true });

    const rows = [];
    for (const [name, url] of Object.entries(pages)) {
        const result = await lighthouse(url, { port: chrome.port, output: 'html', logLevel: 'error', onlyCategories: Object.keys(TARGETS) });
        writeFileSync(resolve(out, `${name}.html`), result.report);
        const scores = Object.fromEntries(Object.keys(TARGETS).map((c) => [c, Math.round((result.lhr.categories[c]?.score ?? 0) * 100)]));
        // Sign-in pages are noindex on purpose: SEO does not apply to them.
        const applies = name === 'login' ? Object.keys(TARGETS).filter((c) => c !== 'seo') : Object.keys(TARGETS);
        if (name === 'login') scores.seo = 'n/a';
        const below = applies.filter((c) => scores[c] < TARGETS[c]);
        rows.push({ page: name, ...scores, status: below.length ? `below: ${below.join(', ')}` : 'ok' });
        if (below.length) exitCode = fail ? 1 : 0;
    }

    console.table(rows);
    console.log(`Targets: ${JSON.stringify(TARGETS)}. Reports in ${out}`);
} catch (error) {
    console.error(error);
    exitCode = 1;
} finally {
    await chrome?.kill();
    // Windows: end the whole tree (shell → artisan serve → php -S) while the shell is still alive.
    if (process.platform === 'win32' && server.pid) {
        spawnSync('taskkill', ['/pid', String(server.pid), '/T', '/F'], { stdio: 'ignore' });
    } else {
        server.kill();
    }
}

process.exit(exitCode);
