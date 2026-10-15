/**
 * QA (formsec): the contact form's submit button against the Astro LeadForm.
 *
 *   WP_BASE=http://localhost:8983 [BASELINE_CSS=wpforms-before.css] [OUT=dir] \
 *     node wordpress/tools/qa/fix-formsec-focus.mjs        (run from the repo root)
 *
 * For EN /contact/ and ES /es/contacto/ at 1440 and 390 px:
 *  - keyboard focus (Tab from the consent checkbox) → 2px violet outline,
 *    offset 2px, identical to Astro (http://127.0.0.1:4321);
 *  - mouse click focus → no outline (as Astro);
 *  - with WPForms' own focus frame simulated (real WPForms "modern" styles
 *    draw a coral ring with button[type=submit]:focus:after) → no ::after;
 *  - disabled (while sending) → opacity 0.6, cursor wait (as Astro);
 *  - resting and hover styles unchanged against BASELINE_CSS (wpforms.css
 *    before the fix, e.g. from `git show HEAD:…`, served in place of the
 *    current one: every local site shares the theme files), and the
 *    resting button looks the same pixel for pixel.
 * Prints PASS/FAIL lines and exits 1 on any failure.
 */
import { launch } from './wp-login.mjs';
import fs from 'node:fs';

const WP = process.env.WP_BASE || 'http://localhost:8983';
const BASELINE = process.env.BASELINE_CSS ? fs.readFileSync(process.env.BASELINE_CSS) : null;
const ASTRO = process.env.ASTRO_BASE || 'http://127.0.0.1:4321';
const OUT = process.env.OUT || '';
if (OUT) fs.mkdirSync(OUT, { recursive: true });

const PAGES = [
  { lang: 'en', wp: '/contact/', astro: '/contact/' },
  { lang: 'es', wp: '/es/contacto/', astro: '/es/contact/' },
];
const PROPS = ['display', 'width', 'height', 'paddingTop', 'paddingLeft', 'fontSize', 'fontWeight', 'color', 'backgroundColor', 'backgroundImage', 'borderRadius', 'outlineStyle', 'cursor', 'transform', 'boxShadow', 'opacity'];

