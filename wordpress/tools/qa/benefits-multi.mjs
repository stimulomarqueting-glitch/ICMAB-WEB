/** QA (group "benefits"): both audiences rows on the multi page work on their own, no JS errors, unique ids.
 *  node wordpress/tools/qa/benefits-multi.mjs <url> */
import { launch } from './wp-login.mjs';
const url = process.argv[2] || 'http://localhost:8904/benefits-multi/';
const browser = await launch();
const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
const page = await ctx.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));
page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
await page.goto(url, { waitUntil: 'networkidle' });
const idx = () => page.evaluate(() => [...document.querySelectorAll('[data-audiences]')].map((r) => [...r.querySelectorAll('.aud__panel')].findIndex((p) => p.hasAttribute('data-active'))));
const dupIds = await page.evaluate(() => { const ids = [...document.querySelectorAll('[id]')].map((e) => e.id); return ids.filter((v, i) => ids.indexOf(v) !== i); });
const rows = page.locator('[data-audiences]');
await rows.nth(1).evaluate((r) => r.scrollIntoView({ block: 'center' }));
await page.waitForTimeout(4300);
const afterSecondInView = await idx();
const b = await rows.nth(1).locator('.aud__panel').nth(3).boundingBox();
await page.mouse.move(b.x + 20, b.y + 20);
await page.waitForTimeout(200);
const hoverSecond = await idx();
await rows.nth(0).evaluate((r) => r.scrollIntoView({ block: 'center' }));
await page.mouse.move(2, 2);
await page.waitForTimeout(4300);
const afterFirstInView = await idx();
console.log(JSON.stringify({ dupIds, afterSecondInView, hoverSecond, afterFirstInView, ready: await page.evaluate(() => [...document.querySelectorAll('[data-audiences-ready]')].length), errors }));
await browser.close();
