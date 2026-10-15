/**
 * QA (secseo): the block editor keeps working with the security + SEO
 * modules, and the "Google y redes sociales" panel saves the SEO texts.
 *   WP_BASE=http://localhost:8916 node wordpress/tools/qa/secseo-editor.mjs <pageId> <postId> <outDir>
 * - admin: page editor (iframe canvas, REST block renderer answers 200,
 *   no JS errors), panel → type a title → counter → save → meta saved →
 *   public <title> changed → field emptied again
 * - editor (editora): article editor, panel present, /wp/v2/users works
 *   while logged in; visitors get 401
 */
import { launch, login, closeWelcome, BASE } from './wp-login.mjs';

const pageId = process.argv[2] || '37';
const postId = process.argv[3] || '54';
const OUT = process.argv[4] || '/tmp';
const failures = [];
const check = (cond, label) => {
  console.log(`${cond ? '  ok  ' : '  FAIL'} ${label}`);
  if (!cond) failures.push(label);
};

const browser = await launch();

async function openEditor(page, id) {
  const errors = [];
  const renderer = { ok: 0, bad: [] };
  page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));
  page.on('console', (m) => {
    if (m.type() === 'error') errors.push('console: ' + m.text());
  });
  page.on('response', (r) => {
    if (r.url().includes('block-renderer')) {
      if (r.status() === 200) renderer.ok++;
      else renderer.bad.push(r.status() + ' ' + r.url());
    }
  });
  await page.goto(`${BASE}/wp-admin/post.php?post=${id}&action=edit`);
  await page.waitForSelector('iframe[name="editor-canvas"], .editor-styles-wrapper', { timeout: 60000 });
  await page.waitForFunction(() => window.wp?.data?.select('core/editor')?.getCurrentPostId(), null, { timeout: 60000 });
  await closeWelcome(page);
  return { errors, renderer };
}

async function openPanel(page) {
  await page.evaluate(() => {
    const d = wp.data.dispatch('core/edit-post');
    if (d?.openGeneralSidebar) d.openGeneralSidebar('edit-post/document');
    const ed = wp.data.select('core/editor');
    if (ed.isEditorPanelOpened && !ed.isEditorPanelOpened('matsurfer-seo/matsurfer-seo')) {
      wp.data.dispatch('core/editor').toggleEditorPanelOpened('matsurfer-seo/matsurfer-seo');
    }
  });
  const panel = page.locator('.mats-seo');
  await panel.waitFor({ timeout: 15000 });
  await panel.locator('.mats-seo__preview').waitFor({ timeout: 10000 });
  await panel.scrollIntoViewIfNeeded();
  return panel;
}

async function save(page) {
  await page.evaluate(() => wp.data.dispatch('core/editor').savePost());
  await page.waitForFunction(() => {
    const ed = wp.data.select('core/editor');
    return !ed.isSavingPost() && !ed.isEditedPostDirty();
  }, null, { timeout: 30000 });
  await page.waitForTimeout(500);
}

/* ── administrator: a sections page ─────────────────────────────────── */
{
  const page = await login(browser, 'admin', 'admin-local-pass', { width: 1440, height: 1000 });
  const { errors, renderer } = await openEditor(page, pageId);
  const iframe = !!(await page.$('iframe[name="editor-canvas"]'));
  check(iframe, 'admin: editor canvas is still an iframe (no classic meta boxes added)');
  const frame = iframe ? page.frameLocator('iframe[name="editor-canvas"]') : page;
  await frame.locator('.mats-preview, [data-mats-edit]').first().waitFor({ timeout: 45000 }).catch(() => {});
  await page.waitForTimeout(2500);
  check(renderer.ok > 0 && renderer.bad.length === 0, `admin: REST block renderer answers 200 (${renderer.ok} ok, ${renderer.bad.length} failed ${renderer.bad.slice(0, 2).join(' | ')})`);
  const meta = await page.evaluate(() => wp.data.select('core/editor').getEditedPostAttribute('meta'));
  check(meta && '_mats_seo_title' in meta && '_mats_seo_description' in meta, 'admin: SEO meta exposed to the editor');
  const original = { title: meta?._mats_seo_title ?? '', description: meta?._mats_seo_description ?? '' };

  const panel = await openPanel(page);
  await page.screenshot({ path: `${OUT}/secseo-panel-page.png` });
  const box = await panel.boundingBox();
  if (box) await page.screenshot({ path: `${OUT}/secseo-panel-page-crop.png`, clip: { x: box.x - 8, y: Math.max(0, box.y - 8), width: box.width + 16, height: Math.min(900, box.height + 16) } });
  const previewTitle = await panel.locator('.mats-seo__ptitle').innerText();
  check(previewTitle.length > 10, `admin: preview shows the title ("${previewTitle}")`);

  const input = panel.locator('input[type="text"]').first();
  const newTitle = 'MatSurfer device — QA title ' + Date.now().toString().slice(-5);
  await input.fill(newTitle);
  await page.waitForTimeout(300);
  const counter = await panel.locator('.mats-seo__count').first().innerText();
  check(/de 60 caracteres/.test(counter), `admin: title counter ("${counter}")`);
  check((await panel.locator('.mats-seo__ptitle').innerText()) === newTitle, 'admin: preview follows the typed title');
  const longDesc = 'x'.repeat(170);
  const textarea = panel.locator('textarea').first();
  await textarea.fill(longDesc);
  await page.waitForTimeout(300);
  const dcount = await panel.locator('.mats-seo__count').nth(1).innerText();
  check(/170 de 155/.test(dcount) && (await panel.locator('.mats-seo__count.is-warn').count()) === 1, `admin: description counter warns over 155 ("${dcount}")`);
  await textarea.fill(original.description);
  await save(page);
  const saved = await page.evaluate((id) => wp.apiFetch({ path: `/wp/v2/pages/${id}?context=edit&_fields=meta` }), pageId);
  check(saved?.meta?._mats_seo_title === newTitle, `admin: title saved in _mats_seo_title (${saved?.meta?._mats_seo_title})`);
  check(saved?.meta?._mats_seo_description === original.description, 'admin: description kept');

  const link = await page.evaluate(() => wp.data.select('core/editor').getPermalink());
  const html = await (await fetch(link)).text();
  const shownTitle = (html.match(/<title>([\s\S]*?)<\/title>/) || [])[1];
  check(shownTitle === newTitle.replace('—', '—'), `public page shows the new <title> ("${shownTitle}")`);

  /* back to the original value */
  await input.fill(original.title);
  await save(page);
  const restored = await page.evaluate((id) => wp.apiFetch({ path: `/wp/v2/pages/${id}?context=edit&_fields=meta` }), pageId);
  check(restored?.meta?._mats_seo_title === original.title, 'admin: original SEO title restored');

  const users = await page.evaluate(() => wp.apiFetch({ path: '/wp/v2/users?context=edit&per_page=5' }).then((u) => u.length).catch((e) => 'error ' + e.code));
  check(typeof users === 'number' && users > 0, `admin: /wp/v2/users works while logged in (${users})`);
  const real = errors.filter((e) => !/favicon|Failed to load resource: the server responded with a status of 404|ERR_TUNNEL_CONNECTION_FAILED|ERR_NAME_NOT_RESOLVED|ERR_PROXY/.test(e));
  check(real.length === 0, `admin: no JS errors in the editor${real.length ? ': ' + real.slice(0, 3).join(' | ') : ''}`);
  await page.context().close();
}

