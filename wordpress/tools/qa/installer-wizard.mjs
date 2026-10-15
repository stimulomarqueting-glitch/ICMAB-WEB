/**
 * QA: drives the setup wizard (MatSurfer → Puesta en marcha) in a real
 * browser, like the person installing the theme.
 *
 *   WP_BASE=http://localhost:8913 node wordpress/tools/qa/installer-wizard.mjs <actions…> [--shots=DIR]
 *
 * Actions, run in the order given:
 *   activate-theme   Appearance → Themes → activate MatSurfer, check the reminder notice
 *   all              "Hacerlo todo" (JS, one AJAX request per step)
 *   nojs-all         "Hacerlo todo" with JavaScript disabled (admin-post.php chain)
 *   step:<key>       one step button (plugins, smtp, languages, content, forms, review)
 *   overwrite-cancel tick "Sobrescribir…", click step 3, CANCEL the dialog (nothing runs)
 *   overwrite        tick "Sobrescribir…", click step 3, ACCEPT the dialog
 *   notice-gone      the reminder notice is no longer shown on the dashboard
 *   mobile           screenshot at phone width
 *   editor-denied    as the Editor "qa-editor" / "qa-editor-pass" (create it first):
 *                    no menu entry, the screen and the AJAX/admin-post handlers refuse
 * Prints every card's ✓/✗ state and messages after each action.
 */
import fs from 'node:fs';
import { BASE, launch, login } from './wp-login.mjs';

const args = process.argv.slice(2);
const shotsArg = args.find((a) => a.startsWith('--shots='));
const SHOTS = shotsArg ? shotsArg.slice(8) : '/tmp/claude-0/-home-user-ICMAB-WEB/ae5d27f5-1af0-537f-966f-1d2257e6cfbd/scratchpad/installer';
fs.mkdirSync(SHOTS, { recursive: true });
const actions = args.filter((a) => !a.startsWith('--'));
const WIZARD = `${BASE}/wp-admin/admin.php?page=mats-setup`;
let shot = 0;
let failures = 0;

const browser = await launch();
const page = await login(browser);
page.on('pageerror', (e) => {
  console.log('  [JS error]', e.message);
  failures++;
});

async function snap(name, p = page) {
  const file = `${SHOTS}/${String(++shot).padStart(2, '0')}-${name}.png`;
  await p.screenshot({ path: file, fullPage: true });
  console.log('  screenshot', file);
}

async function report(p = page) {
  const cards = await p.$$eval('.mats-step', (els) =>
    els.map((el) => ({
      step: el.dataset.step,
      state: el.dataset.state,
      pill: el.querySelector('.mats-step__pill')?.textContent.trim(),
      checks: [...el.querySelectorAll('.mats-step__checks li')].map((li) => li.className.replace('is-', '') + ' ' + li.textContent.trim()),
      messages: [...el.querySelectorAll('.mats-step__messages li')].map((li) => li.className.replace('is-', '') + ' ' + li.textContent.trim()),
    })),
  );
  const summary = await p.$eval('.mats-setup__summary', (el) => el.textContent.trim()).catch(() => '');
  console.log('  summary:', summary);
  for (const c of cards) {
    console.log(`  [${c.step}] ${c.state} — ${c.pill}`);
    for (const l of c.checks) console.log('     check ' + l.slice(0, 220));
    for (const l of c.messages) console.log('     msg   ' + l.slice(0, 260));
  }
  return cards;
}

async function waitDone(p = page) {
  await p.waitForFunction(() => document.getElementById('mats-setup-form')?.getAttribute('aria-busy') === 'false', null, { timeout: 600000 });
}

async function runButton(value, { dialog } = {}) {
  await page.goto(WIZARD);
  if (dialog) page.once('dialog', (d) => (dialog === 'accept' ? d.accept() : d.dismiss()));
  const t0 = Date.now();
  await page.click(`button[name="step"][value="${value}"]`);
  await waitDone();
  console.log(`  ${value}: ${((Date.now() - t0) / 1000).toFixed(1)} s`);
}

