/**
 * QA (module "blog", fix pass): checks for the review findings.
 *   WP_BASE=http://localhost:8914 node wordpress/tools/qa/blog-fix.mjs <outDir> <enPostId> <esPostId>
 * (the posts come from blog-fix-setup.php)
 * - long words: at 390 and 1440 px, every rising title word, card title,
 *   PageHero title word, previous/next title, tag chip and file name must fit
 *   (no clipping: scrollWidth <= clientWidth, right edge inside the viewport),
 *   and the page must not scroll sideways; screenshots of each view;
 * - category chips of the News pages (EN / ES): the default category must
 *   not be listed next to "All news";
 * - tags under the article; one closing CTA band;
 * - file + audio blocks: styled on the site and in the editor canvas, valid
 *   in the editor.
 */
import fs from 'node:fs';
import path from 'node:path';
import { launch, login, closeWelcome, BASE } from './wp-login.mjs';

const [out = '.', enId, esId] = process.argv.slice(2);
fs.mkdirSync(out, { recursive: true });

const LONG = 'functionalizationsuperlongwordwithoutanyspacesatall';
const views = {
  'single-en': `/?p=${enId}`,
  'single-es': `/?p=${esId}`,
  'news-en': '/news/',
  'news-es': '/es/noticias/',
  'search-long': `/?s=${LONG}`,
  'search-es': `/es/?s=poli`,
  'tag-en': '/tag/thin-films/',
};

const CHECK = `(() => {
  const vw = document.documentElement.clientWidth;
  const sel = ['.post-hero__word', '.post-hero__title', '.post-card__title', '.phero__word', '.phero__title', '.post-nav__title', '.post-tags__chip', '.wp-block-file a', '.wp-block-audio'];
  const bad = [];
  for (const s of sel) {
    for (const el of document.querySelectorAll(s)) {
      const r = el.getBoundingClientRect();
      if (!r.width) continue;
      const clipped = el.scrollWidth > el.clientWidth + 1 && getComputedStyle(el).overflowX !== 'visible';
      const runsOut = el.scrollWidth > el.clientWidth + 1 && s.indexOf('title') !== -1;
      if (clipped || runsOut || r.right > vw + 0.5) bad.push(s + ' "' + el.textContent.trim().slice(0, 40) + '" right=' + Math.round(r.right) + ' sw=' + el.scrollWidth + ' cw=' + el.clientWidth);
    }
  }
  return {
    pageScroll: document.documentElement.scrollWidth > vw ? document.documentElement.scrollWidth + '>' + vw : 'ok',
    bad,
    chips: [...document.querySelectorAll('.blog-cats__chip')].map((a) => a.textContent.replace(/\\s+/g, ' ').trim()),
    tags: [...document.querySelectorAll('.post-tags__chip')].map((a) => a.textContent.trim() + ' -> ' + a.getAttribute('href').replace(location.origin, '')),
    ctaBands: document.querySelectorAll('main .pcta').length,
    fileButton: (() => { const b = document.querySelector('.mats-prose .wp-block-file__button'); if (!b) return null; const cs = getComputedStyle(b); return [cs.backgroundColor, cs.color, cs.borderRadius, cs.fontSize].join(' | '); })(),
    filePanel: (() => { const b = document.querySelector('.mats-prose .wp-block-file'); if (!b) return null; const cs = getComputedStyle(b); return [cs.display, cs.borderTopWidth, cs.borderRadius, cs.backgroundColor, Math.round(b.getBoundingClientRect().width)].join(' | '); })(),
    audio: (() => { const a = document.querySelector('.mats-prose .wp-block-audio audio'); return a ? Math.round(a.getBoundingClientRect().width) + 'px of ' + Math.round(a.closest('.mats-prose').getBoundingClientRect().width) : null; })(),
    h1: (document.querySelector('h1') || {}).textContent?.replace(/\\s+/g, ' ').trim().slice(0, 90),
  };
})()`;

const browser = await launch();
for (const w of [390, 1440]) {
  const ctx = await browser.newContext({ viewport: { width: w, height: 900 }, reducedMotion: 'reduce' });
  const page = await ctx.newPage();
  for (const [name, url] of Object.entries(views)) {
    const res = await page.goto(BASE + url, { waitUntil: 'networkidle' });
    await page.evaluate(() => document.fonts.ready);
    await page.waitForTimeout(250);
    const r = await page.evaluate(CHECK);
    console.log(`${name}@${w} [${res.status()}] scroll=${r.pageScroll} h1="${r.h1}"`);
    if (r.bad.length) console.log('   CLIPPED:', r.bad.join('\n            '));
    if (r.chips.length) console.log('   chips:', r.chips.join(' · '));
    if (r.tags.length) console.log('   tags:', r.tags.join(' · '));
    if (name.startsWith('single')) console.log('   cta bands:', r.ctaBands, '| file button:', r.fileButton, '| file panel:', r.filePanel, '| audio:', r.audio);
    await page.screenshot({ path: path.join(out, `${name}-${w}.png`), fullPage: false });
    if (name.startsWith('single')) {
      const body = page.locator('.post-body');
      if (await body.count()) await body.screenshot({ path: path.join(out, `${name}-${w}-body.png`) });
    }
  }
  await ctx.close();
}

/* editor canvas: file + audio blocks valid and styled */
const ed = await login(browser, 'admin', 'admin-local-pass', { width: 1440, height: 1000 });
await ed.goto(`${BASE}/wp-admin/post.php?post=${enId}&action=edit`);
await ed.waitForSelector('iframe[name="editor-canvas"]', { timeout: 60000 });
await closeWelcome(ed);
const frame = ed.frame({ name: 'editor-canvas' });
await frame.waitForSelector('.is-root-container .wp-block-file', { timeout: 60000 });
await ed.waitForTimeout(1500);
const invalid = await ed.evaluate(() => {
  const walk = (blocks) => blocks.flatMap((b) => [b.isValid ? null : b.name, ...walk(b.innerBlocks)]).filter(Boolean);
  return walk(wp.data.select('core/block-editor').getBlocks());
});
console.log('editor invalid blocks:', invalid.length ? invalid : 'none');
const canvas = await frame.evaluate(() => {
  const b = document.querySelector('.is-root-container .wp-block-file .wp-block-file__button');
  const f = document.querySelector('.is-root-container .wp-block-file');
  const a = document.querySelector('.is-root-container .wp-block-audio');
  const cs = (el, props) => (el ? props.map((p) => getComputedStyle(el)[p]).join(' | ') : null);
  return {
    button: cs(b, ['backgroundColor', 'color', 'borderRadius', 'fontSize']),
    panel: cs(f, ['borderTopWidth', 'borderRadius', 'backgroundColor']),
    audio: cs(a, ['borderTopWidth', 'borderRadius', 'backgroundColor']),
    markup: f ? f.outerHTML.replace(/\s+/g, ' ').slice(0, 400) : null,
  };
});
console.log('editor canvas:', JSON.stringify(canvas, null, 1));
await frame.evaluate(() => document.querySelector('.is-root-container .wp-block-file').scrollIntoView({ block: 'start' }));
await ed.waitForTimeout(500);
await ed.screenshot({ path: path.join(out, 'editor-file-audio.png') });
await browser.close();
