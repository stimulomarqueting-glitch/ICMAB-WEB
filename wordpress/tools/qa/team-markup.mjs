/**
 * QA (group "team"): structural markup comparison of the team sections,
 * Astro vs WordPress (tags, classes, ids, aria-*, data-*, role, style,
 * text), ignoring what legitimately differs (image URLs/srcset, hrefs to
 * our own pages, Astro scoping attributes, WP image classes).
 *   node wordpress/tools/qa/team-markup.mjs
 */
import { launch } from './wp-login.mjs';

const ASTRO = 'http://127.0.0.1:4321';
const WP = process.env.WP_BASE || 'http://localhost:8907';
const urls = { astro: { en: '/team/', es: '/es/team/' }, wp: { en: '/team/', es: '/es/equipo/' } };
const sections = [
  { astro: 'section.show', wp: 'section.show' },
  { astro: 'section[aria-labelledby="journey-title"]', wp: 'section.tjourney' },
];

const serialize = (sel) => {
  const root = document.querySelector(sel);
  const skipAttr = /^(data-astro-cid-\w+|src|srcset|sizes|width|height|loading|decoding|href)$/;
  const lines = [];
  const walk = (node, depth) => {
    if (node.nodeType === 3) {
      const t = node.textContent.replace(/\s+/g, ' ').trim();
      if (t) lines.push('  '.repeat(depth) + '"' + t + '"');
      return;
    }
    if (node.nodeType !== 1) return;
    if (node.tagName === 'svg') { lines.push('  '.repeat(depth) + '<svg/>'); return; }
    const attrs = [...node.attributes]
      .filter((a) => !skipAttr.test(a.name))
      .map((a) => {
        let v = a.value;
        if (a.name === 'class') v = v.split(/\s+/).filter((c) => c && !/^(attachment-|size-)/.test(c) && c !== 'tjourney').sort().join(' ');
        return a.name === 'class' && !v ? null : `${a.name}="${v}"`;
      })
      .filter(Boolean)
      .sort();
    const href = node.getAttribute('href');
    if (href) attrs.push('href~' + (href.startsWith('http') && !href.includes('localhost') ? href : href.replace(/^https?:\/\/[^/]+/, '').replace(/\/(es\/)?(research|investigacion)\/?/, '/research')));
    lines.push('  '.repeat(depth) + '<' + node.tagName.toLowerCase() + (attrs.length ? ' ' + attrs.join(' ') : '') + '>');
    node.childNodes.forEach((c) => walk(c, depth + 1));
  };
  walk(root, 0);
  return lines;
};

const browser = await launch();
let total = 0;
for (const lang of ['en', 'es']) {
  const dumps = {};
  for (const site of ['astro', 'wp']) {
    const page = await browser.newPage();
    await page.goto((site === 'astro' ? ASTRO : WP) + urls[site][lang], { waitUntil: 'domcontentloaded' });
    dumps[site] = [];
    for (const s of sections) dumps[site].push(await page.evaluate(serialize, s[site]));
    await page.close();
  }
  sections.forEach((s, i) => {
    const a = dumps.astro[i];
    const b = dumps.wp[i];
    const n = Math.max(a.length, b.length);
    let diffs = 0;
    for (let k = 0; k < n; k++) {
      if (a[k] !== b[k]) {
        if (diffs < 25) console.log(`[${lang} ${s.wp}] line ${k}\n  astro: ${a[k]}\n  wp:    ${b[k]}`);
        diffs++;
      }
    }
    total += diffs;
    console.log(`== ${lang} ${s.wp}: ${a.length} vs ${b.length} lines, ${diffs} differing`);
  });
}
await browser.close();
process.exit(total ? 1 : 0);
