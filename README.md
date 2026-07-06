# SUBLIMAT — Landing Page

First iteration of the website for **SUBLIMAT** (provisional name), an ICMAB-CSIC
technology-transfer project: a family of compact devices for direct material
deposition on surfaces.

Built with [Astro](https://astro.build) — fully static output, no client-side
framework, minimal JavaScript (mobile menu, form validation, scroll reveal).

## Quick start

```bash
npm install       # install dependencies
npm run dev       # dev server at http://localhost:4321
npm run build     # production build → dist/
npm run preview   # preview the production build locally
```

Deploy: the `dist/` folder is plain static files — works on Netlify, Vercel,
Cloudflare Pages, GitHub Pages or any static host.

## Where to edit things

| What | Where |
|---|---|
| **Brand name / claim / CTAs / contact email / form endpoint** | `src/config/brand.ts` — change `projectName` here and the whole site rebrands |
| Final production domain | `src/config/site.mjs` (used by SEO tags, sitemap, robots) + `public/robots.txt` |
| **All copy (EN)** | `src/content/en.ts` |
| **All copy (ES)** | `src/content/es.ts` |
| Content shape / new languages | `src/content/types.ts` + register in `src/content/index.ts` |
| Design tokens (colors, fonts, spacing) | `src/styles/global.css` (`:root` block) |
| Sections / layout | `src/components/*.astro`, composed in `src/components/Landing.astro` |
| SEO head (meta, OG, hreflang, JSON-LD) | `src/layouts/BaseLayout.astro` |

## Languages

- English (default) → `/`
- Spanish → `/es/`

Both locales are fully translated. `hreflang` alternates and a localized
sitemap are generated automatically. To add a language: create
`src/content/<lang>.ts`, register it in `src/content/index.ts`, add
`src/pages/<lang>/index.astro`, and add the locale in `astro.config.mjs`.

## Waitlist form

The form is fully functional on the frontend (validation, error/success
states, accessible labels). It currently runs in **demo mode** — it does not
send data anywhere until an endpoint is configured.

To connect it: set `formEndpoint` in `src/config/brand.ts` to a Formspree /
Netlify Forms / HubSpot / Airtable / custom URL that accepts a JSON POST.
The submission logic lives in `src/components/Waitlist.astro` (bottom
`<script>` block).

## Placeholders to replace (marked with `TODO(client)` in code)

- **Device visuals** — `src/components/DepositionVisual.astro` is a generative
  SVG placeholder; swap for real device photos/renders when available.
- **Institution logos** — text placeholders (ICMAB / CSIC / FGCSIC) in
  `src/components/Credibility.astro`, pending institutional approval.
- **Contact email** — `hello@sublimat.example` in `src/config/brand.ts`.
- **Form endpoint** — empty (demo mode) in `src/config/brand.ts`.
- **Domain** — `https://sublimat.icmab.es` assumed in `src/config/site.mjs`
  and `public/robots.txt`.
- **OG image** — `public/og-image.png` is a generated placeholder (1200×630);
  replace with a branded image when final identity exists.
- **Privacy policy / legal notice** — footer links point to `#`.

## Naming is provisional

"SUBLIMAT" and the claim "Surfaces for all" are provisional. Everything is
centralized in `src/config/brand.ts`; the content files interpolate the name,
so a rebrand is a one-file change (plus regenerating `public/og-image.png`
and `public/favicon.svg`).

## Next iteration — suggested

- Connect the form to a real endpoint + privacy policy page (GDPR consent
  text review).
- Real device photography/renders and the technical schematic.
- Official logos and institutional sign-off on credibility claims.
- Optional pages: About/team, News, FAQ, downloadable one-pager (PDF).
- Analytics (privacy-friendly, e.g. Plausible) once a domain exists.
- Final naming decision → update `brand.ts`, favicon, OG image.
