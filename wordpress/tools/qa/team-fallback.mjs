/**
 * QA (group "team"): the showcase's no-profile fallback vs the verified
 * page-hero block with the same copy (team-fallback-ref.php), pixel diff.
 * Run with every team record set to draft (team-toggle.php draft).
 *   node wordpress/tools/qa/team-fallback.mjs <outDir>
 */
import { launch } from './wp-login.mjs';
import fs from 'node:fs';
const out = process.argv[2] || '/tmp/team-fallback';
fs.mkdirSync(out, { recursive: true });
const WP = process.env.WP_BASE || 'http://localhost:8907';
const pages = { en: { show: '/team/', ref: '/team-ref-en/' }, es: { show: '/es/equipo/', ref: '/es/team-ref-es/' } };
const browser = await launch();
for (const w of [1440, 820, 390]) {
  for (const lang of ['en', 'es']) {
    for (const kind of ['show', 'ref']) {
      for (const mode of ['reduce', 'no-preference']) {
        const ctx = await browser.newContext({ viewport: { width: w, height: 1000 }, reducedMotion: mode });
        const page = await ctx.newPage();
        await page.goto(WP + pages[lang][kind], { waitUntil: 'networkidle' });
        await page.evaluate(() => document.fonts.ready);
        await page.waitForTimeout(mode === 'reduce' ? 100 : 1500);
        const box = await page.locator('main').evaluate((m) => { const r = m.getBoundingClientRect(); return { y: r.y + scrollY }; });
        await page.screenshot({ path: `${out}/fb-${w}-${lang}-${mode}-${kind}.png`, clip: { x: 0, y: box.y, width: w, height: 520 } });
        await ctx.close();
      }
    }
  }
}
await browser.close();
console.log('done');
