/** QA (group "benefits"): computed styles of key elements in the editor canvas vs the public page.
 *  node wordpress/tools/qa/benefits-editor-style.mjs <postId> <publicUrl> */
import { launch, login, closeWelcome, BASE } from './wp-login.mjs';
const postId = process.argv[2] || '35';
const pub = process.argv[3] || `${BASE}/`;
const sels = ['.audiences .eyebrow', '.audiences .h2', '.aud__title', '.aud__more p', '.bene--dark .eyebrow', '.bene__outcome h3', '.bene__outcome p'];
const props = ['fontFamily', 'fontWeight', 'fontSize', 'lineHeight', 'letterSpacing', 'color'];
const read = ({ sels, props }) => Object.fromEntries(sels.map((s) => { const e = document.querySelector(s); if (!e) return [s, null]; const cs = getComputedStyle(e); return [s, props.map((p) => cs[p]).join(' | ')]; }));
const browser = await launch();
const page = await login(browser, 'admin', 'admin-local-pass', { width: 1600, height: 1000 });
await page.goto(`${BASE}/wp-admin/post.php?post=${postId}&action=edit`);
await page.waitForSelector('iframe[name="editor-canvas"]', { timeout: 60000 });
await closeWelcome(page);
const frame = page.frame({ name: 'editor-canvas' });
await frame.waitForSelector('.mats-preview .audiences .aud__title', { timeout: 30000 });
await page.waitForTimeout(1500);
const ed = await frame.evaluate(read, { sels, props });
const ctx = await browser.newContext({ viewport: { width: 1600, height: 1000 } });
const p2 = await ctx.newPage();
await p2.goto(pub, { waitUntil: 'networkidle' });
const pu = await p2.evaluate(read, { sels, props });
for (const s of sels) console.log(s, '\n  editor:', ed[s], '\n  public:', pu[s], ed[s] === pu[s] ? 'SAME' : 'DIFF');
await browser.close();
