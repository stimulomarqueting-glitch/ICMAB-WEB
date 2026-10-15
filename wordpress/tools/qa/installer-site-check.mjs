/**
 * QA (read-only): how the setup wizard looks on an already installed site,
 * WITHOUT running any step — safe on the shared full site.
 *
 *   WP_BASE=http://localhost:8950 node wordpress/tools/qa/installer-site-check.mjs [--shots=DIR]
 *
 *  - dashboard: is the post-activation reminder shown? (it must not be when every step is done)
 *  - the wizard screen: summary, ✓/✗ of every card, no JS errors
 *  - ?mats_confirm=content: the no-JS confirmation box renders (nothing runs until it is accepted)
 *  - review checklist wording (menu directions)
 *  - phone width: no horizontal overflow
 * Loading the dashboard may hide the reminder for good when every step is
 * done (that is the intended behaviour); nothing else is written.
 */
import fs from 'node:fs';
import { BASE, launch, login } from './wp-login.mjs';

const shotsArg = process.argv.find((a) => a.startsWith('--shots='));
const SHOTS = shotsArg ? shotsArg.slice(8) : '/tmp/claude-0/-home-user-ICMAB-WEB/ae5d27f5-1af0-537f-966f-1d2257e6cfbd/scratchpad/installer/site-check';
fs.mkdirSync(SHOTS, { recursive: true });
const WIZARD = `${BASE}/wp-admin/admin.php?page=mats-setup`;
let failures = 0;

const browser = await launch();
const page = await login(browser);
page.on('pageerror', (e) => {
  console.log('  [JS error]', e.message);
  failures++;
});

await page.goto(`${BASE}/wp-admin/index.php`);
const notice = await page.$('.mats-setup-notice');
console.log('dashboard reminder notice:', notice ? 'shown' : 'not shown');

await page.goto(WIZARD);
const summary = await page.$eval('.mats-setup__summary', (el) => el.textContent.trim());
const cards = await page.$$eval('.mats-step', (els) => els.map((el) => `${el.dataset.step}=${el.dataset.state}`));
console.log('wizard summary:', summary);
console.log('cards:', cards.join(' '));
const allDone = /^4 \/ 4/.test(summary);
if (allDone && notice) {
  console.log('  FAIL: every step is done but the reminder is still shown');
  failures++;
}
const review = await page.$$eval('#mats-step-review .mats-step__checks li', (els) => els.map((li) => li.textContent.replace(/\s+/g, ' ').trim()));
for (const line of review) console.log('  review:', line.slice(0, 200));
if (review.some((l) => l.includes('MatSurfer → Ajustes'))) {
  console.log('  FAIL: old menu direction "MatSurfer → Ajustes de la web"');
  failures++;
}
const lang = await page.$$eval('#mats-step-languages .mats-step__checks li', (els) => els.map((li) => li.textContent.replace(/\s+/g, ' ').trim()));
for (const line of lang) console.log('  languages:', line.slice(0, 200));
await page.screenshot({ path: `${SHOTS}/wizard.png`, fullPage: true });

await page.goto(`${WIZARD}&mats_confirm=content`);
const box = await page.$('#mats-confirm');
const boxButtons = box ? await page.$$eval('#mats-confirm form button, #mats-confirm form a', (els) => els.map((e) => e.textContent.trim())) : [];
console.log('confirmation box (?mats_confirm=content):', box ? `shown — buttons: ${boxButtons.join(' | ')}` : 'NOT SHOWN');
if (!box) failures++;
if (box) await box.screenshot({ path: `${SHOTS}/confirm-box.png` });

const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, storageState: await page.context().storageState() });
const mobile = await ctx.newPage();
await mobile.goto(`${WIZARD}&mats_confirm=all`);
const overflow = await mobile.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
console.log('phone width overflow px:', overflow);
if (overflow > 0) failures++;
await mobile.screenshot({ path: `${SHOTS}/wizard-mobile-confirm.png`, fullPage: true });
await ctx.close();

await browser.close();
console.log(failures ? `\n${failures} failure(s)` : '\nsite check OK');
process.exit(failures ? 1 : 0);
