/**
 * QA (installer fix pass) — browser checks on a cloned, set-up instance.
 *
 *   WP_BASE=http://localhost:8984 SHOTS=/path/to/dir node wordpress/tools/qa/fix-installer-check.mjs <check>…
 *
 * Prepare with fix-installer-setup.php (--editor --done, then --pll-off).
 *
 *   pll-off      Polylang deactivated: administrators see the warning on every
 *                screen (dashboard, pages, plugins, settings, wizard), the
 *                wizard's step 5 is no longer «✓ Terminada»
 *   pll-editor   same situation as the Editor qa-editor: short notice on the
 *                dashboard and Pages, and inside the block editor
 *   pll-fix      clicks «Activar Polylang» in the warning: Polylang is back,
 *                the warning is gone and /es/ answers 200 again
 *   no-pll-notice  Polylang active: the warning is nowhere (dashboard, pages)
 *   wizard-copy  step 3: label + help of «Sobrescribir…» name texts AND photos
 *                and say Google texts are kept; the JS dialog says the same
 *                (dismissed: nothing runs)
 *   revisions    as the Editor: open Producto, change the hero title, save →
 *                the sidebar shows «Revisiones» and the oldest revision is
 *                the original text
 *   themes       Appearance → Themes: MatSurfer card has its screenshot and
 *                Theme Details names «menú MatSurfer → Puesta en marcha»
 *   notices      dashboard right after activation (state «show»): the setup
 *                reminder is the first notice and says what it also solves
 */
import { launch, login, closeWelcome, BASE } from './wp-login.mjs';

const SHOTS = process.env.SHOTS || '/tmp';
const checks = process.argv.slice(2);
let failures = 0;
const ok = (cond, text) => {
  console.log(`  ${cond ? '✓' : '✗ FAIL'} ${text}`);
  if (!cond) failures++;
};

const browser = await launch();

async function noticeText(page, sel = '.mats-pll-off-notice') {
  const el = await page.$(sel);
  return el ? (await el.textContent()).replace(/\s+/g, ' ').trim() : '';
}

