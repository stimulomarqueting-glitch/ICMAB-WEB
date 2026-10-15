/**
 * QA (module "cptadmin", fix pass): the review fixes in a real browser.
 *   - «Contenido» cannot be closed by clicking its heading
 *   - ▲▼ only in the full list (not in «Mías», a status view or a search)
 *   - expert name per language: old records show their name in both boxes
 *     and in the list; emptying both boxes really removes the signature
 *     from the website; the automatic internal name includes the person
 *   - browsers without a month picker: the text box accepts what the
 *     server accepts (2026-9, 09/2026…); year from 1900
 *   - the full site (FULL_BASE, read only): «Persona» shows the names
 *
 * Needs cptadmin-setup.php run once (user editora). Run from the repo root:
 *   WP_BASE=http://localhost:8918 MATS_WP_PORT=8918 FULL_BASE=http://localhost:8950 \
 *     node wordpress/tools/qa/cptadmin-fixpass.mjs <shots-dir>
 */
import { launch, login, BASE } from './wp-login.mjs';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';

const out = process.argv[2] || '.';
fs.mkdirSync(out, { recursive: true });
const PORT = process.env.MATS_WP_PORT || new URL(BASE).port;
const FULL = process.env.FULL_BASE || '';
const stamp = Date.now().toString(36).slice(-4);

let failures = 0;
const ok = (cond, msg) => {
  console.log(`${cond ? 'PASS' : 'FAIL'} ${msg}`);
  if (!cond) failures++;
};
const php = (arg) =>
  JSON.parse(
    execFileSync('php', ['/home/user/wpsite-cli.php', 'wordpress/tools/qa/cptadmin-fixpass.php', arg], {
      env: { ...process.env, MATS_WP_PORT: PORT },
    }).toString()
  );
const html = async (base, path) => (await fetch(base + path)).text();
const f = (name) => `[name="mats_cpta[${name}]"]`;

const browser = await launch();
const jsErrors = [];
function watch(page, who) {
  page.on('pageerror', (e) => jsErrors.push(`${who} ${page.url()} ${e.message}`));
  page.on('dialog', (d) => d.accept());
}
async function submit(page, selector = '#publish') {
  await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click(selector)]);
}

/* ═══ Editor ═══════════════════════════════════════════════════════════ */
const ed = await login(browser, 'editora', 'editora-local-pass');
watch(ed, 'editora');

/* ── «Contenido» heading is not a toggle ─────────────────────────── */
for (const type of ['mats_publication', 'mats_team']) {
  await ed.goto(`${BASE}/wp-admin/post-new.php?post_type=${type}`);
  const ajax = [];
  const onReq = (r) => {
    if (r.url().includes('admin-ajax.php') && (r.postData() || '').includes('closed-postboxes')) ajax.push(r.url());
  };
  ed.on('request', onReq);
  await ed.click('#mats-cpta-content .postbox-header h2');
  await ed.waitForTimeout(400);
  const state = await ed.evaluate(() => {
    const box = document.getElementById('mats-cpta-content');
    const inside = box.querySelector('.inside');
    const input = box.querySelector('.mats-cpta__input');
    return {
      closed: box.classList.contains('closed'),
      inside: getComputedStyle(inside).display,
      inputVisible: !!(input && input.offsetParent),
      hndle: box.querySelectorAll('.hndle').length,
      size: getComputedStyle(box.querySelector('h2')).fontSize,
    };
  });
  ed.off('request', onReq);
  ok(!state.closed && state.inside !== 'none' && state.inputVisible, `${type}: clicking «Contenido» does not hide the form (${JSON.stringify(state)})`);
  ok(state.hndle === 0 && state.size === '14px', `${type}: plain heading, same size as the other boxes`);
  ok(ajax.length === 0, `${type}: no closed-postboxes request`);
  /* even if something marks it closed, the fields stay visible */
  const forced = await ed.evaluate(() => {
    const box = document.getElementById('mats-cpta-content');
    box.classList.add('closed');
    const d = getComputedStyle(box.querySelector('.inside')).display;
    box.classList.remove('closed');
    return d;
  });
  ok(forced !== 'none', `${type}: a .closed class cannot hide the fields`);
  /* the side boxes still fold as usual */
  const side = ed.locator('#mats-cpta-order .hndle');
  if (await side.count()) {
    await side.click();
    ok(await ed.locator('#mats-cpta-order.closed').count() === 1, `${type}: side box «Orden en la web» still folds`);
    await side.click();
  }
}
await ed.screenshot({ path: `${out}/fix-contenido-after-click.png` });

