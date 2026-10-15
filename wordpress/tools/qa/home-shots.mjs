/**
 * QA (group "home"): screenshots of the four home sections (value flow,
 * product teaser, applications teaser, research/team strip) in the Astro
 * reference and in WordPress, EN + ES, at 1440 / 820 / 390 px, plus box
 * metrics of the key elements.
 *   node wordpress/tools/qa/home-shots.mjs <outDir> [reduce|motion]
 * reduce = prefers-reduced-motion (static frame, comparable pixel by pixel)
 * motion = normal motion; every .reveal gets .is-visible (what the
 *          site-wide reveal script does) and the entrance is let to finish.
 */
import { launch } from './wp-login.mjs';
import fs from 'node:fs';

const out = process.argv[2] || '/tmp/home';
const mode = process.argv[3] || 'reduce';
const WP = process.env.WP_BASE || 'http://localhost:8903';
const ASTRO = 'http://127.0.0.1:4321';
fs.mkdirSync(out, { recursive: true });

const targets = [
  { name: 'astro-en', url: `${ASTRO}/` },
  { name: 'astro-es', url: `${ASTRO}/es/` },
  { name: 'wp-en', url: `${WP}/` },
  { name: 'wp-es', url: `${WP}/es/inicio/` },
];
const widths = (process.env.WIDTHS || '1440,820,390').split(',').map(Number);
const sections = {
  value: 'section:has(> .container > ol.value)',
  product: '#technology',
  apps: '#applications',
  explore: 'section.explore',
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
    /* load lazy images */
    await page.evaluate(async () => {
      for (let y = 0; y < document.body.scrollHeight; y += 600) { window.scrollTo(0, y); await new Promise((r) => setTimeout(r, 60)); }
      window.scrollTo(0, 0);
      await Promise.all([...document.images].map((i) => (i.complete ? null : new Promise((r) => { i.onload = i.onerror = r; }))));
    });
    if (mode !== 'reduce') {
      await page.evaluate(() => document.querySelectorAll('.reveal').forEach((el) => el.classList.add('is-visible')));
      await page.waitForTimeout(3200);
    } else {
      await page.waitForTimeout(400);
    }
    for (const [key, sel] of Object.entries(sections)) {
      const loc = page.locator(sel).first();
      if (!(await loc.count())) { errors.push('missing ' + key); continue; }
      /* isolate the section (hide the other sections of <main>) so it starts
         at the same y on both sites: no sub-pixel anti-aliasing noise */
      await page.evaluate((sel) => {
        const target = document.querySelector(sel);
        const main = target.closest('main') || target.parentElement;
        [...main.children].forEach((el) => { el.style.display = el.contains(target) ? '' : 'none'; });
        window.scrollTo(0, 0);
      }, sel);
      await page.waitForTimeout(150);
      await loc.screenshot({ path: `${out}/${key}-${t.name}-${w}.png`, animations: 'disabled' });
      await page.evaluate(() => { const main = document.querySelector('main'); [...main.children].forEach((el) => { el.style.display = ''; }); });
    }
    const m = await page.evaluate((sections) => {
      const box = (e) => { if (!e) return null; const r = e.getBoundingClientRect(); return [Math.round(r.left), Math.round(r.width), Math.round(r.height)]; };
      const q = (s) => document.querySelector(s);
      const res = {};
      for (const [k, s] of Object.entries(sections)) res[k] = box(q(s));
      res.valueItems = [...document.querySelectorAll('.value__item')].map(box);
      res.arrow = box(q('.value__arrow'));
      res.brandTag = box(q('.value__tag--brand'));
      res.ptPhoto = box(q('.pt__photo'));
      res.ptCopy = box(q('.pt__grid > div'));
      res.isotype = box(q('.isotype'));
      res.isoSvg = box(q('.isotype__svg'));
      res.chips = [...document.querySelectorAll('.apps__chip')].map(box);
      res.appsCta = box(q('.apps__cta .btn'));
      res.cards = [...document.querySelectorAll('.explore__card')].map(box);
      res.labels = [...document.querySelectorAll('.iso__label')].map((e) => { const b = e.getBBox(); return [Math.round(b.x), Math.round(b.width)]; });
      return res;
    }, sections);
    report[`${t.name}-${w}`] = { ...m, errors };
    await ctx.close();
  }
}
fs.writeFileSync(`${out}/report.json`, JSON.stringify(report, null, 1));
for (const [k, v] of Object.entries(report)) console.log(k, JSON.stringify(v));
await browser.close();
