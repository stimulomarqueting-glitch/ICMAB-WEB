/**
 * QA (fix bucket "blog2"): «Descripción de la foto destacada», end to end, as
 * an Editor in the real block editor. Prints PASS/FAIL lines, exit code 1 on
 * any FAIL.
 *   WP_BASE=http://localhost:8986 node wordpress/tools/qa/fix-blog2-editor.mjs [outDir]
 * Needs blog-setup.php + blog-latest.php on that site; runs
 * fix-blog2-setup.php itself (Editor account, every _mats_thumb_alt removed).
 * Steps, for an EN article and its ES translation sharing one image:
 *  1. before: both use the image's own alt (cards, article, og/twitter);
 *  2. the field sits under the photo of the «Foto destacada» panel, empty,
 *     with the image's alt as placeholder; typed + «Save» in each language;
 *  3. after: each language shows its own text in the article photo, the
 *     cards (News page, «Últimas noticias»), og:image:alt and
 *     twitter:image:alt; the other pair keeps the image's alt; HTML and line
 *     breaks are stripped;
 *  4. the «Últimas noticias» preview in the page editor shows the texts;
 *  5. choosing another photo (media library) empties the field; removing
 *     the photo hides it (nothing saved).
 */
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { launch, login, closeWelcome, BASE } from './wp-login.mjs';

const out = process.argv[2] || '';
if (out) fs.mkdirSync(out, { recursive: true });
const here = path.dirname(fileURLToPath(import.meta.url));
const port = new URL(BASE).port;
const setupOut = execFileSync('php', ['/home/user/wpsite-cli.php', path.join(here, 'fix-blog2-setup.php')], { env: { ...process.env, MATS_WP_PORT: port } }).toString();
const setup = JSON.parse(setupOut.trim().split('\n').pop());
const [pair, other] = setup.pairs;
if (!pair) throw new Error('no EN/ES pair sharing a featured image: run blog-setup.php first');

