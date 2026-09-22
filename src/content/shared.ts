/**
 * Locale-independent data used by client scripts and both languages.
 */

/**
 * Map short URL values (used in internal links) to form option values.
 *   /contact?interest=product      → Product information
 *   /contact?interest=application  → Research application
 *   /contact?interest=research     → Scientific collaboration
 *   /contact?interest=partnership  → Scientific collaboration
 *   /contact?interest=distribution → Distribution
 * A full option value (e.g. ?interest=validation) is also accepted as-is.
 */
export const interestParamMap: Record<string, string> = {
  product: 'product-information',
  application: 'research-application',
  research: 'scientific-collaboration',
  partnership: 'scientific-collaboration',
  distribution: 'distribution',
  validation: 'validation',
};
