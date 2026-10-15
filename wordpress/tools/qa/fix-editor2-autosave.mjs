/**
 * QA (fix "autosave-empty-on-structure-change"): an Editor's autosave of a
 * structural page whose sections differ from the stored ones is kept with
 * its title and content (it used to be stored EMPTY), while the real save
 * is still refused.
 *   A. As the Editor (REST, like the editor's own autosave): POST
 *      /wp/v2/pages/<id>/autosaves with a section removed and the title
 *      changed → stored with that title and those sections; the page itself
 *      is unchanged; the same content as a real save → 403
 *      mats_sections_locked.
 *   B. Real scenario: the Editor has the page open, an administrator
 *      reorders its sections, the Editor changes a text and the editor
 *      autosaves → the autosave has every section and the new text;
 *      reopening the page offers «View the autosave», which compares the
 *      Editor's sections and text (not an empty page); «Restore This
 *      Autosave» is still refused with the Spanish message («No se ha
 *      guardado») because it would change the sections.
 *   SHOTS=/dir saves a screenshot of the comparison.
 *   C. A plain text autosave (same sections) still works.
 * The page and its autosaves are restored at the end (as admin).
 *
 *   WP_BASE=http://localhost:8985 node wordpress/tools/qa/fix-editor2-autosave.mjs
 */
import { execSync } from 'node:child_process';
import { launch, login, closeWelcome, BASE } from './wp-login.mjs';

const PRODUCT = Number(process.env.PRODUCT_ID || 10);
const OTHER = Number(process.env.OTHER_ID || 12); // a page the admin session opens (keeps PRODUCT unlocked)
const PORT = new URL(BASE).port;
const HELPER = new URL('./fix-editor2-autosave.php', import.meta.url).pathname;
const autosaves = (del = false) =>
  JSON.parse(execSync(`MATS_WP_PORT=${PORT} php /home/user/wpsite-cli.php ${HELPER} ${PRODUCT}${del ? ' delete' : ''}`).toString() || '[]');

const browser = await launch();
let fails = 0;
const check = (label, ok, extra = '') => {
  if (!ok) fails++;
  console.log((ok ? 'ok   ' : 'FAIL ') + label + (extra ? ' :: ' + extra : ''));
};

async function session(user, pass, id) {
  const page = await login(browser, user, pass);
  page.on('dialog', (d) => d.accept());
  await page.goto(`${BASE}/wp-admin/post.php?post=${id}&action=edit`);
  await page.waitForFunction(() => window.wp && wp.apiFetch && wp.blocks && wp.blocks.getBlockTypes().length > 20, null, { timeout: 60000 });
  return page;
}
/* another session (e.g. an earlier QA run) may still hold the page's edit lock */
async function takeOver(page) {
  const btn = page.locator('.components-modal__frame button', { hasText: /Take over|Tomar el control/ });
  await btn.first().waitFor({ timeout: 3000 }).catch(() => {});
  if (await btn.count()) {
    await btn.first().click();
    await page.waitForTimeout(1000);
  }
}
const raw = (page, id) => page.evaluate(async (postId) => (await wp.apiFetch({ path: `/wp/v2/pages/${postId}?context=edit` })).content.raw, id);
const names = (content) => (content.match(/<!-- wp:([a-z0-9/-]+)/g) || []).map((s) => s.slice(8));

autosaves(true);
const admin = await session('admin', 'admin-local-pass', OTHER);
const original = await raw(admin, PRODUCT);
const originalNames = names(original);
const editorUser = process.env.WP_USER || 'marta';
const editor = await session(editorUser, process.env.WP_PASS || 'Editor-pass-2026', PRODUCT);
await takeOver(editor);
await closeWelcome(editor);

