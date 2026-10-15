/**
 * QA (secseo): security answers of the public site and the login screen.
 *   WP_BASE=http://localhost:8916 node wordpress/tools/qa/secseo-security.mjs
 * Needs the user "editora" (secseo-setup.php). Clears the login counters
 * before and after (secseo-unlock.php through wpsite-cli.php).
 */
import { execSync } from 'node:child_process';

const BASE = process.env.WP_BASE || 'http://localhost:8916';
const PORT = new URL(BASE).port;
const failures = [];
const check = (cond, label) => {
  console.log(`${cond ? '  ok  ' : '  FAIL'} ${label}`);
  if (!cond) failures.push(label);
};
const unlock = () => execSync(`MATS_WP_PORT=${PORT} php /home/user/wpsite-cli.php /home/user/ICMAB-WEB/wordpress/tools/qa/secseo-unlock.php`).toString().trim();
const get = (path, opts = {}) => fetch(BASE + path, { redirect: 'manual', ...opts });

unlock();

/* XML-RPC */
{
  const r = await get('/xmlrpc.php', { method: 'POST', headers: { 'Content-Type': 'text/xml' }, body: '<?xml version="1.0"?><methodCall><methodName>system.multicall</methodName></methodCall>' });
  check(r.status === 403, `xmlrpc.php POST → ${r.status}`);
  check((await get('/xmlrpc.php?rsd')).status === 403, 'xmlrpc.php?rsd → 403');
}

/* user enumeration */
for (const path of ['/?author=1', '/?author=2', '/?author=999', '/author/admin/', '/author/nobody-at-all/', '/?author_name=admin', '/es/?author=1']) {
  const r = await get(path);
  const loc = r.headers.get('location') || '';
  check(r.status === 301 && /^http:\/\/localhost:\d+\/(es\/)?$/.test(loc), `${path} → ${r.status} ${loc}`);
}
for (const path of ['/wp-json/wp/v2/users', '/wp-json/wp/v2/users/1', '/?rest_route=/wp/v2/users', '/wp-json/wp/v2/users?search=a', '/?rest_route=/wp/v2/users/1']) {
  const r = await get(path);
  check(r.status === 401, `visitor ${path} → ${r.status}`);
}
check((await get('/wp-json/wp/v2/posts')).status === 200, 'REST API itself still answers (posts)');
{
  const post = await (await get('/wp-json/wp/v2/posts?per_page=1&_fields=link')).json();
  const o = await (await get('/wp-json/oembed/1.0/embed?url=' + encodeURIComponent(post[0].link))).json();
  check(!('author_name' in o) && !('author_url' in o), 'oEmbed answer has no author');
  const feed = await (await get('/feed/')).text();
  check(!/<generator>/.test(feed), 'feed has no generator');
  check(!/<dc:creator><!\[CDATA\[(admin|editora)\]\]>/.test(feed), 'feed shows no user names');
}
check((await get('/comments/feed/')).status === 301, 'comments feed redirected');
check((await get('/wp-sitemap-users-1.xml')).status === 404, 'no users sitemap');

/* headers */
for (const path of ['/', '/es/producto/', '/wp-login.php']) {
  const r = await get(path);
  const h = r.headers;
  check(
    h.get('x-content-type-options') === 'nosniff' &&
      h.get('referrer-policy') === 'strict-origin-when-cross-origin' &&
      h.get('x-frame-options') === 'SAMEORIGIN' &&
      h.get('permissions-policy') === 'camera=(), microphone=(), geolocation=(), interest-cohort=()' &&
      !h.get('x-powered-by') && !h.get('x-pingback'),
    `security headers on ${path}`
  );
  const csp = h.get('content-security-policy') || '';
  check(!csp || /^frame-ancestors 'self';?$/.test(csp.trim()), `no script-blocking CSP on ${path} (${csp || 'none'})`);
}

/* login: generic errors, lock, reset, lost password */
async function login(user, pass) {
  const body = new URLSearchParams({ log: user, pwd: pass, 'wp-submit': 'Log In', testcookie: '1' });
  const r = await get('/wp-login.php', { method: 'POST', body, headers: { Cookie: 'wordpress_test_cookie=WP%20Cookie%20check', 'Content-Type': 'application/x-www-form-urlencoded' } });
  const html = await r.text();
  const err = (html.match(/<div id="login_error"[^>]*>([\s\S]*?)<\/div>/) || [, ''])[1].replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim();
  return { status: r.status, err, location: r.headers.get('location') || '' };
}
{
  const a = await login('nobody-here', 'x');
  const b = await login('editora', 'wrong-pass');
  check(a.err === b.err && /usuario o la contraseña no son correctos/.test(a.err), `same message for unknown user and wrong password ("${a.err}")`);
  unlock();
  for (let i = 0; i < 5; i++) await login('editora', 'wrong-' + i);
  const locked = await login('editora', 'editora-local-pass');
  check(locked.status === 200 && /Acceso bloqueado/.test(locked.err) && /15 minutos/.test(locked.err), `6th attempt locked even with the right password ("${locked.err.slice(0, 60)}…")`);
  const byMail = await login('editora@example.org', 'editora-local-pass');
  check(/Acceso bloqueado/.test(byMail.err), 'the lock also applies when using the e-mail');
  const other = await login('admin', 'admin-local-pass');
  check(other.status === 302, 'another account from the same IP can still log in');
  unlock();
  for (let i = 0; i < 4; i++) await login('editora', 'wrong-' + i);
  const ok = await login('editora', 'editora-local-pass');
  check(ok.status === 302 && /wp-admin/.test(ok.location), 'right password after 4 failures logs in');
  for (let i = 0; i < 4; i++) await login('editora', 'wrong-' + i);
  const ok2 = await login('editora', 'editora-local-pass');
  check(ok2.status === 302, 'a successful login cleared the counter');
}
{
  const body = new URLSearchParams({ user_login: 'nobody@example.org', 'wp-submit': 'Get New Password', redirect_to: '' });
  const r = await get('/wp-login.php?action=lostpassword', { method: 'POST', body, headers: { Cookie: 'wordpress_test_cookie=WP%20Cookie%20check', 'Content-Type': 'application/x-www-form-urlencoded' } });
  check(r.status === 302 && /checkemail=confirm/.test(r.headers.get('location') || ''), 'lost password: unknown e-mail gets the normal "check your e-mail" answer');
}

console.log(unlock());
console.log(failures.length ? `\nFAILED (${failures.length}):\n - ${failures.join('\n - ')}` : '\nALL SECURITY CHECKS PASSED');
process.exit(failures.length ? 1 : 0);
