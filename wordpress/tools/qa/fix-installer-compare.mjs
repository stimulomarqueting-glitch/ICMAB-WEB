/**
 * QA (installer fix pass): the public site built by the wizard must look and
 * read exactly like the reference site. Full-page screenshots (reduced
 * motion) of every page EN + ES on both instances, compared pixel by pixel
 * with sharp, plus the visible text of each page.
 *
 *   node wordpress/tools/qa/fix-installer-compare.mjs <refBase> <testBase> <outDir> [width]
 */
import { launch } from './wp-login.mjs';
import { mkdirSync } from 'node:fs';
import sharp from 'sharp';

const [ref = 'http://localhost:8970', test = 'http://localhost:8984', out = '/tmp/fix-installer-compare', width = '1440'] = process.argv.slice(2);
const pages = ['/', '/product/', '/applications/', '/research/', '/team/', '/contact/', '/news/', '/es/', '/es/producto/', '/es/aplicaciones/', '/es/casos-de-estudio/', '/es/equipo/', '/es/contacto/', '/es/noticias/'];
mkdirSync(out, { recursive: true });
const browser = await launch();
const ctx = await browser.newContext({ viewport: { width: Number(width), height: 900 }, reducedMotion: 'reduce' });
const page = await ctx.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(page.url() + ' ' + e.message));
let failures = 0;

async function grab(base, path, file) {
  const res = await page.goto(base + path, { waitUntil: 'networkidle' });
  await page.addStyleTag({ content: '#wpadminbar{display:none!important} .reveal,.rise-in{opacity:1!important;transform:none!important} *{animation:none!important;transition:none!important}' });
  /* lazy images: scroll through the page and wait until every image has loaded */
  await page.evaluate(async () => {
    for (let y = 0; y < document.documentElement.scrollHeight; y += 600) {
      window.scrollTo(0, y);
      await new Promise((r) => setTimeout(r, 60));
    }
    window.scrollTo(0, 0);
    await Promise.all([...document.images].map((img) => (img.complete ? null : new Promise((r) => { img.onload = img.onerror = r; setTimeout(r, 5000); }))));
  });
  await page.waitForTimeout(400);
  const text = await page.evaluate(() => document.body.innerText.replace(/\s+/g, ' ').trim());
  await page.screenshot({ path: file, fullPage: true });
  return { status: res ? res.status() : 0, text };
}

for (const path of pages) {
  const name = path === '/' ? 'home' : path.replace(/^\/|\/$/g, '').replace(/\//g, '-');
  const a = await grab(ref, path, `${out}/${name}-ref.png`);
  const b = await grab(test, path, `${out}/${name}-test.png`);
  const [ia, ib] = await Promise.all([sharp(`${out}/${name}-ref.png`).raw().toBuffer({ resolveWithObject: true }), sharp(`${out}/${name}-test.png`).raw().toBuffer({ resolveWithObject: true })]);
  let diff = -1;
  if (ia.info.width === ib.info.width && ia.info.height === ib.info.height) {
    let n = 0;
    for (let i = 0; i < ia.data.length; i += ia.info.channels) {
      if (Math.abs(ia.data[i] - ib.data[i]) > 8 || Math.abs(ia.data[i + 1] - ib.data[i + 1]) > 8 || Math.abs(ia.data[i + 2] - ib.data[i + 2]) > 8) n++;
    }
    diff = n / (ia.info.width * ia.info.height);
  }
  const sameText = a.text === b.text;
  const good = a.status === b.status && sameText && diff >= 0 && diff < 0.001;
  if (!good) failures++;
  console.log(`${good ? '✓' : '✗'} ${path}  status ${a.status}/${b.status}  size ${ia.info.width}x${ia.info.height} vs ${ib.info.width}x${ib.info.height}  pixels differing: ${diff < 0 ? 'n/a' : (diff * 100).toFixed(3) + '%'}  text: ${sameText ? 'same' : 'DIFFERENT'}`);
  if (!sameText) {
    let i = 0;
    while (i < a.text.length && a.text[i] === b.text[i]) i++;
    console.log(`    ref : …${a.text.slice(Math.max(0, i - 60), i + 80)}`);
    console.log(`    test: …${b.text.slice(Math.max(0, i - 60), i + 80)}`);
  }
}
await browser.close();
console.log(errors.length ? 'JS errors:\n' + errors.join('\n') : 'no JS errors');
console.log(failures ? `${failures} page(s) differ` : 'all pages identical');
process.exit(failures ? 1 : 0);
