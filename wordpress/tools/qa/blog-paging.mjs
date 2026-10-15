/**
 * QA (module "blog"): walks the News pagination and checks every article
 * appears exactly once, newest first (run blog-paging.php add first).
 *   WP_BASE=http://localhost:8914 node wordpress/tools/qa/blog-paging.mjs [/news/] [expectedTotal]
 */
import { launch, BASE } from './wp-login.mjs';

const start = process.argv[2] || '/news/';
const expected = Number(process.argv[3] || 0);
const browser = await launch();
const page = await browser.newPage();
let url = BASE + start;
const seen = [];
let n = 0;
while (url && n < 10) {
  n++;
  const res = await page.goto(url, { waitUntil: 'domcontentloaded' });
  const data = await page.evaluate(() => ({
    titles: [...document.querySelectorAll('.post-card__link')].map((a) => a.textContent.trim()),
    dates: [...document.querySelectorAll('.post-card time')].map((t) => t.getAttribute('datetime')),
    featured: document.querySelectorAll('.post-card--featured').length,
    status: document.querySelector('.blog-pager__status')?.textContent.trim() || '',
    note: document.querySelector('.phero__note')?.textContent.trim() || '',
    next: document.querySelector('.blog-pager__link--next')?.href || '',
    prev: document.querySelector('.blog-pager__link--prev')?.href || '',
  }));
  console.log(`page ${n} [${res.status()}] ${data.titles.length} cards, featured=${data.featured}, "${data.status}" note="${data.note}" prev=${data.prev ? 'yes' : 'no'}`);
  seen.push(...data.titles.map((t, i) => [t, data.dates[i]]));
  url = data.next;
}
const titles = seen.map((s) => s[0]);
const dupes = titles.filter((t, i) => titles.indexOf(t) !== i);
const sorted = seen.every((s, i) => i === 0 || seen[i - 1][1] >= s[1]);
console.log(`total ${titles.length}${expected ? ` (expected ${expected})` : ''}, duplicates: ${dupes.length ? dupes : 'none'}, newest first: ${sorted}`);
await browser.close();
