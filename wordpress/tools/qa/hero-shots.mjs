/**
 * QA (group "hero"): screenshots of the home hero in the Astro reference and
 * in WordPress, EN + ES, at 1440 / 820 / 390 px.
 *   node wordpress/tools/qa/hero-shots.mjs <outDir> [reduce|motion]
 * reduce = prefers-reduced-motion (static frame, comparable pixel by pixel)
 * motion = normal motion, captured after the entrance animation.
 */
import { launch } from './wp-login.mjs';
import fs from 'node:fs';

const out = process.argv[2] || '/tmp/hero';
const mode = process.argv[3] || 'reduce';
const WP = process.env.WP_BASE || 'http://localhost:8901';
const ASTRO = 'http://127.0.0.1:4321';
fs.mkdirSync(out, { recursive: true });

const targets = [
  { name: 'astro-en', url: `${ASTRO}/` },
  { name: 'astro-es', url: `${ASTRO}/es/` },
  { name: 'wp-en', url: `${WP}/` },
  { name: 'wp-es', url: `${WP}/es/inicio/` },
];
const widths = [1440, 820, 390];

const browser = await launch();
const report = {};
for (const w of widths) {
  for (const t of targets) {
    const ctx = await browser.newContext({ viewport: { width: w, height: 1200 }, reducedMotion: mode === 'reduce' ? 'reduce' : 'no-preference' });
    const page = await ctx.newPage();
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));
    page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
    await page.goto(t.url, { waitUntil: 'networkidle' });
    await page.evaluate(() => document.fonts.ready);
    await page.waitForTimeout(mode === 'reduce' ? 600 : 2600);
    const sec = page.locator('section.field').first();
    await sec.screenshot({ path: `${out}/${t.name}-${w}.png`, animations: mode === 'reduce' ? 'disabled' : 'allow' });
    const m = await page.evaluate(() => {
      const q = (s) => document.querySelector(s);
      const box = (s) => { const e = q(s); if (!e) return null; const r = e.getBoundingClientRect(); return [Math.round(r.left), Math.round(r.top + scrollY), Math.round(r.width), Math.round(r.height)]; };
      const cs = (s, p) => { const e = q(s); return e ? getComputedStyle(e)[p] : null; };
      return {
        section: box('section.field'), title: box('.field__title'), claim: box('.field__claim'), lead: box('.field__lead'),
        ctas: box('.field__ctas'), eyebrow: box('.field__eyebrow'), card: box('.plate__card'), marquee: box('.field__marquee'),
        titleFont: cs('.field__title', 'fontSize') + ' ' + cs('.field__title', 'fontFamily'),
        canvas: (() => { const c = q('canvas[data-vapour]'); return c ? [c.width, c.height] : null; })(),
      };
    });
    report[`${t.name}-${w}`] = { ...m, errors };
    await ctx.close();
  }
}
fs.writeFileSync(`${out}/report.json`, JSON.stringify(report, null, 1));
console.log(JSON.stringify(report, null, 0).replace(/\},"/g, '},\n"'));
await browser.close();
