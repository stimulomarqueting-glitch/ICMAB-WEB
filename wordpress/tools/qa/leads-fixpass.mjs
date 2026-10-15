/**
 * QA for the "leads" module — fix pass (browser part).
 *  - list paging after deleting / changing the last rows of the last page,
 *    out-of-range ?paged= links (with and without filters), as admin and editor
 *  - a lead containing '<', quotes and '&': detail, mailto, search, CSV
 * Seeds its own data through the CLI and removes it at the end:
 *   MATS_WP_PORT=8912 php /home/user/wpsite-cli.php wordpress/tools/qa/leads-fixpass.php seed 41 | entity | clean
 * Run (from the repo root):
 *   WP_BASE=http://localhost:8912 node wordpress/tools/qa/leads-fixpass.mjs [screenshot-dir]
 */
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { launch, login, BASE } from './wp-login.mjs';

const OUT = process.argv[2] || '/tmp/leads-fixpass';
fs.mkdirSync(OUT, { recursive: true });
const PORT = new URL(BASE).port;
const cli = (...args) =>
  execFileSync('php', ['/home/user/wpsite-cli.php', 'wordpress/tools/qa/leads-fixpass.php', ...args], {
    env: { ...process.env, MATS_WP_PORT: PORT },
    encoding: 'utf8',
  });
const results = [];
const check = (name, pass, info = '') => results.push([pass ? 'PASS' : 'FAIL', name, String(info)]);
const LIST = `${BASE}/wp-admin/admin.php?page=mats-leads`;
const qs = (url) => Object.fromEntries(new URL(url).searchParams.entries());

cli('clean');
cli('seed', '41');
const entityId = JSON.parse(cli('entity').trim().split('\n').pop()).entity;

