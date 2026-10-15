/**
 * QA (group "hero"): walks every element of the hero section in Astro and WP
 * (same DOM order) and compares computed styles; prints the differences.
 *   node wordpress/tools/qa/hero-styles.mjs [en|es]
 */
import { launch } from './wp-login.mjs';
const WP = process.env.WP_BASE || 'http://localhost:8901';
const lang = process.argv[2] || 'en';
const urls = lang === 'es' ? ['http://127.0.0.1:4321/es/', `${WP}/es/inicio/`] : ['http://127.0.0.1:4321/', `${WP}/`];
const PROPS = ['display', 'position', 'top', 'left', 'right', 'bottom', 'width', 'height', 'margin', 'padding', 'gap', 'gridTemplateColumns', 'alignItems', 'justifyContent', 'textAlign',
  'fontFamily', 'fontSize', 'fontWeight', 'lineHeight', 'letterSpacing', 'textTransform', 'color', 'backgroundImage', 'backgroundColor', 'border', 'borderRadius', 'boxShadow', 'opacity',
  'transform', 'transformStyle', 'perspective', 'filter', 'backdropFilter', 'mixBlendMode', 'maskImage', 'overflow', 'zIndex', 'isolation', 'objectFit', 'objectPosition', 'aspectRatio',
  'animationName', 'animationDuration', 'animationDelay', 'animationTimingFunction', 'animationIterationCount', 'transition', 'willChange', 'pointerEvents', 'maxWidth', 'minHeight', 'verticalAlign', 'whiteSpace'];
const browser = await launch();
for (const w of [1440, 820, 390]) {
  const res = [];
  for (const url of urls) {
    const ctx = await browser.newContext({ viewport: { width: w, height: 1000 } });
    const page = await ctx.newPage();
    await page.goto(url, { waitUntil: 'networkidle' });
    await page.evaluate(() => document.fonts.ready);
    await page.waitForTimeout(2500);
    res.push(await page.evaluate((PROPS) => {
      const root = document.querySelector('section.field');
      const els = [root, ...root.querySelectorAll('*')];
      const pick = (el, pseudo) => { const cs = getComputedStyle(el, pseudo); const o = {}; for (const p of PROPS) o[p] = cs[p]; return o; };
      const out = [];
      for (const el of els) {
        const id = el.tagName.toLowerCase() + (el.getAttribute('class') ? '.' + el.getAttribute('class').trim().replace(/\s+/g, '.') : '');
        out.push([id, pick(el)]);
        if (el.tagName === 'LI') out.push([id + '::after', pick(el, '::after')]);
      }
      return out;
    }, PROPS));
    await ctx.close();
  }
  const [a, b] = res;
  let diffs = 0;
  if (a.length !== b.length) console.log(w, 'element count differs', a.length, b.length);
  for (let i = 0; i < Math.min(a.length, b.length); i++) {
    if (a[i][0] !== b[i][0]) { console.log(w, 'tag/class differs at', i, a[i][0], '<>', b[i][0]); diffs++; continue; }
    for (const p of PROPS) {
      if (a[i][1][p] !== b[i][1][p]) {
        diffs++;
        if (diffs < 40) console.log(w, a[i][0], p, JSON.stringify(a[i][1][p]).slice(0, 90), '<>', JSON.stringify(b[i][1][p]).slice(0, 90));
      }
    }
  }
  console.log(`== ${lang} ${w}: ${a.length} elements compared, ${diffs} differences`);
}
await browser.close();
