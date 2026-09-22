# MatSurfer — Website

Multi-page website for **MatSurfer**, an ICMAB-CSIC technology-transfer
project: a compact device for direct material deposition on surfaces by
sublimation, at lab scale. Visual identity: Design System v3 (5-phase
sublimation gradient, Sora / Manrope / IBM Plex Mono, Obviously Variable
for the wordmark only).

Built with [Astro](https://astro.build) — fully static output, no
client-side framework, minimal JavaScript (mobile menu, form controller,
accordion, scroll reveal).

## Architecture

| Route | Purpose | Final CTA |
|---|---|---|
| `/` | Strategic introduction (8 short blocks, teasers into every page) | Talk to the team → `/contact` |
| `/product` | Full product/technology explanation: differentiator (controllable source–substrate distance), how it works, parameters, benefits, FAQ | Discuss your research → `/contact?interest=product` |
| `/applications` | Six confirmed use cases (need → with MatSurfer → benefit), each with an anchor and contextual CTA | Discuss this application → `/contact?interest=application&application=<slug>` |
| `/research` | Expert experiences, publications, Validation & IP (renders only published entries) | Explore a collaboration → `/contact?interest=research` |
| `/team` | Full-viewport team showcase (published profiles only) + "from the lab to the bench" | Talk to the team → `/contact?interest=partnership` |
| `/contact` | Conversion page: light lead form with interest preselection | — |
| `/es`, `/es/product`, … | The same six pages in Spanish (same slugs, `/es` prefix) | same CTAs, prefixed |

Global header CTA (all pages): **Talk to the team** → `/contact`.

### Languages

English lives at the root and Spanish under `/es`, with identical slugs.
One template per page — `src/pages/[...lang]/*.astro` — builds both
versions; the locale is read from the URL (`src/i18n/index.ts`). Content
hrefs are locale-neutral (`'/product'`); components prefix them at render
time with `localePath`, so translated copy never has to remember `/es`.
`<html lang>`, canonical, `hreflang` (en / es / x-default), `og:locale`, the
JSON-LD breadcrumb and the sitemap are all per-locale; the header carries
an EN / ES switcher that keeps you on the same page.

## Quick start

```bash
npm install       # install dependencies
npm run dev       # dev server at http://localhost:4321
npm run build     # production build → dist/
npm run preview   # preview the production build locally
```

## Deploy to staging (Hostinger)

1. `npm run build`
2. Zip the **contents** of `dist/` (so `index.html` sits at the zip root)
   and add a staging `.htaccess` with `X-Robots-Tag: noindex, nofollow`.
3. Upload to `public_html/`, extract into the forced temp folder, select all
   → Move to `public_html/` with "Overwrite existing files".
4. Delete first the previous `_astro`, `es`, `img`, `video`, `fonts`,
   `product`, `applications`, `research`, `team`, `contact` folders to avoid
   Hostinger's numbered-duplicate rename on folder conflicts.
5. Re-enable hPanel password protection if it was on (the `.htaccess`
   overwrite removes it).

## Where to edit things

| What | Where |
|---|---|
| **Brand name / claim / contact email** | `src/config/brand.ts` |
| Home hero variant (`field` animated sublimation field / `photo` duotone photo) | `src/config/hero.ts` |
| **Lead capture (demo/production, endpoint, privacy URL)** | `src/config/leadCapture.ts` — see below |
| Final production domain | `src/config/site.mjs` + `public/robots.txt` |
| Navigation, footer, default SEO, a11y labels | `src/content/{en,es}/site.ts` |
| Home copy | `src/content/{en,es}/home.ts` |
| Product copy (differentiator, steps, parameters, FAQ) | `src/content/{en,es}/product.ts` |
| Applications data (one entry per use case) | `src/content/en/applications.ts` (data) + `es/applications.ts` (texts by slug) |
| Research data (experiences, publications, validation) | `src/content/en/research.ts` (data) + `es/research.ts` (texts) |
| Team data (profiles + groups) | `src/content/en/team.ts` (data) + `es/team.ts` (roles + bios by name) |
| Team showcase portraits (cut-outs with transparent background) | `public/img/team/showcase/<slug>.webp`, referenced by `showcaseImage` in `en/team.ts` — see below |
| Contact copy + form labels | `src/content/{en,es}/contact.ts` |
| Interest URL mapping (shared by both languages) | `src/content/shared.ts` |
| Shared content types | `src/content/types.ts` |
| Design tokens | `src/styles/global.css` (`:root` block) |
| SEO head (meta, OG, JSON-LD, breadcrumbs) | `src/layouts/BaseLayout.astro` |

**Editing copy in two languages.** The English files define each page's
shape and export its type (`HomeContent`, `ProductContent`, …); the Spanish
files are typed against them, so adding a key to English without its
Spanish counterpart fails `astro check`. Lists with non-translatable data
(slugs, DOIs, photos, LinkedIn URLs, order, `published`) live once in
`en/` — the Spanish file imports them and overrides only the texts, and
throws at build time if an entry has no translation.

### Home hero variants

`src/config/hero.ts` picks the home hero. `HeroField.astro` (default) puts the
product photo on a plate that tilts in 3D with the pointer — layers at
different depths (violet halo and shadow behind, the photo, a sheen sweeping
the glass, the vapour in front) — while particles rise out of the chamber
in the photo, cooling from coral to violet. The headline rises word by word
and a marquee of application fields runs along the bottom edge. It pauses
off-screen and when the tab is hidden, renders a single still (no tilt, no
float) under `prefers-reduced-motion`, and needs no JS for the copy to
show. `Hero.astro` is the previous full-bleed duotone photo. Both read
`home.hero`; the marquee items live in `home.hero.field.marquee`.

### Motion language on interior pages

Interior pages share the home's vocabulary without touching copy:
`PageHero` rises word by word and puts a photo on a tilting plate;
`/applications` has a sticky scroll-spy bar of the five applications and
closes on a plum glass band; `/research` shows the IP & validation copy on
a plum credibility band; `/contact` draws its three steps as a timeline;
the FAQ accordion animates its height. Reveal edges, hover lifts and the
"deposited layer" line recur everywhere. Every effect is motion-safe.

### Team showcase (top of `/team`)

`src/components/TeamShowcase.astro` shows one profile at a time over a plum
studio backdrop, with the page headline on the left, the active bio on the
right and an avatar picker along the bottom. It uses two images per person:

- `image` — the square 800×800 portrait (avatar thumbnail);
- `showcaseImage` — the same person cut out from the background, as a WebP
  with alpha, up to 1600px tall, anchored to the bottom edge. Produce it
  with any portrait matting tool (the current ones were made locally with
  `rembg` and the BiRefNet portrait model, then trimmed to the opaque
  bounding box). A profile without `showcaseImage` renders a monogram
  stand-in, and the showcase opens on the first profile that has one.

The showcase rotates every 5.5 s (`data-interval` on the section), pauses
while hovered or focused, when scrolled out of view or the tab is hidden,
restarts after a manual pick, and does not autoplay under
`prefers-reduced-motion`. Under it, `TeamJourney.astro` renders the
"from the lab to the bench" section from `teamPage.journey` — draft copy,
marked as such in the content file, to be rewritten by the client.

### Publish-gated content (no fillers, no "coming soon")

- **Validation & IP** — `validationItems` in `src/content/research.ts`,
  `published: false` until ICMAB Technology Transfer approves the copy.
- **Team profiles** — `teamMembers` in `src/content/team.ts` (empty until
  confirmed; never invent bios/photos).
- **Expert experiences / publications** — `src/content/research.ts` (empty
  until confirmed; never invent DOIs/quotes).

## Lead capture setup

All behaviour is centralized in **`src/config/leadCapture.ts`**.

| Mode | Behaviour |
|---|---|
| `mode: 'demo'` (current) | Validates locally, logs the payload to the browser console, shows the success state. **Nothing is sent anywhere.** |
| `mode: 'production'` | POSTs the JSON payload below to `formEndpoint`. |

To go live, edit `src/config/leadCapture.ts` only:

```ts
mode: 'production',
formEndpoint: 'https://formspree.io/f/XXXXXXXX',
```

Safety net: production mode with an empty endpoint logs a clear warning and
falls back to demo behaviour — the site never breaks, and no lead is ever
"pretend-saved" silently: demo mode prints the full payload to the console.

Any service that accepts a **JSON POST** works: Formspree, a Make/Zapier
webhook (→ Sheets/Airtable/HubSpot/email), or a small serverless function
wrapping a CRM API (keep API keys server-side).

### Payload sent (keep in sync with `buildPayload()` in `LeadForm.astro`)

```json
{
  "project": "MatSurfer",
  "language": "en | es",
  "sourcePage": "/contact | /es/contact",
  "ctaSource": "header | hero | home-product | home-applications | home-research | home-team | home-final | product-hero | product-final | applications-<slug> | applications-final | research-final | team-final | footer | direct",
  "interest": "product-information | research-application | scientific-collaboration | validation | distribution | other",
  "areaOfInterest": "organic-electronics | solar-cells | sensors | coatings | spm-sample-preparation | new-combinations | other | ''",
  "application": "<slug> | ''",
  "fullName": "…",
  "email": "…",
  "organization": "…",
  "role": "…",
  "message": "…",
  "consent": true,
  "utm": { "source": "", "medium": "", "campaign": "", "content": "", "term": "" },
  "submittedAt": "ISO 8601 timestamp"
}
```

Required: name, work email, organisation, role, interest, message, consent.
Area of interest is optional (and preselected when arriving from an
application card). A hidden honeypot field ("website") silently drops bot
submissions.

### Conversion context in URLs

Internal links preserve intent with short params, mapped to form values in
`src/content/contact.ts` (`interestParamMap`):

```
/contact?interest=product        → Product information
/contact?interest=application    → Research application
/contact?interest=research       → Scientific collaboration
/contact?interest=partnership    → Scientific collaboration
/contact?interest=distribution   → Distribution
/contact?interest=application&application=oleds   (application slug captured in the payload)
```

### CTA source tracking & conversion events

- Every CTA carries `data-cta-source="…"`; the last clicked source is stored
  per session and attached to the payload.
- UTM params are captured on landing (first value per session wins).
- Events (`cta_click`, `form_start`, `form_submit_attempt`,
  `form_submit_success`, `form_submit_error`) flow through `trackEvent()` in
  `src/scripts/leadTracking.ts` — connect Plausible/GA4/GTM there.

## Pending on the client

All confirmation-gated content is centralised in
**`src/config/pendingContent.ts`** — flip a flag only when the
corresponding content is confirmed. Nothing gated renders publicly and no
"coming soon" placeholder appears in its place.

- `patentFamilyConfirmed` — patent family code (Miquel / Alfonso /
  Technology Transfer). While false, the hero shows "ICMAB-CSIC technology"
  and the footer omits the code; the provided code is kept internally in
  `pendingData`.
- `validationIpPublished` — Validation & IP copy (Technology Transfer).
- `teamProfilesPublished` — team profiles ("en camino" from ICMAB).
- `researchContentPublished` — expert experiences and publications
  (still being gathered).
- `legalContentConfirmed` — GDPR consent wording + Privacy Policy +
  Legal notice.
- `contactEmailConfirmed` — `matsurfer@icmab.es` (centralised in
  `src/config/brand.ts`; do not duplicate it in components).
- `productionDomainConfirmed` — final domain (`src/config/site.mjs`,
  `public/robots.txt`).
- Form endpoint: demo mode until `formEndpoint` is set in
  `src/config/leadCapture.ts`.
