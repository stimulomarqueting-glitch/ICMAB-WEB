/** QA (group "benefits"): exact (fractional) boxes of the benefits band after isolating it.
 *  node wordpress/tools/qa/benefits-probe.mjs <width> <astroUrl> <wpUrl> [selector...] */
import { launch } from './wp-login.mjs';
const [w, aUrl, wUrl, ...sels] = process.argv.slice(2);
const browser = await launch();
for (const url of [aUrl, wUrl]) {
  const ctx = await browser.newContext({ viewport: { width: Number(w), height: 1000 }, reducedMotion: 'reduce' });
  const page = await ctx.newPage();
  await page.goto(url, { waitUntil: 'networkidle' });
  await page.evaluate(() => document.fonts.ready);
  const r = await page.evaluate((sels) => {
    const target = document.querySelector(sels[0]);
    const main = target.closest('main');
    [...main.children].forEach((el) => { el.style.display = el.contains(target) ? '' : 'none'; });
    window.scrollTo(0, 0);
    const out = {};
    for (const s of sels) out[s] = [...document.querySelectorAll(s)].slice(0, 4).map((e) => { const b = e.getBoundingClientRect(); return [+b.x.toFixed(3), +b.y.toFixed(3), +b.width.toFixed(3), +b.height.toFixed(3)]; });
    out.header = (() => { const h = document.querySelector('header'); const b = h.getBoundingClientRect(); return [b.y, b.height]; })();
    return out;
  }, sels);
  console.log(url, JSON.stringify(r));
  await ctx.close();
}
await browser.close();
