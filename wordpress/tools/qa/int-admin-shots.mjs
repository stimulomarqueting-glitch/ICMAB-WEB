/** Screenshots of the key admin screens on a fully installed instance. */
import { launch, login, closeWelcome } from './wp-login.mjs';
const base = process.argv[2] || 'http://localhost:8950';
const out = process.argv[3] || '/tmp';
process.env.WP_BASE = base;
const browser = await launch();
const page = await login(browser);
const shots = [
  ['dashboard', '/wp-admin/'],
  ['pages', '/wp-admin/edit.php?post_type=page'],
  ['team', '/wp-admin/edit.php?post_type=mats_team'],
  ['settings', '/wp-admin/admin.php?page=mats-settings'],
  ['setup', '/wp-admin/admin.php?page=mats-setup'],
  ['leads', '/wp-admin/admin.php?page=mats-leads'],
];
for (const [name, path] of shots) {
  await page.goto(base + path, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(1200);
  await page.screenshot({ path: `${out}/admin-${name}.png` });
}
const homeId = await page.evaluate(async () => (await (await fetch('/wp-json/wp/v2/pages?slug=home', { headers: { 'X-WP-Nonce': window.wpApiSettings?.nonce || '' } })).json())[0]?.id);
await page.goto(`${base}/wp-admin/post.php?post=${homeId}&action=edit`);
await page.waitForTimeout(6000);
await closeWelcome(page);
await page.waitForTimeout(3000);
await page.screenshot({ path: `${out}/admin-edit-home.png` });
console.log('home id', homeId);
await browser.close();
