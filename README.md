# MatSurfer — Landing Page

Website for **MatSurfer** (formerly SUBLIMAT), an ICMAB-CSIC
technology-transfer project: a compact device for direct material deposition
on surfaces by sublimation, at lab scale. Visual identity: "Coral Vapor"
design system (Sora / Manrope / IBM Plex Mono, coral–violet–lavender on warm
white).

Built with [Astro](https://astro.build) — fully static output, no client-side
framework, minimal JavaScript (mobile menu, form validation, scroll reveal).

## Quick start

```bash
npm install       # install dependencies
npm run dev       # dev server at http://localhost:4321
npm run build     # production build → dist/
npm run preview   # preview the production build locally
```

## Deploy to staging

The site is fully static — no server, no environment variables needed.

1. `npm install && npm run build`
2. Upload the **entire contents of the `dist/` folder** to any static host
   (Netlify, Vercel, Cloudflare Pages, GitHub Pages, or a plain web server).
   - Netlify (drag & drop or CLI): publish directory = `dist`
   - Vercel: framework preset "Astro", output = `dist`
   - Plain server: copy `dist/*` to the web root
3. `/es` redirects to `/` (this first deliverable is EN-only; a static
   meta-refresh page is generated at `dist/es/index.html`).

Staging notes: canonical URLs point to the placeholder production domain
(`src/config/site.mjs`), which prevents a test URL from competing in search.
If the staging host allows it, also send an `X-Robots-Tag: noindex` header
until launch.

## Where to edit things

| What | Where |
|---|---|
| **Brand name / claim / contact email** | `src/config/brand.ts` — change `projectName` here and the whole site rebrands |
| **Lead capture (demo/production, endpoint, privacy URL, lead magnet)** | `src/config/leadCapture.ts` — see "Lead capture setup" below |
| Final production domain | `src/config/site.mjs` (used by SEO tags, sitemap, robots) + `public/robots.txt` |
| **All copy (EN)** | `src/content/en.ts` |
| **All copy (ES)** | `src/content/es.ts` |
| Content shape / new languages | `src/content/types.ts` + register in `src/content/index.ts` |
| Design tokens (colors, fonts, spacing) | `src/styles/global.css` (`:root` block) |
| Sections / layout | `src/components/*.astro`, composed in `src/components/Landing.astro` |
| SEO head (meta, OG, hreflang, JSON-LD) | `src/layouts/BaseLayout.astro` |

## Languages

**This first deliverable ships English only** (`/`). `/es` redirects to `/`.

The Spanish translation is fully maintained in `src/content/es.ts` — to
re-enable it: restore `src/pages/es/index.astro` (see git history), re-add
the `i18n` block and hreflang tags in `astro.config.mjs` /
`BaseLayout.astro`, and bring back the language switcher in Header/Footer.

## Lead capture setup

All lead-capture behaviour is centralized in **`src/config/leadCapture.ts`**.

### Demo vs production mode

| Mode | Behaviour |
|---|---|
| `mode: 'demo'` (current) | Validates locally, logs the payload to the browser console, shows the success state. **Nothing is sent anywhere.** |
| `mode: 'production'` | POSTs the JSON payload below to `formEndpoint`. |

To go live, edit `src/config/leadCapture.ts` only:

```ts
mode: 'production',
formEndpoint: 'https://formspree.io/f/XXXXXXXX',
```

Safety net: if `mode` is `'production'` but `formEndpoint` is empty, the
build/dev console and the browser console print a clear warning and the form
falls back to demo behaviour — the site never breaks.

### Connecting an endpoint

Any service that accepts a **JSON POST** works:

- **Formspree** — create a form, use `https://formspree.io/f/<id>`. Accepts
  JSON out of the box; the nested `utm` object arrives as a JSON string field.
- **Make / Zapier webhook** — create a custom webhook trigger, paste its URL.
  From there route leads to Google Sheets, Airtable, HubSpot, email, Slack…
  This is the most flexible option while the CRM is undecided.
- **Airtable / HubSpot direct** — both expect their own request format, so
  wrap them in a tiny serverless function (Netlify/Vercel function) that maps
  the payload fields and holds the API key server-side. Never put API keys in
  this frontend.
- **Custom endpoint** — anything that accepts the payload below and returns
  a 2xx status.
- **Netlify Forms** — note: it does **not** accept JSON; it needs
  form-encoded posts with a `form-name` field and a build-time-detectable
  form. If you choose Netlify Forms, adapt the `fetch` call in
  `src/components/Waitlist.astro` accordingly.

### Payload sent (keep in sync with `buildPayload()` in `Waitlist.astro`)

```json
{
  "project": "MatSurfer",
  "language": "en | es",
  "sourcePage": "/ | /es/",
  "ctaSource": "hero | header | technology | applications | audiences | footer | lead-magnet | direct",
  "fullName": "…",
  "email": "…",
  "organization": "…",
  "role": "…",
  "country": "country name (English value in both locales)",
  "organizationType": "academic-research-group | public-research-centre | corporate-rd | testing-services | distributor | tech-transfer | other",
  "areaOfInterest": "oleds | solar-cells | sensors | organic-electronics | coatings | new-surface-materials | other",
  "currentNeed": "exploring | evaluating-project | active-pilot | distribution-partnership | ''",
  "timeline": "within-3-months | 3-6-months | 6-12-months | beyond-12-months | following | ''",
  "message": "…",
  "consent": true,
  "utm": { "source": "", "medium": "", "campaign": "", "content": "", "term": "" },
  "submittedAt": "ISO 8601 timestamp"
}
```

Required fields: full name, work email, organisation, country, organisation
type, area of interest, consent. Role, current need, timeline and message are
optional (labelled as such) to keep friction low; they still feed lead
qualification when filled.

### CTA source tracking & conversion events

- Every CTA that points to the form carries `data-cta-source="…"`. A tiny
  delegated listener (`src/scripts/leadTracking.ts`) stores the last clicked
  source in `sessionStorage` and it is attached to the lead payload — so you
  know which section converts.
- UTM params (`utm_source`, `utm_medium`, `utm_campaign`, `utm_content`,
  `utm_term`) are captured on landing (first value per session wins) and
  attached to the payload.
- Conversion events (`cta_click`, `form_start`, `form_submit_attempt`,
  `form_submit_success`, `form_submit_error`) all flow through
  `trackEvent()` in `src/scripts/leadTracking.ts`. It is currently a
  no-op-friendly wrapper (DOM CustomEvent + dev-only console.debug). To
  connect Plausible / GA4 / GTM later, add one line inside `trackEvent()` —
  every event is already instrumented.

### GDPR / privacy checklist

Already in place: consent checkbox (unchecked by default, required), clear
purpose wording ("data will be used to respond to my request"), privacy
policy link, no tracking cookies, no third-party scripts.

Still needed before real launch — **TODO(client/legal)**:

- Real privacy policy page (`privacyUrl` in `leadCapture.ts` points to `#`).
- Final consent wording validated by legal (marked in `en.ts` / `es.ts`).
- Data-processing details for the chosen endpoint (where leads are stored,
  who accesses them, retention period).

### Lead magnet (optional block)

A "download the one-pager" block is built into the waitlist section but
disabled. When a validated PDF exists, set in `leadCapture.ts`:

```ts
leadMagnet: { enabled: true, url: '/downloads/sublimat-onepager.pdf' },
```

Copy lives in `waitlist.leadMagnet` in the content files.

## Placeholders to replace (marked with `TODO(client)` in code)

- **Device visuals** — `src/components/DepositionVisual.astro` is a generative
  SVG placeholder; swap for real device photos/renders when available.
- **Institution logos** — text placeholders (ICMAB / CSIC / FGCSIC) in
  `src/components/Credibility.astro`, pending institutional approval.
- **Contact email** — `hello@sublimat.example` in `src/config/brand.ts`.
- **Form endpoint** — empty (demo mode) in `src/config/leadCapture.ts`.
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
