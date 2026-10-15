/**
 * QA (module "forms"): the contact section in the block editor — preview
 * with the real form, editor note, in-place editing of the title and a step.
 *
 *   WP_BASE=http://localhost:8911 node wordpress/tools/qa/forms-editor.mjs <postId> <out.png> [--no-edit]
 */
import { launch, login, closeWelcome, BASE } from './wp-login.mjs';

const postId = process.argv[2];
const out = process.argv[3] || '/tmp/forms-editor.png';
const edit = !process.argv.includes('--no-edit');
const browser = await launch();
const page = await login(browser, 'admin', 'admin-local-pass', { width: 1600, height: 1100 });
const errors = [];
page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));
page.on('console', (m) => { if (m.type() === 'error') errors.push('console: ' + m.text()); });
await page.goto(`${BASE}/wp-admin/post.php?post=${postId}&action=edit`);
await page.waitForSelector('.editor-styles-wrapper, iframe[name="editor-canvas"]', { timeout: 60000 });
await closeWelcome(page);
const hasIframe = !!(await page.$('iframe[name="editor-canvas"]'));
const frame = hasIframe ? page.frameLocator('iframe[name="editor-canvas"]') : page;
await frame.locator('.mats-preview .contact-sec').first().waitFor({ timeout: 30000 });
await page.waitForTimeout(800);

const card = frame.locator('.mats-preview .lf__card').first();
const info = await card.evaluate((el) => {
  const btn = el.querySelector('.wpforms-submit');
  const input = el.querySelector('[name="wpforms[fields][1]"]');
  return {
    note: el.querySelector('.mats-note')?.innerText || null,
    noteLink: el.querySelector('.mats-note a')?.getAttribute('href') || null,
    form: !!el.querySelector('form.wpforms-form'),
    missing: el.querySelector('.lf__missing')?.innerText || null,
    buttonBg: btn ? getComputedStyle(btn).backgroundColor : null,
    buttonRadius: btn ? getComputedStyle(btn).borderTopLeftRadius : null,
    inputBorder: input ? getComputedStyle(input).borderTopColor : null,
    asterisksVisible: [...el.querySelectorAll('.wpforms-required-label')].some((s) => getComputedStyle(s).display !== 'none'),
  };
});
const report = { iframe: hasIframe, ...info };

if (edit) {
  const title = frame.locator('.mats-preview [data-mats-edit="title"]').first();
  report.titleBefore = await title.innerText();
  await title.click();
  await page.keyboard.press('End');
  await page.keyboard.type(' now');
  await page.keyboard.press('Enter');
  await page.waitForTimeout(1200);
  const step = frame.locator('.mats-preview [data-mats-edit="steps.1"]').first();
  await step.click();
  await page.keyboard.press('End');
  await page.keyboard.type(' (edited)');
  await page.keyboard.press('Enter');
  await page.waitForTimeout(1500);
  report.attributes = await page.evaluate(() => {
    const b = wp.data.select('core/block-editor').getBlocks().find((x) => x.name === 'matsurfer/contact');
    return b ? { title: b.attributes.title, step2: b.attributes.steps[1], lock: b.attributes.lock } : null;
  });
}
await page.screenshot({ path: out, fullPage: false });
report.errors = errors;
console.log(JSON.stringify(report, null, 2));
await browser.close();
