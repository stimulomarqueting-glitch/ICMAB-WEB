/**
 * QA (fix bucket "seoblog"): checks the SEO / URL / news fixes over HTTP and
 * in the browser. Prints PASS/FAIL lines, exit code 1 on any FAIL.
 *   WP_BASE=http://localhost:8982 NEWS=en,es node wordpress/tools/qa/fix-seoblog-check.mjs
 * NEWS       languages that have published articles in this state ("" = none;
 *            switch with fix-seoblog-state.php, content from blog-setup.php)
 * SITE_ICON  1 when a WordPress Site Icon is set (core prints/serves it)
 * Checks: /favicon.ico + icon links, legacy Astro ES URLs → ES pages (301,
 * query kept), /page/N/ on the home → home, empty News pages (noindex, out
 * of the sitemap, no hreflang to them, still 200), default category and date
 * archives noindex (+ default category out of the sitemap), search titles
 * and ES quotes, unique landmark names on the news views (axe-core), the
 * alt text of featured images per language.
 */
import { launch, BASE } from './wp-login.mjs';

const NEWS = (process.env.NEWS ?? 'en,es').split(',').map((s) => s.trim()).filter(Boolean);
const SITE_ICON = !!process.env.SITE_ICON;
const AXE = new URL('../../../node_modules/axe-core/axe.min.js', import.meta.url).pathname;
let fails = 0;
const check = (ok, label, detail = '') => {
  if (!ok) fails++;
  console.log(`${ok ? 'PASS' : 'FAIL'} ${label}${detail ? ' — ' + detail : ''}`);
};
const get = async (path, opts = {}) => {
  const res = await fetch(BASE + path, { redirect: 'manual', ...opts });
  return { status: res.status, location: res.headers.get('location') || '', text: opts.method === 'HEAD' ? '' : await res.text(), by: res.headers.get('x-redirect-by') || '' };
};
const rel = (url) => url.replace(BASE, '');
const robots = (html) => (html.match(/<meta name='robots' content='([^']*)'/) || [])[1] || '';
const title = (html) => ((html.match(/<title>([^<]*)<\/title>/) || [])[1] || '').replace(/&#8211;/g, '–').replace(/&amp;/g, '&');
const locs = async (path) => [...(await get(path)).text.matchAll(/<loc>([^<]*)<\/loc>/g)].map((m) => rel(m[1]));

/* ── favicon ─────────────────────────────────────────────────────────── */
{
  const r = await get('/favicon.ico');
  if (SITE_ICON) {
    check(r.status === 302 && !/w-logo/.test(r.location), '/favicon.ico → Site Icon', `${r.status} ${rel(r.location)}`);
  } else {
    check(r.status === 302 && /\/assets\/brand\/icon-512\.png$/.test(r.location), '/favicon.ico → theme icon-512.png', `${r.status} ${rel(r.location)}`);
    const img = await fetch(r.location.startsWith('http') ? r.location : BASE + r.location);
    check(img.status === 200 && /image\/png/.test(img.headers.get('content-type') || ''), 'icon-512.png answers 200 image/png', `${img.status} ${img.headers.get('content-type')}`);
  }
  for (const path of ['/', '/es/producto/', '/news/']) {
    const html = (await get(path)).text;
    const themePng = (html.match(/<link rel="icon" type="image\/png" sizes="512x512" href="[^"]*icon-512\.png">/g) || []).length;
    const touch = (html.match(/<link rel="apple-touch-icon"[^>]*>/g) || []).length;
    const svg = (html.match(/<link rel="icon" type="image\/svg\+xml"[^>]*favicon\.svg/g) || []).length;
    if (SITE_ICON) {
      check(themePng === 0 && touch === 1 && svg === 1, `${path} icons: SVG + core Site Icon tags only`, `themePng=${themePng} touch=${touch} svg=${svg}`);
    } else {
      check(themePng === 1 && touch === 1 && svg === 1, `${path} icons: SVG + PNG + apple-touch-icon once`, `themePng=${themePng} touch=${touch} svg=${svg}`);
    }
  }
}

/* ── legacy Astro ES URLs ────────────────────────────────────────────── */
{
  const map = {
    '/es/product/': '/es/producto/',
    '/es/product': '/es/producto/',
    '/es/applications/': '/es/aplicaciones/',
    '/es/applications': '/es/aplicaciones/',
    '/es/research/': '/es/casos-de-estudio/',
    '/es/team/': '/es/equipo/',
    '/es/contact/': '/es/contacto/',
    '/es/contact/?interest=product': '/es/contacto/?interest=product',
    '/es/contact?interest=applications&ref=qr': '/es/contacto/?interest=applications&ref=qr',
    '/es/news/': '/es/noticias/',
    '/es/home/': '/es/',
  };
  for (const [from, to] of Object.entries(map)) {
    const r = await get(from);
    check(r.status === 301 && rel(r.location) === to, `legacy ${from} → ${to}`, `${r.status} ${rel(r.location)} (${r.by})`);
  }
  for (const path of ['/product/', '/es/producto/', '/applications/', '/es/aplicaciones/', '/contact/?interest=product', '/es/contacto/?interest=product', '/es/']) {
    const r = await get(path);
    check(r.status === 200, `${path} still answers 200`, `${r.status} ${rel(r.location)}`);
  }
}

/* ── /page/N/ on the static home ─────────────────────────────────────── */
for (const [from, to] of [['/page/7/', '/'], ['/es/page/3/', '/es/'], ['/page/2/?utm_source=x', '/?utm_source=x']]) {
  const r = await get(from);
  check(r.status === 301 && rel(r.location) === to, `${from} → ${to}`, `${r.status} ${rel(r.location)}`);
}

/* ── News pages: indexable only with articles ────────────────────────── */
{
  const pages = { en: '/news/', es: '/es/noticias/' };
  const sitemap = { en: await locs('/wp-sitemap-posts-page-1.xml'), es: await locs('/es/wp-sitemap-posts-page-1.xml') };
  for (const [lang, path] of Object.entries(pages)) {
    const r = await get(path);
    const has = NEWS.includes(lang);
    const noindex = /noindex/.test(robots(r.text));
    check(r.status === 200, `${path} answers 200 (reachable)`, String(r.status));
    check(has ? !noindex : noindex, `${path} ${has ? 'indexable (has news)' : 'noindex (no news)'}`, robots(r.text));
    check(has ? sitemap[lang].includes(path) : !sitemap[lang].includes(path), `${path} ${has ? 'in' : 'not in'} the ${lang} page sitemap`, sitemap[lang].join(' '));
    const hreflangs = [...r.text.matchAll(/<link rel="alternate" href="([^"]*)" hreflang="([^"]*)"/g)].map((m) => `${m[2]}=${rel(m[1])}`);
    const expected = NEWS.length >= 2 && has ? ['en=/news/', 'es=/es/noticias/', 'x-default=/news/'] : [];
    check(JSON.stringify(hreflangs.sort()) === JSON.stringify(expected.sort()), `${path} hreflang only between News pages with articles`, hreflangs.join(' ') || '(none)');
    const ogAlt = [...r.text.matchAll(/og:locale:alternate" content="([^"]*)"/g)].map((m) => m[1]);
    check(has && NEWS.length >= 2 ? ogAlt.length === 1 : ogAlt.length === 0, `${path} og:locale:alternate matches`, ogAlt.join(' ') || '(none)');
  }
  /* the other pages keep their sitemap entries */
  for (const p of ['/', '/product/', '/applications/', '/research/', '/team/', '/contact/']) {
    check(sitemap.en.includes(p), `${p} in the en page sitemap`);
  }
  for (const p of ['/es/', '/es/producto/', '/es/aplicaciones/', '/es/casos-de-estudio/', '/es/equipo/', '/es/contacto/']) {
    check(sitemap.es.includes(p), `${p} in the es page sitemap`);
  }
}

/* ── default category, other categories, date archives ──────────────── */
if (NEWS.length) {
  const cats = { en: await locs('/wp-sitemap-taxonomies-category-1.xml'), es: await locs('/es/wp-sitemap-taxonomies-category-1.xml') };
  for (const [lang, path, other] of [['en', '/category/news/', '/category/research-news/'], ['es', '/es/category/noticias/', '/es/category/investigacion/']]) {
    if (!NEWS.includes(lang)) continue;
    const r = await get(path);
    check(r.status === 200 && /noindex/.test(robots(r.text)), `${path} (default category) noindex, still 200`, `${r.status} ${robots(r.text)}`);
    check(!cats[lang].includes(path), `${path} not in the category sitemap`, cats[lang].join(' '));
    const o = await get(other);
    check(o.status === 200 && !/noindex/.test(robots(o.text)), `${other} indexable`, robots(o.text));
    check(cats[lang].includes(other), `${other} in the category sitemap`);
    const desc = (o.text.match(/<meta name="description" content="([^"]*)"/) || [])[1] || '';
    const home = ((await get(lang === 'en' ? '/' : '/es/')).text.match(/<meta name="description" content="([^"]*)"/) || [])[1] || '';
    check(desc && desc !== home, `${other} description is not the home page's`, desc);
  }
  for (const [path, t] of [['/2026/09/', 'September 2026 — MatSurfer'], ['/es/2026/09/', 'Septiembre de 2026 — MatSurfer']]) {
    const r = await get(path);
    if (r.status === 404) continue; // no article that month in this state
    check(/noindex/.test(robots(r.text)), `${path} (date archive) noindex`, robots(r.text));
    check(title(r.text) === t, `${path} title`, title(r.text));
  }
}

/* ── search titles / headings ───────────────────────────────────────── */
{
  const h1 = (html) => (html.match(/<h1[^>]*id="page-title"[^>]*>([\s\S]*?)<\/h1>/) || [])[1]?.replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim() || '';
  for (const [path, t, h] of [
    ['/?s=', 'Search — MatSurfer', 'Search'],
    ['/es/?s=%20', 'Buscar — MatSurfer', 'Buscar'],
    ['/?s=device', 'Search results for “device” — MatSurfer', 'Search results for “device”'],
    ['/es/?s=dispositivo', 'Resultados de búsqueda para «dispositivo» — MatSurfer', 'Resultados de búsqueda para «dispositivo»'],
  ]) {
    const r = await get(path);
    check(title(r.text) === t, `${path} <title>`, title(r.text));
    check(h1(r.text) === h, `${path} heading`, h1(r.text));
  }
  const nf = await get('/this-page-does-not-exist/');
  check(nf.status === 404 && /noindex/.test(robots(nf.text)), '404 still noindex', robots(nf.text));
}

/* ── browser: landmark names (axe) + featured image alt ─────────────── */
{
  const browser = await launch();
  const page = await (await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' })).newPage();
  const views = ['/news/', '/es/noticias/', '/?s=device', '/es/?s=dispositivo', '/this-page-does-not-exist/'];
  if (NEWS.includes('en')) views.push('/category/research-news/', '/category/news/', '/2026/09/');
  if (NEWS.includes('es')) views.push('/es/category/investigacion/');
  for (const path of views) {
    await page.goto(BASE + path, { waitUntil: 'load' });
    await page.addScriptTag({ path: AXE });
    const v = await page.evaluate(async () => {
      const r = await axe.run(document, { runOnly: ['landmark-unique', 'landmark-no-duplicate-main', 'region', 'image-alt'] });
      return r.violations.map((x) => `${x.id}(${x.nodes.length})`);
    });
    check(v.length === 0, `${path} axe landmark-unique/region/image-alt`, v.join(' ') || 'clean');
  }
  /* alt text: the article's own photo description (_mats_thumb_alt) when set,
     otherwise the image's alt — never the title (blog2 fix) */
  for (const lang of NEWS) {
    const list = lang === 'en' ? '/news/' : '/es/noticias/';
    await page.goto(BASE + list, { waitUntil: 'load' });
    const cards = await page.$$eval('.post-card', (els) =>
      els.filter((e) => e.querySelector('img.post-card__img')).map((e) => ({ alt: e.querySelector('img.post-card__img').getAttribute('alt'), title: e.querySelector('.post-card__link').textContent.trim(), href: e.querySelector('.post-card__link').href }))
    );
    check(cards.length > 0, `${list} has cards with a photo`, String(cards.length));
    for (const c of cards) {
      check(!!c.alt && c.alt !== c.title, `${list} card alt = description or image alt (not the title)`, `alt="${c.alt}"`);
    }
    if (cards[0]) {
      await page.goto(cards[0].href, { waitUntil: 'load' });
      const a = await page.evaluate(() => ({
        alt: document.querySelector('.post-hero__photo')?.getAttribute('alt') || '',
        h1: document.querySelector('.post-hero__title')?.textContent.replace(/\s+/g, ' ').trim() || '',
        og: document.querySelector('meta[property="og:image:alt"]')?.getAttribute('content') || '',
      }));
      check(a.alt === cards[0].alt && a.og === a.alt && a.alt !== a.h1, `${rel(cards[0].href)} photo alt + og:image:alt = card alt (not the title)`, `alt="${a.alt}" og="${a.og}"`);
    }
  }
  await browser.close();
}

console.log(fails ? `\n${fails} FAIL` : '\nall PASS');
process.exitCode = fails ? 1 : 0;
