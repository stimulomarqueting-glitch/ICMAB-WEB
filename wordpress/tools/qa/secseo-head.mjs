/**
 * QA (secseo): fetches the public pages and validates the SEO head
 * (title, description, canonical, Open Graph, Twitter, hreflang) and the
 * JSON-LD graph, plus the security footprint of the head and headers.
 *   WP_BASE=http://localhost:8916 node wordpress/tools/qa/secseo-head.mjs [--verbose]
 */
const BASE = process.env.WP_BASE || 'http://localhost:8916';
const VERBOSE = process.argv.includes('--verbose');
const failures = [];
const check = (cond, label) => {
  if (!cond) failures.push(label);
  if (VERBOSE || !cond) console.log(`${cond ? '  ok  ' : '  FAIL'} ${label}`);
};

const decode = (s) =>
  String(s ?? '')
    .replace(/&#0?38;|&amp;/g, '&')
    .replace(/&quot;/g, '"')
    .replace(/&#0?39;|&#x27;/g, "'")
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&#8211;/g, '–')
    .replace(/&#8212;/g, '—');

function attrs(tag) {
  const out = {};
  for (const m of tag.matchAll(/([a-zA-Z:_-]+)\s*=\s*("([^"]*)"|'([^']*)')/g)) out[m[1].toLowerCase()] = decode(m[3] ?? m[4]);
  return out;
}

function parseHead(html) {
  const head = (html.match(/<head>([\s\S]*?)<\/head>/i) || [, ''])[1];
  const titles = [...head.matchAll(/<title>([\s\S]*?)<\/title>/gi)].map((m) => decode(m[1]));
  const metas = [...head.matchAll(/<meta\b[^>]*>/gi)].map((m) => attrs(m[0]));
  const links = [...head.matchAll(/<link\b[^>]*>/gi)].map((m) => attrs(m[0]));
  const jsonld = [...html.matchAll(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/gi)].map((m) => m[1]);
  return { head, titles, metas, links, jsonld };
}

const metaAll = (h, key) => h.metas.filter((m) => m.name === key || m.property === key).map((m) => m.content);
const meta = (h, key) => metaAll(h, key)[0];

async function get(path) {
  const res = await fetch(BASE + path, { redirect: 'manual' });
  return { status: res.status, headers: res.headers, html: await res.text() };
}

/* url, expected language, kind, extra expectations */
const pages = [
  { path: '/', lang: 'en', kind: 'front', alt: '/es/' },
  { path: '/es/', lang: 'es', kind: 'front', alt: '/' },
  { path: '/product/', lang: 'en', kind: 'page', crumb: 'Product', types: ['Product'], alt: '/es/producto/' },
  { path: '/es/producto/', lang: 'es', kind: 'page', crumb: 'Producto', types: ['Product'], alt: '/product/' },
  { path: '/applications/', lang: 'en', kind: 'page', crumb: 'Applications' },
  { path: '/research/', lang: 'en', kind: 'page', crumb: 'Case Studies', types: ['ScholarlyArticle'] },
  { path: '/es/casos-de-estudio/', lang: 'es', kind: 'page', crumb: 'Casos de estudio', types: ['ScholarlyArticle'] },
  { path: '/team/', lang: 'en', kind: 'page', crumb: 'Team', types: ['Person'] },
  { path: '/es/equipo/', lang: 'es', kind: 'page', crumb: 'Equipo', types: ['Person'] },
  { path: '/contact/', lang: 'en', kind: 'page', crumb: 'Contact' },
  { path: '/news/', lang: 'en', kind: 'blog', crumb: 'News', alt: '/es/noticias/' },
  { path: '/es/noticias/', lang: 'es', kind: 'blog', crumb: 'Noticias', alt: '/news/' },
  { path: '/secseo-test-article/', lang: 'en', kind: 'post', types: ['BlogPosting'], alt: '/es/secseo-articulo-de-prueba/', featured: true },
  { path: '/es/secseo-articulo-de-prueba/', lang: 'es', kind: 'post', types: ['BlogPosting'], alt: '/secseo-test-article/', featured: true, big: true },
  { path: '/category/news/', lang: 'en', kind: 'term' },
  { path: '/no-such-page-secseo/', lang: 'en', kind: '404', status: 404 },
  { path: '/?s=surface', lang: 'en', kind: 'search' },
];

const report = {};
for (const p of pages) {
  const r = await get(p.path);
  const h = parseHead(r.html);
  const L = `[${p.path}]`;
  const og = p.lang === 'es' ? 'es_ES' : 'en_GB';
  const entry = (report[p.path] = { status: r.status, title: h.titles[0] });
  check(r.status === (p.status || 200), `${L} status ${r.status}`);
  check(h.titles.length === 1 && h.titles[0].trim().length > 0, `${L} one non-empty <title> (${h.titles.length})`);
  check(!h.metas.some((m) => m.name === 'generator'), `${L} no generator meta`);
  check(!h.links.some((l) => l.rel === 'https://api.w.org/' || l.rel === 'shortlink' || l.rel === 'EditURI' || (l.type || '').includes('oembed')), `${L} no REST/oEmbed/RSD/shortlink links`);
  check(!/ver=7\.1\.3/.test(r.html), `${L} no core version in asset URLs`);
  check(r.headers.get('x-content-type-options') === 'nosniff' && r.headers.get('x-frame-options') === 'SAMEORIGIN' && /strict-origin-when-cross-origin/.test(r.headers.get('referrer-policy') || '') && /camera=\(\)/.test(r.headers.get('permissions-policy') || ''), `${L} security headers`);
  check(!r.headers.get('x-pingback') && !r.headers.get('x-powered-by') && !/api\.w\.org|shortlink/.test(r.headers.get('link') || ''), `${L} no X-Pingback / X-Powered-By / REST Link header`);

  /* duplicates of single-valued tags */
  const singles = ['description', 'og:type', 'og:title', 'og:description', 'og:url', 'og:image', 'og:locale', 'twitter:card', 'twitter:title'];
  for (const k of singles) check(metaAll(h, k).length <= 1, `${L} at most one ${k}`);
  const canon = h.links.filter((l) => l.rel === 'canonical');
  check(canon.length <= 1, `${L} at most one canonical (${canon.length})`);

  const ld = h.jsonld.map((s) => {
    try {
      return JSON.parse(s);
    } catch (e) {
      return { error: e.message };
    }
  });
  check(ld.length === 1 && !ld[0].error, `${L} exactly one valid JSON-LD block (${ld.length}${ld[0]?.error ? ' ' + ld[0].error : ''})`);
  const graph = ld[0]?.['@graph'] || [];
  const types = graph.map((n) => n['@type']);
  entry.types = types;
  check(ld[0]?.['@context'] === 'https://schema.org', `${L} JSON-LD @context`);
  check(types.includes('WebSite') && types.includes('Organization'), `${L} WebSite + Organization`);
  const org = graph.find((n) => n['@type'] === 'Organization');
  check(org?.parentOrganization?.['@type'] === 'ResearchOrganization' && /ICMAB-CSIC/.test(org?.parentOrganization?.name || ''), `${L} Organization → ICMAB-CSIC ResearchOrganization`);
  for (const t of p.types || []) check(types.includes(t), `${L} JSON-LD has ${t}`);
  check(types.filter((t) => t === 'BlogPosting').length <= 1, `${L} at most one BlogPosting`);
  const ids = graph.filter((n) => n['@id']).map((n) => n['@id']);
  check(new Set(ids).size === ids.length, `${L} unique @ids`);
  const orgId = org?.['@id'];
  for (const n of graph.filter((n) => n['@type'] === 'Person')) check(n.worksFor?.['@id'] === orgId, `${L} Person ${n.name} worksFor the Organization`);

  if (['search', '404'].includes(p.kind)) {
    check(!meta(h, 'og:title'), `${L} no Open Graph on ${p.kind}`);
    check(p.kind !== '404' || /noindex/.test(meta(h, 'robots') || ''), `${L} 404 is noindex`);
    check(p.kind !== 'search' || /noindex/.test(meta(h, 'robots') || ''), `${L} search is noindex`);
    continue;
  }

  const desc = meta(h, 'description');
  entry.description = desc;
  check(!!desc && desc.length > 20, `${L} meta description`);
  check(canon.length === 1, `${L} one canonical`);
  const canonical = canon[0]?.href;
  check(meta(h, 'og:url') === canonical, `${L} og:url = canonical (${meta(h, 'og:url')} / ${canonical})`);
  check(canonical === BASE + p.path.replace(/\?.*$/, ''), `${L} canonical is the page itself (${canonical})`);
  check(meta(h, 'og:type') === (p.kind === 'post' ? 'article' : 'website'), `${L} og:type ${meta(h, 'og:type')}`);
  check(meta(h, 'og:site_name') === 'MatSurfer', `${L} og:site_name MatSurfer`);
  check(meta(h, 'og:title') === h.titles[0], `${L} og:title = <title>`);
  check(meta(h, 'og:description') === desc, `${L} og:description = description`);
  const img = meta(h, 'og:image');
  check(/^https?:\/\//.test(img || ''), `${L} absolute og:image`);
  if (p.featured) check(!/og-image\.png/.test(img) && /uploads/.test(img), `${L} og:image is the featured image (${img})`);
  else check(/og-image\.png$/.test(img || ''), `${L} og:image is the default image (${img})`);
  const [w, hh] = [Number(meta(h, 'og:image:width')), Number(meta(h, 'og:image:height'))];
  entry.image = `${img.replace(BASE, '')} ${w}×${hh}`;
  if (!p.featured || p.big) check(w === 1200 && hh === 630, `${L} og:image 1200×630 (${w}×${hh})`);
  else check(w <= 1200 && Math.abs(w / hh - 1200 / 630) < 0.02, `${L} og:image cropped to the 1200×630 shape (${w}×${hh})`);
  check(!!meta(h, 'og:image:alt'), `${L} og:image:alt`);
  check(meta(h, 'og:locale') === og, `${L} og:locale ${meta(h, 'og:locale')}`);
  if (p.alt) check(metaAll(h, 'og:locale:alternate').includes(og === 'en_GB' ? 'es_ES' : 'en_GB'), `${L} og:locale:alternate`);
  check(meta(h, 'twitter:card') === 'summary_large_image', `${L} twitter:card`);
  check(meta(h, 'twitter:title') === h.titles[0] && meta(h, 'twitter:image') === img, `${L} twitter title/image`);
  if (p.kind === 'post') check(!!meta(h, 'article:published_time'), `${L} article:published_time`);

  /* hreflang (Polylang) + x-default */
  if (p.alt) {
    const hl = Object.fromEntries(h.links.filter((l) => l.rel === 'alternate' && l.hreflang).map((l) => [l.hreflang, l.href]));
    entry.hreflang = hl;
    const en = p.lang === 'en' ? BASE + p.path : BASE + p.alt;
    const es = p.lang === 'es' ? BASE + p.path : BASE + p.alt;
    check(hl.en === en && hl.es === es, `${L} hreflang en/es (${JSON.stringify(hl)})`);
    check(hl['x-default'] === en, `${L} hreflang x-default → English`);
  }

  /* breadcrumb */
  const bc = graph.find((n) => n['@type'] === 'BreadcrumbList');
  if (p.kind === 'front') check(!bc, `${L} no breadcrumb on the home page`);
  else {
    check(!!bc, `${L} BreadcrumbList`);
    const items = bc?.itemListElement || [];
    entry.breadcrumb = items.map((i) => i.name).join(' → ');
    check(items[0]?.name === (p.lang === 'es' ? 'Inicio' : 'Home') && items[0]?.item === BASE + (p.lang === 'es' ? '/es/' : '/'), `${L} breadcrumb starts at home (${entry.breadcrumb})`);
    check(items.at(-1)?.item === canonical, `${L} breadcrumb ends at the page`);
    if (p.crumb) check(items.at(-1)?.name === p.crumb, `${L} breadcrumb label "${p.crumb}" (${items.at(-1)?.name})`);
    if (p.kind === 'post') check(items.length === 3 && items[1].name === (p.lang === 'es' ? 'Noticias' : 'News'), `${L} breadcrumb Home → News → article`);
  }
  if (p.kind === 'post') {
    const art = graph.find((n) => n['@type'] === 'BlogPosting');
    check(art && art.headline && art.datePublished && art.publisher?.['@id'] === orgId && art.mainEntityOfPage?.['@id'] === canonical && art.inLanguage === p.lang, `${L} BlogPosting fields`);
    check(!JSON.stringify(art || {}).includes('admin'), `${L} BlogPosting reveals no user name`);
  }
  if ((p.types || []).includes('Product')) {
    const pr = graph.find((n) => n['@type'] === 'Product');
    check(pr.name === 'MatSurfer' && pr.brand?.['@id'] === orgId && /Laboratory equipment/.test(pr.category) && pr.description === desc, `${L} Product node like product.astro`);
  }
}

/* robots.txt + sitemaps */
{
  const robots = await get('/robots.txt');
  report.robots = robots.html;
  check(/Sitemap: .*wp-sitemap\.xml/.test(robots.html) && (robots.html.match(/Sitemap:/g) || []).length === 1, 'robots.txt lists the sitemap once');
  const idx = await get('/wp-sitemap.xml');
  const subs = [...idx.html.matchAll(/<loc>([^<]+)<\/loc>/g)].map((m) => m[1]);
  report.sitemaps = subs;
  check(subs.length > 0 && subs.every((u) => /wp-sitemap-(posts-(page|post)|taxonomies-category)-\d+\.xml/.test(u)), `sitemap index: pages, posts, categories only (${subs.map((u) => u.replace(BASE, '')).join(', ')})`);
  const urls = [];
  for (const s of subs) {
    const x = await get(s.replace(BASE, ''));
    check(x.status === 200, `sitemap ${s.replace(BASE, '')} answers 200`);
    urls.push(...[...x.html.matchAll(/<loc>([^<]+)<\/loc>/g)].map((m) => m[1]));
  }
  report.sitemapUrls = urls.map((u) => u.replace(BASE, ''));
  check(urls.some((u) => u.endsWith('/es/producto/')) && urls.some((u) => u.endsWith('/product/')), 'sitemaps list EN and ES pages');
  check(!urls.some((u) => /author|mats_|\?page_id=|privacy-policy|legal-notice/.test(u)), 'sitemaps list no authors, private records or drafts');
  check((await get('/wp-sitemap-users-1.xml')).status === 404, 'no users sitemap');
}

if (VERBOSE) console.log(JSON.stringify(report, null, 2));
console.log(failures.length ? `\nFAILED (${failures.length}):\n - ${failures.join('\n - ')}` : '\nALL HEAD CHECKS PASSED');
process.exit(failures.length ? 1 : 0);
