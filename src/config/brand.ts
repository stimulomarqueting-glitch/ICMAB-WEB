/**
 * ─────────────────────────────────────────────────────────────────────────────
 * BRAND CONFIG — single source of truth for naming & brand-level strings.
 *
 * To rebrand the whole site, edit ONLY this file. Every component and every
 * content file interpolates `brand.projectName` instead of hard-coding it.
 * ─────────────────────────────────────────────────────────────────────────────
 */

export const brand = {
  /** Product/brand name (v2 branding — "Coral Vapor" identity). */
  projectName: 'The MatSurfer',

  /** Short brand claim shown next to the logo and in meta content (EN). */
  claim: 'Surfaces for all.',

  /** Spanish version of the claim. */
  claimEs: 'Superficies para todos.',

  /** Institutional origin line, used in hero microcopy, credibility and footer. */
  origin: 'Developed at ICMAB-CSIC',
  originEs: 'Desarrollado en el ICMAB-CSIC',

  /**
   * Contact email (from the approved design).
   * TODO(client): confirm the inbox is live before launch.
   */
  contactEmail: 'matsurfer@icmab.es',
} as const;

// NOTE: lead-capture settings (demo/production mode, form endpoint, privacy
// URL, lead magnet) live in src/config/leadCapture.ts.

export type Brand = typeof brand;