/* ── ▲▼ only in the full list ─────────────────────────────────────── */
await ed.goto(`${BASE}/wp-admin/edit.php?post_type=mats_team`);
const allArrows = await ed.locator('#the-list a.mats-cpta-move').count();
ok(allArrows > 0, `team «Todas»: ▲▼ present (${allArrows})`);
for (const [q, label] of [
  ['&author=2', '«Mías» (author)'],
  ['&post_status=publish', 'status view'],
  ['&post_status=draft', 'draft view'],
  ['&s=a', 'search'],
  ['&orderby=title&order=asc', 'sorted by name'],
]) {
  await ed.goto(`${BASE}/wp-admin/edit.php?post_type=mats_team${q}`);
  ok((await ed.locator('#the-list a.mats-cpta-move').count()) === 0, `team ${label}: no ▲▼`);
}
await ed.goto(`${BASE}/wp-admin/edit.php?post_type=mats_team&author=2`);
await ed.screenshot({ path: `${out}/fix-team-mine.png`, fullPage: true });
await ed.goto(`${BASE}/wp-admin/edit.php?post_type=mats_team&orderby=menu_order&order=asc`);
ok((await ed.locator('#the-list a.mats-cpta-move').count()) === allArrows, 'team sorted by Orden ascending: ▲▼ present');
/* a move still works and reports the right position */
{
  const rows = await ed.locator('#the-list tr').evaluateAll((trs) => trs.map((tr) => tr.id.replace('post-', '')));
  const first = rows[0];
  await Promise.all([ed.waitForNavigation(), ed.locator(`#post-${first} a.mats-cpta-move`).first().click()]);
  const after = await ed.locator('#the-list tr').evaluateAll((trs) => trs.map((tr) => tr.id.replace('post-', '')));
  ok(after[1] === first && (await ed.textContent('[data-mats-moved]')).includes('posición 2'), '▼ on the first row: now second, notice «posición 2»');
  await Promise.all([ed.waitForNavigation(), ed.locator(`#post-${first} a.mats-cpta-move[title="Subir"]`).click()]);
  const back = await ed.locator('#the-list tr').evaluateAll((trs) => trs.map((tr) => tr.id.replace('post-', '')));
  ok(back[0] === first, '▲ brings it back to first place');
}

