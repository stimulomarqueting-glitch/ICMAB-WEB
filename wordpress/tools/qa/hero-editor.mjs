/**
 * QA (group "hero"): opens the home test page in the block editor and checks
 * the hero-field preview: renders (no error notice), no JS errors, in-place
 * editable texts present, editing the headline / a marquee word updates the
 * attributes, focusing+leaving an uppercase text (claim) leaves it intact,
 * the photo is clickable (media frame opens).
 *   WP_BASE=http://localhost:8901 node wordpress/tools/qa/hero-editor.mjs <postId> <out.png>
 */
import { launch, login, closeWelcome, BASE } from './wp-login.mjs';

const postId = process.argv[2] || '35';
const out = process.argv[3] || '/tmp/hero-editor.png';
const browser = await launch();
const page = await login(browser);
const errors = [];
page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));
page.on('console', (m) => { if (m.type() === 'error') errors.push('console: ' + m.text()); });
page.on('requestfailed', (r) => errors.push('requestfailed: ' + r.url().slice(0, 120)));
await page.goto(`${BASE}/wp-admin/post.php?post=${postId}&action=edit`);
await page.waitForSelector('.editor-styles-wrapper, iframe[name="editor-canvas"]', { timeout: 60000 });
await closeWelcome(page);
const hasIframe = !!(await page.$('iframe[name="editor-canvas"]'));
const frame = hasIframe ? page.frameLocator('iframe[name="editor-canvas"]') : page;
const headline = frame.locator('.mats-preview [data-mats-edit="headline"]').first();
await headline.waitFor({ timeout: 30000 });
await page.waitForTimeout(800);
console.log('iframe canvas:', hasIframe);
/* static preview (nothing selected/hovered) */
await page.mouse.move(5, 500);
await frame.locator('.mats-preview').first().evaluate((el) => el.scrollIntoView());
await page.waitForTimeout(400);
await page.screenshot({ path: out.replace(/\.png$/, '-static.png'), fullPage: false });
const tt = await frame.locator('.mats-preview [data-mats-edit="claim"]').first().evaluate((el) => getComputedStyle(el).textTransform);
console.log('claim text-transform (static):', tt);
const body = await frame.locator('body').innerText();
console.log('error notice:', /Error loading block|No se ha podido cargar/i.test(body));
const paths = await frame.locator('.mats-preview [data-mats-edit]').evaluateAll((ns) => ns.map((n) => n.getAttribute('data-mats-edit') + (n.getAttribute('contenteditable') === 'true' ? '' : '(not editable)')));
console.log('editable paths:', paths.join(', '));
const imgs = await frame.locator('.mats-preview [data-mats-image]').evaluateAll((ns) => ns.map((n) => n.tagName + ':' + n.getAttribute('data-mats-image')));
console.log('images:', imgs.join(', '));
const canvasCount = await frame.locator('.mats-preview canvas[data-vapour]').count();
console.log('canvas in preview:', canvasCount);

const attrs = () => page.evaluate(() => {
  const b = wp.data.select('core/block-editor').getBlocks().find((x) => x.name === 'matsurfer/hero-field');
  return b ? b.attributes : null;
});
const before = await attrs();

/* 1. uppercase text: focus + Enter without typing must not change the value */
const claim = frame.locator('.mats-preview [data-mats-edit="claim"]').first();
await claim.hover();
await claim.click();
await page.keyboard.press('Enter');
await page.waitForTimeout(500);
let a = await attrs();
console.log('claim unchanged after focus/blur:', a.claim === before.claim, JSON.stringify(a.claim));
const eyebrow = frame.locator('.mats-preview [data-mats-edit="eyebrow"]').first();
await eyebrow.hover();
await eyebrow.click();
await page.keyboard.press('Enter');
await page.waitForTimeout(500);
a = await attrs();
console.log('eyebrow unchanged after focus/blur:', a.eyebrow === before.eyebrow, JSON.stringify(a.eyebrow));

/* 2. headline edited in place */
await headline.click();
await page.keyboard.press('Control+End');
await page.keyboard.type(' (edited)');
await page.keyboard.press('Enter');
await page.waitForTimeout(1500);
a = await attrs();
console.log('headline after edit:', JSON.stringify(a.headline));

/* 3. a marquee word edited in place */
const m1 = frame.locator('.mats-preview [data-mats-edit="marquee.1"]').first();
await m1.waitFor({ timeout: 15000 });
await m1.hover();
await m1.click();
await page.keyboard.press('Control+End');
await page.keyboard.type(' X');
await page.keyboard.press('Enter');
await page.waitForTimeout(1500);
a = await attrs();
console.log('marquee after edit:', JSON.stringify(a.marquee));

/* 4. the photo receives the click (opens the media library) */
const img = frame.locator('.mats-preview img[data-mats-image="photo"]').first();
const hit = await img.evaluate((el) => {
  const r = el.getBoundingClientRect();
  const t = el.ownerDocument.elementFromPoint(r.left + r.width * 0.3, r.top + r.height * 0.5);
  return t === el ? 'img' : (t ? t.className : 'none');
});
console.log('element under photo centre:', hit);
await page.keyboard.press('Escape');
await page.screenshot({ path: out, fullPage: false });
await img.click({ force: true });
await page.waitForTimeout(1200);
console.log('media frame open:', await page.locator('.media-modal').first().isVisible().catch(() => false));
await page.keyboard.press('Escape');
console.log(errors.length ? errors.join('\n') : 'no JS errors');
await browser.close();
