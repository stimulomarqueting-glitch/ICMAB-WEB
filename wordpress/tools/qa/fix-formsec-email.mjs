/**
 * QA (formsec): the tracking rows of the lead notification email speak the
 * form's language, and the CTA shows a readable label with the slug in
 * brackets.
 *
 *   WP_BASE=http://localhost:8983 MATS_WP_PORT=8983 node wordpress/tools/qa/fix-formsec-email.mjs
 *   (run from the repo root; needs the WPForms stub; turns the QA mail
 *    capture on, submits three leads, reads the captured emails, then turns
 *    the capture off and deletes the test leads)
 *
 * Scenarios:
 *  1. ES: lands on /es/?utm_source=newsletter&utm_campaign=oct, clicks the
 *     hero button → /es/contacto/ → submit.
 *  2. ES: /es/aplicaciones/ → "applications-coatings" button → submit.
 *  3. EN: /applications/?utm_source=linkedin&utm_medium=social → the
 *     "applications-coatings" button → submit.
 * Prints the tracking rows of each email, PASS/FAIL lines, exits 1 on failure.
 */
import { launch } from './wp-login.mjs';
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const BASE = process.env.WP_BASE || 'http://localhost:8983';
const PORT = process.env.MATS_WP_PORT || new URL(BASE).port;
const HELPER = path.join(path.dirname(fileURLToPath(import.meta.url)), 'fix-formsec-email.php');
const php = (mode) => execFileSync('php', ['/home/user/wpsite-cli.php', HELPER], { env: { ...process.env, MATS_WP_PORT: PORT, MATS_QA_MODE: mode } }).toString();

let failures = 0;
const check = (ok, label, detail = '') => {
  if (!ok) failures++;
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}${detail ? '  ' + detail : ''}`);
};

const scenarios = [
  { name: 'ES hero + UTM', land: '/es/?utm_source=newsletter&utm_campaign=oct', cta: 'hero', who: 'José Núñez', email: 'jose@formsec.example.org' },
  { name: 'ES application button', land: '/es/aplicaciones/', cta: 'applications-coatings', who: 'Lucía Pérez', email: 'lucia@formsec.example.org' },
  { name: 'EN application button + UTM', land: '/applications/?utm_source=linkedin&utm_medium=social', cta: 'applications-coatings', who: 'Ada Lovelace', email: 'ada@formsec.example.org' },
];

console.log(php('on').trim());
const browser = await launch();
try {
  for (const s of scenarios) {
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const page = await ctx.newPage();
    await page.goto(BASE + s.land, { waitUntil: 'networkidle' });
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.locator(`a[data-cta-source="${s.cta}"]`).first().click()]);
    await page.fill('[name="wpforms[fields][1]"]', s.who);
    await page.fill('[name="wpforms[fields][2]"]', s.email);
    await page.fill('[name="wpforms[fields][3]"]', 'ICMAB-CSIC');
    await page.fill('[name="wpforms[fields][4]"]', 'QA');
    if (!(await page.$eval('[name="wpforms[fields][5]"]', (el) => el.value))) await page.selectOption('[name="wpforms[fields][5]"]', { index: 1 });
    await page.fill('[name="wpforms[fields][7]"]', 'Prueba formsec / formsec test');
    await page.check('[name="wpforms[fields][8][]"]');
    await page.click('.wpforms-submit');
    await page.waitForSelector('.wpforms-confirmation-container-full', { timeout: 15000 }).catch(() => {});
    const done = await page.$('.wpforms-confirmation-container-full');
    check(!!done, `${s.name}: form sent (${page.url().replace(BASE, '')})`);
    await ctx.close();
  }
} finally {
  await browser.close();
}

const { mails, leads } = JSON.parse(php('read'));
console.log(php('off').trim());
check(mails.length === scenarios.length, `${scenarios.length} notification emails captured`, String(mails.length));
check(leads.map((l) => l.cta_source).join() === scenarios.map((s) => s.cta).join(), 'Contactos inbox keeps the raw button codes', leads.map((l) => `${l.language}:${l.cta_source}:${l.application}`).join(' '));

const META_EN = ['Language', 'Source page', 'CTA', 'UTM']; // the old labels
const row = (m, label) => (m.rows.find((r) => r[0] === label) || [null, null])[1];
mails.forEach((m, i) => {
  const s = scenarios[i];
  if (!s) return;
  console.log(`\n── ${s.name}: ${m.subject}`);
  m.rows.slice(-5).forEach((r) => console.log(`   ${r[0]} | ${r[1].replace(/\n/g, ' ')}`));
  const es = s.land.startsWith('/es/');
  if (es) {
    check(row(m, 'Idioma') === 'Español (es)', `${s.name}: Idioma`, row(m, 'Idioma'));
    check(/^https?:\/\/[^/]+\/es\/contacto\/$/.test(row(m, 'Página de origen') || ''), `${s.name}: Página de origen`, row(m, 'Página de origen'));
    check(!m.rows.some((r) => META_EN.includes(r[0])), `${s.name}: no English tracking labels`);
  } else {
    check(row(m, 'Language') === 'English (en)', `${s.name}: Language`, row(m, 'Language'));
    check(/^https?:\/\/[^/]+\/contact\/$/.test(row(m, 'Source page') || ''), `${s.name}: Source page`, row(m, 'Source page'));
  }
  const cta = row(m, es ? 'Botón de origen' : 'Source button') || '';
  if (s.cta === 'hero') {
    check(cta === 'Portada: botón principal [hero]', `${s.name}: readable CTA + slug`, cta);
  } else if (es) {
    check(/^Aplicaciones: botón de «.+» \[applications-coatings\]$/.test(cta), `${s.name}: readable CTA + slug`, cta);
  } else {
    check(/^Applications: button of “.+” \[applications-coatings\]$/.test(cta), `${s.name}: readable CTA + slug`, cta);
  }
  const app = row(m, es ? 'Aplicación' : 'Application');
  if (s.cta.startsWith('applications-')) {
    check(/^.+ \(coatings\)$/.test(app || ''), `${s.name}: application name + slug`, app);
  }
  const utm = row(m, es ? 'Campaña (UTM)' : 'Campaign (UTM)');
  if (s.land.includes('utm_')) {
    const want = es ? 'fuente: newsletter · campaña: oct' : 'source: linkedin · medium: social';
    check(utm === want, `${s.name}: UTM`, utm);
  }
});

console.log(failures ? `\n${failures} FAILED` : '\nALL PASS');
process.exit(failures ? 1 : 0);
