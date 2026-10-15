/**
 * QA (group "team"): the showcase with its title emptied in the editor
 * (unsaved). The preview must show the "missing title" warning and no
 * empty h1; typing the title back in the side panel restores the h1.
 *   node wordpress/tools/qa/team-notitle.mjs <postId> <outDir>
 */
import { launch, login, closeWelcome, BASE } from './wp-login.mjs';
import fs from 'node:fs';

const postId = process.argv[2] || '35';
const out = process.argv[3] || '/tmp/team-notitle';
fs.mkdirSync(out, { recursive: true });

const browser = await launch();
const page = await login(browser, 'admin', 'admin-local-pass', { width: 1440, height: 1000 });
const errors = [];
page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));
page.on('console', (m) => { if (m.type() === 'error' && !/gravatar|ERR_TUNNEL/.test(m.text())) errors.push('console: ' + m.text()); });
await page.goto(`${BASE}/wp-admin/post.php?post=${postId}&action=edit`);
await page.waitForSelector('iframe[name="editor-canvas"]', { timeout: 60000 });
await closeWelcome(page);
const frame = page.frameLocator('iframe[name="editor-canvas"]');
const id = await page.evaluate(() => wp.data.select('core/block-editor').getBlocks().find((b) => b.name === 'matsurfer/team-showcase').clientId);
const p = frame.locator(`[data-block="${id}"]`);
await p.locator('.mats-preview__html section.show').waitFor({ timeout: 30000 });
const original = await page.evaluate((cid) => wp.data.select('core/block-editor').getBlock(cid).attributes.title, id);

const state = async (label) => {
  const s = await p.evaluate((el) => {
    const sec = el.querySelector('section.show');
    return {
      h1: [...el.querySelectorAll('h1')].map((h) => h.outerHTML),
      labelledby: sec && sec.getAttribute('aria-labelledby'),
      label: sec && sec.getAttribute('aria-label'),
      notes: [...el.querySelectorAll('.mats-note')].map((n) => n.textContent.trim().slice(0, 70)),
      error: /Error loading block/i.test(el.innerText),
    };
  });
  console.log(label, JSON.stringify(s));
  return s;
};

await state('initial');
/* empty the title (same as clearing the side-panel field) */
await page.evaluate((cid) => wp.data.dispatch('core/block-editor').updateBlockAttributes(cid, { title: '' }), id);
await p.locator('.mats-note', { hasText: 'Falta el título' }).waitFor({ timeout: 30000 });
await page.waitForTimeout(600);
await state('emptied');
await p.scrollIntoViewIfNeeded();
await page.waitForTimeout(400);
await p.screenshot({ path: `${out}/notitle-preview-${postId}.png` });

/* type it back in the side panel field */
await page.evaluate((cid) => wp.data.dispatch('core/block-editor').selectBlock(cid), id);
await page.waitForTimeout(600);
const field = page.locator('.block-editor-block-inspector input[type="text"], .block-editor-block-inspector textarea').nth(1);
const fieldLabel = await field.evaluate((el) => {
  const l = el.id && document.querySelector(`label[for="${el.id}"]`);
  return l ? l.textContent.trim() : '';
});
await field.fill(original);
await p.locator('h1.show__title').waitFor({ timeout: 30000 });
await page.waitForTimeout(600);
const restored = await state('restored via "' + fieldLabel + '"');
const attr = await page.evaluate((cid) => wp.data.select('core/block-editor').getBlock(cid).attributes.title, id);
console.log('title attribute back to original:', attr === original);
console.log('errors:', errors.length ? errors.join('\n') : 'none');
await browser.close();
