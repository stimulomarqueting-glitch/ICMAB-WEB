/**
 * QA (module "settings"): header / footer parity, Astro vs WordPress.
 * For every language × page × width: element shots of the header, the
 * footer and (≤ 64rem) the open burger menu, plus a markup comparison of
 * the header and footer (tags, classes, attributes, texts).
 *   node wordpress/tools/qa/settings-parity.mjs <outDir> [pages=home,product,team] [widths=1440,1100,820,390]
 * then: python3 -I wordpress/tools/qa/settings-diff.py <outDir>
 * Needs the pages of settings-setup.php and the Astro build on :4321.
 */
import { mkdirSync, writeFileSync } from 'node:fs';
import { launch } from './wp-login.mjs';

const out = process.argv[2] || '/tmp/settings-parity';
const pages = (process.argv[3] || 'home,product,team').split(',');
const widths = (process.argv[4] || '1440,1100,820,390').split(',').map(Number);
const WP = process.env.WP_BASE || 'http://localhost:8917';
const ASTRO = 'http://127.0.0.1:4321';
mkdirSync(out, { recursive: true });

const astroPath = { home: '/', product: '/product/', applications: '/applications/', research: '/research/', team: '/team/', contact: '/contact/' };
const wpPath = {
  en: { home: '/', product: '/product/', applications: '/applications/', research: '/research/', team: '/team/', contact: '/contact/' },
  es: { home: '/es/', product: '/es/producto/', applications: '/es/aplicaciones/', research: '/es/casos-de-estudio/', team: '/es/equipo/', contact: '/es/contacto/' },
};

/* normalised markup of an element: tag.classes [attrs] "text" (hrefs → path class only) */
const describe = (sel) => {
  const root = document.querySelector(sel);
  if (!root) return ['(missing)'];
  const lines = [];
  const base = root.getBoundingClientRect();
  const r1 = (v) => Math.round(v * 2) / 2;
  const walk = (el, depth) => {
    const attrs = [];
    for (const a of el.attributes) {
      if (['class', 'src', 'href', 'srcset', 'style'].includes(a.name) || a.name.startsWith('data-astro-')) continue;
      attrs.push(`${a.name}=${a.value}`);
    }
    if (el.hasAttribute('href')) {
      const h = el.getAttribute('href');
      attrs.push('href:' + (h.startsWith('mailto:') ? 'mailto' : h.startsWith('#') ? 'hash' : h.includes('#') ? 'path#' + h.split('#')[1] : 'path'));
    }
    const own = [...el.childNodes].filter((n) => n.nodeType === 3).map((n) => n.textContent.trim()).filter(Boolean).join(' ');
    const r = el.getBoundingClientRect();
    const geo = r.width || r.height ? ` @${r1(r.left - base.left)},${r1(r.top - base.top)} ${r1(r.width)}x${r1(r.height)}` : ' @hidden';
    lines.push(`${'  '.repeat(depth)}${el.tagName.toLowerCase()}${el.className ? '.' + String(el.className).trim().split(/\s+/).join('.') : ''}${attrs.length ? ' [' + attrs.sort().join(' ') + ']' : ''}${own ? ' "' + own + '"' : ''}${geo}`);
    for (const c of el.children) walk(c, depth + 1);
  };
  walk(root, 0);
  return lines;
};

const browser = await launch();
const report = [];
for (const lang of ['en', 'es']) {
  for (const page of pages) {
    for (const width of widths) {
      for (const site of ['astro', 'wp']) {
        const ctx = await browser.newContext({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
        const p = await ctx.newPage();
        const url = site === 'astro' ? ASTRO + (lang === 'es' ? '/es' : '') + astroPath[page] : WP + wpPath[lang][page];
        await p.goto(url, { waitUntil: 'networkidle' });
        await p.evaluate(() => document.fonts.ready);
        const tag = `${lang}-${page}-${width}`;
        const header = p.locator('header.header');
        await header.screenshot({ path: `${out}/header-${site}-${tag}.png` });
        const footer = p.locator('footer.footer');
        /* main content differs on purpose (test pages): hide it so the footer
           starts at the same integer offset on both sites */
        await p.addStyleTag({ content: 'main{display:none!important}' });
        await p.waitForTimeout(150);
        await footer.screenshot({ path: `${out}/footer-${site}-${tag}.png` });
        const fmarkup = await p.evaluate(describe, 'footer.footer');
        /* the open menu (main still hidden: its shadow falls on the identical footer) */
        if (width <= 1024) {
          await p.evaluate(() => window.scrollTo(0, 0));
          await p.click('.header__burger');
          await p.waitForTimeout(400);
          const box = await p.evaluate(() => {
            const nav = document.querySelector('.header__nav');
            const r = nav.getBoundingClientRect();
            return { x: 0, y: 0, width: window.innerWidth, height: Math.ceil(r.bottom) };
          });
          await p.screenshot({ path: `${out}/menu-${site}-${tag}.png`, clip: box });
        }
        const markup = await p.evaluate(describe, 'header.header');
        writeFileSync(`${out}/markup-${site}-${tag}.txt`, markup.join('\n') + '\n----\n' + fmarkup.join('\n') + '\n');
        report.push({ site, tag, url });
        await ctx.close();
      }
    }
  }
}
await browser.close();
console.log(`${report.length} captures in ${out}`);
