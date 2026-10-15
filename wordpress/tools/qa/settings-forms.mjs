/**
 * QA (module "settings"): contact email ↔ WPForms notifications, and the
 * administrators' "Formularios de contacto" table.
 *   node wordpress/tools/qa/settings-forms.mjs <outDir>
 * Needs WPForms (or the QA stub tools/qa/wpforms-stub) active in the
 * instance and the forms created (mats_wpforms_create_forms()).
 */
import { mkdirSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { launch } from './wp-login.mjs';

const out = process.argv[2] || '/tmp/settings-forms';
mkdirSync(out, { recursive: true });
const WP = process.env.WP_BASE || 'http://localhost:8917';
const SCREEN = `${WP}/wp-admin/admin.php?page=mats-settings`;
const php = (code) => {
  const file = `${out}/cli.php`;
  writeFileSync(file, `<?php ${code}`);
  return execFileSync('php', ['/home/user/wpsite-cli.php', file], { env: { ...process.env, MATS_WP_PORT: new URL(WP).port } }).toString();
};
const notify = () =>
  JSON.parse(
    php(`$o = array(); foreach ( mats_langs() as $l ) { $d = mats_wpforms_decode( (string) get_post_field( 'post_content', mats_wpforms_form_id( $l ), 'raw' ) ); $o[ $l ] = array_values( array_map( fn( $n ) => $n['email'] ?? '', (array) ( $d['settings']['notifications'] ?? array() ) ) ); } echo wp_json_encode( $o );`)
  );
let failures = 0;
const check = (ok, label, extra = '') => {
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}${extra ? '  — ' + extra : ''}`);
  if (!ok) failures++;
};

const browser = await launch();
const login = async (user, pass) => {
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await ctx.newPage();
  page.on('dialog', (d) => d.accept());
  await page.goto(`${WP}/wp-login.php`);
  await page.fill('#user_login', user);
  await page.fill('#user_pass', pass);
  await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);
  return page;
};

const before = notify();
check(before.en[0] === 'matsurfer@icmab.es' && before.es[0] === 'matsurfer@icmab.es', 'forms created with the contact email', JSON.stringify(before));

const admin = await login('admin', 'admin-local-pass');
await admin.goto(SCREEN);
const rows = await admin.$$eval('#mats-sec-forms tbody tr', (trs) => trs.map((tr) => tr.textContent.replace(/\s+/g, ' ').trim()));
check(rows.length === 2 && rows.every((r) => r.includes('matsurfer@icmab.es') && r.includes('Abrir en WPForms')), 'admin table: both forms, notification address, link to WPForms', rows.join(' | '));
const link = await admin.getAttribute('#mats-sec-forms tbody tr a', 'href');
check(/page=wpforms-builder&view=fields&form_id=\d+/.test(link), 'link opens the form in the WPForms builder', link);
await admin.locator('#mats-sec-forms').screenshot({ path: `${out}/forms-table.png` });
await admin.context().close();

/* the editor changes the email: both notifications follow */
const ed = await login('editora', 'editora-local-pass');
await ed.goto(SCREEN);
await ed.fill('#mats-global-contact_email', 'tt@icmab.es');
await Promise.all([ed.waitForNavigation(), ed.click('#mats_save')]);
const after = notify();
check(after.en[0] === 'tt@icmab.es' && after.es[0] === 'tt@icmab.es', 'WPForms notifications follow the new contact email', JSON.stringify(after));
/* the section restore never touches the email (the original address is not confirmed as a real inbox) */
await Promise.all([ed.waitForNavigation(), ed.click('button.mats-set__restore[value="contact"]')]);
const kept = notify();
check(kept.en[0] === 'tt@icmab.es' && kept.es[0] === 'tt@icmab.es', 'restoring the section leaves the forms\' address as it is', JSON.stringify(kept));
/* typing the original address back: the forms follow */
await ed.fill('#mats-global-contact_email', 'matsurfer@icmab.es');
await Promise.all([ed.waitForNavigation(), ed.click('#mats_save')]);
const back = notify();
check(back.en[0] === 'matsurfer@icmab.es' && back.es[0] === 'matsurfer@icmab.es', 'typing the original address back puts it back in the forms', JSON.stringify(back));
await ed.context().close();

await browser.close();
console.log(failures ? `${failures} check(s) failed` : 'all checks passed');
process.exit(failures ? 1 : 0);
