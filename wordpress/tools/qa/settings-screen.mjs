/**
 * QA (module "settings"): the "Ajustes de la web" screen.
 *   node wordpress/tools/qa/settings-screen.mjs <outDir>
 *  - access: administrator and editor see the menu item and the screen (the
 *    WPForms info only for the administrator); an author does not
 *  - saving changes the header / footer in both languages; only values that
 *    differ from the originals are stored; HTML is stripped, lengths capped
 *  - email validation, patent code synced into the footer's last line
 *  - "Restaurar valores originales" per section (other edits kept),
 *    "Volver al original", Enter saves (never restores)
 *  - security: no nonce → refused; author with a forged request → 403
 * Needs settings-setup.php (pages + users editora / autora). Exit 1 on failure.
 */
import { mkdirSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { launch } from './wp-login.mjs';

const out = process.argv[2] || '/tmp/settings-screen';
mkdirSync(out, { recursive: true });
const WP = process.env.WP_BASE || 'http://localhost:8917';
const SCREEN = `${WP}/wp-admin/admin.php?page=mats-settings`;
const PORT = new URL(WP).port;
/* runs PHP inside WordPress (wpsite-cli.php) and returns its output */
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

async function login(browser, user, pass, viewport = { width: 1440, height: 1000 }) {
  const context = await browser.newContext({ viewport });
  const page = await context.newPage();
  page.on('dialog', (d) => d.accept());
  await page.goto(`${WP}/wp-login.php`);
  await page.fill('#user_login', user);
  await page.fill('#user_pass', pass);
  await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);
  return page;
}

/* texts of the public header/footer of a URL */
async function chrome(browser, url) {
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const p = await ctx.newPage();
  await p.goto(url);
  const data = await p.evaluate(() => ({
    cta: document.querySelector('.header__cta')?.textContent.trim(),
    nav: [...document.querySelectorAll('.header__menu > li > a:not(.btn)')].map((a) => a.textContent.trim()),
    desc: document.querySelector('.footer__desc')?.textContent.trim(),
    fcta: document.querySelector('.footer__cta')?.textContent.trim(),
    cols: [...document.querySelectorAll('.footer__nav .eyebrow')].map((e) => e.textContent.trim()),
    mail: [...document.querySelectorAll('.footer__nav a')].find((a) => a.href.startsWith('mailto:'))?.getAttribute('href'),
    bottom: document.querySelector('.footer__bottom .mono')?.textContent.trim(),
    copy: document.querySelector('.footer__bottom span')?.textContent.trim(),
    title: document.title,
  }));
  await ctx.close();
  return data;
}

async function save(page) {
  await Promise.all([page.waitForNavigation(), page.click('#mats_save')]);
}

const notices = (page) => page.$$eval('.mats-set__notice', (els) => els.map((e) => `${e.className.match(/notice-(\w+)/)[1]}: ${e.textContent.trim()}`));

const browser = await launch();

/* ── reset to originals ─────────────────────────────────────────────── */
{
  const admin = await login(browser, 'admin', 'admin-local-pass');
  await admin.goto(SCREEN);
  for (const section of ['header', 'footer', 'seo', 'blog', 'contact']) {
    await Promise.all([admin.waitForNavigation(), admin.click(`button.mats-set__restore[value="${section}"]`)]);
  }
  await admin.context().close();
}

/* keys written by other modules (WPForms IDs…) must survive every save */
const formEnBefore = php("$s = get_option( 'mats_settings' ); echo wp_json_encode( $s['global']['form_en'] ?? null );").trim();
php("mats_update_setting( 'form_en', 99 ); mats_update_setting( 'qa_custom', 'keep', 'en' );");

