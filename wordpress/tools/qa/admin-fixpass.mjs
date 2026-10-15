/**
 * admin module QA (fix pass) — what an editor must NOT be able to do to a
 * fixed page through Polylang (bulk edit, quick edit, the editor's
 * «Idiomas» box and its AJAX switch), what still works for other pages,
 * and what administrators keep (unlocking sections, the Spanish-pack
 * notice). CHANGES THE DATABASE of the port it runs on (and restores it).
 *
 *   WP_BASE=http://localhost:8915 node wordpress/tools/qa/admin-fixpass.mjs [outDir]
 *
 * Needs wordpress/tools/qa/admin-setup.php first. Exits 1 on a failure.
 */
import { execSync } from 'node:child_process';
import { launch, login, closeWelcome, BASE } from './wp-login.mjs';

const OUT = process.argv[2] || '/tmp/claude-0/-home-user-ICMAB-WEB/ae5d27f5-1af0-537f-966f-1d2257e6cfbd/scratchpad/admin-fix/shots';
const PORT = new URL(BASE).port;
const cli = (script, args = '') => execSync(`MATS_WP_PORT=${PORT} php /home/user/wpsite-cli.php ${new URL(`./${script}`, import.meta.url).pathname} ${args}`).toString();
const IDS = JSON.parse(cli('admin-ids.php'));
const P = IDS.product;
const PES = IDS.product_es;
const R = IDS.reading;
const failures = [];
const report = { ids: IDS };
const check = (cond, label) => {
  if (!cond) failures.push(label);
  return !!cond;
};
const state = () => JSON.parse(cli('admin-langstate.php', `${P},${PES},${R}`));
const productIntact = (s, label) =>
  check(s[P].lang === 'en' && s[P].tr.en === P && s[P].tr.es === PES && s[PES].lang === 'es' && s[PES].tr.en === P && s[P].status === 'publish' && s[P].slug === 'product', `${label}: Product keeps EN, its link to ES, status and slug`);

