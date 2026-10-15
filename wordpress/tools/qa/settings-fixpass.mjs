/**
 * QA (module "settings", fix pass): checks for the review findings.
 *   node wordpress/tools/qa/settings-fixpass.mjs <outDir>
 *  A. patent code ↔ footer line: restoring "Pie de página" keeps the current
 *     code, later changes keep reaching the footer, hand-written lines get
 *     the new code (or a warning when they do not carry the old one)
 *  B. text cleaning: "<50 nm", "T<300 K", "50%AB" survive; real HTML is
 *     removed with a notice naming the field
 *  C. contact email: a save that does not touch it confirms nothing; the
 *     "Confirmo…" box does; empty is refused; the section restore keeps it;
 *     a change emails the previous address + the administrator
 *  D. scroll reveal without JavaScript / with site.js blocked
 * Needs settings-setup.php (pages + users editora / autora), the WPForms
 * stub and the QA mail capture plugin (activated here, deactivated at the
 * end). The option is put back as it was. Exit 1 on failure.
 */
import { mkdirSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { launch } from './wp-login.mjs';

const out = process.argv[2] || '/tmp/settings-fixpass';
mkdirSync(out, { recursive: true });
const WP = process.env.WP_BASE || 'http://localhost:8917';
const SCREEN = `${WP}/wp-admin/admin.php?page=mats-settings`;
const PORT = new URL(WP).port;
const php = (code) => {
  const file = `${out}/cli.php`;
  writeFileSync(file, `<?php ${code}`);
  return execFileSync('php', ['/home/user/wpsite-cli.php', file], { env: { ...process.env, MATS_WP_PORT: PORT } }).toString();
};
let failures = 0;
const check = (ok, label, extra = '') => {
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}${extra ? '  — ' + extra : ''}`);
  if (!ok) failures++;
};
const option = () => JSON.parse(php("echo wp_json_encode( get_option( 'mats_settings' ) );"));

async function login(browser, user, pass) {
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage();
  page.on('dialog', (d) => d.accept());
  await page.goto(`${WP}/wp-login.php`);
  await page.fill('#user_login', user);
  await page.fill('#user_pass', pass);
  await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);
  return page;
}
async function footer(browser, url) {
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const p = await ctx.newPage();
  const resp = await p.goto(url);
  const html = await resp.text();
  const data = await p.evaluate(() => ({
    bottom: document.querySelector('.footer__bottom .mono')?.textContent.trim(),
    desc: document.querySelector('.footer__desc')?.textContent.trim(),
    fcta: document.querySelector('.footer__cta')?.textContent.trim(),
    cta: document.querySelector('.header__cta')?.textContent.trim(),
    mail: [...document.querySelectorAll('.footer__nav a')].find((a) => a.href.startsWith('mailto:'))?.getAttribute('href'),
  }));
  await ctx.close();
  return { ...data, html };
}
const save = (page) => Promise.all([page.waitForNavigation(), page.click('#mats_save')]);
const restore = (page, section) => Promise.all([page.waitForNavigation(), page.click(`button.mats-set__restore[value="${section}"]`)]);
const notices = (page) => page.$$eval('.mats-set__notice', (els) => els.map((e) => `${e.className.match(/notice-(\w+)/)[1]}: ${e.querySelector('p').textContent.trim()}`));

/* starting point: originals everywhere (other modules' keys kept), email not confirmed */
writeFileSync(`${out}/before.json`, php("echo wp_json_encode( get_option( 'mats_settings' ) );").trim());
php(`require_once ABSPATH . 'wp-admin/includes/plugin.php';
$s = get_option( 'mats_settings' ); $s['en'] = array(); $s['es'] = array(); unset( $s['global']['contact_email'], $s['global']['patent_code'] ); update_option( 'mats_settings', $s );
activate_plugin( 'wpforms-stub/qa-mail-capture.php' ); delete_option( 'wpforms_stub_mails' );`);

const browser = await launch();
const ed = await login(browser, 'editora', 'editora-local-pass');

/* ── A. patent code ─────────────────────────────────────────────────── */
await ed.goto(SCREEN);
await ed.fill('#mats-global-patent_code', 'EP 9999 B2');
await save(ed);
let en = await footer(browser, `${WP}/`);
let es = await footer(browser, `${WP}/es/`);
check(en.bottom === 'EP 9999 B2 · Surfaces for all.' && es.bottom === 'EP 9999 B2 · Superficies para todos.', 'A1 new patent code in the footer (EN + ES)', `${en.bottom} | ${es.bottom}`);
let opt = option();
check(!('footer_bottom' in opt.en) && !('footer_bottom' in opt.es) && opt.global.patent_code === 'EP 9999 B2', 'A1 footer line not stored (original line with the current code)', JSON.stringify({ en: opt.en, es: opt.es }));

await restore(ed, 'footer');
en = await footer(browser, `${WP}/`);
es = await footer(browser, `${WP}/es/`);
check(en.bottom === 'EP 9999 B2 · Surfaces for all.' && es.bottom === 'EP 9999 B2 · Superficies para todos.', 'A2 restoring "Pie de página" keeps the current patent code', `${en.bottom} | ${es.bottom}`);
const field = await ed.$eval('#mats-en-footer_bottom', (i) => ({ value: i.value, ph: i.placeholder, orig: i.closest('.mats-set__field').querySelector('.mats-set__origtext').textContent, hidden: i.closest('.mats-set__field').querySelector('.mats-set__orig').hidden }));
check(field.value === 'EP 9999 B2 · Surfaces for all.' && field.ph === field.value && field.orig === field.value && field.hidden, 'A2 screen: the original line is shown with the current code', JSON.stringify(field));

await ed.fill('#mats-global-patent_code', 'EP 1111 A1');
await save(ed);
let n = await notices(ed);
en = await footer(browser, `${WP}/`);
es = await footer(browser, `${WP}/es/`);
check(en.bottom === 'EP 1111 A1 · Surfaces for all.' && es.bottom === 'EP 1111 A1 · Superficies para todos.', 'A3 a later patent change still reaches the footer', `${en.bottom} | ${es.bottom}`);
check(n.some((t) => t.startsWith('info:') && t.includes('EP 1111 A1') && t.includes('en inglés y en español')), 'A3 sync notice', n.join(' / '));

/* a line written by hand keeps following the code */
await ed.fill('#mats-en-footer_bottom', 'EP 1111 A1 · Surfaces for everyone');
await save(ed);
await ed.fill('#mats-global-patent_code', 'EP 2222 C3');
await save(ed);
en = await footer(browser, `${WP}/`);
es = await footer(browser, `${WP}/es/`);
check(en.bottom === 'EP 2222 C3 · Surfaces for everyone' && es.bottom === 'EP 2222 C3 · Superficies para todos.', 'A4 hand-written line gets the new code', `${en.bottom} | ${es.bottom}`);

/* a line without the code: untouched, with a warning */
await ed.fill('#mats-es-footer_bottom', 'Superficies para todos');
await save(ed);
await ed.fill('#mats-global-patent_code', 'EP 3333 D4');
await save(ed);
n = await notices(ed);
es = await footer(browser, `${WP}/es/`);
check(es.bottom === 'Superficies para todos' && n.some((t) => t.startsWith('warning:') && t.includes('en español') && t.includes('EP 3333 D4')), 'A5 line without the code: untouched + warning', `${es.bottom} — ${n.join(' / ')}`);
check(n.some((t) => t.startsWith('info:') && t.includes('en inglés') && !t.includes('en español')), 'A5 sync notice names only the synced language', n.join(' / '));

/* "Volver al original" on the line → original line with the current code, not stored */
await ed.click('#mats-es-footer_bottom >> xpath=ancestor::div[contains(@class,"mats-set__field")]//button[contains(@class,"mats-set__back")]');
check((await ed.inputValue('#mats-es-footer_bottom')) === 'EP 3333 D4 · Superficies para todos.', 'A6 "Volver al original" uses the current code', await ed.inputValue('#mats-es-footer_bottom'));
await save(ed);
opt = option();
check(!('footer_bottom' in opt.es), 'A6 back to the original: nothing stored', JSON.stringify(opt.es));

/* a line saved by the old code with the very first patent code (data from before this fix) */
php(`$s = get_option( 'mats_settings' ); $s['en']['footer_bottom'] = 'EP 4483 979 B1 · Custom'; update_option( 'mats_settings', $s );`);
await ed.goto(SCREEN);
await ed.fill('#mats-global-patent_code', 'EP 5555 E5');
await save(ed);
en = await footer(browser, `${WP}/`);
check(en.bottom === 'EP 5555 E5 · Custom', 'A7 a line still carrying the first code gets the new one', en.bottom);

/* restoring "Email de contacto y patente" brings the original code back everywhere */
const confirmText = await ed.getAttribute('button.mats-set__restore[value="contact"]', 'data-confirm');
check(confirmText.includes('«Código de la patente»') && confirmText.includes('«Email de contacto» no cambia'), 'A8 the restore confirmation says the email does not change', confirmText);
await restore(ed, 'contact');
n = await notices(ed);
en = await footer(browser, `${WP}/`);
es = await footer(browser, `${WP}/es/`);
check(en.bottom === 'EP 4483 979 B1 · Custom' && es.bottom === 'EP 4483 979 B1 · Superficies para todos.', 'A8 patent restored: footer lines follow', `${en.bottom} | ${es.bottom}`);
check(n.some((t) => t.startsWith('success:') && t.includes('salvo «Email de contacto»')), 'A8 restore notice mentions the kept email', n.join(' / '));
await ed.screenshot({ path: `${out}/patent-restore.png`, clip: { x: 0, y: 0, width: 1440, height: 600 } });
await restore(ed, 'footer');

/* ── B. text cleaning ───────────────────────────────────────────────── */
await ed.goto(SCREEN);
await ed.fill('#mats-en-footer_description', 'Deposition of films <50 nm thick at T<300 K & more');
await ed.fill('#mats-es-header_cta', 'Ahorra 50%AB ya');
await save(ed);
n = await notices(ed);
check(n[0]?.startsWith('success: Cambios guardados') && !n.some((t) => t.startsWith('warning:')), 'B1 no warning for plain text with "<"', n.join(' / '));
opt = option();
en = await footer(browser, `${WP}/`);
es = await footer(browser, `${WP}/es/`);
check(opt.en.footer_description === 'Deposition of films <50 nm thick at T<300 K & more' && en.desc === opt.en.footer_description, 'B1 "<50 nm" / "T<300 K" kept and shown', `${opt.en.footer_description} | ${en.desc}`);
check(en.html.includes('Deposition of films &lt;50 nm thick at T&lt;300 K &amp; more'), 'B1 escaped in the page source');
check(opt.es.header_cta === 'Ahorra 50%AB ya' && es.cta === 'Ahorra 50%AB ya', 'B2 "50%AB" kept', `${opt.es.header_cta} | ${es.cta}`);
await ed.fill('#mats-es-footer_cta', '<em>Escríbenos</em> <img src=x onerror=alert(1)>');
await save(ed);
n = await notices(ed);
es = await footer(browser, `${WP}/es/`);
check(es.fcta === 'Escríbenos' && n.some((t) => t.startsWith('warning:') && t.includes('«Botón» (Pie de página, en español)')), 'B3 HTML removed with a notice naming the field', `${es.fcta} — ${n.join(' / ')}`);
await restore(ed, 'footer');
await restore(ed, 'header');

/* ── C. contact email ───────────────────────────────────────────────── */
await ed.goto(SCREEN);
check(await ed.isVisible('.mats-set__confirm'), 'C1 not confirmed yet: the "Confirmo…" box shows');
await ed.locator('#mats-sec-contact').screenshot({ path: `${out}/email-unconfirmed.png` });
await save(ed);
n = await notices(ed);
opt = option();
check(n[0] === 'success: No había nada nuevo que guardar.' && !('contact_email' in opt.global), 'C2 a save without touching the email confirms nothing', `${n.join(' / ')} | ${JSON.stringify(opt.global)}`);
await ed.check('input[name="mats_email_confirm"]');
check(await ed.isVisible('.mats-set__dirty'), 'C3 ticking the box marks unsaved changes');
await save(ed);
n = await notices(ed);
opt = option();
check(opt.global.contact_email === 'matsurfer@icmab.es' && n[0]?.startsWith('success: Cambios guardados') && !n.some((t) => t.includes('ahora es')), 'C3 the box confirms the original address (no "changed" warning)', `${n.join(' / ')} | ${JSON.stringify(opt.global)}`);
check((await ed.$$('.mats-set__confirm')).length === 0, 'C3 confirmed: the box is gone');
check(JSON.parse(php("$s = get_option( 'mats_settings' ); echo wp_json_encode( ! empty( $s['global']['contact_email'] ) );")) === true, 'C3 installer review sees the email as confirmed');
check(php("echo count( (array) get_option( 'wpforms_stub_mails', array() ) );").trim() === '0', 'C3 no change notice for a confirmation');

await ed.fill('#mats-global-contact_email', '');
await save(ed);
n = await notices(ed);
opt = option();
check(n.some((t) => t.startsWith('error:') && t.includes('no puede quedar vacío')) && !n.some((t) => t.startsWith('success:')) && opt.global.contact_email === 'matsurfer@icmab.es', 'C4 empty email refused, address kept', n.join(' / '));
check((await ed.inputValue('#mats-global-contact_email')) === 'matsurfer@icmab.es' && (await ed.getAttribute('#mats-global-contact_email', 'aria-invalid')) === 'true', 'C4 field shows the current address, marked');

await ed.fill('#mats-global-contact_email', 'leads@icmab.es');
await save(ed);
n = await notices(ed);
const mails = JSON.parse(php("echo wp_json_encode( get_option( 'wpforms_stub_mails', array() ) );"));
const mail = mails[mails.length - 1] || {};
const to = [].concat(mail.to || []).join(',');
check(to.includes('matsurfer@icmab.es') && to.includes(php("echo get_option( 'admin_email' );").trim()), 'C5 change notice sent to the previous address + the administrator', to);
check(/editora/.test(mail.message || '') && (mail.message || '').includes('Antes: matsurfer@icmab.es') && (mail.message || '').includes('Ahora: leads@icmab.es'), 'C5 notice names who changed it, old and new address', (mail.message || '').split('\n').slice(0, 6).join(' | '));
check(n.some((t) => t.startsWith('warning:') && t.includes('leads@icmab.es') && t.includes('Se ha enviado un aviso del cambio a')), 'C5 screen says the notice was sent', n.join(' / '));
const notif = php("$out = array(); foreach ( mats_langs() as $l ) { $id = mats_wpforms_form_id( $l ); if ( $id ) { $d = mats_wpforms_decode( (string) get_post_field( 'post_content', $id, 'raw' ) ); foreach ( (array) ( $d['settings']['notifications'] ?? array() ) as $x ) { $out[] = $x['email'] ?? ''; } } } echo implode( ',', $out );").trim();
check(notif !== '' && notif.split(',').every((e) => e === 'leads@icmab.es'), 'C5 WPForms notifications follow', notif);

await restore(ed, 'contact');
opt = option();
en = await footer(browser, `${WP}/`);
check(opt.global.contact_email === 'leads@icmab.es' && en.mail === 'mailto:leads@icmab.es', 'C6 section restore keeps the contact email', JSON.stringify(opt.global));
await ed.context().close();

/* ── D. reveal without JavaScript ───────────────────────────────────── */
async function revealState(ctxOpts, url, prep) {
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, ...ctxOpts });
  const p = await ctx.newPage();
  if (prep) await prep(p);
  await p.goto(url);
  const get = () =>
    p.evaluate(() => ({
      cls: document.documentElement.className,
      ops: [...document.querySelectorAll('.reveal')].map((el) => getComputedStyle(el).opacity),
    }));
  const first = await get();
  return { p, ctx, first, get };
}
for (const base of [WP, 'http://localhost:8950']) {
  for (const path of base === WP ? ['/qa-reveal/'] : ['/', '/product/', '/es/equipo/']) {
    const r = await revealState({ javaScriptEnabled: false }, base + path);
    check(r.first.ops.length > 0 && r.first.ops.every((o) => o === '1') && !r.first.cls.includes('mats-js'), `D1 no JavaScript: every .reveal visible on ${base}${path}`, `${r.first.ops.filter((o) => o !== '1').length}/${r.first.ops.length} hidden`);
    await r.ctx.close();
  }
}
{
  const r = await revealState({}, `${WP}/qa-reveal/`);
  await r.p.waitForTimeout(300);
  const s = await r.get();
  check(s.cls.includes('mats-js') && s.cls.includes('mats-ready') && s.ops[s.ops.length - 1] === '0', 'D2 with JavaScript: marked, below-the-fold section still hidden (as Astro)', JSON.stringify(s));
  await r.p.waitForTimeout(3300);
  const s2 = await r.get();
  check(s2.cls.includes('mats-js') && s2.ops[s2.ops.length - 1] === '0', 'D2 the 3 s safety net does not fire while site.js runs', s2.cls);
  await r.ctx.close();
}
{
  const r = await revealState({}, `${WP}/qa-reveal/`, (p) => p.route('**/assets/js/site.js*', (route) => route.abort()));
  const hidden = r.first.ops.filter((o) => o === '0').length;
  await r.p.waitForTimeout(3400);
  const s = await r.get();
  check(hidden > 0 && s.ops.every((o) => o === '1') && !s.cls.includes('mats-js'), 'D3 site.js blocked: sections shown after the 3 s safety net', `hidden first: ${hidden}, after: ${s.ops.join(',')} cls="${s.cls}"`);
  /* the inline script run late (as a "delay JS" optimiser would) leaves the page visible */
  const late = await r.p.evaluate(() => {
    const code = [...document.querySelectorAll('head script:not([src])')].map((s) => s.textContent).find((t) => t.includes('mats-js'));
    new Function(code)();
    return document.documentElement.className;
  });
  check(!late.includes('mats-js'), 'D4 inline script run after loading does not hide anything', late);
  await r.ctx.close();
}

await browser.close();

/* back as it was */
php(`require_once ABSPATH . 'wp-admin/includes/plugin.php';
update_option( 'mats_settings', json_decode( (string) file_get_contents( '${out}/before.json' ), true ) ); deactivate_plugins( 'wpforms-stub/qa-mail-capture.php' ); delete_option( 'wpforms_stub_mails' );`);
console.log(failures ? `${failures} check(s) failed` : 'all checks passed');
process.exit(failures ? 1 : 0);
