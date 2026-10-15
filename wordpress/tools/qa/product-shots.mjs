/**
 * QA (group "product"): screenshots of the product page sections
 * (#differentiator, #how-it-works, #parameters, #faq) in the Astro reference
 * and in WordPress, plus a geometry/computed-style dump for a numeric
 * comparison, plus interactive states (hover, FAQ open/close, video facade).
 *   node wordpress/tools/qa/product-shots.mjs <outDir>
 * env: WP_BASE (default http://localhost:8905), WIDTHS, LANGS, STATES=0 to skip states
 * (run from /home/user/ICMAB-WEB so playwright-core resolves)
 */
import { launch } from './wp-login.mjs';
import fs from 'node:fs';

const out = process.argv[2] || '/tmp/product';
fs.mkdirSync(out, { recursive: true });

const ASTRO = 'http://127.0.0.1:4321';
const WP = process.env.WP_BASE || 'http://localhost:8905';
const paths = { astro: { en: '/product/', es: '/es/product/' }, wp: { en: '/product/', es: '/es/producto/' } };
const sections = ['#differentiator', '#how-it-works', '#parameters', '#faq'];
const widths = (process.env.WIDTHS || '1440,820,390').split(',').map(Number);
const langs = (process.env.LANGS || 'en,es').split(',');

const probe = (sel) => {
  const root = document.querySelector(sel);
  if (!root) return null;
  const pick = (el) => {
    if (!el) return null;
    const r = el.getBoundingClientRect();
    const rr = root.getBoundingClientRect();
    const cs = getComputedStyle(el);
    return {
      x: Math.round(r.x * 10) / 10, y: Math.round((r.y - rr.y) * 10) / 10, w: Math.round(r.width * 10) / 10, h: Math.round(r.height * 10) / 10,
      fs: cs.fontSize, lh: cs.lineHeight, ff: cs.fontFamily.split(',')[0], fw: cs.fontWeight, color: cs.color, bg: cs.backgroundColor, bgi: cs.backgroundImage.slice(0, 60),
      ta: cs.textAlign, disp: cs.display, mt: cs.marginTop, pad: cs.padding, bd: cs.border, br: cs.borderRadius, gtc: cs.gridTemplateColumns, gap: cs.gap, ls: cs.letterSpacing, tt: cs.textTransform, shadow: cs.boxShadow,
    };
  };
  const res = { root: pick(root) };
  const sels = [
    '.container', '.eyebrow', 'h2', '.lead',
    '.diff__hero', '.diff__d', '.diff__d .mono', '.diff__d-line', '.diff__text', '.diff__grid', '.diff__feature', '.diff__feature h3', '.diff__feature p', '.diff__feature:last-child',
    '.tech__media', '.tech__videobox', '.tech__video', '.tech__video-ph', '.tech__poster', '.tech__poster img', '.tech__play', '.tech__play svg', '.tech__media-cap', '.tech__steps', '.tech__step', '.tech__step:last-child', '.tech__sn', '.tech__step h3', '.tech__step p',
    '.params__grid', '.params__card', '.params__card:first-child', '.params__card:last-child', '.params__symbol', '.params__label',
    '.acc', '.acc__list', '.acc__item', '.acc__item:last-child', '.acc__trigger', '.acc__icon', '.acc__panel', '.acc__panel p',
  ];
  for (const s of sels) {
    const el = root.querySelector(s);
    if (el) res[s] = pick(el);
  }
  /* pseudo elements */
  const pse = (s, p) => {
    const el = root.querySelector(s);
    if (!el) return;
    const cs = getComputedStyle(el, p);
    res[`${s}${p}`] = { w: cs.width, h: cs.height, bgi: cs.backgroundImage.slice(0, 80), bg: cs.backgroundColor, top: cs.top, left: cs.left, op: cs.opacity };
  };
  pse('.diff__d-line', '::before');
  pse('.diff__d-line', '::after');
  pse('.diff__feature', '::before');
  pse('.tech__steps', '::before');
  pse('.tech__step', '::before');
  pse('.tech__step:nth-child(3)', '::before');
  pse('.acc__item', '::before');
  pse('.acc__icon', '::before');
  pse('.acc__icon', '::after');
  return res;
};

/* settle the page: every .reveal shown (WP's site.js is still a placeholder,
   Astro's observer only fires on scroll), animations frozen at t=0 */
async function settle(page) {
  await page.evaluate(() => document.fonts.ready);
  await page.evaluate(() => document.querySelectorAll('.reveal').forEach((el) => el.classList.add('is-visible')));
  await page.waitForTimeout(1800);
  await page.evaluate(() => document.getAnimations().forEach((a) => { try { a.pause(); a.currentTime = 0; } catch (e) {} }));
  await page.mouse.move(1, 1);
  await page.waitForTimeout(150);
}

