/**
 * QA (fix "empty-required-text-removes-whole-section"), as the Editor:
 *   A. every page opens with saving unlocked and no required-text warning;
 *   B. Producto (ES): emptying the H1 in place locks saving (button disabled,
 *      Ctrl+S does nothing) and the section, the side panel and the top
 *      notice say «Título de la página (H1)» is needed; typing it back in the
 *      side panel unlocks;
 *   C. FAQ: a new empty question locks saving until it gets its text;
 *   D. «Banda oscura destacada» (title OR text): emptying both locks;
 *   E. a normal text edit still saves.
 *
 *   WP_BASE=http://localhost:8981 [SHOTS=/dir] node wordpress/tools/qa/fix-editorux-required.mjs
 * Changes page 11 (step E): re-clone the site afterwards if needed.
 * OLD_JS=/path/to/old/blocks.js serves that file instead of the theme's one
 * (to reproduce the bug with the previous code).
 */
import fs from 'node:fs';
import { launch, login, closeWelcome, BASE } from './wp-login.mjs';

const shots = process.env.SHOTS || '';
const user = process.env.WP_USER || 'marta';
const pass = process.env.WP_PASS || 'Editor-pass-2026';
const pages = (process.env.PAGES || '7 8 10 11 12 13 14 15 16 17 18 19 20 21').split(' ');

const browser = await launch();
const page = await login(browser, user, pass);
const errors = [];
page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));
page.on('dialog', (d) => d.accept());
if (process.env.OLD_JS) {
  const body = fs.readFileSync(process.env.OLD_JS, 'utf8');
  await page.route('**/assets/js/editor/blocks.js*', (route) => route.fulfill({ status: 200, contentType: 'application/javascript', body }));
}
let fails = 0;
const check = (label, ok, extra = '') => {
  if (!ok) fails++;
  console.log((ok ? 'ok   ' : 'FAIL ') + label + (extra ? ' :: ' + extra : ''));
};
const canvas = () => page.frameLocator('iframe[name="editor-canvas"]');

async function open(id) {
  await page.goto(`${BASE}/wp-admin/post.php?post=${id}&action=edit`);
  await page.waitForSelector('iframe[name="editor-canvas"]', { timeout: 60000 });
  await closeWelcome(page);
  await page.waitForFunction(() => window.wp && wp.data.select('core/editor').getCurrentPostId(), null, { timeout: 60000 });
  await page.waitForTimeout(1500);
}
const state = () =>
  page.evaluate(() => ({
    locked: wp.data.select('core/editor').isPostSavingLocked(),
    notices: wp.data.select('core/notices').getNotices().filter((n) => String(n.id).startsWith('mats-required-')).map((n) => n.content),
    panel: (document.querySelector('.mats-required-panel') || {}).innerText || '',
    saveDisabled: (() => {
      const b = document.querySelector('.editor-post-publish-button, .editor-post-publish-button__button');
      return b ? b.getAttribute('aria-disabled') === 'true' || b.disabled : null;
    })(),
  }));
const canvasNotice = async () => {
  const n = canvas().locator('.mats-required');
  return (await n.count()) ? (await n.first().innerText()).replace(/\s+/g, ' ') : '';
};
const blockId = (name, nth = 0) =>
  page.evaluate(([n, k]) => wp.data.select('core/block-editor').getBlocks().filter((b) => b.name === n)[k].clientId, [name, nth]);

/* A ─ every page loads unlocked */
for (const id of pages) {
  await open(id);
  const s = await state();
  const cn = await canvas().locator('.mats-required').count();
  check(`A page ${id} opens unlocked`, !s.locked && !s.notices.length && !cn, JSON.stringify(s.notices));
}

/* B ─ empty H1 in place */
await open(11);
const h1 = canvas().locator('.mats-preview').first().locator('[data-mats-edit="title"]');
const original = (await h1.textContent()).replace(/\s+/g, ' ').trim();
await h1.click();
await page.keyboard.press('Control+A');
await page.keyboard.press('Delete');
await page.keyboard.press('Enter');
await page.waitForTimeout(1500);
let s = await state();
let cn = await canvasNotice();
check('B saving locked after emptying the H1', s.locked === true);
check('B save button disabled', s.saveDisabled === true, String(s.saveDisabled));
check('B canvas warning names the field', cn.includes('Esta sección necesita «Título de la página (H1)» para mostrarse en la web'), cn);
check('B top notice', s.notices.length === 1 && s.notices[0].includes('«Cabecera de página»'), JSON.stringify(s.notices));
await page.evaluate((c) => wp.data.dispatch('core/block-editor').selectBlock(c), await blockId('matsurfer/page-hero'));
await page.waitForTimeout(600);
s = await state();
check('B side panel warning', s.panel.includes('Esta sección necesita «Título de la página (H1)» para mostrarse en la web'), s.panel);
const field = page.locator('.mats-field--text.is-missing').filter({ hasText: 'Título de la página (H1)' });
check('B side panel field marked', (await field.count()) === 1);
if (shots) await page.screenshot({ path: `${shots}/required-empty-h1.png` });
await page.keyboard.press('Control+s');
await page.waitForTimeout(2000);
const afterCtrlS = await page.evaluate(() => ({
  dirty: wp.data.select('core/editor').isEditedPostDirty(),
  saving: wp.data.select('core/editor').isSavingPost(),
  saved: wp.data.select('core/editor').getCurrentPostAttribute('content'),
}));
check('B Ctrl+S does not save', afterCtrlS.dirty && !afterCtrlS.saving && afterCtrlS.saved.includes(original.slice(0, 20)));
await field.locator('input').fill(original);
await page.waitForTimeout(800);
s = await state();
check('B typing the title back unlocks', !s.locked && !s.notices.length && !s.panel, JSON.stringify(s));
check('B canvas warning gone', (await canvasNotice()) === '');