/* ── editor (role Editor): a news article ───────────────────────────── */
{
  const page = await login(browser, 'editora', 'editora-local-pass', { width: 1440, height: 1000 });
  check(page.url().includes('/wp-admin'), `editora: logged in (${page.url()})`);
  const { errors, renderer } = await openEditor(page, postId);
  const panel = await openPanel(page);
  await page.waitForTimeout(800);
  await page.screenshot({ path: `${OUT}/secseo-panel-post.png` });
  const box = await panel.boundingBox();
  if (box) await page.screenshot({ path: `${OUT}/secseo-panel-post-crop.png`, clip: { x: box.x - 8, y: Math.max(0, box.y - 8), width: box.width + 16, height: Math.min(900, box.height + 16) } });
  const note = await panel.locator('.mats-seo__note').innerText();
  check(/Imagen destacada/.test(note), `editora: image note ("${note}")`);
  const placeholder = await panel.locator('input[type="text"]').first().getAttribute('placeholder');
  check(/— MatSurfer$/.test(placeholder || ''), `editora: automatic title as placeholder ("${placeholder}")`);
  const users = await page.evaluate(() => wp.apiFetch({ path: '/wp/v2/users?context=view&who=authors&per_page=5' }).then((u) => u.length).catch((e) => 'error ' + e.code));
  check(typeof users === 'number' && users > 0, `editora: /wp/v2/users works while logged in (${users})`);
  const me = await page.evaluate(() => wp.apiFetch({ path: '/wp/v2/users/me' }).then((u) => u.id).catch((e) => 'error ' + e.code));
  check(typeof me === 'number', `editora: /wp/v2/users/me works (${me})`);
  const real = errors.filter((e) => !/favicon|Failed to load resource: the server responded with a status of 404|ERR_TUNNEL_CONNECTION_FAILED|ERR_NAME_NOT_RESOLVED|ERR_PROXY/.test(e));
  check(real.length === 0, `editora: no JS errors in the editor${real.length ? ': ' + real.slice(0, 3).join(' | ') : ''}`);
  check(renderer.bad.length === 0, `editora: block renderer without errors (${renderer.ok} ok)`);

  /* file editors are gone for everybody */
  const te = await page.goto(`${BASE}/wp-admin/theme-editor.php`);
  check((await page.content()).includes('not allowed') || te.status() >= 400, 'editora: theme file editor refused');
  await page.context().close();
}

/* ── admin: no file editors either ──────────────────────────────────── */
{
  const page = await login(browser);
  for (const screen of ['theme-editor.php', 'plugin-editor.php']) {
    const r = await page.goto(`${BASE}/wp-admin/${screen}`);
    const txt = await page.content();
    check(r.status() >= 400 || /not allowed|no tienes permiso|Lo siento/i.test(txt), `admin: ${screen} refused (${r.status()})`);
  }
  const r = await page.goto(`${BASE}/wp-admin/plugin-install.php`);
  check(r.status() === 200 && !/not allowed/.test(await page.content()), 'admin: can still open "Añadir plugins" (installer needs it)');
  const anon = await fetch(`${BASE}/wp-json/wp/v2/users`);
  check(anon.status === 401, `visitor: /wp/v2/users → ${anon.status}`);
  await page.context().close();
}

await browser.close();
console.log(failures.length ? `\nFAILED (${failures.length}):\n - ${failures.join('\n - ')}` : '\nALL EDITOR CHECKS PASSED');
process.exit(failures.length ? 1 : 0);
