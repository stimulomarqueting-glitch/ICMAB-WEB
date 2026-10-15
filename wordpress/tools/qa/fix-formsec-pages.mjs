/**
 * QA (formsec): regression check of the wpforms.css change and the editor.
 *
 *   git show HEAD:wordpress/matsurfer/assets/css/wpforms.css > /tmp/…/wpforms-before.css
 *   WP_BASE=http://localhost:8983 BASELINE_CSS=/tmp/…/wpforms-before.css OUT=<dir> \
 *     node wordpress/tools/qa/fix-formsec-pages.mjs        (run from the repo root)
 *
 *  - A/B: every page below, full height, at 1440 and 390 px, once with the
 *    current wpforms.css and once with BASELINE_CSS served in its place
 *    (same server, same database: the stylesheet is the only difference)
 *    → the screenshots of the pages with the contact form must be identical
 *    (pixels compared in the browser); the other pages must not load it.
 *  - Editor: the EN and ES contact pages open in the block editor, the
 *    contact section preview shows the form card, no JavaScript errors.
 */
import { launch, login, closeWelcome } from './wp-login.mjs';
import fs from 'node:fs';

const BASE = process.env.WP_BASE || 'http://localhost:8983';
const BASELINE = process.env.BASELINE_CSS;
const OUT = process.env.OUT || '';
if (!BASELINE || !fs.existsSync(BASELINE)) {
  console.error('BASELINE_CSS (the stylesheet before the change) is required');
  process.exit(2);
}
if (OUT) fs.mkdirSync(OUT, { recursive: true });
const baselineCss = fs.readFileSync(BASELINE);

let failures = 0;
const check = (ok, label, detail = '') => {
  if (!ok) failures++;
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}${detail ? '  ' + detail : ''}`);
};

const PAGES = ['/contact/', '/es/contacto/', '/', '/es/', '/product/', '/es/producto/'];

async function shot(browser, url, width, baseline) {
  const ctx = await browser.newContext({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
  const page = await ctx.newPage();
  let swapped = false;
  if (baseline) {
    await page.route('**/assets/css/wpforms.css*', (route) => {
      swapped = true;
      return route.fulfill({ status: 200, contentType: 'text/css', body: baselineCss });
    });
  }
  const res = await page.goto(BASE + url, { waitUntil: 'networkidle' });
  await page.evaluate(() => document.fonts && document.fonts.ready);
  /* bring every lazy image / reveal in, then back to the top */
  await page.evaluate(async () => {
    for (let y = 0; y < document.body.scrollHeight; y += 600) {
      window.scrollTo(0, y);
      await new Promise((r) => setTimeout(r, 40));
    }
    window.scrollTo(0, 0);
  });
  await page.waitForTimeout(500);
  const loadsCss = await page.evaluate(() => !!document.querySelector('link[href*="assets/css/wpforms.css"]'));
  const png = await page.screenshot({ fullPage: true, animations: 'disabled' });
  await ctx.close();
  return { png, status: res.status(), loadsCss, swapped };
}

async function samePixels(browser, a, b) {
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  const r = await page.evaluate(
    async ([a64, b64]) => {
      const load = (s) => new Promise((ok) => { const i = new Image(); i.onload = () => ok(i); i.src = 'data:image/png;base64,' + s; });
      const [ia, ib] = await Promise.all([load(a64), load(b64)]);
      if (ia.width !== ib.width || ia.height !== ib.height) return { same: false, why: `size ${ia.width}x${ia.height} vs ${ib.width}x${ib.height}` };
      const data = (img) => { const c = document.createElement('canvas'); c.width = img.width; c.height = img.height; const x = c.getContext('2d'); x.drawImage(img, 0, 0); return x.getImageData(0, 0, img.width, img.height).data; };
      const da = data(ia), db = data(ib);
      let diff = 0;
      for (let i = 0; i < da.length; i += 4) if (da[i] !== db[i] || da[i + 1] !== db[i + 1] || da[i + 2] !== db[i + 2]) diff++;
      return { same: diff === 0, why: `${diff} px differ` };
    },
    [a.toString('base64'), b.toString('base64')]
  );
  await ctx.close();
  return r;
}

const browser = await launch();
try {
  for (const width of [1440, 390]) {
    for (const url of PAGES) {
      const now = await shot(browser, url, width, false);
      const before = await shot(browser, url, width, true);
      const tag = `${url} ${width}px`;
      if (OUT) {
        const name = url.replace(/\//g, '_') || '_';
        fs.writeFileSync(`${OUT}/page${name}-${width}-now.png`, now.png);
        fs.writeFileSync(`${OUT}/page${name}-${width}-before.png`, before.png);
      }
      if (!now.loadsCss) {
        /* pages without the contact form never load the stylesheet (their
           animated hero dots make two screenshots differ anyway) */
        check(now.status === 200 && !before.swapped, `${tag} does not load wpforms.css (cannot be affected)`);
        continue;
      }
      const cmp = now.png.equals(before.png) ? { same: true, why: 'identical bytes' } : await samePixels(browser, now.png, before.png);
      check(now.status === 200 && before.swapped && cmp.same, `${tag} looks the same with the old and the new wpforms.css`, `${cmp.why}${before.swapped ? ' (stylesheet swapped)' : ' (NOT swapped)'}`);
    }
  }

  /* editor */
  const page = await login(browser);
  const errors = [];
  page.on('pageerror', (e) => errors.push(String(e)));
  page.on('console', (m) => { if (m.type() === 'error' && !/Failed to load resource|ERR_|net::/.test(m.text())) errors.push(m.text()); });
  const ids = await page.evaluate(async (base) => {
    const r = await fetch(`${base}/wp-json/wp/v2/pages?per_page=100&context=edit&_fields=id,link`, { headers: { 'X-WP-Nonce': window.wpApiSettings?.nonce || '' } }).catch(() => null);
    return r && r.ok ? r.json() : [];
  }, BASE);
  for (const [lang, path] of [['en', '/contact/'], ['es', '/es/contacto/']]) {
    const found = ids.find((p) => new URL(p.link).pathname === path);
    let id = found?.id;
    if (!id) {
      const front = await (await fetch(BASE + path)).text();
      id = Number((front.match(/page-id-(\d+)/) || [])[1]);
    }
    await page.goto(`${BASE}/wp-admin/post.php?post=${id}&action=edit`);
    await closeWelcome(page);
    const frame = page.frameLocator('iframe[name="editor-canvas"]');
    const card = frame.locator('.contact .lf__card').first();
    await card.waitFor({ timeout: 30000 }).catch(() => {});
    const visible = await card.isVisible().catch(() => false);
    check(visible, `editor ${lang}: contact page opens and the form card preview renders (post ${id})`);
    if (OUT && visible) await card.screenshot({ path: `${OUT}/editor-${lang}-card.png` }).catch(() => {});
  }
  check(errors.length === 0, 'editor: no JavaScript errors', errors.slice(0, 3).join(' | '));
  await page.context().close();
} finally {
  await browser.close();
}

console.log(failures ? `${failures} FAILED` : 'ALL PASS');
process.exit(failures ? 1 : 0);
