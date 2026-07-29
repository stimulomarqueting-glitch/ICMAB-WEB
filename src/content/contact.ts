import { brand } from '../config/brand';

/**
 * /contact copy + light lead form definition.
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
    lead: 'Write to us to explore an application, request technical information, discuss a collaboration, take part in validations or talk about distribution. We answer every message.',
  },

  form: {
    fullName: { label: 'Name', error: 'Please enter your name.' },
    email: { label: 'Work email', error: 'Please enter a valid email address.' },
    organization: { label: 'Organisation', error: 'Please enter your organisation.' },
    role: { label: 'Role' },
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
    message: {
      label: 'Message or research context',
      placeholder: 'Material, substrate, and what you want to learn from it',
      error: 'Please tell us briefly what you would like to discuss.',
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
    successTitle: 'Message received',
    successText: 'Thank you — we have received your message and will come back to you as soon as possible.',
    /** Ends mid-sentence; the contact email link is appended in markup. */
    errorText: 'Something went wrong while sending your message. Please try again, or write to us directly at',
  },

  aside: {
    title: 'What happens next',
    steps: [
      'We read your message and your research context.',
      'The right person on the team comes back to you.',
      'If there is fit, we plan the next step together.',
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
