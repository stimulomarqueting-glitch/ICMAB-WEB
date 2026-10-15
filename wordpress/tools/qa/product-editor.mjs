/**
 * QA (group "product"): opens the product test pages in the block editor and
 * checks the differentiator / how-it-works / parameters / faq previews:
 * rendered (no error notice), no JS errors, in-place editable elements
 * present, editing them updates the attributes (incl. a repeater item and
 * the FAQ question inside its button), then the public pages carry no
 * data-mats-* attributes.
 *   node wordpress/tools/qa/product-editor.mjs <outDir> <productEnId> <productEsId> [edgeId]
 */
/* this group's instance unless told otherwise (wp-login.mjs reads WP_BASE) */
process.env.WP_BASE = process.env.WP_BASE || 'http://localhost:8905';
const { launch, login, closeWelcome, BASE } = await import('./wp-login.mjs');

const out = process.argv[2] || '/tmp/product';
const [enId, esId, edgeId] = process.argv.slice(3);
const browser = await launch();
const page = await login(browser);
const errors = [];
page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));
page.on('console', (m) => { if (m.type() === 'error' && !/ERR_TUNNEL_CONNECTION_FAILED|ERR_PROXY|net::ERR/.test(m.text())) errors.push('console: ' + m.text()); });
page.on('requestfailed', (r) => { if (r.url().startsWith(BASE) && !/\.mp4/.test(r.url())) errors.push('local request failed: ' + r.url()); });

let ok = true;
const mine = ['Diferencial clave', 'Cómo funciona', 'Parámetros', 'Preguntas frecuentes'];

async function open(id) {
  await page.goto(`${BASE}/wp-admin/post.php?post=${id}&action=edit`);
  await page.waitForSelector('.editor-styles-wrapper, iframe[name="editor-canvas"]', { timeout: 60000 });
  await closeWelcome(page);
  const hasIframe = !!(await page.$('iframe[name="editor-canvas"]'));
  const frame = hasIframe ? page.frameLocator('iframe[name="editor-canvas"]') : page;
  return { frame, hasIframe };
}

async function listPreviews(frame, id, hasIframe) {
  const previews = frame.locator('.mats-preview');
  const n = await previews.count();
  console.log(`post ${id}: iframe=${hasIframe} previews=${n}`);
  for (let i = 0; i < n; i++) {
    const p = previews.nth(i);
    const badge = await p.locator('.mats-preview__badge').innerText().catch(() => '?');
    if (!mine.includes(badge.trim())) continue;
    const notice = await p.locator('.components-notice').count();
    const empty = await p.locator('.mats-empty').count();
    const errText = (await p.innerText()).includes('Error loading block');
    const edits = await p.locator('[data-mats-edit]').evaluateAll((els) => els.map((e) => `${e.getAttribute('data-mats-edit')}${e.isContentEditable ? '' : '(NOT editable)'}`));
    const notEditable = edits.filter((e) => e.includes('NOT')).length;
    console.log(`  [${badge}] notices=${notice} empty=${empty} errorText=${errText} editable=${edits.length} notEditable=${notEditable}`);
    console.log(`     ${edits.join(', ')}`);
    if (notice || errText || notEditable) ok = false;
  }
}

async function editInPlace(frame, t) {
  const node = frame.locator(t.sel).first();
  if (!(await node.count())) {
    console.log(`  edit ${t.block} ${t.attr}: element not found (${t.sel})`);
    ok = false;
    return;
  }
  await node.scrollIntoViewIfNeeded();
  await node.click();
  await page.keyboard.press('Control+End');
  await page.keyboard.type(' (edited)');
  await page.keyboard.press('Enter');
  await page.waitForTimeout(1200);
  const value = await page.evaluate(({ block, attr }) => {
    const b = wp.data.select('core/block-editor').getBlocks().find((x) => x.name === block);
    return attr.split('.').reduce((o, k) => (o == null ? o : o[k]), b && b.attributes);
  }, t);
  const pass = typeof value === 'string' && value.endsWith(' (edited)') && (!t.expectStart || value.startsWith(t.expectStart));
  if (!pass) ok = false;
  console.log(`  edit ${t.block} ${t.attr}: ${pass ? 'OK' : 'FAIL'} → ${JSON.stringify(value)}`);
}

