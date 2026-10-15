/**
 * QA (group "benefits"): opens a test page in the block editor and checks
 * the benefits / audiences previews: rendered (no "Error loading block"),
 * no JS errors, in-place editable elements present, and an in-place edit
 * updates the attribute (benefits title, benefits outcome text, audiences
 * text of the third panel — opened by pointing at it, as on the site).
 * Nothing is saved.
 *   node wordpress/tools/qa/benefits-editor.mjs <postId> <outPng> [home|product]
 */
import { launch, login, closeWelcome, BASE } from './wp-login.mjs';

const postId = process.argv[2] || '35';
const out = process.argv[3] || '/tmp/benefits-editor.png';
const role = process.argv[4] || 'home';
const browser = await launch();
const page = await login(browser, 'admin', 'admin-local-pass', { width: 1600, height: 1000 });
const errors = [];
page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));
page.on('console', (m) => { if (m.type() === 'error') errors.push('console: ' + m.text()); });
page.on('requestfailed', (r) => errors.push('requestfailed: ' + r.url()));
await page.goto(`${BASE}/wp-admin/post.php?post=${postId}&action=edit`);
await page.waitForSelector('.editor-styles-wrapper, iframe[name="editor-canvas"]', { timeout: 60000 });
await closeWelcome(page);
const hasIframe = !!(await page.$('iframe[name="editor-canvas"]'));
const frame = hasIframe ? page.frameLocator('iframe[name="editor-canvas"]') : page;
console.log('iframe canvas:', hasIframe);

const names = role === 'home' ? ['benefits', 'audiences'] : ['benefits'];
for (const name of names) {
  const block = frame.locator(`[data-type="matsurfer/${name}"]`).first();
  await block.waitFor({ timeout: 30000 });
  await block.scrollIntoViewIfNeeded();
  await block.locator('.mats-preview__html section').first().waitFor({ timeout: 30000 });
  const txt = await block.innerText();
  const edits = await block.locator('[data-mats-edit]').evaluateAll((els) => els.map((e) => e.getAttribute('data-mats-edit') + (e.getAttribute('contenteditable') === 'true' ? '' : ' (NOT editable)')));
  console.log(`[${name}] error box:`, /Error loading block/i.test(txt), '| editable:', edits.length, JSON.stringify(edits));
}

async function editInPlace(locator, suffix) {
  await locator.scrollIntoViewIfNeeded();
  await locator.click();
  await page.keyboard.press('Control+End');
  await page.keyboard.type(suffix);
  await page.keyboard.press('Enter');
  await page.waitForTimeout(1800);
}
const attrsOf = (name) => page.evaluate((name) => wp.data.select('core/block-editor').getBlocks().filter((b) => b.name === 'matsurfer/' + name).map((b) => b.attributes), name);

/* benefits: title + second outcome text */
const bene = frame.locator('[data-type="matsurfer/benefits"]').first();
await editInPlace(bene.locator('[data-mats-edit="title"]'), ' (edited)');
await editInPlace(bene.locator('[data-mats-edit="outcomes.1.text"]'), ' (edited)');
let a = (await attrsOf('benefits'))[0];
console.log('benefits title after edit:', JSON.stringify(a.title));
console.log('benefits outcomes.1.text after edit:', JSON.stringify(a.outcomes[1].text));
console.log('benefits preview shows edit:', (await bene.locator('h2').innerText()).includes('(edited)'));

if (role === 'home') {
  const aud = frame.locator('[data-type="matsurfer/audiences"]').first();
  const panel = aud.locator('.aud__panel').nth(2);
  await panel.scrollIntoViewIfNeeded();
  await panel.hover();
  await page.waitForTimeout(900);
  const text = aud.locator('[data-mats-edit="groups.2.text"]');
  console.log('audiences panel 3 text visible on hover:', await text.isVisible(), (await text.boundingBox())?.height);
  await page.screenshot({ path: out.replace('.png', '-aud-hover.png') });
  await editInPlace(text, ' (edited)');
  a = (await attrsOf('audiences'))[0];
  console.log('audiences groups.2.text after edit:', JSON.stringify(a.groups[2].text));
  await editInPlace(aud.locator('[data-mats-edit="groups.0.title"]'), ' (edited)');
  a = (await attrsOf('audiences'))[0];
  console.log('audiences groups.0.title after edit:', JSON.stringify(a.groups[0].title));
}
await page.screenshot({ path: out, fullPage: false });
console.log(errors.length ? errors.join('\n') : 'no JS errors');
await browser.close();