/* ── access ────────────────────────────────────────────────────────── */
{
  const admin = await login(browser, 'admin', 'admin-local-pass');
  await admin.goto(`${WP}/wp-admin/`);
  const menu = await admin.$eval('#toplevel_page_mats-settings', (li) => ({ text: li.textContent.trim(), icon: li.querySelector('.wp-menu-image').className })).catch(() => null);
  check(menu && menu.text === 'Ajustes de la web' && menu.icon.includes('dashicons-admin-generic'), 'admin: top-level menu "Ajustes de la web" with dashicons-admin-generic', JSON.stringify(menu));
  await admin.goto(SCREEN);
  const h1 = await admin.textContent('h1');
  check(h1.trim() === 'Ajustes de la web', 'admin: screen opens', h1);
  check((await admin.$$('#mats-sec-forms')).length === 1, 'admin: sees the WPForms forms info (read only)');
  const formRow = await admin.$eval('#mats-sec-forms tbody tr', (tr) => tr.textContent.replace(/\s+/g, ' ').trim());
  check(formRow.includes('99') && formRow.includes('ya no existe'), 'admin: stale form ID reported', formRow);
  const sections = await admin.$$eval('.mats-set__section h2', (els) => els.map((e) => e.childNodes[0].textContent.trim()));
  check(sections.join('|') === 'Cabecera|Pie de página|Textos por defecto para Google|Cierre de las noticias|Email de contacto y patente|Formularios de contacto', 'sections in order', sections.join('|'));
  const ph = await admin.$eval('#mats-es-header_cta', (i) => ({ value: i.value, placeholder: i.placeholder, lang: i.lang }));
  check(ph.placeholder === 'Habla con el equipo' && ph.value === 'Habla con el equipo' && ph.lang === 'es', 'placeholder = original text, lang attribute', JSON.stringify(ph));
  await admin.screenshot({ path: `${out}/screen-admin-1440.png`, fullPage: true });
  await admin.context().close();

  const editor = await login(browser, 'editora', 'editora-local-pass');
  await editor.goto(`${WP}/wp-admin/`);
  check((await editor.$$('#toplevel_page_mats-settings')).length === 1, 'editor: sees the menu item');
  await editor.goto(SCREEN);
  check((await editor.textContent('h1')).trim() === 'Ajustes de la web', 'editor: screen opens');
  check((await editor.$$('#mats-sec-forms')).length === 0, 'editor: does not see the WPForms forms info');
  await editor.screenshot({ path: `${out}/screen-editor-1440.png`, fullPage: true });
  await editor.setViewportSize({ width: 1024, height: 900 });
  await editor.screenshot({ path: `${out}/screen-editor-1024.png`, fullPage: true });
  await editor.setViewportSize({ width: 390, height: 844 });
  await editor.screenshot({ path: `${out}/screen-editor-390.png`, fullPage: true });
  const overflow = await editor.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  check(overflow <= 0, 'editor 390px: no horizontal scroll', String(overflow));
  await editor.context().close();

  const author = await login(browser, 'autora', 'autora-local-pass');
  await author.goto(`${WP}/wp-admin/`);
  check((await author.$$('#toplevel_page_mats-settings')).length === 0, 'author: no menu item');
  const resp = await author.goto(SCREEN);
  check(resp.status() === 403 || /permiso|allowed|permis/i.test(await author.textContent('body')), 'author: screen refused', String(resp.status()));
  /* forged save as author */
  const forged = await author.evaluate(async (base) => {
    const body = new URLSearchParams({ action: 'mats_settings_save', mats_settings_nonce: 'x', 'mats[en][header_cta]': 'Hacked' });
    const r = await fetch(`${base}/wp-admin/admin-post.php`, { method: 'POST', body, credentials: 'include', redirect: 'manual' });
    return r.status;
  }, WP);
  check(forged === 403, 'author: forged save refused (403)', String(forged));
  await author.context().close();
}

