/**
 * Legibility audit (client request: "some text is too small / hard to read").
 * For every visible text element of the main pages (EN + ES, 1440 + 390) it
 * records the computed font size, weight and family, classifies it
 * (heading / label / caption / body) and measures the contrast against the
 * pixels really behind the glyphs: the page is shot twice, once as is and
 * once with every glyph made transparent, and the second shot is sampled
 * inside each text box (so gradients, photos, glass and the audiences
 * spotlight are measured as they render). Interactive states that reveal
 * text are audited too: every audiences card active, the hovered card with
 * the spotlight under the pointer, every FAQ item open, the phone menu open.
 *
 *   WP_BASE=http://localhost:8990 OUT=/path/to/dir TAG=before node wordpress/tools/qa/legibility-audit.mjs
 *   PAGES="/,/es/" WIDTHS="1440,390" limit the run.
 *
 * Writes OUT/audit-TAG.json, OUT/audit-TAG.csv, OUT/audit-TAG-fails.txt and
 * full-page shots OUT/shots-TAG/<lang>-<page>-<width>.png.
 *
 * Thresholds: nothing under 12px; mono / uppercase labels >= 12px; captions
 * and microcopy >= 14px; paragraphs (block text >= 70 characters) >= 16px on
 * desktop and >= 15px on phones; contrast >= 4.5:1 (>= 3:1 from 24px, or
 * from 18.66px at weight 700). The contrast used for pass/fail is the worst
 * 5% of the background pixels behind the text (photos and gradients vary).
 */
import { mkdirSync, writeFileSync } from 'node:fs';
import { chromium } from '../../../node_modules/playwright-core/index.mjs';
import sharp from '../../../node_modules/sharp/lib/index.js';

const BASE = process.env.WP_BASE || 'http://localhost:8990';
const OUT = process.env.OUT || '.';
const TAG = process.env.TAG || 'run';
const WIDTHS = (process.env.WIDTHS || '1440,390').split(',').map(Number);
const ALL_PAGES = ['/', '/product/', '/applications/', '/research/', '/team/', '/contact/', '/es/', '/es/producto/', '/es/aplicaciones/', '/es/casos-de-estudio/', '/es/equipo/', '/es/contacto/'];
const PAGES = process.env.PAGES ? process.env.PAGES.split(',') : ALL_PAGES;
const SHOTS = `${OUT}/shots-${TAG}`;
mkdirSync(SHOTS, { recursive: true });

const FREEZE = `
.reveal{opacity:1!important;transform:none!important}
.rise-in,.field .rise{opacity:1!important;transform:none!important;animation:none!important}
#wpadminbar{display:none!important}
*,*::before,*::after{transition-duration:0s!important;transition-delay:0s!important;animation-duration:0s!important;animation-delay:0s!important;caret-color:transparent!important}
`;
const NO_TEXT = `
*,*::before,*::after{color:transparent!important;-webkit-text-fill-color:transparent!important;text-shadow:none!important;text-decoration-color:transparent!important}
[data-la-clip]{background-image:none!important;background-color:transparent!important}
input::placeholder,textarea::placeholder{color:transparent!important}
`;

