/**
 * QA (group "benefits"): audiences script behaviour, Astro vs WordPress —
 * cycling once in view (desktop, motion-safe), stop on hover/focus/tap,
 * spotlight custom properties, no cycling with reduced motion or on narrow
 * screens. Also checks that the site-wide reveal is not needed for content.
 *   node wordpress/tools/qa/benefits-behaviour.mjs [wpHomeUrl]
 */
import { launch } from './wp-login.mjs';
const WP = process.argv[2] || 'http://localhost:8904/';
const urls = { astro: 'http://127.0.0.1:4321/', wp: WP };
const browser = await launch();
const activeIdx = (page) => page.evaluate(() => [...document.querySelectorAll('[data-audiences]')].map((r) => [...r.querySelectorAll('.aud__panel')].findIndex((p) => p.hasAttribute('data-active'))));
for (const [name, url] of Object.entries(urls)) {
  const res = {};
  /* desktop, motion */
  let ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'no-preference' });
  let page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  await page.goto(url, { waitUntil: 'networkidle' });
  res.atLoad = await activeIdx(page);
  await page.waitForTimeout(4200);
  res.offscreenAfter4s = await activeIdx(page);
  await page.locator('[data-audiences]').first().scrollIntoViewIfNeeded();
  await page.evaluate(() => document.querySelector('[data-audiences]').scrollIntoView({ block: 'center' }));
  await page.waitForTimeout(4300);
  res.inView4s = await activeIdx(page);
  await page.waitForTimeout(3800);
  res.inView8s = await activeIdx(page);
  const p3 = page.locator('.aud__panel').nth(3);
  const b = await p3.boundingBox();
  await page.mouse.move(b.x + b.width * 0.25, b.y + b.height * 0.75);
  await page.waitForTimeout(300);
  res.hover3 = await activeIdx(page);
  res.spot = await p3.evaluate((p) => [p.style.getPropertyValue('--mx'), p.style.getPropertyValue('--my')].map((v) => Math.round(parseFloat(v))));
  await page.mouse.move(5, 5);
  await page.waitForTimeout(8000);
  res.afterLeave8s = await activeIdx(page);
  res.errors = errors;
  await ctx.close();
  /* keyboard focus + click, narrow screen: no cycling */
  ctx = await browser.newContext({ viewport: { width: 820, height: 1000 }, reducedMotion: 'no-preference' });
  page = await ctx.newPage();
  await page.goto(url, { waitUntil: 'networkidle' });
  await page.evaluate(() => document.querySelector('[data-audiences]').scrollIntoView({ block: 'center' }));
  await page.waitForTimeout(4500);
  res.narrow4s = await activeIdx(page);
  await page.locator('.aud__panel').nth(1).focus();
  res.focus1 = await activeIdx(page);
  await page.locator('.aud__panel').nth(2).click();
  res.click2 = await activeIdx(page);
  await ctx.close();
  /* reduced motion: no cycling */
  ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
  page = await ctx.newPage();
  await page.goto(url, { waitUntil: 'networkidle' });
  await page.evaluate(() => document.querySelector('[data-audiences]').scrollIntoView({ block: 'center' }));
  await page.waitForTimeout(4500);
  res.reduce4s = await activeIdx(page);
  res.reduceMoreVisible = await page.evaluate(() => [...document.querySelectorAll('.aud__more')].map((m) => Math.round(m.getBoundingClientRect().height) > 0));
  await ctx.close();
  console.log(name, JSON.stringify(res));
}
await browser.close();
