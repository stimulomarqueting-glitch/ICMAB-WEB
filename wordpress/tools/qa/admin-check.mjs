/**
 * admin module QA — checks the admin lockdown as an administrator and as
 * an editor, and takes screenshots.
 *
 *   WP_BASE=http://localhost:8915 node wordpress/tools/qa/admin-check.mjs [outDir]
 *
 * Needs wordpress/tools/qa/admin-setup.php first (editor user + content).
 * Prints a JSON report; exits 1 when an assertion fails.
 */
import { execSync } from 'node:child_process';
import { launch, login, closeWelcome, BASE } from './wp-login.mjs';

const OUT = process.argv[2] || '/tmp/claude-0/-home-user-ICMAB-WEB/ae5d27f5-1af0-537f-966f-1d2257e6cfbd/scratchpad/admin/shots';
const EDITOR = ['editora', 'editora-local-pass'];
const failures = [];
const report = {};

function check(cond, label) {
  if (!cond) failures.push(label);
  return !!cond;
}

async function menu(page) {
  return page.$$eval('#adminmenu > li.menu-top', (items) =>
    items
      .filter((li) => getComputedStyle(li).display !== 'none')
      .map((li) => (li.querySelector('.wp-menu-name')?.childNodes[0]?.textContent || '').trim())
  );
}

async function pageIds(page) {
  return page.evaluate(async () => {
    const r = await fetch('/wp-json/wp/v2/pages?per_page=50&status=publish,draft&context=edit&lang=', { headers: { 'X-WP-Nonce': window.wpApiSettings?.nonce || '' } });
    return r.ok ? (await r.json()).map((p) => ({ id: p.id, title: p.title.raw, slug: p.slug })) : [];
  });
}

async function editorInfo(page) {
  return page.evaluate(() => {
    const be = wp.data.select('core/block-editor');
    const s = be.getSettings();
    const types = wp.blocks.getBlockTypes().map((b) => b.name);
    const insertable = types.filter((n) => be.canInsertBlockType(n, null));
    const feats = s.__experimentalFeatures || {};
    return {
      allowed: s.allowedBlockTypes,
      insertable,
      codeEditingEnabled: s.codeEditingEnabled,
      canLockBlocks: s.canLockBlocks,
      templateLock: s.templateLock,
      openverse: s.enableOpenverseMediaCategory,
      fontLibrary: s.fontLibraryEnabled,
      disableCustomColors: s.disableCustomColors,
      disableCustomFontSizes: s.disableCustomFontSizes,
      palette: feats.color && feats.color.palette,
      fontSizes: feats.typography && feats.typography.fontSizes,
      dropCap: feats.typography && feats.typography.dropCap,
      notices: wp.data.select('core/notices').getNotices().map((n) => n.content),
      imageStyles: (wp.data.select('core/blocks').getBlockStyles('core/image') || []).map((x) => x.name),
      buttonStyles: (wp.data.select('core/blocks').getBlockStyles('core/button') || []).map((x) => x.name),
      removedPanels: ['page-attributes', 'template'].filter((p) => wp.data.select('core/editor').isEditorPanelRemoved(p)),
      sidebar: (document.querySelector('.editor-sidebar, .interface-complementary-area')?.innerText || '').slice(0, 1500),
      bodyClass: document.body.className,
      inserterButton: !!document.querySelector('.editor-document-tools__inserter-toggle:not([disabled])'),
    };
  });
}

async function openEditor(page, url, shot) {
  await page.goto(BASE + url, { waitUntil: 'domcontentloaded' });
  try {
    await page.waitForFunction(() => window.wp && wp.data && wp.data.select('core/editor') && wp.data.select('core/editor').getCurrentPostId(), null, { timeout: 60000, polling: 500 });
  } catch (e) {
    await page.screenshot({ path: `${OUT}/fail-${shot}.png` });
    throw new Error(`editor did not load at ${page.url()}: ${e.message}`);
  }
  await page.waitForTimeout(2500);
  await closeWelcome(page);
  await page.waitForTimeout(800);
  if (shot) await page.screenshot({ path: `${OUT}/${shot}.png` });
  return editorInfo(page);
}

