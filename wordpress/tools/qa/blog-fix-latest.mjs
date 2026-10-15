/**
 * QA (module "blog", fix pass): in the editor, the standard words of the
 * «Últimas noticias» section (kicker, title, button) are editable in place,
 * and typing a title keeps the kicker and the button.
 *   WP_BASE=http://localhost:8914 node wordpress/tools/qa/blog-fix-latest.mjs <outDir> <pageId>
 * (pageId: a page with a latest-posts section, e.g. from blog-latest.php).
 * Nothing is saved: the editor is left without saving.
 */
import fs from 'node:fs';
import path from 'node:path';
import { launch, login, closeWelcome, BASE } from './wp-login.mjs';

const [out = '.', pageId] = process.argv.slice(2);
fs.mkdirSync(out, { recursive: true });
const browser = await launch();
const ed = await login(browser);
await ed.goto(`${BASE}/wp-admin/post.php?post=${pageId}&action=edit`);
await ed.waitForSelector('iframe[name="editor-canvas"]', { timeout: 60000 });
await closeWelcome(ed);
const frame = ed.frame({ name: 'editor-canvas' });
await frame.waitForSelector('.lposts .lposts__head', { timeout: 60000 });
await ed.waitForTimeout(1500);
const heads = async () => frame.evaluate(() => [...document.querySelectorAll('.lposts .lposts__head')].map((h) => [...h.querySelectorAll('[data-mats-edit]')].map((n) => `${n.getAttribute('data-mats-edit')}=${n.textContent.trim()}${n.isContentEditable ? ' (editable)' : ''}`).join(' | ')));
console.log('before:', await heads());

/* type a new title in the first section whose attributes are all empty */
const target = await ed.evaluate(() => {
  const b = wp.data.select('core/block-editor').getBlocks().find((x) => x.name === 'matsurfer/latest-posts' && !x.attributes.title && !x.attributes.kicker && !(x.attributes.cta && x.attributes.cta.label));
  return b ? b.clientId : null;
});
console.log('fresh section:', target);
if (target) {
  const title = frame.locator(`[data-block="${target}"] .lposts__head h2[data-mats-edit="title"]`);
  await title.click();
  await ed.keyboard.press('Control+A');
  await ed.keyboard.type('Our latest stories');
  await ed.keyboard.press('Enter');
  await ed.waitForTimeout(2500);
  const attrs = await ed.evaluate((id) => wp.data.select('core/block-editor').getBlockAttributes(id), target);
  console.log('attributes after typing:', JSON.stringify({ kicker: attrs.kicker, title: attrs.title, cta: attrs.cta }));
  const head = await frame.evaluate((id) => document.querySelector(`[data-block="${id}"] .lposts__head`).textContent.replace(/\s+/g, ' ').trim(), target);
  console.log('preview after typing:', head);
  await frame.locator(`[data-block="${target}"]`).screenshot({ path: path.join(out, 'latest-after-title.png') });
}
await browser.close();
