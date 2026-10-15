# MatSurfer WordPress theme — architecture & conventions

The WordPress version of the Astro site in `src/`. Same design, same copy,
EN at the root and ES under `/es/` (Polylang), editable by non-technical
staff, leads through WPForms. The Astro site stays the visual reference.

```
wordpress/
  matsurfer/                 ← the theme (zip this folder to install)
    functions.php            loads inc/<core>.php in order, then inc/modules/*.php
    inc/i18n.php             mats_lang(), mats_pick(), mats_t(), language switcher
    inc/settings-api.php     mats_setting( key ), mats_contact_email()
    inc/links.php            page roles, mats_link_url(), mats_link_attrs()
    inc/helpers.php          mats_edit(), mats_text(), mats_image(), mats_icon()…
    inc/content.php          CPT data layer + getters + seed (team, apps…)
    inc/blocks.php           auto-registers blocks/*, field schema → attributes
    inc/assets.php           base.css, chrome.css, site.js, editor styles
    inc/setup.php            theme supports, mats_is_landing()
    inc/modules/*.php        self-contained feature modules (hooks only)
    inc/data/                content.json (original copy), settings-defaults.php
    blocks/<name>/           one page section per folder
    assets/css|js|fonts|brand|icons|media
    template-parts/          site-header.php, site-footer.php, …
  tools/                     node scripts (content export) + qa/ (Playwright)
```

## Sections = dynamic blocks with locked structure

Each section of the Astro pages is one block in `blocks/<name>/`:

* `block.json` — metadata + `"matsurfer": { "fields": [...] }`. **Attributes
  are derived from the fields** (see the schema doc at the top of
  `inc/blocks.php`). Field keys are a contract with the importer: never
  rename or remove a key; you may improve labels/help, add fields, or add
  non-editable attributes under `"attributes"`.
* `render.php` — server rendering. Must output **the same markup and classes
  as the Astro component** so the ported CSS gives a pixel match. Receives
  `$attributes`. Escape everything (`mats_text`, `esc_attr`, `esc_url`,
  `mats_link_attrs`). Read values with `mats_get( $attributes, 'path' )`.
  Optional elements render only when filled (`mats_has()`).
* `style.css` — the component's `<style>` ported from Astro. Astro scoped
  styles were per-component; here CSS is global, so **every selector must be
  scoped under the section's own root class** (e.g. `.pcta .btn`, never a
  bare `.btn`, `.eyebrow`, `.lead`, `.field`, `h2`). Global utilities
  (`.btn`, `.panel`, `.sec`, `.container`, `.eyebrow`, `.h2`, `.lead`,
  `.reveal`, `.rise-in`, `.mono`, `.surface-line`) come from
  `assets/css/base.css` (= `src/styles/global.css`) — do not redefine them.
* `view.js` (optional, `"viewScript": "file:./view.js"`) — the component's
  `<script>` ported to plain ES2017 (no imports, no TypeScript), wrapped in
  an IIFE, defensive (`if (!el) return`), respecting
  `prefers-reduced-motion`. It must work when the section appears more
  than once or not at all.

Pages are lists of these blocks (`lock: {move:true, remove:true}` set by the
importer). Editors can't break the layout; they edit text and photos.

### Editor preview & in-place editing

The editor shows the server-rendered HTML (`assets/js/editor/blocks.js`).
In that preview (`mats_is_preview()` is true):

* add `<?php echo mats_edit( 'path' ); ?>` to an element whose text content
  is exactly the value of a text/textarea/list attribute → editable in place.
  Paths: `title`, `cta.label`, `items.2.text`, `marquee.0`.
* images: `mats_image( $img, [ 'class' => …, 'edit' => 'photo' ] )` → click
  to replace. Use `mats_image()` for every attribute image (responsive
  srcset, alt fallback, placeholder in the editor when empty).
* text that is transformed (split into words for `rise-in`, numbered, joined)
  must render plain and marked in preview: see `mats_words()`.
* `.reveal`/`.rise-in` are forced visible in the editor (editor-canvas.css);
  view.js does not run there. Never rely on JS for content to be visible.
* data-driven sections (team, applications…) print `mats_preview_note()`
  with a link to where the data is edited.
