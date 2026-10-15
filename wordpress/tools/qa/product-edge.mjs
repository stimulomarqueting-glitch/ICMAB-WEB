/**
 * QA (group "product"): edge cases of the product sections on the WP edge
 * page built by product-build.php —
 *  - no-video branch of #how-it-works vs the Astro no-video markup (the
 *    Astro build has the video, so its figure is swapped in the DOM for
 *    the exact markup of its no-video branch, same scoped attribute);
 *  - sections repeated twice: unique ids, each accordion / video facade
 *    wired on its own;
 *  - empty sections print nothing;
 *  - prefers-reduced-motion: the accordion opens/closes at once.
 *   node wordpress/tools/qa/product-edge.mjs <outDir>
 */
import { launch } from './wp-login.mjs';
import fs from 'node:fs';

const out = process.argv[2] || '/tmp/product-edge';
fs.mkdirSync(out, { recursive: true });
const ASTRO = 'http://127.0.0.1:4321';
const WP = process.env.WP_BASE || 'http://localhost:8905';
let ok = true;
const check = (cond, msg) => { console.log(`${cond ? 'OK  ' : 'FAIL'} ${msg}`); if (!cond) ok = false; };

const browser = await launch();
for (const lang of ['en', 'es']) {
  const ph = { en: 'Device video coming soon', es: 'Vídeo del dispositivo próximamente' }[lang];
  for (const w of [1440, 390]) {
    /* Astro, no-video branch emulated in the DOM */
    let ctx = await browser.newContext({ viewport: { width: w, height: 1000 } });
    let page = await ctx.newPage();
    await page.goto(`${ASTRO}${lang === 'es' ? '/es' : ''}/product/`, { waitUntil: 'networkidle' });
    await page.evaluate(() => document.fonts.ready);
    await page.evaluate((label) => {
      const box = document.querySelector('#how-it-works .tech__videobox');
      const cid = [...box.attributes].find((a) => a.name.startsWith('data-astro-cid')).name;
      const div = document.createElement('div');
      div.innerHTML = `<div class="tech__video-ph" role="img" aria-label="${label}" ${cid}><span class="tech__video-badge" ${cid}>${label}</span></div>`;
      box.replaceWith(div.firstElementChild);
      document.querySelectorAll('.reveal').forEach((el) => el.classList.add('is-visible'));
    }, ph);
    await page.waitForTimeout(1500);
    await page.locator('#how-it-works .tech__media').scrollIntoViewIfNeeded();
    await page.mouse.move(1, 1);
    await page.waitForTimeout(300);
    await page.locator('#how-it-works .tech__media').screenshot({ path: `${out}/ph-${lang}-${w}-astro.png` });
    await ctx.close();

    /* WordPress edge page: first how-it-works has no video */
    ctx = await browser.newContext({ viewport: { width: w, height: 1000 } });
    page = await ctx.newPage();
    await page.goto(`${WP}${lang === 'es' ? '/es/product-qa-edge-es/' : '/product-qa-edge/'}`, { waitUntil: 'networkidle' });
    await page.evaluate(() => document.fonts.ready);
    await page.evaluate(() => document.querySelectorAll('.reveal').forEach((el) => el.classList.add('is-visible')));
    await page.waitForTimeout(1500);
    const media = page.locator('section.tech .tech__media').first();
    await media.scrollIntoViewIfNeeded();
    await page.mouse.move(1, 1);
    await page.waitForTimeout(300);
    await media.screenshot({ path: `${out}/ph-${lang}-${w}-wp.png` });

    if (w === 1440) {
      const info = await page.evaluate(() => {
        const ids = [...document.querySelectorAll('[id]')].map((e) => e.id);
        const dup = ids.filter((id, i) => ids.indexOf(id) !== i);
        const secs = [...document.querySelectorAll('main section')].map((s) => `${s.className.split(' ').pop()}#${s.id || '-'}[${s.getAttribute('aria-labelledby') || '-'}]`);
        const labelled = [...document.querySelectorAll('[aria-labelledby]')].every((e) => document.getElementById(e.getAttribute('aria-labelledby')));
        const controls = [...document.querySelectorAll('[aria-controls]')].every((e) => document.getElementById(e.getAttribute('aria-controls')));
        const phEl = document.querySelector('.tech__video-ph');
        return {
          dup, secs, labelled, controls,
          ph: phEl ? `${phEl.getAttribute('role')}|${phEl.getAttribute('aria-label')}|${phEl.textContent.trim()}` : null,
          posters: [...document.querySelectorAll('.tech__poster')].map((p) => p.id),
          posterNames: [...document.querySelectorAll('.tech__poster')].map((p) => (p.getAttribute('aria-label') || '').trim()),
          faqTriggers: [...document.querySelectorAll('.acc__trigger')].map((b) => b.id).filter((id, i) => i === 0 || i === 7),
          empties: document.querySelectorAll('.mats-empty').length,
        };
      });
      console.log(lang, 'sections:', info.secs.join('  '));
      check(info.dup.length === 0, `${lang}: no duplicate ids ${info.dup.join(',')}`);
      check(info.labelled && info.controls, `${lang}: every aria-labelledby / aria-controls resolves`);
      check(info.ph === `img|${ph}|${ph}`, `${lang}: placeholder figure role/label/text (${info.ph})`);
      check(info.posters.length === 1 && info.posters[0] === 'tech-video-poster', `${lang}: one facade, Astro id (${info.posters.join(',')})`);
      check(info.posterNames.every((n) => n.length > 0), `${lang}: play button has an accessible name (${info.posterNames.join(' | ')})`);
      check(info.secs.length === 8, `${lang}: 8 sections printed, empty ones print nothing (${info.secs.length})`);
      check(info.empties === 0, `${lang}: no editor empty-state on the public page`);
      console.log(lang, 'faq trigger ids (first of each accordion):', info.faqTriggers.join(', '));

      /* second accordion wired on its own */
      const acc2 = page.locator('section.faq').nth(1);
      await acc2.locator('.acc__trigger').first().click();
      await page.waitForTimeout(800);
      const s = await page.evaluate(() => {
        const accs = document.querySelectorAll('section.faq');
        const f = (a) => { const li = a.querySelector('.acc__item'); return `${li.querySelector('.acc__trigger').getAttribute('aria-expanded')}/${li.hasAttribute('data-open')}/${li.querySelector('.acc__panel').hidden}`; };
        return [f(accs[0]), f(accs[1])];
      });
      check(s[0] === 'false/false/true' && s[1] === 'true/true/false', `${lang}: clicking the 2nd accordion opens only its own item (${s.join(' | ')})`);
      /* video facade in the 2nd how-it-works */
      await page.locator('.tech__poster').first().click();
      await page.waitForTimeout(500);
      const gone = await page.evaluate(() => document.querySelectorAll('.tech__poster').length);
      check(gone === 0, `${lang}: facade removed on click`);
    }
    await ctx.close();
  }
}