let fails = 0;
const check = (ok, label, detail = '') => {
  if (!ok) fails++;
  console.log(`${ok ? 'PASS' : 'FAIL'} ${label}${detail ? ' — ' + detail : ''}`);
};
const rel = (url) => url.replace(BASE, '');
const decode = (s) => s.replace(/&quot;/g, '"').replace(/&#039;/g, "'").replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>');
const TEXT = {
  en: 'The glass chamber of MatSurfer on a lab table, beside its manual and a sample substrate',
  es: 'La cámara de vidrio de MatSurfer sobre una mesa, junto a su documentación y un sustrato de muestra',
};

/* what visitors and social networks get, over HTTP */
async function publicAlts(url, slug) {
  const html = await (await fetch(url)).text();
  const photo = (html.match(/<img[^>]*class="[^"]*post-hero__photo[^"]*"[^>]*>/) || [''])[0];
  return {
    photo: decode((photo.match(/ alt="([^"]*)"/) || [])[1] ?? '(none)'),
    og: decode((html.match(/<meta property="og:image:alt" content="([^"]*)"/) || [])[1] ?? '(none)'),
    tw: decode((html.match(/<meta name="twitter:image:alt" content="([^"]*)"/) || [])[1] ?? '(none)'),
  };
}
async function cardAlt(listUrl, articleUrl) {
  const html = await (await fetch(listUrl)).text();
  const alts = [];
  for (const card of html.split('<article class="panel post-card').slice(1)) {
    if (!card.includes(`href="${articleUrl}"`)) continue;
    const img = (card.match(/<img[^>]*post-card__img[^>]*>/) || [''])[0];
    alts.push(decode((img.match(/ alt="([^"]*)"/) || [])[1] ?? '(none)'));
  }
  return alts;
}
const lists = { en: [BASE + '/news/', BASE + '/qa-latest/'], es: [BASE + '/es/noticias/', BASE + '/es/qa-ultimas/'] };

async function expectEverywhere(lang, articleUrl, expected, label) {
  const a = await publicAlts(articleUrl);
  check(a.photo === expected && a.og === expected && a.tw === expected, `${label}: ${rel(articleUrl)} photo alt + og:image:alt + twitter:image:alt`, `photo="${a.photo}" og="${a.og}" tw="${a.tw}"`);
  for (const list of lists[lang]) {
    const alts = await cardAlt(list, articleUrl);
    check(alts.length > 0 && alts.every((x) => x === expected), `${label}: card(s) on ${rel(list)}`, alts.map((x) => `"${x}"`).join(' ') || 'no card');
  }
}

/* 1 ── before */
await expectEverywhere('en', pair.enUrl, pair.shared, 'before (EN)');
await expectEverywhere('es', pair.esUrl, pair.shared, 'before (ES, no Spanish text yet: the image alt, not the title)');

/* 2 ── the editor */
const browser = await launch();
const page = await login(browser, 'editora', 'editora-local-pass');
const errors = [];
page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));
page.on('console', (m) => m.type() === 'error' && /post-thumb-alt|matsThumbAlt|TextareaControl/.test(m.text()) && errors.push('console: ' + m.text()));
page.on('dialog', (d) => d.accept().catch(() => {}));

const field = () => page.locator('.mats-thumb-alt textarea');
async function openEditor(id) {
  await page.goto(`${BASE}/wp-admin/post.php?post=${id}&action=edit`, { waitUntil: 'load' });
  await page.waitForSelector('.editor-styles-wrapper, iframe[name="editor-canvas"]', { timeout: 60000 });
  await closeWelcome(page);
  /* the «Noticia» tab of the sidebar (open it when it is closed) */
  if (!(await page.locator('.editor-sidebar, .interface-complementary-area').count())) {
    await page.getByRole('button', { name: /^(Settings|Ajustes)$/ }).first().click();
  }
  const tab = page.getByRole('tab', { name: /^(Noticia|Post|Entrada)$/ });
  if (await tab.count()) await tab.first().click();
}
async function saveWithButton() {
  await page.locator('.editor-header__settings').getByRole('button', { name: /^(Save|Guardar|Update|Actualizar)$/ }).first().click();
  await page.waitForFunction(() => {
    const ed = wp.data.select('core/editor');
    return !ed.isSavingPost() && !ed.isEditedPostDirty();
  }, null, { timeout: 30000 });
}

for (const [lang, id] of [['en', pair.en], ['es', pair.es]]) {
  await openEditor(id);
  const ok = await field().waitFor({ timeout: 20000 }).then(() => true).catch(() => false);
  check(ok, `${lang} article (${id}): the field is in the «Foto destacada» panel`);
  if (!ok) continue;
  const info = await page.evaluate(() => {
    const box = document.querySelector('.mats-thumb-alt');
    const img = document.querySelector('.editor-post-featured-image img');
    const label = box.querySelector('label')?.textContent || '';
    const help = box.querySelector('.components-base-control__help')?.textContent || '';
    const below = img ? img.getBoundingClientRect().bottom <= box.getBoundingClientRect().top : false;
    return { label, help, below, value: box.querySelector('textarea').value, placeholder: box.querySelector('textarea').placeholder };
  });
  check(info.label === 'Descripción de la foto destacada', `${lang}: Spanish label`, info.label);
  check(info.below, `${lang}: shown right under the photo`);
  check(info.value === '' && info.placeholder === pair.shared, `${lang}: empty, the image's alt as placeholder`, `placeholder="${info.placeholder}"`);
  check(/idioma de esta noticia/.test(info.help) && /biblioteca de medios/.test(info.help), `${lang}: help in plain Spanish`, info.help);
  if (out) await page.locator('.editor-sidebar, .interface-complementary-area').first().screenshot({ path: path.join(out, `editor-field-${lang}-empty.png`) });
  await field().click();
  await field().fill(lang === 'en' ? `<b>${TEXT.en}</b>` : TEXT.es.replace(' sobre ', '\n sobre '));
  if (lang === 'es') {
    /* the Enter key does not split it: one line */
    check(!(await field().inputValue()).includes('\n'), 'es: line breaks become spaces while typing', await field().inputValue());
  }
  await saveWithButton();
  if (out) await page.locator('.editor-sidebar, .interface-complementary-area').first().screenshot({ path: path.join(out, `editor-field-${lang}-filled.png`) });
}

/* reload: the saved values come back */
for (const [lang, id] of [['en', pair.en], ['es', pair.es]]) {
  await openEditor(id);
  await field().waitFor({ timeout: 20000 });
  const v = await field().inputValue();
  const expected = lang === 'en' ? TEXT.en : TEXT.es;
  check(v === expected, `${lang}: saved and shown again after reload (tags stripped)`, `"${v}"`);
}

/* 3 ── after, public site */
await expectEverywhere('en', pair.enUrl, TEXT.en, 'after (EN)');
await expectEverywhere('es', pair.esUrl, TEXT.es, 'after (ES)');
if (other) {
  await expectEverywhere('en', other.enUrl, other.shared, 'other article, no text (EN)');
  await expectEverywhere('es', other.esUrl, other.shared, 'other article, no text (ES)');
}

/* 4 ── «Últimas noticias» preview in the page editor */
for (const [lang, id, articleUrl] of [['en', setup.latest.en, pair.enUrl], ['es', setup.latest.es, pair.esUrl]]) {
  if (!id) continue;
  await page.goto(`${BASE}/wp-admin/post.php?post=${id}&action=edit`, { waitUntil: 'load' });
  await page.waitForSelector('.editor-styles-wrapper, iframe[name="editor-canvas"]', { timeout: 60000 });
  await closeWelcome(page);
  const frame = (await page.$('iframe[name="editor-canvas"]')) ? page.frameLocator('iframe[name="editor-canvas"]') : page;
  const card = frame.locator(`.lposts .post-card:has(a.post-card__link[href="${articleUrl}"]) img.post-card__img`).first();
  const ok = await card.waitFor({ timeout: 30000 }).then(() => true).catch(() => false);
  const alt = ok ? await card.getAttribute('alt') : '(no card)';
  const expected = lang === 'en' ? TEXT.en : TEXT.es;
  check(alt === expected, `${lang}: «Últimas noticias» preview in the editor (page ${id})`, `alt="${alt}"`);
}

/* 5 ── another photo empties the field, no photo hides it (not saved) */
await openEditor(pair.es);
await field().waitFor({ timeout: 20000 });
let replaced = false;
try {
  /* the real way: click the photo → media library → choose another → «Usar como foto destacada» */
  await page.locator('.editor-post-featured-image__toggle, .editor-post-featured-image__preview').first().click();
  const modal = page.locator('.media-modal');
  await modal.waitFor({ timeout: 15000 });
  const libraryTab = modal.getByRole('tab', { name: /Media Library|Biblioteca de medios/ });
  if (await libraryTab.count()) await libraryTab.first().click();
  await modal.locator(`li.attachment[data-id="${setup.other}"]`).click({ timeout: 15000 });
  await modal.locator('.media-button-select, .media-toolbar-primary .button-primary').first().click();
  await page.waitForFunction((want) => wp.data.select('core/editor').getEditedPostAttribute('featured_media') === want, setup.other, { timeout: 15000 });
  replaced = true;
} catch (e) {
  console.log('note: media modal path failed (' + e.message.split('\n')[0] + '), using the editor store');
  await page.evaluate((id) => wp.data.dispatch('core/editor').editPost({ featured_media: id }), setup.other);
}
await page.waitForTimeout(500);
const afterReplace = await page.evaluate((key) => ({ meta: wp.data.select('core/editor').getEditedPostAttribute('meta')[key], value: document.querySelector('.mats-thumb-alt textarea')?.value ?? '(no field)' }), '_mats_thumb_alt');
check(afterReplace.meta === '' && afterReplace.value === '', `another photo${replaced ? ' (media library)' : ''}: the description is emptied`, JSON.stringify(afterReplace));
if (out) await page.locator('.editor-sidebar, .interface-complementary-area').first().screenshot({ path: path.join(out, 'editor-field-replaced.png') });
await page.evaluate(() => wp.data.dispatch('core/editor').editPost({ featured_media: 0 }));
await page.waitForTimeout(300);
check((await page.locator('.mats-thumb-alt').count()) === 0, 'no photo: the field is hidden');
/* leave without saving: the stored text is untouched */
await page.goto(`${BASE}/wp-admin/edit.php`, { waitUntil: 'load' });
const still = await publicAlts(pair.esUrl);
check(still.photo === TEXT.es, 'nothing saved: the ES article keeps its text', `"${still.photo}"`);

check(errors.length === 0, 'no JavaScript errors from the field', errors.join(' | '));
await browser.close();
console.log(fails ? `\n${fails} FAIL` : '\nall PASS');
process.exitCode = fails ? 1 : 0;
