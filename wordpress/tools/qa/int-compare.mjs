/**
 * Integration parity: full-page screenshots of WordPress vs the Astro build
 * for every page, EN + ES, at the given width. Reduced motion → reveal/rise
 * content visible and canvases still.
 *   node wordpress/tools/qa/int-compare.mjs <wpBase> <outDir> <width>
 */
import { launch } from './wp-login.mjs';
import { mkdirSync } from 'node:fs';
const [wp = 'http://localhost:8950', out = '/tmp/int', width = '1440'] = process.argv.slice(2);
const astro = 'http://127.0.0.1:4321';
const pages = [
  ['home', '/', '/'], ['product', '/product/', '/product/'], ['applications', '/applications/', '/applications/'],
  ['research', '/research/', '/research/'], ['team', '/team/', '/team/'], ['contact', '/contact/', '/contact/'],
  ['es-home', '/es/', '/es/'], ['es-product', '/es/producto/', '/es/product/'], ['es-applications', '/es/aplicaciones/', '/es/applications/'],
  ['es-research', '/es/casos-de-estudio/', '/es/research/'], ['es-team', '/es/equipo/', '/es/team/'], ['es-contact', '/es/contacto/', '/es/contact/'],
];
mkdirSync(out, { recursive: true });
const browser = await launch();
const ctx = await browser.newContext({ viewport: { width: Number(width), height: 900 }, reducedMotion: 'reduce' });
const page = await ctx.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(page.url() + ' ' + e.message));
const only = process.env.ONLY ? process.env.ONLY.split(',') : null;
for (const [name, wpPath, astroPath] of pages) {
  if (only && !only.includes(name)) continue;
  for (const [site, base, path] of [['wp', wp, wpPath], ['astro', astro, astroPath]]) {
    await page.goto(base + path, { waitUntil: 'networkidle' });
    await page.addStyleTag({ content: '#wpadminbar{display:none!important} .reveal{opacity:1!important;transform:none!important}' });
    await page.waitForTimeout(300);
    const h = await page.evaluate(() => document.documentElement.scrollHeight);
    await page.screenshot({ path: `${out}/${name}-${width}-${site}.png`, fullPage: true });
    console.log(name, site, width, 'height', h);
  }
}
console.log(errors.length ? 'JS errors:\n' + errors.join('\n') : 'no JS errors');
await browser.close();
