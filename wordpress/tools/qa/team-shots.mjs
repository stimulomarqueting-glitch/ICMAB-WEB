/**
 * QA (group "team"): screenshots of the team showcase and the "from the lab
 * to the bench" section in the Astro reference and in WordPress, plus a
 * geometry/computed-style dump for a numeric comparison.
 *   node wordpress/tools/qa/team-shots.mjs <outDir> [reduce|motion]
 * (run from /home/user/ICMAB-WEB so playwright-core resolves)
 * env: WP_BASE (default http://localhost:8907), WIDTHS, LANGS
 */
import { launch } from './wp-login.mjs';
import fs from 'node:fs';

const out = process.argv[2] || '/tmp/team';
const mode = process.argv[3] || 'reduce';
fs.mkdirSync(out, { recursive: true });

const ASTRO = 'http://127.0.0.1:4321';
const WP = process.env.WP_BASE || 'http://localhost:8907';
const urls = {
  astro: { en: '/team/', es: '/es/team/' },
  wp: { en: '/team/', es: '/es/equipo/' },
};
const sections = {
  show: { astro: 'section.show', wp: 'section.show' },
  journey: { astro: 'section[aria-labelledby="journey-title"]', wp: 'section.tjourney' },
  cta: { astro: 'section.pcta-sec', wp: 'section.pcta-sec' },
};
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
      mt: cs.marginTop, pad: cs.padding, op: cs.opacity, tf: cs.transform, bg: cs.backgroundImage.slice(0, 40), bc: cs.borderColor,
    };
  };
  const all = (s) => [...root.querySelectorAll(s)].map(pick);
  const q = (s) => pick(root.querySelector(s));
  return {
    root: pick(root),
    kicker: q('.eyebrow'),
    h: q('h1, h2'),
    lead: q('.lead'),
    // showcase
    person: q('.show__person[data-active]'),
    personImg: q('.show__person[data-active] img'),
    content: q('.show__content'),
    bio: q('.show__bio[data-active]'),
    picker: q('.show__picker'),
    avatars: all('.show__avatar'),
    thumb: q('.show__thumb'),
    metas: q('.show__metas'),
    meta: q('.show__meta[data-active]'),
    name: q('.show__meta[data-active] .show__name'),
    role: q('.show__meta[data-active] .show__role'),
    aff: q('.show__meta[data-active] .show__aff'),
    link: q('.show__meta[data-active] .show__link'),
    linkIcon: q('.show__meta[data-active] .show__link-icon svg'),
    strip: q('.show__strip'),
    // journey
    journey: q('.journey'),
    steps: all('.journey__step'),
    nums: all('.journey__num'),
    nodes: all('.journey__node'),
    stitles: all('.journey__title'),
    texts: all('.journey__text'),
    facts: q('.journey__facts'),
    fact: all('.journey__fact'),
    values: all('.journey__value'),
    labels: all('.journey__label'),
    note: q('.journey__note'),
    noteLink: q('.journey__note a'),
  };
};

const browser = await launch();
const report = {};
for (const w of widths) {
  for (const lang of langs) {
    for (const site of ['astro', 'wp']) {
      const ctx = await browser.newContext({ viewport: { width: w, height: 1000 }, reducedMotion: mode === 'reduce' ? 'reduce' : 'no-preference' });
      const page = await ctx.newPage();
      const errors = [];
      page.on('pageerror', (e) => errors.push(e.message));
      page.on('console', (m) => { if (m.type() === 'error') errors.push('console: ' + m.text()); });
      const url = (site === 'astro' ? ASTRO : WP) + urls[site][lang];
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
      for (const [name, sel] of Object.entries(sections)) {
        const el = page.locator(sel[site]).first();
        if (!(await el.count())) { console.log('missing', site, name, w, lang); continue; }
        await el.scrollIntoViewIfNeeded();
        await page.waitForTimeout(mode === 'reduce' ? 150 : 1100);
        /* images in the section fully decoded */
        await page.evaluate((s) => Promise.all([...document.querySelectorAll(s + ' img')].map((i) => (i.complete ? null : new Promise((r) => { i.onload = i.onerror = r; })))), sel[site]);
        await el.screenshot({ path: `${out}/${name}-${w}-${lang}-${site}.png` });
        report[`${name}-${w}-${lang}-${site}`] = await page.evaluate(probe, sel[site]);
      }
      if (errors.length) console.log(site, w, lang, 'errors:', errors);
      await ctx.close();
    }
  }
}
fs.writeFileSync(`${out}/report.json`, JSON.stringify(report, null, 1));
await browser.close();
console.log('done', out);
