/**
 * QA for the "leads" module (Contactos inbox) — admin screens.
 * Prepare data first:
 *   MATS_WP_PORT=8912 php /home/user/wpsite-cli.php wordpress/tools/qa/leads-setup.php reset
 * Run (from the repo root):
 *   WP_BASE=http://localhost:8912 node wordpress/tools/qa/leads-admin.mjs [screenshot-dir]
 */
import fs from 'node:fs';
import path from 'node:path';
import { launch, login, BASE } from './wp-login.mjs';

const OUT = process.argv[2] || '/tmp/leads-qa';
fs.mkdirSync(OUT, { recursive: true });
const results = [];
const check = (name, pass, info = '') => {
  results.push([pass ? 'PASS' : 'FAIL', name, info]);
};
const LIST = `${BASE}/wp-admin/admin.php?page=mats-leads`;

const browser = await launch();
try {
  /* ── administrator ───────────────────────────────────────────────── */
  const page = await login(browser, 'admin', 'admin-local-pass');
  const alerts = [];
  page.on('dialog', async (d) => {
    if (d.type() === 'alert') alerts.push(d.message());
    await d.accept();
  });

  await page.goto(`${BASE}/wp-admin/index.php`);
  const menu = page.locator('#toplevel_page_mats-leads');
  check('admin: menu item', (await menu.count()) === 1);
  check('admin: menu icon', (await menu.locator('.dashicons-email-alt').count()) === 1);
  const bubble = (await menu.locator('.awaiting-mod .pending-count').textContent().catch(() => '')) || '';
  check('admin: red bubble = 3 new', bubble.trim() === '3', bubble);
  const widget = page.locator('#mats_leads_widget');
  check('admin: dashboard widget', (await widget.count()) === 1);
  await widget.screenshot({ path: path.join(OUT, 'dashboard-widget.png') }).catch(() => {});

  await page.goto(LIST);
  await page.screenshot({ path: path.join(OUT, 'list-admin.png'), fullPage: true });
  const rows = () => page.locator('#the-list tr:not(.no-items)');
  check('list: 5 rows', (await rows().count()) === 5, await rows().count());
  const heads = await page.locator('.wp-list-table thead th, .wp-list-table thead td').allTextContents();
  check('list: columns', ['Recibido', 'Nombre', 'Email', 'Organización', 'Le interesa', 'Idioma', 'Estado'].every((h) => heads.join('|').includes(h)), heads.join('|'));
  check('list: no "Añadir" button', (await page.locator('.page-title-action', { hasText: /Añadir|Add/ }).count()) === 0);
  check('list: export button', (await page.locator('.page-title-action', { hasText: 'Exportar CSV' }).count()) === 1);
  const mailto = await page.locator('#the-list td.column-email a').first().getAttribute('href');
  check('list: email is mailto', /^mailto:/.test(mailto || ''), mailto);
  check('list: flags', (await page.locator('#the-list .mats-lang--en').count()) >= 1 && (await page.locator('#the-list .mats-lang--es').count()) >= 1);
  check('list: hostile name escaped', (await page.locator('#the-list script, #the-list img[src="x"]').count()) === 0);

  /* language filter */
  await page.selectOption('#mats-filter-lang', 'es');
  await Promise.all([page.waitForNavigation(), page.click('#mats-leads-filter')]);
  check('filter: lang=es → 2 rows', (await rows().count()) === 2, `${await rows().count()} ${page.url()}`);
  check('filter: tidy URL', page.url() === `${LIST}&lead_lang=es`, page.url());
  check('filter: Polylang lang param untouched', !/[?&]lang=/.test(page.url()));

  /* search */
  await page.goto(LIST);
  await page.fill('#mats-leads-search-search-input', 'Oxford');
  await Promise.all([page.waitForNavigation(), page.click('#search-submit')]);
  check('search: Oxford → 1 row', (await rows().count()) === 1, await rows().count());
  await page.goto(`${LIST}&s=${encodeURIComponent('perovskite')}`);
  check('search: in message', (await rows().count()) === 1);
  await page.goto(`${LIST}&s=${encodeURIComponent('tohoku.example')}`);
  check('search: in email', (await rows().count()) === 1);

  /* views by status */
  await page.goto(LIST);
  await Promise.all([page.waitForNavigation(), page.click('.subsubsub a:has-text("Nuevos")')]);
  check('view: Nuevos → 3 rows', (await rows().count()) === 3, await rows().count());
  check('view: current', (await page.locator('.subsubsub a.current', { hasText: 'Nuevos' }).count()) === 1);

  /* sort by name */
  await page.goto(`${LIST}&orderby=name&order=asc`);
  const names = (await page.locator('#the-list .row-title').allTextContents()).map((s) => s.trim());
  check('sort: by name asc', names.length === 5 && names[0].startsWith('Dr. Emily'), names.join(' / '));

  /* row action: mark as answered (Kenji, status read) */
  await page.goto(LIST);
  const kenji = page.locator('#the-list tr', { hasText: 'Kenji Watanabe' });
  await kenji.hover();
  await Promise.all([page.waitForNavigation(), kenji.locator('.row-actions a', { hasText: 'Marcar como atendido' }).click()]);
  check('row action: notice', (await page.locator('.notice-success', { hasText: 'marcado como atendido' }).count()) === 1);
  check('row action: status pill', (await page.locator('#the-list tr', { hasText: 'Kenji Watanabe' }).locator('.mats-status--answered').count()) === 1);

  /* bad nonce is refused */
  const href = await page.locator('#the-list tr', { hasText: 'Dr. Emily Carter' }).locator('.row-actions a', { hasText: 'Marcar como atendido' }).getAttribute('href');
  const bad = await page.goto(href.replace(/_wpnonce=[^&]+/, '_wpnonce=deadbeef'));
  check('row action: bad nonce → 403', bad.status() === 403, bad.status());

  /* bulk: mark two as read */
  await page.goto(LIST);
  await page.locator('#the-list tr', { hasText: 'Dr. Emily Carter' }).locator('input[type=checkbox]').check();
  await page.locator('#the-list tr', { hasText: 'María José' }).locator('input[type=checkbox]').check();
  await page.selectOption('#bulk-action-selector-top', 'read');
  await Promise.all([page.waitForNavigation(), page.click('#doaction')]);
  check('bulk: notice', (await page.locator('.notice-success', { hasText: '2 contactos marcados como leídos' }).count()) === 1, await page.locator('.notice').allTextContents());
  const bubble2 = (await page.locator('#toplevel_page_mats-leads .pending-count').textContent().catch(() => '')) || '';
  check('bulk: bubble now 1', bubble2.trim() === '1', bubble2);
  /* bulk with nothing selected */
  /* (core JS stops it in the browser with a notice; the server also refuses it with a warning) */
  await page.selectOption('#bulk-action-selector-top', 'answered');
  await page.click('#doaction');
  await page.waitForTimeout(600);
  check('bulk: nothing selected → core notice, no request', (await page.locator('#no-items-selected').count()) === 1 && !/[?&]action=/.test(page.url()), page.url());
  const nonceVal = await page.locator('#mats-leads-form input[name=_wpnonce]').inputValue();
  await page.goto(`${LIST}&action=answered&_wpnonce=${nonceVal}`);
  check('bulk: nothing selected → server warning', (await page.locator('.notice-warning').count()) === 1, await page.locator('.notice').allTextContents());
  /* back to new for the detail test */
  await page.goto(LIST);
  await page.locator('#the-list tr', { hasText: 'Dr. Emily Carter' }).locator('input[type=checkbox]').check();
  await page.selectOption('#bulk-action-selector-bottom', 'new');
  await Promise.all([page.waitForNavigation(), page.click('#doaction2')]);
  check('bulk bottom: mark new', (await page.locator('#the-list tr', { hasText: 'Dr. Emily Carter' }).locator('.mats-status--new').count()) === 1);

  /* detail: opens read-only + marks as read */
  await Promise.all([page.waitForNavigation(), page.locator('#the-list .row-title', { hasText: 'Dr. Emily Carter' }).click()]);
  await page.screenshot({ path: path.join(OUT, 'detail-admin.png'), fullPage: true });
  check('detail: title', (await page.locator('.mats-lead h1').textContent()).trim() === 'Dr. Emily Carter');
  check('detail: marked read', (await page.locator('.mats-lead__meta .mats-status--read').count()) === 1);
  check('detail: no editable inputs for data', (await page.locator('.mats-lead__main input, .mats-lead__main textarea').count()) === 0);
  const reply = await page.locator('a.mats-reply').getAttribute('href');
  check('detail: reply mailto with subject+body', /^mailto:emily\.carter@oxford-materials\.example\?subject=Re%3A%20your%20message/.test(reply) && reply.includes('&body=Hello%20Dr.%20Emily%20Carter%2C'), reply.slice(0, 160));
  check('detail: message lines kept', (await page.locator('.mats-lead__message').textContent()).includes('specs & pricing?'));
  check('detail: UTM shown', (await page.locator('.mats-utm').count()) === 3);
  check('detail: cta label', (await page.locator('.mats-dl', { hasText: 'Portada: botón principal' }).count()) === 1);
  check('detail: consent', (await page.locator('.mats-yes').count()) === 1);
  check('detail: tab title', (await page.title()).startsWith('Dr. Emily Carter ‹'), await page.title());
  /* Emily was the 2nd new lead (with Mallory): opening her leaves 1 */
  check('detail: bubble updated on the same load', ((await page.locator('#toplevel_page_mats-leads .pending-count').textContent()) || '').trim() === '1', await page.locator('#toplevel_page_mats-leads').innerText());

  /* detail: status selector */
  await page.locator('.mats-radio', { hasText: 'Atendido' }).locator('input').check();
  await Promise.all([page.waitForNavigation(), page.click('.mats-status-form button[type=submit]')]);
  check('detail: status saved', (await page.locator('.notice-success', { hasText: 'Estado guardado: «Atendido»' }).count()) === 1);
  check('detail: answered by', (await page.locator('.mats-answered').textContent()).includes('Atendido el'));

  /* Spanish lead: quick "marcar como atendido" + Spanish mailto + extra field */
  await page.goto(LIST);
  await Promise.all([page.waitForNavigation(), page.locator('#the-list .row-title', { hasText: 'María José' }).click()]);
  const replyEs = await page.locator('a.mats-reply').getAttribute('href');
  check('detail ES: Spanish mailto', replyEs.includes('subject=Re%3A%20tu%20mensaje') && replyEs.includes('Hola%2C%20Mar%C3%ADa'), replyEs.slice(0, 140));
  check('detail ES: extra field', (await page.locator('.mats-dl__row', { hasText: 'Teléfono' }).count()) === 1);
  check('detail ES: application title', (await page.locator('.mats-dl__row', { hasText: 'Aplicación que estaba viendo' }).count()) === 1);
  await Promise.all([page.waitForNavigation(), page.click('.mats-quick__btn')]);
  check('detail ES: quick answered', (await page.locator('.mats-lead__meta .mats-status--answered').count()) === 1);

  /* hostile lead renders escaped, no script runs */
  await page.goto(LIST);
  await Promise.all([page.waitForNavigation(), page.locator('#the-list .row-title', { hasText: 'Mallory' }).click()]);
  await page.screenshot({ path: path.join(OUT, 'detail-hostile.png'), fullPage: true });
  check('hostile: nothing injected', (await page.locator('.mats-lead script, .mats-lead iframe, .mats-lead img[src="x"], .mats-lead b').count()) === 0);
  check('hostile: no alert fired', alerts.length === 0, alerts.join(','));
  check('hostile: consent "No consta"', (await page.locator('.mats-no').count()) === 1);

  /* old screens redirect */
  const id = new URL(page.url()).searchParams.get('lead');
  await page.goto(`${BASE}/wp-admin/post.php?post=${id}&action=edit`);
  check('redirect: post.php → detail', page.url().includes(`page=mats-leads&lead=${id}`), page.url());
  await page.goto(`${BASE}/wp-admin/edit.php?post_type=mats_lead`);
  check('redirect: edit.php → list', page.url().endsWith('page=mats-leads'), page.url());
  await page.goto(`${BASE}/wp-admin/post-new.php?post_type=mats_lead`);
  check('redirect: post-new.php → list', page.url().endsWith('page=mats-leads'), page.url());

  /* CSV export (header button = everything) */
  await page.goto(LIST);
  const [dl] = await Promise.all([page.waitForEvent('download'), page.click('.page-title-action:has-text("Exportar CSV")')]);
  const csvPath = path.join(OUT, dl.suggestedFilename());
  await dl.saveAs(csvPath);
  const buf = fs.readFileSync(csvPath);
  check('csv: BOM', buf[0] === 0xef && buf[1] === 0xbb && buf[2] === 0xbf);
  const csv = buf.toString('utf8').slice(1);
  const lines = csv.split('\r\n').filter(Boolean);
  check('csv: ; separator + header', lines[0].startsWith('Fecha;Estado;Nombre;Email;Organización;'), lines[0].slice(0, 80));
  check('csv: accents', csv.includes('María José Núñez') && csv.includes('Universitat Autònoma'));
  check('csv: formula neutralised', csv.includes(`"'=HYPERLINK`) || csv.includes(`;'=HYPERLINK`), (csv.match(/.{0,5}=HYPERLINK.{0,10}/) || [''])[0]);
  check('csv: filename', /^contactos-matsurfer-\d{4}-\d{2}-\d{2}\.csv$/.test(dl.suggestedFilename()), dl.suggestedFilename());
  const records = csv.match(/\r\n"?\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}"?;/g) || [];
  check('csv: 5 records', records.length === 5, records.length);

  /* CSV export card with filters */
  await page.selectOption('#mats-leads-export select[name=lead_lang]', 'es');
  await page.selectOption('#mats-leads-export select[name=lead_status]', 'answered');
  const [dl2] = await Promise.all([page.waitForEvent('download'), page.click('#mats-leads-export button[type=submit]')]);
  const csv2 = fs.readFileSync(await dl2.path()).toString('utf8');
  const rec2 = csv2.match(/\r\n"?\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}"?;/g) || [];
  check('csv filtered: es + answered → 2 (María José + old)', rec2.length === 2, `${rec2.length} ${dl2.suggestedFilename()}`);
  const today = new Date().toISOString().slice(0, 10);
  await page.fill('#mats-leads-export input[name=lead_from]', '2020-01-01');
  await page.fill('#mats-leads-export input[name=lead_to]', new Date(Date.now() - 400 * 864e5).toISOString().slice(0, 10));
  await page.selectOption('#mats-leads-export select[name=lead_lang]', '');
  await page.selectOption('#mats-leads-export select[name=lead_status]', '');
  const [dl3] = await Promise.all([page.waitForEvent('download'), page.click('#mats-leads-export button[type=submit]')]);
  const csv3 = fs.readFileSync(await dl3.path()).toString('utf8');
  const rec3 = csv3.match(/\r\n"?\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}"?;/g) || [];
  check('csv dates: only the 2-year-old lead', rec3.length === 1 && csv3.includes('Old Contact'), `${rec3.length} (${today})`);

  /* export without nonce is refused */
  const noNonce = await page.goto(`${BASE}/wp-admin/admin-post.php?action=mats_leads_export`);
  check('csv: no nonce → refused', noNonce.status() === 403, noNonce.status());

  /* retention: 2 years → deletes the 25-month-old lead (confirm accepted) */
  await page.goto(LIST);
  await page.selectOption('#mats-leads-retention select[name=retention]', '24');
  await Promise.all([page.waitForNavigation(), page.click('#mats-leads-retention button[type=submit]')]);
  check('retention: saved + old deleted', (await page.locator('.notice-success', { hasText: 'Se ha borrado 1 contacto antiguo' }).count()) === 1, await page.locator('.notice').allTextContents());
  check('retention: state on', (await page.locator('#mats-leads-retention .mats-state__on').count()) === 1);
  await page.locator('#mats-leads-retention').screenshot({ path: path.join(OUT, 'retention-on.png') });
  check('list: 4 rows after cleanup', (await rows().count()) === 4, await rows().count());

  /* delete from the detail screen */
  await Promise.all([page.waitForNavigation(), page.locator('#the-list .row-title', { hasText: 'Mallory' }).click()]);
  await Promise.all([page.waitForNavigation(), page.click('button.mats-delete')]);
  check('detail delete: notice', (await page.locator('.notice-success', { hasText: '1 contacto borrado definitivamente' }).count()) === 1);
  check('detail delete: 3 rows left', (await rows().count()) === 3, await rows().count());
  const gone = await page.goto(`${LIST}&lead=${id}`);
  check('deleted lead → list with warning', page.url().includes('page=mats-leads') && (await page.locator('.notice-warning').count()) === 1, page.url());

  /* mobile */
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(LIST);
  await page.screenshot({ path: path.join(OUT, 'list-mobile.png'), fullPage: true });
  await Promise.all([page.waitForNavigation(), page.locator('#the-list .row-title').first().click()]);
  await page.screenshot({ path: path.join(OUT, 'detail-mobile.png'), fullPage: true });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  check('mobile: no horizontal scroll on detail', overflow <= 0, overflow);
  await page.context().close();

  /* ── editor ──────────────────────────────────────────────────────── */
  const ed = await login(browser, 'editor1', 'qa-pass-123');
  ed.on('dialog', (d) => d.accept());
  await ed.goto(`${BASE}/wp-admin/index.php`);
  check('editor: menu item', (await ed.locator('#toplevel_page_mats-leads').count()) === 1);
  await ed.goto(LIST);
  await ed.screenshot({ path: path.join(OUT, 'list-editor.png'), fullPage: true });
  check('editor: list rows', (await ed.locator('#the-list tr:not(.no-items)').count()) === 3);
  check('editor: retention locked', (await ed.locator('#mats-leads-retention select[disabled]').count()) === 1 && (await ed.locator('#mats-leads-retention button[type=submit]').count()) === 0);
  await Promise.all([ed.waitForNavigation(), ed.locator('#the-list .row-title').first().click()]);
  check('editor: detail opens', (await ed.locator('.mats-lead h1').count()) === 1);
  const [edl] = await Promise.all([ed.waitForEvent('download'), ed.goto(LIST).then(() => ed.click('.page-title-action:has-text("Exportar CSV")'))]);
  check('editor: can export', /\.csv$/.test(edl.suggestedFilename()));
  /* editor forging the retention form is refused */
  const forged = await ed.evaluate(async (base) => {
    const html = await (await fetch(`${base}/wp-admin/admin.php?page=mats-leads`)).text();
    const part = html.split('id="mats-leads-retention"')[1] || '';
    const nonce = (part.match(/name="_wpnonce" value="([a-f0-9]+)"/) || [])[1] || 'x';
    const body = new URLSearchParams({ action: 'mats_leads_settings', retention: '6', _wpnonce: nonce });
    const r = await fetch(`${base}/wp-admin/admin-post.php`, { method: 'POST', body, redirect: 'manual' });
    return r.status;
  }, BASE);
  check('editor: cannot change retention (403)', forged === 403, forged);
  await ed.context().close();

  /* ── author ──────────────────────────────────────────────────────── */
  const au = await login(browser, 'author1', 'qa-pass-123');
  await au.goto(`${BASE}/wp-admin/index.php`);
  check('author: no menu item', (await au.locator('#toplevel_page_mats-leads').count()) === 0);
  check('author: no widget', (await au.locator('#mats_leads_widget').count()) === 0);
  const denied = await au.goto(LIST);
  check('author: page denied', denied.status() === 403 || (await au.locator('body').innerText()).match(/not allowed|no tienes permiso/i) !== null, denied.status());
  await au.screenshot({ path: path.join(OUT, 'author-denied.png') });
  const exp = await au.goto(`${BASE}/wp-admin/admin-post.php?action=mats_leads_export`);
  check('author: export denied', exp.status() === 403, exp.status());
  const post = await au.goto(`${BASE}/wp-admin/edit.php?post_type=mats_lead`);
  check('author: edit.php does not list leads', !(await au.locator('body').innerText()).includes('Kenji'), post.status());
  await au.context().close();

  /* ── public side ─────────────────────────────────────────────────── */
  const anon = await (await browser.newContext()).newPage();
  const rest = await anon.goto(`${BASE}/wp-json/wp/v2/mats_lead`);
  check('public: no REST route', rest.status() === 404, rest.status());
  const q = await anon.goto(`${BASE}/?post_type=mats_lead`);
  check('public: no archive', !(await anon.content()).includes('Kenji'), q.status());
  const sm = await anon.goto(`${BASE}/wp-sitemap.xml`);
  check('public: sitemap has no leads', !(await anon.content()).includes('mats_lead'), sm.status());
  const search = await anon.goto(`${BASE}/?s=Kenji`);
  check('public: search finds nothing', !(await anon.content()).includes('Watanabe'), search.status());
  await anon.context().close();
} finally {
  await browser.close();
}

let failed = 0;
for (const [s, n, i] of results) {
  if (s === 'FAIL') failed++;
  console.log(`${s}  ${n}${s === 'FAIL' && i !== '' ? '  → ' + i : ''}`);
}
console.log(failed ? `\n${failed} FAILED` : `\nALL PASSED (${results.length})`);
process.exit(failed ? 1 : 0);
