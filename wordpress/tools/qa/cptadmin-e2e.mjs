/**
 * QA (module "cptadmin"): end-to-end test of the Equipo / Aplicaciones /
 * Publicaciones / Valoraciones admin screens, as an Editor and as an
 * administrator: create, edit, reorder, both languages, photo pickers,
 * identifier lock, notices, security guards — then checks the getters
 * (mats_get_team('es')…) and the public pages.
 *
 * Needs: wordpress/tools/qa/cptadmin-setup.php run once on the instance.
 * Run from the repo root:
 *   WP_BASE=http://localhost:8918 MATS_WP_PORT=8918 node wordpress/tools/qa/cptadmin-e2e.mjs <shots-dir>
 */
import { launch, login, BASE } from './wp-login.mjs';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';

const out = process.argv[2] || '.';
fs.mkdirSync(out, { recursive: true });
const PORT = process.env.MATS_WP_PORT || new URL(BASE).port;
const stamp = Date.now().toString(36).slice(-4);

let failures = 0;
const ok = (cond, msg) => {
  console.log(`${cond ? 'PASS' : 'FAIL'} ${msg}`);
  if (!cond) failures++;
};
const cli = (lang = 'es') =>
  JSON.parse(
    execFileSync('php', ['/home/user/wpsite-cli.php', 'wordpress/tools/qa/cptadmin-check.php', lang], {
      env: { ...process.env, MATS_WP_PORT: PORT },
    }).toString()
  );
const html = async (path) => (await fetch(BASE + path)).text();

const browser = await launch();
const jsErrors = [];
const dialogs = [];

function watch(page, who) {
  page.on('pageerror', (e) => jsErrors.push(`${who} ${page.url()} ${e.message}`));
  page.on('dialog', async (d) => {
    dialogs.push(d.type());
    await d.accept();
  });
}

async function submit(page, selector = '#publish') {
  await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click(selector)]);
}

async function pickMedia(page, opener, id) {
  await page.click(opener);
  const modal = page.locator('.media-modal:visible');
  await modal.waitFor();
  const browseTab = modal.locator('.media-router #menu-item-browse');
  if ((await browseTab.count()) && (await browseTab.getAttribute('aria-selected')) !== 'true') await browseTab.click();
  const item = modal.locator(`.attachments .attachment[data-id="${id}"]`);
  await item.waitFor({ timeout: 15000 });
  await item.click();
  await modal.locator('.media-button-select').click();
  await modal.waitFor({ state: 'hidden' });
}

const f = (name) => `[name="mats_cpta[${name}]"]`;

/* ═══ Editor ═══════════════════════════════════════════════════════════ */
const ed = await login(browser, 'editora', 'editora-local-pass');
watch(ed, 'editor');

/* ── Team: create ─────────────────────────────────────────────────── */
const personName = `Ana Prueba ${stamp}`;
await ed.goto(`${BASE}/wp-admin/post-new.php?post_type=mats_team`);
ok((await ed.textContent('.mats-cpta-title-label')).includes('Nombre y apellidos'), 'team: title labelled «Nombre y apellidos»');
ok((await ed.textContent('#postimagediv .hndle, #postimagediv h2')).includes('Retrato'), 'team: featured image box is «Retrato»');
ok((await ed.locator('#pageparentdiv').count()) === 0 && (await ed.locator('#mats-cpta-order').count()) === 1, 'team: «Orden en la web» box replaces «Atributos»');
ok((await ed.locator('#ml_box').count()) === 0, 'team: no Polylang language box');
const newOrder = await ed.inputValue('#mats-cpta-menu-order');
ok(Number(newOrder) >= 1, `team: a new person goes last (Orden ${newOrder})`);
await ed.fill('#title', personName);
await ed.fill(f('role_en'), 'Research engineer QA');
await ed.fill(f('role_es'), 'Ingeniera de investigación QA');
await ed.fill(f('affiliation_en'), 'ICMAB-CSIC QA');
ok(await ed.isVisible('#mats-cpta-affiliation-es-missing'), 'team: live «sin traducir» note while ES is empty');
await ed.fill(f('bio_en'), 'English bio written in the QA test.');
await ed.fill(f('bio_es'), 'Biografía escrita en la prueba de QA.');
await ed.fill(f('linkedin'), 'www.linkedin.com/in/ana-prueba-qa');
await ed.locator(f('linkedin')).blur();
ok((await ed.inputValue(f('linkedin'))) === 'https://www.linkedin.com/in/ana-prueba-qa', 'team: LinkedIn gets https:// on blur');
const longBio = 'x'.repeat(170);
await ed.fill(f('bio_en'), longBio);
ok(await ed.isVisible('#mats-cpta-bio-en-count .mats-cpta__count-over'), 'team: character guide warns over 160');
await ed.fill(f('bio_en'), 'English bio written in the QA test.');

