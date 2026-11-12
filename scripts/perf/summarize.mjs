// Summarise Lighthouse JSON reports for scripts/perf/lighthouse.sh.
//   node summarize.mjs <report.json> <name>   → one Markdown table row
//   node summarize.mjs --bfcache <reports…>   → back/forward cache failure reasons per page
import { readFileSync } from 'node:fs';
import { basename } from 'node:path';

const args = process.argv.slice(2);

if (args[0] === '--bfcache') {
    for (const file of args.slice(1)) {
        const audit = JSON.parse(readFileSync(file, 'utf8')).audits['bf-cache'];
        const reasons = (audit?.details?.items ?? []).map((i) => `${i.reason} (${i.failureType})`);
        console.log(`bfcache ${basename(file, '.report.json')}: ${reasons.length ? reasons.join('; ') : 'eligible'}`);
    }
    process.exit(0);
}

const [file, name] = args;
const report = JSON.parse(readFileSync(file, 'utf8'));
const score = (key) => Math.round((report.categories[key]?.score ?? 0) * 100);
const audit = (key) => report.audits[key]?.displayValue ?? '–';
const kib = Math.round((report.audits['total-byte-weight']?.numericValue ?? 0) / 1024);
console.log(`| ${name} | ${score('performance')} | ${score('accessibility')} | ${score('best-practices')} | ${score('seo')} | ${audit('largest-contentful-paint')} | ${audit('cumulative-layout-shift')} | ${audit('total-blocking-time')} | ${kib} KiB |`);
