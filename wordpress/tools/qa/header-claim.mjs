/**
 * QA for the brand claim under the logo (template-parts/site-header.php,
 * assets/css/chrome.css): measures the header at several widths in EN and
 * ES, checks that the claim is one line, inside the 68px bar, left-aligned
 * with the logotype, not a link / not focusable / no hover change, and that
 * it never touches the menu, the CTA or the burger. Saves a screenshot of
 * the header per width and language.
 *
 *   WP_BASE=http://localhost:8990 SHOTS=/path/to/dir TAG=5items node wordpress/tools/qa/header-claim.mjs
 *   PATHS="/qa-post/|/es/entrada/" overrides the pages (EN|ES).
 */
import { mkdirSync } from 'node:fs';
import { chromium } from '../../../node_modules/playwright-core/index.mjs';

const BASE = process.env.WP_BASE || 'http://localhost:8990';
const SHOTS = process.env.SHOTS || '';
const TAG = process.env.TAG || 'home';
const WIDTHS = (process.env.WIDTHS || '1440,1280,1120,1100,1066,1040,1025,1024,900,768,640,600,390,360,340,320').split(',').map(Number);
const SHOT_WIDTHS = new Set((process.env.SHOT_WIDTHS || '1440,1280,1100,1024,768,390').split(',').map(Number));
const [PATH_EN, PATH_ES] = (process.env.PATHS || '/|/es/').split('|');

if (SHOTS) mkdirSync(SHOTS, { recursive: true });

const browser = await chromium.launch({
  executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome',
  args: ['--no-sandbox'],
});

let failures = 0;
const fail = (msg) => {
  failures++;
  console.log('  FAIL ' + msg);
};

