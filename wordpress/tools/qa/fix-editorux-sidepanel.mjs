/**
 * Side panel width check (fix "sidepanel-list-overflow-hides-controls").
 *
 * For every MatSurfer section of the given pages: selects it, opens every
 * side-panel group, then opens each list item one by one, and measures
 * whether anything sticks out of the 280px sidebar (horizontal scroll) and
 * whether every ▲ Subir / ▼ Bajar / 🗑 Quitar button is fully visible.
 *
 *   WP_BASE=http://localhost:8981 [WP_USER=marta WP_PASS=…] [SHOTS=/dir] \
 *     node wordpress/tools/qa/fix-editorux-sidepanel.mjs 7 8 10 11 …
 *
 * Prints one line per section and "RESULT ok" / "RESULT FAIL n".
 */
import { launch, login, closeWelcome, BASE } from './wp-login.mjs';

const ids = process.argv.slice(2).length ? process.argv.slice(2) : ['7', '8', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19'];
const user = process.env.WP_USER || 'marta';
const pass = process.env.WP_PASS || 'Editor-pass-2026';
const shots = process.env.SHOTS || '';

const browser = await launch();
const page = await login(browser, user, pass);
const errors = [];
page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));
page.on('dialog', (d) => d.dismiss());

const measure = () =>
  page.evaluate(() => {
    const sc = document.querySelector('.interface-complementary-area');
    if (!sc) return null;
    const r = sc.getBoundingClientRect();
    const right = r.left + sc.clientWidth; // excludes a vertical scrollbar
    const btns = [...sc.querySelectorAll('.mats-repeater__head button:not(.mats-repeater__toggle)')];
    const off = btns.filter((b) => {
      const br = b.getBoundingClientRect();
      return br.width === 0 || br.right > right + 0.5 || br.left < r.left - 0.5;
    });
    const wide = [...sc.querySelectorAll('.block-editor-block-inspector *')]
      .filter((n) => {
        const nr = n.getBoundingClientRect();
        return nr.width > 0 && nr.right > right + 0.5;
      })
      .slice(0, 3)
      .map((n) => n.tagName.toLowerCase() + (n.className && typeof n.className === 'string' ? '.' + n.className.trim().split(/\s+/)[0] : ''));
    return { sw: sc.scrollWidth, cw: sc.clientWidth, buttons: btns.length, off: off.length, wide };
  });

let fails = 0;
for (const id of ids) {
  await page.goto(`${BASE}/wp-admin/post.php?post=${id}&action=edit`);
  await page.waitForSelector('iframe[name="editor-canvas"]', { timeout: 60000 });
  await closeWelcome(page);
  await page.waitForFunction(() => window.wp && wp.data.select('core/block-editor').getBlocks().length > 0, null, { timeout: 60000 }).catch(() => {});
  await page.waitForTimeout(1200);
  const blocks = await page.evaluate(() => wp.data.select('core/block-editor').getBlocks().map((b) => [b.clientId, b.name]));
  for (const [cid, name] of blocks) {
    if (!name.startsWith('matsurfer/')) continue;
    await page.evaluate((c) => wp.data.dispatch('core/block-editor').selectBlock(c), cid);
    await page.waitForTimeout(500);
    const toggles = page.locator('.block-editor-block-inspector .components-panel__body:not(.is-opened) > h2 .components-panel__body-toggle');
    for (let k = await toggles.count(); k > 0; k--) {
      await toggles.first().click({ timeout: 2000 }).catch(() => {});
      await page.waitForTimeout(80);
    }
    let worst = await measure();
    /* open each list item in turn (its fields: selects, text areas…) */
    const items = page.locator('.block-editor-block-inspector .mats-repeater__toggle');
    const n = await items.count();
    for (let k = 0; k < n; k++) {
      await items.nth(k).click({ timeout: 2000 }).catch(() => {});
      await page.waitForTimeout(120);
      const m = await measure();
      if (m && (m.sw > worst.sw || m.off > worst.off)) worst = m;
    }
    const bad = !worst || worst.sw > worst.cw || worst.off > 0;
    if (bad) fails++;
    console.log(
      String(id).padEnd(3),
      name.replace('matsurfer/', '').padEnd(24),
      'scroll', worst ? worst.sw + '/' + worst.cw : '-',
      '| item buttons', worst ? worst.buttons : '-',
      'hidden', worst ? worst.off : '-',
      bad ? 'FAIL ' + (worst ? worst.wide.join(' ') : '') : 'ok'
    );
    if (shots && (name === 'matsurfer/faq' || name === 'matsurfer/benefits')) {
      await items.first().click({ timeout: 2000 }).catch(() => {});
      await page.waitForTimeout(200);
      await page.screenshot({ path: `${shots}/sidepanel-${id}-${name.replace('matsurfer/', '')}.png` });
    }
  }
}
console.log(errors.length ? errors.join('\n') : 'no JS errors');
console.log(fails ? 'RESULT FAIL ' + fails : 'RESULT ok');
await browser.close();
