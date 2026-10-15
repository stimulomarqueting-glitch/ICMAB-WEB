/**
 * QA (group "appsresearch"): scroll-spy parity sweep. Scrolls the Astro and
 * WordPress applications pages to the same positions (step by step, both
 * directions) and compares which nav pill is aria-current after settling.
 *   node wordpress/tools/qa/appsresearch-spy-sweep.mjs   (env WIDTHS, LANGS, WP_BASE)
 */
import { launch } from './wp-login.mjs';
const ASTRO = 'http://127.0.0.1:4321';
const WP = process.env.WP_BASE || 'http://localhost:8906';
const paths = { astro: { en: '/applications/', es: '/es/applications/' }, wp: { en: '/applications/', es: '/es/aplicaciones/' } };
const widths = (process.env.WIDTHS || '1440,820,390').split(',').map(Number);
const langs = (process.env.LANGS || 'en,es').split(',');
const browser = await launch();
let mismatches = 0;
for (const w of widths) for (const lang of langs) {
  const res = {};
  /* same scroll positions on both sites: only the part of the page both can reach */
  const maxes = {};
  for (const site of ['astro', 'wp']) {
    const ctx = await browser.newContext({ viewport: { width: w, height: 1000 } });
    const page = await ctx.newPage();
    await page.goto((site === 'astro' ? ASTRO : WP) + paths[site][lang], { waitUntil: 'networkidle' });
    await page.evaluate(() => document.fonts.ready);
    maxes[site] = await page.evaluate(() => {
      const nav = document.querySelector('[data-apps-nav]');
      const cards = [...document.querySelectorAll('.app[id]')];
      const last = cards[cards.length - 1];
      return { max: document.documentElement.scrollHeight - innerHeight, end: last ? Math.round(last.getBoundingClientRect().bottom + scrollY) : 0, navTop: nav ? Math.round(nav.getBoundingClientRect().top + scrollY) : 0 };
    });
    await ctx.close();
  }
  const common = Math.min(maxes.astro.max, maxes.wp.max);
  const ys = [];
  for (let y = 0; y <= common; y += 150) ys.push(y);
  for (let y = common; y >= 0; y -= 210) ys.push(y);
  for (const site of ['astro', 'wp']) {
    const ctx = await browser.newContext({ viewport: { width: w, height: 1000 } });
    const page = await ctx.newPage();
    await page.goto((site === 'astro' ? ASTRO : WP) + paths[site][lang], { waitUntil: 'networkidle' });
    await page.evaluate(() => document.fonts.ready);
    const max = maxes[site].max;
    const seq = [];
    for (const y of ys) {
      await page.evaluate((y) => window.scrollTo({ top: y, behavior: 'instant' }), y);
      await page.waitForTimeout(120);
      seq.push(await page.evaluate(() => {
        const p = document.querySelector('[data-apps-nav] [aria-current]');
        return (p && p.getAttribute('data-target')) || '-';
      }));
    }
    res[site] = { max, ys, seq };
    await ctx.close();
  }
  const diff = res.astro.seq.map((s, i) => (s !== res.wp.seq[i] ? `${res.astro.ys[i]}:${s}/${res.wp.seq[i]}` : '')).filter(Boolean);
  mismatches += diff.length;
  console.log(`${lang}-${w} maxScroll astro=${res.astro.max} wp=${res.wp.max} navTop astro=${maxes.astro.navTop} wp=${maxes.wp.navTop} listEnd astro=${maxes.astro.end} wp=${maxes.wp.end} positions=${ys.length} mismatches=${diff.length}`, diff.slice(0, 8).join(' '));
}
console.log('total mismatches:', mismatches);
await browser.close();
