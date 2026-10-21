/**
 * Before/after check for the legibility fixes. "before" is rebuilt on the fly:
 * every HTML and CSS response is served with the fixes reversed (PAIRS = a
 * JSON list of [file, old, new] replacements), so nothing on the shared site
 * is reverted. For every page, width and mode it shoots each section on its
 * own (header made static so it never covers a section) and records layout
 * facts: section heights, horizontal overflow, wrapped / overflowing buttons
 * and, for cards that share a row, the spread of their heights.
 *
 *   WP_BASE=http://localhost:8990 OUT=/dir PAIRS=/dir/pairs.json node wordpress/tools/qa/legibility-compare.mjs
 *
 * Writes OUT/sections/<mode>/<lang>-<page>-<width>-<nn>-<section>.png and
 * OUT/layout-<mode>.json.
 */
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { chromium } from '../../../node_modules/playwright-core/index.mjs';

const BASE = process.env.WP_BASE || 'http://localhost:8990';
const OUT = process.env.OUT || '.';
const PAIRS = JSON.parse(readFileSync(process.env.PAIRS, 'utf8'));
const WIDTHS = (process.env.WIDTHS || '1440,390').split(',').map(Number);
const MODES = (process.env.MODES || 'before,after').split(',');
const PAGES = process.env.PAGES
  ? process.env.PAGES.split(',')
  : ['/', '/product/', '/applications/', '/research/', '/team/', '/contact/', '/es/', '/es/producto/', '/es/aplicaciones/', '/es/casos-de-estudio/', '/es/equipo/', '/es/contacto/'];

const FREEZE = `
.reveal{opacity:1!important;transform:none!important}
.rise-in,.field .rise{opacity:1!important;transform:none!important;animation:none!important}
#wpadminbar{display:none!important}
.header{position:relative!important}
*,*::before,*::after{transition-duration:0s!important;transition-delay:0s!important;animation-duration:0s!important;animation-delay:0s!important;caret-color:transparent!important}
`;
const CARDS = '.aud__panel,.value__item,.bene__outcome,.diff__feature,.tech__step,.params__card,.explore__card,.apps-list .app,.journey__step,.journey__fact,.research__card,.research__pub,.apps__chip,.contact__steps li';

const slug = (p) => p.replace(/^\/(es\/)?|\/$/g, '').replace(/\//g, '_') || 'home';
const reverse = (text) => {
  let n = 0;
  for (const [, oldS, newS] of PAIRS) {
    if (text.includes(newS)) {
      text = text.split(newS).join(oldS);
      n++;
    }
  }
  return { text, n };
};

const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
for (const mode of MODES) {
  const dir = `${OUT}/sections/${mode}`;
  mkdirSync(dir, { recursive: true });
  const layout = [];
  for (const path of PAGES) {
    const lang = path.startsWith('/es/') ? 'es' : 'en';
    for (const width of WIDTHS) {
      const ctx = await browser.newContext({ viewport: { width, height: 900 }, deviceScaleFactor: 1 });
      let reversed = 0;
      if (mode === 'before') {
        await ctx.route('**/*', async (route) => {
          const req = route.request();
          const type = req.resourceType();
          if (type !== 'document' && type !== 'stylesheet') return route.continue();
          const res = await route.fetch();
          const r = reverse(await res.text());
          reversed += r.n;
          return route.fulfill({ response: res, body: r.text });
        });
      }
      const page = await ctx.newPage();
      await page.goto(BASE + path, { waitUntil: 'networkidle' });
      await page.addStyleTag({ content: FREEZE });
      await page.evaluate(async () => {
        document.querySelectorAll('img[loading="lazy"]').forEach((i) => (i.loading = 'eager'));
        document.querySelectorAll('video').forEach((v) => { try { v.pause(); v.currentTime = 0; } catch (e) {} });
        await Promise.all([...document.images].map((i) => (i.complete ? null : new Promise((r) => { i.onload = i.onerror = r; setTimeout(r, 4000); }))));
        await document.fonts.ready;
        document.querySelectorAll('[data-audiences] .aud__panel[data-active]').forEach((p) => p.dispatchEvent(new Event('click')));
        document.querySelectorAll('[data-showcase]').forEach((r) => r.dispatchEvent(new Event('pointerenter')));
      });
      await page.waitForTimeout(200);
      const facts = await page.evaluate((cardSel) => {
        const secs = [...document.querySelectorAll('main section, footer.footer')].filter((s) => !s.parentElement.closest('section'));
        const btns = [...document.querySelectorAll('.btn')].filter((b) => b.offsetParent).map((b) => {
          const r = b.getBoundingClientRect();
          const lh = parseFloat(getComputedStyle(b).fontSize) * 1.15;
          return { text: b.textContent.trim().slice(0, 40), h: Math.round(r.height), w: Math.round(r.width), wrapped: b.getClientRects().length > 1 || r.height > lh * 2 + 30, overflow: b.scrollWidth > b.clientWidth + 1 };
        });
        return {
          scrollW: document.documentElement.scrollWidth,
          clientW: document.documentElement.clientWidth,
          docH: document.documentElement.scrollHeight,
          btns,
          sections: secs.map((s, i) => {
            const r = s.getBoundingClientRect();
            const cards = [...s.querySelectorAll(cardSel)].filter((c) => c.offsetParent);
            const rows = {};
            cards.forEach((c) => {
              const b = c.getBoundingClientRect();
              const k = Math.round(b.top + scrollY);
              (rows[k] = rows[k] || []).push(Math.round(b.height));
            });
            return {
              i,
              cls: [...s.classList].filter((c) => !/^(sec|hairtop|mist|mist--violet|reveal|is-visible)$/.test(c)).join('.') || s.tagName.toLowerCase(),
              h: Math.round(r.height),
              rows: Object.values(rows).filter((v) => v.length > 1).map((v) => ({ n: v.length, min: Math.min(...v), max: Math.max(...v) })),
              overflowX: s.scrollWidth > s.clientWidth + 1,
            };
          }),
        };
      }, CARDS);
      layout.push({ mode, lang, path, width, reversed, ...facts });
      const secs = page.locator('main section, footer.footer');
      const n = await secs.count();
      for (let i = 0; i < n; i++) {
        const s = secs.nth(i);
        if (await s.evaluate((el) => !!el.parentElement.closest('section'))) continue;
        const cls = (await s.getAttribute('class') || 'footer').split(/\s+/).filter((c) => !/^(sec|hairtop|mist|mist--violet|reveal|is-visible)$/.test(c))[0] || 'section';
        await s.screenshot({ path: `${dir}/${lang}-${slug(path)}-${width}-${String(i).padStart(2, '0')}-${cls}.png` });
      }
      await ctx.close();
      console.log(`${mode} ${path} @${width}${mode === 'before' ? ` (reversed ${reversed} rules)` : ''}`);
    }
  }
  writeFileSync(`${OUT}/layout-${mode}.json`, JSON.stringify(layout, null, 1));
}
await browser.close();
