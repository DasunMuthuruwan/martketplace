// Copies the Lucide icons the UI uses (design system §7) from lucide-static into resources/icons,
// so the server renders them inline without node_modules (cPanel hosts have no Node at runtime).
// Run `npm run icons` after changing the list; the copied files are committed.
import { copyFileSync, existsSync, mkdirSync, readdirSync, unlinkSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const source = join(root, 'node_modules', 'lucide-static', 'icons');
const target = join(root, 'resources', 'icons');

export const ICONS = [
    // Navigation and actions
    'house', 'search', 'plus', 'heart', 'user', 'circle-user', 'menu', 'x', 'ellipsis',
    'chevron-down', 'chevron-up', 'chevron-left', 'chevron-right', 'external-link', 'log-out', 'settings', 'bell',
    // Theme
    'sun', 'moon', 'monitor',
    // Listing and contact
    'phone', 'message-circle', 'share-2', 'flag', 'map-pin', 'clock', 'image', 'camera', 'upload',
    'trash-2', 'pencil', 'eye', 'eye-off', 'globe',
    // Status
    'check', 'circle-check', 'circle-alert', 'triangle-alert', 'info', 'shield-check', 'badge-check', 'loader-circle',
    // Search
    'sliders-horizontal', 'arrow-up-down', 'layout-grid', 'list',
    // Categories
    'car', 'land-plot', 'wrench', 'smartphone', 'laptop', 'sofa', 'package',
    // Admin
    'keyboard', 'rotate-ccw', 'circle-x', 'ban', 'inbox', 'users',
];

if (!existsSync(source)) {
    console.error('lucide-static is not installed: run npm install first.');
    process.exit(1);
}
mkdirSync(target, { recursive: true });

const wanted = new Set(ICONS.map((name) => `${name}.svg`));
for (const file of readdirSync(target)) {
    if (file.endsWith('.svg') && !wanted.has(file)) {
        unlinkSync(join(target, file));
    }
}
for (const name of ICONS) {
    const file = join(source, `${name}.svg`);
    if (!existsSync(file)) {
        console.error(`Unknown Lucide icon: ${name}`);
        process.exit(1);
    }
    copyFileSync(file, join(target, `${name}.svg`));
}
console.log(`Copied ${ICONS.length} icons to resources/icons.`);