/* EN product page: previews + in-place edits */
{
  const { frame, hasIframe } = await open(enId);
  await frame.locator('.mats-preview .faq').first().waitFor({ timeout: 60000 });
  await page.waitForTimeout(2500);
  await listPreviews(frame, enId, hasIframe);
  /* FAQ answers are shown open in the preview */
  const openItems = await frame.locator('.faq .acc__item[data-open]').count();
  const hiddenPanels = await frame.locator('.faq .acc__panel[hidden]').count();
  console.log(`  faq preview: open items=${openItems} hidden panels=${hiddenPanels}`);
  if (!openItems || hiddenPanels) ok = false;
  await page.screenshot({ path: `${out}/editor-${enId}-top.png` });

  const targets = [
    { sel: '.diff [data-mats-edit="kicker"]', block: 'matsurfer/differentiator', attr: 'kicker', expectStart: 'Key differentiator' },
    { sel: '.diff [data-mats-edit="title"]', block: 'matsurfer/differentiator', attr: 'title' },
    { sel: '.diff [data-mats-edit="features.1.text"]', block: 'matsurfer/differentiator', attr: 'features.1.text' },
    { sel: '.tech [data-mats-edit="caption"]', block: 'matsurfer/how-it-works', attr: 'caption' },
    { sel: '.tech [data-mats-edit="steps.2.title"]', block: 'matsurfer/how-it-works', attr: 'steps.2.title' },
    { sel: '.params [data-mats-edit="lead"]', block: 'matsurfer/parameters', attr: 'lead' },
    { sel: '.params [data-mats-edit="items.4.label"]', block: 'matsurfer/parameters', attr: 'items.4.label' },
    { sel: '.faq [data-mats-edit="items.0.q"]', block: 'matsurfer/faq', attr: 'items.0.q', expectStart: 'How does it work?' },
    { sel: '.faq [data-mats-edit="items.1.a"]', block: 'matsurfer/faq', attr: 'items.1.a' },
    { sel: '.faq [data-mats-edit="kicker"]', block: 'matsurfer/faq', attr: 'kicker', expectStart: 'FAQ' },
  ];
  for (const t of targets) await editInPlace(frame, t);
  await page.waitForTimeout(2000);
  /* the re-rendered previews show the edited values */
  const shown = await frame.locator('.faq [data-mats-edit="items.0.q"]').first().innerText();
  const shown2 = await frame.locator('.tech [data-mats-edit="steps.2.title"]').first().innerText();
  console.log(`  preview after re-render: ${JSON.stringify(shown)} / ${JSON.stringify(shown2)}`);
  if (!shown.endsWith('(edited)') || !shown2.endsWith('(edited)')) ok = false;
  /* still a single accordion item list; the question button holds only the text + icon */
  const btnKids = await frame.locator('.faq .acc__trigger').first().evaluate((b) => [...b.children].map((c) => c.className || c.tagName).join(','));
  console.log(`  faq trigger children: ${btnKids}`);
  await frame.locator('.faq').first().scrollIntoViewIfNeeded();
  await page.screenshot({ path: `${out}/editor-${enId}-faq.png` });
  await frame.locator('.tech').first().scrollIntoViewIfNeeded();
  await page.screenshot({ path: `${out}/editor-${enId}-tech.png` });
  await frame.locator('.diff').first().scrollIntoViewIfNeeded();
  await page.screenshot({ path: `${out}/editor-${enId}-diff.png` });
  /* leave without saving */
}

/* ES product page: previews only */
if (esId) {
  page.once('dialog', (d) => d.accept().catch(() => {}));
  const { frame, hasIframe } = await open(esId);
  await frame.locator('.mats-preview .faq').first().waitFor({ timeout: 60000 });
  await page.waitForTimeout(2500);
  await listPreviews(frame, esId, hasIframe);
  const q = await frame.locator('.faq [data-mats-edit="items.0.q"]').first().innerText();
  console.log(`  es first question: ${JSON.stringify(q)}`);
  await frame.locator('.params').first().scrollIntoViewIfNeeded();
  await page.screenshot({ path: `${out}/editor-${esId}-params.png` });
}

/* edge page: no-video placeholder editable, empty sections show the empty state */
if (edgeId) {
  page.once('dialog', (d) => d.accept().catch(() => {}));
  const { frame, hasIframe } = await open(edgeId);
  await frame.locator('.mats-preview .faq').first().waitFor({ timeout: 60000 });
  await page.waitForTimeout(3000);
  await listPreviews(frame, edgeId, hasIframe);
  const empties = await frame.locator('.mats-preview .mats-empty').count();
  console.log(`  edge: empty-state boxes=${empties} (4 expected)`);
  if (empties !== 4) ok = false;
  await editInPlace(frame, { sel: '.tech [data-mats-edit="placeholder"]', block: 'matsurfer/how-it-works', attr: 'placeholder', expectStart: 'Device video coming soon' });
  await frame.locator('.tech').first().scrollIntoViewIfNeeded();
  await page.screenshot({ path: `${out}/editor-${edgeId}-placeholder.png` });
}

/* public pages: no editing markers */
for (const path of ['/product/', '/es/producto/', '/product-qa-edge/', '/es/product-qa-edge-es/']) {
  const html = await (await fetch(BASE + path)).text();
  const markers = (html.match(/data-mats-(edit|image)/g) || []).length;
  const empty = (html.match(/mats-empty|mats-note/g) || []).length;
  if (markers || empty) ok = false;
  console.log(`public ${path}: data-mats-* markers=${markers} editor boxes=${empty}`);
}
console.log(errors.length ? 'JS errors:\n' + errors.join('\n') : 'no JS errors');
console.log(ok && !errors.length ? 'RESULT: PASS' : 'RESULT: FAIL');
await browser.close();