/* A ─ REST autosave with a changed structure */
const a = await editor.evaluate(async (postId) => {
  const cur = await wp.apiFetch({ path: `/wp/v2/pages/${postId}?context=edit` });
  const blocks = wp.blocks.parse(cur.content.raw).filter((b) => b.name);
  const content = wp.blocks.serialize(blocks.slice(0, -1));
  const title = cur.title.raw + ' (autoguardado QA)';
  const out = { title, count: blocks.length - 1 };
  try {
    const res = await wp.apiFetch({ path: `/wp/v2/pages/${postId}/autosaves`, method: 'POST', data: { id: postId, title, content } });
    out.autosave = { status: 200, title: res.title.raw, blocks: wp.blocks.parse(res.content.raw).filter((b) => b.name).length };
  } catch (e) {
    out.autosave = { status: (e.data && e.data.status) || 0, code: e.code };
  }
  try {
    await wp.apiFetch({ path: `/wp/v2/pages/${postId}`, method: 'POST', data: { title, content } });
    out.save = { status: 200 };
  } catch (e) {
    out.save = { status: (e.data && e.data.status) || 0, code: e.code };
  }
  return out;
}, PRODUCT);
let stored = autosaves().find((x) => x.author === editorUser);
check('A. editor autosave with a section removed → answered 200', a.autosave.status === 200, JSON.stringify(a.autosave));
check('   stored autosave keeps the title', stored && stored.title === a.title, JSON.stringify(stored && stored.title));
check('   stored autosave keeps the sections', stored && stored.blocks.length === a.count && stored.length > 1000, stored ? stored.blocks.length + ' blocks, ' + stored.length + ' chars' : 'none');
check('   the page itself is unchanged', (await raw(admin, PRODUCT)) === original);
check('   the real save is still refused (403 mats_sections_locked)', a.save.status === 403 && a.save.code === 'mats_sections_locked', JSON.stringify(a.save));
autosaves(true);

/* B ─ the admin reorders the sections while the Editor has the page open */
await editor.reload();
await editor.waitForFunction(() => window.wp && wp.data && wp.data.select('core/block-editor').getBlocks().length > 3, null, { timeout: 60000 });
await takeOver(editor);
await closeWelcome(editor);
await editor.waitForTimeout(1500);
const reordered = await admin.evaluate(async (postId) => {
  const cur = await wp.apiFetch({ path: `/wp/v2/pages/${postId}?context=edit` });
  const b = wp.blocks.parse(cur.content.raw).filter((x) => x.name);
  const t = b[1];
  b[1] = b[2];
  b[2] = t;
  const res = await wp.apiFetch({ path: `/wp/v2/pages/${postId}`, method: 'POST', data: { content: wp.blocks.serialize(b) } });
  return res.content.raw;
}, PRODUCT);
check('B. admin reorders two sections', names(reordered).join() !== originalNames.join());
await editor.waitForTimeout(1500); // WordPress offers the autosave only when it is newer (by the second) than the page
const marker = 'Texto del autoguardado QA';
await editor.evaluate((text) => {
  const be = wp.data.select('core/block-editor');
  const hero = be.getBlocks()[0];
  wp.data.dispatch('core/block-editor').updateBlockAttributes(hero.clientId, { lead: text });
}, marker);
await editor.waitForTimeout(500);
await editor.evaluate(() => wp.data.dispatch('core/editor').autosave());
await editor.waitForFunction(() => !wp.data.select('core/editor').isAutosavingPost(), null, { timeout: 30000 });
await editor.waitForTimeout(800);
stored = autosaves().find((x) => x.author === editorUser);
check('   editor autosave stored (not empty)', stored && stored.length > 1000 && stored.title !== '', stored ? stored.length + ' chars, title ' + JSON.stringify(stored.title) : 'none');
check('   it has the Editor’s sections (old order) and new text', stored && stored.blocks.join() === originalNames.join() && stored.content.includes(marker), stored ? stored.blocks.join() : '');

