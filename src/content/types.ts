/**
 * Shared shape for all locale content files (en.ts / es.ts).
 * v2 — "Coral Vapor" low-density layout: short visible copy per section,
 * expanded detail lives in the technology accordion.
 */

export interface NavItem {
  label: string;
  href: string;
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

/** Technical parameter chip: symbol in mono + value/unit or a short label. */
export interface ParamChip {
  symbol: string;
  value?: string;
  unit?: string;
  label?: string;
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
    /** category descriptor line under the H1, mono */
    descriptor: string;
    lead: string;
    ctaPrimary: string;
    ctaSecondary: string;
    photoAlt: string;
    /** label on the spec divider line */
    paramsLabel: string;
    chips: ParamChip[];
  };
  problem: {
    kicker: string;
    title: string;
    lead: string;
    cards: { tag: string; title: string; text: string }[];
  };
  technology: {
    kicker: string;
    title: string;
    lead: string;
    diagramAlt: string;
    /** phase captions under the process diagram, in order */
    diagramCaption: string[];
    steps: { title: string; text: string }[];
    facts: { value: string; text: string }[];
    factImgAlt: string;
    /** expanded detail accordion ("understand without opening; open to go deeper") */
    accordionTitle: string;
    accordion: { q: string; a: string }[];
    ctaBandText: string;
    ctaBandButton: string;
  };
  benefits: {
    kicker: string;
    title: string;
    specsHead: string;
    specs: { sym: string; text: string }[];
    outcomesHead: string;
    outcomes: { title: string; text: string }[];
  };
  applications: {
    kicker: string;
    kickerNote: string;
    title: string;
    items: { sub: string; name: string; text: string }[];
    ctaButton: string;
    ctaNote: string;
  };
  audiences: {
    kicker: string;
    title: string;
    groups: { icon: string; title: string; text: string }[];
    ctaButton: string;
    ctaNote: string;
  };
  credibility: {
    kicker: string;
    title: string;
    lead: string;
    photoAlt: string;
    chips: ParamChip[];
    heritage: { title: string; body: string; logos: string[]; note: string };
  };
  waitlist: {
    kicker: string;
    title: string;
    lead: string;
    steps: string[];
    form: {
      fullName: FormField;
      email: FormField;
      organization: FormField;
      role: FormField;
      country: FormField & { options: SelectOption[] };
      organizationType: FormField & { options: SelectOption[] };
      areaOfInterest: FormField & { options: SelectOption[] };
      currentNeed: FormField & { options: SelectOption[] };
      timeline: FormField & { options: SelectOption[] };
      message: FormField;
      consent: { labelBefore: string; privacyLabel: string; labelAfter: string; error: string };
      optionalTag: string;
      selectPlaceholder: string;
      submit: string;
      submitting: string;
      successTitle: string;
      successText: string;
      /** Ends mid-sentence; the contact email link is appended in markup. */
      errorText: string;
    };
  };
  footer: {
    description: string;
    cta: string;
    columns: { title: string; links: NavItem[] }[];
    languageTitle: string;
    copyright: string;
    bottomMono: string;
  };
}
