/**
 * QA (secseo, fix pass): the review findings, checked over HTTP and in the
 * block editor.
 *   WP_BASE=http://localhost:8916 node wordpress/tools/qa/secseo-fixpass.mjs [outDir]
 * Needs "editora" (secseo-setup.php). Restarts the server of its port with
 * the mail stub (secseo-mailstub.php) for the lost-password checks and
 * puts the normal server back (wp-serve.sh) at the end. Every change it
 * makes (settings, test articles, editora's password, login counters,
 * uploads) is undone.
 *
 *  1. lost password: same raw answer (status + Location) for real and
 *     unknown accounts, with and without redirect_to
 *  2. a password reset lifts the login lock at once
 *  3. Atom feed: no <uri> of the user's profile
 *  4. og:locale:alternate only for published translations; automatic
 *     description cut after a multibyte letter stays intact
 *  5. home page: changing Ajustes de la web → «Textos por defecto para
 *     Google» changes its <title> and description
 *  6. editor panel: no «Extracto» hint on landing pages, home page fields
 *     follow the settings, 120–155 counter; REST upload of informe.pl.png
 */
import { execSync, spawn } from 'node:child_process';
import { openSync } from 'node:fs';
import { launch, login as browserLogin, closeWelcome } from './wp-login.mjs';

const BASE = process.env.WP_BASE || 'http://localhost:8916';
const PORT = new URL(BASE).port;
const OUT = process.argv[2] || '/tmp';
const QA = '/home/user/ICMAB-WEB/wordpress/tools/qa';
const failures = [];
const check = (cond, label) => {
  console.log(`${cond ? '  ok  ' : '  FAIL'} ${label}`);
  if (!cond) failures.push(label);
};
const cli = (step, file = 'secseo-fixpass.php') =>
  execSync(`MATS_WP_PORT=${PORT} MATS_QA_STEP=${step} php /home/user/wpsite-cli.php ${QA}/${file}`).toString().trim();
