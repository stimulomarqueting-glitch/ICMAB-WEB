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
} as const;

// NOTE: lead-capture settings (demo/production mode, form endpoint, privacy
// URL, lead magnet) live in src/config/leadCapture.ts.

export type Brand = typeof brand;
