/**
 * QA (fixes "pages-notice-polylang-off" and "pages-screen-jargon"): the
 * notice on Pages (edit.php?post_type=page), as the Editor and as the
 * administrator, with Polylang on and off.
 *   - Polylang on: the notice says each page exists in English and Spanish
 *     (flag columns); Polylang off: it does not, it points to the warning
 *     about the Spanish site instead (which is shown above it).
 *   - Nobody sees internal names (_mats_role, mats_page_roles, meta,
 *     option…) on the screen; the administrator gets a plain hint with a
 *     link to the guide; the row label reads «Página principal: …».
 * Polylang is switched back on at the end.
 *
 *   WP_BASE=http://localhost:8985 [SHOTS=/dir] node wordpress/tools/qa/fix-editor2-pages.mjs
 */
import { execSync } from 'node:child_process';
import { launch, login, BASE } from './wp-login.mjs';

const PORT = new URL(BASE).port;
const SHOTS = process.env.SHOTS || '';
const HELPER = new URL('./fix-editor2-polylang.php', import.meta.url).pathname;
const polylang = (state) => execSync(`MATS_WP_PORT=${PORT} php /home/user/wpsite-cli.php ${HELPER} ${state}`).toString().trim();
const JARGON = /_mats_role|mats_page_roles|\bmeta\b|opción mats|campo _/;
const BILINGUAL = /Cada una existe en inglés y en español/;

const browser = await launch();
let fails = 0;
const check = (label, ok, extra = '') => {
  if (!ok) fails++;
  console.log((ok ? 'ok   ' : 'FAIL ') + label + (extra ? ' :: ' + extra : ''));
};

async function pagesScreen(user, pass, tag) {
  const page = await login(browser, user, pass);
  await page.goto(`${BASE}/wp-admin/edit.php?post_type=page`);
  await page.waitForSelector('.mats-pages-notice');
  if (SHOTS) await page.screenshot({ path: `${SHOTS}/pages-${tag}.png` });
  const out = await page.evaluate(() => {
    const notice = document.querySelector('.mats-pages-notice');
    const pll = document.querySelector('.mats-pll-off-notice');
    const all = [...document.querySelectorAll('.wrap .notice')];
    return {
      notice: notice.innerText.trim(),
      guideLink: (notice.querySelector('a[href*="mats-guide"]') || {}).href || '',
      pllOff: !!pll,
      pllAbove: !!pll && all.indexOf(pll) > -1 && all.indexOf(pll) < all.indexOf(notice),
      screenText: document.querySelector('#wpbody-content').innerText,
      states: [...document.querySelectorAll('#the-list .post-state')].map((s) => s.textContent.trim()),
      flags: document.querySelectorAll('th[class*="column-language_"]').length,
    };
  });
  await page.context().close();
  return out;
}

console.log(polylang('on'));
for (const [user, pass, who] of [
  [process.env.WP_USER || 'marta', process.env.WP_PASS || 'Editor-pass-2026', 'editor'],
  ['admin', 'admin-local-pass', 'admin'],
]) {
  const on = await pagesScreen(user, pass, `${who}-pll-on`);
  check(`${who}, Polylang on: notice says English + Spanish (flag columns: ${on.flags})`, BILINGUAL.test(on.notice) && on.flags > 0, on.notice.slice(0, 80));
  check(`${who}, Polylang on: no internal names on the screen`, !JARGON.test(on.screenText), (on.screenText.match(JARGON) || [''])[0]);
  check(`${who}, Polylang on: rows say «Página principal: …»`, on.states.some((s) => /^Página principal: /.test(s)), on.states.slice(0, 3).join(' | '));
  if (who === 'admin') {
    check('admin: plain hint about removing a main page + link to the guide', /quitarle antes su papel de página principal/.test(on.notice) && /#administradores$/.test(on.guideLink), on.guideLink);
  } else {
    check('editor: no administrator hint', !/Administradores/.test(on.notice));
  }
}

console.log(polylang('off'));
try {
  for (const [user, pass, who] of [
    [process.env.WP_USER || 'marta', process.env.WP_PASS || 'Editor-pass-2026', 'editor'],
    ['admin', 'admin-local-pass', 'admin'],
  ]) {
    const off = await pagesScreen(user, pass, `${who}-pll-off`);
    check(`${who}, Polylang off: no «Cada una existe en inglés y en español»`, !BILINGUAL.test(off.notice), off.notice.slice(0, 160));
    check(`${who}, Polylang off: points to the Spanish-site warning, shown above`, /aviso de arriba/.test(off.notice) && off.pllOff && off.pllAbove, JSON.stringify({ pllOff: off.pllOff, above: off.pllAbove }));
    check(`${who}, Polylang off: no internal names on the screen`, !JARGON.test(off.screenText), (off.screenText.match(JARGON) || [''])[0]);
  }
} finally {
  console.log(polylang('on'));
}

console.log(fails ? 'RESULT FAIL ' + fails : 'RESULT ok');
await browser.close();
process.exit(fails ? 1 : 0);
