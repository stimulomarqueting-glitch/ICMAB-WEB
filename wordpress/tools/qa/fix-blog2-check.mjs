/**
 * QA (fix bucket "blog2"): a news category without published articles in
 * its language is kept out of Google but still answers. Prints PASS/FAIL
 * lines, exit code 1 on any FAIL.
 *   WP_BASE=http://localhost:8986 node wordpress/tools/qa/fix-blog2-check.mjs
 * Needs blog-setup.php on that site (Research = research-news / investigacion,
 * Product = product-news / producto-noticias). Empties one category at a time
 * with fix-blog2-category.php and puts it back at the end.
 * Per state: 200, robots, category sitemap of each language, hreflang and
 * og:locale:alternate of both versions; the other categories untouched.
 */
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { BASE } from './wp-login.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const port = new URL(BASE).port;
const setState = (rule) =>
  execFileSync('php', ['/home/user/wpsite-cli.php', path.join(here, 'fix-blog2-category.php')], { env: { ...process.env, MATS_WP_PORT: port, MATS_QA_CAT: rule } }).toString().trim();

let fails = 0;
const check = (ok, label, detail = '') => {
  if (!ok) fails++;
  console.log(`${ok ? 'PASS' : 'FAIL'} ${label}${detail ? ' — ' + detail : ''}`);
};
const rel = (url) => url.replace(BASE, '');
const get = async (p) => {
  const res = await fetch(BASE + p, { redirect: 'manual' });
  return { status: res.status, text: await res.text() };
};
const robots = (html) => (html.match(/<meta name='robots' content='([^']*)'/) || [])[1] || '';
const hreflang = (html) => [...html.matchAll(/<link rel="alternate" href="([^"]*)" hreflang="([^"]*)"/g)].map((m) => `${m[2]}=${rel(m[1])}`).sort();
const ogAlt = (html) => [...html.matchAll(/og:locale:alternate" content="([^"]*)"/g)].map((m) => m[1]);
const locs = async (p) => [...(await get(p)).text.matchAll(/<loc>([^<]*)<\/loc>/g)].map((m) => rel(m[1]));

const cats = {
  research: { en: '/category/research-news/', es: '/es/category/investigacion/', slugs: { en: 'research-news', es: 'investigacion' } },
  product: { en: '/category/product-news/', es: '/es/category/producto-noticias/' },
};

/* expected: indexable pair (both versions have articles) */
async function expectPair(name, label) {
  const c = cats[name];
  const sm = { en: await locs('/wp-sitemap-taxonomies-category-1.xml'), es: await locs('/es/wp-sitemap-taxonomies-category-1.xml') };
  for (const lang of ['en', 'es']) {
    const r = await get(c[lang]);
    check(r.status === 200 && !/noindex/.test(robots(r.text)), `${label}: ${c[lang]} 200 + indexable`, `${r.status} ${robots(r.text)}`);
    check(sm[lang].includes(c[lang]), `${label}: ${c[lang]} in the ${lang} category sitemap`);
    const h = hreflang(r.text);
    check(JSON.stringify(h) === JSON.stringify([`en=${c.en}`, `es=${c.es}`, `x-default=${c.en}`].sort()), `${label}: ${c[lang]} hreflang to both versions`, h.join(' ') || '(none)');
    check(ogAlt(r.text).length === 1, `${label}: ${c[lang]} og:locale:alternate`, ogAlt(r.text).join(' ') || '(none)');
  }
}

/* expected: `empty` has no article in its language, `full` has */
async function expectOneEmpty(name, empty, label) {
  const c = cats[name];
  const full = empty === 'en' ? 'es' : 'en';
  const sm = { en: await locs('/wp-sitemap-taxonomies-category-1.xml'), es: await locs('/es/wp-sitemap-taxonomies-category-1.xml') };
  const e = await get(c[empty]);
  check(e.status === 200, `${label}: ${c[empty]} still answers 200 (reachable)`, String(e.status));
  check(/noindex/.test(robots(e.text)) && /follow/.test(robots(e.text)), `${label}: ${c[empty]} noindex,follow`, robots(e.text));
  check(!sm[empty].includes(c[empty]), `${label}: ${c[empty]} not in the ${empty} category sitemap`, sm[empty].join(' '));
  check(hreflang(e.text).length === 0, `${label}: ${c[empty]} no hreflang`, hreflang(e.text).join(' ') || '(none)');
  check(ogAlt(e.text).length === 0, `${label}: ${c[empty]} no og:locale:alternate`, ogAlt(e.text).join(' ') || '(none)');
  const f = await get(c[full]);
  check(f.status === 200 && !/noindex/.test(robots(f.text)), `${label}: ${c[full]} (has articles) indexable`, robots(f.text));
  check(sm[full].includes(c[full]), `${label}: ${c[full]} in the ${full} category sitemap`);
  check(hreflang(f.text).length === 0, `${label}: ${c[full]} offers no hreflang to the empty version`, hreflang(f.text).join(' ') || '(none)');
  check(ogAlt(f.text).length === 0, `${label}: ${c[full]} no og:locale:alternate to it`, ogAlt(f.text).join(' ') || '(none)');
}

await expectPair('research', 'start');
await expectPair('product', 'start');

for (const lang of ['en', 'es']) {
  const slug = cats.research.slugs[lang];
  console.log(`-- ${setState(`${slug}:off`)}`);
  try {
    await expectOneEmpty('research', lang, `${lang} Research empty`);
    await expectPair('product', `${lang} Research empty, Product untouched`);
  } finally {
    console.log(`-- ${setState(`${slug}:on`)}`);
  }
  await expectPair('research', `${lang} Research refilled`);
}

console.log(fails ? `\n${fails} FAIL` : '\nall PASS');
process.exitCode = fails ? 1 : 0;