/* cut-out: a JPG warns, a WebP does not */
await pickMedia(ed, '[data-mats-media-choose]', 13);
ok(await ed.isVisible('[data-mats-media-warn]'), 'team: choosing a JPG as cut-out shows the transparency warning');
await pickMedia(ed, '[data-mats-media-choose]', 14);
ok(!(await ed.isVisible('[data-mats-media-warn]')), 'team: a WebP cut-out shows no warning');
ok((await ed.inputValue(f('showcase_image'))) === '14', 'team: cut-out id stored in the field');
ok((await ed.locator('[data-mats-media-preview] img').count()) === 1, 'team: cut-out preview shown');
/* portrait through the core featured image box */
await pickMedia(ed, '#set-post-thumbnail', 13);
await ed.waitForSelector('#postimagediv img');
ok(true, 'team: portrait chosen in «Retrato»');
await ed.screenshot({ path: `${out}/e2e-team-new-filled.png`, fullPage: true });
await submit(ed);
ok((await ed.textContent('#message')).includes('Publicada'), 'team: «Publicada: ya se ve en la web.» message');
ok((await ed.locator('.notice[data-mats-missing="es"]').textContent()).includes('Institución'), 'team: warning lists «Institución» without Spanish');
const teamId = Number(new URL(ed.url()).searchParams.get('post'));
await ed.screenshot({ path: `${out}/e2e-team-saved.png`, fullPage: true });

/* list: indicator, order, move up */
await ed.goto(`${BASE}/wp-admin/edit.php?post_type=mats_team`);
const row = ed.locator(`#post-${teamId}`);
ok((await row.locator('.column-mats_langs').textContent()).includes('Falta ES: Institución'), 'team list: «! Falta ES» indicator');
ok((await ed.locator('#the-list tr').last().getAttribute('id')) === `post-${teamId}`, 'team list: new person is last (website order)');
ok((await ed.locator('th.column-date, th[id^="language_"], th[class*="column-language"]').count()) === 0, 'team list: no date column and no Polylang column');
ok((await ed.locator('#wp-admin-bar-languages').count()) === 0, 'team list: no Polylang language filter in the admin bar');
const before = cli('es').team.map((m) => m.name);
await Promise.all([ed.waitForNavigation(), row.locator('a.mats-cpta-move[title="Subir"]').click()]);
ok((await ed.textContent('.notice[data-mats-moved]')).includes('posición'), 'team list: ▲ moves the person up with a confirmation');
await ed.screenshot({ path: `${out}/e2e-team-list-moved.png`, fullPage: true });
const teamEs = cli('es').team;
const idxBefore = before.indexOf(personName);
const idxAfter = teamEs.findIndex((m) => m.name === personName);
ok(idxBefore === -1 || idxAfter === idxBefore - 1, `getter: mats_get_team order changed (${idxBefore} → ${idxAfter})`);
const me = teamEs.find((m) => m.name === personName) || {};
ok(me.role === 'Ingeniera de investigación QA', 'getter: mats_get_team(es) role in Spanish');
ok(me.affiliation === 'ICMAB-CSIC QA', 'getter: empty Spanish affiliation falls back to English');
ok(me.bio === 'Biografía escrita en la prueba de QA.', 'getter: Spanish bio');
ok(me.linkedin === 'https://www.linkedin.com/in/ana-prueba-qa', 'getter: LinkedIn');
ok(me.showcaseImage && me.showcaseImage.id === 14 && me.image && me.image.id === 13, 'getter: cut-out and portrait');
const teamEn = cli('en').team.find((m) => m.name === personName) || {};
ok(teamEn.role === 'Research engineer QA', 'getter: mats_get_team(en) role in English');
let pub = await html('/es/equipo/');
ok(pub.includes(personName) && pub.includes('Ingeniera de investigación QA'), 'public: /es/equipo/ shows the new person in Spanish');
pub = await html('/team/');
ok(pub.includes(personName) && pub.includes('Research engineer QA'), 'public: /team/ shows the new person in English');