/* ── expert name: an old record (one language-neutral name) ───────── */
const legacy = php('make-legacy');
ok((await html(BASE, '/research/')).includes(legacy.name), 'legacy: /research/ shows the old name');
await ed.goto(`${BASE}/wp-admin/edit.php?post_type=mats_expert`);
{
  const cell = await ed.locator(`#post-${legacy.id} .column-mats_who`).textContent();
  ok(cell.includes(legacy.name) && !cell.includes('Sin firma'), `legacy list: «Persona» shows the name (${cell.trim()})`);
}
await ed.goto(`${BASE}/wp-admin/post.php?post=${legacy.id}&action=edit`);
ok((await ed.inputValue(f('name_en'))) === legacy.name && (await ed.inputValue(f('name_es'))) === legacy.name, 'legacy edit: both name boxes prefilled');
ok((await ed.locator('.mats-cpta__row[data-field="name"] .is-missing').count()) === 0, 'legacy edit: no «Sin traducir» on the name');
ok((await ed.locator('.notice[data-mats-missing="es"]').count()) === 0, 'legacy edit: no Spanish warning');
ok((await ed.textContent('.mats-cpta__intro')).includes('El nombre también va en las dos columnas'), 'expert intro: name in both columns');
await ed.screenshot({ path: `${out}/fix-expert-legacy.png`, fullPage: true });
await ed.fill(f('name_en'), '');
await ed.fill(f('name_es'), '');
await submit(ed);
ok((await ed.textContent('#message')).includes('Cambios guardados'), 'legacy: saved with both boxes empty');
{
  const m = php(`meta:${legacy.id}`);
  ok(!('_mats_name' in m.meta) && m.en === '' && m.es === '', `legacy: old neutral name removed, getter unsigned (${JSON.stringify(m.meta._mats_name ?? null)})`);
}
ok(!(await html(BASE, '/research/')).includes(legacy.name), 'legacy: /research/ no longer shows the name');
ok(!(await html(BASE, '/es/investigacion/')).includes(legacy.name), 'legacy: /es/investigacion/ no longer shows the name');
ok((await html(BASE, '/research/')).includes(`Legacy English quote`), 'legacy: the quote is still on /research/ (unsigned)');
await ed.goto(`${BASE}/wp-admin/edit.php?post_type=mats_expert`);
ok((await ed.locator(`#post-${legacy.id} .column-mats_who`).textContent()).includes('Sin firma'), 'legacy list: now «Sin firma»');
/* give it a name per language again */
await ed.goto(`${BASE}/wp-admin/post.php?post=${legacy.id}&action=edit`);
await ed.fill(f('name_en'), `Dr Signed ${stamp}`);
await ed.fill(f('name_es'), `Dr. Firmado ${stamp}`);
await submit(ed);
ok((await html(BASE, '/research/')).includes(`Dr Signed ${stamp}`) && (await html(BASE, '/es/investigacion/')).includes(`Dr. Firmado ${stamp}`), 'legacy: per-language names on /research/ and /es/investigacion/');

/* the seeded old record of the golden database (post 27), saved untouched */
{
  const m27 = php('meta:27');
  if (m27.meta._mats_name) {
    await ed.goto(`${BASE}/wp-admin/post.php?post=27&action=edit`);
    ok((await ed.inputValue(f('name_en'))) === m27.meta._mats_name, `post 27: EN box shows «${m27.meta._mats_name}»`);
    await submit(ed);
    const a = php('meta:27');
    ok(a.en === m27.meta._mats_name && a.es === m27.meta._mats_name && !('_mats_name' in a.meta), 'post 27: saved untouched → same name on both sites, stored per language');
    ok((await html(BASE, '/research/')).includes(m27.meta._mats_name.replace(/^Dr /, '')), 'post 27: /research/ still shows the name');
  } else {
    console.log('INFO post 27 already migrated:', JSON.stringify(m27));
  }
}

/* ── new evaluation: automatic internal name with the person ───────── */
await ed.goto(`${BASE}/wp-admin/post-new.php?post_type=mats_expert`);
await ed.fill(f('field_en'), `Fix field ${stamp}`);
await ed.fill(f('field_es'), `Campo fix ${stamp}`);
await ed.fill(f('name_en'), `Dr Auto ${stamp}`);
await ed.fill(f('name_es'), `Dr. Auto ${stamp}`);
await ed.fill(f('quote_en'), 'Fix pass quote.');
await ed.fill(f('quote_es'), 'Cita de la revisión.');
/* a browser without a month picker (desktop Firefox / Safari): plain text box */
const pattern = await ed.getAttribute('#mats-cpta-date', 'pattern');
const validity = await ed.evaluate((p) => {
  const i = document.createElement('input');
  i.type = 'text';
  i.pattern = p;
  document.body.appendChild(i);
  const res = {};
  for (const v of ['2026-09', '2026-9', '09/2026', '9/2026', '2026.09', 'septiembre', '2026', '2026-09-01']) {
    i.value = v;
    res[v] = i.validity.valid;
  }
  i.remove();
  return res;
}, pattern);
ok(
  validity['2026-09'] && validity['2026-9'] && validity['09/2026'] && validity['9/2026'] && validity['2026.09'] && !validity.septiembre && !validity['2026'] && !validity['2026-09-01'],
  `month pattern in the browser = server rules (${JSON.stringify(validity)})`
);
ok((await ed.getAttribute('#mats-cpta-date', 'title')) === 'Año y mes, por ejemplo 2026-09', 'month box: title explains the format');
ok((await ed.textContent('#mats-cpta-date-help')).includes('2026-09'), 'month help text: «formato año-mes, por ejemplo 2026-09»');
await ed.evaluate(() => {
  document.getElementById('mats-cpta-date').type = 'text';
});
await ed.fill('#mats-cpta-date', '2026-9');
await submit(ed);
ok(ed.url().includes('post.php') && (await ed.textContent('#message')).includes('Publicada'), 'text date «2026-9» is not blocked by the browser: published');
ok((await ed.inputValue('#title')) === `Fix field ${stamp} — Dr Auto ${stamp}`, `automatic internal name includes the person («${await ed.inputValue('#title')}»)`);
ok((await ed.inputValue('#mats-cpta-date')) === '2026-09', 'date stored as 2026-09');
await ed.goto(`${BASE}/wp-admin/edit.php?post_type=mats_expert`);
await ed.screenshot({ path: `${out}/fix-expert-list.png`, fullPage: true });
{
  const row = ed.locator('#the-list tr', { hasText: `Fix field ${stamp}` });
  ok((await row.locator('.column-mats_who').textContent()).includes(`Dr Auto ${stamp}`), 'new evaluation: «Persona» shows the name');
}
/* year from 1900, like the server */
await ed.goto(`${BASE}/wp-admin/post-new.php?post_type=mats_publication`);
ok((await ed.getAttribute(f('year'), 'min')) === '1900', 'year box: min 1900');
await ed.context().close();

