/**
 * QA: screenshots of the pages the setup wizard created (public site, EN + ES).
 *
 *   WP_BASE=http://localhost:8913 node wordpress/tools/qa/installer-shots.mjs [DIR] [path…]
 * Default paths: / /es/ /product/ /es/producto/ /contact/ /es/contacto/ /team/ /es/casos-de-estudio/
 */
import fs from 'node:fs';
import { BASE, launch } from './wp-login.mjs';

const [dirArg, ...rest] = process.argv.slice(2);
const DIR = dirArg || '/tmp/claude-0/-home-user-ICMAB-WEB/ae5d27f5-1af0-537f-966f-1d2257e6cfbd/scratchpad/installer/front';
const paths = rest.length ? rest : ['/', '/es/', '/product/', '/es/producto/', '/contact/', '/es/contacto/', '/team/', '/es/casos-de-estudio/'];
fs.mkdirSync(DIR, { recursive: true });

const browser = await launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
page.on('pageerror', (e) => console.log('  [JS error]', e.message));
for (const p of paths) {
  const res = await page.goto(BASE + p, { waitUntil: 'networkidle' });
  await page.evaluate(() => document.querySelectorAll('.reveal, .rise-in').forEach((el) => el.classList.add('is-visible', 'in')));
  const name = (p.replace(/\//g, '_').replace(/^_|_$/g, '') || 'home') + '.png';
  await page.screenshot({ path: `${DIR}/${name}`, fullPage: true });
  const title = await page.title();
  const h1 = await page.$eval('h1', (el) => el.textContent.trim().replace(/\s+/g, ' ')).catch(() => '(no h1)');
  console.log(`${res.status()} ${p} — ${title} — h1: ${h1.slice(0, 80)} → ${DIR}/${name}`);
}
await browser.close();
