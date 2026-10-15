/**
 * QA (module "forms"): admin notice "Falta el formulario de contacto…" and
 * its "Crear los formularios de contacto" button (nonce + admin-post).
 *
 *   WP_BASE=http://localhost:8911 node wordpress/tools/qa/forms-admin.mjs <out.png>
 */
import { launch, login, BASE } from './wp-login.mjs';

const out = process.argv[2] || '/tmp/forms-admin.png';
const browser = await launch();
const page = await login(browser);
await page.goto(`${BASE}/wp-admin/index.php`);
const notice = page.locator('.notice:has(button:text("Crear los formularios de contacto"))');
const report = { noticeBefore: (await notice.count()) ? (await notice.first().innerText()).trim() : null };
if (report.noticeBefore) {
  await Promise.all([page.waitForNavigation(), notice.locator('button').click()]);
  report.urlAfter = page.url().replace(BASE, '');
  report.noticeAfter = (await page.locator('.notice-success, .notice-error').first().innerText().catch(() => '')).trim();
  report.stillMissing = (await page.locator('.notice:has(button:text("Crear los formularios de contacto"))').count()) > 0;
}
/* forged request without nonce must be refused */
const forged = await page.evaluate(async (base) => {
  const body = new URLSearchParams({ action: 'mats_wpforms_create' });
  const res = await fetch(`${base}/wp-admin/admin-post.php`, { method: 'POST', body, credentials: 'same-origin', redirect: 'manual' });
  return res.status;
}, BASE);
report.forgedStatus = forged;
await page.screenshot({ path: out });
console.log(JSON.stringify(report, null, 2));
await browser.close();
