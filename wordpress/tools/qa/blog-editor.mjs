/**
 * QA (module "blog"): the block editor.
 *   WP_BASE=http://localhost:8914 node wordpress/tools/qa/blog-editor.mjs <outDir> <tag> <postId> [landingPageId]
 * - opens the article in the editor, checks every block is valid, measures
 *   the typography of the canvas (title, paragraph, h2, h3, quote, caption,
 *   list) and the same elements on the public article, prints both side by
 *   side and screenshots the canvas;
 * - lists what the side panel offers (colour palette, font sizes, custom
 *   colour / size pickers) for a paragraph;
 * - screenshots a landing page in the editor (sections must stay edge to edge).
 */
import fs from 'node:fs';
import path from 'node:path';
import { launch, login, closeWelcome, BASE } from './wp-login.mjs';

const [out = '.', tag = 'run', postId, landingId] = process.argv.slice(2);
fs.mkdirSync(out, { recursive: true });

const PROBE = `(() => {
  const pick = (el) => {
    if (!el) return null;
    const cs = getComputedStyle(el);
    return [cs.fontFamily.split(',')[0].replace(/"/g, ''), cs.fontSize, cs.fontWeight, cs.lineHeight, cs.color, cs.letterSpacing].join(' | ');
  };
  const root = document.querySelector('.is-root-container') || document.querySelector('.mats-prose');
  const title = document.querySelector('.editor-post-title') || document.querySelector('.post-hero__title');
  return {
    title: pick(title),
    p: pick(root.querySelector(':scope > p')),
    h2: pick(root.querySelector(':scope > h2')),
    h3: pick(root.querySelector(':scope > h3')),
    li: pick(root.querySelector('ol > li')),
    quote: pick(root.querySelector('.wp-block-quote p')),
    cite: pick(root.querySelector('.wp-block-quote cite')),
    caption: pick(root.querySelector('.wp-block-image figcaption')),
    th: pick(root.querySelector('.wp-block-table th')),
    button: pick(root.querySelector('.wp-block-button__link')),
    columnWidth: Math.round(root.querySelector(':scope > p').getBoundingClientRect().width),
  };
})()`;

const browser = await launch();
const page = await login(browser, 'admin', 'admin-local-pass', { width: 1440, height: 1000 });
await page.goto(`${BASE}/wp-admin/post.php?post=${postId}&action=edit`);
await page.waitForSelector('iframe[name="editor-canvas"]', { timeout: 60000 });
await closeWelcome(page);
const frame = page.frame({ name: 'editor-canvas' });
await frame.waitForSelector('.is-root-container > p', { timeout: 60000 });
await page.waitForTimeout(1500);
const invalid = await page.evaluate(() => {
  const walk = (blocks) => blocks.flatMap((b) => [b.isValid ? null : b.name, ...walk(b.innerBlocks)]).filter(Boolean);
  return walk(wp.data.select('core/block-editor').getBlocks());
});
console.log('invalid blocks:', invalid.length ? invalid : 'none');
const editor = await frame.evaluate(PROBE);
await page.screenshot({ path: path.join(out, `${tag}-editor-post.png`) });

/* side panel: what a paragraph offers */
await frame.click('.is-root-container > p');
await page.waitForTimeout(600);
const settings = await page.evaluate(() => {
  const s = wp.data.select('core/block-editor').getSettings();
  const f = s.__experimentalFeatures || {};
  /* what the UI offers: the theme's presets, plus core's only when its default* flag is on */
  const val = (o, def) => (o ? [...(o.theme || []), ...(def ? o.default || [] : [])].map((x) => x.slug || x.name) : []);
  return {
    palette: val(f.color && f.color.palette, f.color && f.color.defaultPalette),
    gradients: val(f.color && f.color.gradients, f.color && f.color.defaultGradients),
    duotone: val(f.color && f.color.duotone, f.color && f.color.defaultDuotone),
    customColor: f.color && f.color.custom,
    fontSizes: val(f.typography && f.typography.fontSizes, f.typography && f.typography.defaultFontSizes),
    customFontSize: f.typography && f.typography.customFontSize,
    fontFamilies: val(f.typography && f.typography.fontFamilies, true),
    dropCap: f.typography && f.typography.dropCap,
    lineHeight: f.typography && f.typography.lineHeight,
    letterSpacing: f.typography && f.typography.letterSpacing,
    textTransform: f.typography && f.typography.textTransform,
    padding: f.spacing && f.spacing.padding,
    margin: f.spacing && f.spacing.margin,
    border: f.border,
    shadow: val(f.shadow && f.shadow.presets, f.shadow && f.shadow.defaultPresets),
    blockTemplates: !!(s.supportsTemplateMode),
    layout: f.layout,
  };
});
console.log('editor settings:', JSON.stringify(settings));
const stylesTab = page.locator('.block-editor-block-inspector [role="tab"]', { hasText: /Styles|Estilos/ });
if (await stylesTab.count()) await stylesTab.first().click();
await page.waitForTimeout(500);
const colorBtn = page.locator('.block-editor-block-inspector button', { hasText: /^Text$|^Texto$/ });
if (await colorBtn.count()) await colorBtn.first().click();
await page.waitForTimeout(500);
await page.screenshot({ path: path.join(out, `${tag}-editor-post-panel.png`) });

/* the public article, same probe */
const pub = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
await pub.goto(`${BASE}/?p=${postId}`, { waitUntil: 'networkidle' });
const site = await pub.evaluate(PROBE);
for (const k of Object.keys(editor)) {
  const same = String(editor[k]) === String(site[k]) ? 'same' : 'DIFF';
  console.log(`${k.padEnd(12)} ${same}\n   editor: ${editor[k]}\n   site:   ${site[k]}`);
}

if (landingId) {
  await page.goto(`${BASE}/wp-admin/post.php?post=${landingId}&action=edit`);
  await page.waitForSelector('iframe[name="editor-canvas"]', { timeout: 60000 });
  const lf = page.frame({ name: 'editor-canvas' });
  await lf.waitForSelector('.mats-preview__html section', { timeout: 60000 });
  await page.waitForTimeout(2500);
  const boxes = await lf.evaluate(() => [...document.querySelectorAll('.is-root-container > .mats-preview')].map((el) => {
    const r = el.getBoundingClientRect();
    const cs = getComputedStyle(el);
    return [Math.round(r.x), Math.round(r.width), cs.marginTop, cs.marginBottom, cs.maxWidth].join(' ');
  }));
  console.log('landing sections (x width mt mb max):', boxes);
  await page.screenshot({ path: path.join(out, `${tag}-editor-landing.png`) });
}
await browser.close();
