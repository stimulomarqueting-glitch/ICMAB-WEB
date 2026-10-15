/** admin reviewer: dashboard + menus as admin and editor (read-only). */
import { launch, login, closeWelcome, BASE } from './wp-login.mjs';
const OUT = process.argv[2];
const browser = await launch();
const out = {};
for (const [who, u, p] of [['admin', 'admin', 'admin-local-pass'], ['editor', 'editora', 'editora-local-pass']]) {
  const page = await login(browser, u, p);
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  page.on('console', (m) => { if (m.type() === 'error') errors.push('console: ' + m.text()); });
  await page.goto(`${BASE}/wp-admin/`);
  await page.waitForTimeout(1000);
  await page.screenshot({ path: `${OUT}/${who}-dashboard.png`, fullPage: true });
  out[who] = {
    menu: await page.$$eval('#adminmenu > li', (items) => items.filter((li) => getComputedStyle(li).display !== 'none').map((li) => li.classList.contains('wp-menu-separator') ? '---' : ((li.querySelector('.wp-menu-name')?.innerText || '').trim() + ' <' + (li.querySelector('a')?.getAttribute('href') || '') + '>'))),
    submenus: await page.$$eval('#adminmenu > li.menu-top', (items) => items.map((li) => [(li.querySelector('.wp-menu-name')?.innerText || '').trim(), [...li.querySelectorAll('.wp-submenu li a')].map((a) => a.innerText.trim() + ' <' + a.getAttribute('href') + '>')])),
    tiles: await page.$$eval('.mats-tile', (els) => els.map((e) => e.innerText.replace(/\s+/g, ' ').trim() + ' <' + e.querySelector('a').getAttribute('href') + '>')),
    widgets: await page.$$eval('#dashboard-widgets .postbox', (els) => els.filter((e) => e.offsetParent).map((e) => e.id + ': ' + (e.querySelector('h2')?.innerText || ''))),
    bar: await page.$$eval('#wpadminbar li[id^="wp-admin-bar-"]', (els) => els.map((e) => e.id.replace('wp-admin-bar-', '') + '=' + (e.querySelector('a, .ab-item')?.innerText || '').trim().slice(0, 40))),
    notices: await page.$$eval('#wpbody-content .notice, #wpbody-content .updated, #wpbody-content .error', (els) => els.map((e) => e.className + ' :: ' + e.innerText.replace(/\s+/g, ' ').slice(0, 200))),
    errors,
  };
  await page.close();
}
console.log(JSON.stringify(out, null, 1));
await browser.close();