const unlock = () => cli('', 'secseo-unlock.php');
const get = (path, opts = {}) => fetch(path.startsWith('http') ? path : BASE + path, { redirect: 'manual', ...opts });
const form = (body) => ({ method: 'POST', body: new URLSearchParams(body), headers: { 'Content-Type': 'application/x-www-form-urlencoded', Cookie: 'wordpress_test_cookie=WP%20Cookie%20check' } });
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const head = (html) => {
  const metas = {};
  for (const m of html.matchAll(/<meta (?:property|name)="([^"]+)" content="([^"]*)"/g)) (metas[m[1]] ||= []).push(m[2]);
  return {
    title: (html.match(/<title>([\s\S]*?)<\/title>/) || [, ''])[1],
    metas,
    hreflang: [...html.matchAll(/<link rel="alternate" href="[^"]*" hreflang="([^"]+)"/g)].map((m) => m[1]),
  };
};

async function restartServer(withStub) {
  if (!withStub) {
    execSync(`/home/user/wp-serve.sh ${PORT}`, { stdio: "ignore" });
    return;
  }
  execSync(`for pid in $(pgrep -f "localhost:${PORT} /home/user/wpsite-route[r].php"); do kill "$pid" 2>/dev/null; done; sleep 0.3`, { shell: '/bin/bash' });
  const log = openSync(`/home/user/wpsite-data/server-${PORT}.log`, 'a');
  spawn('php', ['-d', `auto_prepend_file=${QA}/secseo-mailstub.php`, '-S', `localhost:${PORT}`, '/home/user/wpsite-router.php'], {
    cwd: '/home/user/wpsite',
    env: { ...process.env, PHP_CLI_SERVER_WORKERS: '4' },
    detached: true,
    stdio: ['ignore', log, log],
  }).unref();
  for (let i = 0; i < 30; i++) {
    await sleep(200);
    try {
      if ((await fetch(BASE + '/')).status === 200) return;
    } catch (e) {}
  }
}

async function wpLogin(user, pass) {
  const r = await get('/wp-login.php', form({ log: user, pwd: pass, 'wp-submit': 'Log In', testcookie: '1' }));
  const html = await r.text();
  const err = (html.match(/<div id="login_error"[^>]*>([\s\S]*?)<\/div>/) || [, ''])[1].replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim();
  return { status: r.status, err, location: r.headers.get('location') || '' };
}

unlock();
await restartServer(true);
try {
  /* ── 1. lost password ─────────────────────────────────────────────── */
  const lost = async (login, extra = {}) => {
    const r = await get('/wp-login.php?action=lostpassword', form({ user_login: login, 'wp-submit': 'Get New Password', ...extra }));
    const body = await r.text();
    return { status: r.status, location: r.headers.get('location') || '', error: /id="login_error"/.test(body) };
  };
  const variants = [
    ['no redirect_to', { redirect_to: '' }],
    ['redirect_to on the site', { redirect_to: `${BASE}/?probe=1` }],
    ['redirect_to elsewhere', { redirect_to: 'https://example.com/x' }],
    ['redirect_to as an array', { 'redirect_to[]': 'x' }],
  ];
  for (const [label, extra] of variants) {
    const realName = await lost('editora', extra);
    const realMail = await lost('editora@example.org', extra);
    const fakeName = await lost('nobody-xyz-' + Date.now(), extra);
    const fakeMail = await lost('nobody-' + Date.now() + '@example.org', extra);
    const same = [realMail, fakeName, fakeMail].every((x) => x.status === realName.status && x.location === realName.location);
    check(realName.status === 302 && !realName.error && same, `lost password, ${label}: real and unknown accounts get the same answer (${realName.status} "${realName.location}" | unknown: ${fakeName.status} "${fakeName.location}")`);
  }
  {
    const empty = await lost('');
    check(empty.status === 200 && empty.error, 'lost password: empty field still explained by WordPress');
  }

  /* ── 2. password reset lifts the lock ─────────────────────────────── */
  {
    unlock();
    for (let i = 0; i < 5; i++) await wpLogin('editora', 'wrong-' + i);
    const locked = await wpLogin('editora', 'editora-local-pass');
    check(/Acceso bloqueado/.test(locked.err) && /en cuanto la cambies podrás entrar/.test(locked.err), `account locked, message says a new password unlocks ("${locked.err.slice(0, 90)}…")`);
    const { key } = JSON.parse(cli('reset-key'));
    const rp = await get(`/wp-login.php?action=rp&key=${encodeURIComponent(key)}&login=editora`);
    const cookie = ((rp.headers.getSetCookie ? rp.headers.getSetCookie() : [rp.headers.get('set-cookie') || '']).find((c) => /^wp-resetpass-/.test(c)) || '').split(';')[0];
    check(rp.status === 302 && /wp-resetpass-/.test(cookie), `reset link accepted (${rp.status})`);
    const page = await (await get('/wp-login.php?action=rp', { headers: { Cookie: cookie } })).text();
    const rpKey = (page.match(/name="rp_key" value="([^"]+)"/) || [])[1];
    const newPass = 'Secseo-New-Pass-' + Date.now();
    const reset = await get('/wp-login.php?action=resetpass', { method: 'POST', body: new URLSearchParams({ pass1: newPass, pass2: newPass, rp_key: rpKey || '', 'wp-submit': 'Save Password' }), headers: { 'Content-Type': 'application/x-www-form-urlencoded', Cookie: cookie } });
    const resetHtml = await reset.text();
    check(/password has been reset|contraseña se ha restablecido/i.test(resetHtml), 'password reset page confirms the new password');
    const after = await wpLogin('editora', newPass);
    check(after.status === 302 && /wp-admin/.test(after.location), `the new password logs in at once (${after.status} ${after.err.slice(0, 60)})`);
    console.log('    ' + cli('restore-pass'));
    unlock();
    const back = await wpLogin('editora', 'editora-local-pass');
    check(back.status === 302, 'editora password restored');
  }
} finally {
  await restartServer(false);
  unlock();
}

/* ── 3. Atom feed ─────────────────────────────────────────────────── */
{
  const atom = await (await get('/feed/atom/')).text();
  check(/<entry>/.test(atom) && !/<uri>/.test(atom), 'Atom feed: no <uri> of the user profile');
  check(/<author>\s*<name>MatSurfer<\/name>/.test(atom), 'Atom feed: author is the site name');
}

/* ── 4. og:locale:alternate ───────────────────────────────────────── */
{
  const urls = JSON.parse(cli('drafts-setup'));
  try {
    const only = head(await (await get(urls.enOnly)).text());
    check(!only.metas['og:locale:alternate'] && !only.hreflang.includes('es'), `EN article with a draft ES translation: no og:locale:alternate, no hreflang es (${only.metas['og:locale:alternate'] || 'none'} / ${only.hreflang.join(',') || 'none'})`);
    const both = head(await (await get(urls.both)).text());
    check((both.metas['og:locale:alternate'] || []).join() === 'es_ES' && both.hreflang.includes('es'), `EN article with a published ES translation: og:locale:alternate es_ES + hreflang es (${both.metas['og:locale:alternate']} / ${both.hreflang.join(',')})`);
    const mbHtml = await (await get(urls.multibyte)).text();
    const mb = head(mbHtml);
    const desc = (mb.metas.description || [''])[0];
    const ld = JSON.parse((mbHtml.match(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/) || [, '{}'])[1]);
    const posting = (ld['@graph'] || []).find((n) => n['@type'] === 'BlogPosting') || {};
    check(/PRESENTÓ…$/.test(desc) && (mb.metas['og:description'] || [''])[0] === desc && (mb.metas['twitter:description'] || [''])[0] === desc, `automatic description cut after «Ó» is intact in meta / og / twitter ("…${desc.slice(-24)}")`);
    check(posting.description === desc && !/\?/.test(posting.description || '?'), 'BlogPosting description intact (no "?")');
  } finally {
    console.log('    ' + cli('drafts-cleanup'));
  }
}

/* ── 5. home page follows Ajustes de la web ───────────────────────── */
{
  const before = { en: head(await (await get('/')).text()), es: head(await (await get('/es/')).text()) };
  cli('settings-set');
  try {
    for (const lang of ['en', 'es']) {
      const h = head(await (await get(lang === 'en' ? '/' : '/es/')).text());
      const L = lang.toUpperCase();
      check(h.title === `QA GENERAL TITLE ${L}`, `home ${lang}: <title> from Ajustes de la web ("${h.title}")`);
      check((h.metas.description || [])[0] === `QA GENERAL DESCRIPTION ${L} — written in Ajustes de la web.` && (h.metas['og:description'] || [])[0] === h.metas.description[0], `home ${lang}: description + og:description from Ajustes de la web`);
      check((h.metas['og:title'] || [])[0] === `QA GENERAL TITLE ${L}`, `home ${lang}: og:title from Ajustes de la web`);
    }
    const product = head(await (await get('/product/')).text());
    check(!/QA GENERAL/.test(product.title) && !/QA GENERAL/.test((product.metas.description || [''])[0]), 'product page keeps its own texts');
  } finally {
    console.log('    ' + cli('settings-reset'));
  }
  const after = { en: head(await (await get('/')).text()), es: head(await (await get('/es/')).text()) };
  check(after.en.title === before.en.title && after.es.title === before.es.title, `home titles back to the original ("${after.en.title}")`);
}

/* ── 6. editor panel + upload ─────────────────────────────────────── */
{
  const ids = JSON.parse(cli('ids'));
  const browser = await launch();
  const page = await browserLogin(browser, 'editora', 'editora-local-pass', { width: 1440, height: 1000 });
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  const panelText = async (id, shot) => {
    await page.goto(`${BASE}/wp-admin/post.php?post=${id}&action=edit`);
    await page.waitForFunction(() => window.wp?.data?.select('core/editor')?.getCurrentPostId(), null, { timeout: 60000 });
    await closeWelcome(page);
    await page.evaluate(() => {
      const d = wp.data.dispatch('core/edit-post');
      if (d?.openGeneralSidebar) d.openGeneralSidebar('edit-post/document');
      const ed = wp.data.select('core/editor');
      if (ed.isEditorPanelOpened && !ed.isEditorPanelOpened('matsurfer-seo/matsurfer-seo')) wp.data.dispatch('core/editor').toggleEditorPanelOpened('matsurfer-seo/matsurfer-seo');
    });
    const panel = page.locator('.mats-seo');
    await panel.locator('.mats-seo__preview').waitFor({ timeout: 15000 });
    await page.waitForTimeout(500);
    const box = await panel.boundingBox();
    if (box && shot) await page.screenshot({ path: `${OUT}/${shot}`, clip: { x: box.x - 8, y: Math.max(0, box.y - 8), width: box.width + 16, height: Math.min(1000, box.height + 16) } });
    return {
      text: await panel.innerText(),
      title: await panel.locator('input[type="text"]').first().inputValue(),
      titlePh: await panel.locator('input[type="text"]').first().getAttribute('placeholder'),
      desc: await panel.locator('textarea').first().inputValue(),
      excerptPanel: await page.evaluate(() => !!wp.data.select('core').getPostType(wp.data.select('core/editor').getCurrentPostType())?.supports?.excerpt),
    };
  };
  const product = await panelText(ids.product, 'secseo-fixpass-panel-product.png');
  check(!product.excerptPanel && !/Extracto/.test(product.text) && /descripción general de la web \(Ajustes de la web → Textos por defecto para Google\)/.test(product.text), 'landing page (ES product): no «Extracto» hint, points to Ajustes de la web');
  check(/entre 120 y 155|de 155 caracteres/.test(product.text), `landing page: description counter uses 120–155 ("${(product.text.match(/\d+ (de 155 )?caracteres[^\n]*/) || [''])[0]}")`);
  const home = await panelText(ids.home, 'secseo-fixpass-panel-home.png');
  check(home.title === '' && home.titlePh === ids.siteTitle && home.desc === '', `home page: original copy shown as empty, placeholder = general title ("${home.titlePh}")`);
  check(/título general de la web \(Ajustes de la web/.test(home.text) && /descripción general de la web \(Ajustes de la web/.test(home.text), 'home page: help points to Ajustes de la web');
  const news = await panelText(ids.news);
  check(news.excerptPanel && /«Extracto» de la página/.test(news.text), 'News page keeps the «Extracto» hint (it has the box)');
  if (ids.post) {
    const post = await panelText(ids.post);
    check(/«Extracto» de la noticia/.test(post.text), 'news article keeps the «Extracto» hint');
  }
  /* uploads through the REST API, as the media library does */
  const png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
  const upload = (name) =>
    page.evaluate(
      async ({ name, png }) => {
        const bin = Uint8Array.from(atob(png), (c) => c.charCodeAt(0));
        const body = new FormData();
        body.append('file', new Blob([bin], { type: 'image/png' }), name);
        try {
          const r = await wp.apiFetch({ path: '/wp/v2/media', method: 'POST', body });
          await wp.apiFetch({ path: `/wp/v2/media/${r.id}?force=true`, method: 'DELETE' });
          return { ok: true, id: r.id };
        } catch (e) {
          return { ok: false, message: e.message };
        }
      },
      { name, png }
    );
  for (const name of ['informe.pl.png', 'figura.py.png']) {
    const r = await upload(name);
    check(r.ok, `editora uploads ${name} (${r.ok ? 'id ' + r.id + ', deleted again' : r.message})`);
  }
  const bad = await upload('foto.php.png');
  check(!bad.ok && /«\.php\.»/.test(bad.message) && /foto-php\.png/.test(bad.message), `foto.php.png refused with a clear message ("${bad.message}")`);
  check(errors.length === 0, `no JS errors (${errors.slice(0, 2).join(' | ')})`);
  await browser.close();
}

console.log(unlock());
console.log(failures.length ? `\nFAILED (${failures.length}):\n - ${failures.join('\n - ')}` : '\nALL FIXPASS CHECKS PASSED');
process.exit(failures.length ? 1 : 0);
