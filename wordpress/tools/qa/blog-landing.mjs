/**
 * QA (module "blog"): landing pages must render identically with and
 * without the theme's theme.json / blog changes.
 *   WP_BASE=http://localhost:8914 node wordpress/tools/qa/blog-landing.mjs <outDir> <tag>
 * Writes <tag>-<page>-<width>.png (full page, reduced motion so .reveal
 * content is visible without site.js) and <tag>-metrics.json (box + key
 * computed styles of every element inside <main>). Compare two tags with
 * blog-landing-diff.py.
 */
import fs from 'node:fs';
import path from 'node:path';
import { launch, BASE } from './wp-login.mjs';

const out = process.argv[2] || '.';
const tag = process.argv[3] || 'run';
fs.mkdirSync(out, { recursive: true });

const pages = {
  home: '/',
  product: '/product/',
  applications: '/applications/',
  research: '/research/',
  team: '/team/',
  contact: '/contact/',
  'es-home': '/es/',
  'es-product': '/es/producto/',
};
const widths = [1440, 390];

const browser = await launch();
const metrics = {};
for (const w of widths) {
  const ctx = await browser.newContext({ viewport: { width: w, height: 900 }, reducedMotion: 'reduce' });
  /* QA_COOKIE="name=value": e.g. a QA router switch (see the blog module report) */
  if (process.env.QA_COOKIE) {
    const [cname, cvalue] = process.env.QA_COOKIE.split('=');
    await ctx.addCookies([{ name: cname, value: cvalue, url: BASE }]);
  }
  const page = await ctx.newPage();
  for (const [name, url] of Object.entries(pages)) {
    await page.goto(BASE + url, { waitUntil: 'networkidle' });
    await page.evaluate(() => document.fonts.ready);
    await page.waitForTimeout(300);
    metrics[`${name}-${w}`] = await page.evaluate(() => {
      const props = ['marginTop', 'marginBottom', 'paddingTop', 'paddingBottom', 'fontFamily', 'fontSize', 'lineHeight', 'color', 'textDecorationLine', 'maxWidth', 'gap'];
      return [...document.querySelectorAll('main, main *')].map((el) => {
        const r = el.getBoundingClientRect();
        const cs = getComputedStyle(el);
        const o = { el: el.tagName.toLowerCase() + (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/).join('.') : ''), box: [Math.round(r.x), Math.round(r.y + scrollY), Math.round(r.width), Math.round(r.height)] };
        props.forEach((p) => (o[p] = cs[p]));
        return o;
      });
    });
    await page.screenshot({ path: path.join(out, `${tag}-${name}-${w}.png`), fullPage: true });
  }
  await ctx.close();
}
fs.writeFileSync(path.join(out, `${tag}-metrics.json`), JSON.stringify(metrics));
await browser.close();
console.log('done', Object.keys(metrics).length, 'page/width combinations');
