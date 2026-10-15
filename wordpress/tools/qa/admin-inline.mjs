/**
 * admin module QA — an editor can still edit texts in place on a locked
 * structural page (templateLock 'all') and save; and a new post opened from
 * the dashboard tile gets the editor's language.
 *   WP_BASE=http://localhost:8915 node wordpress/tools/qa/admin-inline.mjs
 */
import { execSync } from 'node:child_process';
import { launch, login, closeWelcome, BASE } from './wp-login.mjs';
const OUT = '/tmp/claude-0/-home-user-ICMAB-WEB/ae5d27f5-1af0-537f-966f-1d2257e6cfbd/scratchpad/admin/shots';
const port = new URL(BASE).port;
execSync(`MATS_WP_PORT=${port} php /home/user/wpsite-cli.php ${new URL('./admin-unlock.php', import.meta.url).pathname}`, { stdio: 'ignore' });
const browser = await launch();
const page = await login(browser, 'editora', 'editora-local-pass');
await page.goto(`${BASE}/wp-admin/post.php?post=38&action=edit`);
await page.waitForFunction(() => window.wp && wp.data.select('core/editor').getCurrentPostId(), null, { timeout: 60000, polling: 500 });
await page.waitForTimeout(4000);
await closeWelcome(page);
const frame = page.frameLocator('iframe[name="editor-canvas"]');
const target = frame.locator('[data-mats-edit="title"]').first();
await target.waitFor({ timeout: 30000 });
const before = await target.innerText();
await target.click();
await page.keyboard.press('Control+End');
await page.keyboard.type(' (QA)');
await page.keyboard.press('Enter');
await page.waitForTimeout(800);
const dirty = await page.evaluate(() => wp.data.select('core/editor').isEditedPostDirty());
await page.click('.editor-post-publish-button, .editor-post-publish-button__button');
await page.waitForFunction(() => !wp.data.select('core/editor').isSavingPost() && !wp.data.select('core/editor').isEditedPostDirty(), null, { timeout: 30000, polling: 300 });
await page.screenshot({ path: `${OUT}/editor-inline-saved.png` });
const saved = await page.evaluate(async () => {
  const p = await wp.apiFetch({ path: '/wp/v2/pages/38?context=edit' });
  const hero = wp.blocks.parse(p.content.raw).find((b) => b.name === 'matsurfer/page-hero');
  return hero ? hero.attributes.title : null;
});
/* restore */
await page.evaluate(async (orig) => {
  const p = await wp.apiFetch({ path: '/wp/v2/pages/38?context=edit' });
  const blocks = wp.blocks.parse(p.content.raw);
  blocks.find((b) => b.name === 'matsurfer/page-hero').attributes.title = orig;
  await wp.apiFetch({ path: '/wp/v2/pages/38', method: 'POST', data: { content: wp.blocks.serialize(blocks) } });
}, before);

/* new post from the tile, in Spanish */
await page.goto(`${BASE}/wp-admin/post-new.php?new_lang=es`);
await page.waitForFunction(() => window.wp && wp.data.select('core/editor').getCurrentPostId(), null, { timeout: 60000, polling: 500 });
await page.waitForTimeout(2500);
const lang = await page.evaluate(() => document.querySelector('#post_lang_choice')?.value || null);
console.log(JSON.stringify({ before, dirty, saved, newPostLang: lang }, null, 2));
const ok = dirty && saved === before + ' (QA)' && lang === 'es';
console.log(ok ? 'INLINE + LANGUAGE CHECKS PASSED' : 'FAILED');
await browser.close();
process.exit(ok ? 0 : 1);
