/**
 * QA (formsec): «¿Has olvidado la contraseña?» gives real and unknown
 * accounts the same answer, also when the site cannot send e-mails, and
 * WordPress still sends the reset e-mail to real accounts.
 *
 *   WP_BASE=http://localhost:8983 node wordpress/tools/qa/fix-formsec-lostpassword.mjs
 *   (run from the repo root; creates the test editor "formsec-ed", toggles
 *    the QA mail capture, deletes everything at the end)
 *
 *  1. mail failing: real user name / e-mail / admin vs unknown name / e-mail
 *     → byte-identical answers (status, Location, X-Redirect-By, body),
 *     with and without redirect_to, and with a non-text redirect_to; the
 *     reset key was still generated (WordPress tried to send the e-mail);
 *     an empty field still gets WordPress' explanation.
 *  2. mail working: same identical answers, and the reset e-mail with the
 *     link reaches the real account.
 *  3. admin notice «La web no consigue enviar correos» for administrators
 *     only; «Ocultar este aviso» hides it; a sent e-mail clears it.
 */
import { launch, login } from './wp-login.mjs';
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const BASE = process.env.WP_BASE || 'http://localhost:8983';
const PORT = new URL(BASE).port;
const HELPER = path.join(path.dirname(fileURLToPath(import.meta.url)), 'fix-formsec-lostpassword.php');
const php = (mode) => execFileSync('php', ['/home/user/wpsite-cli.php', HELPER], { env: { ...process.env, MATS_WP_PORT: PORT, MATS_QA_MODE: mode } }).toString().trim();

let failures = 0;
const check = (ok, label, detail = '') => {
  if (!ok) failures++;
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}${detail ? '  ' + detail : ''}`);
};

const REAL = ['formsec-ed', 'formsec-ed@example.test', 'FORMSEC-ED@example.test', 'admin'];
const UNKNOWN = ['nobody-formsec', 'nobody-formsec@example.test'];

/** POSTs the lost-password form; returns the comparable parts of the answer. */
async function ask(userLogin, extra = '') {
  const body = `user_login=${encodeURIComponent(userLogin)}&wp-submit=Get+New+Password${extra}`;
  const res = await fetch(`${BASE}/wp-login.php?action=lostpassword`, {
    method: 'POST',
    redirect: 'manual',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', Cookie: 'wordpress_test_cookie=WP%20Cookie%20check' },
    body,
  });
  const text = await res.text();
  const headers = [...res.headers.entries()].filter(([k]) => k !== 'date').map(([k, v]) => `${k}: ${v}`).sort().join('\n');
  const error = (text.match(/<div id="login_error"[\s\S]*?<\/div>/) || [''])[0].replace(/<[^>]+>/g, '').trim();
  return { status: res.status, location: res.headers.get('location') || '', headers, body: text, error };
}

async function sameAnswers(label, extra = '', expectLocation = '/wp-login.php?checkemail=confirm') {
  const ref = await ask(UNKNOWN[0], extra);
  check(ref.status === 302 && ref.location === expectLocation, `${label}: unknown account → 302 ${expectLocation}`, `${ref.status} ${ref.location}`);
  for (const who of [...REAL, ...UNKNOWN.slice(1)]) {
    const r = await ask(who, extra);
    const same = r.status === ref.status && r.headers === ref.headers && r.body === ref.body;
    check(same, `${label}: "${who}" gets the identical answer`, same ? '' : `${r.status} ${r.location} ${r.error.slice(0, 90)}`);
  }
}

try {
  console.log(php('setup'));

  /* 1. mail failing */
  console.log(php('mail-off'));
  await sameAnswers('mail failing');
  let state = JSON.parse(php('state'));
  check(state.resetKey, 'mail failing: WordPress still generated the reset key for the real account (it tried to send the e-mail)');
  check(state.mailFailed > 0, 'mail failing: the failure is remembered for the admin notice');
  await sameAnswers('mail failing + redirect_to', `&redirect_to=${encodeURIComponent(`${BASE}/es/`)}`, `${BASE}/es/`);
  await sameAnswers('mail failing + non-text redirect_to', '&redirect_to[]=x');
  const empty = await ask('');
  check(empty.status === 200 && /Please enter a username or email address/.test(empty.error), 'empty field: WordPress still explains it', `${empty.status} ${empty.error}`);

  /* 3. admin notice (the failure above is still remembered) */
  const browser = await launch();
  try {
    const ed = await login(browser, 'formsec-ed', 'formsec-pass-123');
    await ed.goto(`${BASE}/wp-admin/`);
    check((await ed.locator('.notice:has-text("La web no consigue enviar correos")').count()) === 0, 'notice: editors do not see it');
    await ed.context().close();

    const admin = await login(browser);
    await admin.goto(`${BASE}/wp-admin/`);
    const notice = admin.locator('.notice:has-text("La web no consigue enviar correos")');
    check((await notice.count()) === 1, 'notice: administrators see it on the dashboard');
    if (await notice.count()) {
      console.log('      ' + (await notice.innerText()).replace(/\s+/g, ' '));
      await admin.screenshot({ path: process.env.OUT ? `${process.env.OUT}/lostpassword-notice.png` : '/dev/null', clip: { x: 160, y: 30, width: 1280, height: 260 } }).catch(() => {});
      await Promise.all([admin.waitForNavigation(), notice.locator('a:has-text("Ocultar este aviso")').click()]);
      check(new URL(admin.url()).pathname === '/wp-admin/' && (await admin.locator('.notice:has-text("La web no consigue enviar correos")').count()) === 0, '«Ocultar este aviso» hides it and comes back to the same screen', admin.url());
      check(JSON.parse(php('state')).mailFailed === 0, 'notice: hiding it clears the flag');
    }
    /* forged hide link (no nonce) does nothing */
    await sameAnswers('mail failing (again)');
    const forged = await admin.goto(`${BASE}/wp-admin/admin-post.php?action=mats_sec_mail_notice`);
    check(forged.status() === 403 && JSON.parse(php('state')).mailFailed > 0, 'notice: the hide link needs its nonce', String(forged.status()));
    await admin.context().close();
  } finally {
    await browser.close();
  }
  check(JSON.parse(php('succeeded')).mailFailed === 0, 'notice: an e-mail sent successfully clears the flag');

  /* 2. mail working */
  console.log(php('setup'));
  console.log(php('mail-on'));
  await sameAnswers('mail working');
  state = JSON.parse(php('state'));
  const toEd = state.mails.filter((m) => m.to === 'formsec-ed@example.test');
  check(toEd.length >= 2 && toEd.every((m) => m.resetUrl), 'mail working: the reset e-mail with its link reached the real account (user name + e-mail requests)', JSON.stringify(toEd));
  check(!state.mails.some((m) => /nobody/.test(m.to)), 'mail working: nothing is sent for unknown accounts');
  check(state.mailFailed === 0, 'mail working: no failure remembered');
} finally {
  console.log(php('cleanup'));
}

console.log(failures ? `${failures} FAILED` : 'ALL PASS');
process.exit(failures ? 1 : 0);
