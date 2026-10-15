/**
 * QA (group "team"): behaviour of the team showcase in Astro vs WordPress —
 * autoplay (5.5s), pause on hover, manual pick (click), arrow keys, focus
 * pause, reduced motion (no autoplay), plus a screenshot of every person
 * (picked through the avatars) in both sites for a pixel comparison.
 *   node wordpress/tools/qa/team-behaviour.mjs <outDir>
 */
import { launch } from './wp-login.mjs';
import fs from 'node:fs';

const out = process.argv[2] || '/tmp/team-behaviour';
fs.mkdirSync(out, { recursive: true });
const ASTRO = 'http://127.0.0.1:4321';
const WP = process.env.WP_BASE || 'http://localhost:8907';
const urls = { astro: { en: '/team/', es: '/es/team/' }, wp: { en: '/team/', es: '/es/equipo/' } };
const widths = (process.env.WIDTHS || '1440,390').split(',').map(Number);

const state = (page) =>
  page.evaluate(() => {
    const root = document.querySelector('[data-showcase]');
    const idx = (s) => [...root.querySelectorAll(s)].findIndex((el) => el.hasAttribute('data-active'));
    return {
      person: idx('.show__person'),
      bio: idx('.show__bio'),
      meta: idx('.show__meta'),
      pressed: [...root.querySelectorAll('.show__avatar')].map((b) => b.getAttribute('aria-pressed')).join(','),
      focus: document.activeElement && document.activeElement.className,
    };
  });

const browser = await launch();
const results = {};
for (const site of ['astro', 'wp']) {
  const log = [];
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  await page.goto((site === 'astro' ? ASTRO : WP) + urls[site].en, { waitUntil: 'networkidle' });
  await page.mouse.move(1, 999); // pointer outside the section (below it is the next section)
  await page.mouse.move(5, 5);
  log.push(['initial', await state(page)]);
  await page.waitForTimeout(6000);
  log.push(['after 6s (autoplay)', await state(page)]);
  await page.waitForTimeout(5500);
  log.push(['after 11.5s', await state(page)]);
  /* hover pauses */
  await page.mouse.move(700, 500);
  await page.waitForTimeout(6500);
  log.push(['hovered 6.5s (paused)', await state(page)]);
  /* manual pick */
  await page.locator('.show__avatar').nth(3).click();
  await page.waitForTimeout(300);
  log.push(['clicked avatar 3', await state(page)]);
  await page.keyboard.press('ArrowRight');
  await page.waitForTimeout(300);
  log.push(['ArrowRight', await state(page)]);
  await page.keyboard.press('ArrowLeft');
  await page.keyboard.press('ArrowLeft');
  await page.waitForTimeout(300);
  log.push(['ArrowLeft x2', await state(page)]);
  /* focus inside pauses even with the pointer out */
  await page.mouse.move(5, 5);
  await page.mouse.move(1430, 990);
  await page.waitForTimeout(6500);
  log.push(['focused, pointer out 6.5s (paused)', await state(page)]);
  await page.evaluate(() => document.activeElement.blur());
  await page.waitForTimeout(6000);
  log.push(['blurred 6s (resumed)', await state(page)]);
  results[site] = { log, errors };
  await ctx.close();

  /* reduced motion: never autoplays */
  const ctx2 = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
  const p2 = await ctx2.newPage();
  await p2.goto((site === 'astro' ? ASTRO : WP) + urls[site].en, { waitUntil: 'networkidle' });
  await p2.mouse.move(5, 5);
  await p2.waitForTimeout(6500);
  results[site].reduced = await state(p2);
  await ctx2.close();

  /* every person, picked through the avatars, both languages and widths */
  for (const w of widths) {
    for (const lang of ['en', 'es']) {
      const ctx3 = await browser.newContext({ viewport: { width: w, height: 1000 }, reducedMotion: 'reduce' });
      const p3 = await ctx3.newPage();
      await p3.goto((site === 'astro' ? ASTRO : WP) + urls[site][lang], { waitUntil: 'networkidle' });
      await p3.evaluate(() => document.fonts.ready);
      const n = await p3.locator('.show__avatar').count();
      for (let i = 0; i < n; i++) {
        await p3.locator('.show__avatar').nth(i).click();
        await p3.mouse.move(0, 0);
        await p3.waitForTimeout(250);
        await p3.locator('section.show').screenshot({ path: `${out}/person${i}-${w}-${lang}-${site}.png` });
      }
      await ctx3.close();
    }
  }
}
fs.writeFileSync(`${out}/behaviour.json`, JSON.stringify(results, null, 1));
for (const site of ['astro', 'wp']) {
  console.log('==', site, results[site].errors.length ? results[site].errors : 'no errors');
  for (const [k, v] of results[site].log) console.log('  ', k.padEnd(36), JSON.stringify(v));
  console.log('   reduced motion after 6.5s'.padEnd(39), JSON.stringify(results[site].reduced));
}
await browser.close();
