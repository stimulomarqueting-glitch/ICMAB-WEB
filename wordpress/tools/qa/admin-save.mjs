/**
 * admin module QA — what an editor's save does to a structural page and to
 * section texts:
 *   - slug / status / parent changes are ignored on fixed pages
 *   - "R&D", "a < b" and quotes in a section attribute survive WordPress'
 *     HTML filter unchanged (no &amp; / &lt; entities)
 *   - a <script> typed in a news article is removed (no unfiltered_html)
 *   - deleting a fixed page is refused (REST 403)
 *
 *   WP_BASE=http://localhost:8915 node wordpress/tools/qa/admin-save.mjs
 */
import { execSync } from 'node:child_process';
import { launch, login, BASE } from './wp-login.mjs';

const port = new URL(BASE).port;
execSync(`MATS_WP_PORT=${port} php /home/user/wpsite-cli.php ${new URL('./admin-unlock.php', import.meta.url).pathname}`, { stdio: 'ignore' });

const browser = await launch();
const page = await login(browser, 'editora', 'editora-local-pass');
await page.goto(`${BASE}/wp-admin/post.php?post=37&action=edit`);
await page.waitForFunction(() => window.wp && wp.apiFetch && wp.data.select('core/editor').getCurrentPostId(), null, { timeout: 60000, polling: 500 });

const result = await page.evaluate(async () => {
  const out = {};
  const before = await wp.apiFetch({ path: '/wp/v2/pages/37?context=edit' });
  const blocks = wp.blocks.parse(before.content.raw);
  const hero = blocks.find((b) => b.name === 'matsurfer/page-hero');
  const originalTitle = hero.attributes.title;
  hero.attributes.title = 'QA R&D <b>bold</b> a < b "quoted"';
  const content = wp.blocks.serialize(blocks);
  try {
    const saved = await wp.apiFetch({ path: '/wp/v2/pages/37', method: 'POST', data: { content, slug: 'hacked-slug', status: 'draft', parent: 39, password: 'x' } });
    out.slug = saved.slug;
    out.status = saved.status;
    out.parent = saved.parent;
    out.password = saved.password;
    const parsed = wp.blocks.parse(saved.content.raw);
    out.savedTitle = parsed.find((b) => b.name === 'matsurfer/page-hero').attributes.title;
  } catch (e) {
    out.saveError = e.message;
  }
  /* restore */
  hero.attributes.title = originalTitle;
  await wp.apiFetch({ path: '/wp/v2/pages/37', method: 'POST', data: { content: wp.blocks.serialize(blocks) } });

  try {
    await wp.apiFetch({ path: '/wp/v2/pages/37', method: 'DELETE' });
    out.delete = 'deleted!';
  } catch (e) {
    out.delete = e.code || e.message;
  }

  const post = await wp.apiFetch({ path: '/wp/v2/posts/53', method: 'POST', data: { content: '<!-- wp:paragraph --><p>Hola <script>alert(1)</script><strong>R&amp;D</strong></p><!-- /wp:paragraph -->' } });
  out.postContent = post.content.raw;
  return out;
});
console.log(JSON.stringify(result, null, 2));

const fails = [];
if (result.slug !== 'product') fails.push('slug changed');
if (result.status !== 'publish') fails.push('status changed');
if (result.parent !== 0) fails.push('parent changed');
if (result.password) fails.push('password set');
/* allowed inline tags stay as typed (render.php escapes them); no &amp; / &lt; entities appear */
if (result.savedTitle !== 'QA R&D <b>bold</b> a < b "quoted"') fails.push('attribute text altered: ' + result.savedTitle);
if (!/rest_cannot_delete|cannot/i.test(result.delete)) fails.push('fixed page deletable');
if (/<script/i.test(result.postContent)) fails.push('script kept in news');
await browser.close();
console.log(fails.length ? 'FAILED: ' + fails.join(', ') : 'ALL SAVE CHECKS PASSED');
process.exit(fails.length ? 1 : 0);