for (const check of checks) {
  console.log(`\n== ${check}`);
  if (check === 'pll-off') {
    const page = await login(browser);
    for (const path of ['/wp-admin/', '/wp-admin/edit.php?post_type=page', '/wp-admin/plugins.php', '/wp-admin/options-general.php', '/wp-admin/admin.php?page=mats-setup']) {
      await page.goto(BASE + path);
      const text = await noticeText(page);
      ok(text.includes('Polylang está desactivado') && text.includes('No se ha borrado nada'), `${path}: warning shown — ${text.slice(0, 90)}…`);
      const first = await page.$eval('#wpbody-content .notice', (n) => n.className).catch(() => '');
      ok(first.includes('mats-pll-off-notice'), `${path}: it is the first notice`);
    }
    await page.goto(BASE + '/wp-admin/');
    const btn = await page.$('.mats-pll-off-notice a.button-primary');
    const href = btn ? await btn.getAttribute('href') : '';
    ok(btn && (await btn.textContent()).trim() === 'Activar Polylang' && href.includes('action=activate') && href.includes('_wpnonce='), `button «Activar Polylang» → ${href.replace(BASE, '')}`);
    await page.screenshot({ path: `${SHOTS}/pll-off-dashboard.png` });
    await page.goto(BASE + '/wp-admin/admin.php?page=mats-setup');
    const pill = await page.$eval('#mats-step-review .mats-step__pill', (n) => n.textContent.trim());
    const summary = await page.$eval('.mats-setup__summary', (n) => n.textContent.replace(/\s+/g, ' ').trim());
    ok(!pill.includes('Terminada'), `wizard step 5 pill: «${pill}» (summary: ${summary.slice(0, 40)}…)`);
    await page.screenshot({ path: `${SHOTS}/pll-off-wizard.png`, fullPage: true });
    await page.context().close();
  } else if (check === 'pll-editor') {
    const page = await login(browser, 'qa-editor', 'qa-editor-pass');
    for (const path of ['/wp-admin/', '/wp-admin/edit.php?post_type=page', '/wp-admin/edit.php']) {
      await page.goto(BASE + path);
      const text = await noticeText(page);
      ok(text.includes('La web en español no se ve ahora mismo') && !text.includes('Activar'), `${path}: editor notice — ${text}`);
    }
    await page.goto(BASE + '/wp-admin/');
    await page.screenshot({ path: `${SHOTS}/pll-off-editor-dashboard.png` });
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));
    await page.goto(`${BASE}/wp-admin/post.php?post=10&action=edit`);
    await page.waitForSelector('iframe[name="editor-canvas"], .editor-styles-wrapper', { timeout: 60000 });
    await closeWelcome(page);
    await page.waitForTimeout(1500);
    const notices = await page.evaluate(() => wp.data.select('core/notices').getNotices().map((n) => n.id + ': ' + n.content));
    ok(notices.some((n) => n.startsWith('mats-pll-off: La web en español no se ve')), `block editor notice: ${JSON.stringify(notices)}`);
    ok(!errors.length, `no JS errors in the editor ${errors.join(' | ')}`);
    await page.screenshot({ path: `${SHOTS}/pll-off-editor-block-editor.png` });
    await page.context().close();
  } else if (check === 'pll-fix') {
    const page = await login(browser);
    await page.goto(BASE + '/wp-admin/');
    await Promise.all([page.waitForNavigation(), page.click('.mats-pll-off-notice a.button-primary')]);
    console.log('  after the click:', page.url().replace(BASE, ''));
    ok(!(await page.$('.mats-pll-off-notice')), 'warning gone after activating');
    await page.goto(BASE + '/wp-admin/');
    ok(!(await page.$('.mats-pll-off-notice')), 'dashboard without warning');
    const res = await page.request.get(BASE + '/es/', { maxRedirects: 0 });
    ok(res.status() === 200, `/es/ → ${res.status()}`);
    await page.goto(BASE + '/wp-admin/admin.php?page=mats-setup');
    const pill = await page.$eval('#mats-step-review .mats-step__pill', (n) => n.textContent.trim());
    ok(pill.includes('Terminada'), `wizard step 5 pill back to «${pill}»`);
    await page.context().close();
  } else if (check === 'no-pll-notice') {
    for (const [user, pass] of [['admin', 'admin-local-pass'], ['qa-editor', 'qa-editor-pass']]) {
      const page = await login(browser, user, pass);
      for (const path of ['/wp-admin/', '/wp-admin/edit.php?post_type=page']) {
        await page.goto(BASE + path);
        ok(!(await page.$('.mats-pll-off-notice')), `${user} ${path}: no Polylang warning`);
      }
      await page.context().close();
    }
  } else if (check === 'wizard-copy') {
    const page = await login(browser);
    await page.goto(BASE + '/wp-admin/admin.php?page=mats-setup');
    const label = await page.$eval('#mats-step-content .mats-step__option label', (n) => n.textContent.replace(/\s+/g, ' ').trim());
    const help = await page.$eval('#mats-step-content .mats-step__help', (n) => n.textContent.replace(/\s+/g, ' ').trim());
    console.log('  label:', label);
    console.log('  help :', help);
    ok(label === 'Sobrescribir los textos y las fotos con los originales', 'label names texts and photos');
    ok(help.includes('Los textos y las fotos de las secciones') && help.includes('título y la descripción para Google') && help.includes('Revisiones'), 'help says what is replaced, what is kept and how to get it back');
    const before = await page.$eval('#mats-step-content .mats-step__when', (n) => n.textContent).catch(() => '');
    let dialog = '';
    page.once('dialog', async (d) => {
      dialog = d.message();
      await d.dismiss();
    });
    await page.check('.mats-setup__overwrite');
    await page.click('button[name="step"][value="content"]');
    await page.waitForTimeout(1200);
    console.log('  dialog:', dialog.replace(/\n+/g, ' / '));
    ok(dialog.startsWith('¿Seguro que quieres sobrescribir los textos y las fotos?') && dialog.includes('título y la descripción para Google'), 'dialog names texts, photos and the Google texts kept');
    const after = await page.$eval('#mats-step-content .mats-step__when', (n) => n.textContent).catch(() => '');
    ok(before === after, 'dismissing the dialog runs nothing');
    await page.screenshot({ path: `${SHOTS}/wizard-step3.png`, clip: await page.$eval('#mats-step-content', (n) => { const r = n.getBoundingClientRect(); return { x: r.x, y: r.y + window.scrollY, width: r.width, height: r.height }; }), fullPage: true });
    await page.context().close();
  } else if (check === 'revisions') {
    const page = await login(browser, 'qa-editor', 'qa-editor-pass');
    await page.goto(`${BASE}/wp-admin/post.php?post=10&action=edit`);
    await page.waitForSelector('iframe[name="editor-canvas"], .editor-styles-wrapper', { timeout: 60000 });
    await closeWelcome(page);
    const frame = (await page.$('iframe[name="editor-canvas"]')) ? page.frameLocator('iframe[name="editor-canvas"]') : page;
    const title = frame.locator('.mats-preview [data-mats-edit="title"]').first();
    await title.waitFor({ timeout: 30000 });
    const original = (await title.innerText()).trim();
    const countBefore = await page.evaluate(() => wp.data.select('core/editor').getCurrentPostRevisionsCount());
    await title.click();
    await page.keyboard.press('End');
    await page.keyboard.type(' QA-REV');
    await page.keyboard.press('Enter');
    await page.waitForTimeout(1500);
    ok(await page.evaluate(() => wp.data.select('core/editor').isEditedPostDirty()), 'the change is in the editor (unsaved)');
    await page.evaluate(() => wp.data.dispatch('core/editor').savePost());
    await page.waitForFunction(() => !wp.data.select('core/editor').isSavingPost() && !wp.data.select('core/editor').isEditedPostDirty(), null, { timeout: 60000 });
    await page.reload();
    await page.waitForSelector('iframe[name="editor-canvas"], .editor-styles-wrapper', { timeout: 60000 });
    await closeWelcome(page);
    await page.waitForTimeout(1500);
    const countAfter = await page.evaluate(() => wp.data.select('core/editor').getCurrentPostRevisionsCount());
    console.log(`  original title «${original}» | revisions before: ${countBefore} → after the first save: ${countAfter}`);
    ok(countAfter >= 2, 'at least 2 revisions after the first save (the original + the change)');
    await page.evaluate(() => wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/document'));
    await page.waitForTimeout(800);
    const panel = await page.$eval('.interface-complementary-area', (n) => n.textContent.replace(/\s+/g, ' ')).catch(() => '');
    ok(/Revisiones|Revisions/.test(panel), `sidebar shows the revisions row (${(panel.match(/(Revisiones|Revisions)\s*\d*/) || [''])[0]})`);
    await page.screenshot({ path: `${SHOTS}/revisions-sidebar.png` });
    const revId = await page.evaluate(() => wp.data.select('core/editor').getCurrentPostLastRevisionId());
    await page.goto(`${BASE}/wp-admin/revision.php?revision=${revId}`);
    await page.waitForSelector('.revisions-controls, .revisions', { timeout: 30000 });
    await page.waitForTimeout(1500);
    const diff = await page.$eval('.revisions-diff-frame, .revisions', (n) => n.textContent.replace(/\s+/g, ' ')).catch(() => '');
    ok(diff.includes('QA-REV'), 'revisions screen compares against the earlier version (the change is highlighted)');
    await page.screenshot({ path: `${SHOTS}/revisions-screen.png` });
    await page.context().close();
  } else if (check === 'themes') {
    const page = await login(browser);
    await page.goto(BASE + '/wp-admin/themes.php');
    const shot = await page.$eval('.theme[data-slug="matsurfer"] .theme-screenshot', (n) => ({ cls: n.className, img: n.querySelector('img')?.getAttribute('src') || '' })).catch(() => ({ cls: 'missing', img: '' }));
    ok(!shot.cls.includes('blank') && shot.img.includes('screenshot.png'), `MatSurfer card screenshot: ${shot.img.replace(BASE, '') || shot.cls}`);
    await page.screenshot({ path: `${SHOTS}/themes.png` });
    await page.click('#matsurfer-action, .theme[data-slug="matsurfer"] .more-details');
    await page.waitForSelector('.theme-overlay .theme-description', { timeout: 15000 });
    const desc = await page.$eval('.theme-overlay .theme-description', (n) => n.textContent.trim());
    ok(desc.includes('menú MatSurfer → Puesta en marcha') && !desc.includes('Apariencia → MatSurfer'), `Theme Details: …${desc.slice(-70)}`);
    await page.screenshot({ path: `${SHOTS}/theme-details.png` });
    await page.context().close();
  } else if (check === 'notices') {
    const page = await login(browser);
    await page.goto(BASE + '/wp-admin/');
    const all = await page.$$eval('#wpbody-content .notice', (ns) => ns.map((n) => n.className.split(' ').filter((c) => c.startsWith('mats') || c.startsWith('notice-')).join('.') + ' — ' + n.textContent.replace(/\s+/g, ' ').trim().slice(0, 140)));
    all.forEach((n) => console.log('   ', n));
    ok(all[0] && all[0].includes('mats-setup-notice'), 'the setup reminder is the first notice');
    const reminder = await noticeText(page, '.mats-setup-notice');
    console.log('  reminder:', reminder);
    ok(/también/.test(reminder), 'the reminder says what else it does');
    await page.screenshot({ path: `${SHOTS}/notices-dashboard.png`, fullPage: true });
    await page.context().close();
  } else {
    console.log('  unknown check');
    failures++;
  }
}

await browser.close();
console.log(failures ? `\n${failures} FAILURE(S)` : '\nall checks passed');
process.exit(failures ? 1 : 0);
