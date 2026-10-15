/**
 * QA (formsec): no X-Powered-By header (PHP version) on any answer
 * WordPress gives, and the security headers still sent where they were.
 *
 *   WP_BASE=http://localhost:8983 node wordpress/tools/qa/fix-formsec-headers.mjs
 *
 * GET and POST over the public pages (EN/ES), 404, feed, sitemaps, REST API
 * (pretty and ?rest_route=), admin-ajax with and without action,
 * admin-post, wp-cron, xmlrpc.php, /wp-admin/ (redirect for visitors) and
 * the login screen. A PHP file opened directly (outside WordPress) is only
 * reported: the theme cannot run there (needs expose_php = Off on the host).
 */
const BASE = process.env.WP_BASE || 'http://localhost:8983';

let failures = 0;
const check = (ok, label, detail = '') => {
  if (!ok) failures++;
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}${detail ? '  ' + detail : ''}`);
};

const URLS = [
  ['GET', '/'],
  ['GET', '/es/'],
  ['GET', '/contact/'],
  ['GET', '/es/contacto/'],
  ['GET', '/no-such-page-formsec/'],
  ['GET', '/es/no-existe-formsec/'],
  ['GET', '/feed/'],
  ['GET', '/wp-sitemap.xml'],
  ['GET', '/wp-sitemap-posts-page-1.xml'],
  ['GET', '/robots.txt'],
  ['GET', '/wp-json/'],
  ['GET', '/wp-json/wp/v2/pages?per_page=1'],
  ['GET', '/wp-json/wp/v2/users'],
  ['GET', '/wp-json/no/such-route'],
  ['GET', '/?rest_route=/'],
  ['GET', '/wp-admin/admin-ajax.php'],
  ['POST', '/wp-admin/admin-ajax.php'],
  ['GET', '/wp-admin/admin-ajax.php?action=heartbeat'],
  ['POST', '/wp-admin/admin-ajax.php?action=no_such_action'],
  ['GET', '/wp-admin/admin-post.php'],
  ['GET', '/wp-cron.php'],
  ['GET', '/xmlrpc.php'],
  ['POST', '/xmlrpc.php'],
  ['GET', '/wp-admin/'],
  ['GET', '/wp-admin/edit.php'],
  ['GET', '/wp-login.php'],
  ['POST', '/wp-login.php'],
  ['GET', '/wp-login.php?action=lostpassword'],
  ['GET', '/?author=1'],
  ['GET', '/?s=matsurfer'],
];

for (const [method, url] of URLS) {
  const res = await fetch(BASE + url, { method, redirect: 'manual', headers: method === 'POST' ? { 'Content-Type': 'application/x-www-form-urlencoded' } : {}, body: method === 'POST' ? 'a=1' : undefined });
  await res.arrayBuffer();
  const xpb = res.headers.get('x-powered-by');
  check(!xpb, `${method} ${url} (${res.status}) no X-Powered-By`, xpb || '');
}

/* the other security headers are still sent where they were */
for (const url of ['/', '/es/contacto/', '/wp-login.php', '/wp-admin/admin-ajax.php?action=heartbeat']) {
  const res = await fetch(BASE + url, { redirect: 'manual' });
  await res.arrayBuffer();
  const ok = res.headers.get('x-content-type-options') === 'nosniff' && res.headers.get('x-frame-options') === 'SAMEORIGIN' && !!res.headers.get('referrer-policy');
  check(ok, `GET ${url} keeps X-Content-Type-Options / X-Frame-Options / Referrer-Policy`);
}

/* outside WordPress: only reported */
const direct = await fetch(`${BASE}/wp-content/themes/matsurfer/functions.php`, { redirect: 'manual' });
await direct.arrayBuffer();
console.log(`INFO  direct hit on a theme PHP file (${direct.status}): X-Powered-By ${direct.headers.get('x-powered-by') || 'absent'} — WordPress does not run there; set expose_php = Off on the host`);

console.log(failures ? `${failures} FAILED` : 'ALL PASS');
process.exit(failures ? 1 : 0);