/* ── saving as editor ──────────────────────────────────────────────── */
{
  const ed = await login(browser, 'editora', 'editora-local-pass');
  await ed.goto(SCREEN);

  /* missing nonce (copy of the form without it) */
  const noNonce = await ed.evaluate(async (base) => {
    const body = new URLSearchParams({ action: 'mats_settings_save', 'mats[en][header_cta]': 'No nonce' });
    const r = await fetch(`${base}/wp-admin/admin-post.php`, { method: 'POST', body, credentials: 'include', redirect: 'manual' });
    return r.status;
  }, WP);
  check(noNonce === 403, 'save without nonce refused (403)', String(noNonce));

  await ed.fill('#mats-en-header_cta', "Let's talk");
  await ed.fill('#mats-es-header_cta', 'Hablemos');
  await ed.fill('#mats-en-nav_product', 'The device');
  await ed.fill('#mats-es-nav_product', 'Dispositivo');
  await ed.fill('#mats-es-footer_description', '<b>Descripción</b> de prueba <script>alert(1)</script>del pie.');
  await ed.fill('#mats-en-footer_col2_title', 'Discover');
  await ed.fill('#mats-en-footer_copyright', 'x'.repeat(260));
  /* "Volver al original" appears and works */
  const origShown = await ed.$eval('#mats-en-footer_col2_title', (i) => !i.closest('.mats-set__field').querySelector('.mats-set__orig').hidden);
  check(origShown, '"Original:" hint appears when a text is changed');
  const dirty = await ed.$eval('.mats-set__dirty', (e) => !e.hidden);
  check(dirty, '"Tienes cambios sin guardar" appears');
  await ed.fill('#mats-es-footer_col2_title', 'Algo');
  await ed.click('#mats-es-footer_col2_title >> xpath=ancestor::div[contains(@class,"mats-set__field")]//button[contains(@class,"mats-set__back")]');
  check((await ed.inputValue('#mats-es-footer_col2_title')) === 'Explora', '"Volver al original" puts the original back', await ed.inputValue('#mats-es-footer_col2_title'));
  await ed.screenshot({ path: `${out}/screen-editing.png`, clip: { x: 0, y: 0, width: 1440, height: 1000 } });
  await save(ed);
  const n1 = await notices(ed);
  check(n1.some((t) => t.startsWith('success: Cambios guardados')), 'success notice after saving', n1.join(' / '));
  check((await ed.inputValue('#mats-es-footer_description')) === 'Descripción de prueba del pie.', 'HTML stripped from the text', await ed.inputValue('#mats-es-footer_description'));
  check((await ed.inputValue('#mats-en-footer_copyright')).length === 200, 'length capped (200)', String((await ed.inputValue('#mats-en-footer_copyright')).length));

  const en = await chrome(browser, `${WP}/product/`);
  const es = await chrome(browser, `${WP}/es/producto/`);
  check(en.cta === "Let's talk" && en.nav[0] === 'The device' && en.cols[1] === 'Discover', 'EN header/footer show the new texts', JSON.stringify({ cta: en.cta, nav: en.nav[0], col: en.cols[1] }));
  check(es.cta === 'Hablemos' && es.nav[0] === 'Dispositivo' && es.desc === 'Descripción de prueba del pie.' && es.cols[1] === 'Explora', 'ES header/footer show the new texts', JSON.stringify({ cta: es.cta, nav: es.nav[0], desc: es.desc, col: es.cols[1] }));
  check(en.desc.startsWith('Compact surface') && es.fcta === 'Habla con el equipo', 'untouched texts keep their originals');

  /* server side: crafted request (no maxlength, HTML, line breaks) with the page's nonce */
  const crafted = await ed.evaluate(async () => {
    const form = document.getElementById('mats-settings-form');
    const data = new FormData(form);
    data.set('mats[en][footer_l_faq]', 'Q'.repeat(500));
    data.set('mats[en][footer_l_how]', 'How\n<img src=x onerror=alert(1)>it works');
    data.set('mats[fr][header_cta]', 'Bonjour');
    const r = await fetch(form.getAttribute('action'), { method: 'POST', body: new URLSearchParams(data), credentials: 'include' });
    return r.status;
  });
  await ed.goto(SCREEN);
  const faq = await ed.inputValue('#mats-en-footer_l_faq');
  const how = await ed.inputValue('#mats-en-footer_l_how');
  check(crafted === 200 && faq.length === 40 && how === 'How it works', 'server caps lengths and strips HTML / line breaks', `${faq.length} "${how}"`);

  /* Enter in a field saves (does not restore the first section) */
  await ed.fill('#mats-en-nav_team', 'People');
  await Promise.all([ed.waitForNavigation(), ed.press('#mats-en-nav_team', 'Enter')]);
  check((await ed.inputValue('#mats-en-nav_team')) === 'People' && (await ed.inputValue('#mats-en-header_cta')) === "Let's talk", 'Enter key saves and never restores', await ed.inputValue('#mats-en-header_cta'));

  /* email: invalid then valid */
  await ed.fill('#mats-global-contact_email', 'not-an-email');
  await save(ed);
  const n2 = await notices(ed);
  check(n2.some((t) => t.startsWith('error:') && t.includes('not-an-email')), 'invalid email refused with a clear message', n2.join(' / '));
  check((await ed.getAttribute('#mats-global-contact_email', 'aria-invalid')) === 'true', 'invalid email field marked and kept for correction');
  check((await chrome(browser, `${WP}/`)).mail === 'mailto:matsurfer@icmab.es', 'site keeps the previous email');
  await ed.fill('#mats-global-contact_email', 'transfer@icmab.es');
  await save(ed);
  const n3 = await notices(ed);
  check(n3.some((t) => t.startsWith('warning:') && t.includes('transfer@icmab.es')), 'email change: reminder to send a test', n3.join(' / '));
  check((await chrome(browser, `${WP}/es/`)).mail === 'mailto:transfer@icmab.es', 'footer email link uses the new address (ES)');

  /* patent code synced into the footer's last line */
  await ed.fill('#mats-global-patent_code', 'EP 1234 567 B2');
  await save(ed);
  const n4 = await notices(ed);
  const enB = (await chrome(browser, `${WP}/`)).bottom;
  const esB = (await chrome(browser, `${WP}/es/`)).bottom;
  check(enB === 'EP 1234 567 B2 · Surfaces for all.' && esB === 'EP 1234 567 B2 · Superficies para todos.', 'patent code replaced in the footer line (EN + ES)', `${enB} | ${esB}`);
  check(n4.some((t) => t.startsWith('info:') && t.includes('EP 1234 567 B2')), 'patent sync explained in a notice', n4.join(' / '));
  await ed.screenshot({ path: `${out}/screen-notices.png`, clip: { x: 0, y: 0, width: 1440, height: 700 } });

  /* SEO default title reaches the home page <title> when it has none of its own */
  await ed.fill('#mats-en-seo_title', 'QA default title');
  await save(ed);
  const home = await chrome(browser, `${WP}/`);
  check(home.title.includes('QA default title'), 'SEO default title used by the home page', home.title);

  /* restore one section: header back to originals, footer edits kept */
  await ed.fill('#mats-es-footer_cta', 'Escríbenos');
  await Promise.all([ed.waitForNavigation(), ed.click('button.mats-set__restore[value="header"]')]);
  const n5 = await notices(ed);
  check(n5.some((t) => t.startsWith('success: Listo: «Cabecera»')), 'restore notice', n5.join(' / '));
  check(ed.url().endsWith('#mats-sec-header'), 'back at the restored section', ed.url());
  const en2 = await chrome(browser, `${WP}/product/`);
  const es2 = await chrome(browser, `${WP}/es/producto/`);
  check(en2.cta === 'Talk to the team' && en2.nav[0] === 'Product' && en2.nav[3] === 'Team' && es2.cta === 'Habla con el equipo' && es2.nav[0] === 'Producto', 'header restored in both languages', JSON.stringify({ en: en2.cta, nav: en2.nav, es: es2.cta }));
  check(es2.fcta === 'Escríbenos' && en2.cols[1] === 'Discover' && es2.desc === 'Descripción de prueba del pie.', 'other sections kept (and the pending edit saved)', JSON.stringify({ fcta: es2.fcta, col: en2.cols[1] }));

  /* one-line long texts: Enter adds no line break */
  await ed.goto(SCREEN);
  await ed.click('#mats-en-seo_title');
  await ed.keyboard.press('End');
  await ed.keyboard.press('Enter');
  await ed.keyboard.type(' more');
  check(!(await ed.inputValue('#mats-en-seo_title')).includes('\n'), 'Enter adds no line break in long one-line texts');

  /* the option stores only what differs; keys of other modules survive */
  const opt = JSON.parse(php("echo wp_json_encode( get_option( 'mats_settings' ) );"));
  check(opt.en.footer_col2_title === 'Discover' && !('header_cta' in opt.en) && !('footer_l_how' in opt.en) && opt.global.form_en === 99 && opt.en.qa_custom === 'keep', 'option: only changed values stored, other keys preserved', JSON.stringify({ en: Object.keys(opt.en), global: opt.global }));
  await ed.context().close();
}

