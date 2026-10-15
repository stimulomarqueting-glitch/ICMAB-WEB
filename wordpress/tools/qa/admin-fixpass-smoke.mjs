/**
 * admin module QA (fix pass) — read-only smoke test as administrator, safe
 * on a shared full site (e.g. 8950): dashboard, guide, Pages list, the
 * Product page in the editor (sections can be unlocked), media screen.
 * Opening the editor takes WordPress' usual 150-second edit lock on the
 * Product page; nothing is saved.
 *
 *   WP_BASE=http://localhost:8950 node wordpress/tools/qa/admin-fixpass-smoke.mjs [outDir]
 */
import { launch, login, closeWelcome, BASE } from './wp-login.mjs';

const OUT = process.argv[2] || '/tmp/claude-0/-home-user-ICMAB-WEB/ae5d27f5-1af0-537f-966f-1d2257e6cfbd/scratchpad/admin-fix/smoke';
const failures = [];
const report = {};
const check = (cond, label) => (cond ? true : (failures.push(label), false));
const errors = [];
const browser = await launch();
const page = await login(browser);
page.on('pageerror', (e) => errors.push(String(e).slice(0, 200)));

await page.goto(`${BASE}/wp-admin/`);
report.tiles = await page.$$eval('.mats-tile__title', (els) => els.map((e) => e.childNodes[0].textContent.trim()));
report.esNotice = await page.$$eval('.mats-es-notice', (e) => e.length);
await page.screenshot({ path: `${OUT}/dashboard.png`, fullPage: true });
check(report.tiles.length >= 10, 'dashboard tiles');

await page.goto(`${BASE}/wp-admin/admin.php?page=mats-guide`);
report.guide = await page.$$eval('.mats-guide__section h2', (els) => els.length);
report.guideBroken = await page.$$eval('.mats-guide a[href^="#"]', (as) => as.map((a) => a.getAttribute('href')).filter((h) => h.length > 1 && !document.getElementById(h.slice(1))));
check(report.guide === 15 && report.guideBroken.length === 0, 'guide: 15 sections, anchors ok');

await page.goto(`${BASE}/wp-admin/edit.php?post_type=page&lang=all`);
report.pagesRows = await page.$$eval('#the-list tr', (r) => r.length);
report.lockedRows = await page.$$eval('.mats-row-locked', (r) => r.length);
check(report.lockedRows > 0, 'pages list: fixed pages marked');

const productId = await page.$$eval('#the-list tr', (rows) => {
  const row = rows.find((r) => /^Product\b/.test(r.querySelector('.row-title')?.textContent.trim() || ''));
  return row ? +row.id.replace('post-', '') : 0;
});
await page.goto(`${BASE}/wp-admin/post.php?post=${productId}&action=edit`);
await page.waitForFunction(() => window.wp?.data?.select('core/editor')?.getCurrentPostId(), null, { timeout: 60000, polling: 500 });
await page.waitForTimeout(2500);
await closeWelcome(page);
report.product = await page.evaluate(() => {
  const be = wp.data.select('core/block-editor');
  const blocks = be.getBlocks();
  return { blocks: blocks.length, canLockType: be.canLockBlockType(blocks[0].name), templateLock: be.getSettings().templateLock, palette: (be.getSettings().__experimentalFeatures?.color?.palette?.theme || []).length, notices: wp.data.select('core/notices').getNotices().map((n) => n.content.slice(0, 60)) };
});
await page.screenshot({ path: `${OUT}/product-editor.png` });
check(report.product.canLockType === true && report.product.templateLock !== 'all', 'admin: Product sections can be unlocked');
await page.goto(`${BASE}/wp-admin/media-new.php`);
report.tip = await page.$$eval('.mats-upload-tip', (e) => e.map((x) => x.innerText).join(' ').slice(0, 220));
check(/Recorte sin fondo/.test(report.tip), 'upload tip');
await browser.close();
report.jsErrors = errors;
console.log(JSON.stringify(report, null, 2));
if (failures.length) {
  console.log('\nFAILED:\n - ' + failures.join('\n - '));
  process.exit(1);
}
console.log('\nALL CHECKS PASSED');
