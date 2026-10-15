/**
 * QA (group "pagehero"): in-place editing of the uppercase eyebrows
 * (page-hero kicker/kickerNote, section-intro kicker/kickerNote) must keep
 * the stored case. `.eyebrow` is `text-transform: uppercase` and the shared
 * editor (assets/js/editor/blocks.js) reads node.innerText, which applies the
 * transform. Scenarios per field:
 *   A  click the field, click the block's h1/h2 (no typing)      → unchanged
 *   B  click the field, click another block (no typing)          → unchanged
 *   C  click the field, type, press Enter                         → typed text in its own case
 *   D  click the field, type, press Escape                        → unchanged (restore)
 *   E  keyboard focus (no pointer over the block), press Escape   → unchanged
 *   F  click the field, click the editor's top bar (outside the canvas)   → unchanged
 *   G  click the field, click the empty canvas below the last section     → unchanged
 * Nothing is saved: the editor is reloaded between posts.
 *   node wordpress/tools/qa/pagehero-eyebrow-case.mjs <outDir> [--patched-js] postId...
 * --patched-js serves blocks.js with innerText → textContent (the proposed
 * shared fix) to check it on its own.
 */
import { launch, login, closeWelcome, BASE } from './wp-login.mjs';
import { readFileSync } from 'node:fs';

const args = process.argv.slice(2);
const out = args.shift() || '/tmp/pagehero';
const patched = args.includes('--patched-js');
const ids = args.filter((a) => /^\d+$/.test(a));

const browser = await launch();
const page = await login(browser);
const errors = [];
page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));
page.on('dialog', (d) => d.accept().catch(() => {}));

if (patched) {
  const src = readFileSync(new URL('../../matsurfer/assets/js/editor/blocks.js', import.meta.url), 'utf8').replace(/\.innerText\b/g, '.textContent');
  await page.context().route(/\/assets\/js\/editor\/blocks\.js/, (route) => route.fulfill({ status: 200, contentType: 'application/javascript', body: src }));
  console.log('serving blocks.js with innerText → textContent');
}

const fields = [
  { root: '.phero', block: 'matsurfer/page-hero', attr: 'kicker', heading: 'h1' },
  { root: '.phero', block: 'matsurfer/page-hero', attr: 'kickerNote', heading: 'h1' },
  { root: '.sec-intro', block: 'matsurfer/section-intro', attr: 'kicker', heading: 'h2' },
  { root: '.sec-intro', block: 'matsurfer/section-intro', attr: 'kickerNote', heading: 'h2' },
];