for (const [lang, path] of [['en', PATH_EN], ['es', PATH_ES]]) {
  for (const width of WIDTHS) {
    const ctx = await browser.newContext({ viewport: { width, height: 800 } });
    const page = await ctx.newPage();
    await page.goto(BASE + path, { waitUntil: 'networkidle' });
    await page.addStyleTag({ content: '.reveal{opacity:1!important;transform:none!important} #wpadminbar{display:none!important}' });
    await page.evaluate(() => document.fonts.ready);
    const m = await page.evaluate(() => {
      const r = (el) => {
        if (!el) return null;
        const b = el.getBoundingClientRect();
        if (!b.width || !b.height || getComputedStyle(el).display === 'none') return null;
        return { l: +b.left.toFixed(1), r: +b.right.toFixed(1), t: +b.top.toFixed(1), b: +b.bottom.toFixed(1), w: +b.width.toFixed(1), h: +b.height.toFixed(1) };
      };
      const claim = document.querySelector('.header__claim');
      const cs = claim ? getComputedStyle(claim) : null;
      const emph = claim && claim.querySelector('b');
      const menuLinks = [...document.querySelectorAll('.header__menu > li:not(.header__menu-cta) > a, .header__menu .lang a')]
        .map((a) => ({ t: a.textContent.trim(), rect: r(a) }))
        .filter((x) => x.rect);
      return {
        headerH: document.querySelector('.header').getBoundingClientRect().height,
        inner: r(document.querySelector('.header__inner')),
        brand: r(document.querySelector('.header__brand')),
        logo: r(document.querySelector('.header__logotype')),
        claim: r(claim),
        claimText: claim ? claim.textContent : '',
        truncated: claim ? claim.scrollWidth > claim.clientWidth + 0.5 : false,
        emphText: emph ? emph.textContent : '',
        claimInLink: !!(claim && claim.closest('a')),
        claimTab: claim ? claim.tabIndex : null,
        claimStyle: cs && {
          font: cs.fontFamily.split(',')[0],
          size: cs.fontSize,
          weight: cs.fontWeight,
          ls: cs.letterSpacing,
          tt: cs.textTransform,
          color: cs.color,
          cursor: cs.cursor,
          bg: cs.backgroundColor + ' ' + cs.backgroundImage,
          border: cs.borderTopWidth + ' ' + cs.borderTopStyle,
          shadow: cs.boxShadow,
          deco: cs.textDecorationLine,
          lh: cs.lineHeight,
        },
        emphStyle: emph && { weight: getComputedStyle(emph).fontWeight, fill: getComputedStyle(emph).webkitTextFillColor, bgimg: getComputedStyle(emph).backgroundImage.slice(0, 60) },
        nav: r(document.querySelector('.header__nav')),
        menuLinks,
        cta: r(document.querySelector('.header__cta')),
        burger: r(document.querySelector('.header__burger')),
        menuItems: document.querySelectorAll('.header__menu > li').length,
        docW: document.documentElement.scrollWidth,
      };
    });

    /* hover the claim: nothing may change */
    let hoverSame = true;
    if (m.claim) {
      const before = await page.$eval('.header__claim', (el) => getComputedStyle(el).color + getComputedStyle(el).textDecorationLine + getComputedStyle(el).backgroundColor);
      await page.hover('.header__claim');
      const after = await page.$eval('.header__claim', (el) => getComputedStyle(el).color + getComputedStyle(el).textDecorationLine + getComputedStyle(el).backgroundColor);
      hoverSame = before === after;
      await page.mouse.move(0, 400);
    }

    const right = [];
    const firstMenu = m.menuLinks.length && m.nav && m.nav.l > 0 && !m.burger ? m.menuLinks[0].rect : null;
    if (firstMenu) right.push(['menu', firstMenu.l]);
    if (m.cta) right.push(['cta', m.cta.l]);
    if (m.burger) right.push(['burger', m.burger.l]);
    const nextLeft = right.length ? Math.min(...right.map((x) => x[1])) : Infinity;
    const nextName = right.length ? right.find((x) => x[1] === nextLeft)[0] : '-';
    const room = m.claim ? +(nextLeft - Math.max(m.claim.r, m.logo.r)).toFixed(1) : null;
    const wrapped = m.menuLinks.filter((x) => x.rect.h > 30).map((x) => x.t);

    console.log(
      `${lang} ${String(width).padStart(4)} items=${m.menuItems} header=${m.headerH} logo=${m.logo?.l}-${m.logo?.r} (top ${m.logo?.t}) claim=${m.claim?.l}-${m.claim?.r} w=${m.claim?.w} h=${m.claim?.h} (top ${m.claim?.t}, bottom ${m.claim?.b}) ls=${m.claimStyle?.ls}${m.truncated ? ' TRUNCATED' : ''} gap→${nextName}=${room}px` +
        (wrapped.length ? ` WRAPPED:${wrapped.join(',')}` : ''),
    );

    if (!m.claim) fail(`${lang} ${width}: claim not visible`);
    else {
      if (m.truncated && !process.env.ALLOW_TRUNCATE) fail(`${lang} ${width}: claim cut with an ellipsis`);
      if (m.claim.h > 20) fail(`${lang} ${width}: claim wraps (h=${m.claim.h})`);
      if (Math.abs(m.claim.l - m.logo.l) > 0.6) fail(`${lang} ${width}: claim not left-aligned with logotype (${m.claim.l} vs ${m.logo.l})`);
      if (m.logo.t < 0 || m.claim.b > m.headerH) fail(`${lang} ${width}: logo/claim outside the bar`);
      if (room !== null && room < 12) fail(`${lang} ${width}: only ${room}px between brand and ${nextName}`);
      if (m.claimInLink) fail(`${lang} ${width}: claim inside a link`);
      if (m.claimTab >= 0) fail(`${lang} ${width}: claim focusable`);
      if (!hoverSame) fail(`${lang} ${width}: claim changes on hover`);
      if (parseFloat(m.claimStyle.size) < 12.4) fail(`${lang} ${width}: claim font-size ${m.claimStyle.size}`);
    }
    if (m.headerH !== 68) fail(`${lang} ${width}: header height ${m.headerH}`);
    if (m.docW > width) fail(`${lang} ${width}: horizontal scroll (${m.docW})`);
    if (wrapped.length) fail(`${lang} ${width}: menu items wrap: ${wrapped.join(', ')}`);

    if (width === 1440) {
      console.log('   text:', JSON.stringify(m.claimText), 'emph:', JSON.stringify(m.emphText));
      console.log('   style:', JSON.stringify(m.claimStyle));
      console.log('   emph :', JSON.stringify(m.emphStyle));
    }

    if (SHOTS && SHOT_WIDTHS.has(width)) {
      await page.screenshot({ path: `${SHOTS}/${TAG}-${lang}-${width}.png`, clip: { x: 0, y: 0, width, height: 68 } });
    }
    await ctx.close();
  }
}

await browser.close();
console.log(failures ? `${failures} failure(s)` : 'all checks passed');
process.exit(failures ? 1 : 0);