/* unsaved changes warning */
await ed.goto(`${BASE}/wp-admin/post.php?post=${teamId}&action=edit`);
await ed.fill(f('role_en'), 'Unsaved change');
dialogs.length = 0;
await ed.goto(`${BASE}/wp-admin/edit.php?post_type=mats_team`).catch(() => {});
ok(dialogs.includes('beforeunload'), 'edit: leaving with unsaved changes asks first');

/* a forged save without the nonce changes nothing */
await ed.goto(`${BASE}/wp-admin/post.php?post=${teamId}&action=edit`);
await ed.evaluate(() => document.getElementById('mats_cpta_nonce')?.remove());
await ed.fill(f('role_en'), 'Forged');
await submit(ed);
ok(cli('en').raw.mats_team.find((p) => p.id === teamId).meta._mats_role_en === 'Research engineer QA', 'security: a save without the nonce does not touch the fields');

/* hide it (Borrador) → gone from getters and page */
await ed.goto(`${BASE}/wp-admin/post.php?post=${teamId}&action=edit`);
await ed.click('#post-status-select').catch(() => {});
await ed.click('a.edit-post-status');
await ed.selectOption('#post_status', 'draft');
await ed.click('.save-post-status');
await submit(ed);
ok(!cli('es').team.some((m) => m.name === personName), 'draft: hidden person is not returned by mats_get_team');
ok(!(await html('/es/equipo/')).includes(personName), 'draft: hidden person disappears from /es/equipo/');
await ed.goto(`${BASE}/wp-admin/edit.php?post_type=mats_team`);
ok((await ed.locator(`#post-${teamId} .column-mats_status`).textContent()).includes('Oculta'), 'team list: «Oculta · borrador» status');
await ed.goto(`${BASE}/wp-admin/post.php?post=${teamId}&action=edit`);
await submit(ed, '#publish');
ok(cli('es').team.some((m) => m.name === personName), 'publish again: back on the site');

/* wrong nonce on ▲▼ */
const bad = await ed.goto(`${BASE}/wp-admin/admin-post.php?action=mats_cpta_move&post=${teamId}&dir=up&_wpnonce=bad`);
ok(bad.status() === 403, `security: ▲▼ with a wrong nonce is refused (${bad.status()})`);

/* ── Applications ─────────────────────────────────────────────────── */
await ed.goto(`${BASE}/wp-admin/post-new.php?post_type=mats_application`);
ok((await ed.textContent('.mats-cpta-title-label')).includes('Nombre interno (solo para el panel)'), 'apps: title labelled «Nombre interno (solo para el panel)»');
const appTitle = `Thin-film catalysis ${stamp}`;
await ed.fill(f('title_en'), appTitle);
ok((await ed.inputValue(f('slug'))) === `thin-film-catalysis-${stamp}`, 'apps: identifier filled from the English title');
await ed.fill(f('title_es'), `Catálisis en película fina ${stamp}`);
await ed.fill(f('tag_en'), 'catalysts · thin films');
await ed.fill(f('tag_es'), 'catalizadores · películas finas');
for (const [k, en, es] of [
  ['challenge', 'Catalysis groups need many surface variations.', 'Los grupos de catálisis necesitan muchas variaciones de superficie.'],
  ['use', 'MatSurfer prepares them in parallel.', 'MatSurfer las prepara en paralelo.'],
  ['benefit', 'Faster screening.', 'Cribado más rápido.'],
]) {
  await ed.fill(f(`${k}_en`), en);
  await ed.fill(f(`${k}_es`), es);
}
await ed.fill('#mats-cpta-menu-order', '0');
await submit(ed);
const appId = Number(new URL(ed.url()).searchParams.get('post'));
ok((await ed.inputValue('#title')) === appTitle, 'apps: empty internal name filled from the English title');
ok(await ed.locator(f('slug')).evaluate((i) => i.readOnly), 'apps: identifier locked after publishing');
ok(await ed.isVisible('[data-mats-slug-change]'), 'apps: «Cambiar» button shown');
await ed.screenshot({ path: `${out}/e2e-app-saved.png`, fullPage: true });

