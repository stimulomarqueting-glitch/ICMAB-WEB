/**
 * QA (group "pagehero"): pointer tilt of the product hero photo plate,
 * Astro vs WordPress (same pointer positions → same --rx/--ry/transform),
 * plus the reduced-motion case (no tilt) and the rise-in delays.
 *   node wordpress/tools/qa/pagehero-tilt.mjs <outDir>
 */
import { launch } from './wp-login.mjs';

const out = process.argv[2] || '/tmp/pagehero';
const WP = process.env.WP_BASE || 'http://localhost:8902';
const sites = { astro: 'http://127.0.0.1:4321/product/', wp: WP + '/product/' };
const browser = await launch();
for (const reduce of [false, true]) {
  for (const [site, url] of Object.entries(sites)) {
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: reduce ? 'reduce' : 'no-preference' });
    const page = await ctx.newPage();
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));
    await page.goto(url, { waitUntil: 'networkidle' });
    const delays = await page.evaluate(() => [...document.querySelectorAll('.phero .rise-in')].map((e) => getComputedStyle(e).animationDelay).join(' '));
    await page.waitForTimeout(2500);
    const res = [];
    for (const [x, y] of [[1300, 150], [200, 500], [900, 300]]) {
      await page.mouse.move(x, y, { steps: 4 });
      await page.waitForTimeout(500);
      res.push(await page.evaluate(() => {
        const h = document.querySelector('[data-phero]');
        const p = h.querySelector('.phero__plate');
        return `${h.style.getPropertyValue('--rx') || '-'}/${h.style.getPropertyValue('--ry') || '-'} ${getComputedStyle(p).transform}`;
      }));
      if (x === 1300 && !reduce) await page.screenshot({ path: `${out}/tilt-${site}.png`, clip: { x: 760, y: 100, width: 680, height: 520 } });
    }
    await page.mouse.move(700, 900);
    await page.mouse.move(700, 990);
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.mouse.move(5, 5);
    // leave the hero (header area is outside it)
    await page.waitForTimeout(500);
    res.push('after leave: ' + (await page.evaluate(() => {
      const h = document.querySelector('[data-phero]');
      return `${h.style.getPropertyValue('--rx') || '-'}/${h.style.getPropertyValue('--ry') || '-'}`;
    })));
    console.log(reduce ? 'REDUCED' : 'MOTION', site, '\n  delays:', delays, '\n  ' + res.join('\n  '), errors.length ? '\n  errors: ' + errors.join('; ') : '');
    await ctx.close();
  }
}
await browser.close();
