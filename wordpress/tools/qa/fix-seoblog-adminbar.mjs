/**
 * QA (fix bucket "seoblog"): the sticky application pills (applications-list)
 * must stay below the sticky header when the admin bar is shown.
 *   WP_BASE=http://localhost:8982 node wordpress/tools/qa/fix-seoblog-adminbar.mjs [outDir]
 * Logs in as admin, opens /applications/ and /es/aplicaciones/ at 1440, 782,
 * 700, 600 and 390, scrolls to y=1500 and measures the admin bar, the header
 * and .apps-nav. FAIL when the header covers the nav (nav.top < header.bottom
 * - 1) or the nav is not visible; also checks logged out (no admin bar).
 */
import fs from 'node:fs';
import path from 'node:path';
import { launch, login, BASE } from './wp-login.mjs';

const out = process.argv[2] || '';
if (out) fs.mkdirSync(out, { recursive: true });

const browser = await launch();
let fails = 0;
for (const logged of [true, false]) {
  for (const w of [1440, 782, 700, 600, 390]) {
    let page;
    if (logged) {
      page = await login(browser, 'admin', 'admin-local-pass', { width: w, height: 900 });
    } else {
      page = await (await browser.newContext({ viewport: { width: w, height: 900 } })).newPage();
    }
    for (const url of ['/applications/', '/es/aplicaciones/']) {
      await page.goto(BASE + url, { waitUntil: 'load' });
      await page.evaluate(() => window.scrollTo(0, 1500));
      await page.waitForTimeout(400);
      const m = await page.evaluate(() => {
        const r = (sel) => {
          const el = document.querySelector(sel);
          if (!el) return null;
          const b = el.getBoundingClientRect();
          return { top: Math.round(b.top), bottom: Math.round(b.bottom), pos: getComputedStyle(el).position };
        };
        return { bar: r('#wpadminbar'), header: r('.header'), nav: r('.apps-nav') };
      });
      const covered = m.nav && m.header && m.nav.top < m.header.bottom - 1;
      const ok = m.nav && !covered && m.nav.bottom > 0;
      if (!ok) fails++;
      console.log(
        `${ok ? 'PASS' : 'FAIL'} ${logged ? 'admin' : 'guest'} ${w} ${url}`,
        `bar ${m.bar ? `${m.bar.top}..${m.bar.bottom} ${m.bar.pos}` : '-'}`,
        `header ${m.header.top}..${m.header.bottom}`,
        `nav ${m.nav ? `${m.nav.top}..${m.nav.bottom}` : '-'}`
      );
      if (out && url === '/applications/') {
        await page.screenshot({ path: path.join(out, `apps-${logged ? 'admin' : 'guest'}-${w}.png`) });
      }
    }
    await page.context().close();
  }
}
await browser.close();
console.log(fails ? `${fails} FAIL` : 'all PASS');
process.exitCode = fails ? 1 : 0;