/* tampering without «Cambiar» is ignored */
await ed.evaluate(() => {
  const i = document.querySelector('[name="mats_cpta[slug]"]');
  i.removeAttribute('readonly');
  i.value = 'hacked';
});
await submit(ed);
ok((await ed.inputValue(f('slug'))) === `thin-film-catalysis-${stamp}`, 'apps: identifier cannot change without «Cambiar»');

/* «Cambiar» to a duplicate → made unique */
await ed.click('[data-mats-slug-change]');
ok(await ed.isVisible('[data-mats-slug-warn]'), 'apps: «Cambiar» shows the broken-links warning');
await ed.fill(f('slug'), 'sensors');
await submit(ed);
ok((await ed.inputValue(f('slug'))) === 'sensors-2', 'apps: duplicate identifier saved as «sensors-2»');
ok((await ed.textContent('.mats-cpta-notice')).includes('ya lo usa otra aplicación'), 'apps: duplicate identifier explained');
await ed.click('[data-mats-slug-change]');
await ed.fill(f('slug'), `Catálisis Films ${stamp}`);
await ed.locator(f('slug')).blur();
await submit(ed);
const appSlug = `catalisis-films-${stamp}`;
ok((await ed.inputValue(f('slug'))) === appSlug, `apps: identifier changed and cleaned («${appSlug}»)`);

const appsEs = cli('es').applications;
ok(appsEs[0] && appsEs[0].slug === appSlug && appsEs[0].title === `Catálisis en película fina ${stamp}`, 'getter: mats_get_applications(es) first = new application (Orden 0) in Spanish');
ok(appsEs[0] && appsEs[0].benefit === 'Cribado más rápido.', 'getter: Spanish benefit');
pub = await html('/es/aplicaciones/');
ok(pub.includes(`id="${appSlug}"`) && pub.includes(`Catálisis en película fina ${stamp}`), 'public: /es/aplicaciones/ shows the application with its anchor');
pub = await html('/applications/');
ok(pub.includes(appTitle) && pub.includes(`application=${appSlug}`), 'public: /applications/ shows it with the contact link');
await ed.goto(`${BASE}/wp-admin/edit.php?post_type=mats_application`);
ok((await ed.locator('#the-list tr').first().getAttribute('id')) === `post-${appId}`, 'apps list: Orden 0 puts it first');
const viewEs = await ed.locator(`#post-${appId} .row-actions .mats_view_es a`).getAttribute('href');
ok(viewEs && viewEs.endsWith(`/es/aplicaciones/#${appSlug}`), 'apps list: «Ver en la web (ES)» links to the anchor');
/* move down with ▼ */
await Promise.all([ed.waitForNavigation(), ed.locator(`#post-${appId} a.mats-cpta-move[title="Bajar"]`).click()]);
ok((await ed.locator('#the-list tr').nth(1).getAttribute('id')) === `post-${appId}`, 'apps list: ▼ moves it to second place');
ok(cli('es').applications[1].slug === appSlug, 'getter: application order follows ▼');
await ed.screenshot({ path: `${out}/e2e-apps-list.png`, fullPage: true });