for (const action of actions) {
  console.log(`\n== ${action}`);
  if (action === 'activate-theme') {
    await page.goto(`${BASE}/wp-admin/themes.php`);
    const active = await page.$('.theme.active[data-slug="matsurfer"]');
    if (!active) {
      await page.hover('.theme[data-slug="matsurfer"]');
      await Promise.all([page.waitForNavigation(), page.click('.theme[data-slug="matsurfer"] .theme-actions a.activate')]);
    }
    const notice = await page.$('.mats-setup-notice');
    console.log('  reminder notice shown:', !!notice);
    if (!notice) failures++;
    await snap('themes-notice');
    if (notice) await Promise.all([page.waitForNavigation(), page.click('.mats-setup-notice a.button-primary')]);
    console.log('  now at', page.url());
    await snap('wizard-before');
    await report();
  } else if (action === 'all') {
    await runButton('all');
    await snap('wizard-after-all');
    await report();
  } else if (action === 'nojs-all') {
    const ctx = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 1440, height: 1000 }, storageState: await page.context().storageState() });
    const p = await ctx.newPage();
    await p.goto(WIZARD);
    const t0 = Date.now();
    await Promise.all([p.waitForNavigation({ timeout: 600000, url: /page=mats-setup/ }), p.click('button[name="step"][value="all"]')]);
    console.log(`  no-JS all: ${((Date.now() - t0) / 1000).toFixed(1)} s → ${p.url()}`);
    await snap('wizard-nojs-all', p);
    await report(p);
    await ctx.close();
  } else if (action.startsWith('step:')) {
    const value = action.slice(5);
    await runButton(value);
    await snap(`wizard-step-${value}`);
    await report();
  } else if (action === 'overwrite-cancel' || action === 'overwrite') {
    await page.goto(WIZARD);
    await page.check('.mats-setup__overwrite');
    let asked = '';
    page.once('dialog', async (d) => {
      asked = d.message();
      if (action === 'overwrite') await d.accept();
      else await d.dismiss();
    });
    await page.click('button[name="step"][value="content"]');
    await page.waitForTimeout(500);
    console.log('  confirm dialog shown:', asked ? 'yes — ' + asked.split('\n')[0] : 'NO');
    if (!asked) failures++;
    if (action === 'overwrite') {
      await waitDone();
      await snap('wizard-overwrite');
    } else {
      const busy = await page.$eval('#mats-setup-form', (f) => f.getAttribute('aria-busy'));
      console.log('  ran after cancel:', busy === 'true' ? 'YES (wrong)' : 'no');
    }
    await report();
  } else if (action === 'notice-gone') {
    await page.goto(`${BASE}/wp-admin/index.php`);
    const notice = await page.$('.mats-setup-notice');
    console.log('  reminder notice on the dashboard:', notice ? 'STILL SHOWN' : 'gone');
    if (notice) failures++;
  } else if (action === 'mobile') {
    const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, storageState: await page.context().storageState() });
    const p = await ctx.newPage();
    await p.goto(WIZARD);
    await snap('wizard-mobile', p);
    const overflow = await p.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    console.log('  horizontal overflow px:', overflow);
    await ctx.close();
  } else if (action === 'editor-denied') {
    const ep = await login(browser, 'qa-editor', 'qa-editor-pass');
    await ep.goto(`${BASE}/wp-admin/index.php`);
    const menu = await ep.$('a[href*="page=mats-setup"]');
    const notice = await ep.$('.mats-setup-notice');
    const res = await ep.goto(WIZARD);
    const body = (await ep.textContent('body')).replace(/\s+/g, ' ').slice(0, 120);
    const ajax = await ep.evaluate(async (base) => {
      const r = await fetch(base + '/wp-admin/admin-ajax.php', { method: 'POST', body: new URLSearchParams({ action: 'mats_installer_step', step: 'content', _ajax_nonce: 'x' }) });
      return r.status + ' ' + (await r.text()).slice(0, 120);
    }, BASE);
    const post = await ep.evaluate(async (base) => {
      const r = await fetch(base + '/wp-admin/admin-post.php', { method: 'POST', body: new URLSearchParams({ action: 'mats_installer_run', step: 'all', _wpnonce: 'x' }) });
      return r.status + '';
    }, BASE);
    console.log('  menu entry for editor:', menu ? 'SHOWN (wrong)' : 'none', '| reminder notice:', notice ? 'SHOWN (wrong)' : 'none');
    console.log('  wizard screen:', res.status(), body);
    console.log('  AJAX step:', ajax);
    console.log('  admin-post run:', post);
    if (menu || notice || !ajax.startsWith('403') || !post.startsWith('403')) failures++;
    await ep.context().close();
  } else {
    console.log('  unknown action');
  }
}

await browser.close();
console.log(failures ? `\n${failures} failure(s)` : '\nwizard run finished');
process.exit(failures ? 1 : 0);
