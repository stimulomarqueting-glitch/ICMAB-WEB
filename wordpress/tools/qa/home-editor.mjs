/**
 * QA (group "home"): opens a home test page in the block editor and checks
 * the value-flow, product-teaser, apps-teaser and explore previews: each
 * renders (no error notice), no JS errors, in-place editable texts exist,
 * editing one text per section updates its attribute, the product photo is
 * click-to-replace, the isotype preview shows its drawn state. Nothing is
 * saved. Screenshots of each preview go to <outDir>.
 *   WP_BASE=http://localhost:8903 node wordpress/tools/qa/home-editor.mjs <postId> <outDir> [alt]
 */
import { launch, login, closeWelcome, BASE } from './wp-login.mjs';
import fs from 'node:fs';

const postId = process.argv[2] || '35';
const out = process.argv[3] || '/tmp/home-editor';
fs.mkdirSync(out, { recursive: true });
const browser = await launch();
const page = await login(browser, 'admin', 'admin-local-pass', { width: 1440, height: 1000 });
const errors = [];
page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));
page.on('console', (m) => { if (m.type() === 'error') errors.push('console: ' + m.text()); });
page.on('requestfailed', (r) => errors.push('requestfailed: ' + r.url().slice(0, 120)));
await page.goto(`${BASE}/wp-admin/post.php?post=${postId}&action=edit`);
await page.waitForSelector('.editor-styles-wrapper, iframe[name="editor-canvas"]', { timeout: 60000 });
await closeWelcome(page);
const hasIframe = !!(await page.$('iframe[name="editor-canvas"]'));
const frame = hasIframe ? page.frameLocator('iframe[name="editor-canvas"]') : page;
console.log('iframe canvas:', hasIframe);

/* set 'alt' (3rd argument) edits a second set of texts: the tag next to
   the isotype, button labels and an uppercase kicker */
const alt = process.argv[4] === 'alt';
const sections = alt ? {
  'value-flow': { sel: 'section.value-flow', edit: 'items.1.tag', check: (a) => a.items[1].tag },
  'product-teaser': { sel: 'section.product-teaser', edit: 'cta.label', check: (a) => a.cta.label },
  'apps-teaser': { sel: 'section.apps-teaser', edit: 'kicker', check: (a) => a.kicker },
  explore: { sel: 'section.explore', edit: 'teamCta.label', check: (a) => a.teamCta.label },
} : {
  'value-flow': { sel: 'section.value-flow', edit: 'items.0.tag', check: (a) => a.items[0].tag },
  'product-teaser': { sel: 'section.product-teaser', edit: 'title', check: (a) => a.title },
  'apps-teaser': { sel: 'section.apps-teaser', edit: 'isotypeCaption', check: (a) => a.isotypeCaption },
  explore: { sel: 'section.explore', edit: 'researchTitle', check: (a) => a.researchTitle },
};
const attrsOf = (name) => page.evaluate((name) => {
  const b = wp.data.select('core/block-editor').getBlocks().find((x) => x.name === 'matsurfer/' + name);
  return b ? b.attributes : null;
}, name);

