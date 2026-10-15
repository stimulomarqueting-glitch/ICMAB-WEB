/**
 * QA (group "home"): hover / focus states of the home sections, Astro vs
 * WordPress (EN + ES, 1440 px, normal motion):
 *  - product teaser: pointer over the grid → the photo tilts;
 *  - research/team strip: pointer over a card → lift + violet wash;
 *  - applications teaser: pointer over a chip → chip style + its field
 *    lights up in the isotype (data-hot via view.js); keyboard focus too.
 *   node wordpress/tools/qa/home-hover.mjs <outDir>
 */
import { launch } from './wp-login.mjs';
import fs from 'node:fs';

const out = process.argv[2] || '/tmp/home-hover';
const WP = process.env.WP_BASE || 'http://localhost:8903';
const ASTRO = 'http://127.0.0.1:4321';
fs.mkdirSync(out, { recursive: true });

const targets = [
  { name: 'astro-en', url: `${ASTRO}/` },
  { name: 'astro-es', url: `${ASTRO}/es/` },
  { name: 'wp-en', url: `${WP}/` },
  { name: 'wp-es', url: `${WP}/es/inicio/` },
];

async function isolate(page, sel) {
  await page.evaluate((sel) => {
    const target = document.querySelector(sel);
    const main = target.closest('main');
    [...main.children].forEach((el) => { el.style.display = el.contains(target) ? '' : 'none'; });
    window.scrollTo(0, 0);
  }, sel);
  await page.waitForTimeout(100);
}

const browser = await launch();
const report = {};
for (const t of targets) {
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 1100 } });
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
  await page.goto(t.url, { waitUntil: 'networkidle' });
  await page.evaluate(() => document.fonts.ready);
  await page.evaluate(async () => {
    for (let y = 0; y < document.body.scrollHeight; y += 600) { window.scrollTo(0, y); await new Promise((r) => setTimeout(r, 50)); }
    await Promise.all([...document.images].map((i) => (i.complete ? null : new Promise((r) => { i.onload = i.onerror = r; }))));
    document.querySelectorAll('.reveal').forEach((el) => el.classList.add('is-visible'));
  });
  await page.waitForTimeout(3000);
  const r = {};

  /* product tilt */
  await isolate(page, '#technology');
  await page.hover('#technology .pt__grid', { position: { x: 300, y: 100 } });
  await page.waitForTimeout(1000);
  r.photoTransform = await page.$eval('.pt__photo', (e) => getComputedStyle(e).transform);
  await page.locator('#technology').screenshot({ path: `${out}/product-${t.name}.png` });
  await page.mouse.move(5, 5);

  /* explore card */
  await isolate(page, 'section.explore');
  await page.hover('section.explore .explore__card >> nth=1');
  await page.waitForTimeout(700);
  r.cardTransform = await page.$eval('.explore__card:nth-child(2)', (e) => getComputedStyle(e).transform);
  await page.locator('section.explore').screenshot({ path: `${out}/explore-${t.name}.png` });
  await page.mouse.move(5, 5);

  /* chip → callout */
  await isolate(page, '#applications');
  await page.hover('[data-apps-links] [data-field="sensors"]');
  await page.waitForTimeout(600);
  r.hotOnHover = await page.$$eval('.iso__callout[data-hot]', (els) => els.map((e) => e.dataset.field));
  r.ringStroke = await page.$eval('.iso__callout[data-field="sensors"] .iso__ring', (e) => getComputedStyle(e).stroke + ' ' + getComputedStyle(e).strokeWidth);
  await page.locator('#applications').screenshot({ path: `${out}/apps-${t.name}.png` });
  await page.mouse.move(5, 5);
  await page.waitForTimeout(300);
  r.hotAfterLeave = await page.$$eval('.iso__callout[data-hot]', (els) => els.map((e) => e.dataset.field));
  await page.focus('[data-apps-links] [data-field="spm-sample-preparation"]');
  await page.waitForTimeout(100);
  r.hotOnFocus = await page.$$eval('.iso__callout[data-hot]', (els) => els.map((e) => e.dataset.field));
  await page.evaluate(() => document.activeElement.blur());
  await page.waitForTimeout(100);
  r.hotAfterBlur = await page.$$eval('.iso__callout[data-hot]', (els) => els.map((e) => e.dataset.field));

  report[t.name] = { ...r, errors };
  await ctx.close();
}
fs.writeFileSync(`${out}/report.json`, JSON.stringify(report, null, 1));
for (const [k, v] of Object.entries(report)) console.log(k, JSON.stringify(v));
await browser.close();