const slug = (p) => (p.replace(/^\/|\/$/g, '').replace(/\//g, '_') || 'home');
const langOf = (p) => (p.startsWith('/es/') ? 'es' : 'en');

/* ---------- colour maths ---------- */
const lin = (c) => {
  c /= 255;
  return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
};
const lum = ([r, g, b]) => 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b);
const ratio = (a, b) => {
  const [hi, lo] = a > b ? [a, b] : [b, a];
  return (hi + 0.05) / (lo + 0.05);
};
const mix = (fg, bg, a) => [0, 1, 2].map((i) => fg[i] * a + bg[i] * (1 - a));
const pct = (arr, p) => {
  if (!arr.length) return null;
  const s = [...arr].sort((x, y) => x - y);
  return s[Math.min(s.length - 1, Math.max(0, Math.floor(p * (s.length - 1))))];
};

/* ---------- in-page collector ---------- */
function collectInPage(scopeSel) {
  const canvas = document.createElement('canvas');
  canvas.width = canvas.height = 1;
  const ctx = canvas.getContext('2d', { willReadFrequently: true });
  const toRGBA = (c) => {
    ctx.clearRect(0, 0, 1, 1);
    ctx.fillStyle = '#000';
    ctx.fillStyle = c;
    ctx.fillRect(0, 0, 1, 1);
    const d = ctx.getImageData(0, 0, 1, 1).data;
    return [d[0], d[1], d[2], +(d[3] / 255).toFixed(3)];
  };
  const roots = scopeSel ? [...document.querySelectorAll(scopeSel)] : [document.body];
  const groups = new Map();
  for (const root of roots) {
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
      acceptNode: (n) => (n.nodeValue.trim() ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT),
    });
    while (walker.nextNode()) {
      const n = walker.currentNode;
      const el = n.parentElement;
      if (!el) continue;
      if (!groups.has(el)) groups.set(el, []);
      groups.get(el).push(n);
    }
  }
  const out = [];
  const sx = window.scrollX;
  const sy = window.scrollY;
  for (const [el, nodes] of groups) {
    if (el.closest('script,style,noscript,template,#wpadminbar,.screen-reader-text,.sr-only,[hidden],svg title,svg desc,option')) continue;
    const cs = getComputedStyle(el);
    if (cs.visibility !== 'visible') continue;
    if (el.checkVisibility && !el.checkVisibility({ opacityProperty: true, visibilityProperty: true, contentVisibilityAuto: true })) continue;
    let op = 1;
    const clips = [];
    for (let a = el; a && a !== document.documentElement; a = a.parentElement) {
      const c = getComputedStyle(a);
      op *= parseFloat(c.opacity);
      if (a !== el && (c.overflowX !== 'visible' || c.overflowY !== 'visible' || c.clipPath !== 'none')) {
        const r = a.getBoundingClientRect();
        clips.push({ l: r.left + sx, t: r.top + sy, r: r.right + sx, b: r.bottom + sy, x: c.overflowX !== 'visible', y: c.overflowY !== 'visible' });
      }
      if (c.position === 'fixed') break;
    }
    if (op < 0.05) continue;
    const rects = [];
    for (const n of nodes) {
      const range = document.createRange();
      range.selectNodeContents(n);
      for (const q of range.getClientRects()) {
        let l = q.left + sx, t = q.top + sy, r = q.right + sx, b = q.bottom + sy;
        for (const c of clips) {
          if (c.x) { l = Math.max(l, c.l); r = Math.min(r, c.r); }
          if (c.y) { t = Math.max(t, c.t); b = Math.min(b, c.b); }
        }
        if (r - l >= 2 && b - t >= 2) rects.push({ l, t, r, b });
      }
    }
    if (!rects.length) continue;

    const tag = el.tagName.toLowerCase();
    const fs = parseFloat(cs.fontSize);
    const fw = parseInt(cs.fontWeight, 10) || 400;
    const fam = cs.fontFamily.split(',')[0].replace(/["']/g, '').trim();
    const mono = /mono/i.test(cs.fontFamily);
    const upper = cs.textTransform === 'uppercase';
    const own = nodes.map((n) => n.nodeValue).join(' ').replace(/\s+/g, ' ').trim();
    /* nearest block ancestor: paragraphs keep their category across inline tags */
    let block = el;
    while (block && block.parentElement && /^inline/.test(getComputedStyle(block).display) && !/^(a|button)$/i.test(block.tagName)) block = block.parentElement;
    const blockText = (block.textContent || '').replace(/\s+/g, ' ').trim();
    const heading = !!el.closest('h1,h2,h3,h4,h5,h6');
    const isBtn = !!el.closest('.btn,button');
    /* paragraphs: running text in a p / li / dd / blockquote, 60+ characters */
    /* bylines, citation metadata and footnotes count as captions (>= 14px) */
    const captionLike = !!el.closest('.research__who, .research__meta, .journey__note');
    const para = !isBtn && !captionLike && /^(p|li|dd|blockquote)$/i.test(block.tagName) && blockText.length >= 60;
    let cat;
    if (!/[\p{L}\p{N}]/u.test(own)) cat = 'icon';
    else if (heading || fs >= 24) cat = 'heading';
    else if (mono || upper) cat = 'label';
    else if (para) cat = 'body';
    else cat = 'caption';
    /* client request: the description on the dark audiences card needs 7:1 */
    const strict = !!el.closest('.audiences .aud__more');

    const clip = cs.webkitBackgroundClip === 'text' || cs.backgroundClip === 'text';
    let stops = [];
    if (clip) {
      el.setAttribute('data-la-clip', '');
      const m = cs.backgroundImage.match(/rgba?\([^)]+\)|#[0-9a-f]{3,8}\b/gi) || [];
      stops = m.map(toRGBA);
    }
    const sec = el.closest('section,header,footer,nav');
    const secName = sec ? (sec.tagName.toLowerCase() + '.' + [...sec.classList].filter((c) => !/^(sec|hairtop|mist|mist--violet|reveal|is-visible|container)$/.test(c)).join('.')) : 'body';
    const chain = [];
    for (let a = el, k = 0; a && k < 3 && a !== sec; a = a.parentElement, k++) {
      chain.unshift(a.tagName.toLowerCase() + (a.classList.length ? '.' + [...a.classList].filter((c) => c !== 'reveal' && c !== 'is-visible').join('.') : ''));
    }
    out.push({
      sec: secName,
      sel: chain.join(' > '),
      tag,
      text: own.slice(0, 70),
      len: blockText.length,
      fs: +fs.toFixed(2),
      fw,
      fam,
      upper,
      ls: cs.letterSpacing,
      lh: cs.lineHeight,
      color: toRGBA(cs.color),
      op: +op.toFixed(3),
      clip,
      stops,
      cat,
      strict,
      ariaHidden: !!el.closest('[aria-hidden="true"]'),
      rects,
    });
  }
  return out;
}

/* ---------- pixel sampling ---------- */
async function loadRaw(buf) {
  const { data, info } = await sharp(buf).removeAlpha().raw().toBuffer({ resolveWithObject: true });
  return { data, w: info.width, h: info.height };
}

function sampleBg(img, rects, offX = 0, offY = 0) {
  const px = [];
  for (const r of rects) {
    const l = Math.max(0, Math.floor(r.l - offX)), t = Math.max(0, Math.floor(r.t - offY));
    const rr = Math.min(img.w - 1, Math.ceil(r.r - offX)), bb = Math.min(img.h - 1, Math.ceil(r.b - offY));
    const step = Math.max(1, Math.floor(Math.min(rr - l, bb - t) / 12));
    for (let y = t; y <= bb; y += step) {
      for (let x = l; x <= rr; x += step) {
        const i = (y * img.w + x) * 3;
        px.push([img.data[i], img.data[i + 1], img.data[i + 2]]);
      }
    }
  }
  return px;
}

function judge(rec, bgPx, width) {
  const desktop = width >= 1024;
  const large = rec.fs >= 24 || (rec.fs >= 18.66 && rec.fw >= 700);
  const minSize = rec.cat === 'body' ? (desktop ? 16 : 15) : rec.cat === 'caption' ? 14 : 12;
  const need = rec.cat === 'icon' ? 3 : large ? 3 : 4.5;
  if (!bgPx.length) return { ...rec, minSize, need, contrast: null, contrastMed: null, sizeOK: rec.fs >= minSize - 0.05, contrastOK: true };
  const lums = bgPx.map(lum);
  const medIdx = lums.indexOf(pct(lums, 0.5));
  const bgMed = bgPx[medIdx];
  const fills = rec.clip && rec.stops.length ? rec.stops : [rec.color];
  let worst = Infinity, med = Infinity;
  for (const f of fills) {
    const a = (f[3] ?? 1) * rec.op;
    const textRGB = mix(f, bgMed, a);
    const tl = lum(textRGB);
    const crs = lums.map((bl) => ratio(tl, bl));
    worst = Math.min(worst, pct(crs, 0.05));
    med = Math.min(med, pct(crs, 0.5));
  }
  const lmin = pct(lums, 0.02), lmax = pct(lums, 0.98);
  const out = {
    ...rec,
    large,
    minSize,
    need,
    bg: bgMed.map(Math.round),
    bgRange: [lmin, lmax].map((v) => +v.toFixed(3)),
    contrast: +worst.toFixed(2),
    contrastMed: +med.toFixed(2),
    sizeOK: rec.fs >= minSize - 0.05,
    contrastOK: rec.ariaHidden && rec.cat === 'heading' ? true : worst >= need - 0.005,
  };
  /* the 7:1 rule applies on the dark (active) card only */
  if (rec.strict && lum(out.bg) < 0.2) {
    out.need = 7;
    out.contrastOK = worst >= 6.995;
  }
  return out;
}

/* ---------- page setup + states ---------- */
async function prepare(page) {
  await page.addStyleTag({ content: FREEZE });
  await page.evaluate(async () => {
    document.querySelectorAll('img[loading="lazy"]').forEach((i) => (i.loading = 'eager'));
    document.querySelectorAll('video').forEach((v) => { try { v.pause(); v.currentTime = 0; } catch (e) {} });
    await Promise.all([...document.images].map((i) => (i.complete ? null : new Promise((r) => { i.onload = i.onerror = r; setTimeout(r, 4000); }))));
    await document.fonts.ready;
    /* stop the audiences auto-cycle: a pick marks the row as engaged */
    document.querySelectorAll('[data-audiences] .aud__panel[data-active]').forEach((p) => p.dispatchEvent(new Event('click')));
    /* pause the team showcase autoplay */
    document.querySelectorAll('[data-showcase]').forEach((r) => r.dispatchEvent(new Event('pointerenter')));
  });
  await page.waitForTimeout(250);
}

async function statesFor(page, width) {
  const s = [{ name: 'default', scope: null }];
  const nAud = await page.locator('[data-audiences] .aud__panel').count();
  for (let i = 0; i < nAud; i++) {
    s.push({
      name: `aud-${i}-active`, scope: '.audiences',
      setup: async () => page.evaluate((k) => document.querySelectorAll('[data-audiences] .aud__panel')[k].dispatchEvent(new Event('click')), i),
    });
    if (width >= 1024) {
      s.push({
        name: `aud-${i}-hover`, scope: '.audiences',
        setup: async () => {
          await page.evaluate((k) => document.querySelectorAll('[data-audiences] .aud__panel')[k].dispatchEvent(new Event('click')), i);
          const p = page.locator('[data-audiences] .aud__panel').nth(i).locator('.aud__more p');
          await p.scrollIntoViewIfNeeded();
          const b = await p.boundingBox();
          if (b) {
            await page.mouse.move(b.x + b.width * 0.5, b.y + b.height * 0.5, { steps: 1 });
            await page.waitForTimeout(120);
          }
        },
        cleanup: async () => { await page.mouse.move(2, 2); await page.evaluate(() => window.scrollTo(0, 0)); },
      });
    }
  }
  const nTeam = await page.locator('[data-showcase] .show__avatar').count();
  for (let i = 1; i < nTeam; i++) {
    s.push({
      name: `team-${i}`, scope: '.show',
      setup: async () => {
        await page.evaluate((k) => document.querySelectorAll('[data-showcase] .show__avatar')[k].click(), i);
        await page.waitForTimeout(150);
      },
    });
  }
  if (await page.locator('.acc__trigger').count()) {
    s.push({
      name: 'faq-open', scope: '.faq, [data-acc]',
      setup: async () => {
        await page.evaluate(() => document.querySelectorAll('.acc__trigger[aria-expanded="false"]').forEach((b) => b.click()));
        await page.waitForTimeout(300);
      },
    });
  }
  if (width < 1024 && (await page.locator('.header__burger').isVisible())) {
    s.push({
      name: 'menu-open', scope: '.header', viewport: true,
      setup: async () => { await page.click('.header__burger'); await page.waitForTimeout(300); },
      cleanup: async () => { await page.click('.header__burger'); await page.waitForTimeout(200); },
    });
  }
  return s;
}

async function measure(page, state, width, meta) {
  if (state.setup) await state.setup();
  const hover = state.name.endsWith('-hover');
  /* the hover state needs the pointer where it is: shoot the viewport as is */
  if (!state.viewport && !hover) await page.evaluate(() => window.scrollTo(0, 0));
  const recs = await page.evaluate(collectInPage, state.scope);
  let off = [0, 0];
  const opts = state.viewport || hover ? {} : { fullPage: true };
  if (hover) off = await page.evaluate(() => [scrollX, scrollY]);
  if (state.scope && !state.viewport && !hover) {
    const box = await page.evaluate((sel) => {
      const els = [...document.querySelectorAll(sel)];
      if (!els.length) return null;
      let t = Infinity, b = -Infinity;
      els.forEach((e) => { const r = e.getBoundingClientRect(); t = Math.min(t, r.top + scrollY); b = Math.max(b, r.bottom + scrollY); });
      return { x: 0, y: Math.max(0, Math.floor(t)), width: document.documentElement.clientWidth, height: Math.ceil(b - t) };
    }, state.scope);
    if (box) { opts.clip = box; off = [box.x, box.y]; }
  }
  const shotA = await page.screenshot(opts);
  const h = await page.addStyleTag({ content: NO_TEXT });
  const shotB = await page.screenshot(opts);
  await h.evaluate((n) => n.remove());
  return finish(recs, shotA, shotB, off, width, meta, state, page);
}

async function finish(recs, shotA, shotB, off, width, meta, state, page) {
  const img = await loadRaw(shotB);
  const res = recs.map((r) => ({ ...meta, state: state.name, ...judge(r, sampleBg(img, r.rects, off[0], off[1]), width) }));
  for (const r of res) {
    r.y = Math.round(r.rects[0].t);
    delete r.rects;
  }
  if (state.name === 'default') writeFileSync(`${SHOTS}/${meta.lang}-${meta.page}-${width}.png`, shotA);
  else writeFileSync(`${SHOTS}/${meta.lang}-${meta.page}-${width}-${state.name}.png`, shotA);
  await page.evaluate(() => document.querySelectorAll('[data-la-clip]').forEach((e) => e.removeAttribute('data-la-clip')));
  if (state.cleanup) await state.cleanup();
  return res;
}

const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
const all = [];
for (const path of PAGES) {
  for (const width of WIDTHS) {
    const ctx = await browser.newContext({ viewport: { width, height: 900 }, deviceScaleFactor: 1 });
    const page = await ctx.newPage();
    await page.goto(BASE + path, { waitUntil: 'networkidle' });
    await prepare(page);
    const meta = { lang: langOf(path), page: slug(path.replace(/^\/es/, '') || '/'), path, width };
    for (const st of await statesFor(page, width)) {
      try {
        const res = await measure(page, st, width, meta);
        all.push(...res);
      } catch (e) {
        console.log(`ERR ${path} ${width} ${st.name}: ${e.message.split('\n')[0]}`);
      }
    }
    await ctx.close();
    console.log(`done ${path} @${width}`);
  }
}
await browser.close();

writeFileSync(`${OUT}/audit-${TAG}.json`, JSON.stringify(all, null, 1));
const cols = ['lang', 'path', 'width', 'state', 'sec', 'sel', 'tag', 'cat', 'strict', 'fs', 'fw', 'fam', 'upper', 'ls', 'lh', 'color', 'op', 'bg', 'contrast', 'contrastMed', 'need', 'minSize', 'sizeOK', 'contrastOK', 'ariaHidden', 'y', 'text'];
const esc = (v) => {
  const s = Array.isArray(v) ? v.join(' ') : String(v ?? '');
  return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
};
writeFileSync(`${OUT}/audit-${TAG}.csv`, [cols.join(','), ...all.map((r) => cols.map((c) => esc(r[c])).join(','))].join('\n'));

/* failures grouped by selector signature, with where they occur */
const fails = all.filter((r) => !r.sizeOK || !r.contrastOK);
const grp = new Map();
for (const r of fails) {
  const k = `${r.sec} | ${r.sel} | ${r.cat}`;
  if (!grp.has(k)) grp.set(k, { n: 0, size: new Set(), contrast: [], where: new Set(), sample: r.text, fs: new Set(), fw: r.fw, color: r.color });
  const g = grp.get(k);
  g.n++;
  g.where.add(`${r.lang}${r.width}${r.state === 'default' ? '' : ':' + r.state}`);
  if (!r.sizeOK) g.size.add(`${r.fs}<${r.minSize}`);
  if (!r.contrastOK) g.contrast.push(r.contrast);
  g.fs.add(r.fs);
}
const lines = [`Legibility audit ${TAG} — ${all.length} text elements, ${fails.length} failing`, ''];
for (const [k, g] of [...grp.entries()].sort((a, b) => b[1].n - a[1].n)) {
  const c = g.contrast.length ? ` contrast min ${Math.min(...g.contrast).toFixed(2)}` : '';
  lines.push(`${k}\n   n=${g.n} fs=${[...g.fs].join('/')} fw=${g.fw} color=${g.color.join(',')}${g.size.size ? ' SIZE ' + [...g.size].join(' ') : ''}${c}\n   «${g.sample}»\n   ${[...g.where].join(' ')}`);
}
writeFileSync(`${OUT}/audit-${TAG}-fails.txt`, lines.join('\n'));
console.log(lines.slice(0, 1).join('\n'));
