/**
 * READ-ONLY audit of a live MatSurfer WordPress install (never submits the
 * form, never logs in). Compares it with the Astro build served locally.
 *
 *   LIVE=https://example.com ASTRO=http://127.0.0.1:4321 OUT=/tmp/live \
 *     node wordpress/tools/qa/live-audit.mjs
 *
 * Writes OUT/report.json + full-page screenshots (live vs Astro, 1440 and 390)
 * and prints a summary of every check.
 */
import { chromium } from 'playwright-core';
import { mkdirSync, writeFileSync } from 'node:fs';

const LIVE = (process.env.LIVE || '').replace(/\/$/, '');
const ASTRO = (process.env.ASTRO || 'http://127.0.0.1:4321').replace(/\/$/, '');
const OUT = process.env.OUT || '/tmp/live';
if (!LIVE) throw new Error('Set LIVE=https://…');
mkdirSync(OUT, { recursive: true });

const PAGES = [
  ['home', '/', '/'], ['product', '/product/', '/product/'], ['applications', '/applications/', '/applications/'],
  ['research', '/research/', '/research/'], ['team', '/team/', '/team/'], ['contact', '/contact/', '/contact/'],
  ['es-home', '/es/', '/es/'], ['es-product', '/es/producto/', '/es/product/'], ['es-applications', '/es/aplicaciones/', '/es/applications/'],
  ['es-research', '/es/casos-de-estudio/', '/es/research/'], ['es-team', '/es/equipo/', '/es/team/'], ['es-contact', '/es/contacto/', '/es/contact/'],
];
const report = { live: LIVE, checks: [], pages: {}, http: {}, contact: {}, visual: {} };
const check = (ok, name, detail = '') => {
  report.checks.push({ ok: !!ok, name, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${detail ? ' — ' + detail : ''}`);
};

/* ── 1. HTTP level ─────────────────────────────────────────────────── */
async function head(url, opts = {}) {
  const res = await fetch(url, { redirect: 'manual', ...opts });
  return { status: res.status, location: res.headers.get('location') || '', headers: Object.fromEntries(res.headers) };
}
for (const [, path] of PAGES) {
  const r = await head(LIVE + path);
  report.http[path] = r;
  check(r.status === 200, `HTTP 200 ${path}`, String(r.status));
}
{
  const r = report.http['/'];
  const h = r.headers;
  check(!h['x-powered-by'], 'no X-Powered-By header', h['x-powered-by'] || '');
  check(/nosniff/i.test(h['x-content-type-options'] || ''), 'X-Content-Type-Options nosniff', h['x-content-type-options'] || 'missing');
  check(!!h['referrer-policy'], 'Referrer-Policy present', h['referrer-policy'] || 'missing');
  check(/sameorigin/i.test(h['x-frame-options'] || ''), 'X-Frame-Options SAMEORIGIN', h['x-frame-options'] || 'missing');
}
const httpUrl = LIVE.replace(/^https:/, 'http:') + '/';
try {
  const r = await head(httpUrl);
  check([301, 302, 307, 308].includes(r.status) && r.location.startsWith('https://'), 'http → https redirect', `${r.status} ${r.location}`);
} catch (e) {
  check(false, 'http → https redirect', e.message);
}
for (const [path, want] of [['/es/product/', '/es/producto/'], ['/es/team/', '/es/equipo/']]) {
  const r = await head(LIVE + path);
  check(r.status === 301 && r.location.includes(want), `legacy ${path} → ${want}`, `${r.status} ${r.location}`);
}
for (const [path, want, label] of [
  ['/wp-json/wp/v2/users', [401], 'REST users hidden from visitors'],
  ['/xmlrpc.php', [403, 405], 'XML-RPC disabled'],
  ['/nonexistent-page-xyz/', [404], '404 page'],
  ['/robots.txt', [200], 'robots.txt'],
  ['/wp-sitemap.xml', [200, 404], 'sitemap (404 is expected while «Disuadir» is on)'],
  ['/favicon.ico', [200, 301, 302], 'favicon.ico answered'],
]) {
  const r = await head(LIVE + path, path === '/xmlrpc.php' ? { method: 'POST', body: '<x/>' } : {});
  check(want.includes(r.status), label, `${r.status} ${r.location}`);
}
{
  const r = await fetch(LIVE + '/?author=1', { redirect: 'manual' });
  check(r.status !== 200 || !(await r.text()).includes('author'), 'no author enumeration (?author=1)', String(r.status));
}

/* ── 2. Browser level ──────────────────────────────────────────────── */
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });

async function settle(page) {
  /* scroll through the page so lazy images load, then back to top */
  await page.evaluate(async () => {
    const h = document.documentElement.scrollHeight;
    for (let y = 0; y < h; y += 500) { window.scrollTo(0, y); await new Promise((r) => setTimeout(r, 60)); }
    window.scrollTo(0, 0);
  });
  await page.waitForLoadState('networkidle').catch(() => {});
  await page.addStyleTag({ content: '.reveal{opacity:1!important;transform:none!important}' });
  await page.waitForTimeout(400);
}

for (const width of [1440, 390]) {
  const ctx = await browser.newContext({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
  for (const [name, livePath, astroPath] of PAGES) {
    const page = await ctx.newPage();
    const errors = [];
    const failed = [];
    const thirdParty = new Set();
    page.on('pageerror', (e) => errors.push(e.message));
    page.on('console', (m) => { if (m.type() === 'error') errors.push('console: ' + m.text()); });
    page.on('requestfailed', (r) => failed.push(r.url()));
    page.on('response', (r) => { if (r.status() >= 400 && r.url().startsWith(LIVE)) failed.push(`${r.status()} ${r.url()}`); });
    page.on('request', (r) => {
      const u = r.url();
      const m = u.match(/\/wp-content\/plugins\/([^/]+)\//);
      if (m) thirdParty.add('plugin:' + m[1]);
      else if (!u.startsWith(LIVE) && !u.startsWith('data:')) thirdParty.add('external:' + new URL(u).host);
    });
    await page.goto(LIVE + livePath, { waitUntil: 'load' });
    await settle(page);
    const info = await page.evaluate(() => {
      const q = (s) => document.querySelector(s);
      const metas = (sel) => [...document.querySelectorAll(sel)].map((e) => e.getAttribute('content') || e.getAttribute('href'));
      const ld = [...document.querySelectorAll('script[type="application/ld+json"]')].map((s) => { try { JSON.parse(s.textContent); return 'ok'; } catch (e) { return 'invalid'; } });
      const imgs = [...document.images].filter((i) => i.getBoundingClientRect().width > 0);
      return {
        lang: document.documentElement.lang,
        title: document.title,
        description: q('meta[name="description"]')?.content || '',
        robots: metas('meta[name="robots"]').join(' | '),
        canonical: q('link[rel="canonical"]')?.href || '',
        hreflang: [...document.querySelectorAll('link[rel="alternate"][hreflang]')].map((l) => l.hreflang + '=' + l.href),
        og: metas('meta[property^="og:"]').length,
        jsonld: ld,
        theme: !!q('link[href*="/themes/matsurfer/assets/css/base.css"]'),
        header: !!q('header.header'),
        footer: !!q('footer.footer'),
        h1: [...document.querySelectorAll('h1')].map((h) => h.textContent.replace(/\s+/g, ' ').trim()),
        brokenImages: imgs.filter((i) => !i.complete || i.naturalWidth === 0).map((i) => i.currentSrc || i.src),
        editMarkers: document.querySelectorAll('[data-mats-edit],[data-mats-image]').length,
        adminBar: !!q('#wpadminbar'),
        scrollX: document.documentElement.scrollWidth > window.innerWidth + 1,
      };
    });
    const key = `${name}@${width}`;
    report.pages[key] = { ...info, errors, failed: [...new Set(failed)], thirdParty: [...thirdParty] };
    if (width === 1440) {
      check(info.theme && info.header && info.footer, `${name}: MatSurfer theme, header and footer`);
      check(info.lang.startsWith(name.startsWith('es') ? 'es' : 'en'), `${name}: html lang`, info.lang);
      check(info.h1.length === 1, `${name}: exactly one h1`, JSON.stringify(info.h1));
      check(info.hreflang.length >= 2, `${name}: hreflang alternates`, info.hreflang.join(' '));
      check(/noindex/.test(info.robots), `${name}: noindex while staging («Disuadir» on)`, info.robots || 'none');
      check(info.jsonld.length > 0 && info.jsonld.every((x) => x === 'ok'), `${name}: JSON-LD parses`, info.jsonld.join(','));
      check(info.editMarkers === 0, `${name}: no editor markers on the public page`);
    }
    check(errors.length === 0, `${name}@${width}: no JS errors`, errors.slice(0, 3).join(' | '));
    check(failed.length === 0, `${name}@${width}: no failed requests`, [...new Set(failed)].slice(0, 4).join(' | '));
    check(info.brokenImages.length === 0, `${name}@${width}: all images load`, info.brokenImages.slice(0, 3).join(' | '));
    check(!info.scrollX, `${name}@${width}: no horizontal scroll`);
    await page.screenshot({ path: `${OUT}/${name}-${width}-live.png`, fullPage: true });

    /* Astro reference */
    const ref = await ctx.newPage();
    await ref.goto(ASTRO + astroPath, { waitUntil: 'load' });
    await settle(ref);
    await ref.screenshot({ path: `${OUT}/${name}-${width}-astro.png`, fullPage: true });
    const [hl, ha] = await Promise.all([page.evaluate(() => document.documentElement.scrollHeight), ref.evaluate(() => document.documentElement.scrollHeight)]);
    report.visual[key] = { liveHeight: hl, astroHeight: ha };
    await ref.close();
    await page.close();
  }
  await ctx.close();
}

/* ── 3. Contact form with the real WPForms ─────────────────────────── */
for (const [lang, path, query] of [['en', '/contact/', '?interest=product&application=sensors'], ['es', '/es/contacto/', '?interest=research&application=coatings']]) {
  for (const width of [1440, 390]) {
    const ctx = await browser.newContext({ viewport: { width, height: 1000 } });
    const page = await ctx.newPage();
    await page.goto(LIVE + path + query, { waitUntil: 'networkidle' });
    const f = await page.evaluate(() => {
      const form = document.querySelector('.lf__card form.wpforms-form, .lf__card form');
      if (!form) return { form: false };
      const field = (id) => form.querySelector(`[id$="_${id}-container"], #wpforms-${form.dataset.formid}-field_${id}-container`);
      const sel = (id) => form.querySelector(`select[name="wpforms[fields][${id}]"]`);
      const btn = form.querySelector('button[type="submit"]');
      const cs = btn ? getComputedStyle(btn) : null;
      const rect = (el) => (el ? el.getBoundingClientRect() : null);
      const f1 = rect(field(1));
      const f2 = rect(field(2));
      return {
        form: true,
        formId: form.dataset.formid || form.id,
        fields: [1, 2, 3, 4, 5, 6, 7, 8].map((id) => !!field(id)),
        labels: [...form.querySelectorAll('.wpforms-field-label')].map((l) => l.textContent.replace(/\s+/g, ' ').trim()),
        interest: sel(5)?.value || '',
        area: sel(6)?.value || '',
        interestOptions: sel(5) ? [...sel(5).options].map((o) => o.textContent.trim()) : [],
        meta: [...form.querySelectorAll('input[name^="matsurfer_meta"]')].map((i) => i.name.replace(/^matsurfer_meta\[|\]$/g, '')),
        consentLink: form.querySelector('.wpforms-field-checkbox a, .wpforms-field-gdpr-checkbox a')?.href || '',
        twoColumns: f1 && f2 ? Math.abs(f1.top - f2.top) < 2 && f2.left > f1.left : null,
        button: cs ? { bg: cs.backgroundColor, radius: cs.borderRadius, color: cs.color, height: btn.getBoundingClientRect().height, text: btn.textContent.trim() } : null,
        wpformsCss: [...document.styleSheets].some((s) => (s.href || '').includes('wpforms')),
      };
    });
    report.contact[`${lang}@${width}`] = f;
    check(f.form, `contact ${lang}@${width}: WPForms form rendered inside the card`);
    if (f.form) {
      check(f.fields.every(Boolean), `contact ${lang}@${width}: fields 1–8 present`, JSON.stringify(f.fields));
      check(!!f.interest, `contact ${lang}@${width}: interest preselected from ?interest`, f.interest);
      check(!!f.area, `contact ${lang}@${width}: area preselected from ?application`, f.area);
      check(f.meta.length >= 4, `contact ${lang}@${width}: tracking inputs present`, f.meta.join(','));
      if (width === 1440) check(f.twoColumns === true, `contact ${lang}@1440: two-column grid`);
      if (width === 390) check(f.twoColumns === false, `contact ${lang}@390: single column`);
      check(f.button && /999|9999|px/.test(f.button.radius) && parseFloat(f.button.radius) > 20, `contact ${lang}@${width}: pill submit button`, JSON.stringify(f.button));
    }
    /* client-side validation only: submit empty → error messages, nothing is sent */
    if (width === 1440 && f.form) {
      let posted = false;
      page.on('request', (r) => { if (r.method() === 'POST') posted = true; });
      await page.click('.lf__card button[type="submit"]');
      await page.waitForTimeout(800);
      const errs = await page.$$eval('.lf__card label.wpforms-error, .lf__card .wpforms-error', (els) => els.map((e) => e.textContent.trim()).filter(Boolean));
      report.contact[`${lang}@${width}`].validation = errs;
      check(errs.length >= 4 && !posted, `contact ${lang}: empty submit shows errors and sends nothing`, `${errs.length} errors · posted=${posted} · ${errs.slice(0, 2).join(' / ')}`);
    }
    await page.screenshot({ path: `${OUT}/contact-form-${lang}-${width}.png`, fullPage: true });
    await ctx.close();
  }
}

await browser.close();
writeFileSync(`${OUT}/report.json`, JSON.stringify(report, null, 1));
const fails = report.checks.filter((c) => !c.ok);
console.log(`\n${report.checks.length - fails.length}/${report.checks.length} checks passed`);
const plugins = new Set(Object.values(report.pages).flatMap((p) => p.thirdParty));
console.log('front-end assets from plugins/external hosts:', [...plugins].join(', ') || 'none');
