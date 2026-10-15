/**
 * QA (group "benefits"): screenshots of the benefits band (home + product)
 * and the "Who it's for" panels (home) in the Astro reference and in
 * WordPress, EN + ES, plus box metrics of the key elements.
 *   node wordpress/tools/qa/benefits-shots.mjs <outDir> [reduce|motion]
 * reduce = prefers-reduced-motion (static frame, comparable pixel by pixel)
 * motion = normal motion; every .reveal gets .is-visible (what the
 *          site-wide reveal script does), the audiences cycling is paused
 *          (document.hidden + visibilitychange) and panel 0 is active.
 * STATE=hover2 additionally hovers the third audience panel and the second
 * benefit card before the shot.
 */
import { launch } from './wp-login.mjs';
import fs from 'node:fs';

const out = process.argv[2] || '/tmp/benefits';
const mode = process.argv[3] || 'reduce';
const state = process.env.STATE || '';
const WP = process.env.WP_BASE || 'http://localhost:8904';
const ASTRO = 'http://127.0.0.1:4321';
fs.mkdirSync(out, { recursive: true });

const targets = [
  { name: 'astro-en', url: `${ASTRO}/`, page: 'home' },
  { name: 'astro-es', url: `${ASTRO}/es/`, page: 'home' },
  { name: 'wp-en', url: `${WP}/`, page: 'home' },
  { name: 'wp-es', url: `${WP}/es/inicio/`, page: 'home' },
  { name: 'astro-en', url: `${ASTRO}/product/`, page: 'product' },
  { name: 'astro-es', url: `${ASTRO}/es/product/`, page: 'product' },
  { name: 'wp-en', url: `${WP}/product/`, page: 'product' },
  { name: 'wp-es', url: `${WP}/es/producto/`, page: 'product' },
];
const widths = (process.env.WIDTHS || '1440,820,390').split(',').map(Number);
const sections = {
  home: { benefits: 'section.bene--dark', audiences: '#who-is-it-for' },
  product: { benefits: 'section.bene--dark' },
};

const browser = await launch();
const report = {};
for (const w of widths) {
  for (const t of targets) {
    const ctx = await browser.newContext({ viewport: { width: w, height: 1000 }, reducedMotion: mode === 'reduce' ? 'reduce' : 'no-preference' });
    const page = await ctx.newPage();
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));
    page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
    await page.goto(t.url, { waitUntil: 'networkidle' });
    await page.evaluate(() => document.fonts.ready);
    if (mode !== 'reduce') {
      await page.evaluate(() => {
        Object.defineProperty(document, 'hidden', { configurable: true, get: () => true });
        document.dispatchEvent(new Event('visibilitychange'));
        document.querySelectorAll('.reveal').forEach((el) => el.classList.add('is-visible'));
        document.querySelectorAll('.aud__panel').forEach((p, k) => p.toggleAttribute('data-active', k === 0));
      });
      await page.waitForTimeout(2600);
    } else {
      await page.waitForTimeout(300);
    }
    for (const [key, sel] of Object.entries(sections[t.page])) {
      const loc = page.locator(sel).first();
      if (!(await loc.count())) { errors.push('missing ' + key); continue; }
      /* isolate the section (hide the other sections of <main>) so it starts
         at the same y on both sites */
      await page.evaluate((sel) => {
        const target = document.querySelector(sel);
        const main = target.closest('main') || target.parentElement;
        [...main.children].forEach((el) => { el.style.display = el.contains(target) ? '' : 'none'; });
        window.scrollTo(0, 0);
      }, sel);
      await page.waitForTimeout(150);
      if (state === 'hover2') {
        const hov = key === 'audiences' ? page.locator('.aud__panel').nth(2) : page.locator('.bene__outcome').nth(1);
        const box = await hov.boundingBox();
        await page.mouse.move(box.x + box.width * 0.3, box.y + box.height * 0.5);
        await page.waitForTimeout(1300);
      }
      await loc.screenshot({ path: `${out}/${t.page}-${key}-${t.name}-${w}.png`, animations: 'disabled' });
      await page.mouse.move(0, 0);
      await page.evaluate(() => { const main = document.querySelector('main'); [...main.children].forEach((el) => { el.style.display = ''; }); });
    }
    const m = await page.evaluate(() => {
      const box = (e) => { if (!e) return null; const r = e.getBoundingClientRect(); return [Math.round(r.left), Math.round(r.top), Math.round(r.width), Math.round(r.height)]; };
      const q = (s) => document.querySelector(s);
      const res = {};
      res.bene = box(q('section.bene--dark'));
      res.beneHead = box(q('.bene--dark .h2'));
      res.beneCards = [...document.querySelectorAll('.bene__outcome')].map(box);
      res.aud = box(q('#who-is-it-for'));
      res.audPanels = [...document.querySelectorAll('.aud__panel')].map(box);
      res.audIcon = box(q('.aud__icon svg'));
      return res;
    });
    report[`${t.page}-${t.name}-${w}`] = { ...m, errors };
    await ctx.close();
  }
}
fs.writeFileSync(`${out}/report.json`, JSON.stringify(report, null, 1));
for (const [k, v] of Object.entries(report)) console.log(k, JSON.stringify(v));
await browser.close();
