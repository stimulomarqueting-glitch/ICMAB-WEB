/**
 * ─────────────────────────────────────────────────────────────────────────────
 * PENDING CONTENT — single switchboard for everything awaiting confirmation
 * by ICMAB, Technology Transfer or legal. Nothing gated here renders
 * publicly, and no "coming soon" placeholder is shown in its place.
 *
 * Flip a flag to true ONLY when the corresponding content is confirmed.
 * ─────────────────────────────────────────────────────────────────────────────
 */

export const pendingContent = {
  /** Patent code CONFIRMED by the Technology Transfer office (Coral,
   *  2025-09): European patent EP 4483 979 B1. */
  patentFamilyConfirmed: true,

  /** Validation & IP copy APPROVED — "IP & Technological Validation"
   *  (see validationItems in src/content/research.ts). */
  validationIpPublished: true,

  /** Team profiles CONFIRMED by ICMAB (Material_Web, 2026-09-16): four
   *  approved bios live, three portraits mapped and confirmed. Núria's
   *  portrait is still to be supplied — src/content/team.ts. */
  teamProfilesPublished: true,

  /** Publications LIVE (two open-access papers) and the first expert
   *  evaluation published; further experiences and papers still being
   *  gathered — src/content/research.ts. */
  researchContentPublished: true,

  /** GDPR consent wording + Privacy Policy + Legal notice pending legal. */
  legalContentConfirmed: false,

  /** matsurfer@icmab.es pending confirmation (Alfonso, then Javier).
   *  The address itself is centralised in src/config/brand.ts. */
  contactEmailConfirmed: false,

  /** Final production domain pending decision (src/config/site.mjs). */
  productionDomainConfirmed: false,
} as const;

/**
 * Data referenced by the flags above.
 */
export const pendingData = {
  /** European patent code confirmed by the Technology Transfer office. */
  patentFamilyCode: 'EP 4483 979 B1',
} as const;
