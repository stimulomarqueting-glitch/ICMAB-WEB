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
  waitlist: {
    kicker: string;
    title: string;
    intro: string;
    form: {
      name: FormField;
      organization: FormField;
      email: FormField;
      role: FormField;
      country: FormField;
      profile: FormField & { options: SelectOption[] };
      application: FormField & { options: SelectOption[] };
      message: FormField;
      consent: { label: string; error: string };
      submit: string;
      submitting: string;
      successTitle: string;
      successText: string;
      errorText: string;
      requiredHint: string;
    };
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
