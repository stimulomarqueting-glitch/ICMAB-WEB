/**
 * QA (group "team"): opens the team test page in the block editor, checks
 * that the team-showcase and team-journey previews render (no "Error
 * loading block", no JS errors), lists the in-place editable elements,
 * edits the showcase h1 and a journey step title in place and checks the
 * attributes; then checks the public page has no data-mats-edit.
 *   node wordpress/tools/qa/team-editor.mjs <postId> <outDir> [publicPath]
 */
import { launch, login, closeWelcome, BASE } from './wp-login.mjs';
import fs from 'node:fs';

const postId = process.argv[2] || '35';
const out = process.argv[3] || '/tmp/team-editor';
const publicPath = process.argv[4] || '/team/';
fs.mkdirSync(out, { recursive: true });

const browser = await launch();
const page = await login(browser, 'admin', 'admin-local-pass', { width: 1440, height: 1000 });
const errors = [];
page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));
page.on('console', (m) => { if (m.type() === 'error') errors.push('console: ' + m.text() + ' @ ' + JSON.stringify(m.location())); });
page.on('requestfailed', (r) => errors.push('requestfailed: ' + r.url()));
await page.goto(`${BASE}/wp-admin/post.php?post=${postId}&action=edit`);
await page.waitForSelector('.editor-styles-wrapper, iframe[name="editor-canvas"]', { timeout: 60000 });
await closeWelcome(page);
const hasIframe = !!(await page.$('iframe[name="editor-canvas"]'));
const frame = hasIframe ? page.frameLocator('iframe[name="editor-canvas"]') : page;

const blocks = await page.evaluate(() => wp.data.select('core/block-editor').getBlocks().map((b) => ({ id: b.clientId, name: b.name })));
console.log('iframe canvas:', hasIframe, 'blocks:', blocks.map((b) => b.name).join(', '));

const preview = (name) => {
  const b = blocks.find((x) => x.name === name);
  return frame.locator(`[data-block="${b.id}"]`);
};

for (const name of ['matsurfer/team-showcase', 'matsurfer/team-journey']) {
  const p = preview(name);
  await p.locator('.mats-preview__html section').first().waitFor({ timeout: 30000 });
  const text = await p.innerText();
  const edits = await p.locator('[data-mats-edit]').evaluateAll((els) => els.map((e) => e.getAttribute('data-mats-edit')));
  const imgs = await p.locator('img').evaluateAll((els) => els.filter((i) => !i.complete || i.naturalWidth === 0).map((i) => i.src));
  console.log(`${name}: error=${/Error loading block/i.test(text)} notes=${await p.locator('.mats-note').count()} editable=[${edits.join(', ')}] brokenImgs=${imgs.length}`);
  await p.scrollIntoViewIfNeeded();
  await page.waitForTimeout(500);
  await p.screenshot({ path: `${out}/editor-${name.split('/')[1]}-${postId}.png` });
}

/* edit the showcase h1 in place */
const h1 = preview('matsurfer/team-showcase').locator('[data-mats-edit="title"]').first();
await h1.scrollIntoViewIfNeeded();
const before = await h1.innerText();
await h1.click();
await page.keyboard.press('Control+End');
await page.keyboard.type(' (edited)');
await page.keyboard.press('Enter');
await page.waitForTimeout(1500);

/* edit a journey step title in place */
const step = preview('matsurfer/team-journey').locator('[data-mats-edit="steps.1.title"]').first();
await step.scrollIntoViewIfNeeded();
await step.click();
await page.keyboard.press('Control+End');
await page.keyboard.type(' (edited)');
await page.keyboard.press('Enter');
await page.waitForTimeout(1500);

/* a fact value */
const fact = preview('matsurfer/team-journey').locator('[data-mats-edit="facts.0.value"]').first();
await fact.click();
await page.keyboard.press('Control+End');
await page.keyboard.type('0');
await page.keyboard.press('Enter');
await page.waitForTimeout(1500);

const attrs = await page.evaluate(() => {
  const bs = wp.data.select('core/block-editor').getBlocks();
  const show = bs.find((b) => b.name === 'matsurfer/team-showcase');
  const jour = bs.find((b) => b.name === 'matsurfer/team-journey');
  return { showTitle: show.attributes.title, step1: jour.attributes.steps[1].title, fact0: jour.attributes.facts[0].value, lock: show.attributes.lock };
});
console.log('before:', before);
console.log('attributes after edit:', JSON.stringify(attrs));
await page.waitForTimeout(1500);
const reRendered = await preview('matsurfer/team-showcase').locator('[data-mats-edit="title"]').first().innerText();
console.log('preview after re-render:', reRendered);
await preview('matsurfer/team-showcase').screenshot({ path: `${out}/editor-show-edited-${postId}.png` });
console.log(errors.length ? errors.join('\n') : 'no JS errors');

/* public page: no editing hooks */
const pub = await browser.newPage();
await pub.goto(`${BASE}${publicPath}`);
const html = await pub.content();
console.log('public data-mats-edit/image:', (html.match(/data-mats-(edit|image)/g) || []).length, ' note elements:', (html.match(/class="mats-(note|empty)/g) || []).length);
await browser.close();