const browser = await launch();

/* ── login screen ─────────────────────────────────────────────────── */
{
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 860 } });
  const p = await ctx.newPage();
  await p.goto(`${BASE}/wp-login.php`);
  await p.screenshot({ path: `${OUT}/login.png` });
  report.loginLogoHref = await p.$eval('.login h1 a', (a) => a.href);
  report.loginLogoBg = await p.$eval('.login h1 a', (a) => getComputedStyle(a).backgroundImage);
  check(/logotipo\.svg/.test(report.loginLogoBg), 'login logo is the MatSurfer logotype');
  check(report.loginLogoHref.replace(/\/$/, '') === BASE, 'login logo links to the home page');
  await p.fill('#user_login', 'editora');
  await p.fill('#user_pass', 'wrong-pass');
  await Promise.all([p.waitForNavigation(), p.click('#wp-submit')]);
  report.loginError = (await p.textContent('#login_error')).trim();
  check(/usuario o la contraseña no son correctos/.test(report.loginError), 'generic login error');
  await p.screenshot({ path: `${OUT}/login-error.png` });
  /* comments endpoint */
  const res = await p.request.post(`${BASE}/wp-comments-post.php`, { form: { comment_post_ID: '53', comment: 'spam', author: 'x', email: 'x@example.org' } });
  report.commentsPostStatus = res.status();
  check(res.status() === 403, 'wp-comments-post.php answers 403');
  const xmlrpc = await p.request.post(`${BASE}/xmlrpc.php`, { data: '<?xml version="1.0"?><methodCall><methodName>pingback.ping</methodName></methodCall>', headers: { 'Content-Type': 'text/xml' } });
  report.xmlrpcPingback = (await xmlrpc.text()).includes('faultCode');
  const users = await p.request.get(`${BASE}/wp-json/wp/v2/users`);
  report.restUsersAnon = users.status();
  check([401, 403, 404].includes(users.status()), 'REST users hidden for visitors (inc/modules/security.php)');
  await ctx.close();
}

/* ── administrator ────────────────────────────────────────────────── */
{
  const page = await login(browser);
  await page.goto(`${BASE}/wp-admin/`);
  await page.waitForTimeout(800);
  await page.screenshot({ path: `${OUT}/admin-dashboard.png`, fullPage: true });
  report.adminMenu = await menu(page);
  report.adminTiles = await page.$$eval('.mats-tile__title', (els) => els.map((e) => e.childNodes[0].textContent.trim()));
  report.adminWidgets = await page.$$eval('#dashboard-widgets .postbox', (els) => els.filter((e) => !e.classList.contains('hidden') && e.offsetParent).map((e) => e.id));
  report.adminBar = await page.$$eval('#wp-admin-bar-root-default > li', (els) => els.map((e) => e.id));
  check(report.adminMenu[0] === 'Dashboard' || report.adminMenu[0] === 'Escritorio', 'admin menu starts with the dashboard');
  check(report.adminMenu[report.adminMenu.length - 1] === 'Guía de uso', 'guide is the last menu item (admin)');
  check(report.adminMenu.includes('Noticias'), 'Posts renamed to Noticias');
  check(!report.adminMenu.some((m) => /Comments|Comentarios/.test(m)), 'no comments menu (admin)');
  check(!report.adminBar.includes('wp-admin-bar-wp-logo') && !report.adminBar.includes('wp-admin-bar-comments'), 'admin bar without logo and comments');

  await page.goto(`${BASE}/wp-admin/admin.php?page=mats-guide`);
  await page.screenshot({ path: `${OUT}/admin-guide.png`, fullPage: true });
  report.adminGuideSections = await page.$$eval('.mats-guide__section h2', (els) => els.map((e) => e.textContent.trim()));

  await page.goto(`${BASE}/wp-admin/edit.php?post_type=page`);
  await page.screenshot({ path: `${OUT}/admin-pages.png`, fullPage: true });
  report.adminTrashLinksOnStructural = await page.$$eval('#the-list tr', (rows) => rows.filter((r) => r.querySelector('.mats-row-locked') && r.querySelector('.row-actions .trash')).length);
  check(report.adminTrashLinksOnStructural === 0, 'no trash link on structural pages (admin)');

  report.adminProduct = await openEditor(page, '/wp-admin/post.php?post=37&action=edit', 'admin-editor-product');
  check(report.adminProduct.templateLock !== 'all', 'admins can change the structure');
  check(report.adminProduct.insertable.includes('core/html'), 'admins get Custom HTML on pages');
  report.adminPost = await openEditor(page, '/wp-admin/post.php?post=53&action=edit', 'admin-editor-post');
  await page.close();
}

