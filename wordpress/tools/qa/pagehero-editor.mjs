/**
 * QA (group "pagehero"): opens the test pages in the block editor and checks
 * the page-hero / section-intro previews: rendered (no error notice), no JS
 * errors, in-place editable elements present, editing one updates the
 * attribute, the photo is click-to-replace. Then checks the public pages
 * carry no data-mats-edit attributes.
 *   node wordpress/tools/qa/pagehero-editor.mjs <outDir> <productId> <applicationsId> [esProductId]
 */
import { launch, login, closeWelcome, BASE } from './wp-login.mjs';

const out = process.argv[2] || '/tmp/pagehero';
const ids = process.argv.slice(3).filter(Boolean);
const browser = await launch();
const page = await login(browser);
const errors = [];
page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));
page.on('dialog', (d) => d.accept().catch(() => {}));
page.on('console', (m) => { if (m.type() === 'error' && !/ERR_TUNNEL_CONNECTION_FAILED/.test(m.text())) errors.push('console: ' + m.text()); });
/* offline sandbox: remote requests (wordpress.org, gravatar…) fail; log them apart */
page.on('requestfailed', (r) => { if (!r.url().startsWith(BASE)) console.log('  (offline) request failed:', r.url().slice(0, 90)); else errors.push('local request failed: ' + r.url()); });

let ok = true;
for (const id of ids) {
  await page.goto(`${BASE}/wp-admin/post.php?post=${id}&action=edit`);
  await page.waitForSelector('.editor-styles-wrapper, iframe[name="editor-canvas"]', { timeout: 60000 });
  await closeWelcome(page);
  const hasIframe = !!(await page.$('iframe[name="editor-canvas"]'));
  const frame = hasIframe ? page.frameLocator('iframe[name="editor-canvas"]') : page;
  await frame.locator('.mats-preview .phero').first().waitFor({ timeout: 30000 });
  await page.waitForTimeout(1500);

  const previews = frame.locator('.mats-preview');
  const n = await previews.count();
  console.log(`post ${id}: iframe=${hasIframe} previews=${n}`);
  for (let i = 0; i < n; i++) {
    const p = previews.nth(i);
    const badge = await p.locator('.mats-preview__badge').innerText().catch(() => '?');
    const notice = await p.locator('.components-notice, .mats-empty').count();
    const errText = (await p.innerText()).includes('Error loading block');
    const edits = await p.locator('[data-mats-edit]').evaluateAll((els) => els.map((e) => `${e.getAttribute('data-mats-edit')}${e.isContentEditable ? '' : '(not editable)'}`));
    const imgs = await p.locator('[data-mats-image]').evaluateAll((els) => els.map((e) => e.getAttribute('data-mats-image')));
    console.log(`  [${badge}] notices=${notice} errorText=${errText} edit=[${edits.join(', ')}] images=[${imgs.join(', ')}]`);
    if (notice || errText) ok = false;
  }

  /* the photo opens the media library on click */
  const img = frame.locator('.phero [data-mats-image="photo"]').first();
  if (await img.count()) {
    await img.click();
    const modal = page.locator('.media-modal').first();
    const opened = await modal.waitFor({ state: 'visible', timeout: 10000 }).then(() => true).catch(() => false);
    console.log(`  photo click opens media library: ${opened ? 'OK' : 'FAIL'}`);
    if (!opened) ok = false;
    await page.keyboard.press('Escape');
    await page.waitForTimeout(500);
  }

  /* edit the hero title and (if present) the intro lead in place */
  const targets = [
    { sel: '.phero [data-mats-edit="title"]', block: 'matsurfer/page-hero', attr: 'title' },
    { sel: '.phero [data-mats-edit="cta.label"]', block: 'matsurfer/page-hero', attr: 'cta.label' },
    { sel: '.phero [data-mats-edit="kicker"]', block: 'matsurfer/page-hero', attr: 'kicker' },
    { sel: '.phero [data-mats-edit="kickerNote"]', block: 'matsurfer/page-hero', attr: 'kickerNote' },
    { sel: '.sec-intro [data-mats-edit="kicker"]', block: 'matsurfer/section-intro', attr: 'kicker' },
    { sel: '.sec-intro [data-mats-edit="lead"]', block: 'matsurfer/section-intro', attr: 'lead' },
    { sel: '.sec-intro [data-mats-edit="title"]', block: 'matsurfer/section-intro', attr: 'title' },
  ];
  for (const t of targets) {
    const node = frame.locator(t.sel).first();
    if (!(await node.count())) continue;
    await node.click();
    await page.keyboard.press('Control+End');
    await page.keyboard.type(' (edited)');
    await page.keyboard.press('Enter');
    await page.waitForTimeout(1200);
    const value = await page.evaluate(({ block, attr }) => {
      const b = wp.data.select('core/block-editor').getBlocks().find((x) => x.name === block);
      return attr.split('.').reduce((o, k) => (o == null ? o : o[k]), b && b.attributes);
    }, t);
    /* eyebrows are text-transform: uppercase; the blocks' editor.css keeps the stored case
       (pagehero-eyebrow-case.mjs covers the click-away cases) */
    const pass = typeof value === 'string' && value.endsWith(' (edited)') && value !== value.toUpperCase();
    if (!pass) ok = false;
    console.log(`  edit ${t.block} ${t.attr}: ${pass ? 'OK' : 'FAIL'} → ${JSON.stringify(value)}`);
  }
  await page.waitForTimeout(1500);
  /* after the re-render the preview shows the edited value */
  const shown = await frame.locator('.phero [data-mats-edit="title"]').first().innerText();
  console.log(`  preview title after re-render: ${JSON.stringify(shown)}`);
  await page.screenshot({ path: `${out}/editor-${id}.png`, fullPage: false });
}

/* public pages: no editing markers */
for (const path of ['/product/', '/es/producto/', '/applications/', '/es/aplicaciones/', '/research/', '/es/investigacion/', '/team/', '/es/equipo/']) {
  const html = await (await fetch(BASE + path)).text();
  const markers = (html.match(/data-mats-(edit|image)/g) || []).length;
  const editorCss = (html.match(/blocks\/(page-hero|section-intro)\/editor\.css/g) || []).length;
  if (markers || editorCss) ok = false;
  console.log(`public ${path}: data-mats-* markers=${markers} editor.css links=${editorCss}`);
}
/* a hero whose photo attachment was deleted (pagehero-deleted-photo.php): text-only layout, no <img> */
{
  const res = await fetch(BASE + '/pagehero-deleted-photo/');
  if (res.ok) {
    const html = await res.text();
    const sec = (html.match(/<section class="sec phero"[\s\S]*?<\/section>/) || [''])[0];
    const pass = !!sec && !/phero__grid--photo/.test(sec) && !/<img/.test(sec);
    if (!pass) ok = false;
    console.log(`public /pagehero-deleted-photo/: ${pass ? 'OK text-only, no <img>' : 'FAIL'}`);
  }
}
console.log(errors.length ? 'JS errors:\n' + errors.join('\n') : 'no JS errors');
console.log(ok && !errors.length ? 'RESULT: PASS' : 'RESULT: FAIL');
await browser.close();
