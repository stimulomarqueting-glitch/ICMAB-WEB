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
  /** Patent family code pending confirmation by Miquel / Alfonso / Technology
   *  Transfer. While false, the hero shows only "ICMAB-CSIC technology" and
   *  the footer omits the code. */
  patentFamilyConfirmed: false,

  /** Validation & IP copy pending Technology Transfer approval
   *  (see validationItems in src/content/research.ts). */
  validationIpPublished: false,

  /** Team profiles pending from ICMAB ("en camino") —
   *  see teamMembers in src/content/team.ts. */
  teamProfilesPublished: false,

  /** Expert experiences / publications still being gathered —
   *  see src/content/research.ts. */
  researchContentPublished: false,

  /** GDPR consent wording + Privacy Policy + Legal notice pending legal. */
  legalContentConfirmed: false,

  /** matsurfer@icmab.es pending confirmation (Alfonso, then Javier).
   *  The address itself is centralised in src/config/brand.ts. */
  contactEmailConfirmed: false,

  /** Final production domain pending decision (src/config/site.mjs). */
  productionDomainConfirmed: false,
} as const;

/**
 * Internal data kept for when the flags flip. NOT rendered while the
 * corresponding flag is false. Do not replace the code with another one —
 * confirm it first.
 */
export const pendingData = {
  /** Patent family code AS PROVIDED EARLIER — awaiting confirmation. */
  patentFamilyCode: 'EP 23711114.1',
} as const;