let failures = 0;
const check = (ok, label, detail = '') => {
  if (!ok) failures++;
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}${detail ? '  ' + detail : ''}`);
};

/* simulated real-WPForms focus frame (wpforms-full.css, modern markup) */
const WPFORMS_FOCUS_FRAME = `div.wpforms-container-full button[type=submit]:focus:after { content: ""; position: absolute; border: 2px solid var(--wpforms-button-background-color); border-radius: calc(var(--wpforms-button-border-radius) + 2px); top: -5px; right: -5px; bottom: -5px; left: -5px; }`;

const btnSel = (astro) => (astro ? '.lf__card .lf__submit' : '.lf__card button[type=submit]');

async function styles(page, sel, pseudo = null) {
  return page.evaluate(
    ({ sel, props, pseudo }) => {
      const el = document.querySelector(sel);
      if (!el) return null;
      const cs = getComputedStyle(el, pseudo);
      const out = {};
      for (const p of props) out[p] = cs[p];
      out.outline = `${cs.outlineStyle} ${cs.outlineWidth} ${cs.outlineColor} offset ${cs.outlineOffset}`;
      out.content = cs.content;
      out.focusVisible = el.matches(':focus-visible');
      out.active = document.activeElement === el;
      const r = el.getBoundingClientRect();
      out.rect = [r.x, r.y, r.width, r.height].map(Math.round);
      return out;
    },
    { sel, props: PROPS, pseudo }
  );
}

async function open(browser, url, width, baseline = false) {
  const ctx = await browser.newContext({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
  const page = await ctx.newPage();
  if (baseline) {
    await page.route('**/assets/css/wpforms.css*', (route) => route.fulfill({ status: 200, contentType: 'text/css', body: BASELINE }));
  }
  await page.goto(url, { waitUntil: 'networkidle' });
  await page.evaluate(() => document.fonts && document.fonts.ready);
  return { ctx, page };
}

/* keyboard focus on the submit button: from the control just before it */
async function keyboardFocus(page, astro) {
  await page.locator(astro ? '.lf__card .consent a' : '.lf__card input[type=checkbox]').first().focus();
  await page.keyboard.press('Tab');
  await page.waitForTimeout(250);
}

async function clip(page, sel, file) {
  if (!OUT) return;
  await page.locator(sel).first().scrollIntoViewIfNeeded();
  const r = await page.locator(sel).first().boundingBox();
  const x = Math.max(0, r.x - 10);
  await page.screenshot({ path: `${OUT}/${file}`, clip: { x, y: Math.max(0, r.y - 10), width: Math.min(page.viewportSize().width - x, r.width + 20), height: r.height + 20 } });
}

const browser = await launch();
for (const width of [1440, 390]) {
  for (const p of PAGES) {
    const tag = `${p.lang} ${width}px`;

    /* Astro reference */
    const a = await open(browser, ASTRO + p.astro, width);
    await keyboardFocus(a.page, true);
    const astroFocus = await styles(a.page, btnSel(true));
    await clip(a.page, btnSel(true), `focus-${p.lang}-${width}-astro.png`);
    await a.page.evaluate((sel) => { const b = document.querySelector(sel); b.disabled = true; }, btnSel(true));
    const astroDisabled = await styles(a.page, btnSel(true));
    await a.ctx.close();

    /* WordPress */
    const w = await open(browser, WP + p.wp, width);
    const rest = await styles(w.page, btnSel(false));
    await clip(w.page, btnSel(false), `rest-${p.lang}-${width}-wp.png`);
    await keyboardFocus(w.page, false);
    const focus = await styles(w.page, btnSel(false));
    await clip(w.page, btnSel(false), `focus-${p.lang}-${width}-wp.png`);
    check(focus.active && focus.focusVisible, `${tag} button has keyboard focus (:focus-visible)`);
    check(focus.outline === astroFocus.outline, `${tag} keyboard focus ring = Astro`, `wp="${focus.outline}" astro="${astroFocus.outline}"`);

    /* hover while focused keeps the ring (Astro: the global :focus-visible rule) */
    await w.page.hover(btnSel(false));
    await w.page.waitForTimeout(400);
    const focusHover = await styles(w.page, btnSel(false));
    check(focusHover.outline === astroFocus.outline, `${tag} ring stays while hovered`, focusHover.outline);

    /* WPForms' own focus frame must not show next to the theme's ring */
    await w.page.addStyleTag({ content: WPFORMS_FOCUS_FRAME });
    await keyboardFocus(w.page, false);
    const after = await styles(w.page, btnSel(false), '::after');
    check(after.content === 'none' || after.content === 'normal', `${tag} no WPForms ::after focus frame`, `content=${after.content}`);

    /* mouse press: focus without a ring (not :focus-visible), as Astro.
       (mouse down on the button, released elsewhere: no click, so the
       form is not validated or sent) */
    await w.page.evaluate(() => document.activeElement && document.activeElement.blur());
    const box = await w.page.locator(btnSel(false)).boundingBox();
    await w.page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
    await w.page.mouse.down();
    await w.page.mouse.move(5, 5);
    await w.page.mouse.up();
    await w.page.waitForTimeout(400);
    const clicked = await styles(w.page, btnSel(false));
    check(clicked.active && !clicked.focusVisible && clicked.outlineStyle === 'none', `${tag} no ring after a mouse press`, `${clicked.outline} focused=${clicked.active} fv=${clicked.focusVisible}`);

    /* disabled while sending */
    await w.page.evaluate((sel) => { const b = document.querySelector(sel); b.blur(); b.disabled = true; }, btnSel(false));
    const disabled = await styles(w.page, btnSel(false));
    check(disabled.cursor === astroDisabled.cursor && disabled.opacity === astroDisabled.opacity, `${tag} disabled = Astro`, `wp cursor=${disabled.cursor} opacity=${disabled.opacity} · astro cursor=${astroDisabled.cursor} opacity=${astroDisabled.opacity}`);
    await w.ctx.close();

    /* resting + hover unchanged against the site before the fix */
    if (BASELINE) {
      const r = await open(browser, WP + p.wp, width, true);
      const refRest = await styles(r.page, btnSel(false));
      await clip(r.page, btnSel(false), `rest-${p.lang}-${width}-ref.png`);
      await r.page.hover(btnSel(false));
      await r.page.waitForTimeout(400);
      const refHover = await styles(r.page, btnSel(false));
      await r.page.mouse.move(5, 5); // not hovered: the old base rule then wins
      await r.page.waitForTimeout(400);
      await keyboardFocus(r.page, false);
      const refFocus = await styles(r.page, btnSel(false));
      check(refFocus.focusVisible && refFocus.outlineStyle === 'none', `${tag} (control) the old stylesheet shows no ring: the bug this fix removes`, refFocus.outline);
      await r.ctx.close();

      const w2 = await open(browser, WP + p.wp, width);
      await w2.page.hover(btnSel(false));
      await w2.page.waitForTimeout(400);
      const hover = await styles(w2.page, btnSel(false));
      await w2.ctx.close();

      const diff = (x, y) => PROPS.filter((k) => x[k] !== y[k]).map((k) => `${k}: ${y[k]} → ${x[k]}`);
      const dr = diff(rest, refRest);
      check(dr.length === 0 && rest.rect.join() === refRest.rect.join(), `${tag} resting style unchanged`, dr.join(' | ') || rest.rect.join(','));
      const dh = diff(hover, refHover);
      check(dh.length === 0, `${tag} hover style unchanged`, dh.join(' | '));
      if (OUT) {
        const same = fs.readFileSync(`${OUT}/rest-${p.lang}-${width}-wp.png`).equals(fs.readFileSync(`${OUT}/rest-${p.lang}-${width}-ref.png`));
        check(same, `${tag} resting button screenshot identical (bytes)`);
      }
    }
  }
}
await browser.close();
console.log(failures ? `${failures} FAILED` : 'ALL PASS');
process.exit(failures ? 1 : 0);