/* ── editor ───────────────────────────────────────────────────────── */
/* release the edit locks the administrator just took (no "already being edited" dialog) */
try {
  const port = new URL(BASE).port;
  execSync(`MATS_WP_PORT=${port} php /home/user/wpsite-cli.php ${new URL('./admin-unlock.php', import.meta.url).pathname}`, { stdio: 'ignore' });
} catch (e) {
  /* not critical */
}
{
  const page = await login(browser, ...EDITOR);
  await page.goto(`${BASE}/wp-admin/`);
  await page.waitForTimeout(800);
  await page.screenshot({ path: `${OUT}/editor-dashboard.png`, fullPage: true });
  report.editorMenu = await menu(page);
  report.editorTiles = await page.$$eval('.mats-tile', (els) => els.map((e) => ({ title: e.querySelector('.mats-tile__title').childNodes[0].textContent.trim(), href: e.querySelector('.mats-tile__link').getAttribute('href'), alt: e.querySelector('.mats-tile__alt')?.getAttribute('href') || '' })));
  report.editorWidgets = await page.$$eval('#dashboard-widgets .postbox', (els) => els.filter((e) => e.offsetParent).map((e) => e.id));
  report.editorBar = await page.$$eval('#wp-admin-bar-root-default > li', (els) => els.map((e) => e.id));
  report.editorNewItems = await page.$$eval('#wp-admin-bar-new-content-default > li', (els) => els.map((e) => e.id));
  const forbidden = ['Tools', 'Herramientas', 'Appearance', 'Apariencia', 'Plugins', 'Settings', 'Ajustes', 'Users', 'Usuarios', 'Languages', 'Idiomas', 'Comments', 'Comentarios', 'WPForms (stub)'];
  check(!report.editorMenu.some((m) => forbidden.includes(m)), 'editor menu without system screens');
  check(report.editorMenu[report.editorMenu.length - 1] === 'Guía de uso', 'guide is the last menu item (editor)');
  check(report.editorWidgets.every((id) => id.startsWith('mats_')), 'editor dashboard only has MatSurfer widgets');

  /* blocked screens */
  for (const path of ['/wp-admin/tools.php', '/wp-admin/edit-comments.php', '/wp-admin/edit.php?post_type=wp_block']) {
    await page.goto(BASE + path);
    report[`editorVisit ${path}`] = page.url().replace(BASE, '');
    check(/\/wp-admin\/(index\.php)?$/.test(page.url().replace(BASE, '')), `editor redirected away from ${path}`);
  }

  await page.goto(`${BASE}/wp-admin/admin.php?page=mats-guide`);
  await page.screenshot({ path: `${OUT}/editor-guide.png`, fullPage: true });
  report.editorGuideSections = await page.$$eval('.mats-guide__section h2', (els) => els.map((e) => e.textContent.trim()));
  check(!report.editorGuideSections.includes('Para administradores'), 'admin-only guide section hidden from editors');

  await page.goto(`${BASE}/wp-admin/edit.php?post_type=page`);
  await page.screenshot({ path: `${OUT}/editor-pages.png`, fullPage: true });
  report.editorQuickEditOnStructural = await page.$$eval('#the-list tr', (rows) => rows.filter((r) => r.querySelector('.mats-row-locked') && r.querySelector('.row-actions .inline')).length);
  check(report.editorQuickEditOnStructural === 0, 'no quick edit on structural pages (editor)');

  report.editorProduct = await openEditor(page, '/wp-admin/post.php?post=37&action=edit', 'editor-editor-product');
  const ep = report.editorProduct;
  check(ep.templateLock === 'all', 'structure locked for editors on structural pages');
  check(ep.codeEditingEnabled === false && ep.canLockBlocks === false, 'no code editor / lock for editors');
  check(ep.notices.some((n) => /secciones bloqueadas/.test(n)), 'structural notice shown');
  check(!/\bSlug\b|\bParent\b/.test(ep.sidebar), 'slug and parent rows hidden');
  check(ep.allowed.every((n) => n.startsWith('matsurfer/')), 'sections pages: only MatSurfer sections');

  report.editorLegal = await openEditor(page, '/wp-admin/post.php?post=49&action=edit', 'editor-editor-legal');
  check(report.editorLegal.allowed.every((n) => n.startsWith('core/')) && report.editorLegal.allowed.includes('core/table'), 'legal page: core text blocks');
  check(report.editorLegal.templateLock !== 'all', 'legal page is editable text');

  report.editorPost = await openEditor(page, '/wp-admin/post.php?post=53&action=edit', 'editor-editor-post');
  const po = report.editorPost;
  check(po.allowed.includes('core/embed') && po.allowed.includes('matsurfer/page-cta') && !po.allowed.includes('core/html') && !po.allowed.includes('core/shortcode') && !po.allowed.includes('matsurfer/faq'), 'news: writing blocks + page-cta only');
  check(po.disableCustomColors === true && po.disableCustomFontSizes === true && po.dropCap === false, 'no free colours or font sizes, no drop cap');
  /* the paragraph inspector has no colour / typography / dimension panels for editors */
  report.editorParagraphInspector = await page.evaluate(() => {
    const be = wp.data.select('core/block-editor');
    const first = be.getBlocks().find((b) => b.name === 'core/paragraph');
    if (first) wp.data.dispatch('core/block-editor').selectBlock(first.clientId);
    wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/block');
    return new Promise((r) => setTimeout(() => r(document.querySelector('.block-editor-block-inspector')?.innerText || ''), 1200));
  });
  check(!/Typography|Tipografía|Color|Dimensions|Dimensiones|Advanced|Avanzado/.test(report.editorParagraphInspector), 'paragraph inspector without design controls');
  check(po.imageStyles.length === 0 && po.buttonStyles.length === 0, 'no block style variations for editors');

  /* inserter of a news article */
  await page.click('.editor-document-tools__inserter-toggle');
  await page.waitForTimeout(1200);
  await page.screenshot({ path: `${OUT}/editor-post-inserter.png` });
  report.editorPostInserter = await page.$$eval('.block-editor-block-types-list__item-title', (els) => els.map((e) => e.textContent.trim()));
  await page.keyboard.press('Escape');

  report.editorNewPage = await openEditor(page, '/wp-admin/post-new.php?post_type=page', 'editor-editor-newpage');
  check(report.editorNewPage.notices.some((n) => /Página nueva/.test(n)), 'new page notice');
  await page.close();
}

await browser.close();
console.log(JSON.stringify(report, null, 2));
if (failures.length) {
  console.log('\nFAILED:\n - ' + failures.join('\n - '));
  process.exit(1);
}
console.log('\nALL CHECKS PASSED');
