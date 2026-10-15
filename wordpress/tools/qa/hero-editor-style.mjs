/** QA (group "hero"): computed styles of hero parts inside the editor preview vs. public page. */
import { launch, login, closeWelcome, BASE } from './wp-login.mjs';
const browser = await launch();
const page = await login(browser);
await page.goto(`${BASE}/wp-admin/post.php?post=${process.argv[2] || 35}&action=edit`);
await page.waitForSelector('iframe[name="editor-canvas"]', { timeout: 60000 });
await closeWelcome(page);
const frame = page.frameLocator('iframe[name="editor-canvas"]');
const btn = frame.locator('.mats-preview .field__ctas .btn').first();
await btn.waitFor({ timeout: 30000 });
const props = ['borderRadius', 'padding', 'fontFamily', 'fontSize', 'fontWeight', 'backgroundColor', 'lineHeight'];
const get = (loc) => loc.evaluate((el, props) => { const cs = getComputedStyle(el); return props.map((p) => p + '=' + cs[p]).join(' | '); }, props);
console.log('editor btn:', await get(btn));
console.log('editor title:', await get(frame.locator('.mats-preview .field__title').first()));
const p2 = await page.context().newPage();
await p2.goto(`${BASE}/`);
console.log('public btn:', await get(p2.locator('.field__ctas .btn').first()));
console.log('public title:', await get(p2.locator('.field__title').first()));
const rules = await btn.evaluate((el) => {
  const out = [];
  for (const sh of el.ownerDocument.styleSheets) { let rs; try { rs = sh.cssRules; } catch (e) { continue; }
    for (const r of rs) { if (r.selectorText && el.matches(r.selectorText) && /radius/.test(r.cssText)) out.push((sh.href || 'inline:' + (sh.ownerNode && sh.ownerNode.id)) .slice(-60) + ' :: ' + r.cssText.slice(0, 200)); } }
  return out;
});
console.log(rules.join('\n'));
await browser.close();
