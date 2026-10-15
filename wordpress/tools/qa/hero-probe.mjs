/** QA (group "hero"): prints fractional boxes/computed styles of hero parts in Astro vs WP. */
import { launch } from './wp-login.mjs';
const WP = process.env.WP_BASE || 'http://localhost:8901';
const w = +(process.argv[2] || 390);
const sels = (process.argv[3] || '.field__track li,.field__marquee,.field__track,.field__strip').split(',');
const browser = await launch();
for (const url of ['http://127.0.0.1:4321/', `${WP}/`]) {
  const ctx = await browser.newContext({ viewport: { width: w, height: +(process.env.VH || 1000) }, reducedMotion: 'reduce' });
  const page = await ctx.newPage();
  await page.goto(url, { waitUntil: 'networkidle' });
  await page.evaluate(() => document.fonts.ready);
  const r = await page.evaluate((sels) => sels.map((s) => {
    const e = document.querySelector(s); if (!e) return s + ': none';
    const b = e.getBoundingClientRect(); const cs = getComputedStyle(e);
    return `${s}: ${b.left.toFixed(2)},${(b.top + scrollY).toFixed(2)} ${b.width.toFixed(2)}x${b.height.toFixed(2)} lh=${cs.lineHeight} fs=${cs.fontSize} ff=${cs.fontFamily.slice(0, 20)} tr=${cs.transform} anim=${cs.animationName}`;
  }), sels);
  console.log(url, '\n  ' + r.join('\n  '));
  await ctx.close();
}
await browser.close();