const browser = await launch();
const report = {};
for (const w of widths) {
  for (const lang of langs) {
    for (const site of ['astro', 'wp']) {
      const ctx = await browser.newContext({ viewport: { width: w, height: 1000 } });
      const page = await ctx.newPage();
      const errors = [];
      page.on('pageerror', (e) => errors.push(e.message));
      const url = (site === 'astro' ? ASTRO : WP) + paths[site][lang];
      await page.goto(url, { waitUntil: 'networkidle' });
      await settle(page);
      for (const sel of sections) {
        const el = page.locator(sel).first();
        if (!(await el.count())) {
          console.log('MISSING', site, url, sel);
          continue;
        }
        await el.scrollIntoViewIfNeeded();
        await page.waitForTimeout(250);
        const tag = `${lang}-${w}-${sel.slice(1)}`;
        await el.screenshot({ path: `${out}/${tag}-${site}.png` });
        report[tag] = report[tag] || {};
        report[tag][site] = await page.evaluate(probe, sel);
      }
      if (errors.length) console.log('JS errors', site, url, errors);
      await ctx.close();
    }
  }
}
fs.writeFileSync(`${out}/report.json`, JSON.stringify(report, null, 1));

/* numeric diff */
let diffs = 0;
for (const [tag, r] of Object.entries(report)) {
  if (!r.astro || !r.wp) continue;
  for (const k of new Set([...Object.keys(r.astro), ...Object.keys(r.wp)])) {
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

/* interactive states (hover, FAQ open/close, video facade) */
if (process.env.STATES !== '0') {
  for (const w of [1440, 390]) {
    for (const site of ['astro', 'wp']) {
      const ctx = await browser.newContext({ viewport: { width: w, height: 1000 } });
      const page = await ctx.newPage();
      const errors = [];
      page.on('pageerror', (e) => errors.push(e.message));
      await page.goto((site === 'astro' ? ASTRO : WP) + paths[site].en, { waitUntil: 'networkidle' });
      await settle(page);
      const shot = async (name, sel) => {
        const el = page.locator(sel).first();
        await el.scrollIntoViewIfNeeded();
        await page.waitForTimeout(350);
        await el.screenshot({ path: `${out}/state-${w}-${name}-${site}.png` });
      };
      /* hover: differentiator panel ("d" turns into the gradient) */
      await page.locator('#differentiator .diff__hero').scrollIntoViewIfNeeded();
      await page.locator('#differentiator .diff__hero').hover();
      await shot('diff-hover', '#differentiator .diff__hero');
      /* hover: second process card, poster */
      await page.locator('#how-it-works .tech__step').nth(1).hover();
      await shot('step-hover', '#how-it-works .tech__steps');
      await page.locator('#how-it-works .tech__poster').hover();
      await shot('poster-hover', '#how-it-works .tech__media');
      /* hover: parameter card */
      await page.locator('#parameters .params__card').nth(2).hover();
      await shot('param-hover', '#parameters .params__grid');
      /* FAQ: hover a trigger, open the first two, close the second */
      const t = page.locator('#faq .acc__trigger');
      await t.nth(3).hover();
      await shot('faq-hover', '#faq .acc');
      await t.nth(0).click();
      await t.nth(1).click();
      await page.mouse.move(1, 1);
      await page.waitForTimeout(900);
      await shot('faq-open', '#faq .acc');
      const st = await page.evaluate(() => [...document.querySelectorAll('#faq .acc__item')].slice(0, 3).map((li) => {
        const b = li.querySelector('.acc__trigger');
        const p = li.querySelector('.acc__panel');
        return `${b.getAttribute('aria-expanded')}/${li.hasAttribute('data-open')}/${p.hidden}/${Math.round(p.getBoundingClientRect().height)}`;
      }));
      await t.nth(1).click();
      await page.waitForTimeout(700);
      const st2 = await page.evaluate(() => {
        const li = document.querySelectorAll('#faq .acc__item')[1];
        return `${li.querySelector('.acc__trigger').getAttribute('aria-expanded')}/${li.hasAttribute('data-open')}/${li.querySelector('.acc__panel').hidden}`;
      });
      /* keyboard: Enter on the third trigger opens it */
      await t.nth(2).focus();
      await page.keyboard.press('Enter');
      await page.waitForTimeout(700);
      const st3 = await page.evaluate(() => {
        const li = document.querySelectorAll('#faq .acc__item')[2];
        return `${li.querySelector('.acc__trigger').getAttribute('aria-expanded')}/${li.hasAttribute('data-open')}/${li.querySelector('.acc__panel').hidden}`;
      });
      console.log(`${site} ${w}: faq after opening 0,1 [expanded/data-open/hidden/height]:`, st.join(' '), '| after closing 1:', st2, '| Enter on 2:', st3);
      /* video facade: click removes the poster and starts playback */
      await page.locator('#how-it-works .tech__poster').click();
      await page.waitForTimeout(1500);
      const v = await page.evaluate(() => {
        const vid = document.querySelector('#how-it-works .tech__video');
        return { poster: !!document.querySelector('#how-it-works .tech__poster'), paused: vid ? vid.paused : null, time: vid ? Math.round(vid.currentTime * 10) / 10 : null, err: vid && vid.error ? vid.error.code : null };
      });
      console.log(`${site} ${w}: video after click`, JSON.stringify(v));
      if (errors.length) console.log('JS errors', site, errors);
      await ctx.close();
    }
  }
}
await browser.close();
