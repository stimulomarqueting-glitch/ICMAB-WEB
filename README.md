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
| `/team` | Confirmed profiles, grouped; published-only | Talk to the team → `/contact?interest=partnership` |
| `/contact` | Conversion page: light lead form with interest preselection | — |
| `/es` | Redirects to `/` (site is EN-only) | — |

Global header CTA (all pages): **Talk to the team** → `/contact`.

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
| **Lead capture (demo/production, endpoint, privacy URL)** | `src/config/leadCapture.ts` — see below |
| Final production domain | `src/config/site.mjs` + `public/robots.txt` |
| Navigation, footer, default SEO | `src/content/site.ts` |
| Home copy | `src/content/home.ts` |
| Product copy (differentiator, steps, parameters, FAQ) | `src/content/product.ts` |
| Applications data (one entry per use case) | `src/content/applications.ts` |
| Research data (experiences, publications, validation) | `src/content/research.ts` |
| Team data (profiles + groups) | `src/content/team.ts` |
| Contact copy + form labels + interest URL mapping | `src/content/contact.ts` |
| Design tokens | `src/styles/global.css` (`:root` block) |
| SEO head (meta, OG, JSON-LD, breadcrumbs) | `src/layouts/BaseLayout.astro` |

The site ships **English only** (`lang="en"`, no hreflang, `/es` → `/`).
The old Spanish content lives in git history (`src/content/es.ts`, removed
in the multi-page restructure).

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
  "sourcePage": "/contact",
  "ctaSource": "header | hero | home-product | home-applications | home-research | home-team | home-final | product-hero | product-final | applications-<slug> | applications-final | research-final | team-final | footer | direct",
  "interest": "product-information | research-application | scientific-collaboration | validation | distribution | other",
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

Required: name, work email, organisation, interest, message, consent.
Role is optional. A hidden honeypot field ("website") silently drops bot
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

## Pending on the client (marked `TODO(client)` in code)

- Form endpoint (demo mode until then) + privacy policy & legal notice pages.
- Final consent wording (legal).
- Validation & IP copy sign-off (Technology Transfer) → flip `published`.
- Team profiles, expert experiences, publications (confirmed data only).
- Final production domain (`src/config/site.mjs`, `public/robots.txt`).
- Confirm the `matsurfer@icmab.es` inbox is live.
