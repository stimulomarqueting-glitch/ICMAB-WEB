/**
 * admin module QA: viewport screenshots of admin screens (dashboard, guide
 * sections, editor) for a user.
 *   WP_BASE=http://localhost:8915 node wordpress/tools/qa/admin-shots.mjs <user> <pass> <prefix> [width]
 */
import { launch, login, closeWelcome, BASE } from './wp-login.mjs';
const [user = 'editora', pass = 'editora-local-pass', prefix = 'ed', width = '1440'] = process.argv.slice(2);
const OUT = '/tmp/claude-0/-home-user-ICMAB-WEB/ae5d27f5-1af0-537f-966f-1d2257e6cfbd/scratchpad/admin/shots';
const browser = await launch();
const page = await login(browser, user, pass, { width: Number(width), height: 1000 });
await page.goto(`${BASE}/wp-admin/`);
await page.waitForTimeout(600);
await page.screenshot({ path: `${OUT}/${prefix}-dash.png` });
await page.goto(`${BASE}/wp-admin/admin.php?page=mats-guide`);
await page.waitForTimeout(400);
await page.screenshot({ path: `${OUT}/${prefix}-guide-top.png` });
for (const id of ['noticias', 'fichas', 'consejos-fotos', 'no-se-puede']) {
  await page.goto(`${BASE}/wp-admin/admin.php?page=mats-guide#${id}`);
  await page.waitForTimeout(400);
  await page.screenshot({ path: `${OUT}/${prefix}-guide-${id}.png` });
}
await page.emulateMedia({ media: 'print' });
await page.pdf({ path: `${OUT}/${prefix}-guide.pdf`, format: 'A4' }).catch(() => {});
await page.emulateMedia({ media: 'screen' });
await page.goto(`${BASE}/wp-admin/upload.php?mode=list`);
await page.screenshot({ path: `${OUT}/${prefix}-media.png` });
await page.goto(`${BASE}/wp-admin/media-new.php`);
await page.screenshot({ path: `${OUT}/${prefix}-media-new.png` });
await browser.close();
