/**
 * QA (module "forms", fix pass): behaviours added after the review.
 *  - the optional "Area of interest" placeholder can be chosen again
 *    (the required "I am interested in" placeholder stays disabled)
 *  - ?application=<slug not among the area options> leaves the area alone
 *  - sending fails → original errorText + mailto under the button
 *    (network error, and a WPForms refusal without field errors); a
 *    refusal with field errors shows no extra line; the line hides on the
 *    next submit; the styles match the Astro .lf__status
 *
 *   WP_BASE=http://localhost:8911 node wordpress/tools/qa/forms-fixpass.mjs <outDir> [en|es]
 * Prints a JSON report. Needs the WPForms test stub (or WPForms) on that port.
 * QA_READONLY=1: nothing reaches the server (no real submission, no lead,
 * no mail) — for checking a shared instance.
 */
import { launch, BASE } from './wp-login.mjs';

const out = process.argv[2] || '/tmp';
const lang = process.argv[3] === 'es' ? 'es' : 'en';
const path = lang === 'es' ? '/es/contacto/' : '/contact/';
const astroPath = lang === 'es' ? '/es/contact/' : '/contact/';
const width = Number(process.env.QA_WIDTH || 1440);
const readonly = !!process.env.QA_READONLY;

const browser = await launch();
const report = { lang, width };

async function open(query, setup) {
  const ctx = await browser.newContext({ viewport: { width, height: 1100 }, reducedMotion: 'reduce' });
  await ctx.addInitScript(() => {
    window.__events = [];
    window.addEventListener('lead:track', (e) => window.__events.push(e.detail));
  });
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));
  page.on('console', (m) => {
    if (m.type() === 'error' && !/Failed to load resource|ERR_FAILED/.test(m.text())) errors.push('console: ' + m.text());
  });
  if (setup) await setup(page);
  await page.goto(`${BASE}${path}${query}`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(300);
  return { ctx, page, errors };
}

async function fill(page) {
  await page.fill('[name="wpforms[fields][1]"]', 'Ada Lovelace');
  await page.fill('[name="wpforms[fields][2]"]', 'ada@example.org');
  await page.fill('[name="wpforms[fields][3]"]', 'ICMAB-CSIC');
  await page.fill('[name="wpforms[fields][4]"]', 'Researcher');
  if (!(await page.inputValue('[name="wpforms[fields][5]"]'))) await page.selectOption('[name="wpforms[fields][5]"]', { index: 1 });
  await page.fill('[name="wpforms[fields][7]"]', 'Fix pass test');
  await page.check('[name="wpforms[fields][8][]"]');
}

const statusInfo = (page) =>
  page.evaluate(() => {
    const box = document.querySelector('.lf__card .lf__status');
    if (!box) return null;
    const c = getComputedStyle(box);
    const a = box.querySelector('a');
    const btn = document.querySelector('.lf__card .wpforms-submit');
    return {
      visible: !box.hidden && c.display !== 'none',
      text: box.textContent.replace(/\s+/g, ' ').trim(),
      role: box.getAttribute('role'),
      href: a ? a.getAttribute('href') : null,
      style: `${c.fontSize}/${c.lineHeight} ${c.fontWeight} ${c.color} mt${c.marginTop}`,
      linkStyle: a ? `${getComputedStyle(a).color} ${getComputedStyle(a).textDecorationLine}` : null,
      gapBelowButton: btn ? Math.round(box.getBoundingClientRect().top - btn.getBoundingClientRect().bottom) : null,
    };
  });

