/**
 * QA (fix "section-lock-client-side-only"): the sections of a structural
 * page are fixed on the server for non-administrators.
 *   As the Editor (REST, like DevTools wp.apiFetch):
 *     remove sections / add a core block / reorder / duplicate a section /
 *     unlock a section → refused (403 mats_sections_locked), page unchanged;
 *     change a text inside a section → saved;
 *     News page: excerpt with the (empty) content → saved;
 *     legal text page: add a paragraph → saved; add a section → refused.
 *   As the Editor (classic form post.php action=editpost): structure change
 *     → "No se ha guardado" page, page unchanged.
 *   As the administrator: reordering is allowed.
 * Every page is restored at the end (as admin).
 *
 *   WP_BASE=http://localhost:8981 node wordpress/tools/qa/fix-editorux-lock.mjs
 */
import { launch, login, BASE } from './wp-login.mjs';

const PRODUCT = Number(process.env.PRODUCT_ID || 10);
const NEWS = Number(process.env.NEWS_ID || 20);
const LEGAL = Number(process.env.LEGAL_ID || 22);

const browser = await launch();
let fails = 0;
const check = (label, ok, extra = '') => {
  if (!ok) fails++;
  console.log((ok ? 'ok   ' : 'FAIL ') + label + (extra ? ' :: ' + extra : ''));
};

async function session(user, pass) {
  const page = await login(browser, user, pass);
  await page.goto(`${BASE}/wp-admin/post.php?post=${PRODUCT}&action=edit`);
  await page.waitForFunction(() => window.wp && wp.apiFetch && wp.blocks && wp.blocks.getBlockTypes().length > 20, null, { timeout: 60000 });
  return page;
}

/* runs fn(raw blocks) in the page and POSTs the result; returns {status, code, message, names} */
const tryPost = (page, id, transform) =>
  page.evaluate(
    async ([postId, src]) => {
      const fn = new Function('blocks', 'wp', src);
      const cur = await wp.apiFetch({ path: `/wp/v2/pages/${postId}?context=edit` });
      const blocks = wp.blocks.parse(cur.content.raw).filter((b) => b.name);
      const data = fn(blocks, wp);
      try {
        const res = await wp.apiFetch({ path: `/wp/v2/pages/${postId}`, method: 'POST', data });
        return { status: 200, names: wp.blocks.parse(res.content.raw).filter((b) => b.name).map((b) => b.name) };
      } catch (e) {
        return { status: (e.data && e.data.status) || 0, code: e.code, message: e.message };
      }
    },
    [id, transform]
  );
const raw = (page, id) => page.evaluate(async (postId) => (await wp.apiFetch({ path: `/wp/v2/pages/${postId}?context=edit` })).content.raw, id);

const admin = await session('admin', 'admin-local-pass');
const originals = {};
for (const id of [PRODUCT, NEWS, LEGAL]) originals[id] = await raw(admin, id);
const newsExcerpt = await admin.evaluate(async (postId) => (await wp.apiFetch({ path: `/wp/v2/pages/${postId}?context=edit` })).excerpt.raw, NEWS);
const sameAsOriginal = async (id) => (await raw(admin, id)) === originals[id];

const editor = await session(process.env.WP_USER || 'marta', process.env.WP_PASS || 'Editor-pass-2026');
const refused = (r) => r.status === 403 && r.code === 'mats_sections_locked';

let r = await tryPost(editor, PRODUCT, `return { content: wp.blocks.serialize([blocks[0]]) + '\\n<!-- wp:paragraph --><p>Hola</p><!-- /wp:paragraph -->' };`);
check('editor: remove sections + add a paragraph → refused', refused(r), JSON.stringify(r));
check('  message in Spanish', /no se pueden añadir, quitar ni cambiar de orden las secciones/.test(r.message || ''), r.message);
check('  page unchanged', await sameAsOriginal(PRODUCT));

r = await tryPost(editor, PRODUCT, `const b = blocks.slice(); const t = b[1]; b[1] = b[2]; b[2] = t; return { content: wp.blocks.serialize(b) };`);
check('editor: reorder two sections → refused', refused(r), JSON.stringify(r));

r = await tryPost(editor, PRODUCT, `const b = blocks.slice(); b.splice(6, 0, wp.blocks.cloneBlock(b[6])); return { content: wp.blocks.serialize(b) };`);
check('editor: duplicate a section → refused', refused(r), JSON.stringify(r));

