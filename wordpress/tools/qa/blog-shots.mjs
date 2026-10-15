/**
 * QA (module "blog"): screenshots of every news view, EN + ES, 1440 + 390.
 *   WP_BASE=http://localhost:8914 node wordpress/tools/qa/blog-shots.mjs <outDir> [filter]
 * Run wordpress/tools/qa/blog-setup.php first (test articles + legal pages).
 * Full-page shots use reduced motion so scroll-reveal content is visible
 * without site.js; a second 1440 shot of the single article keeps motion on
 * and marks .reveal elements visible (the hover / vapour states). Also
 * reports horizontal overflow and console errors per page.
 */
import fs from 'node:fs';
import path from 'node:path';
import { launch, BASE } from './wp-login.mjs';

const out = process.argv[2] || '.';
const filter = process.argv[3] || '';
fs.mkdirSync(out, { recursive: true });

async function firstHref(page, url, selector) {
  await page.goto(BASE + url, { waitUntil: 'domcontentloaded' });
  return page.$eval(selector, (a) => a.getAttribute('href')).catch(() => '');
}

const browser = await launch();
const probe = await (await browser.newContext()).newPage();
const views = {};
for (const [lang, news, search] of [
  ['en', '/news/', '/?s=surface'],
  ['es', '/es/noticias/', '/es/?s=superficie'],
]) {
  views[`${lang}-news`] = news;
  const single = await firstHref(probe, news, '.post-card__link');
  views[`${lang}-single`] = single.replace(BASE, '');
  await probe.goto(single.startsWith("http") ? single : BASE + single);
  views[`${lang}-archive`] = (await probe.$eval('.post-meta__cat', (a) => a.getAttribute('href'))).replace(BASE, '');
  views[`${lang}-search`] = search;
  views[`${lang}-search-empty`] = (lang === 'en' ? '/?s=' : '/es/?s=') + 'zzqxv';
  views[`${lang}-404`] = (lang === 'en' ? '' : '/es') + '/this-page-does-not-exist/';
  views[`${lang}-legal`] = lang === 'en' ? '/privacy-policy/' : '/es/politica-de-privacidad/';
}
await probe.context().close();
console.log(views);

for (const w of [1440, 390]) {
  const ctx = await browser.newContext({ viewport: { width: w, height: 900 }, reducedMotion: 'reduce' });
  const page = await ctx.newPage();
  const errors = [];
  page.on('console', (m) => m.type() === 'error' && errors.push(m.text()));
  page.on('pageerror', (e) => errors.push(String(e)));
  for (const [name, url] of Object.entries(views)) {
    if (filter && !name.includes(filter)) continue;
    errors.length = 0;
    const res = await page.goto(BASE + url, { waitUntil: 'networkidle' });
    await page.evaluate(async () => {
      /* load lazy images: scroll through the page once */
      for (let y = 0; y < document.body.scrollHeight; y += 600) {
        window.scrollTo(0, y);
        await new Promise((r) => setTimeout(r, 40));
      }
      window.scrollTo(0, 0);
      await document.fonts.ready;
    });
    await page.waitForTimeout(400);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    await page.screenshot({ path: path.join(out, `${name}-${w}.png`), fullPage: true });
    console.log(`${name}-${w}`, res.status(), overflow > 0 ? `OVERFLOW ${overflow}px` : 'ok', errors.length ? errors.slice(0, 3) : '');
  }
  await ctx.close();
}

/* motion on: hover a card and the article plate */
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();
await page.goto(BASE + views['en-news'], { waitUntil: 'networkidle' });
await page.evaluate(() => document.querySelectorAll('.reveal').forEach((el) => el.classList.add('is-visible')));
await page.waitForTimeout(1200);
const card = page.locator('.post-card').nth(1);
await card.scrollIntoViewIfNeeded();
await card.hover();
await page.waitForTimeout(900);
await page.screenshot({ path: path.join(out, 'en-news-hover-1440.png') });
await page.goto(BASE + views['en-single'], { waitUntil: 'networkidle' });
await page.waitForTimeout(1600);
await page.mouse.move(1000, 400);
await page.waitForTimeout(900);
await page.screenshot({ path: path.join(out, 'en-single-motion-1440.png') });
await browser.close();