/* ── Publications ─────────────────────────────────────────────────── */
await ed.goto(`${BASE}/wp-admin/post-new.php?post_type=mats_publication`);
ok((await ed.textContent('.mats-cpta-title-label')).includes('Título del artículo'), 'pubs: title labelled «Título del artículo»');
ok((await ed.textContent('#postimagediv .hndle, #postimagediv h2')).includes('Figura'), 'pubs: featured image box is «Figura»');
const paper = `QA paper on vapour deposition ${stamp}`;
await ed.fill('#title', paper);
await ed.fill(f('authors'), 'A. Prueba, B. Test');
await ed.fill(f('journal'), 'Journal of QA');
await ed.fill(f('year'), '2026');
await ed.fill(f('doi'), 'https://doi.org/10.1234/qa.' + stamp);
await ed.locator(f('doi')).blur();
ok((await ed.inputValue(f('doi'))) === '10.1234/qa.' + stamp, 'pubs: DOI keeps only the code');
await ed.fill(f('description_en'), 'English description of the QA paper.');
await ed.fill(f('description_es'), 'Descripción en español del artículo de QA.');
await submit(ed);
ok((await ed.inputValue(f('url'))) === `https://doi.org/10.1234/qa.${stamp}`, 'pubs: empty link filled from the DOI');
const pubsEs = cli('es').publications;
const pIdx = pubsEs.findIndex((p) => p.title === paper);
const firstOlder = pubsEs.findIndex((p) => p.year < 2026);
ok(pIdx !== -1 && pubsEs[pIdx].year === 2026 && (firstOlder === -1 || pIdx < firstOlder), 'getter: mats_get_publications(es) newest year first');
ok(pIdx !== -1 && pubsEs[pIdx].description === 'Descripción en español del artículo de QA.', 'getter: Spanish description');
pub = await html('/es/investigacion/');
ok(pub.includes(paper) && pub.includes('Descripción en español del artículo de QA.'), 'public: /es/investigacion/ shows the publication in Spanish');
await ed.goto(`${BASE}/wp-admin/edit.php?post_type=mats_publication`);
{
  const rows = await ed.locator('#the-list tr').evaluateAll((trs) => trs.map((tr) => [tr.querySelector('.row-title')?.textContent || '', Number(tr.querySelector('.column-mats_year')?.textContent || 0)]));
  const at = rows.findIndex((r) => r[0].includes(paper));
  const years = rows.map((r) => r[1]);
  ok(at !== -1 && years.every((y, i) => i === 0 || y <= years[i - 1]), 'pubs list: newest year first (website order)');
}
ok((await ed.locator('#the-list tr .mats-cpta-move').count()) === 0, 'pubs list: no ▲▼ (ordered by year)');

/* ── Expert evaluations ───────────────────────────────────────────── */
await ed.goto(`${BASE}/wp-admin/post-new.php?post_type=mats_expert`);
ok((await ed.textContent('.mats-cpta-title-label')).includes('Nombre interno'), 'experts: title labelled «Nombre interno»');
await ed.fill(f('field_en'), `QA surfaces ${stamp}`);
await ed.fill(f('field_es'), `Superficies QA ${stamp}`);
await ed.fill(f('quote_en'), 'The QA test wrote this English quote.');
await ed.fill(f('quote_es'), 'La prueba de QA escribió esta cita en español.');
await ed.fill(f('date'), '2026-12');
await submit(ed);
ok((await ed.inputValue('#title')) === `QA surfaces ${stamp}`, 'experts: empty internal name filled from the field');
const expEs = cli('es').experts;
const eIdx = expEs.findIndex((e) => e.field === `Superficies QA ${stamp}`);
const dates = expEs.map((e) => e.date);
ok(eIdx !== -1 && expEs[eIdx].date === '2026-12' && dates.every((d, i) => i === 0 || d <= dates[i - 1]), 'getter: mats_get_experts(es) newest first, in Spanish');
ok(eIdx !== -1 && expEs[eIdx].quote === 'La prueba de QA escribió esta cita en español.', 'getter: Spanish quote');
pub = await html('/es/investigacion/');
ok(pub.includes('La prueba de QA escribió esta cita en español.'), 'public: /es/investigacion/ shows the Spanish quote');
pub = await html('/research/');
ok(pub.includes('The QA test wrote this English quote.'), 'public: /research/ shows the English quote');
await ed.goto(`${BASE}/wp-admin/edit.php?post_type=mats_expert`);
ok((await ed.locator('#the-list tr .column-mats_date').first().textContent()).includes('diciembre de 2026'), 'experts list: newest date first, month in Spanish');

