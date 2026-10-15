/** QA (group "hero"): two hero sections on one page — both initialise, no JS errors. */
import { launch } from './wp-login.mjs';
const url = process.argv[2];
const out = process.argv[3] || '/tmp/hero-multi.png';
const browser = await launch();
for (const rm of ['no-preference', 'reduce']) {
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: rm });
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
  await page.goto(url, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1500);
  const secs = page.locator('section.field');
  const n = await secs.count();
  const info = [];
  for (let i = 0; i < n; i++) {
    const s = secs.nth(i);
    await s.scrollIntoViewIfNeeded();
    await page.waitForTimeout(400);
    const box = await s.boundingBox();
    await page.mouse.move(box.x + box.width * 0.9, box.y + 100);
    await page.mouse.move(box.x + box.width * 0.92, box.y + 120, { steps: 3 });
    await page.waitForTimeout(300);
    info.push(await s.evaluate((el) => {
      const c = el.querySelector('canvas[data-vapour]');
      let lit = null;
      if (c) { const d = c.getContext('2d').getImageData(0, 0, c.width, c.height).data; lit = 0; for (let k = 3; k < d.length; k += 4) if (d[k] > 0) lit++; }
      return { ready: el.hasAttribute('data-field-ready'), ry: el.style.getPropertyValue('--ry'), canvas: !!c, litPixels: lit };
    }));
  }
  console.log(rm, 'sections:', n, JSON.stringify(info), errors.length ? errors : 'no JS errors');
  if (rm === 'no-preference') await page.screenshot({ path: out, fullPage: true });
  await ctx.close();
}
await browser.close();
