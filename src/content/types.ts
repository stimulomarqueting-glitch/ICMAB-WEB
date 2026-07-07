/**
 * Shared shape for all locale content files (en.ts / es.ts).
 * Adding a new language = create a new file implementing `SiteContent`
 * and register it in src/content/index.ts.
 */

export interface NavItem {
  label: string;
  href: string;
}

export interface Stat {
  value: string;
  label: string;
}

export interface Card {
  title: string;
  text: string;
  /** key used to pick an inline SVG icon in the component layer */
  icon?: string;
}

export interface Step {
  title: string;
  text: string;
}

export interface SelectOption {
  value: string;
  label: string;
}

export interface FormField {
  label: string;
  placeholder?: string;
  error?: string;
}

export interface SiteContent {
  meta: {
    title: string;
    description: string;
    ogAlt: string;
  };
  header: {
    nav: NavItem[];
    cta: string;
    menuOpen: string;
    menuClose: string;
    langLabel: string;
  };
  hero: {
    eyebrow: string;
    headline: string;
    headlineAccent: string;
    subheadline: string;
    ctaPrimary: string;
    ctaSecondary: string;
    microcopy: string;
    stats: Stat[];
    visualCaption: string;
    visualAlt: string;
  };
  problem: {
    kicker: string;
    title: string;
    intro: string;
    points: Card[];
    bridge: string;
  };
  technology: {
    kicker: string;
    title: string;
    intro: string;
    whatIsIt: { title: string; text: string };
    steps: Step[];
    stepsTitle: string;
    features: Card[];
    featuresTitle: string;
    schematicAlt: string;
    schematicNote: string;
  };
  benefits: {
    kicker: string;
    title: string;
    intro: string;
    items: Card[];
  };
  applications: {
    kicker: string;
    title: string;
    intro: string;
    /** rendered as "Surfaces for {name}" cards */
    surfacesForLabel: string;
    items: { name: string; short: string; text: string }[];
    openEnded: string;
  };
  audiences: {
    kicker: string;
    title: string;
    intro: string;
    groups: Card[];
    quote: string;
  };
  credibility: {
    kicker: string;
    title: string;
    intro: string;
    facts: Card[];
    logosTitle: string;
    logoPlaceholders: string[];
    phaseNote: string;
  };
  /** Contextual conversion CTAs distributed across the page (all → #waitlist). */
  conversion: {
    technology: { title: string; text: string; button: string };
    applications: { button: string };
    audiences: { button: string };
    footer: { button: string };
  };
  waitlist: {
    kicker: string;
    title: string;
    intro: string;
    /** Why leaving your data is worth it (early access, pilots, tech talk…). */
    reasons: string[];
    form: {
      /** numbered fieldset group titles of the "application dossier" */
      groups: { about: string; organisation: string; project: string };
      fullName: FormField;
      email: FormField;
      organization: FormField;
      role: FormField;
      country: FormField;
      organizationType: FormField & { options: SelectOption[] };
      areaOfInterest: FormField & { options: SelectOption[] };
      currentNeed: FormField & { options: SelectOption[] };
      timeline: FormField & { options: SelectOption[] };
      message: FormField;
      /** Consent sentence is split so the privacy-policy link can be injected. */
      consent: { labelBefore: string; privacyLabel: string; labelAfter: string; error: string };
      submit: string;
      submitting: string;
      noSpam: string;
      successTitle: string;
      successText: string;
      /** Ends mid-sentence; the contact email link is appended in markup. */
      errorText: string;
      requiredHint: string;
    };
    /** Optional download block, rendered only when leadCapture.leadMagnet.enabled. */
    leadMagnet: { title: string; text: string; button: string };
  };
  footer: {
    description: string;
    navTitle: string;
    nav: NavItem[];
    contactTitle: string;
    contactText: string;
    legalTitle: string;
    legal: NavItem[];
    languageTitle: string;
    copyright: string;
  };
}
