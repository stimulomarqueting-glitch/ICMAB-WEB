/** QA (group "hero"): pointer tilt — moves the mouse over the hero in Astro and WP, compares state + screenshots. */
import { launch } from './wp-login.mjs';
const WP = process.env.WP_BASE || 'http://localhost:8901';
const out = process.argv[2] || '/tmp';
const browser = await launch();
for (const [name, url] of [['astro', 'http://127.0.0.1:4321/'], ['wp', `${WP}/`]]) {
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await ctx.newPage();
  await page.goto(url, { waitUntil: 'networkidle' });
  await page.waitForTimeout(2000);
  await page.mouse.move(1300, 200);
  await page.mouse.move(1320, 220, { steps: 4 });
  await page.waitForTimeout(500);
  const st = await page.evaluate(() => { const s = document.querySelector('section.field').style; return ['--px', '--py', '--rx', '--ry'].map((k) => k + '=' + s.getPropertyValue(k)).join(' '); });
  const tr = await page.evaluate(() => getComputedStyle(document.querySelector('.plate')).transform);
  console.log(name, st, tr);
  await page.screenshot({ path: `${out}/tilt-${name}.png`, animations: 'disabled' });
  await page.mouse.move(1320, 990); await page.mouse.move(700, 1200 > 1000 ? 999 : 999);
  await ctx.close();
}
await browser.close();
