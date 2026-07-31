import { brand } from '../config/brand';

/**
 * /contact copy + lead form definition.
 * Editorial line (ICMAB review): prudent early-access wording — no
 * "allocated by fit / in order of application", no promised priority
 * access. Role is required; area of interest qualifies the enquiry.
 * The interest select accepts preselection via URL params — see LeadForm:
 *   /contact?interest=product | application | research | partnership | distribution
 */

const name = brand.projectName;

export const contact = {
  meta: {
    title: `Contact — ${name} · Talk to the Team`,
    description: `Talk to the ${name} team: explore an application, request technical information, discuss a scientific collaboration, join a validation or talk about distribution.`,
  },

  hero: {
    kicker: 'Contact',
    title: 'Talk to the team',
    lead: `Tell us about your laboratory, your research needs and how you are considering using ${name}. We will review your enquiry and contact you as early-access opportunities become available.`,
  },

  form: {
    fullName: { label: 'Name', error: 'Please enter your name.' },
    email: { label: 'Work email', error: 'Please enter a valid email address.' },
    organization: { label: 'Organisation', error: 'Please enter your organisation.' },
    role: { label: 'Role', error: 'Please enter your role.' },
    interest: {
      label: 'I am interested in',
      error: 'Please select what you are interested in.',
      options: [
        { value: 'product-information', label: 'Product information' },
        { value: 'research-application', label: 'Research application' },
        { value: 'scientific-collaboration', label: 'Scientific collaboration' },
        { value: 'validation', label: 'Validation' },
        { value: 'distribution', label: 'Distribution' },
        { value: 'other', label: 'Other' },
      ],
    },
    areaOfInterest: {
      label: 'Area of interest',
      options: [
        { value: 'organic-electronics', label: 'Organic electronics, including OLEDs' },
        { value: 'solar-cells', label: 'Solar cells — perovskite and organic' },
        { value: 'sensors', label: 'Chemical and biosensors' },
        { value: 'coatings', label: 'Functional coatings' },
        { value: 'spm-sample-preparation', label: 'SPM sample preparation' },
        { value: 'new-combinations', label: 'New material–surface combinations' },
        { value: 'other', label: 'Other' },
      ],
    },
    message: {
      label: `What would you like to explore with ${name}?`,
      placeholder: 'Tell us about your material, substrate, current workflow or research challenge',
      error: 'Please tell us briefly what you would like to explore.',
    },
    consent: {
      // TODO(client/legal): validate final consent wording with legal counsel.
      labelBefore: `I agree to be contacted about ${name} and accept the`,
      privacyLabel: 'Privacy Policy',
      labelAfter: '.',
      error: 'Please accept the Privacy Policy to continue.',
    },
    optionalTag: '(optional)',
    selectPlaceholder: 'Select…',
    submit: 'Send message',
    submitting: 'Sending…',
    successTitle: 'Enquiry received',
    successText: `Thank you — we have received your enquiry. The ${name} team will review the information and contact you as the early-access programme develops.`,
    /** Ends mid-sentence; the contact email link is appended in markup. */
    errorText: 'Something went wrong while sending your message. Please try again, or write to us directly at',
  },

  aside: {
    title: 'What happens next',
    steps: [
      'We review your research context and intended application.',
      'We contact you to discuss potential fit and availability.',
      'Suitable projects may be invited to future pilot or early-access opportunities.',
    ],
    directTitle: 'Prefer email?',
    directText: 'You can also write directly to',
  },
} as const;

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
