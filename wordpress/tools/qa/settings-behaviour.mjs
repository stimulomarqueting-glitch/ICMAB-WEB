/**
 * QA (module "settings"): assets/js/site.js behaviours + header chrome.
 *   node wordpress/tools/qa/settings-behaviour.mjs <outDir>
 *  - lead tracking: utm capture in sessionStorage (first landing wins),
 *    window.matsLead API, data-cta-source clicks → 'lead:cta_source' +
 *    'lead:track' cta_click event
 *  - burger menu: open/close labels, Escape closes and returns focus, a link
 *    click closes it, back to desktop closes it
 *  - reveal: thresholds (sections below the fold stay hidden until
 *    scrolled), prefers-reduced-motion shows everything at once
 *  - sticky glass header over content (Astro vs WP shot) and admin-bar
 *    offsets for a logged-in user (32 / 46 / 0 px)
 * Needs settings-setup.php. Exit code 1 when a check fails.
 */
import { mkdirSync } from 'node:fs';
import { launch } from './wp-login.mjs';

const out = process.argv[2] || '/tmp/settings-behaviour';
mkdirSync(out, { recursive: true });
const WP = process.env.WP_BASE || 'http://localhost:8917';
const ASTRO = 'http://127.0.0.1:4321';

/* wp-login.mjs reads WP_BASE at import time; log in against our own port */
async function login(browser, user, pass, viewport) {
  const context = await browser.newContext({ viewport });
  const page = await context.newPage();
  await page.goto(`${WP}/wp-login.php`);
  await page.fill('#user_login', user);
  await page.fill('#user_pass', pass);
  await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);
  return page;
}
let failures = 0;
const check = (ok, label, extra = '') => {
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}${extra ? '  — ' + extra : ''}`);
  if (!ok) failures++;
};

const browser = await launch();

/* ── lead tracking ─────────────────────────────────────────────────── */
{
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const p = await ctx.newPage();
  const errors = [];
  p.on('pageerror', (e) => errors.push(String(e)));
  await p.goto(`${WP}/product/?utm_source=newsletter&utm_medium=email&utm_campaign=launch&utm_content=hero&utm_term=surface`);
  const utm = await p.evaluate(() => sessionStorage.getItem('lead:utm'));
  check(utm === JSON.stringify({ source: 'newsletter', medium: 'email', campaign: 'launch', content: 'hero', term: 'surface' }), 'utm_* stored in sessionStorage lead:utm', utm);
  const api = await p.evaluate(() => ({
    keys: Object.keys(window.matsLead || {}).sort().join(','),
    utm: window.matsLead && window.matsLead.getUtm(),
    source: window.matsLead && window.matsLead.getCtaSource(),
  }));
  check(api.keys.includes('getCtaSource') && api.keys.includes('getUtm') && api.keys.includes('trackEvent'), 'window.matsLead exposes trackEvent/getUtm/getCtaSource', api.keys);
  check(api.utm && api.utm.campaign === 'launch' && api.utm.term === 'surface', 'matsLead.getUtm() returns the stored params', JSON.stringify(api.utm));
  check(api.source === 'direct', "getCtaSource() is 'direct' before any CTA click", api.source);

  /* second landing with other utm: first landing wins */
  await p.goto(`${WP}/team/?utm_source=other`);
  const utm2 = await p.evaluate(() => JSON.parse(sessionStorage.getItem('lead:utm')).source);
  check(utm2 === 'newsletter', 'first landing wins (utm not overwritten)', utm2);

  /* trackEvent dispatches lead:track */
  const ev = await p.evaluate(() => {
    let got = null;
    window.addEventListener('lead:track', (e) => (got = e.detail), { once: true });
    window.matsLead.trackEvent('form_start', { a: 1 });
    return got;
  });
  check(ev && ev.name === 'form_start' && ev.data.a === 1, "trackEvent() dispatches window 'lead:track' { name, data }", JSON.stringify(ev));

  /* CTA click: event + source stored (record the event across the navigation) */
  await p.evaluate(() => {
    window.addEventListener('lead:track', (e) => sessionStorage.setItem('qa:last', JSON.stringify(e.detail)));
  });
  await Promise.all([p.waitForNavigation(), p.click('.header__cta')]);
  const after = await p.evaluate(() => ({
    source: sessionStorage.getItem('lead:cta_source'),
    api: window.matsLead.getCtaSource(),
    last: JSON.parse(sessionStorage.getItem('qa:last') || 'null'),
    path: location.pathname,
  }));
  check(after.source === 'header' && after.api === 'header', 'header CTA click stores lead:cta_source=header', JSON.stringify(after));
  check(after.last && after.last.name === 'cta_click' && after.last.data.source === 'header', 'cta_click lead:track event sent', JSON.stringify(after.last));
  check(after.path === '/contact/', 'header CTA leads to the contact page', after.path);

  /* storage blocked: nothing breaks */
  const ctx2 = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  await ctx2.addInitScript(() => {
    Object.defineProperty(window, 'sessionStorage', { get() { throw new Error('blocked'); } });
  });
  const p2 = await ctx2.newPage();
  const errors2 = [];
  p2.on('pageerror', (e) => errors2.push(String(e)));
  await p2.goto(`${WP}/?utm_source=x`);
  const blocked = await p2.evaluate(() => ({ src: window.matsLead.getCtaSource(), utm: window.matsLead.getUtm().source }));
  check(errors2.length === 0 && blocked.src === 'direct' && blocked.utm === '', 'blocked sessionStorage: no errors, safe defaults', JSON.stringify({ blocked, errors2 }));
  await ctx2.close();
  check(errors.length === 0, 'no JavaScript errors', errors.join(' | '));
  await ctx.close();
}

/* ── burger menu ───────────────────────────────────────────────────── */
for (const lang of ['en', 'es']) {
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const p = await ctx.newPage();
  await p.goto(`${WP}${lang === 'es' ? '/es/producto/' : '/product/'}`);
  const state = () =>
    p.evaluate(() => {
      const b = document.querySelector('.header__burger');
      return {
        expanded: b.getAttribute('aria-expanded'),
        label: b.getAttribute('aria-label'),
        open: document.querySelector('.header__nav').classList.contains('is-open'),
        visible: getComputedStyle(document.querySelector('.header__nav')).display !== 'none',
        focus: document.activeElement === b,
      };
    });
  let s = await state();
  check(s.expanded === 'false' && !s.visible, `[${lang}] menu closed on load`, JSON.stringify(s));
  await p.click('.header__burger');
  s = await state();
  check(s.expanded === 'true' && s.open && s.visible && s.label === (lang === 'es' ? 'Cerrar menú' : 'Close menu'), `[${lang}] burger opens the menu, label "close"`, JSON.stringify(s));
  await p.screenshot({ path: `${out}/menu-open-${lang}.png` });
  await p.locator('.header__menu a').first().focus();
  await p.keyboard.press('Escape');
  s = await state();
  check(s.expanded === 'false' && !s.open && s.focus && s.label === (lang === 'es' ? 'Abrir menú' : 'Open menu'), `[${lang}] Escape closes and returns the focus to the burger`, JSON.stringify(s));
  await p.click('.header__burger');
  await p.evaluate(() => window.addEventListener('click', (e) => e.preventDefault(), { capture: true, once: true }));
  await p.click('.header__menu li:nth-child(2) a');
  s = await state();
  check(s.expanded === 'false' && !s.open, `[${lang}] a link click closes the menu`, JSON.stringify(s));
  await p.click('.header__burger');
  await p.setViewportSize({ width: 1300, height: 844 });
  await p.waitForTimeout(100);
  s = await state();
  check(s.expanded === 'false' && !s.open, `[${lang}] back to desktop width closes the menu`, JSON.stringify(s));
  const current = await p.evaluate(() => [...document.querySelectorAll('.header__menu a[aria-current="page"]')].map((a) => a.textContent.trim()));
  check(current.length === 1 && current[0] === (lang === 'es' ? 'Producto' : 'Product'), `[${lang}] current page marked in the menu`, current.join(','));
  const sw = await p.evaluate(() => [...document.querySelectorAll('.lang a')].map((a) => `${a.textContent.trim()}:${new URL(a.href).pathname}:${a.getAttribute('aria-current') || ''}`));
  check(sw.join(' ') === (lang === 'es' ? 'EN:/product/: ES:/es/producto/:true' : 'EN:/product/:true ES:/es/producto/:'), `[${lang}] language switcher points to the translation`, sw.join(' '));
  await ctx.close();
}

/* ── reveal ────────────────────────────────────────────────────────── */
{
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: 'no-preference' });
  const p = await ctx.newPage();
  await p.goto(`${WP}/qa-reveal/`);
  await p.waitForTimeout(300);
  const vis = () => p.evaluate(() => [...document.querySelectorAll('.reveal')].map((el) => el.classList.contains('is-visible')));
  let v = await vis();
  check(v.length >= 4 && v[0] && !v[v.length - 1], 'reveal: first section visible, last one still hidden', JSON.stringify(v));
  const op = await p.evaluate(() => getComputedStyle(document.querySelectorAll('.reveal')[document.querySelectorAll('.reveal').length - 1]).opacity);
  check(op === '0', 'reveal: hidden section has opacity 0 (motion-safe)', op);
  /* just entering the viewport is not enough: 12 % + 40 px margin */
  const lastTop = await p.evaluate(() => {
    const els = document.querySelectorAll('.reveal');
    return els[els.length - 1].getBoundingClientRect().top + window.scrollY;
  });
  await p.evaluate((y) => window.scrollTo(0, y - window.innerHeight + 20), lastTop);
  await p.waitForTimeout(250);
  v = await vis();
  check(!v[v.length - 1], 'reveal: 20 px inside the viewport is not enough (rootMargin -40px)', JSON.stringify(v));
  await p.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
  await p.waitForTimeout(400);
  v = await vis();
  check(v.every(Boolean) || v[v.length - 1], 'reveal: scrolling down reveals the last section', JSON.stringify(v));
  await ctx.close();

  const ctx2 = await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
  const p2 = await ctx2.newPage();
  await p2.goto(`${WP}/qa-reveal/`);
  await p2.waitForTimeout(100);
  const v2 = await p2.evaluate(() => [...document.querySelectorAll('.reveal')].map((el) => el.classList.contains('is-visible')));
  check(v2.length >= 4 && v2.every(Boolean), 'reveal: prefers-reduced-motion shows every section at once', JSON.stringify(v2));
  await ctx2.close();
}

/* ── sticky glass header over content: Astro vs WP ─────────────────── */
for (const width of [1440, 390]) {
  for (const site of ['astro', 'wp']) {
    const ctx = await browser.newContext({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
    const p = await ctx.newPage();
    await p.goto(site === 'astro' ? `${ASTRO}/product/` : `${WP}/product/`, { waitUntil: 'networkidle' });
    await p.evaluate(() => document.fonts.ready);
    /* put the CTA band's card 20 px under the header's top edge */
    await p.evaluate(() => {
      const el = document.querySelector('.pcta');
      window.scrollTo(0, el.getBoundingClientRect().top + window.scrollY - 30);
    });
    await p.waitForTimeout(300);
    const info = await p.evaluate(() => {
      const h = document.querySelector('.header');
      const cs = getComputedStyle(h);
      return { top: h.getBoundingClientRect().top, position: cs.position, blur: cs.backdropFilter || cs.webkitBackdropFilter };
    });
    check(info.top === 0 && info.position === 'sticky' && /blur/.test(info.blur), `[${site} ${width}] header stays on top with the glass blur`, JSON.stringify(info));
    await p.screenshot({ path: `${out}/sticky-${site}-${width}.png`, clip: { x: 0, y: 0, width, height: 68 } });
    await ctx.close();
  }
}

/* ── admin bar offsets (logged in) ─────────────────────────────────── */
{
  const page = await login(browser, 'admin', 'admin-local-pass', { width: 1440, height: 900 });
  for (const [width, expected] of [[1440, 32], [700, 46], [390, 0]]) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto(`${WP}/qa-reveal/`);
    await page.evaluate(() => window.scrollTo(0, 600));
    await page.waitForTimeout(200);
    const top = await page.evaluate(() => Math.round(document.querySelector('.header').getBoundingClientRect().top));
    check(top === expected, `admin bar: sticky header sits at ${expected}px at ${width}px`, String(top));
    await page.screenshot({ path: `${out}/adminbar-${width}.png`, clip: { x: 0, y: 0, width, height: 140 } });
  }
  await page.context().close();
}

await browser.close();
console.log(failures ? `${failures} check(s) failed` : 'all checks passed');
process.exit(failures ? 1 : 0);
