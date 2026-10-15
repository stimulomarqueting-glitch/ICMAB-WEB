/**
 * QA (group "hero"): the uppercase texts of the hero (claim, eyebrow,
 * marquee words) must keep their stored case through every in-place editing
 * path, and the idle / hovered / selected preview must look like the public
 * page (text-transform uppercase). Never saves the post: the attributes are
 * restored through wp.data after each scenario.
 *   WP_BASE=http://localhost:8901 node wordpress/tools/qa/hero-uppercase.mjs <postId> <outDir>
 */
import { launch, login, closeWelcome, BASE } from './wp-login.mjs';

const postId = process.argv[2] || '35';
const outDir = process.argv[3] || '/tmp';
const browser = await launch();
const page = await login(browser);
const errors = [];
page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));
page.on('console', (m) => { if (m.type() === 'error' && !/gravatar/i.test(m.text())) errors.push('console: ' + m.text()); });
await page.goto(`${BASE}/wp-admin/post.php?post=${postId}&action=edit`);
await page.waitForSelector('iframe[name="editor-canvas"]', { timeout: 60000 });
await closeWelcome(page);
const frame = page.frameLocator('iframe[name="editor-canvas"]');
const sel = (p) => `.mats-preview [data-mats-edit="${p}"]`;
await frame.locator(sel('headline')).first().waitFor({ timeout: 30000 });
await page.waitForTimeout(800);

const block = () => page.evaluate(() => {
  const b = wp.data.select('core/block-editor').getBlocks().find((x) => x.name === 'matsurfer/hero-field');
  return b ? { id: b.clientId, a: b.attributes } : null;
});
const start = await block();
const pick = (a) => ({ claim: a.claim, eyebrow: a.eyebrow, marquee: a.marquee, headline: a.headline });
const original = JSON.stringify(pick(start.a));
const restore = async () => {
  await page.evaluate(({ id, a }) => {
    wp.data.dispatch('core/block-editor').updateBlockAttributes(id, a);
    wp.data.dispatch('core/block-editor').clearSelectedBlock();
  }, { id: start.id, a: pick(start.a) });
  await page.mouse.move(2, 2);
  await page.waitForTimeout(1500);
  await frame.locator(sel('claim')).first().waitFor();
};
const check = async (name) => {
  await page.waitForTimeout(700);
  const now = JSON.stringify(pick((await block()).a));
  const ok = now === original;
  console.log((ok ? 'OK   ' : 'FAIL ') + name + (ok ? '' : '  → ' + now));
  await restore();
  return ok;
};
const tt = (p) => frame.locator(sel(p)).first().evaluate((el) => getComputedStyle(el).textTransform);
const states = async (label) => {
  const r = [];
  for (const p of ['claim', 'eyebrow', 'marquee.0']) r.push(p + '=' + (await tt(p)));
  console.log(label.padEnd(26), r.join('  '));
};
const iframeBox = await page.locator('iframe[name="editor-canvas"]').boundingBox();

/* ── look: idle / hovered / selected must stay uppercase like the site ── */
await page.mouse.move(2, 2);
await page.waitForTimeout(300);
await states('idle');
const claimBox = async () => {
  const b = await frame.locator(sel('claim')).first().boundingBox();
  return b;
};
const lead = frame.locator(sel('lead')).first();
await lead.hover();
await page.waitForTimeout(300);
await states('pointer over section');
await page.screenshot({ path: `${outDir}/editor-hover.png` });
await lead.click();
await page.keyboard.press('Escape');
await page.mouse.move(2, 2);
await page.waitForTimeout(500);
console.log('selected:', await frame.locator('.mats-preview.is-selected').count() > 0);
await states('selected (pointer away)');
await restore();

/* ── editing paths ── */
let all = true;
// 1. keyboard focus with nothing selected, pointer outside, Escape
await page.evaluate(() => wp.data.dispatch('core/block-editor').clearSelectedBlock());
await page.mouse.move(2, 2);
await page.waitForTimeout(300);
await frame.locator(sel('claim')).first().evaluate((el) => el.focus());
await page.waitForTimeout(300);
await states('claim focused (keyboard)');
await page.screenshot({ path: `${outDir}/editor-focus-claim.png` });
await page.keyboard.press('Escape');
all = (await check('keyboard focus + Escape (claim)')) && all;

