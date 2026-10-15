/**
 * QA (module "forms"): contact form end to end with WPForms (or the test
 * stub). Preselection from the URL, hidden tracking inputs, error messages,
 * AJAX submission, thank-you box, lead:track events.
 *
 *   WP_BASE=http://localhost:8911 node wordpress/tools/qa/forms-e2e.mjs <outDir> [en|es] [query]
 * Prints a JSON report. Mail capture is checked separately (forms-mail.php).
 */
import { launch, BASE } from './wp-login.mjs';

const out = process.argv[2] || '/tmp';
const lang = process.argv[3] === 'es' ? 'es' : 'en';
const query = process.argv[4] || '?interest=research&application=coatings';
const path = lang === 'es' ? '/es/contacto/' : '/contact/';
const width = Number(process.env.QA_WIDTH || 1440);

const browser = await launch();
const ctx = await browser.newContext({ viewport: { width, height: 1100 }, reducedMotion: 'reduce' });
await ctx.addInitScript(() => {
  try {
    sessionStorage.setItem('lead:cta_source', 'product-hero');
    sessionStorage.setItem('lead:utm', JSON.stringify({ source: 'newsletter', medium: 'email', campaign: 'launch<b>', content: '', term: '' }));
  } catch (e) {}
  window.__events = [];
  window.addEventListener('lead:track', (e) => window.__events.push(e.detail));
});
const page = await ctx.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));
page.on('console', (m) => { if (m.type() === 'error') errors.push('console: ' + m.text()); });

const report = { lang, url: `${BASE}${path}${query}` };
await page.goto(report.url, { waitUntil: 'networkidle' });
await page.waitForTimeout(300);

report.preselect = await page.evaluate(() => {
  const v = (n) => document.querySelector(`[name="wpforms[fields][${n}]"]`)?.value ?? null;
  return { interest: v(5), area: v(6) };
});
report.meta = await page.evaluate(() =>
  Object.fromEntries([...document.querySelectorAll('input[name^="matsurfer_meta"]')].map((i) => [i.name, i.value]))
);
report.autocomplete = await page.evaluate(() => [1, 2, 3, 4].map((n) => document.querySelector(`[name="wpforms[fields][${n}]"]`)?.getAttribute('autocomplete')));

/* empty submit → original error messages */
await page.locator('.wpforms-submit').scrollIntoViewIfNeeded();
await page.click('.wpforms-submit');
await page.waitForTimeout(400);
report.errors = await page.evaluate(() => [...document.querySelectorAll('.lf__card .wpforms-error:not(input):not(select):not(textarea)')].map((e) => e.textContent.trim()));
report.focused = await page.evaluate(() => document.activeElement?.name || null);
await page.locator('.lf__card').screenshot({ path: `${out}/e2e-${lang}-errors.png` });

/* fill and send */
await page.fill('[name="wpforms[fields][1]"]', 'Ada Lovelace');
await page.fill('[name="wpforms[fields][2]"]', 'ada@example.org');
await page.fill('[name="wpforms[fields][3]"]', 'ICMAB-CSIC');
await page.fill('[name="wpforms[fields][4]"]', 'Researcher');
await page.fill('[name="wpforms[fields][7]"]', 'Testing the contact form "quotes" & <tags>.');
await page.check('[name="wpforms[fields][8][]"]');
report.errorsAfterFix = await page.evaluate(() => [...document.querySelectorAll('.lf__card .wpforms-error:not(input):not(select):not(textarea)')].map((e) => e.textContent.trim()));
await page.click('.wpforms-submit');
await page.waitForSelector('.lf__card .wpforms-confirmation-container-full', { timeout: 15000 });
await page.waitForTimeout(400);
report.confirmation = await page.evaluate(() => {
  const box = document.querySelector('.lf__card .wpforms-confirmation-container-full');
  return { text: box.innerText.trim(), focused: document.activeElement === box, role: box.getAttribute('role') };
});
await page.locator('.lf__card').screenshot({ path: `${out}/e2e-${lang}-success.png` });
report.events = await page.evaluate(() => window.__events);
report.jsErrors = errors;
console.log(JSON.stringify(report, null, 2));
await browser.close();