let ok = true;
for (const id of ids) {
  await page.goto(`${BASE}/wp-admin/post.php?post=${id}&action=edit`);
  await page.waitForSelector('iframe[name="editor-canvas"]', { timeout: 60000 });
  await closeWelcome(page);
  const frame = page.frameLocator('iframe[name="editor-canvas"]');
  await frame.locator('.mats-preview .phero').first().waitFor({ timeout: 30000 });
  await page.waitForTimeout(1200);

  const attrOf = (block, attr) =>
    page.evaluate(({ block, attr }) => {
      const b = wp.data.select('core/block-editor').getBlocks().find((x) => x.name === block);
      return b ? b.attributes[attr] : undefined;
    }, { block, attr });

  /* give every eyebrow field a mixed-case value (fields this page does not use get one too) */
  for (const f of fields) {
    const has = await page.evaluate((block) => !!wp.data.select('core/block-editor').getBlocks().find((x) => x.name === block), f.block);
    if (!has) continue;
    const cur = await attrOf(f.block, f.attr);
    if (!cur) {
      await page.evaluate(({ block, attr }) => {
        const be = wp.data.select('core/block-editor');
        const b = be.getBlocks().find((x) => x.name === block);
        wp.data.dispatch('core/block-editor').updateBlockAttributes(b.clientId, { [attr]: 'Mixed case ' + attr });
        if (attr === 'kickerNote' && !b.attributes.kicker) wp.data.dispatch('core/block-editor').updateBlockAttributes(b.clientId, { kicker: 'Mixed case kicker' });
      }, f);
      await page.waitForTimeout(1500);
    }
  }
  await frame.locator('[data-mats-edit="kickerNote"]').first().waitFor({ timeout: 15000 }).catch(() => {});

  console.log(`post ${id}${patched ? ' (patched blocks.js)' : ''}`);
  for (const f of fields) {
    const sel = `${f.root} [data-mats-edit="${f.attr}"]`;
    const node = frame.locator(sel).first();
    if (!(await node.count())) continue;
    const before = await attrOf(f.block, f.attr);
    const results = [];
    const check = async (label, expected) => {
      await page.waitForTimeout(400);
      const v = await attrOf(f.block, f.attr);
      const pass = v === expected;
      if (!pass) ok = false;
      results.push(`${label}:${pass ? 'OK' : 'FAIL ' + JSON.stringify(v)}`);
      /* put the original back for the next scenario */
      if (v !== before) {
        await page.evaluate(({ block, attr, before }) => {
          const b = wp.data.select('core/block-editor').getBlocks().find((x) => x.name === block);
          wp.data.dispatch('core/block-editor').updateBlockAttributes(b.clientId, { [attr]: before });
        }, { ...f, before });
        await page.waitForTimeout(1500);
      }
    };

    /* A: focus + click the heading of the same block */
    await node.click();
    await frame.locator(`${f.root} ${f.heading}`).first().click();
    await check('A', before);

    /* B: focus + click another block */
    await node.click();
    const other = frame.locator('.mats-preview .pcta').first();
    await other.click({ position: { x: 20, y: 20 } });
    await check('B', before);

    /* C: type + Enter */
    await node.click();
    await page.keyboard.press('Control+End');
    await page.keyboard.type(' Typed');
    await page.keyboard.press('Enter');
    await page.waitForTimeout(1200);
    await check('C', before + ' Typed');

    /* D: type + Escape */
    await node.click();
    await page.keyboard.press('Control+End');
    await page.keyboard.type(' Xx');
    await page.keyboard.press('Escape');
    await check('D', before);

    /* E: keyboard focus with the pointer elsewhere and the block not selected */
    await page.evaluate(() => wp.data.dispatch('core/block-editor').clearSelectedBlock());
    await page.mouse.move(5, 5);
    await page.waitForTimeout(300);
    await node.evaluate((el) => el.focus());
    await page.keyboard.press('Escape');
    await check('E', before);

    /* F: focus + click outside the canvas (editor top bar) */
    await node.click();
    await page.locator('.editor-header, .edit-post-header').first().click({ position: { x: 400, y: 30 } });
    await check('F', before);

    /* G: focus + click the empty canvas below the last section */
    await node.click();
    const box = await page.locator('iframe[name="editor-canvas"]').boundingBox();
    await page.mouse.click(box.x + box.width / 2, box.y + box.height - 8);
    const selAfterG = await page.evaluate(() => { const c = wp.data.select('core/block-editor').getSelectedBlock(); return c ? c.name : 'none'; });
    await check('G', before);
    results[results.length - 1] += `(selected after click: ${selAfterG})`;

    await node.evaluate((el) => el.blur());
    await page.evaluate(() => wp.data.dispatch('core/block-editor').clearSelectedBlock());
    await page.mouse.move(5, 5);
    await page.waitForTimeout(1800); /* editor.css switches back to capitals 1.5s after the edit */
    const shown = await node.evaluate((el) => ({ text: el.textContent, transform: getComputedStyle(el).textTransform }));
    console.log(`  ${f.block} ${f.attr} ${JSON.stringify(before)} → ${results.join(' ')}  (idle preview: ${shown.transform})`);
  }
  await page.screenshot({ path: `${out}/eyebrow-case-${id}${patched ? '-patched' : ''}.png` });
}
console.log(errors.length ? 'JS errors:\n' + errors.join('\n') : 'no JS errors');
console.log(ok && !errors.length ? 'RESULT: PASS' : 'RESULT: FAIL');
await browser.close();
