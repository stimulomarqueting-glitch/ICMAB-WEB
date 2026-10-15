/**
 * QA (group "home"): the applications-teaser script with the section placed
 * twice (page built by home-edge.php): a chip lights only the figure of its
 * own section; plus the script on a page without the section (no errors).
 *   WP_BASE=http://localhost:8903 node wordpress/tools/qa/home-multi.mjs
 */
import { launch } from './wp-login.mjs';

const WP = process.env.WP_BASE || 'http://localhost:8903';
const browser = await launch();
const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
const page = await ctx.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));
page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
await page.goto(`${WP}/home-multi/`, { waitUntil: 'networkidle' });
const hot = () => page.$$eval('section.apps-teaser', (secs) => secs.map((s) => [...s.querySelectorAll('.iso__callout[data-hot]')].map((c) => c.dataset.field)));
console.log('teasers:', await page.locator('section.apps-teaser').count());
await page.locator('section.apps-teaser').nth(1).locator('[data-field="coatings"].apps__chip').hover();
console.log('hover chip in 2nd teaser →', JSON.stringify(await hot()));
await page.mouse.move(2, 2);
await page.locator('section.apps-teaser').nth(0).locator('[data-field="sensors"].apps__chip').hover();
console.log('hover chip in 1st teaser →', JSON.stringify(await hot()));
await page.mouse.move(2, 2);
console.log('after leave →', JSON.stringify(await hot()));
/* the script itself on a page without the section */
await page.goto(`${WP}/product/`, { waitUntil: 'networkidle' });
await page.addScriptTag({ url: `${WP}/wp-content/themes/matsurfer/blocks/apps-teaser/view.js` });
await page.waitForTimeout(200);
console.log(errors.length ? errors.join('\n') : 'no JS errors');
await browser.close();
