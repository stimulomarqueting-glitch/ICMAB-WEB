import type { SiteContent } from './types';
import { brand } from '../config/brand';
import { leadCapture } from '../config/leadCapture';

const name = brand.projectName;

export const en: SiteContent = {
  meta: {
    title: `${name} — Compact Surface Deposition Device · Lab-Scale R&D`,
    description: `${name} is a compact device for direct deposition of materials on surfaces by sublimation, at lab scale. Developed at ICMAB-CSIC. Join the early-access waitlist.`,
    ogAlt: `${name} — compact surface deposition device for lab-scale R&D`,
  },

  header: {
    nav: [
      { label: 'Technology', href: '#technology' },
      { label: 'Benefits', href: '#benefits' },
      { label: 'Applications', href: '#applications' },
      { label: "Who it's for", href: '#who-is-it-for' },
    ],
    cta: 'Join the waitlist',
    menuOpen: 'Open menu',
    menuClose: 'Close menu',
    langLabel: 'Language',
  },

  hero: {
    eyebrow: 'ICMAB-CSIC technology · Patented (EP 23711114.1)',
    headline: 'Direct surface experimentation, made compact.',
    descriptor: 'Compact surface deposition device · lab-scale R&D',
    lead: 'Deposit, screen and characterise materials by sublimation, directly on the surfaces that matter — on your bench, without a cleanroom or a large vacuum system.',
    ctaPrimary: 'Request early access',
    ctaSecondary: 'See how it works',
    photoAlt: `The ${name} glass deposition device on a table, next to its documentation and a sample substrate`,
  },

  problem: {
    kicker: 'The problem',
    title: "Surface experiments shouldn't require a facility.",
    lead: 'Depositing a material on a surface usually means big vacuum systems, cleanroom access, trained operators and queues — and most of the loaded material never reaches the sample.',
    photoAlt: 'A dedicated laboratory corridor lined with fume hoods — the infrastructure surface work usually requires',
    cards: [
      {
        tag: 'Infrastructure',
        title: 'Big systems, shared queues',
        text: 'High-vacuum deposition lines are expensive, centralised and booked — a bottleneck between an idea and its first test.',
      },
      {
        tag: 'Expertise',
        title: 'Specialist training required',
        text: 'Conventional tools keep surface work in the hands of a few trained operators, out of reach for adjacent fields.',
      },
      {
        tag: 'Material',
        title: 'Grams spent for cm² of result',
        text: 'Most of the loaded material never reaches the sample. For novel or costly molecules, that waste decides what gets tried.',
      },
    ],
  },

  technology: {
    kicker: 'The technology',
    title: 'Sublimation, brought to the bench.',
    lead: `${name} heats a solid source until it sublimes, and deposits the vapour directly onto your final substrate across an adjustable distance — in a benchtop glass chamber, with minimal steps, energy and material loss.`,
    video: {
      caption: `${name} in operation — from solid material to deposited layer.`,
      placeholder: 'Device video coming soon.',
    },
    steps: [
      { title: 'Load milligrams', text: 'Place a small amount of your solid material in the source holder.' },
      { title: 'Set the parameters', text: 'Temperature, pressure, time — and the source–substrate distance d.' },
      { title: 'Sublimate directly', text: 'The vapour deposits straight onto your final substrate. No transfer, no solvent.' },
      { title: 'Characterise', text: 'Take the 1–3 cm² sample to analysis, or screen the next condition.' },
    ],
    accordionTitle: 'In more detail',
    accordion: [
      {
        q: 'How does it work?',
        a: `${name} is a compact, controlled sublimation chamber. A small amount of solid material is heated in the source holder until it sublimes; the vapour travels a short, adjustable distance and condenses directly on your target substrate as a thin deposited layer. Because the geometry is close-spaced and the chamber is small, most of the material ends up where the sample is — not on the chamber walls. The process needs no solvents and no transfer step: the surface you grow is the surface you measure.`,
      },
      {
        q: 'What can it be used for?',
        a: 'Any experiment where a sublimable material must meet a surface: emitter and transport layers for OLEDs, absorber and interface layers for perovskite and organic solar cells, receptor layers for chemical and biosensors, molecular films for organic electronics, candidate functional coatings, and screening of new compounds such as MOFs, COFs and novel molecules across different substrates.',
      },
      {
        q: 'Who is it for?',
        a: 'Surface-science groups that want reduced-scale testing beside their existing deposition lines; researchers from chemistry, biology or physics doing their first surface work without specialist infrastructure; corporate R&D and testing services adding surface screening to their portfolio; and lab equipment distributors looking for a patent-protected, benchtop-format product.',
      },
      {
        q: 'What makes it different?',
        a: 'Deposition happens directly on the final substrate at 1–3 cm² scale, with milligram loads instead of grams, in a device under 20 cm that runs without a cleanroom or a large vacuum line. The source–substrate distance is adjustable, the pieces are modular and economically replaceable, and the workflow is simple enough that no specialist operator is needed.',
      },
      {
        q: 'Validation & IP',
        a: 'The technology was developed at ICMAB-CSIC (Institut de Ciència de Materials de Barcelona) and is protected by a Spanish and European patent family (EP 23711114.1). Working prototypes are at TRL 5 moving towards 6, supported by competitive research and technology-transfer programmes, with real experimental use in the lab.',
      },
    ],
    ctaBandText: 'See it on your own bench first.',
    ctaBandButton: 'Request early access',
  },

  benefits: {
    kicker: 'Benefits',
    title: 'What your team gets.',
    outcomes: [
      {
        title: 'Less material per answer',
        text: 'Screen a candidate molecule with milligrams, not grams — costly compounds stay viable to test.',
      },
      {
        title: 'More conditions tested per week',
        text: 'Direct cycles on the bench replace facility queues — sweeps happen the same day.',
      },
      {
        title: 'Autonomy for the whole group',
        text: 'Anyone can run a deposition in their own lab. No booking, no specialist operator.',
      },
      {
        title: 'Defensible results',
        text: 'Films grown on the real substrate — what you measure is what you made.',
      },
    ],
  },

  applications: {
    kicker: 'Applications',
    kickerNote: 'confirmed use cases',
    title: "What you'd use it for, concretely.",
    items: [
      {
        sub: 'emissive stacks',
        name: 'OLEDs',
        text: 'Deposit emitter and transport layers on test substrates and compare stack variations before committing shared-evaporator time.',
      },
      {
        sub: 'perovskite & organic',
        name: 'Solar cells',
        text: 'Sublime absorber and interface layers directly on device substrates to screen compositions at milligram cost.',
      },
      {
        sub: 'receptor layers',
        name: 'Chemical & biosensors',
        text: 'Functionalise the sensing surface with receptor molecules and test the response on the final transducer itself.',
      },
      {
        sub: 'molecular films',
        name: 'Organic electronics',
        text: 'Grow thin molecular films on device-ready substrates for transistors and circuit prototypes, one condition at a time.',
      },
      {
        sub: 'functional layers',
        name: 'Coatings',
        text: 'Apply candidate functional coatings on real workpiece samples and compare adhesion and coverage before scaling up.',
      },
      {
        sub: 'MOFs · COFs · novel molecules',
        name: 'New surface materials',
        text: 'Screen new compounds across many substrates quickly — find what deposits, and under which conditions.',
      },
    ],
    ctaButton: 'Discuss your use case',
    ctaNote: "Tell us what you'd deposit first — it shapes the pilot programme.",
  },

  audiences: {
    kicker: "Who it's for",
    title: 'Built for four kinds of teams.',
    photoAlt: `A researcher holding the compact ${name} glass chamber in one hand`,
    groups: [
      {
        icon: 'surface',
        title: 'Surface-science research groups',
        text: 'Reduced-scale testing beside your existing deposition lines — reserve the big system for what earns it.',
      },
      {
        icon: 'lab',
        title: 'Researchers new to surfaces',
        text: 'A first deposition without specialist infrastructure or training — chemistry, biology and physics groups exploring surfaces.',
      },
      {
        icon: 'insitu',
        title: 'Corporate R&D and testing services',
        text: 'A compact apparatus that adds surface preparation and screening to your service portfolio without a facility build-out.',
      },
      {
        icon: 'modularity',
        title: 'Lab equipment distributors',
        text: 'A patent-protected, benchtop-format addition to a glassware and instrumentation catalogue.',
      },
    ],
    ctaButton: 'Join the waitlist',
    ctaNote: 'Early-access units are allocated by fit, in order of application.',
  },

  waitlist: {
    kicker: 'Early access',
    title: 'Join the waitlist.',
    lead: "Tell us about your lab and what you'd deposit first. Pilot units are limited; we allocate them by fit, in order of application.",
    steps: [
      'We review your application and use case.',
      'We contact you as pilot units become available.',
      'You get priority access to the first production run.',
    ],
    photoAlt: `A standard lab bench with an analytical balance — the only infrastructure ${name} needs`,
    form: {
      fullName: { label: 'Full name', error: 'Please enter your full name.' },
      email: { label: 'Work email', error: 'Please enter a valid email address.' },
      organization: { label: 'Organisation', error: 'Please enter your organisation.' },
      role: { label: 'Role' },
      country: {
        label: 'Country',
        error: 'Please select your country.',
        options: [
          'Spain', 'Portugal', 'France', 'Germany', 'Italy', 'United Kingdom', 'Netherlands', 'Belgium',
          'Switzerland', 'Austria', 'Denmark', 'Sweden', 'Norway', 'Finland', 'Ireland', 'Poland', 'Czechia',
          'United States', 'Canada', 'Japan', 'South Korea', 'China', 'Australia', 'Other',
        ].map((c) => ({ value: c, label: c })),
      },
      organizationType: {
        label: 'Organisation type',
        error: 'Please select your organisation type.',
        options: [
          { value: 'academic-research-group', label: 'Academic research group' },
          { value: 'public-research-centre', label: 'Public research centre' },
          { value: 'corporate-rd', label: 'Corporate R&D' },
          { value: 'testing-services', label: 'Testing & characterisation services' },
          { value: 'distributor', label: 'Lab equipment distributor' },
          { value: 'tech-transfer', label: 'Technology transfer office' },
          { value: 'other', label: 'Other' },
        ],
      },
      areaOfInterest: {
        label: 'Area of interest',
        error: 'Please select an area of interest.',
        options: [
          { value: 'oleds', label: 'OLEDs' },
          { value: 'solar-cells', label: 'Solar cells (perovskite & organic)' },
          { value: 'sensors', label: 'Chemical & biosensors' },
          { value: 'organic-electronics', label: 'Organic electronics' },
          { value: 'coatings', label: 'Coatings' },
          { value: 'new-surface-materials', label: 'New surface materials' },
          { value: 'other', label: 'Other' },
        ],
      },
      currentNeed: {
        label: 'Current need',
        options: [
          { value: 'exploring', label: 'Exploring the technology' },
          { value: 'evaluating-project', label: 'Evaluating for a specific project' },
          { value: 'active-pilot', label: 'Active need — interested in a pilot unit' },
          { value: 'distribution-partnership', label: 'Distribution or partnership' },
        ],
      },
      timeline: {
        label: 'Timeline',
        options: [
          { value: 'within-3-months', label: 'Within 3 months' },
          { value: '3-6-months', label: '3–6 months' },
          { value: '6-12-months', label: '6–12 months' },
          { value: 'beyond-12-months', label: 'Beyond 12 months' },
          { value: 'following', label: 'Just following progress' },
        ],
      },
      message: {
        label: 'What would you deposit first?',
        placeholder: 'Material, substrate, and what you want to learn from it',
      },
      // TODO(client/legal): validate final consent wording with legal counsel.
      consent: {
        labelBefore: `I agree to be contacted about ${name} early access and accept the`,
        privacyLabel: 'Privacy Policy',
        labelAfter: '.',
        error: 'Please accept the Privacy Policy to continue.',
      },
      optionalTag: '(optional)',
      selectPlaceholder: 'Select…',
      submit: 'Request early access',
      submitting: 'Sending…',
      successTitle: "You're on the waitlist.",
      successText:
        "Thank you — we've received your application. We review use cases in order of arrival and will contact you as pilot units become available.",
      errorText: 'Something went wrong while sending your request. Please try again, or contact us directly at',
    },
  },

  footer: {
    description: `Compact surface deposition device for lab-scale R&D. ${brand.origin}, Barcelona.`,
    cta: 'Join the waitlist',
    columns: [
      {
        title: 'Product',
        links: [
          { label: 'Technology', href: '#technology' },
          { label: 'Benefits', href: '#benefits' },
          { label: 'Applications', href: '#applications' },
        ],
      },
      {
        title: 'Programme',
        links: [
          { label: "Who it's for", href: '#who-is-it-for' },
          { label: 'Early access', href: '#waitlist' },
        ],
      },
      {
        title: 'Legal',
        links: [
          // TODO(client): link real privacy policy & legal notice pages
          { label: 'Privacy Policy', href: leadCapture.privacyUrl },
          { label: 'Legal notice', href: '#' },
          { label: 'Contact', href: `mailto:${brand.contactEmail}` },
        ],
      },
    ],
    copyright: `© 2026 ${name} · ICMAB-CSIC · Name and brand are provisional.`,
    bottomMono: `EP 23711114.1 · ${brand.claim}`,
  },
};