execSync(`mkdir -p ${OUT}`);
const browser = await launch();
try {
  productIntact(state(), 'start');

  /* ── editor ──────────────────────────────────────────────────────── */
  const page = await login(browser, 'editora', 'editora-local-pass');

  await page.goto(`${BASE}/wp-admin/edit.php?post_type=page&lang=all`);
  await page.screenshot({ path: `${OUT}/editor-pages-list.png`, fullPage: true });
  report.checkboxProduct = await page.$(`#cb-select-${P}`);
  report.checkboxReading = await page.$(`#cb-select-${R}`);
  check(!report.checkboxProduct, 'editor: no checkbox on the fixed Product page');
  check(!!report.checkboxReading, 'editor: the QA reading page keeps its checkbox');
  /* "select all" only ticks the pages that have a checkbox */
  await page.check('#cb-select-all-1');
  report.ticked = await page.$$eval('#the-list input[name="post[]"]:checked', (els) => els.map((e) => +e.value));
  check(!report.ticked.includes(P), 'editor: "select all" does not tick the fixed pages');
  await page.uncheck('#cb-select-all-1');

  /* forged bulk edit: Product + the reading page → Spanish, draft */
  const nonce = await page.$eval('#posts-filter input[name="_wpnonce"]', (e) => e.value);
  const qs = new URLSearchParams({ post_type: 'page', _wpnonce: nonce, action: 'edit', action2: '-1', bulk_edit: 'Update', inline_lang_choice: 'es', _status: 'draft', post_parent: '-1', page_template: '-1', post_author: '-1', comment_status: '', ping_status: '' });
  qs.append('post[]', String(P));
  qs.append('post[]', String(R));
  await page.goto(`${BASE}/wp-admin/edit.php?${qs.toString()}`);
  await page.waitForTimeout(600);
  await page.screenshot({ path: `${OUT}/editor-bulk-forged.png` });
  report.bulkNotice = await page.$$eval('.notice-warning p', (els) => els.map((e) => e.textContent.trim()));
  check(report.bulkNotice.some((t) => /página principal se ha dejado como estaba/.test(t)), 'editor: bulk edit says the fixed page was left as it was');
  let s = state();
  report.afterBulk = s;
  productIntact(s, 'bulk edit');
  check(s[R].lang === 'es' && s[R].status === 'draft', 'bulk edit still works for a normal page (language + status changed)');
  cli('admin-langstate.php', `restore ${P},${PES},${R}`);

  /* forged quick edit of Product */
  await page.goto(`${BASE}/wp-admin/edit.php?post_type=page&lang=all`);
  const inlineNonce = await page.$eval('#_inline_edit', (e) => e.value);
  const qe = await page.request.post(`${BASE}/wp-admin/admin-ajax.php`, {
    form: { pll_ajax_backend: '1', action: 'inline-save', _inline_edit: inlineNonce, post_ID: String(P), post_type: 'page', screen: 'edit-page', post_view: 'list', edit_date: '', post_title: 'Product', post_name: 'product-moved', _status: 'draft', inline_lang_choice: 'es', page_template: 'default', menu_order: '0', post_parent: '0', post_author: '1' },
  });
  report.quickEditStatus = qe.status();
  s = state();
  report.afterQuickEdit = s;
  productIntact(s, 'quick edit');

  /* the block editor: AJAX language switch + «Idiomas» box saved with the page */
  await page.goto(`${BASE}/wp-admin/post.php?post=${P}&action=edit`);
  await page.waitForFunction(() => window.wp?.data?.select('core/editor')?.getCurrentPostId(), null, { timeout: 60000, polling: 500 });
  await page.waitForTimeout(2500);
  await closeWelcome(page);
  const pll = await page.evaluate(() => ({ nonce: document.querySelector('#_pll_nonce')?.value || '', select: !!document.querySelector('[name="post_lang_choice"]'), selectVisible: !!document.querySelector('[name="post_lang_choice"]')?.offsetParent }));
  report.pllBox = pll;
  check(pll.select && !pll.selectVisible, 'editor: language selector present but hidden on the fixed page');
  const ajax = await page.request.post(`${BASE}/wp-admin/admin-ajax.php`, { form: { pll_ajax_backend: '1', action: 'post_lang_choice', lang: 'es', post_type: 'page', post_id: String(P), _pll_nonce: pll.nonce } });
  report.ajaxStatus = ajax.status();
  s = state();
  productIntact(s, 'AJAX language switch');
  cli('admin-langstate.php', `restore ${P},${PES},${R}`);

  /* change the hidden select and unlink the ES translation, then save (meta boxes are posted to post.php) */
  report.metaboxSave = await page.evaluate(async () => {
    const sel = document.querySelector('[name="post_lang_choice"]');
    if (sel) sel.value = 'es';
    document.querySelectorAll('[name^="post_tr_lang"]').forEach((i) => (i.value = '0'));
    const ed = wp.data.dispatch('core/editor');
    ed.editPost({ excerpt: wp.data.select('core/editor').getEditedPostAttribute('excerpt') + '' });
    await ed.savePost();
    const until = Date.now() + 15000;
    while (Date.now() < until && (wp.data.select('core/edit-post').isSavingMetaBoxes() || wp.data.select('core/editor').isSavingPost())) {
      await new Promise((r) => setTimeout(r, 300));
    }
    return { trInputs: document.querySelectorAll('[name^="post_tr_lang"]').length, sel: sel ? sel.value : null };
  });
  await page.waitForTimeout(1200);
  s = state();
  report.afterMetabox = s;
  productIntact(s, 'editor «Idiomas» box save');
  cli('admin-langstate.php', `restore ${P},${PES},${R}`);

  /* news article: no Tags panel, no page-cta, brand palette */
  await page.goto(`${BASE}/wp-admin/post.php?post=${IDS.post}&action=edit`);
  await page.waitForFunction(() => window.wp?.data?.select('core/editor')?.getCurrentPostId(), null, { timeout: 60000, polling: 500 });
  await page.waitForTimeout(2500);
  await closeWelcome(page);
  report.post = await page.evaluate(() => {
    const be = wp.data.select('core/block-editor');
    const s = be.getSettings();
    wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/document');
    return new Promise((r) =>
      setTimeout(
        () =>
          r({
            allowed: s.allowedBlockTypes,
            palette: (s.__experimentalFeatures?.color?.palette?.theme || []).map((c) => c.slug),
            sizes: (s.__experimentalFeatures?.typography?.fontSizes?.theme || []).map((c) => c.slug),
            sidebar: document.querySelector('.editor-sidebar, .interface-complementary-area')?.innerText || '',
            tagsTax: !!wp.data.select('core').getTaxonomy?.('post_tag')?.types?.includes('post'),
          }),
        1500
      )
    );
  });
  await page.screenshot({ path: `${OUT}/editor-post-sidebar.png` });
  check(!report.post.allowed.includes('matsurfer/page-cta'), 'editor news: page-cta not allowed');
  check(report.post.palette.join() === 'ink,coral,violet,plum,cream', 'editor news: brand palette');
  check(report.post.sizes.join() === 'small,normal,large,x-large', 'editor news: S/M/L/XL sizes');
  check(!/\bTags\b|Etiquetas/.test(report.post.sidebar), 'editor news: no Tags panel');
  report.post.sidebar = report.post.sidebar.slice(0, 400);

  await page.goto(`${BASE}/wp-admin/edit.php`);
  report.tagsMenu = await page.$$eval('#adminmenu a[href*="taxonomy=post_tag"]', (els) => els.length);
  check(report.tagsMenu === 0, 'editor: no Noticias → Tags submenu');

  await page.goto(`${BASE}/wp-admin/media-new.php`);
  report.uploadTip = await page.$$eval('.mats-upload-tip', (e) => e.map((x) => x.innerText).join(' '));
  check(/Recorte sin fondo/.test(report.uploadTip) && /WebP o PNG/.test(report.uploadTip), 'upload tip: team cut-out exception');
  await page.goto(`${BASE}/wp-admin/upload.php?mode=list`);
  report.mediaNotice = await page.$$eval('.mats-media-notice', (e) => e.map((x) => x.innerText).join(' '));
  check(/Recorte sin fondo/.test(report.mediaNotice), 'media library notice: team cut-out exception');

  await page.goto(`${BASE}/wp-admin/admin.php?page=mats-guide`);
  await page.screenshot({ path: `${OUT}/editor-guide.png`, fullPage: true });
  report.editorGuide = await page.$$eval('.mats-guide__section h2', (els) => els.map((e) => e.textContent.trim()));
  check(report.editorGuide.length === 14 && report.editorGuide.includes('Google y redes sociales'), 'editor guide: 14 sections incl. Google y redes sociales');
  report.editorGuideBroken = await page.$$eval('.mats-guide a[href^="#"]', (as) => as.map((a) => a.getAttribute('href')).filter((h) => h.length > 1 && !document.getElementById(h.slice(1))));
  check(report.editorGuideBroken.length === 0, 'editor guide: every #anchor link has its section');
  await page.context().close();

  /* ── administrator ───────────────────────────────────────────────── */
  cli('admin-unlock.php');
  const esInstalled = JSON.parse(cli('admin-fixpass-reset.php')).es;
  const admin = await login(browser);
  await admin.goto(`${BASE}/wp-admin/`);
  await admin.waitForTimeout(600);
  report.esNotice = await admin.$$eval('.mats-es-notice', (e) => e.map((x) => x.innerText.trim().slice(0, 120)));
  check(esInstalled ? report.esNotice.length === 0 : report.esNotice.length === 1, 'admin: Spanish-pack notice only when the pack is missing');
  await admin.screenshot({ path: `${OUT}/admin-dashboard.png`, fullPage: true });
  if (report.esNotice.length) {
    await Promise.all([admin.waitForNavigation(), admin.click('.mats-es-notice a:has-text("No volver a mostrar")')]);
    report.esNoticeAfterHide = await admin.$$eval('.mats-es-notice', (e) => e.length);
    check(report.esNoticeAfterHide === 0, 'admin: "No volver a mostrar" hides the notice');
    cli('admin-fixpass-reset.php');
  }

  await admin.goto(`${BASE}/wp-admin/edit.php?post_type=page&lang=all`);
  check(!!(await admin.$(`#cb-select-${P}`)), 'admin: fixed pages keep their checkbox');

  await admin.goto(`${BASE}/wp-admin/post.php?post=${P}&action=edit`);
  await admin.waitForFunction(() => window.wp?.data?.select('core/editor')?.getCurrentPostId(), null, { timeout: 60000, polling: 500 });
  await admin.waitForTimeout(2500);
  await closeWelcome(admin);
  report.adminLock = await admin.evaluate(() => {
    const be = wp.data.select('core/block-editor');
    const first = be.getBlocks()[0];
    wp.data.dispatch('core/block-editor').selectBlock(first.clientId);
    return { name: first.name, lock: first.attributes.lock, canLockType: be.canLockBlockType(first.name), canMove: be.canMoveBlock(first.clientId), canLockBlocks: be.getSettings().canLockBlocks, notices: wp.data.select('core/notices').getNotices().map((n) => n.content) };
  });
  check(report.adminLock.canLockType === true, 'admin: sections can be unlocked (canLockBlockType)');
  check(report.adminLock.notices.some((n) => /Desbloquear/.test(n)), 'admin: notice explains ⋮ → Desbloquear');
  await admin.waitForTimeout(600);
  const options = admin.locator('.block-editor-block-toolbar button[aria-label="Options"], .block-editor-block-toolbar button[aria-label="Opciones"]').first();
  if (await options.count()) {
    await options.click();
    await admin.waitForTimeout(600);
    report.adminMenuItems = await admin.$$eval('.components-dropdown-menu__menu [role="menuitem"]', (els) => els.map((e) => e.textContent.trim()));
    await admin.screenshot({ path: `${OUT}/admin-section-menu.png` });
    const unlock = admin.locator('.components-dropdown-menu__menu [role="menuitem"]:has-text("Unlock"), .components-dropdown-menu__menu [role="menuitem"]:has-text("Desbloquear")').first();
    check((await unlock.count()) > 0, 'admin: ⋮ menu offers Unlock');
    if (await unlock.count()) {
      await unlock.click();
      await admin.waitForTimeout(600);
      await admin.screenshot({ path: `${OUT}/admin-unlock-modal.png` });
      const allBox = admin.locator('.block-editor-block-lock-modal input[type="checkbox"]').first();
      if (await allBox.count()) {
        if (await allBox.isChecked()) await allBox.uncheck();
        await admin.locator('.block-editor-block-lock-modal button[type="submit"]').click();
        await admin.waitForTimeout(500);
        report.adminAfterUnlock = await admin.evaluate(() => {
          const be = wp.data.select('core/block-editor');
          const first = be.getBlocks()[0];
          return { canMove: be.canMoveBlock(first.clientId), canRemove: be.canRemoveBlock(first.clientId), lock: first.attributes.lock };
        });
        check(report.adminAfterUnlock.canMove && report.adminAfterUnlock.canRemove, 'admin: after Unlock the section can be moved and removed');
      }
    }
  } else {
    check(false, 'admin: block toolbar ⋮ button found');
  }
  await admin.context().close();
} finally {
  cli('admin-langstate.php', `restore ${P},${PES},${R}`);
  cli('admin-unlock.php');
  await browser.close();
}
productIntact(state(), 'end');
console.log(JSON.stringify(report, null, 2));
if (failures.length) {
  console.log('\nFAILED:\n - ' + failures.join('\n - '));
  process.exit(1);
}
console.log('\nALL CHECKS PASSED');
