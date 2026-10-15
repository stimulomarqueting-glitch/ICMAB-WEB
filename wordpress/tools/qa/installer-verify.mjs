/**
 * QA: verifies what the setup wizard produced on one instance.
 *
 *   WP_BASE=http://localhost:8913 MATS_WP_PORT=8913 node wordpress/tools/qa/installer-verify.mjs [--allow-pending]
 *
 * Checks (exit code 1 when any fails):
 *  - DB (installer-check.php): one page per role and language, EN↔ES linked,
 *    sections in the Astro order and locked, SEO meta, front/blog/privacy
 *    options, Polylang languages/options, WordPress options, categories,
 *    structured content, sample content gone, legal pages as drafts
 *  - HTTP: every published page in EN and ES answers 200 with the right
 *    <html lang>, the language switcher points to the translation, front
 *    pages at / and /es/
 *  - no new PHP warnings/notices/errors in /home/user/wpsite-data/debug-<port>.log
 */
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';

const PORT = process.env.MATS_WP_PORT || '8913';
const BASE = process.env.WP_BASE || `http://localhost:${PORT}`;
const LOG = `/home/user/wpsite-data/debug-${PORT}.log`;
const allowPending = process.argv.includes('--allow-pending');

const problems = [];
const notes = [];
const fail = (msg) => problems.push(msg);
const ok = (cond, msg) => {
  if (!cond) fail(msg);
  return cond;
};

const json = execFileSync('php', ['/home/user/wpsite-cli.php', new URL('./installer-check.php', import.meta.url).pathname], {
  env: { ...process.env, MATS_WP_PORT: PORT },
  encoding: 'utf8',
  maxBuffer: 32 * 1024 * 1024,
});
const state = JSON.parse(json.slice(json.indexOf('{')));
const o = state.options;

