/**
 * QA (group "pagehero"): screenshots of the page hero and the "What it is"
 * intro in the Astro reference and in WordPress, side by side, plus a
 * geometry/computed-style dump for a numeric comparison.
 *   node wordpress/tools/qa/pagehero-shots.mjs <outDir> [reduce|motion]
 * (run from /home/user/ICMAB-WEB so playwright-core resolves)
 */
import { launch } from './wp-login.mjs';
import fs from 'node:fs';

const out = process.argv[2] || '/tmp/pagehero';
const mode = process.argv[3] || 'motion';
fs.mkdirSync(out, { recursive: true });

const ASTRO = 'http://127.0.0.1:4321';
const WP = process.env.WP_BASE || 'http://localhost:8902';
const cases = [
  { role: 'product', astro: { en: '/product/', es: '/es/product/' }, wp: { en: '/product/', es: '/es/producto/' }, sections: ['section.phero', '#what-it-is'] },
  { role: 'applications', astro: { en: '/applications/', es: '/es/applications/' }, wp: { en: '/applications/', es: '/es/aplicaciones/' }, sections: ['section.phero'] },
  { role: 'research', astro: { en: '/research/', es: '/es/research/' }, wp: { en: '/research/', es: '/es/investigacion/' }, sections: ['section.phero'] },
];
const widths = (process.env.WIDTHS || '1440,820,390').split(',').map(Number);
const langs = (process.env.LANGS || 'en,es').split(',');

const probe = (sel) => {
  const root = document.querySelector(sel);
  if (!root) return null;
  const pick = (el) => {
    if (!el) return null;
    const r = el.getBoundingClientRect();
    const cs = getComputedStyle(el);
    return {
      x: Math.round(r.x * 10) / 10, y: Math.round((r.y + scrollY) * 10) / 10, w: Math.round(r.width * 10) / 10, h: Math.round(r.height * 10) / 10,
      fs: cs.fontSize, lh: cs.lineHeight, ff: cs.fontFamily.split(',')[0], fw: cs.fontWeight, color: cs.color, ta: cs.textAlign, disp: cs.display,
      mt: cs.marginTop, pad: cs.padding, op: cs.opacity, tf: cs.transform,
    };
  };
  const q = (s) => pick(root.querySelector(s));
  return {
    root: pick(root),
    eyebrow: q('.eyebrow'),
    row: q('.phero__row, .sec-head__row'),
    note: q('.phero__note, .sec-head__note'),
    h: q('h1, h2'),
    lead: q('.lead'),
    cta: q('.phero__cta'),
    btn: q('.btn'),
    stage: q('.phero__stage'),
    plate: q('.phero__plate'),
    halo: q('.phero__halo'),
    photo: q('.phero__photo'),
  };
};

const browser = await launch();
const report = {};
for (const w of widths) {
  for (const lang of langs) {
    for (const c of cases) {
      for (const site of ['astro', 'wp']) {
        const ctx = await browser.newContext({ viewport: { width: w, height: 1000 }, reducedMotion: mode === 'reduce' ? 'reduce' : 'no-preference' });
        const page = await ctx.newPage();
        const errors = [];
        page.on('pageerror', (e) => errors.push(e.message));
        const url = (site === 'astro' ? ASTRO : WP) + c[site][lang];
        await page.goto(url, { waitUntil: 'networkidle' });
        await page.evaluate(() => document.fonts.ready);
        /* site.js (scroll reveal) is still a placeholder in the theme: emulate it on WP */
        if (site === 'wp' && process.env.NO_REVEAL_SHIM !== '1') {
          await page.evaluate(() => {
            if (document.querySelector('.reveal.is-visible')) return;
            const io = new IntersectionObserver((entries) => {
              for (const e of entries) if (e.isIntersecting) { e.target.classList.add('is-visible'); io.unobserve(e.target); }
            }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
            document.querySelectorAll('.reveal').forEach((el) => io.observe(el));
          });
        }
        await page.mouse.move(0, 0);
        for (const sel of c.sections) {
          const el = page.locator(sel).first();
          if (!(await el.count())) {
            console.log('MISSING', site, url, sel);
            continue;
          }
          await el.scrollIntoViewIfNeeded();
          await page.waitForTimeout(2600);
          const tag = `${c.role}-${lang}-${w}-${sel.replace(/[^a-z-]/gi, '')}`;
          await el.screenshot({ path: `${out}/${tag}-${site}.png` });
          report[`${tag}`] = report[`${tag}`] || {};
          report[`${tag}`][site] = await page.evaluate(probe, sel);
        }
        if (errors.length) console.log('JS errors', site, url, errors);
        await ctx.close();
      }
    }
  }
}
fs.writeFileSync(`${out}/report.json`, JSON.stringify(report, null, 1));

/* numeric diff */
let diffs = 0;
for (const [tag, r] of Object.entries(report)) {
  if (!r.astro || !r.wp) continue;
  for (const k of Object.keys(r.astro)) {
    const a = r.astro[k];
    const b = r.wp[k];
    if (!a && !b) continue;
    if (!a || !b) {
      console.log(tag, k, 'present only in', a ? 'astro' : 'wp');
      diffs++;
      continue;
    }
    for (const p of Object.keys(a)) {
      const va = a[p];
      const vb = b[p];
      const num = typeof va === 'number';
      if (num ? Math.abs(va - vb) > 0.6 : va !== vb) {
        console.log(tag, k, p, 'astro=', va, 'wp=', vb);
        diffs++;
      }
    }
  }
}
console.log('differences:', diffs);
await browser.close();
