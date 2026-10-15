/**
 * QA (module "forms", fix pass): what Editors and administrators see and do.
 *  A. an Editor changes Ajustes de la web → Email de contacto; the forms keep
 *     their HTML: a visitor's submission still shows the thank-you <h2>
 *  B. block editor notes as Editor (no WPForms links, "pide ayuda") and as
 *     administrator (link to the WPForms builder, which opens a new tab)
 *  C. a missing form: Editor note "Avisa a la persona que administra la web",
 *     admin note "Ve al Escritorio…", the Dashboard button, and no button on
 *     the setup wizard screen
 * Restores the contact email and the form setting at the end.
 *
 *   WP_BASE=http://localhost:8911 node wordpress/tools/qa/forms-fixpass-roles.mjs <outDir> <enPageId> <esPageId>
 * Needs the users admin/admin-local-pass and qa-editor/qa-editor-pass
 * (forms-fixpass.php creates qa-editor) and the WPForms test stub.
 */
import { writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { launch, closeWelcome, BASE } from './wp-login.mjs';

const out = process.argv[2] || '/tmp';
const enPage = process.argv[3] || '35';
const esPage = process.argv[4] || '36';
const port = new URL(BASE).port;
const php = (code) => {
  const file = `${out}/fixpass-cli.php`;
  writeFileSync(file, `<?php ${code}`);
  return execFileSync('php', ['/home/user/wpsite-cli.php', file], { env: { ...process.env, MATS_WP_PORT: port } }).toString();
};
const forms = () =>
  JSON.parse(
    php(`$o = array(); foreach ( mats_langs() as $l ) { $id = mats_wpforms_form_id( $l ); $d = mats_wpforms_decode( (string) get_post_field( 'post_content', $id, 'raw' ) ); $o[ $l ] = array( 'id' => $id, 'email' => $d['settings']['notifications'][1]['email'] ?? '', 'h2' => false !== strpos( (string) ( $d['settings']['confirmations'][1]['message'] ?? '' ), '<h2>' ), 'link' => false !== strpos( (string) ( $d['fields'][8]['choices'][1]['label'] ?? '' ), '<a ' ) ); } echo wp_json_encode( $o );`)
  );

let failures = 0;
const check = (ok, label, extra = '') => {
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}${extra ? '  — ' + extra : ''}`);
  if (!ok) failures++;
};

const browser = await launch();
const login = async (user, pass) => {
  const ctx = await browser.newContext({ viewport: { width: 1600, height: 1100 } });
  const page = await ctx.newPage();
  page.on('dialog', (d) => d.accept());
  await page.goto(`${BASE}/wp-login.php`);
  await page.fill('#user_login', user);
  await page.fill('#user_pass', pass);
  await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);
  return page;
};

/** Contact block note (+ side-panel notice) in the block editor. */
async function editorNote(page, postId, shot) {
  await page.goto(`${BASE}/wp-admin/post.php?post=${postId}&action=edit`);
  await page.waitForSelector('iframe[name="editor-canvas"], .editor-styles-wrapper', { timeout: 60000 });
  await closeWelcome(page);
  const hasIframe = !!(await page.$('iframe[name="editor-canvas"]'));
  const frame = hasIframe ? page.frameLocator('iframe[name="editor-canvas"]') : page;
  const card = frame.locator('.mats-preview .lf__card').first();
  await card.waitFor({ timeout: 30000 });
  await page.waitForTimeout(800);
  const info = await card.evaluate((el) => ({
    note: el.querySelector('.mats-note')?.innerText.replace(/\s+/g, ' ').trim() || null,
    links: [...el.querySelectorAll('.mats-note a')].map((a) => a.getAttribute('href')),
    form: !!el.querySelector('form.wpforms-form'),
    missing: el.querySelector('.lf__missing')?.innerText.replace(/\s+/g, ' ').trim() || null,
  }));
  /* side panel: select the block, read the notice */
  await frame.locator('.mats-preview .contact__aside-title').first().click({ position: { x: 5, y: 5 } }).catch(() => {});
  await page.waitForTimeout(600);
  /* the notice sits in one of the collapsed panels: open them all */
  for (const btn of await page.locator('.interface-complementary-area .components-panel__body:not(.is-opened) > h2 button').all()) {
    await btn.click().catch(() => {});
  }
  await page.waitForTimeout(300);
  info.panel = await page.evaluate(() => {
    const n = document.querySelector('.mats-notice');
    return n ? { text: n.innerText.replace(/\s+/g, ' ').trim(), href: n.querySelector('a')?.getAttribute('href') || null } : null;
  });
  if (shot) await page.screenshot({ path: shot });
  return { info, frame };
}

const original = forms();
check(original.en.h2 && original.es.h2 && original.en.link && original.es.link, 'start: both forms have the thank-you <h2> and the privacy link', JSON.stringify(original));

/* A — Editor changes the contact email */
const ed = await login('qa-editor', 'qa-editor-pass');
await ed.goto(`${BASE}/wp-admin/admin.php?page=mats-settings`);
await ed.fill('#mats-global-contact_email', 'editor-change@example.org');
await Promise.all([ed.waitForNavigation(), ed.click('#mats_save')]);
const afterEd = forms();
check(afterEd.en.email === 'editor-change@example.org' && afterEd.es.email === 'editor-change@example.org', 'A. notifications follow the email the Editor saved', JSON.stringify(afterEd));
check(afterEd.en.h2 && afterEd.es.h2 && afterEd.en.link && afterEd.es.link, 'A. thank-you <h2> and privacy link survive the Editor save');

{
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 1100 }, reducedMotion: 'reduce' });
  const page = await ctx.newPage();
  await page.goto(`${BASE}/es/contacto/?interest=product`, { waitUntil: 'networkidle' });
  await page.fill('[name="wpforms[fields][1]"]', 'Ada Lovelace');
  await page.fill('[name="wpforms[fields][2]"]', 'ada@example.org');
  await page.fill('[name="wpforms[fields][3]"]', 'ICMAB-CSIC');
  await page.fill('[name="wpforms[fields][4]"]', 'Investigadora');
  await page.fill('[name="wpforms[fields][7]"]', 'Prueba tras el cambio de email');
  await page.check('[name="wpforms[fields][8][]"]');
  await page.click('.wpforms-submit');
  await page.waitForSelector('.lf__card .wpforms-confirmation-container-full', { timeout: 15000 });
  const conf = await page.evaluate(() => {
    const box = document.querySelector('.lf__card .wpforms-confirmation-container-full');
    return { h2: box.querySelector('h2')?.textContent || null, html: box.innerHTML.slice(0, 120) };
  });
  check(!!conf.h2, 'A. visitor submission after the Editor save shows the thank-you title as <h2>', JSON.stringify(conf));
  await page.locator('.lf__card').screenshot({ path: `${out}/fixpass-roles-thankyou-es.png` });
  await ctx.close();
}

/* back to the default email (as the Editor too) */
await ed.goto(`${BASE}/wp-admin/admin.php?page=mats-settings`);
await ed.fill('#mats-global-contact_email', '');
await Promise.all([ed.waitForNavigation(), ed.click('#mats_save')]);
const back = forms();
check(back.en.email === original.en.email && back.es.email === original.es.email && back.en.h2 && back.es.h2, 'A. back to the default address, HTML intact', JSON.stringify(back));

/* B — notes, Editor */
{
  const { info } = await editorNote(ed, esPage, `${out}/fixpass-roles-editor-note.png`);
  console.log('   editor note:', JSON.stringify(info));
  check(info.form && !!info.note, 'B. Editor: real form + note');
  check(!info.links.some((h) => /wpforms|plugins\.php|index\.php/.test(h)), 'B. Editor: no links to WPForms / Plugins / Dashboard in the note', JSON.stringify(info.links));
  check(/persona que administra la web/.test(info.note || ''), 'B. Editor: note says who to ask');
  check(info.links.every((h) => /page=mats-leads/.test(h)), 'B. Editor: only link goes to Contactos', JSON.stringify(info.links));
  check(!!info.panel && !/wpforms-overview/.test(info.panel.href || '') && /administra la web/.test(info.panel.text), 'B. side panel notice is role-neutral', JSON.stringify(info.panel));
}

/* B — notes, admin (+ the link opens a new tab) */
const admin = await login('admin', 'admin-local-pass');
{
  const { info, frame } = await editorNote(admin, enPage, `${out}/fixpass-roles-admin-note.png`);
  console.log('   admin note:', JSON.stringify(info));
  check(info.links.some((h) => /page=wpforms-builder&view=fields&form_id=\d+/.test(h)), 'B. admin: note links to the WPForms builder');
  const popup = admin.context().waitForEvent('page', { timeout: 5000 }).catch(() => null);
  await frame.locator('.mats-preview .lf__card .mats-note a').first().click();
  const tab = await popup;
  check(!!tab, 'B. admin: the note link opens in a new tab', tab ? tab.url() : 'no new tab');
  if (tab) await tab.close();
}

/* C — missing ES form */
php(`mats_update_setting( 'form_es', 999999, 'global' ); echo 'ok';`);
try {
  const { info } = await editorNote(ed, esPage, `${out}/fixpass-roles-editor-missing.png`);
  console.log('   editor missing:', JSON.stringify(info));
  check(!info.form && /Avisa a la persona que administra la web/.test(info.note || '') && info.links.length === 0, 'C. Editor: missing-form note without admin links');
  check(/matsurfer@icmab\.es/.test(info.missing || ''), 'C. Editor preview shows the public email fallback', info.missing);

  const a = await editorNote(admin, esPage, `${out}/fixpass-roles-admin-missing.png`);
  console.log('   admin missing:', JSON.stringify(a.info));
  check(/Crear los formularios de contacto/.test(a.info.note || '') && a.info.links.some((h) => /index\.php/.test(h)), 'C. admin: missing note points to the Dashboard button');

  await admin.goto(`${BASE}/wp-admin/index.php`);
  const dash = await admin.locator('input[name="action"][value="mats_wpforms_create"]').count();
  check(dash === 1, 'C. admin Dashboard shows «Crear los formularios de contacto»', `count ${dash}`);
  await admin.goto(`${BASE}/wp-admin/admin.php?page=mats-setup`);
  const screenId = await admin.evaluate(() => (document.body.className.match(/\b(?:toplevel|[a-z0-9-]+)_page_mats-setup\b/) || [''])[0]);
  const wiz = await admin.locator('input[name="action"][value="mats_wpforms_create"]').count();
  check(wiz === 0, 'C. setup wizard screen: no forms notice (it has its own step)', `count ${wiz}, body class ${screenId}`);
  await admin.screenshot({ path: `${out}/fixpass-roles-wizard.png` });
  await ed.goto(`${BASE}/wp-admin/index.php`);
  const edDash = await ed.locator('input[name="action"][value="mats_wpforms_create"]').count();
  check(edDash === 0, 'C. Editor Dashboard: no button (administrators only)', `count ${edDash}`);
} finally {
  php(`mats_update_setting( 'form_es', ${original.es.id}, 'global' ); echo 'ok';`);
}
const final = forms();
check(final.es.id === original.es.id && final.en.id === original.en.id, 'restored form settings', JSON.stringify(final));

await browser.close();
console.log(failures ? `${failures} FAILED` : 'ALL PASS');
process.exit(failures ? 1 : 0);