/* ── database ─────────────────────────────────────────────────────── */
const ROLES = ['home', 'product', 'applications', 'research', 'team', 'contact', 'news', 'privacy', 'legal'];
const SLUGS = {
  en: { home: 'home', product: 'product', applications: 'applications', research: 'research', team: 'team', contact: 'contact', news: 'news', privacy: 'privacy-policy', legal: 'legal-notice' },
  es: { home: 'inicio', product: 'producto', applications: 'aplicaciones', research: 'casos-de-estudio', team: 'equipo', contact: 'contacto', news: 'noticias', privacy: 'politica-de-privacidad', legal: 'aviso-legal' },
};
ok(state.polylang_active, 'Polylang is not active');
ok(Object.keys(state.duplicates).length === 0, `duplicate pages: ${JSON.stringify(state.duplicates)}`);
const byId = Object.fromEntries(state.pages.map((p) => [p.id, p]));
for (const role of ROLES) {
  for (const lang of ['en', 'es']) {
    const id = state.roles[role]?.[lang];
    if (!ok(id, `missing page ${role}.${lang}`)) continue;
    const p = byId[id];
    ok(p.slug === SLUGS[lang][role], `${role}.${lang}: slug ${p.slug} (expected ${SLUGS[lang][role]})`);
    ok(p.translations?.en === state.roles[role].en && p.translations?.es === state.roles[role].es, `${role}.${lang}: translations not linked ${JSON.stringify(p.translations)}`);
    const draft = role === 'privacy' || role === 'legal';
    ok(p.status === (draft ? 'draft' : 'publish'), `${role}.${lang}: status ${p.status}`);
    if (p.order_ok === false) fail(`${role}.${lang}: sections ${JSON.stringify(p.blocks)} not in the expected order`);
    if ((p.empty || []).some((e) => e.startsWith('UNLOCKED:'))) fail(`${role}.${lang}: unlocked sections ${p.empty}`);
    const empty = (p.empty || []).filter((e) => !e.startsWith('UNLOCKED:'));
    if (empty.length) (allowPending ? notes : problems).push(`${role}.${lang}: empty sections (no seed mapper yet): ${empty.join(', ')}`);
    if (['home', 'product', 'applications', 'research', 'team', 'contact'].includes(role)) {
      ok(p.seo_title && p.seo_desc, `${role}.${lang}: SEO meta missing`);
    }
    if (role === 'news') ok(p.excerpt, `news.${lang}: excerpt missing`);
    if (draft) ok(String(p.content).includes('Pendiente: texto legal facilitado por el ICMAB.'), `${role}.${lang}: legal placeholder missing`);
  }
}
const roles = o.mats_page_roles || {};
for (const role of ROLES) ok(roles[role] === state.roles[role]?.en, `mats_page_roles.${role} = ${roles[role]} (EN page ${state.roles[role]?.en})`);
ok(o.show_on_front === 'page', `show_on_front ${o.show_on_front}`);
ok(Number(o.page_on_front) === state.roles.home?.en, 'page_on_front is not the EN home');
ok(Number(o.page_for_posts) === state.roles.news?.en, 'page_for_posts is not the EN news page');
ok(Number(o.wp_page_for_privacy_policy) === state.roles.privacy?.en, 'privacy page option');
ok(o.permalink_structure === '/%postname%/', `permalinks ${o.permalink_structure}`);
ok(o.timezone_string === 'Europe/Madrid', `timezone ${o.timezone_string}`);
ok(o.date_format === 'j F Y', `date_format ${o.date_format}`);
ok(String(o.start_of_week) === '1', 'start_of_week');
ok(o.default_comment_status === 'closed' && o.default_ping_status === 'closed', 'comments/pings not closed');
ok(o.blogdescription === 'Surfaces for all.', `tagline ${o.blogdescription}`);
const pll = o.polylang || {};
for (const [k, v] of Object.entries({ default_lang: 'en', hide_default: true, force_lang: 1, rewrite: true, browser: false, redirect_lang: true, media_support: false })) {
  ok(pll[k] === v, `polylang.${k} = ${JSON.stringify(pll[k])} (expected ${JSON.stringify(v)})`);
}
const langs = Object.fromEntries(state.languages.map((l) => [l.slug, l]));
ok(langs.en && langs.en.locale === 'en_GB' && langs.en.flag === 'gb' && langs.en.order === 0 && langs.en.default, `language en ${JSON.stringify(langs.en)}`);
ok(langs.es && langs.es.locale === 'es_ES' && langs.es.flag === 'es' && langs.es.order === 1, `language es ${JSON.stringify(langs.es)}`);
const cats = state.categories;
ok(cats.some((c) => c.name === 'News' && c.lang === 'en') && cats.some((c) => c.name === 'Noticias' && c.lang === 'es'), `categories ${JSON.stringify(cats.map((c) => c.name + '/' + c.lang))}`);
for (const [type, counts] of Object.entries(state.cpts)) ok(Number(counts.publish || 0) > 0, `${type}: nothing published`);
for (const [id, s] of Object.entries(state.samples)) if (s && /hello-world|sample-page/.test(s)) fail(`sample content still there: ${id} ${s}`);
notes.push(`attachments: ${state.attachments}, comments: ${state.comments}, forms: ${JSON.stringify(state.forms)}`);
/* admin language: es_ES when its pack is installed, otherwise WPLANG left as it was (WordPress refuses a missing language) */
const wpLangs = state.wp_languages || [];
if (wpLangs.includes('es_ES')) ok(o.WPLANG === 'es_ES' || !['', 'en_US', false].includes(o.WPLANG), `WPLANG ${JSON.stringify(o.WPLANG)} although es_ES is installed`);
else ok(['', 'en_US', false].includes(o.WPLANG), `WPLANG ${JSON.stringify(o.WPLANG)} without the es_ES pack`);
notes.push(`admin language: WPLANG=${JSON.stringify(o.WPLANG)}, installed: ${JSON.stringify(wpLangs)}, reminder notice: ${state.installer_state && state.installer_state.notice}`);

