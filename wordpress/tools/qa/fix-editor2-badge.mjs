/**
 * QA (fix "badge-covers-required-warning"), as the Editor: when a section
 * shows the red «falta un texto obligatorio» warning, the section's name
 * badge (top left, shown when the section is selected or hovered) does not
 * cover it. Checked on the EN and ES product pages for three sections
 * (page hero H1, FAQ question, final call to action), selected and not
 * selected. Sections without a warning keep their preview at the top of
 * the block (nothing moves). Nothing is saved (saving is locked anyway).
 *
 *   WP_BASE=http://localhost:8985 [SHOTS=/dir] node wordpress/tools/qa/fix-editor2-badge.mjs
 * OLD_JS=/path/to/old/blocks.js serves that file instead of the theme's one
 * (to reproduce the bug with the previous code).
 */
import fs from 'node:fs';
import { launch, login, closeWelcome, BASE } from './wp-login.mjs';

const SHOTS = process.env.SHOTS || '';
const PAGES = (process.env.PAGES || '10 11').split(' ').map(Number);
const user = process.env.WP_USER || 'marta';
const pass = process.env.WP_PASS || 'Editor-pass-2026';

/* block name → how to empty its required text */
const CASES = {
  'matsurfer/page-hero': (a) => ({ title: '' }),
  'matsurfer/faq': (a) => ({ items: a.items.map((it, i) => (i === 0 ? Object.assign({}, it, { q: '' }) : it)) }),
  'matsurfer/page-cta': (a) => ({ title: '', button: Object.assign({}, a.button, { label: '' }) }),
};

const browser = await launch();
const page = await login(browser, user, pass);
page.on('dialog', (d) => d.accept());
if (process.env.OLD_JS) {
  const body = fs.readFileSync(process.env.OLD_JS, 'utf8');
  await page.route('**/assets/js/editor/blocks.js*', (route) => route.fulfill({ status: 200, contentType: 'application/javascript', body }));
}
let fails = 0;
const check = (label, ok, extra = '') => {
  if (!ok) fails++;
  console.log((ok ? 'ok   ' : 'FAIL ') + label + (extra ? ' :: ' + extra : ''));
};
const frame = () => page.frameLocator('iframe[name="editor-canvas"]');

for (const id of PAGES) {
  await page.goto(`${BASE}/wp-admin/post.php?post=${id}&action=edit`);
  await page.waitForFunction(() => window.wp && wp.data && wp.data.select('core/block-editor').getBlocks().length > 3, null, { timeout: 60000 });
  await closeWelcome(page);
  const take = page.locator('.components-modal__frame button:has-text("Take over")');
  if (await take.count()) await take.click();
  await frame().locator('.mats-preview__html section, .mats-preview__html > *').first().waitFor({ timeout: 60000 });
  await page.waitForTimeout(1500);

  /* without warnings, every preview starts at the top of its block */
  const tops = await frame().locator('body').evaluate((b) =>
    [...b.querySelectorAll('.mats-preview')].map((m) => Math.round(m.querySelector('.mats-preview__html').getBoundingClientRect().top - m.getBoundingClientRect().top))
  );
  check(`page ${id}: previews start at the top of their block (no warning)`, tops.every((t) => t === 0), tops.join(','));

  for (const [name, change] of Object.entries(CASES)) {
    const src = change.toString();
    await page.evaluate(([n, s]) => {
      const fn = new Function('return ' + s)();
      const b = wp.data.select('core/block-editor').getBlocks().find((x) => x.name === n);
      wp.data.dispatch('core/block-editor').updateBlockAttributes(b.clientId, fn(b.attributes));
    }, [name, src]);
    await page.waitForTimeout(1200);
    for (const selected of [true, false]) {
      await page.evaluate(([n, sel]) => {
        const b = wp.data.select('core/block-editor').getBlocks().find((x) => x.name === n);
        if (sel) wp.data.dispatch('core/block-editor').selectBlock(b.clientId);
        else wp.data.dispatch('core/block-editor').clearSelectedBlock();
      }, [name, selected]);
      await page.waitForTimeout(500);
      const block = frame().locator(`[data-type="${name}"]`).first();
      await block.locator('.mats-required').scrollIntoViewIfNeeded();
      if (!selected) await block.hover({ position: { x: 300, y: 20 } }); // the badge also shows on hover
      await page.waitForTimeout(300);
      const r = await block.evaluate((el) => {
        const box = (n) => {
          const x = n.getBoundingClientRect();
          return [Math.round(x.left), Math.round(x.top), Math.round(x.right), Math.round(x.bottom)];
        };
        const badge = el.querySelector('.mats-preview__badge');
        const warn = el.querySelector('.mats-required');
        const b = box(badge);
        const w = box(warn);
        return {
          badge: b,
          warn: w,
          badgeVisible: parseFloat(getComputedStyle(badge).opacity) > 0.5,
          overlap: !(b[2] <= w[0] || b[0] >= w[2] || b[3] <= w[1] || b[1] >= w[3]),
          inside: w[1] >= box(el)[1],
        };
      });
      check(
        `page ${id} ${name.replace('matsurfer/', '')} ${selected ? 'selected' : 'hovered'}: badge shown, warning not covered, warning inside the block`,
        r.badgeVisible && !r.overlap && r.inside,
        JSON.stringify(r)
      );
      if (SHOTS && selected) {
        const bb = await block.boundingBox();
        await page.screenshot({ path: `${SHOTS}/badge-${id}-${name.replace('matsurfer/', '')}.png`, clip: { x: bb.x, y: Math.max(bb.y - 20, 0), width: bb.width, height: 260 } });
      }
    }
  }
}

console.log(fails ? 'RESULT FAIL ' + fails : 'RESULT ok');
await browser.close();
process.exit(fails ? 1 : 0);
