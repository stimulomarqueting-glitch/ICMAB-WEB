/** Opens a page in the block editor, checks the section preview, edits a title in place. */
import { launch, login, closeWelcome, BASE } from './wp-login.mjs';

const postId = process.argv[2] || '5';
const out = process.argv[3] || '/tmp/editor.png';
const browser = await launch();
const page = await login(browser);
const errors = [];
page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));
page.on('console', (m) => { if (m.type() === 'error') errors.push('console: ' + m.text()); });
await page.goto(`${BASE}/wp-admin/post.php?post=${postId}&action=edit`);
await page.waitForSelector('.editor-styles-wrapper, iframe[name="editor-canvas"]', { timeout: 60000 });
await closeWelcome(page);
const frame = (await page.$('iframe[name="editor-canvas"]')) ? page.frameLocator('iframe[name="editor-canvas"]') : page;
const title = frame.locator('.mats-preview [data-mats-edit="title"]').first();
await title.waitFor({ timeout: 30000 });
console.log('iframe canvas:', !!(await page.$('iframe[name="editor-canvas"]')));
console.log('preview title:', await title.innerText());
await title.click();
await page.keyboard.press('End');
await page.keyboard.type(' (edited)');
await page.keyboard.press('Enter');
await page.waitForTimeout(1500);
const attrs = await page.evaluate(() => wp.data.select('core/block-editor').getBlocks().map((b) => b.attributes.title));
console.log('attributes after edit:', JSON.stringify(attrs));
await page.screenshot({ path: out, fullPage: false });
console.log(errors.length ? errors.join('\n') : 'no JS errors');
await browser.close();