/* ── HTTP ─────────────────────────────────────────────────────────── */
const logBefore = fs.existsSync(LOG) ? fs.statSync(LOG).size : 0;
const expectUrl = (role, lang) => {
  if (role === 'home') return lang === 'en' ? `${BASE}/` : `${BASE}/es/`;
  return byId[state.roles[role][lang]].url;
};
async function get(url) {
  const res = await fetch(url, { redirect: 'manual' });
  return { status: res.status, location: res.headers.get('location'), html: res.status === 200 ? await res.text() : '' };
}
for (const role of ROLES.filter((r) => r !== 'privacy' && r !== 'legal')) {
  for (const lang of ['en', 'es']) {
    if (!state.roles[role]?.[lang]) continue;
    const url = expectUrl(role, lang);
    const r = await get(url);
    if (!ok(r.status === 200, `${url}: HTTP ${r.status}${r.location ? ' → ' + r.location : ''}`)) continue;
    const htmlLang = (r.html.match(/<html[^>]*\blang="([^"]+)"/) || [])[1] || '';
    ok(htmlLang.toLowerCase().startsWith(lang), `${url}: <html lang="${htmlLang}">`);
    const sw = {};
    for (const m of r.html.matchAll(/<ul class="lang"[\s\S]*?<\/ul>/g)) {
      for (const a of m[0].matchAll(/<a href="([^"]+)" hreflang="([a-z]+)"/g)) sw[a[2]] = a[1].replace(/&#038;|&amp;/g, '&');
      break;
    }
    for (const other of ['en', 'es']) {
      ok(sw[other] === expectUrl(role, other), `${url}: switcher ${other} → ${sw[other]} (expected ${expectUrl(role, other)})`);
    }
    ok(!/Fatal error|Warning:|Notice:|Deprecated:/.test(r.html), `${url}: PHP message in the HTML`);
    notes.push(`200 ${url} lang=${htmlLang} switcher=${JSON.stringify(sw)}`);
  }
}
/* legal drafts must not be public */
for (const role of ['privacy', 'legal']) {
  for (const lang of ['en', 'es']) {
    const p = byId[state.roles[role]?.[lang]];
    if (!p) continue;
    const r = await get(`${BASE}/${lang === 'es' ? 'es/' : ''}${p.slug}/`);
    ok(r.status !== 200, `${role}.${lang}: the draft is public (HTTP ${r.status})`);
  }
}
const front = await get(`${BASE}/es/inicio/`);
notes.push(`/es/inicio/ → ${front.status} ${front.location || ''}`);

await new Promise((r) => setTimeout(r, 300));
const logAfter = fs.existsSync(LOG) ? fs.readFileSync(LOG, 'utf8').slice(logBefore) : '';
const phpLines = logAfter.split('\n').filter((l) => /PHP (Warning|Notice|Deprecated|Fatal|Parse)/.test(l));
ok(phpLines.length === 0, `PHP messages in ${LOG} while fetching:\n${phpLines.slice(0, 10).join('\n')}`);
const wholeLog = fs.existsSync(LOG) ? fs.readFileSync(LOG, 'utf8') : '';
const allPhp = wholeLog.split('\n').filter((l) => /PHP (Warning|Notice|Deprecated|Fatal|Parse)/.test(l));
notes.push(`debug log: ${allPhp.length} PHP lines in total`);
for (const l of allPhp.slice(0, 8)) notes.push('  log: ' + l.slice(0, 240));

for (const n of notes) console.log('  ' + n);
if (problems.length) {
  console.log(`\nFAIL — ${problems.length} problem(s):`);
  for (const p of problems) console.log(' ✗ ' + p);
  process.exit(1);
}
console.log('\nPASS — wizard result verified (DB + HTTP + debug log).');