/* 1 + 2: placeholders, unknown application slug */
{
  const { ctx, page, errors } = await open('?interest=research&application=a-new-application-slug');
  report.unknownApplication = await page.evaluate(() => {
    const s5 = document.querySelector('[name="wpforms[fields][5]"]');
    const s6 = document.querySelector('[name="wpforms[fields][6]"]');
    return {
      interest: s5.value,
      area: s6.value,
      interestPlaceholderDisabled: s5.options[0].value === '' && s5.options[0].disabled,
      areaPlaceholderDisabled: s6.options[0].value === '' && s6.options[0].disabled,
      metaApplication: document.querySelector('input[name="matsurfer_meta[application]"]')?.value,
    };
  });
  report.unknownApplication.jsErrors = errors;
  await ctx.close();
}
{
  const { ctx, page, errors } = await open('?interest=product&application=coatings');
  const before = await page.inputValue('[name="wpforms[fields][6]"]');
  await page.selectOption('[name="wpforms[fields][6]"]', '');
  const after = await page.inputValue('[name="wpforms[fields][6]"]');
  /* send with the area cleared: it must go through (optional field) */
  let success = 'skipped (read-only)';
  if (!readonly) {
    await fill(page);
    await page.click('.wpforms-submit');
    await page.waitForSelector('.lf__card .wpforms-confirmation-container-full', { timeout: 15000 });
    success = await page.evaluate(() => window.__events.filter((e) => e.name === 'form_submit_success').map((e) => e.data));
  }
  report.clearArea = { before, after, success, jsErrors: errors };
  await ctx.close();
}

/* 3: network error → status line; next submit hides it and succeeds */
{
  let fail = true;
  const { ctx, page, errors } = await open('', async (p) => {
    await p.route('**/admin-ajax.php', (route) => (fail ? route.abort('failed') : route.continue()));
  });
  await fill(page);
  await page.click('.wpforms-submit');
  await page.waitForTimeout(800);
  const shown = await statusInfo(page);
  await page.locator('.lf__card').screenshot({ path: `${out}/fixpass-${lang}-${width}-neterror.png` });
  if (!readonly) fail = false;
  await page.click('.wpforms-submit');
  const hiddenDuringSubmit = await statusInfo(page);
  if (!readonly) await page.waitForSelector('.lf__card .wpforms-confirmation-container-full', { timeout: 15000 });
  else await page.waitForTimeout(800);
  const events = await page.evaluate(() => window.__events.map((e) => e.name));
  report.networkError = { shown, hiddenOnNextSubmit: readonly ? 'n/a (read-only: the retry fails too)' : !!hiddenDuringSubmit && !hiddenDuringSubmit.visible, events, jsErrors: errors };
  await ctx.close();
}

/* 4: WPForms refusals */
for (const [name, body] of [
  ['generalOnly', { success: false, data: { errors: { general: { header: 'Token check failed' } } } }],
  ['fieldErrors', { success: false, data: { errors: { general: { header: 'Form has not been submitted, please see the errors below.' }, field: { 2: 'Invalid email' } } } }],
]) {
  const { ctx, page, errors } = await open('', async (p) => {
    await p.route('**/admin-ajax.php', (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(body) }));
  });
  await fill(page);
  await page.click('.wpforms-submit');
  await page.waitForTimeout(800);
  report[name] = { status: await statusInfo(page), jsErrors: errors };
  await ctx.close();
}

/* 5: the Astro .lf__status, unhidden, for the style comparison */
{
  const ctx = await browser.newContext({ viewport: { width, height: 1100 }, reducedMotion: 'reduce' });
  const page = await ctx.newPage();
  await page.goto(`http://127.0.0.1:4321${astroPath}`, { waitUntil: 'networkidle' });
  report.astroStatus = await page.evaluate(() => {
    const box = document.querySelector('.lf__status');
    box.hidden = false;
    const c = getComputedStyle(box);
    const a = box.querySelector('a');
    const btn = document.querySelector('.lf__submit');
    return {
      text: box.textContent.replace(/\s+/g, ' ').trim(),
      href: a.getAttribute('href'),
      style: `${c.fontSize}/${c.lineHeight} ${c.fontWeight} ${c.color} mt${c.marginTop}`,
      linkStyle: `${getComputedStyle(a).color} ${getComputedStyle(a).textDecorationLine}`,
      gapBelowButton: Math.round(box.getBoundingClientRect().top - btn.getBoundingClientRect().bottom),
    };
  });
  await page.locator('.lf__card').screenshot({ path: `${out}/fixpass-${lang}-${width}-astro-status.png` });
  await ctx.close();
}

console.log(JSON.stringify(report, null, 2));
await browser.close();
