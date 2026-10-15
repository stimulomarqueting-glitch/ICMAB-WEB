/**
 * QA (fix "photo-replace-keeps-old-alt"), as the Editor on Inicio (ES):
 * replacing the product-teaser photo with a library image that has no
 * description must not keep the old photo's description; the side panel's
 * «Descripción de la foto» gets the focus and a visible warning. Same for
 * «Cambiar foto» in the side panel. Nothing is saved.
 *
 * Needs a library image without alt titled NOALT_TITLE (default
 * "foto-sin-descripcion-es").
 *   WP_BASE=http://localhost:8981 [SHOTS=/dir] node wordpress/tools/qa/fix-editorux-alt.mjs
 * OLD_JS=/path/to/old/blocks.js serves that file instead of the theme's one
 * (to reproduce the bug with the previous code).
 */
import fs from 'node:fs';
import { launch, login, closeWelcome, BASE } from './wp-login.mjs';

const shots = process.env.SHOTS || '';
const noAlt = process.env.NOALT_TITLE || 'foto-sin-descripcion-es';
const withAlt = process.env.ALT_TITLE || 'tech-detail-11';
const browser = await launch();
const page = await login(browser, process.env.WP_USER || 'marta', process.env.WP_PASS || 'Editor-pass-2026');
const errors = [];
page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));
if (process.env.OLD_JS) {
  const body = fs.readFileSync(process.env.OLD_JS, 'utf8');
  await page.route('**/assets/js/editor/blocks.js*', (route) => route.fulfill({ status: 200, contentType: 'application/javascript', body }));
}
let fails = 0;
const check = (label, ok, extra = '') => {
  if (!ok) fails++;
  console.log((ok ? 'ok   ' : 'FAIL ') + label + (extra ? ' :: ' + extra : ''));
};

await page.goto(`${BASE}/wp-admin/post.php?post=8&action=edit`);
await page.waitForSelector('iframe[name="editor-canvas"]', { timeout: 60000 });
await closeWelcome(page);
const f = page.frameLocator('iframe[name="editor-canvas"]');
const pt = f.locator('[data-type="matsurfer/product-teaser"]').first();
await pt.locator('[data-mats-image="photo"]').waitFor({ timeout: 60000 });
await page.waitForTimeout(1000);
const photo = () => page.evaluate(() => wp.data.select('core/block-editor').getBlocks().find((b) => b.name === 'matsurfer/product-teaser').attributes.photo);

async function pickInModal(title) {
  await page.locator('.media-modal:visible').first().waitFor({ state: 'visible', timeout: 30000 });
  await page.waitForTimeout(600);
  await page.locator('.media-modal:visible .media-menu-item, .media-modal:visible .media-router button').filter({ hasText: /Media Library|Biblioteca/ }).first().click().catch(() => {});
  await page.waitForTimeout(1200);
  await page.locator(`.media-modal:visible .attachments .attachment[aria-label="${title}"]`).first().click();
  await page.waitForTimeout(500);
  await page.locator('.media-modal:visible .media-button-select').click();
  await page.waitForTimeout(1500);
}
const altField = () =>
  page.evaluate(() => {
    const box = document.querySelector('.block-editor-block-inspector .mats-field-image__alt');
    const ta = box && box.querySelector('textarea');
    return {
      exists: !!box,
      empty: !!box && box.classList.contains('is-empty'),
      focused: !!ta && document.activeElement === ta,
      help: box ? box.innerText.replace(/\s+/g, ' ') : '',
    };
  });

/* 1 ─ click on the photo in the page */
const before = await photo();
console.log('before:', String(before.url).split('/').pop(), '| alt:', before.alt);
await pt.locator('[data-mats-image="photo"]').click();
await pickInModal(noAlt);
const after = await photo();
console.log('after :', String(after.url).split('/').pop(), '| alt:', JSON.stringify(after.alt));
check('canvas: new photo set', after.id && after.id !== before.id);
check('canvas: old description not carried over', after.alt === '', JSON.stringify(after.alt));
const a1 = await altField();
check('canvas: «Descripción de la foto» shown with warning', a1.exists && a1.empty && a1.help.includes('Esta foto no tiene descripción'), a1.help);
check('canvas: «Descripción de la foto» has the focus', a1.focused);
if (shots) await page.screenshot({ path: `${shots}/alt-after-canvas-pick.png` });
await page.keyboard.type('Foto de prueba sin descripción previa');
await page.waitForTimeout(500);
check('typing fills the description', (await photo()).alt === 'Foto de prueba sin descripción previa' && !(await altField()).empty);

/* 2 ─ «Cambiar foto» in the side panel: an image with description, then one without */
await page.locator('.block-editor-block-inspector button', { hasText: 'Cambiar foto' }).first().click();
await pickInModal(withAlt);
const p2 = await photo();
check('panel: image with description takes its own description', p2.alt !== 'Foto de prueba sin descripción previa' && p2.alt !== '', JSON.stringify(p2.alt));
await page.locator('.block-editor-block-inspector button', { hasText: 'Cambiar foto' }).first().click();
await pickInModal(noAlt);
const p3 = await photo();
const a3 = await altField();
check('panel: image without description → empty description', p3.alt === '', JSON.stringify(p3.alt));
check('panel: field focused and flagged', a3.focused && a3.empty);

/* 3 ─ the home hero's photo lives in a closed group («Foto y animación»): it opens */
const hero = f.locator('[data-type="matsurfer/hero-field"]').first();
await page.evaluate(() => wp.data.dispatch('core/block-editor').clearSelectedBlock());
await page.waitForTimeout(400);
await hero.locator('[data-mats-image="photo"]').first().click({ force: true });
await pickInModal(noAlt);
const heroPhoto = await page.evaluate(() => wp.data.select('core/block-editor').getBlocks().find((b) => b.name === 'matsurfer/hero-field').attributes.photo);
const a4 = await altField();
const groupOpen = await page.evaluate(() =>
  [...document.querySelectorAll('.block-editor-block-inspector .components-panel__body')].some((b) => b.classList.contains('is-opened') && b.innerText.includes('Foto y animación'))
);
check('hero: description emptied, its group opened, field focused', heroPhoto.alt === '' && groupOpen && a4.focused && a4.empty, JSON.stringify({ alt: heroPhoto.alt, groupOpen, a4 }));
if (shots) await page.screenshot({ path: `${shots}/alt-hero-group.png` });

console.log(errors.length ? errors.join('\n') : 'no JS errors');
console.log(fails ? 'RESULT FAIL ' + fails : 'RESULT ok');
await browser.close(); // not saved