for (const [name, s] of Object.entries(sections)) {
  const sec = frame.locator(`.mats-preview ${s.sel}`).first();
  await sec.waitFor({ timeout: 30000 });
  await sec.scrollIntoViewIfNeeded();
  await page.mouse.move(5, 500);
  await page.waitForTimeout(500);
  const preview = frame.locator('.mats-preview', { has: frame.locator(s.sel) }).first();
  const text = await preview.innerText();
  const errorNotice = /Error loading block|No se ha podido cargar/i.test(text) || (await preview.locator('.components-notice.is-error').count()) > 0;
  const paths = await preview.locator('[data-mats-edit]').evaluateAll((ns) => ns.map((n) => n.getAttribute('data-mats-edit') + (n.getAttribute('contenteditable') === 'true' ? '' : '(not editable)')));
  const imgs = await preview.locator('[data-mats-image]').evaluateAll((ns) => ns.map((n) => n.tagName + ':' + n.getAttribute('data-mats-image')));
  const notes = await preview.locator('.mats-note').allInnerTexts();
  await preview.screenshot({ path: `${out}/editor-${name}.png` });
  console.log(`\n[${name}] error notice: ${errorNotice}`);
  console.log(`  editable: ${paths.join(', ')}`);
  if (imgs.length) console.log(`  images: ${imgs.join(', ')}`);
  if (notes.length) console.log(`  notes: ${notes.join(' | ')}`);
  if (name === 'apps-teaser') {
    const iso = await preview.locator('.isotype').evaluate((el) => ({
      cls: el.className,
      dash: getComputedStyle(el.querySelector('.iso__capsule')).strokeDashoffset,
      labelOpacity: getComputedStyle(el.querySelector('.iso__label')).opacity,
      opacity: getComputedStyle(el).opacity,
    }));
    console.log('  isotype:', JSON.stringify(iso));
    console.log('  chips:', (await preview.locator('.apps__chip').allInnerTexts()).map((t) => t.replace(/\s+/g, ' ').trim()).join(' | '));
    console.log('  brand mark in value flow:', await frame.locator('.mats-preview .value__tag--brand img').count());
  }

  /* edit one text in place */
  const before = s.check(await attrsOf(name));
  const node = preview.locator(`[data-mats-edit="${s.edit}"]`).first();
  await node.hover();
  await node.click();
  await page.keyboard.press('Control+End');
  await page.keyboard.type(' (edited)');
  await page.keyboard.press('Enter');
  await page.waitForTimeout(1500);
  const after = s.check(await attrsOf(name));
  console.log(`  in-place edit ${s.edit}: ${JSON.stringify(before)} → ${JSON.stringify(after)} ${after === before + ' (edited)' ? 'OK' : 'FAIL'}`);
  /* the preview re-renders with the new value */
  await page.waitForTimeout(800);
  const shown = await preview.locator(`[data-mats-edit="${s.edit}"]`).first().innerText();
  console.log(`  preview shows: ${JSON.stringify(shown)}`);
}

/* side panel: group titles of each section, and the fields inside each
   group (every group opened in turn) */
for (const name of Object.keys(sections)) {
  await page.evaluate((name) => {
    const b = wp.data.select('core/block-editor').getBlocks().find((x) => x.name === 'matsurfer/' + name);
    wp.data.dispatch('core/block-editor').selectBlock(b.clientId);
    wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/block');
  }, name);
  await page.waitForTimeout(800);
  const bodies = page.locator('.block-editor-block-inspector .components-panel__body');
  const groups = [];
  for (let i = 0; i < (await bodies.count()); i++) {
    const body = bodies.nth(i);
    const title = (await body.locator('.components-panel__body-title').innerText()).trim();
    if (title === 'Advanced' || title === 'Avanzado') { groups.push(title); continue; }
    if (!(await body.evaluate((el) => el.classList.contains('is-opened')))) {
      await body.locator('.components-panel__body-toggle').click();
      await page.waitForTimeout(250);
    }
    const labels = await body.locator('.components-base-control__label, .components-input-control__label, label, .mats-notice p, .mats-list__title, legend').evaluateAll((ns) =>
      [...new Set(ns.map((n) => n.textContent.replace(/\s+/g, ' ').trim()).filter(Boolean))].map((t) => (t.length > 40 ? t.slice(0, 40) + '…' : t)));
    const inputs = await body.locator('input[type="text"], input:not([type]), textarea').evaluateAll((ns) => ns.map((n) => n.value));
    groups.push(`${title} [${labels.join(' / ')}] ${inputs.length} text inputs${inputs.length ? ': ' + inputs.map((v) => JSON.stringify(v.length > 24 ? v.slice(0, 24) + '…' : v)).join(', ') : ''}`);
  }
  console.log(`\nside panel ${name}:\n  ${groups.join('\n  ')}`);
}

/* the product photo opens the media library */
const img = frame.locator('.mats-preview section.product-teaser img[data-mats-image="photo"]').first();
await img.scrollIntoViewIfNeeded();
await img.click({ force: true });
await page.waitForTimeout(1200);
console.log('\nmedia frame open on photo click:', await page.locator('.media-modal').first().isVisible().catch(() => false));
await page.keyboard.press('Escape');
console.log('unsaved changes (expected true, not saved):', await page.evaluate(() => wp.data.select('core/editor').isEditedPostDirty()));
console.log(errors.length ? errors.join('\n') : 'no JS errors');
await browser.close();
