/**
 * QA (module "forms"): contact section, WordPress vs the Astro reference.
 * Screenshots of the section at 1440 / 820 / 390 and a metric diff of the
 * form (positions relative to the section, sizes, key computed styles).
 *
 *   WP_BASE=http://localhost:8911 node wordpress/tools/qa/forms-shots.mjs <outDir> [query] [en|es]
 */
import { launch, BASE } from './wp-login.mjs';

const out = process.argv[2] || '/tmp';
const query = process.argv[3] || '';
const lang = process.argv[4] === 'es' ? 'es' : 'en';
const paths = lang === 'es' ? { astro: '/es/contact/', wp: '/es/contacto/' } : { astro: '/contact/', wp: '/contact/' };
const ASTRO = 'http://127.0.0.1:4321';

const sel = {
  astro: {
    card: '.lf__card',
    label1: 'label[for=lf-fullname]',
    f1: '#lf-fullname', f2: '#lf-email', f3: '#lf-org', f4: '#lf-role', f5: '#lf-interest', f6: '#lf-area',
    label6: 'label[for=lf-area]', f7: '#lf-message', f8: '#lf-consent', consentText: '.consent span', submit: '.lf__submit',
    title: '.contact__title', lead: '.contact .lead', steps: '.contact__steps', direct: '.contact__direct', directText: '.contact__direct-text',
  },
  wp: {
    card: '.lf__card',
    label1: '.wpforms-field[data-field-id="1"] > .wpforms-field-label, [id$="-field_1-container"] > .wpforms-field-label',
    f1: '[name="wpforms[fields][1]"]', f2: '[name="wpforms[fields][2]"]', f3: '[name="wpforms[fields][3]"]', f4: '[name="wpforms[fields][4]"]',
    f5: '[name="wpforms[fields][5]"]', f6: '[name="wpforms[fields][6]"]',
    label6: '[id$="-field_6-container"] > .wpforms-field-label', f7: '[name="wpforms[fields][7]"]', f8: '[name="wpforms[fields][8][]"]',
    consentText: '.wpforms-field-label-inline', submit: '.wpforms-submit',
    title: '.contact__title', lead: '.contact .lead', steps: '.contact__steps', direct: '.contact__direct', directText: '.contact__direct-text',
  },
};

async function measure(page, map) {
  return page.evaluate((map) => {
    const sec = document.querySelector('.contact-sec').getBoundingClientRect();
    const res = {};
    for (const [k, s] of Object.entries(map)) {
      const el = document.querySelector(s);
      if (!el) { res[k] = null; continue; }
      const b = el.getBoundingClientRect();
      const c = getComputedStyle(el);
      res[k] = {
        box: [b.x - sec.x, b.y - sec.y, b.width, b.height].map((n) => Math.round(n * 10) / 10),
        font: `${c.fontSize}/${c.lineHeight} ${c.fontWeight}`,
        color: c.color,
        bg: c.backgroundColor,
        border: `${c.borderTopWidth} ${c.borderTopColor} r${c.borderTopLeftRadius}`,
      };
    }
    res.sectionHeight = Math.round(sec.height);
    return res;
  }, map);
}

const browser = await launch();
const report = [];
for (const width of [1440, 820, 390]) {
  const ctx = await browser.newContext({ viewport: { width, height: 1100 }, reducedMotion: 'reduce' });
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  const results = {};
  for (const [name, url] of [['astro', `${ASTRO}${paths.astro}${query}`], ['wp', `${BASE}${paths.wp}${query}`]]) {
    /* twice: the second load has the fonts cached (a <select> keeps the
       height of its fallback font when the web font swaps in late) */
    await page.goto(url, { waitUntil: 'networkidle' });
    await page.goto(url, { waitUntil: 'networkidle' });
    await page.evaluate(() => document.fonts.ready);
    await page.waitForTimeout(300);
    const section = page.locator('.contact-sec');
    await section.screenshot({ path: `${out}/contact-${lang}-${name}-${width}.png` });
    results[name] = await measure(page, sel[name]);
  }
  const diffs = [];
  for (const k of Object.keys(sel.astro)) {
    const a = results.astro[k];
    const w = results.wp[k];
    if (!a || !w) { diffs.push(`${k}: missing (${!a ? 'astro' : 'wp'})`); continue; }
    const d = a.box.map((v, i) => Math.round((w.box[i] - v) * 10) / 10);
    const big = d.some((v) => Math.abs(v) > 1.5);
    const style = ['font', 'color', 'bg', 'border'].filter((p) => a[p] !== w[p]).map((p) => `${p}: ${a[p]} → ${w[p]}`);
    if (big || style.length) diffs.push(`${k}: Δbox ${JSON.stringify(d)}${style.length ? ' | ' + style.join(' | ') : ''}`);
  }
  diffs.push(`section height: astro ${results.astro.sectionHeight} wp ${results.wp.sectionHeight}`);
  report.push({ width, diffs, errors });
  await ctx.close();
}
await browser.close();
for (const r of report) {
  console.log(`\n== ${r.width}px`);
  r.diffs.forEach((d) => console.log('  ' + d));
  if (r.errors.length) console.log('  JS errors: ' + r.errors.join(' | '));
}