/* incomplete draft → required + language warnings */
await ed.goto(`${BASE}/wp-admin/post-new.php?post_type=mats_expert`);
await ed.fill(f('field_en'), `Draft only English ${stamp}`);
await submit(ed, '#save-post');
ok((await ed.textContent('#message')).includes('Borrador guardado'), 'experts: «Borrador guardado: todavía no se ve en la web.»');
ok((await ed.textContent('.notice[data-mats-missing="required"]')).includes('Cita'), 'experts: «Faltan datos importantes» lists «Cita»');
ok((await ed.textContent('.notice[data-mats-missing="es"]')).includes('Campo científico'), 'experts: Spanish translation warning');
await ed.screenshot({ path: `${out}/e2e-expert-draft.png`, fullPage: true });

await ed.context().close();

/* ═══ Administrator ════════════════════════════════════════════════════ */
const ad = await login(browser, 'admin', 'admin-local-pass');
watch(ad, 'admin');
const daniel = cli('es').raw.mats_team.find((p) => p.title === 'Daniel Herrera');
await ad.goto(`${BASE}/wp-admin/post.php?post=${daniel.id}&action=edit`);
const newBio = `Investigador posdoctoral (editado por QA ${stamp}).`;
await ad.fill(f('bio_es'), newBio);
await submit(ad);
ok((await ad.textContent('#message')).includes('Cambios guardados'), 'admin: «Cambios guardados» after Update');
ok(cli('es').team.find((m) => m.name === 'Daniel Herrera').bio === newBio, 'admin: edited Spanish bio returned by the getter');
ok((await html('/es/equipo/')).includes(newBio), 'admin: edited bio on /es/equipo/');
/* the Spanish affiliation of the QA person, then the warning is gone */
await ad.goto(`${BASE}/wp-admin/post.php?post=${teamId}&action=edit`);
await ad.fill(f('affiliation_es'), 'ICMAB-CSIC QA (ES)');
await submit(ad);
ok((await ad.locator('.notice[data-mats-missing="es"]').count()) === 0, 'admin: warning disappears once Spanish is filled');
await ad.goto(`${BASE}/wp-admin/edit.php?post_type=mats_team`);
ok((await ad.locator(`#post-${teamId} .column-mats_langs`).textContent()).includes('EN · ES'), 'admin list: ✓ EN · ES once complete');
/* quick edit order */
await ad.locator(`#post-${teamId}`).hover();
await ad.locator(`#post-${teamId} button.editinline`).click();
const qe = ad.locator('.inline-edit-row:visible');
ok(!(await qe.locator('.inline-edit-date').isVisible()) && !(await qe.locator('.inline-edit-password-input').isVisible()), 'quick edit: date and password hidden');
await qe.locator('input[name="menu_order"]').fill('1');
await qe.locator('button.save').click();
await ad.waitForSelector(`#post-${teamId}:not(.inline-editor)`);
await ad.waitForTimeout(500);
ok(cli('es').raw.mats_team.find((p) => p.id === teamId).order === 1, 'quick edit: order saved');
/* Polylang control: pages still have their language column */
await ad.goto(`${BASE}/wp-admin/edit.php?post_type=page`);
ok((await ad.locator('th[class*="column-language_"]').count()) > 0, 'control: Polylang language column still on Pages');
for (const type of ['mats_team', 'mats_application', 'mats_publication', 'mats_expert']) {
  await ad.goto(`${BASE}/wp-admin/edit.php?post_type=${type}`);
  ok((await ad.locator('th[class*="column-language_"]').count()) === 0, `${type}: no Polylang language column (admin)`);
}
await ad.context().close();
await browser.close();

ok(jsErrors.length === 0, `no JavaScript errors${jsErrors.length ? ': ' + jsErrors.join(' | ') : ''}`);
console.log(failures ? `\n${failures} FAILED` : '\nALL PASSED');
process.exit(failures ? 1 : 0);
