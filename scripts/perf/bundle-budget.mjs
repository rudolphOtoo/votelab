#!/usr/bin/env node
// Fails (exit 1) when a built bundle is over its gzipped budget (D-124). Run after `npm run build`:
//   node scripts/perf/bundle-budget.mjs [public/build]
// Each entry's size is its own file plus every chunk it imports statically (what the browser must download
// before the page works), gzipped at level 9 like the server's pre-compressed assets.
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { gzipSync } from 'node:zlib';

const KB = 1024;
const BUDGETS = [
    // Voters on mid-range Android phones over mobile data (UI brief §4).
    { entry: 'resources/js/entries/public.js', limit: 100 * KB },
    { entry: 'resources/css/app.css', limit: 30 * KB },
    // Not voter-facing; generous ceilings so a regression is still caught.
    { entry: 'resources/js/entries/auth.js', limit: 110 * KB },
    { entry: 'resources/js/entries/admin.js', limit: 120 * KB },
    { entry: 'resources/js/entries/stage.js', limit: 120 * KB },
];

const buildDir = process.argv[2] ?? 'public/build';
const manifest = JSON.parse(readFileSync(join(buildDir, 'manifest.json'), 'utf8'));

function files(key, seen = new Set()) {
    const chunk = manifest[key];

    if (!chunk || seen.has(key)) {
        return seen;
    }

    seen.add(key);
    (chunk.imports ?? []).forEach((child) => files(child, seen));

    return seen;
}

const gzipped = (file) => gzipSync(readFileSync(join(buildDir, file)), { level: 9 }).length;
let failed = false;

console.log('| Bundle | Gzipped | Budget | |');
console.log('|---|---|---|---|');

for (const { entry, limit } of BUDGETS) {
    if (!manifest[entry]) {
        console.log(`| ${entry} | missing from manifest | ${limit / KB} KB | FAIL |`);
        failed = true;
        continue;
    }

    const size = [...files(entry)].reduce((total, key) => total + gzipped(manifest[key].file), 0);
    const ok = size <= limit;
    failed ||= !ok;
    console.log(`| ${entry} | ${(size / KB).toFixed(1)} KB | ${limit / KB} KB | ${ok ? 'ok' : 'OVER BUDGET'} |`);
}

process.exit(failed ? 1 : 0);