await page.evaluate(() => wp.data.dispatch('core/block-editor').clearSelectedBlock());
await page.mouse.move(2, 2);
await frame.locator(sel('eyebrow')).first().evaluate((el) => el.focus());
await page.waitForTimeout(300);
await page.keyboard.press('Escape');
all = (await check('keyboard focus + Escape (eyebrow)')) && all;

await page.evaluate(() => wp.data.dispatch('core/block-editor').clearSelectedBlock());
await page.mouse.move(2, 2);
await frame.locator(sel('marquee.2')).first().evaluate((el) => el.focus());
await page.waitForTimeout(300);
await page.keyboard.press('Escape');
all = (await check('keyboard focus + Escape (marquee.2)')) && all;

// 2. keyboard focus + Enter
await frame.locator(sel('claim')).first().evaluate((el) => el.focus());
await page.waitForTimeout(300);
await page.keyboard.press('Enter');
all = (await check('keyboard focus + Enter (claim)')) && all;

// 3. click + Enter / Escape
for (const p of ['claim', 'eyebrow', 'marquee.2']) {
  await frame.locator(sel(p)).first().click();
  await page.keyboard.press('Enter');
  all = (await check(`click + Enter (${p})`)) && all;
  await frame.locator(sel(p)).first().click();
  await page.keyboard.press('Escape');
  all = (await check(`click + Escape (${p})`)) && all;
}

// 4. click, then click outside the block (sidebar, top bar, empty canvas below)
for (const p of ['claim', 'eyebrow', 'marquee.2']) {
  await frame.locator(sel(p)).first().click();
  await page.waitForTimeout(200);
  await page.mouse.click(iframeBox.x + iframeBox.width - 12, iframeBox.y + iframeBox.height - 12);
  all = (await check(`click + click canvas corner (${p})`)) && all;
  await frame.locator(sel(p)).first().click();
  await page.waitForTimeout(200);
  await page.locator('.interface-interface-skeleton__sidebar, .editor-sidebar').first().click({ position: { x: 40, y: 300 } }).catch(() => {});
  all = (await check(`click + click sidebar (${p})`)) && all;
}

// 5. Tab from the claim to the next editable text
await frame.locator(sel('claim')).first().click();
await page.keyboard.press('Tab');
await page.waitForTimeout(200);
await page.keyboard.press('Tab');
await page.waitForTimeout(200);
await page.mouse.click(iframeBox.x + iframeBox.width - 12, iframeBox.y + iframeBox.height - 12);
all = (await check('click + Tab + Tab + click outside')) && all;

// 6. a real edit keeps the case typed
await frame.locator(sel('claim')).first().click();
await page.keyboard.press('Control+End');
await page.keyboard.type(' Ok');
await page.waitForTimeout(200);
await page.mouse.click(iframeBox.x + iframeBox.width - 12, iframeBox.y + iframeBox.height - 12);
await page.waitForTimeout(900);
const edited = (await block()).a.claim;
const expect = start.a.claim + ' Ok';
console.log((edited === expect ? 'OK   ' : 'FAIL ') + 'typed edit keeps case → ' + JSON.stringify(edited));
all = edited === expect && all;
await restore();

await frame.locator(sel('marquee.1')).first().click();
await page.keyboard.press('Control+End');
await page.keyboard.type(' x');
await page.keyboard.press('Enter');
await page.waitForTimeout(900);
const m1 = (await block()).a.marquee[1];
console.log((m1 === start.a.marquee[1] + ' x' ? 'OK   ' : 'FAIL ') + 'marquee.1 edit → ' + JSON.stringify(m1));
all = m1 === start.a.marquee[1] + ' x' && all;
await restore();

console.log(all ? 'ALL PATHS KEEP THE STORED CASE' : 'SOME PATHS CHANGE THE CASE');
console.log(errors.length ? errors.join('\n') : 'no JS errors');
await browser.close();
