/**
 * QA (fix bucket "seoblog"): the block editor still opens, previews and
 * saves the screens touched by the fixes — a Spanish news article (featured
 * image), the News page, the Applications page (sticky pills preview) — with
 * no JavaScript errors. Logs in as admin (or USER/PASS).
 *   WP_BASE=http://localhost:8982 node wordpress/tools/qa/fix-seoblog-editor.mjs <postId> <newsPageId> <appsPageId> [outDir]
 */
import fs from 'node:fs';
import path from 'node:path';
import { launch, login, closeWelcome, BASE } from './wp-login.mjs';

const [postId, newsId, appsId, out = ''] = process.argv.slice(2);
if (out) fs.mkdirSync(out, { recursive: true });
const browser = await launch();
const page = await login(browser, process.env.USER_LOGIN || 'admin', process.env.USER_PASS || 'admin-local-pass');
const errors = [];
page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));
page.on('console', (m) => m.type() === 'error' && !/favicon|404 \(Not Found\)|ERR_TUNNEL_CONNECTION_FAILED|ERR_PROXY/.test(m.text()) && errors.push('console: ' + m.text()));
let fails = 0;
const check = (ok, label, detail = '') => {
  if (!ok) fails++;
  console.log(`${ok ? 'PASS' : 'FAIL'} ${label}${detail ? ' — ' + detail : ''}`);
};

for (const [id, label, selector] of [
  [postId, 'news article', '.wp-block-post-content, .block-editor-block-list__layout'],
  [newsId, 'News page', '.block-editor-block-list__layout'],
  [appsId, 'Applications page', '.mats-preview .apps-nav, .mats-preview .apps-list'],
]) {
  errors.length = 0;
  await page.goto(`${BASE}/wp-admin/post.php?post=${id}&action=edit`, { waitUntil: 'load' });
  await page.waitForSelector('.editor-styles-wrapper, iframe[name="editor-canvas"]', { timeout: 60000 });
  await closeWelcome(page);
  const frame = (await page.$('iframe[name="editor-canvas"]')) ? page.frameLocator('iframe[name="editor-canvas"]') : page;
  const found = await frame.locator(selector).first().waitFor({ timeout: 30000 }).then(() => true).catch(() => false);
  check(found, `${label} (${id}) editor canvas shows ${selector}`);
  if (label === 'news article') {
    const thumb = await page.evaluate(() => wp.data.select('core/editor').getEditedPostAttribute('featured_media'));
    check(thumb > 0, `${label} keeps its featured image`, String(thumb));
    await page.evaluate(() => wp.data.dispatch('core/editor').savePost());
    await page.waitForFunction(() => !wp.data.select('core/editor').isSavingPost(), null, { timeout: 30000 });
    const ok = await page.evaluate(() => wp.data.select('core/editor').didPostSaveRequestSucceed());
    check(ok, `${label} saves`);
  }
  if (out) await page.screenshot({ path: path.join(out, `editor-${id}.png`) });
  check(errors.length === 0, `${label} no JS errors`, errors.slice(0, 3).join(' | '));
}
await browser.close();
console.log(fails ? `${fails} FAIL` : 'all PASS');
process.exitCode = fails ? 1 : 0;
