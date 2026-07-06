import type { SiteContent } from './types';
import { brand } from '../config/brand';

const name = brand.projectName;

export const en: SiteContent = {
  meta: {
    title: `${name} — Compact Surface Deposition for Advanced Materials Research`,
    description: `${name} is a compact lab-scale device for direct material deposition on surfaces, helping research teams explore OLEDs, sensors, organic electronics and advanced materials faster.`,
    ogAlt: `${name} — compact surface deposition device for research laboratories`,
  },

  header: {
    nav: [
      { label: 'Technology', href: '#technology' },
      { label: 'Applications', href: '#applications' },
      { label: 'Benefits', href: '#benefits' },
      { label: 'Who is it for', href: '#who-is-it-for' },
      { label: 'Contact', href: '#waitlist' },
    ],
    cta: 'Join the waitlist',
    menuOpen: 'Open menu',
    menuClose: 'Close menu',
    langLabel: 'Language',
  },

  hero: {
    eyebrow: brand.claim,
    headline: 'Surface experimentation,',
    headlineAccent: 'made simple.',
    subheadline: `${name} is a compact lab-scale device designed to make direct material deposition on surfaces faster, easier and more accessible for research teams — no complex infrastructure, no specialised facilities.`,
    ctaPrimary: 'Join the waitlist',
    ctaSecondary: 'Explore the technology',
    microcopy: `${brand.origin} · Patent-protected technology`,
    stats: [
      { value: '1–3 cm²', label: 'working sample scale' },
      { value: '< 20 cm', label: 'benchtop footprint' },
      { value: 'Direct', label: 'deposition on target substrates' },
    ],
    visualCaption: 'Conceptual visual — real device photography coming soon.',
    visualAlt: `Abstract schematic of the ${name} device: material particles depositing from a source onto a target surface`,
  },

  problem: {
    kicker: 'The problem',
    title: 'Working with surfaces is still harder than it should be.',
    intro:
      'Functional surfaces are behind OLEDs, sensors, coatings and next-generation devices. Yet for many teams, simply testing a material on a real surface remains a slow, expensive process.',
    points: [
      {
        icon: 'building',
        title: 'Heavy infrastructure',
        text: 'Advanced surface work usually depends on complex equipment, dedicated facilities and experienced operators — resources many labs simply don’t have.',
      },
      {
        icon: 'clock',
        title: 'Slow early testing',
        text: 'Early-stage surface tests are often slow and material-intensive, turning a simple “does this work on my substrate?” question into a long project.',
      },
      {
        icon: 'flask',
        title: 'Precious material, wasted',
        text: 'Standard workflows consume far more compound than the experiment needs — a real cost when materials are expensive or available only in milligrams.',
      },
      {
        icon: 'gap',
        title: 'A gap in the workflow',
        text: 'Between discovering a promising material and validating it on a practical surface, there is a missing tool: something simple, direct and lab-scale.',
      },
    ],
    bridge: `${name} was designed to close that gap.`,
  },

  technology: {
    kicker: 'The technology',
    title: 'A compact deposition device that brings the experiment to your bench.',
    intro: `${name} is a family of compact devices for direct material deposition on surfaces, born from sublimation research at ICMAB-CSIC and designed around real laboratory needs.`,
    whatIsIt: {
      title: 'What is it?',
      text: `At its core, ${name} lets you take a material of interest and deposit it directly onto the surface, substrate or device you actually care about — inside a compact, controlled environment that sits on a standard lab bench. No cleanroom booking. No large vacuum coater. No dedicated operator.`,
    },
    stepsTitle: 'How it works — at a glance',
    steps: [
      {
        title: 'Load the material',
        text: 'Place a small amount of your compound in the material source. Experiments are designed for minimal material consumption.',
      },
      {
        title: 'Controlled deposition',
        text: 'The material is transferred by controlled sublimation/deposition in a compact chamber, with an adjustable source-to-substrate distance.',
      },
      {
        title: 'Direct to your surface',
        text: 'The material lands directly on your target substrate — a test surface, an electrode, or a device under development.',
      },
      {
        title: 'Explore & validate',
        text: 'Characterise the functional surface, iterate conditions, swap substrates and move quickly from idea to evidence.',
      },
    ],
    featuresTitle: 'Designed for real lab work',
    features: [
      {
        icon: 'resize',
        title: 'Small-scale by design',
        text: 'Work on 1–3 cm² samples — the right size for screening materials and validating conditions before committing to scale.',
      },
      {
        icon: 'tune',
        title: 'Adjustable geometry',
        text: 'Tune the distance between material source and substrate to explore different experimental conditions.',
      },
      {
        icon: 'modular',
        title: 'Modular & adaptable',
        text: 'A compact, modular format with replaceable parts, designed to fit alongside existing lab procedures and equipment.',
      },
      {
        icon: 'user',
        title: 'Accessible workflow',
        text: 'Built to be usable without deep expertise in surface techniques, so more teams can run their own surface experiments.',
      },
    ],
    schematicAlt: `Conceptual flow diagram: material source, controlled sublimation and deposition, target substrate, functional surface exploration`,
    schematicNote: 'Simplified conceptual scheme — a detailed device schematic will replace this visual.',
  },

  benefits: {
    kicker: 'Key benefits',
    title: 'Why research teams are interested.',
    intro: 'A simpler route from material idea to surface validation.',
    items: [
      {
        icon: 'target',
        title: 'Direct-to-surface deposition',
        text: 'Deposit materials straight onto the substrate or device that matters — not onto an intermediate you then have to transfer.',
      },
      {
        icon: 'bench',
        title: 'Compact lab-scale workflow',
        text: 'A benchtop format that doesn’t demand dedicated rooms, large vacuum systems or specialised facilities.',
      },
      {
        icon: 'drop',
        title: 'Lower material consumption',
        text: 'Run meaningful experiments with small quantities of compound — ideal for expensive or scarce materials.',
      },
      {
        icon: 'fast',
        title: 'Faster iterations',
        text: 'Set up, deposit, characterise, repeat. Shorter cycles mean more conditions tested and faster decisions.',
      },
      {
        icon: 'tune',
        title: 'Adjustable experimental setup',
        text: 'Control key parameters such as source-to-substrate distance to explore how conditions shape your surface.',
      },
      {
        icon: 'unlock',
        title: 'Accessible surface R&D',
        text: 'Opens surface experimentation to teams without prior surface-science infrastructure — exploration before scaling.',
      },
    ],
  },

  applications: {
    kicker: 'Applications',
    title: 'Surfaces for…',
    intro:
      'Deposition is the starting point — not the limit. The same compact platform supports exploration across very different fields. Wherever a material meets a surface, there is an experiment worth running.',
    surfacesForLabel: 'Surfaces for',
    items: [
      {
        name: 'OLEDs',
        short: 'OLEDs',
        text: 'Explore organic molecule deposition for light-emitting devices and material screening.',
      },
      {
        name: 'Sensors',
        short: 'Sensors',
        text: 'Test functional layers directly on target substrates for chemical and biosensing research.',
      },
      {
        name: 'Organic electronics',
        short: 'Organic electronics',
        text: 'Prototype surface interactions and material behaviours for organic semiconductors.',
      },
      {
        name: 'Coatings',
        short: 'Coatings',
        text: 'Experiment with functional thin layers and study material–surface combinations.',
      },
      {
        name: 'Biomaterials',
        short: 'Biomaterials',
        text: 'Explore surface modification routes in research contexts, at a manageable scale.',
      },
      {
        name: 'Advanced materials research',
        short: 'Advanced materials',
        text: 'Move from material concept to surface validation — from novel molecules to MOFs and beyond.',
      },
    ],
    openEnded:
      'Working on something different? The platform is deliberately open-ended — tell us about your surface challenge.',
  },

  audiences: {
    kicker: 'Who is it for',
    title: 'Built for the people who work with surfaces — and those who want to start.',
    intro:
      'From expert surface scientists who need a faster small-scale tool, to teams from other fields who want an easy first step into surface experimentation.',
    groups: [
      { icon: 'atom', title: 'Research groups', text: 'Surface chemistry, surface physics and materials science groups in universities and research centres.' },
      { icon: 'school', title: 'University laboratories', text: 'Teaching and research labs that want hands-on surface experimentation without heavy infrastructure.' },
      { icon: 'rnd', title: 'R&D teams', text: 'Industrial R&D departments in organic electronics, photovoltaics, sensing and coatings.' },
      { icon: 'layers', title: 'Materials companies', text: 'Teams developing or testing new compounds that need fast validation on real substrates.' },
      { icon: 'chip', title: 'Device developers', text: 'Prototyping teams that want to deposit materials directly onto devices under development.' },
      { icon: 'handshake', title: 'Lab equipment partners', text: 'Distributors and lab-sourcing companies looking to broaden their portfolio.' },
      { icon: 'transfer', title: 'Tech transfer & innovation', text: 'Technology transfer offices, investors and innovation teams exploring emerging lab technologies.' },
    ],
    quote: `Whether you are exploring a new molecule, validating a surface treatment or looking for a more accessible way to test functional layers, ${name} is designed to lower the barrier between material ideas and surface experiments.`,
  },

  credibility: {
    kicker: 'Origin & credibility',
    title: 'Born in the lab. Built for the lab.',
    intro: `${name} emerges from research at the Institute of Materials Science of Barcelona (ICMAB-CSIC), shaped by the everyday needs of working scientists rather than by abstract product design.`,
    facts: [
      {
        icon: 'lab',
        title: 'ICMAB-CSIC research',
        text: 'Developed from research at ICMAB-CSIC, one of Europe’s reference centres in materials science.',
      },
      {
        icon: 'shield',
        title: 'Patent-protected',
        text: 'The technology is protected by a Spanish and European patent family.',
      },
      {
        icon: 'gear',
        title: 'Working prototypes',
        text: 'Functional lab prototypes are in active development and refinement with real experimental use.',
      },
      {
        icon: 'rocket',
        title: 'Technology transfer phase',
        text: 'Currently in an early-access, technology-transfer phase, supported by competitive research and entrepreneurship programmes.',
      },
    ],
    logosTitle: 'Institutional environment',
    logoPlaceholders: ['ICMAB', 'CSIC', 'FGCSIC'],
    phaseNote:
      'Logos shown as placeholders pending institutional approval. Final partner and programme logos will be added here.',
  },

  waitlist: {
    kicker: 'Early access',
    title: `Interested in exploring what ${name} could do for your surfaces?`,
    intro:
      'We are collecting interest from research teams, R&D departments and partners who want to test, validate or discuss applications for compact surface deposition. Join the waitlist and we will get back to you as the project moves forward.',
    form: {
      name: { label: 'Name', placeholder: 'Your full name', error: 'Please enter your name.' },
      organization: { label: 'Organization', placeholder: 'University, company or institution', error: 'Please enter your organization.' },
      email: { label: 'Email', placeholder: 'you@institution.org', error: 'Please enter a valid email address.' },
      role: { label: 'Role', placeholder: 'e.g. Principal Investigator, R&D Engineer' },
      country: { label: 'Country', placeholder: 'e.g. Spain' },
      profile: {
        label: 'What best describes you?',
        error: 'Please choose an option.',
        options: [
          { value: 'research-lab', label: 'Research lab' },
          { value: 'rd-company', label: 'R&D company' },
          { value: 'distributor', label: 'Lab equipment distributor' },
          { value: 'investor-tt', label: 'Investor / tech transfer' },
          { value: 'other', label: 'Other' },
        ],
      },
      application: {
        label: 'What application are you interested in?',
        options: [
          { value: 'oleds', label: 'OLEDs' },
          { value: 'sensors', label: 'Sensors' },
          { value: 'organic-electronics', label: 'Organic electronics' },
          { value: 'coatings', label: 'Coatings' },
          { value: 'biomaterials', label: 'Biomaterials' },
          { value: 'advanced-materials', label: 'Advanced materials research' },
          { value: 'other', label: 'Other' },
        ],
      },
      message: {
        label: 'Tell us about your surface challenge',
        placeholder: 'What would you like to deposit, test or explore? (optional)',
      },
      consent: {
        label: 'I agree to be contacted about this project and accept the privacy policy.',
        error: 'Please accept the privacy policy to continue.',
      },
      submit: 'Join the waitlist',
      submitting: 'Sending…',
      successTitle: 'Thank you — you are on the list.',
      successText: 'We have received your interest. The team will contact you as early-access opportunities open up.',
      errorText: 'Something went wrong while sending the form. Please try again or write to us directly.',
      requiredHint: 'Required fields are marked with *',
    },
  },

  footer: {
    description: `A compact way to explore, test and create functional surfaces. ${brand.origin}.`,
    navTitle: 'Explore',
    nav: [
      { label: 'Technology', href: '#technology' },
      { label: 'Applications', href: '#applications' },
      { label: 'Benefits', href: '#benefits' },
      { label: 'Contact', href: '#waitlist' },
    ],
    contactTitle: 'Contact',
    contactText: 'For enquiries about the technology, partnerships or early access:',
    legalTitle: 'Legal',
    legal: [
      { label: 'Privacy Policy', href: '#' },
      { label: 'Legal Notice', href: '#' },
    ],
    languageTitle: 'Language',
    copyright: `${name} is a technology-transfer project from ICMAB-CSIC research. Name and brand are provisional.`,
  },
};