/* C ─ FAQ: a new question without text */
await page.evaluate((c) => wp.data.dispatch('core/block-editor').selectBlock(c), await blockId('matsurfer/faq'));
await page.waitForTimeout(600);
const faqPanel = page.locator('.components-panel__body').filter({ has: page.locator('button', { hasText: 'Preguntas' }) });
if (!(await faqPanel.first().evaluate((n) => n.classList.contains('is-opened')))) await faqPanel.first().locator('.components-panel__body-toggle').click();
const before = await page.evaluate(() => wp.data.select('core/block-editor').getSelectedBlock().attributes.items.length);
await page.locator('.mats-repeater__add', { hasText: 'Añadir pregunta' }).click();
await page.waitForTimeout(800);
s = await state();
check('C new empty question locks saving', s.locked && s.panel.includes(`Pregunta ${before + 1} necesita «Pregunta» para mostrarse en la web`), s.panel);
check('C item marked in red', (await page.locator('.mats-repeater__item.has-missing').count()) === 1);
if (shots) await page.screenshot({ path: `${shots}/required-faq-new.png` });
await page.locator('.mats-repeater__item.is-open .mats-field--text input').first().fill('¿Pregunta de prueba?');
await page.waitForTimeout(800);
s = await state();
check('C filling the question unlocks', !s.locked && !s.panel);
await page.locator('.mats-repeater__item.is-open button[aria-label="Quitar"]').click();
await page.waitForTimeout(600);
const after = await page.evaluate(() => wp.data.select('core/block-editor').getSelectedBlock().attributes.items.length);
check('C question removed again, still unlocked', after === before && !(await state()).locked);

/* E ─ a normal edit still saves (section intro title) */
const intro = canvas().locator('.mats-preview').nth(1).locator('[data-mats-edit="title"]');
await intro.click();
await page.keyboard.press('End');
await page.keyboard.type(' QA');
await page.keyboard.press('Enter');
await page.waitForTimeout(1200);
const metaBoxes = [];
page.on('response', (res) => {
  if (res.url().includes('meta-box-loader')) metaBoxes.push(res.status());
});
await page.keyboard.press('Control+s');
await page.waitForFunction(() => !wp.data.select('core/editor').isSavingPost() && !wp.data.select('core/editor').isEditedPostDirty(), null, { timeout: 30000 }).catch(() => {});
await page.waitForTimeout(1500);
check('E meta boxes (Idiomas, Google…) saved with the page', metaBoxes.length > 0 && metaBoxes.every((st) => st < 400), JSON.stringify(metaBoxes));
const saved = await page.evaluate(() => wp.data.select('core/editor').getCurrentPostAttribute('content'));
check('E text edit saved', saved.includes(' QA') && !saved.includes('¿Pregunta de prueba?'));

/* D ─ group: title OR text (Aplicaciones · banda oscura) */
await open(13);
const broader = await blockId('matsurfer/broader');
const keep = await page.evaluate((c) => wp.data.select('core/block-editor').getBlock(c).attributes, broader);
await page.evaluate((c) => wp.data.dispatch('core/block-editor').updateBlockAttributes(c, { title: '' }), broader);
await page.waitForTimeout(600);
check('D title only empty: not locked', !(await state()).locked);
await page.evaluate((c) => wp.data.dispatch('core/block-editor').updateBlockAttributes(c, { text: '' }), broader);
await page.evaluate((c) => wp.data.dispatch('core/block-editor').selectBlock(c), broader);
await page.waitForTimeout(800);
s = await state();
check('D title and text empty: locked', s.locked && s.panel.includes('Esta sección necesita «Título» o «Texto» para mostrarse en la web'), s.panel);
await page.evaluate(([c, a]) => wp.data.dispatch('core/block-editor').updateBlockAttributes(c, { title: a.title, text: a.text }), [broader, keep]);
await page.waitForTimeout(600);
check('D restored: unlocked', !(await state()).locked);

console.log(errors.length ? errors.join('\n') : 'no JS errors');
console.log(fails ? 'RESULT FAIL ' + fails : 'RESULT ok');
await browser.close();
