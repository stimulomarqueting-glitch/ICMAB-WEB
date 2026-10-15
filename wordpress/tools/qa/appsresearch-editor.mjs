/**
 * QA (group "appsresearch"): opens the applications and research test pages
 * in the block editor, checks that every section preview renders (no error
 * notice, no "Error loading block"), lists the in-place editable elements,
 * edits a few of them in place and checks the attributes, and confirms the
 * public pages carry no data-mats-edit.
 *   node wordpress/tools/qa/appsresearch-editor.mjs <applicationsId> <researchId> <outDir>
 * (run from /home/user/ICMAB-WEB so playwright-core resolves)
 */
import { launch, login, closeWelcome, BASE as DEFAULT_BASE } from './wp-login.mjs';
import fs from 'node:fs';

const BASE = process.env.WP_BASE || 'http://localhost:8906';
const [appsId, researchId] = [process.argv[2], process.argv[3]];
const out = process.argv[4] || '/tmp/appsresearch-editor';
fs.mkdirSync(out, { recursive: true });
void DEFAULT_BASE;

const ours = ['applications-list', 'broader', 'research-experiences', 'research-publications', 'research-validation', 'invite'];
const edits = {
  [appsId]: [
    { block: 'applications-list', path: 'labelCta', add: ' (editado)' },
    { block: 'applications-list', path: 'labelChallenge', add: ' X' },
    { block: 'broader', path: 'title', add: ' (editado)' },
    { block: 'broader', path: 'kicker', add: ' X' },
  ],
  [researchId]: [
    { block: 'research-experiences', path: 'title', add: ' (editado)' },
    { block: 'research-publications', path: 'title', add: ' (editado)' },
    { block: 'research-validation', path: 'paragraphs.0', add: ' (editado)' },
    { block: 'invite', path: 'text', add: ' (editado)' },
  ],
};

const browser = await launch();
/* wp-login.mjs reads WP_BASE at import time; log in on our own port */
const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
const page = await context.newPage();
await page.goto(`${BASE}/wp-login.php`);
await page.fill('#user_login', 'admin');
await page.fill('#user_pass', 'admin-local-pass');
await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);
void login;

let failures = 0;
for (const postId of [appsId, researchId]) {
  const errors = [];
  page.removeAllListeners('pageerror');
  page.removeAllListeners('console');
  page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));
  page.on('console', (m) => { if (m.type() === 'error') errors.push('console: ' + m.text()); });
  await page.goto(`${BASE}/wp-admin/post.php?post=${postId}&action=edit`);
  await page.waitForSelector('.editor-styles-wrapper, iframe[name="editor-canvas"]', { timeout: 60000 });
  await closeWelcome(page);
  const hasIframe = !!(await page.$('iframe[name="editor-canvas"]'));
  const frame = hasIframe ? page.frameLocator('iframe[name="editor-canvas"]') : page;
  await frame.locator('.mats-preview__html').first().waitFor({ timeout: 30000 });
  await page.waitForTimeout(4000);

  const info = await page.evaluate(() => wp.data.select('core/block-editor').getBlocks().map((b) => ({ name: b.name, clientId: b.clientId })));
  console.log(`post ${postId}: iframe=${hasIframe} blocks=${info.map((b) => b.name.replace('matsurfer/', '')).join(', ')}`);

  for (const b of info) {
    const short = b.name.replace('matsurfer/', '');
    if (!ours.includes(short)) continue;
    const blk = frame.locator(`[data-block="${b.clientId}"]`);
    await blk.scrollIntoViewIfNeeded();
    await page.waitForTimeout(300);
    const html = await blk.innerHTML();
    const bad = /Error loading block|No se ha podido cargar|components-notice is-error/.test(html);
    const edits = await blk.locator('[data-mats-edit]').evaluateAll((els) => els.map((e) => e.getAttribute('data-mats-edit')));
    const notes = await blk.locator('.mats-note').count();
    const contentEditable = await blk.locator('[data-mats-edit][contenteditable="true"]').count();
    console.log(`  ${short}: ok=${!bad} editable=${[...new Set(edits)].join(',')} (${edits.length} el, ${contentEditable} contenteditable) notes=${notes}`);
    if (bad) failures++;
    await blk.screenshot({ path: `${out}/editor-${short}-${postId}.png` }).catch(() => {});
  }

  for (const e of edits[postId]) {
    const clientId = info.find((b) => b.name === 'matsurfer/' + e.block).clientId;
    const node = frame.locator(`[data-block="${clientId}"] [data-mats-edit="${e.path}"]`).first();
    await node.scrollIntoViewIfNeeded();
    await node.click();
    await page.keyboard.press('Control+End');
    await page.keyboard.type(e.add);
    await page.keyboard.press('Enter');
    await page.waitForTimeout(1500);
    const value = await page.evaluate(([id, path]) => {
      const attrs = wp.data.select('core/block-editor').getBlockAttributes(id);
      return path.split('.').reduce((o, k) => (o == null ? o : o[k]), attrs);
    }, [clientId, e.path]);
    const ok = typeof value === 'string' && value.endsWith(e.add.trim()) && !/[A-ZÁÉÍÓÚ]{6,} X$/.test(value);
    console.log(`  edit ${e.block}.${e.path} → ${JSON.stringify(value)} ${ok ? 'OK' : 'FAIL'}`);
    if (!ok) failures++;
  }
  await page.waitForTimeout(2500);
  /* the preview re-renders with the new value */
  const shown = await frame.locator(`[data-block="${info.find((b) => b.name === 'matsurfer/' + edits[postId][0].block).clientId}"] [data-mats-edit="${edits[postId][0].path}"]`).first().innerText();
  console.log(`  re-rendered preview shows: ${JSON.stringify(shown)}`);
  await page.screenshot({ path: `${out}/editor-${postId}.png` });
  const real = errors.filter((x) => !/favicon|404 \(Not Found\)|ERR_TUNNEL_CONNECTION_FAILED/.test(x));
  console.log(real.length ? '  JS errors:\n   ' + real.join('\n   ') : '  no JS errors');
  if (real.length) failures++;
}

/* public pages: no editor markers */
for (const path of ['/applications/', '/es/aplicaciones/', '/research/', '/es/investigacion/']) {
  const res = await fetch(BASE + path);
  const html = await res.text();
  const n = (html.match(/data-mats-edit|data-mats-image|mats-note/g) || []).length;
  console.log(`public ${path}: status=${res.status} editor markers=${n}`);
  if (n) failures++;
}
console.log('failures:', failures);
await browser.close();
