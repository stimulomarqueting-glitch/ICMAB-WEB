/**
 * QA (fix bucket "seoblog"): full-page screenshots of a list of URLs at 1440
 * and 390, reduced motion (scroll-reveal content visible), for before/after
 * comparisons with fix-seoblog-diff.py.
 *   WP_BASE=http://localhost:8982 node wordpress/tools/qa/fix-seoblog-shots.mjs <outDir> /path/ /es/path/ …
 * Env LOGGED_IN=1 logs in as admin first (admin bar shown).
 * File names: the path with / replaced by _ (+ -<width>.png).
 */
import fs from 'node:fs';
import path from 'node:path';
import { launch, login, BASE } from './wp-login.mjs';

const out = process.argv[2] || '.';
const urls = process.argv.slice(3);
fs.mkdirSync(out, { recursive: true });

const name = (u) => (u.replace(/[^a-z0-9]+/gi, '_').replace(/^_+|_+$/g, '') || 'home');

const browser = await launch();
for (const w of [1440, 390]) {
  let page;
  if (process.env.LOGGED_IN) {
    page = await login(browser, 'admin', 'admin-local-pass', { width: w, height: 900 });
  } else {
    const ctx = await browser.newContext({ viewport: { width: w, height: 900 }, reducedMotion: 'reduce' });
    page = await ctx.newPage();
  }
  const errors = [];
  page.on('console', (m) => m.type() === 'error' && errors.push(m.text()));
  page.on('pageerror', (e) => errors.push(String(e)));
  for (const url of urls) {
    errors.length = 0;
    const res = await page.goto(BASE + url, { waitUntil: 'load', timeout: 60000 });
    await page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => {});
    await page.evaluate(async () => {
      for (let y = 0; y < document.body.scrollHeight; y += 600) {
        window.scrollTo(0, y);
        await new Promise((r) => setTimeout(r, 40));
      }
      window.scrollTo(0, 0);
      document.querySelectorAll('.reveal').forEach((el) => el.classList.add('is-visible'));
      await document.fonts.ready;
    });
    await page.waitForTimeout(500);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    await page.screenshot({ path: path.join(out, `${name(url)}-${w}.png`), fullPage: true });
    console.log(`${url} @${w}`, res ? res.status() : '-', page.url().replace(BASE, ''), overflow > 0 ? `OVERFLOW ${overflow}px` : 'ok', errors.length ? errors.slice(0, 3) : '');
  }
  await page.context().close();
}
await browser.close();
