// Summarise Lighthouse JSON reports for scripts/perf/lighthouse.sh.
//   node summarize.mjs <report.json> <name>   → one Markdown table row
//   node summarize.mjs --median <dir> <page…> → per page: the median of its runs (<dir>/<page>-<n>.report.json),
//                                               then every run with Lighthouse's CPU benchmark index
//   node summarize.mjs --bfcache <reports…>   → back/forward cache failure reasons per page
//
// Slow-machine runs: Lighthouse simulates a slow phone by scaling what it observed, so a busy or hot laptop
// inflates blocking time several-fold (D-146: the nominee deep link went from 99 to 69 while the page was
// unchanged). A run whose benchmark index is below SLOW_RATIO of the session's best is flagged and left out of
// its page's median, as long as one unflagged run remains.
import { readFileSync } from 'node:fs';
import { basename } from 'node:path';

const args = process.argv.slice(2);

const SLOW_RATIO = 0.8;

if (args[0] === '--median') {
    const [dir, ...pages] = args.slice(1);
    const runs = {};

    for (const page of pages) {
        runs[page] = [];

        for (let n = 1; ; n++) {
            let report;
            try {
                report = JSON.parse(readFileSync(`${dir}/${page}-${n}.report.json`, 'utf8'));
            } catch {
                break;
            }
            runs[page].push({
                n,
                perf: Math.round((report.categories.performance?.score ?? 0) * 100),
                a11y: Math.round((report.categories.accessibility?.score ?? 0) * 100),
                bp: Math.round((report.categories['best-practices']?.score ?? 0) * 100),
                seo: Math.round((report.categories.seo?.score ?? 0) * 100),
                lcp: report.audits['largest-contentful-paint']?.numericValue ?? 0,
                cls: report.audits['cumulative-layout-shift']?.numericValue ?? 0,
                tbt: report.audits['total-blocking-time']?.numericValue ?? 0,
                kib: Math.round((report.audits['total-byte-weight']?.numericValue ?? 0) / 1024),
                index: Math.round(report.environment?.benchmarkIndex ?? 0),
            });
        }
    }

    const best = Math.max(...Object.values(runs).flat().map((r) => r.index));
    const slow = (r) => r.index < best * SLOW_RATIO;
    const median = (values) => {
        const sorted = [...values].sort((a, b) => a - b);
        const mid = Math.floor(sorted.length / 2);
        return sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
    };
    const fmt = {
        s: (ms) => `${(ms / 1000).toFixed(1)} s`,
        ms: (ms) => `${Math.round(ms / 10) * 10} ms`,
        cls: (v) => (Math.round(v * 1000) / 1000).toString(),
    };

    console.log('| Page | Perf | A11y | Best pr. | SEO | LCP | CLS | TBT | Transfer | Runs (slow machine) |');
    console.log('|---|---|---|---|---|---|---|---|---|---|');
    for (const [page, list] of Object.entries(runs)) {
        if (list.length === 0) {
            console.log(`| ${page} | no report | | | | | | | | |`);
            continue;
        }
        const clean = list.filter((r) => !slow(r));
        const use = clean.length > 0 ? clean : list;
        const m = (key) => median(use.map((r) => r[key]));
        console.log(
            `| ${page} | ${m('perf')} | ${m('a11y')} | ${m('bp')} | ${m('seo')} | ${fmt.s(m('lcp'))} | ${fmt.cls(m('cls'))} | ${fmt.ms(m('tbt'))} | ${m('kib')} KiB | ${list.length} (${list.length - clean.length}) |`,
        );
    }

    console.log(`\nMedians of each page's runs, leaving out runs whose CPU benchmark index was below ${SLOW_RATIO * 100}% of this session's best (${best}).`);
    console.log('\n| Run | Perf | TBT | LCP | Benchmark index | |');
    console.log('|---|---|---|---|---|---|');
    for (const [page, list] of Object.entries(runs)) {
        for (const r of list) {
            console.log(`| ${page}-${r.n} | ${r.perf} | ${fmt.ms(r.tbt)} | ${fmt.s(r.lcp)} | ${r.index} | ${slow(r) ? 'slow machine' : ''} |`);
        }
    }
    process.exit(0);
}

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