r = await tryPost(editor, PRODUCT, `const b = blocks.slice(); b.pop(); return { content: wp.blocks.serialize(b) };`);
check('editor: remove the last section → refused', refused(r), JSON.stringify(r));

r = await tryPost(editor, PRODUCT, `const b = blocks.map((x, i) => i === 3 ? wp.blocks.cloneBlock(x, { lock: { move: false, remove: false } }) : x); return { content: wp.blocks.serialize(b) };`);
check('editor: unlock a section → refused', refused(r), JSON.stringify(r));
check('  page unchanged after all refusals', await sameAsOriginal(PRODUCT));

r = await tryPost(editor, PRODUCT, `const b = blocks.map((x, i) => i === 0 ? wp.blocks.cloneBlock(x, { title: x.attributes.title + ' (QA)' }) : x); return { content: wp.blocks.serialize(b) };`);
const savedText = (await raw(admin, PRODUCT)).includes(' (QA)');
check('editor: change a text inside a section → saved', r.status === 200 && savedText, JSON.stringify(r).slice(0, 200));

r = await tryPost(editor, NEWS, `return { content: wp.blocks.serialize(blocks), excerpt: 'Extracto de prueba QA' };`);
check('editor: News page excerpt (empty content) → saved', r.status === 200, JSON.stringify(r));

r = await tryPost(editor, LEGAL, `return { content: wp.blocks.serialize(blocks) + '\\n<!-- wp:paragraph --><p>Párrafo legal QA</p><!-- /wp:paragraph -->' };`);
check('editor: legal page, add a paragraph → saved', r.status === 200 && r.names.length === 2, JSON.stringify(r));
r = await tryPost(editor, LEGAL, `return { content: wp.blocks.serialize(blocks) + '\\n' + wp.blocks.serialize([wp.blocks.createBlock('matsurfer/page-cta', { title: 'QA' })]) };`);
check('editor: legal page, add a section → refused', refused(r), JSON.stringify(r));

/* classic form (post.php) with the editor's own nonce */
const classic = await editor.evaluate(async ([postId, base]) => {
  const nonce = (document.querySelector('form.metabox-base-form input[name="_wpnonce"]') || {}).value;
  const cur = await wp.apiFetch({ path: `/wp/v2/pages/${postId}?context=edit` });
  const blocks = wp.blocks.parse(cur.content.raw).filter((b) => b.name);
  const body = new URLSearchParams({
    _wpnonce: nonce || '',
    action: 'editpost',
    post_ID: String(postId),
    post_type: 'page',
    originalaction: 'editpost',
    post_title: cur.title.raw,
    content: wp.blocks.serialize(blocks.slice(0, 2)),
  });
  const res = await fetch(base + '/wp-admin/post.php', { method: 'POST', body, credentials: 'same-origin' });
  const text = await res.text();
  return { nonce: !!nonce, status: res.status, die: /No se ha guardado/.test(text) };
}, [PRODUCT, BASE]);
check('editor: classic form structure change → "No se ha guardado" (403)', classic.nonce && classic.status === 403 && classic.die, JSON.stringify(classic));
check('  page keeps its sections', (await raw(admin, PRODUCT)).split('<!-- wp:matsurfer/').length === originals[PRODUCT].split('<!-- wp:matsurfer/').length);

/* administrators are not checked */
r = await tryPost(admin, PRODUCT, `const b = blocks.slice(); const t = b[1]; b[1] = b[2]; b[2] = t; return { content: wp.blocks.serialize(b) };`);
check('admin: reorder → saved', r.status === 200, JSON.stringify(r).slice(0, 200));

/* restore */
for (const id of [PRODUCT, NEWS, LEGAL]) {
  await admin.evaluate(async ([postId, content]) => wp.apiFetch({ path: `/wp/v2/pages/${postId}`, method: 'POST', data: { content } }), [id, originals[id]]);
}
await admin.evaluate(async ([postId, newsExcerpt]) => wp.apiFetch({ path: `/wp/v2/pages/${postId}`, method: 'POST', data: { excerpt: newsExcerpt } }), [NEWS, newsExcerpt]);
check('restored', (await sameAsOriginal(PRODUCT)) && (await sameAsOriginal(NEWS)) && (await sameAsOriginal(LEGAL)));

console.log(fails ? 'RESULT FAIL ' + fails : 'RESULT ok');
await browser.close();
