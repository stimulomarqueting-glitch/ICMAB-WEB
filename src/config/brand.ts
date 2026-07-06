/**
 * ─────────────────────────────────────────────────────────────────────────────
 * BRAND CONFIG — single source of truth for naming & brand-level strings.
 *
 * The project name is provisional. To rebrand the whole site (e.g. from
 * "SUBLIMAT" to another name), edit ONLY this file. Every component and every
 * content file interpolates `brand.projectName` instead of hard-coding it.
 * ─────────────────────────────────────────────────────────────────────────────
 */

export const brand = {
  /** Provisional product/brand name. TODO(client): confirm final naming. */
  projectName: 'SUBLIMAT',

  /** Short brand claim shown next to the logo and in meta content (EN). */
  claim: 'Surfaces for all',

  /** Spanish version of the claim. */
  claimEs: 'Superficies para todos',

  /** Institutional origin line, used in hero microcopy, credibility and footer. */
  origin: 'Developed from ICMAB-CSIC research',
  originEs: 'Desarrollado a partir de la investigación del ICMAB-CSIC',

  /**
   * Contact email placeholder.
   * TODO(client): replace with the real project inbox.
   */
  contactEmail: 'hello@sublimat.example',

  /**
   * Waitlist form endpoint.
   * Leave EMPTY ('') while there is no backend: the form will run in demo mode
   * (validates, shows the success state, logs the payload to the console).
   *
   * To go live, point it at Formspree / Netlify Forms / HubSpot / Airtable or a
   * custom endpoint that accepts a JSON POST, e.g.:
   *   formEndpoint: 'https://formspree.io/f/XXXXXXXX'
   */
  formEndpoint: '',
} as const;

export type Brand = typeof brand;
