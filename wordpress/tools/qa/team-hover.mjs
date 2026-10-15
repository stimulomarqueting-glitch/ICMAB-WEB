/**
 * QA (group "team"): hover/focus states of the showcase (avatar thumb,
 * LinkedIn link, focus-visible ring) and the journey note link, Astro vs WP.
 *   node wordpress/tools/qa/team-hover.mjs <outDir>
 */
import { launch } from './wp-login.mjs';
import fs from 'node:fs';
const out = process.argv[2] || '/tmp/team-hover';
fs.mkdirSync(out, { recursive: true });
const sites = { astro: 'http://127.0.0.1:4321/team/', wp: (process.env.WP_BASE || 'http://localhost:8907') + '/team/' };
const browser = await launch();
const res = {};
for (const [site, url] of Object.entries(sites)) {
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
  const page = await ctx.newPage();
  await page.goto(url, { waitUntil: 'networkidle' });
  const r = {};
  const style = (sel, props) => page.evaluate(([s, p]) => { const cs = getComputedStyle(document.querySelector(s)); return p.map((k) => cs[k]).join(' | '); }, [sel, props]);
  r.thumb2 = await style('.show__avatar[data-index="2"] .show__thumb', ['opacity', 'transform']);
  await page.hover('.show__avatar[data-index="2"]');
  await page.waitForTimeout(100);
  r.thumb2Hover = await style('.show__avatar[data-index="2"] .show__thumb', ['opacity', 'transform']);
  await page.locator('.show__picker').screenshot({ path: `${out}/picker-hover-${site}.png` });
  await page.hover('.show__meta[data-active] .show__link');
  await page.waitForTimeout(100);
  r.linkHover = await style('.show__meta[data-active] .show__link', ['color', 'textDecorationColor']);
  await page.mouse.move(0, 0);
  await page.keyboard.press('Tab');
  for (let i = 0; i < 30; i++) {
    const cls = await page.evaluate(() => document.activeElement.className);
    if (cls === 'show__avatar') break;
    await page.keyboard.press('Tab');
  }
  r.focus = await page.evaluate(() => { const cs = getComputedStyle(document.activeElement); return document.activeElement.className + ' ' + cs.outlineStyle + ' ' + cs.outlineWidth + ' ' + cs.outlineColor + ' ' + cs.outlineOffset; });
  await page.locator('.show__picker').screenshot({ path: `${out}/picker-focus-${site}.png` });
  const note = site === 'astro' ? '.journey__note a' : '.tjourney .journey__note a';
  await page.locator(note).scrollIntoViewIfNeeded();
  r.noteLink = await style(note, ['color', 'fontWeight', 'marginLeft']);
  await page.hover(note);
  r.noteLinkHover = await style(note, ['color', 'textDecorationLine']);
  res[site] = r;
  await ctx.close();
}
for (const k of Object.keys(res.astro)) console.log(k.padEnd(14), res.astro[k] === res.wp[k] ? 'SAME' : 'DIFF', '|', res.astro[k], '||', res.wp[k]);
await browser.close();
