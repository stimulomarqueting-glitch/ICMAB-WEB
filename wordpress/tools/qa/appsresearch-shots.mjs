/**
 * QA (group "appsresearch"): screenshots of the applications-list, broader,
 * research-* and invite sections in the Astro reference and in WordPress,
 * plus an element-by-element geometry/computed-style comparison (the
 * markup is a 1:1 port, so the n-th element of a section in Astro is
 * compared with the n-th element in WordPress).
 *   node wordpress/tools/qa/appsresearch-shots.mjs <outDir>
 *   env: WP_BASE (default http://localhost:8906), WIDTHS=1440,820,390, LANGS=en,es
 * (run from /home/user/ICMAB-WEB so playwright-core resolves)
 */
import { launch } from './wp-login.mjs';
import fs from 'node:fs';

const out = process.argv[2] || '/tmp/appsresearch';
fs.mkdirSync(out, { recursive: true });

const ASTRO = 'http://127.0.0.1:4321';
const WP = process.env.WP_BASE || 'http://localhost:8906';
const cases = [
  {
    role: 'applications',
    astro: { en: '/applications/', es: '/es/applications/' },
    wp: { en: '/applications/', es: '/es/aplicaciones/' },
    sections: { nav: 'nav.apps-nav', list: 'nav.apps-nav + section', broader: 'section.broader--dark' },
  },
  {
    role: 'research',
    astro: { en: '/research/', es: '/es/research/' },
    wp: { en: '/research/', es: '/es/investigacion/' },
    sections: {
      experiences: 'section[aria-labelledby="experiences-title"]',
      publications: 'section#publications',
      validation: 'section#validation',
      invite: 'section[aria-labelledby="invite-title"]',
    },
  },
];
const widths = (process.env.WIDTHS || '1440,820,390').split(',').map(Number);
const langs = (process.env.LANGS || 'en,es').split(',');

/* every element of the section, in document order, with its box + main styles */
const probe = (sel) => {
  const root = document.querySelector(sel);
  if (!root) return null;
  const els = [root, ...root.querySelectorAll('*')].filter((el) => !el.closest('.mats-note'));
  return els.map((el) => {
    const r = el.getBoundingClientRect();
    const cs = getComputedStyle(el);
    const before = getComputedStyle(el, '::before');
    const after = getComputedStyle(el, '::after');
    return {
      tag: el.tagName.toLowerCase() + (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/).filter((c) => !['apps-list', 'research-experiences', 'research-publications', 'invite', 'is-visible'].includes(c)).join('.') : ''),
      text: el.children.length ? '' : (el.textContent || '').trim().slice(0, 40),
      x: Math.round(r.x * 10) / 10,
      y: Math.round((r.y + scrollY) * 10) / 10,
      w: Math.round(r.width * 10) / 10,
      h: Math.round(r.height * 10) / 10,
      fs: cs.fontSize, lh: cs.lineHeight, ff: cs.fontFamily.split(',')[0], fw: cs.fontWeight, ls: cs.letterSpacing, tt: cs.textTransform,
      color: cs.color, bg: cs.backgroundColor, bgi: cs.backgroundImage.slice(0, 60), bd: cs.border, br: cs.borderRadius, sh: cs.boxShadow,
      pad: cs.padding, mar: cs.margin, op: cs.opacity, pos: cs.position,
      bfr: before.content !== 'none' ? `${before.content}|${before.width}|${before.height}|${before.backgroundImage.slice(0, 30)}` : '',
      aft: after.content !== 'none' ? `${after.content}|${after.fontSize}|${after.top}|${after.right}|${after.opacity}` : '',
      attrs: ['id', 'href', 'aria-label', 'aria-labelledby', 'aria-hidden', 'role', 'target', 'rel', 'data-cta-source', 'data-target', 'aria-current']
        .map((a) => (el.hasAttribute(a) ? `${a}=${el.getAttribute(a)}` : ''))
        .filter(Boolean)
        .join(' '),
    };
  });
};

/* the theme's site.js (scroll reveal) is still a placeholder: emulate it on WP */
const revealShim = () => {
  if (document.querySelector('.reveal.is-visible')) return;
  const io = new IntersectionObserver(
    (entries) => {
      for (const e of entries) if (e.isIntersecting) { e.target.classList.add('is-visible'); io.unobserve(e.target); }
    },
    { threshold: 0.12, rootMargin: '0px 0px -40px 0px' }
  );
  document.querySelectorAll('.reveal').forEach((el) => io.observe(el));
};

const normHref = (s) => s.replace('http://localhost:8906', '').replace(/&#038;|&amp;/g, '&')
  .replace('/es/contacto/', '/es/contact').replace('/contact/', '/contact');

const browser = await launch();
const report = {};
let diffs = 0;
for (const w of widths) {
  for (const lang of langs) {
    for (const c of cases) {
      const data = {};
      for (const site of ['astro', 'wp']) {
        const ctx = await browser.newContext({ viewport: { width: w, height: 1000 } });
        const page = await ctx.newPage();
        const errors = [];
        page.on('pageerror', (e) => errors.push(e.message));
        const url = (site === 'astro' ? ASTRO : WP) + c[site][lang];
        await page.goto(url, { waitUntil: 'networkidle' });
        await page.evaluate(() => document.fonts.ready);
        if (site === 'wp') await page.evaluate(revealShim);
        await page.mouse.move(0, 0);
        /* reveal everything first (scroll through the page), then shoot */
        await page.evaluate(async () => {
          for (let y = 0; y < document.body.scrollHeight; y += 300) {
            window.scrollTo(0, y);
            await new Promise((r) => setTimeout(r, 120));
          }
          window.scrollTo(0, 0);
        });
        await page.waitForTimeout(2600);
        data[site] = {};
        for (const [name, sel] of Object.entries(c.sections)) {
          const el = page.locator(sel).first();
          if (!(await el.count())) {
            console.log('MISSING', site, url, sel);
            continue;
          }
          await el.scrollIntoViewIfNeeded();
          await page.waitForTimeout(400);
          const tag = `${c.role}-${name}-${lang}-${w}`;
          await el.screenshot({ path: `${out}/${tag}-${site}.png` });
          data[site][name] = await page.evaluate(probe, sel);
        }
        if (errors.length) console.log('JS errors', site, url, errors);
        await ctx.close();
      }
      for (const name of Object.keys(c.sections)) {
        const a = data.astro[name];
        const b = data.wp[name];
        const tag = `${c.role}-${name}-${lang}-${w}`;
        report[tag] = { astro: a, wp: b };
        if (!a || !b) continue;
        if (a.length !== b.length) {
          console.log(tag, 'element count astro=', a.length, 'wp=', b.length);
          diffs++;
        }
        const n = Math.min(a.length, b.length);
        let shown = 0;
        for (let i = 0; i < n; i++) {
          for (const p of Object.keys(a[i])) {
            let va = a[i][p];
            let vb = b[i][p];
            if (p === 'attrs') { va = normHref(va); vb = normHref(vb); }
            const num = typeof va === 'number';
            /* y: compare relative to the section top */
            if (p === 'y') { va -= a[0].y; vb -= b[0].y; }
            if (num ? Math.abs(va - vb) > 0.6 : va !== vb) {
              if (shown < 25) console.log(tag, i, a[i].tag, p, 'astro=', va, 'wp=', vb);
              shown++;
              diffs++;
            }
          }
        }
        if (shown > 25) console.log(tag, '…', shown - 25, 'more');
      }
    }
  }
}
fs.writeFileSync(`${out}/report.json`, JSON.stringify(report, null, 1));
console.log('differences:', diffs);
await browser.close();