/* reduced motion: open/close at once */
{
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  await page.goto(`${WP}/product/`, { waitUntil: 'networkidle' });
  const t = page.locator('#faq .acc__trigger').first();
  await t.click();
  await page.waitForTimeout(30);
  const open = await page.evaluate(() => { const li = document.querySelector('#faq .acc__item'); return `${li.hasAttribute('data-open')}/${li.querySelector('.acc__panel').hidden}/${Math.round(li.querySelector('.acc__panel').getBoundingClientRect().height)}`; });
  await t.click();
  await page.waitForTimeout(30);
  const closed = await page.evaluate(() => { const li = document.querySelector('#faq .acc__item'); return `${li.hasAttribute('data-open')}/${li.querySelector('.acc__panel').hidden}`; });
  check(open.startsWith('true/false/') && Number(open.split('/')[2]) > 50 && closed === 'false/true', `reduced motion: opens (${open}) and closes (${closed}) at once`);
  /* reveal content visible without JS under reduced motion */
  const op = await page.evaluate(() => getComputedStyle(document.querySelector('#parameters .params__grid')).opacity);
  check(op === '1', `reduced motion: .reveal content visible (opacity ${op})`);
  /* the "d" line does not breathe */
  check(errors.length === 0, `reduced motion: no JS errors ${errors.join(' ')}`);
  await ctx.close();
}
console.log(ok ? 'RESULT: PASS' : 'RESULT: FAIL');
await browser.close();