const browser = await launch();
try {
  const page = await login(browser, 'admin', 'admin-local-pass');
  page.on('dialog', (d) => d.accept());
  const rows = () => page.locator('#the-list tr:not(.no-items)');
  const notice = async () => ((await page.locator('.wrap .notice p').first().textContent().catch(() => '')) || '').trim();
  const total = async () => ((await page.locator('.tablenav.top .displaying-num').textContent().catch(() => '')) || '').trim();
  const rowAction = async (cls) => {
    const link = page.locator(`#the-list tr .row-actions .${cls} a`).first();
    const href = await link.getAttribute('href');
    await Promise.all([page.waitForNavigation(), link.click({ force: true })]);
    return href;
  };

  /* G. view "Nuevos" + search, last page has 1 row → mark it answered */
  await page.goto(`${LIST}&s=QAFIX&lead_status=new&paged=3`);
  check('setup: Nuevos+QAFIX page 3 has 1 row', (await rows().count()) === 1, `${await rows().count()} ${await total()}`);
  await rowAction('answered');
  let q = qs(page.url());
  check('answered on last page → previous page', q.paged === '2' && q.lead_status === 'new' && q.s === 'QAFIX', page.url());
  check('… shows 20 rows', (await rows().count()) === 20, await rows().count());
  check('… notice kept', (await notice()) === '1 contacto marcado como atendido.', await notice());
  check('… total 40', /^40\b/.test(await total()), await total());

  /* A. row "Borrar" on the last page (1 row) */
  await page.goto(`${LIST}&s=QAFIX&paged=3`);
  check('setup: QAFIX page 3 has 1 row', (await rows().count()) === 1, await rows().count());
  await rowAction('delete');
  q = qs(page.url());
  check('row delete on last page → page 2', q.paged === '2' && q.s === 'QAFIX', page.url());
  check('… 20 rows, total 40', (await rows().count()) === 20 && /^40\b/.test(await total()), `${await rows().count()} ${await total()}`);
  check('… notice', (await notice()) === '1 contacto borrado definitivamente.', await notice());
  check('… no empty-inbox text', (await page.locator('#the-list .no-items').count()) === 0);
  await page.screenshot({ path: path.join(OUT, 'after-row-delete.png'), fullPage: false });

  /* B. bulk delete every row of the last page (page 2 of 2) */
  await page.locator('#cb-select-all-1').check();
  await page.selectOption('#bulk-action-selector-top', 'delete');
  await Promise.all([page.waitForNavigation(), page.click('#doaction')]);
  q = qs(page.url());
  check('bulk delete of the whole last page → page 1', !('paged' in q) && q.s === 'QAFIX', page.url());
  check('… 20 rows, total 20', (await rows().count()) === 20 && /^20\b/.test(await total()), `${await rows().count()} ${await total()}`);
  check('… notice', (await notice()) === '20 contactos borrados definitivamente.', await notice());
  await page.screenshot({ path: path.join(OUT, 'after-bulk-delete.png'), fullPage: false });

  /* C–F. out-of-range links */
  await page.goto(`${LIST}&s=QAFIX&lead_status=new&paged=7`);
  q = qs(page.url());
  check('paged=7 in a 1-page view → page 1, filters kept', !('paged' in q) && q.lead_status === 'new' && q.s === 'QAFIX' && (await rows().count()) === parseInt(await total(), 10) && (await rows().count()) > 0, `${page.url()} ${await rows().count()}`);
  await page.goto(`${LIST}&s=zz-no-such-lead&paged=2`);
  q = qs(page.url());
  check('paged=2 with no results → page 1 + "no results" text', !('paged' in q) && /No hay ningún contacto con estos filtros/.test(await page.locator('#the-list').textContent()), page.url());
  await page.goto(`${LIST}&paged=9`);
  const all = await total();
  const allN = parseInt(all, 10);
  const last = Math.max(1, Math.ceil(allN / 20));
  q = qs(page.url());
  check('unfiltered paged=9 → last page', (last > 1 ? q.paged === String(last) : !('paged' in q)) && (await rows().count()) === allN - (last - 1) * 20, `${page.url()} ${all}`);
  await page.goto(`${LIST}&paged=2`);
  check('valid page 2 is not redirected', qs(page.url()).paged === '2' && (await rows().count()) > 0, page.url());
  await page.goto(`${LIST}&orderby=name&order=asc&s=QAFIX&paged=4`);
  q = qs(page.url());
  check('sorting kept on redirect', q.orderby === 'name' && q.order === 'asc' && !('paged' in q), page.url());

  /* plain-text lead: detail, mailto, search, CSV */
  await page.goto(`${LIST}&lead=${entityId}`);
  const msg = (await page.locator('.mats-lead__message').textContent()).trim();
  check('detail: message shown as typed', msg === 'We need Tc < 300 K for "our" R&D samples.\nSecond line: T < 300 K and P > 2 bar.', msg);
  check('detail: name shown as typed', (await page.locator('h1').first().textContent()).trim() === 'Ana "Nan" O\'Neil <y Cía');
  const how = await page.locator('.mats-card:has(h2:text("Cómo llegó"))').textContent();
  check('detail: Spanish label of latest-news', how.includes('Sección «Últimas noticias»') && how.includes('latest-news'), how.replace(/\s+/g, ' ').slice(0, 160));
  const mailto = await page.locator('a.mats-reply').getAttribute('href');
  const body = decodeURIComponent(mailto.split('&body=')[1] || '');
  check('detail: reply email quotes plain text', body.includes('> We need Tc < 300 K for "our" R&D samples.') && !body.includes('&lt;') && !body.includes('&quot;'), body.slice(0, 120));
  check('detail: tab title plain', (await page.title()).startsWith('Ana "Nan" O\'Neil <y Cía'), await page.title());
  await page.screenshot({ path: path.join(OUT, 'detail-plain-text.png'), fullPage: true });

  await page.goto(LIST);
  await page.fill('#mats-leads-search-search-input', 'Tc < 300 K for "our"');
  await Promise.all([page.waitForNavigation(), page.click('#search-submit')]);
  check('search with < and quotes finds it', (await rows().count()) === 1 && (await page.locator('#the-list .row-title').textContent()).includes('Ana "Nan"'), `${await rows().count()} ${page.url()}`);
  check('search box keeps the typed text', (await page.inputValue('#mats-leads-search-search-input')) === 'Tc < 300 K for "our"', await page.inputValue('#mats-leads-search-search-input'));
  const [dl] = await Promise.all([page.waitForEvent('download'), page.click('.page-title-action:has-text("Exportar CSV")')]);
  const csv = fs.readFileSync(await dl.path()).toString('utf8').slice(1);
  const lines = csv.split('\r\n');
  check('csv: description column', lines[0].includes(';"Botón de origen";"Botón de origen (qué es)";"Página de origen";'), lines[0]);
  check('csv: filtered by the search → 1 record', (csv.match(/\r\n"?\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}"?;/g) || []).length === 1);
  check('csv: plain text, no entities', csv.includes('"We need Tc < 300 K for ""our"" R&D samples.') && csv.includes('QAENT R&D <Labs') && !/&(lt|gt|quot|amp);/.test(csv), (csv.match(/We need.{0,60}/) || [''])[0]);
  check('csv: cta code + Spanish label', csv.includes(';latest-news;"Sección «Últimas noticias»'), (csv.match(/latest-news.{0,40}/) || [''])[0]);
  fs.writeFileSync(path.join(OUT, 'export-plain.csv'), csv);

  /* editor: same redirect */
  const ed = await login(browser, 'editor1', 'qa-pass-123');
  await ed.goto(`${LIST}&s=QAFIX&paged=5`);
  const eq = qs(ed.url());
  check('editor: out-of-range page → page 1', !('paged' in eq) && (await ed.locator('#the-list tr:not(.no-items)').count()) === 20, ed.url());
} finally {
  await browser.close();
  cli('clean');
}

const log = `/home/user/wpsite-data/debug-${PORT}.log`;
const logText = fs.existsSync(log) ? fs.readFileSync(log, 'utf8') : '';
check('debug log empty', logText.trim() === '', logText.slice(0, 300));
const fail = results.filter((r) => r[0] === 'FAIL').length;
console.log(JSON.stringify({ pass: results.length - fail, fail, results }, null, 1));
