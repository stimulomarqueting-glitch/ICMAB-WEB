/**
 * QA (group "appsresearch"): hover states, the scroll-spy of the sticky
 * applications nav (scrolling + pill click), anchor landing (:target halo)
 * and the reduced-motion case, Astro vs WordPress.
 *   node wordpress/tools/qa/appsresearch-behaviour.mjs <outDir>
 * (run from /home/user/ICMAB-WEB so playwright-core resolves)
 */
import { launch } from './wp-login.mjs';
import fs from 'node:fs';

const out = process.argv[2] || '/tmp/appsresearch-behaviour';
fs.mkdirSync(out, { recursive: true });
const ASTRO = 'http://127.0.0.1:4321';
const WP = process.env.WP_BASE || 'http://localhost:8906';
const urls = {
  applications: { astro: { en: '/applications/', es: '/es/applications/' }, wp: { en: '/applications/', es: '/es/aplicaciones/' } },
  research: { astro: { en: '/research/', es: '/es/research/' }, wp: { en: '/research/', es: '/es/investigacion/' } },
};
const revealShim = () => {
  if (document.querySelector('.reveal.is-visible')) return;
  const io = new IntersectionObserver((entries) => {
    for (const e of entries) if (e.isIntersecting) { e.target.classList.add('is-visible'); io.unobserve(e.target); }
  }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
  document.querySelectorAll('.reveal').forEach((el) => io.observe(el));
};
const styleOf = (sel) => {
  const el = document.querySelector(sel);
  if (!el) return null;
  const cs = getComputedStyle(el);
  const b = getComputedStyle(el, '::before');
  return { color: cs.color, bg: cs.backgroundColor, bd: cs.borderColor, sh: cs.boxShadow, tf: cs.transform, pl: cs.paddingLeft, beforeW: b.width, beforeH: b.height, anim: cs.animationName };
};
const current = () => [...document.querySelectorAll('[data-apps-nav] [data-target]')].filter((p) => p.hasAttribute('aria-current')).map((p) => p.dataset.target);

const browser = await launch();
const results = {};
const hovers = [
  ['applications', '.apps-nav__pill:nth-child(2)'],
  ['applications', 'article.app:nth-child(2)'],
  ['research', '.research__card:nth-child(1)'],
  ['research', '.research__pub:nth-child(1)'],
  ['research', '.research__pub:nth-child(1) h3 a'],
  ['research', '.page__contact p a'],
];
for (const site of ['astro', 'wp']) {
  for (const motion of ['no-preference', 'reduce']) {
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: motion });
    const page = await ctx.newPage();
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));
    const base = site === 'astro' ? ASTRO : WP;
    const r = (results[`${site}-${motion}`] = {});

    /* hovers */
    for (const [role, sel] of hovers) {
      await page.goto(base + urls[role][site].en, { waitUntil: 'networkidle' });
      if (site === 'wp') await page.evaluate(revealShim);
      const el = page.locator(sel).first();
      await el.scrollIntoViewIfNeeded();
      await page.waitForTimeout(2000);
      const before = await page.evaluate(styleOf, sel);
      await el.hover();
      await page.waitForTimeout(600);
      const after = await page.evaluate(styleOf, sel);
      r[`hover ${sel}`] = { before, after };
      if (motion === 'no-preference') {
        const box = await el.boundingBox();
        await page.screenshot({ path: `${out}/hover-${sel.replace(/[^a-z0-9]/gi, '')}-${site}.png`, clip: { x: Math.max(0, box.x - 30), y: Math.max(0, box.y - 30), width: Math.min(1440, box.width + 60), height: box.height + 60 } });
      }
      await page.mouse.move(0, 0);
    }

    /* scroll-spy: scroll through the applications, then click a pill */
    await page.goto(base + urls.applications[site].en, { waitUntil: 'networkidle' });
    const spy = [];
    for (const id of ['organic-electronics', 'solar-cells', 'sensors', 'coatings', 'spm-sample-preparation']) {
      await page.evaluate((i) => {
        const el = document.getElementById(i);
        window.scrollTo(0, el.getBoundingClientRect().top + scrollY - window.innerHeight * 0.3 + 10);
      }, id);
      await page.waitForTimeout(500);
      spy.push(`${id}→${(await page.evaluate(current)).join(',')}`);
    }
    r.scrollSpy = spy;
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.waitForTimeout(300);
    await page.click('.apps-nav__pill[data-target="coatings"]');
    await page.waitForTimeout(1500);
    r.afterClick = {
      current: await page.evaluate(current),
      hash: await page.evaluate(() => location.hash),
      cardTop: await page.evaluate(() => Math.round(document.getElementById('coatings').getBoundingClientRect().top)),
      navTop: await page.evaluate(() => Math.round(document.querySelector('.apps-nav').getBoundingClientRect().top)),
      target: await page.evaluate(() => getComputedStyle(document.getElementById('coatings')).animationName),
    };
    if (motion === 'no-preference') await page.screenshot({ path: `${out}/after-click-${site}.png` });

    /* anchor landing from another page (home chips) */
    await page.goto(base + urls.applications[site].en + '#sensors', { waitUntil: 'networkidle' });
    await page.waitForTimeout(1200);
    r.landing = {
      cardTop: await page.evaluate(() => Math.round(document.getElementById('sensors').getBoundingClientRect().top)),
      anim: await page.evaluate(() => getComputedStyle(document.getElementById('sensors')).animationName),
      current: await page.evaluate(current),
    };
    r.errors = errors;
    await ctx.close();
  }
}
fs.writeFileSync(`${out}/behaviour.json`, JSON.stringify(results, null, 1));
for (const motion of ['no-preference', 'reduce']) {
  const a = results[`astro-${motion}`];
  const w = results[`wp-${motion}`];
  for (const k of Object.keys(a)) {
    const same = JSON.stringify(a[k]) === JSON.stringify(w[k]);
    console.log(`${motion} ${k}: ${same ? 'SAME' : 'DIFF'}`);
    if (!same) console.log('  astro', JSON.stringify(a[k]), '\n  wp   ', JSON.stringify(w[k]));
  }
}
await browser.close();
