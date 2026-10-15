/** QA (group "team"): two showcases + two journeys on one page.  node team-multi.mjs */
import { launch } from './wp-login.mjs';
const WP = process.env.WP_BASE || 'http://localhost:8907';
const browser = await launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));
await page.goto(WP + '/team-multi/', { waitUntil: 'networkidle' });
const ids = await page.evaluate(() => {
  const all = [...document.querySelectorAll('[id]')].map((e) => e.id);
  const dup = all.filter((v, i) => all.indexOf(v) !== i);
  const labelled = [...document.querySelectorAll('section[aria-labelledby]')].map((s) => s.getAttribute('aria-labelledby') + ':' + !!document.getElementById(s.getAttribute('aria-labelledby')));
  return { dup, labelled };
});
console.log('duplicate ids:', JSON.stringify(ids.dup), 'labelledby:', ids.labelled.join(', '));
const shows = page.locator('section.show');
await shows.nth(1).scrollIntoViewIfNeeded();
await shows.nth(1).locator('.show__avatar').nth(2).click();
await page.waitForTimeout(300);
const st = await page.evaluate(() => [...document.querySelectorAll('section.show')].map((r) => [...r.querySelectorAll('.show__avatar')].map((b) => b.getAttribute('aria-pressed') === 'true' ? 1 : 0).join('')));
console.log('pressed per showcase after clicking #2 in the second:', st.join(' / '));
console.log(errors.length ? errors : 'no JS errors');
await browser.close();
