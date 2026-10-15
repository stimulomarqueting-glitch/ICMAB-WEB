/**
 * QA (module "cptadmin"): screenshots of the four list screens and edit
 * screens, as admin and as editor, desktop + narrow.
 * Run from the repo root:
 *   WP_BASE=http://localhost:8918 node wordpress/tools/qa/cptadmin-shots.mjs <outdir>
 */
import { launch, login, BASE } from './wp-login.mjs';
import fs from 'node:fs';

const out = process.argv[2] || '.';
fs.mkdirSync(out, { recursive: true });
const types = ['mats_team', 'mats_application', 'mats_publication', 'mats_expert'];

const browser = await launch();
const errors = [];
for (const [user, pass] of [
  ['admin', 'admin-local-pass'],
  ['editora', 'editora-local-pass'],
]) {
  for (const vp of [
    { width: 1440, height: 1000, tag: 'd' },
    { width: 390, height: 844, tag: 'm' },
  ]) {
    const page = await login(browser, user, pass, { width: vp.width, height: vp.height });
    page.on('pageerror', (e) => errors.push(`${user} ${page.url()} ${e.message}`));
    page.on('console', (m) => m.type() === 'error' && !/ERR_TUNNEL|gravatar/.test(m.text()) && errors.push(`${user} console ${m.text()}`));
    for (const type of types) {
      await page.goto(`${BASE}/wp-admin/edit.php?post_type=${type}`);
      await page.screenshot({ path: `${out}/${user}-${vp.tag}-${type}-list.png`, fullPage: true });
      const first = page.locator('#the-list tr a.row-title').first();
      if (await first.count()) {
        await first.click();
        await page.waitForLoadState('load');
        await page.screenshot({ path: `${out}/${user}-${vp.tag}-${type}-edit.png`, fullPage: true });
      }
      if (vp.tag === 'd') {
        await page.goto(`${BASE}/wp-admin/post-new.php?post_type=${type}`);
        await page.screenshot({ path: `${out}/${user}-${vp.tag}-${type}-new.png`, fullPage: true });
      }
    }
    await page.context().close();
  }
}
await browser.close();
console.log(errors.length ? errors.join('\n') : 'no JS errors');
