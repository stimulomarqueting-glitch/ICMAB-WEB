/**
 * QA (group "team"): screenshots of the showcase edge states set up by
 * team-edge.php on (monogram portrait + thumb, missing bio, website link,
 * default profile = first with a cut-out).
 *   node wordpress/tools/qa/team-edge.mjs <outDir>
 */
import { launch } from './wp-login.mjs';
import fs from 'node:fs';
const out = process.argv[2] || '/tmp/team-edge';
fs.mkdirSync(out, { recursive: true });
const WP = process.env.WP_BASE || 'http://localhost:8907';
const browser = await launch();
for (const w of [1440, 390]) {
  for (const [lang, path] of [['en', '/team/'], ['es', '/es/equipo/']]) {
    const ctx = await browser.newContext({ viewport: { width: w, height: 1000 }, reducedMotion: 'reduce' });
    const page = await ctx.newPage();
    await page.goto(WP + path, { waitUntil: 'networkidle' });
    const initial = await page.evaluate(() => [...document.querySelectorAll('.show__avatar')].map((b) => b.getAttribute('aria-pressed')).join(','));
    console.log(w, lang, 'initial pressed:', initial);
    await page.locator('section.show').screenshot({ path: `${out}/edge-initial-${w}-${lang}.png` });
    await page.locator('.show__avatar').nth(2).click();
    await page.mouse.move(0, 0);
    await page.waitForTimeout(200);
    const meta = await page.evaluate(() => {
      const m = document.querySelector('.show__meta[data-active]');
      const a = m.querySelector('a');
      return { bio: document.querySelector('.show__bio[data-active]').textContent, link: a && [a.textContent.trim(), a.getAttribute('aria-label'), a.href] };
    });
    console.log('   person 2:', JSON.stringify(meta));
    await page.locator('section.show').screenshot({ path: `${out}/edge-mono-${w}-${lang}.png` });
    await ctx.close();
  }
}
await browser.close();
