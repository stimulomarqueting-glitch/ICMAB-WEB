/**
 * admin module QA (fix pass) — negative control for admin-fixpass.mjs:
 * sends the same quick-edit, AJAX and «Idiomas»-box requests as an
 * ADMINISTRATOR (the language guard does not apply to them), to prove the
 * requests really change a language when nothing stops them. Uses the QA
 * reading page (never a fixed page) and restores it afterwards.
 *
 *   WP_BASE=http://localhost:8915 node wordpress/tools/qa/admin-fixpass-control.mjs
 */
import { execSync } from 'node:child_process';
import { launch, login, closeWelcome, BASE } from './wp-login.mjs';

const PORT = new URL(BASE).port;
const cli = (script, args = '') => execSync(`MATS_WP_PORT=${PORT} php /home/user/wpsite-cli.php ${new URL(`./${script}`, import.meta.url).pathname} ${args}`).toString();
const IDS = JSON.parse(cli('admin-ids.php'));
const R = IDS.reading;
const lang = () => JSON.parse(cli('admin-langstate.php', `${R}`))[R].lang;
const restore = () => cli('admin-langstate.php', `restore ${IDS.product},${IDS.product_es},${R}`);
const out = {};
const browser = await launch();
try {
  const page = await login(browser);
  await page.goto(`${BASE}/wp-admin/edit.php?post_type=page&lang=all`);
  const inlineNonce = await page.$eval('#_inline_edit', (e) => e.value);
  await page.request.post(`${BASE}/wp-admin/admin-ajax.php`, {
    form: { pll_ajax_backend: '1', action: 'inline-save', _inline_edit: inlineNonce, post_ID: String(R), post_type: 'page', screen: 'edit-page', post_view: 'list', post_title: 'QA reading page', post_name: 'qa-reading-page', _status: 'publish', inline_lang_choice: 'es', page_template: 'default', menu_order: '0', post_parent: '0', post_author: '1' },
  });
  out.quickEdit = lang();
  restore();

  await page.goto(`${BASE}/wp-admin/post.php?post=${R}&action=edit`);
  await page.waitForFunction(() => window.wp?.data?.select('core/editor')?.getCurrentPostId(), null, { timeout: 60000, polling: 500 });
  await page.waitForTimeout(2000);
  await closeWelcome(page);
  const nonce = await page.evaluate(() => document.querySelector('#_pll_nonce')?.value || '');
  await page.request.post(`${BASE}/wp-admin/admin-ajax.php`, { form: { pll_ajax_backend: '1', action: 'post_lang_choice', lang: 'es', post_type: 'page', post_id: String(R), _pll_nonce: nonce } });
  out.ajax = lang();
  restore();

  await page.reload();
  await page.waitForFunction(() => window.wp?.data?.select('core/editor')?.getCurrentPostId(), null, { timeout: 60000, polling: 500 });
  await page.waitForTimeout(2000);
  await page.evaluate(async () => {
    const sel = document.querySelector('[name="post_lang_choice"]');
    if (sel) sel.value = 'es';
    await wp.data.dispatch('core/editor').savePost();
    const until = Date.now() + 15000;
    while (Date.now() < until && (wp.data.select('core/edit-post').isSavingMetaBoxes() || wp.data.select('core/editor').isSavingPost())) {
      await new Promise((r) => setTimeout(r, 300));
    }
  });
  await page.waitForTimeout(1000);
  out.metabox = lang();
} finally {
  restore();
  cli('admin-unlock.php');
  await browser.close();
}
out.restored = lang();
console.log(JSON.stringify(out));
console.log(out.quickEdit === 'es' && out.ajax === 'es' && out.metabox === 'es' && out.restored === 'en' ? 'CONTROL OK: the unguarded requests do change the language' : 'CONTROL INCONCLUSIVE');
