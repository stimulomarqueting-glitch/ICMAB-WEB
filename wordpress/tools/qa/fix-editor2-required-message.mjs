/**
 * QA (fix "team-showcase-required-message"), as the Editor: emptying the H1
 * of «Escaparate del equipo» (Equipo / Team pages, with people published)
 * still locks saving, but the warning on the section, the side panel and
 * the notice at the top give the real reason («El título de la página (H1)
 * no puede quedar vacío: Google y los lectores de pantalla lo necesitan»)
 * instead of saying the section would disappear (it does not: it only
 * loses its H1). Typing the title back unlocks saving. Other sections keep
 * the default message (page hero H1, FAQ question). Nothing is saved.
 *
 *   WP_BASE=http://localhost:8985 node wordpress/tools/qa/fix-editor2-required-message.mjs
 * OLD_JS=/path/to/old/blocks.js serves that file instead of the theme's one
 * (to reproduce the bug with the previous code).
 */
import fs from 'node:fs';
import { launch, login, closeWelcome, BASE } from './wp-login.mjs';

const TEAM = (process.env.TEAM_IDS || '17 16').split(' ').map(Number);
const PRODUCT = Number(process.env.PRODUCT_ID || 11);
const MESSAGE = 'El título de la página (H1) no puede quedar vacío: Google y los lectores de pantalla lo necesitan.';

const browser = await launch();
const page = await login(browser, process.env.WP_USER || 'marta', process.env.WP_PASS || 'Editor-pass-2026');
page.on('dialog', (d) => d.accept());
if (process.env.OLD_JS) {
  const body = fs.readFileSync(process.env.OLD_JS, 'utf8');
  await page.route('**/assets/js/editor/blocks.js*', (route) => route.fulfill({ status: 200, contentType: 'application/javascript', body }));
}
let fails = 0;
const check = (label, ok, extra = '') => {
  if (!ok) fails++;
  console.log((ok ? 'ok   ' : 'FAIL ') + label + (extra ? ' :: ' + extra : ''));
};
const canvas = () => page.frameLocator('iframe[name="editor-canvas"]');

async function open(id) {
  await page.goto(`${BASE}/wp-admin/post.php?post=${id}&action=edit`);
  await page.waitForFunction(() => window.wp && wp.data && wp.data.select('core/block-editor').getBlocks().length > 0, null, { timeout: 60000 });
  await closeWelcome(page);
  await canvas().locator('.mats-preview__html > *').first().waitFor({ timeout: 60000 });
  await page.waitForTimeout(1000);
}
async function change(name, src) {
  await page.evaluate(([n, s]) => {
    const fn = new Function('return ' + s)();
    const b = wp.data.select('core/block-editor').getBlocks().find((x) => x.name === n);
    wp.data.dispatch('core/block-editor').updateBlockAttributes(b.clientId, fn(b.attributes));
    wp.data.dispatch('core/block-editor').selectBlock(b.clientId);
  }, [name, src]);
  await page.waitForTimeout(1200);
}
const state = (name) =>
  page.evaluate(async (n) => {
    const doc = document.querySelector('iframe[name="editor-canvas"]').contentDocument;
    const block = doc.querySelector(`[data-type="${n}"]`);
    return {
      locked: wp.data.select('core/editor').isPostSavingLocked(),
      warning: ((block && block.querySelector('.mats-required')) || {}).innerText || '',
      panel: (document.querySelector('.mats-required-panel') || {}).innerText || '',
      notices: wp.data.select('core/notices').getNotices().filter((x) => String(x.id).startsWith('mats-required-')).map((x) => x.content).join(' | '),
      hint: [...document.querySelectorAll('.block-editor-block-inspector .components-base-control__help')].map((h) => h.textContent).join(' | '),
    };
  }, name);

for (const id of TEAM) {
  await open(id);
  const people = await canvas().locator('[data-type="matsurfer/team-showcase"] .mats-preview__html [class*="avatar"], [data-type="matsurfer/team-showcase"] .mats-preview__html img').count();
  check(`page ${id}: team showcase shows people (${people} photos)`, people > 0);
  const before = await state('matsurfer/team-showcase');
  check(`page ${id}: opens unlocked, no warning`, !before.locked && !before.warning);
  await change('matsurfer/team-showcase', `(a) => ({ title: '' })`);
  const s = await state('matsurfer/team-showcase');
  check(`page ${id}: empty H1 → saving locked`, s.locked);
  check(`page ${id}: warning on the section gives the real reason`, s.warning.includes(MESSAGE) && !/para mostrarse en la web/.test(s.warning), s.warning.replace(/\s+/g, ' ').slice(0, 160));
  check(`page ${id}: side panel says the same`, s.panel.includes(MESSAGE) && !/para mostrarse en la web/.test(s.panel), s.panel.replace(/\s+/g, ' ').slice(0, 160));
  check(`page ${id}: top notice says the same`, s.notices.includes(MESSAGE), s.notices.slice(0, 160));
  check(`page ${id}: the field hint says the same`, s.hint.includes(MESSAGE), s.hint.slice(0, 200));
  await change('matsurfer/team-showcase', `(a) => ({ title: 'Equipo QA' })`);
  const t = await state('matsurfer/team-showcase');
  check(`page ${id}: typing the title back unlocks`, !t.locked && !t.warning && !t.notices, JSON.stringify(t).slice(0, 160));
}

/* other sections keep the default message */
await open(PRODUCT);
await change('matsurfer/page-hero', `(a) => ({ title: '' })`);
let s = await state('matsurfer/page-hero');
check('page hero: default message («… para mostrarse en la web»)', s.locked && /Esta sección necesita «Título de la página \(H1\)» para mostrarse en la web\./.test(s.warning), s.warning.replace(/\s+/g, ' ').slice(0, 160));
await change('matsurfer/page-hero', `(a) => ({ title: 'QA' })`);
await change('matsurfer/faq', `(a) => ({ items: a.items.map((it, i) => (i === 0 ? Object.assign({}, it, { q: '' }) : it)) })`);
s = await state('matsurfer/faq');
check('FAQ item: default message', s.locked && /Pregunta 1 necesita «Pregunta» para mostrarse en la web\./.test(s.warning), s.warning.replace(/\s+/g, ' ').slice(0, 160));

console.log(fails ? 'RESULT FAIL ' + fails : 'RESULT ok');
await browser.close();
process.exit(fails ? 1 : 0);
