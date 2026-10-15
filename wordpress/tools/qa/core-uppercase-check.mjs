/** Focus+blur an uppercase-styled editable and check the stored value keeps its case. */
import { launch, login, closeWelcome, BASE } from './wp-login.mjs';
const postId = process.argv[2];
const browser = await launch();
const page = await login(browser);
await page.goto(`${BASE}/wp-admin/post.php?post=${postId}&action=edit`);
await page.waitForSelector('iframe[name="editor-canvas"]', { timeout: 60000 });
await closeWelcome(page);
const frame = page.frameLocator('iframe[name="editor-canvas"]');
const els = frame.locator('.mats-preview [data-mats-edit]');
await els.first().waitFor({ timeout: 30000 });
const n = await els.count();
let checked = 0, bad = [];
for (let i = 0; i < n && checked < 40; i++) {
  const el = els.nth(i);
  const tt = await el.evaluate((e) => getComputedStyle(e).textTransform);
  if (tt !== 'uppercase') continue;
  const path = await el.getAttribute('data-mats-edit');
  const before = await page.evaluate(() => JSON.stringify(wp.data.select('core/block-editor').getBlocks().map((b) => b.attributes)));
  await el.click({ force: true });
  await page.mouse.click(5, 300);
  await page.waitForTimeout(400);
  const after = await page.evaluate(() => JSON.stringify(wp.data.select('core/block-editor').getBlocks().map((b) => b.attributes)));
  checked++;
  if (before !== after) bad.push(path);
}
const img = frame.locator('.mats-preview img[data-mats-image]').first();
const pe = (await img.count()) ? await img.evaluate((e) => getComputedStyle(e).pointerEvents) : 'no-image';
console.log(JSON.stringify({ uppercaseFieldsChecked: checked, changedOnFocusBlur: bad, imagePointerEvents: pe }));
await browser.close();