/* ── restore everything, check the option is clean ─────────────────── */
{
  const admin = await login(browser, 'admin', 'admin-local-pass');
  await admin.goto(SCREEN);
  for (const section of ['footer', 'seo', 'blog', 'contact']) {
    await Promise.all([admin.waitForNavigation(), admin.click(`button.mats-set__restore[value="${section}"]`)]);
  }
  const en = await chrome(browser, `${WP}/`);
  const es = await chrome(browser, `${WP}/es/`);
  check(en.bottom === 'EP 4483 979 B1 · Surfaces for all.' && es.bottom === 'EP 4483 979 B1 · Superficies para todos.' && en.mail === 'mailto:matsurfer@icmab.es' && en.cols[1] === 'Explore', 'all sections restored', JSON.stringify({ b: en.bottom, m: en.mail, c: en.cols[1] }));
  await admin.context().close();
}

php(`$s = get_option( 'mats_settings' ); unset( $s['global']['form_en'], $s['en']['qa_custom'] ); $f = json_decode( '${formEnBefore}', true ); if ( null !== $f ) { $s['global']['form_en'] = $f; } update_option( 'mats_settings', $s );`);
await browser.close();
console.log(failures ? `${failures} check(s) failed` : 'all checks passed');
process.exit(failures ? 1 : 0);