/* reopen: «View the autosave» brings it back */
await editor.goto(`${BASE}/wp-admin/post.php?post=${PRODUCT}&action=edit`);
await editor.waitForFunction(() => window.wp && wp.data && wp.data.select('core/block-editor').getBlocks().length > 3, null, { timeout: 60000 });
await takeOver(editor);
await closeWelcome(editor);
const view = editor.locator('.components-notice a, .components-notice button', { hasText: /View the autosave|Ver el autoguardado/ });
await view.first().waitFor({ timeout: 20000 }).catch(() => {});
check('   reopening offers «View the autosave»', (await view.count()) > 0);
if (await view.count()) {
  /* WordPress opens «Compare Revisions» for the autosave (revision.php) */
  await Promise.all([editor.waitForURL(/revision\.php/, { timeout: 30000 }), view.first().click()]);
  await editor.waitForSelector('.revisions-diff .diff', { timeout: 30000 });
  await editor.waitForTimeout(800);
  if (process.env.SHOTS) await editor.screenshot({ path: process.env.SHOTS + '/autosave-compare.png', fullPage: false });
  const cmp = await editor.evaluate(() => {
    const diff = document.querySelector('.revisions-diff');
    const heads = [...diff.querySelectorAll('h2, h3')].map((h) => h.textContent.trim());
    return { heads, noTitle: /\(no title\)|\(sin título\)/.test(diff.textContent), text: diff.textContent };
  });
  check('   «View the autosave» compares real content (not an empty page)', cmp.heads.some((h) => /Content|Contenido/.test(h)) && !cmp.noTitle, JSON.stringify(cmp.heads) + (cmp.noTitle ? ' (no title)' : ''));
  check('   … including the Editor’s new text', cmp.text.includes(marker));
  /* restoring it would change the sections: still refused */
  const restore = editor.locator('.restore-revision');
  await Promise.all([editor.waitForURL(/action=restore/, { timeout: 30000 }), restore.first().click()]);
  await editor.waitForTimeout(800);
  const body = await editor.textContent('body');
  check('   «Restore This Autosave» with other sections → «No se ha guardado»', /No se ha guardado/.test(body) && /no se pueden añadir, quitar ni cambiar de orden las secciones/.test(body), body.slice(0, 120).replace(/\s+/g, ' '));
  check('   page keeps the admin’s order', (await raw(admin, PRODUCT)) === reordered);
}

/* C ─ text-only autosave */
await editor.goto(`${BASE}/wp-admin/post.php?post=${PRODUCT}&action=edit`);
await editor.waitForFunction(() => window.wp && wp.apiFetch && wp.blocks && wp.blocks.getBlockTypes().length > 20, null, { timeout: 60000 });
await admin.evaluate(async ([postId, content]) => wp.apiFetch({ path: `/wp/v2/pages/${postId}`, method: 'POST', data: { content } }), [PRODUCT, original]);
autosaves(true);
const c = await editor.evaluate(async (postId) => {
  const cur = await wp.apiFetch({ path: `/wp/v2/pages/${postId}?context=edit` });
  const blocks = wp.blocks.parse(cur.content.raw).filter((b) => b.name);
  const edited = blocks.map((b, i) => (i === 0 ? wp.blocks.cloneBlock(b, { lead: 'Solo texto QA' }) : b));
  try {
    const res = await wp.apiFetch({ path: `/wp/v2/pages/${postId}/autosaves`, method: 'POST', data: { id: postId, title: cur.title.raw, content: wp.blocks.serialize(edited) } });
    return { status: 200, has: res.content.raw.includes('Solo texto QA') };
  } catch (e) {
    return { status: (e.data && e.data.status) || 0, code: e.code };
  }
}, PRODUCT);
check('C. text-only autosave → stored with the text', c.status === 200 && c.has, JSON.stringify(c));

/* restore */
await admin.evaluate(async ([postId, content]) => wp.apiFetch({ path: `/wp/v2/pages/${postId}`, method: 'POST', data: { content } }), [PRODUCT, original]);
autosaves(true);
check('restored', (await raw(admin, PRODUCT)) === original && autosaves().length === 0);

console.log(fails ? 'RESULT FAIL ' + fails : 'RESULT ok');
await browser.close();
process.exit(fails ? 1 : 0);