* when required content is empty, render `mats_preview_empty( 'Name' )` and
  return (prints nothing on the public site).

### Seed mappers (original copy → block attributes)

For every block, `inc/installer/seed/<block>.php` defines
`mats_seed_attrs_<block_with_underscores>( array $c, string $page, string $lang ): array`
returning the attributes for that section on page role `$page`, built from
`$c` = `inc/data/content.json[$lang]` (keys `home`, `product`,
`applicationsPage`, `researchPage`, `teamPage`, `contact`, `site`, …) plus
`$c['_brand']`, `$c['_pending']`, `$c['_pendingData']`, `$c['_interestMap']`.
Helpers: `mats_seed_link( $label, $astroHref )`, `mats_seed_image(
'tech-detail.jpg', $alt )`. `mats_seed_block( 'page-cta', 'team', 'es' )`
returns the serialized locked block — use it to build your test pages, so
the mapping is verified by your own screenshots. Reference:
`inc/installer/seed/page-cta.php` + `blocks/page-cta/`.

### Links

Buttons store `{label, page, url, query, hash}`; `page` is a role (`home`,
`product`, `applications`, `research`, `team`, `contact`, `news`,
`privacy`, `legal`) or `email`. Use `mats_link_attrs( $link )` inside `<a>`
and `mats_link_label( $link )`. Internal URLs are never hard-coded.
Keep the Astro `data-cta-source` attributes (lead tracking).

### Language

`mats_lang()` → `'en'|'es'` (in editor previews: the language of the page
being edited). Section copy is per page (EN page and ES page are separate
Polylang translations). Small fixed UI words: `mats_t()`. Bilingual CPT
data comes already resolved from the getters.

### Structured content (inc/content.php)

`mats_get_applications()`, `mats_get_team()`, `mats_get_publications()`,
`mats_get_experts()` return arrays shaped like `src/content/types.ts`
(images as `{id,url,alt}` → pass to `mats_image()`), already filtered to
published and ordered like the Astro pages. `mats_has_news()` tells whether
news exist in the current language.

## Local QA environment (shared machine, isolated databases)

* WordPress 7.1.3 on SQLite in `/home/user/wpsite` (theme symlinked from
  this repo — edits are live). Polylang 3.8 active (EN default at `/`, ES at
  `/es/`). WPForms Lite is NOT available offline.
* **Your own instance:** `/home/user/wp-instance.sh <port>` copies the golden
  database (theme active, Polylang configured, team/apps/publications/experts
  and site photos imported) and serves `http://localhost:<port>`. Use only
  the port you were given. `--reset` starts over.
* PHP inside WP: `MATS_WP_PORT=<port> php /home/user/wpsite-cli.php file.php`
  (runs as admin; `mats_import_media( 'product-table.jpg' )` returns the
  attachment ID of a site photo; files live in `assets/media/`).
* Create test pages with `serialize_block()` + `wp_insert_post()`; set the
  language with `pll_set_post_language( $id, 'es' )` and link translations
  with `pll_save_post_translations( [ 'en' => $id1, 'es' => $id2 ] )`.
* Visual reference: the Astro build at `http://127.0.0.1:4321/` (ES:
  `/es/product/` …). Playwright helpers: `wordpress/tools/qa/wp-login.mjs`
  (chromium at `/opt/pw-browsers/chromium-1194/chrome-linux/chrome`,
  `--no-sandbox`). Put QA scripts in `wordpress/tools/qa/`, screenshots in
  the scratchpad. Floating/animated elements: screenshot by coordinates.
* `php -l` every PHP file you touch. Check `/home/user/wpsite-data/debug-<port>.log`.

## Rules

* Never change copy: all text comes from `inc/data/content.json` (the Astro
  content) through the importer. Never invent people, quotes or data.
* The logo/isotype is always the client's original SVG (`assets/brand/`),
  shown as `<img>` (`mats_brand_mark()`), never redrawn.
* Admin-facing text in Spanish, plain words, no jargon.
* Security: escape output, sanitize input, nonces + capability checks on
  every form/AJAX handler, no `eval`, no remote requests except the
  installer's plugin installs from wordpress.org.
* Only edit the files you own (your task lists them). If you need a change
  in a shared core file, describe it in your final report instead.