/* ═══ Full site (read only) ═══════════════════════════════════════════ */
if (FULL) {
  const page = await (async () => {
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    const p = await context.newPage();
    /* straight to the list: the dashboard of the full site can be slow (remote widgets) */
    await p.goto(`${FULL}/wp-login.php?redirect_to=${encodeURIComponent(`${FULL}/wp-admin/edit.php?post_type=mats_expert`)}`);
    await p.fill('#user_login', 'admin');
    await p.fill('#user_pass', 'admin-local-pass');
    await Promise.all([p.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 90000 }), p.click('#wp-submit')]);
    return p;
  })();
  watch(page, 'full');
  await page.goto(`${FULL}/wp-admin/edit.php?post_type=mats_expert`);
  const cells = await page.locator('#the-list .column-mats_who').allTextContents();
  ok(cells.some((c) => c.includes('Diego Gutiérrez Yatacue')), `full site list: «Persona» shows Dr Diego (${cells.map((c) => c.trim()).join(' | ')})`);
  await page.screenshot({ path: `${out}/fix-full-expert-list.png`, fullPage: true });
  const firstId = await page.locator('#the-list tr').first().getAttribute('id');
  await page.goto(`${FULL}/wp-admin/post.php?post=${firstId.replace('post-', '')}&action=edit`);
  ok((await page.locator(f('name_en')).count()) === 1 && (await page.locator(f('name_es')).count()) === 1, 'full site: edit screen has the EN and ES name boxes');
  await page.click('#mats-cpta-content .postbox-header h2');
  ok((await page.locator('#mats-cpta-content.closed').count()) === 0, 'full site: «Contenido» stays open after a click');
  await page.screenshot({ path: `${out}/fix-full-expert-edit.png`, fullPage: true });
  for (const type of ['mats_team', 'mats_application', 'mats_publication']) {
    await page.goto(`${FULL}/wp-admin/edit.php?post_type=${type}`);
    ok((await page.locator('#the-list tr').count()) > 0, `full site: ${type} list renders`);
  }
  await page.goto(`${FULL}/wp-admin/edit.php?post_type=mats_team&author=1`);
  ok((await page.locator('#the-list a.mats-cpta-move').count()) === 0, 'full site: no ▲▼ in «Mías»');
  await page.context().close();
}

await browser.close();
ok(jsErrors.length === 0, `no JavaScript errors${jsErrors.length ? ': ' + jsErrors.join(' | ') : ''}`);
console.log(failures ? `\n${failures} FAILED` : '\nALL PASSED');
process.exit(failures ? 1 : 0);
