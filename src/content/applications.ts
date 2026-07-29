import { brand } from '../config/brand';

/**
 * /applications data — one entry per confirmed use case.
 * The page renders entries with `published: true`, ordered by `order`.
 * Add new applications here as they are confirmed; do NOT invent
 * performance claims, scale-up promises or results.
 */

const name = brand.projectName;

export interface Application {
  slug: string;
  /** short mono tag shown above the title */
  tag: string;
  title: string;
  /** one-line summary used on teasers/cards */
  shortDescription: string;
  /** need or context the team faces */
  challenge: string;
  /** what MatSurfer lets them explore */
  use: string;
  /** benefit for the R&D team */
  benefit: string;
  published: boolean;
  order: number;
}

export const applications: Application[] = [
  {
    slug: 'oleds',
    tag: 'emissive stacks',
    title: 'Organic electronics and OLEDs',
    shortDescription: 'Emitter, transport and molecular layers on test substrates.',
    challenge: 'Comparing stack variations or growing molecular films usually competes for shared-evaporator time, so fewer ideas get tried.',
    use: `${name} deposits emitter and transport layers on test substrates, and grows thin molecular films on device-ready substrates for transistors and circuit prototypes — one condition at a time.`,
    benefit: 'Stack and film variations are compared on the bench before committing shared-facility time.',
    published: true,
    order: 1,
  },
  {
    slug: 'solar-cells',
    tag: 'perovskite & organic',
    title: 'Perovskite and organic solar cells',
    shortDescription: 'Absorber and interface layers directly on device substrates.',
    challenge: 'Screening absorber compositions and interface layers is limited by material cost and access to deposition lines.',
    use: `${name} sublimes absorber and interface layers directly on device substrates, screening compositions at milligram cost.`,
    benefit: 'More candidate compositions tested per week, with costly compounds staying viable to test.',
    published: true,
    order: 2,
  },
  {
    slug: 'sensors',
    tag: 'receptor layers',
    title: 'Chemical and biosensors',
    shortDescription: 'Receptor molecules deposited on the final transducer.',
    challenge: 'A sensing layer prepared on an intermediate carrier is not the layer the final device will see.',
    use: `${name} functionalises the sensing surface with receptor molecules directly on the final transducer itself.`,
    benefit: 'The response is tested on the real device surface — what you measure is what you made.',
    published: true,
    order: 3,
  },
  {
    slug: 'coatings',
    tag: 'functional layers',
    title: 'Functional coatings',
    shortDescription: 'Candidate coatings applied on real workpiece samples.',
    challenge: 'Coating candidates need to be compared on representative surfaces before any scale-up decision.',
    use: `${name} applies candidate functional coatings on real workpiece samples for comparison of adhesion and coverage.`,
    benefit: 'Adhesion and coverage are compared early, on the material that matters, before scaling up.',
    published: true,
    order: 4,
  },
  {
    slug: 'new-surface-materials',
    tag: 'MOFs · COFs · novel molecules',
    title: 'New surface materials',
    shortDescription: 'Screening new compounds across many substrates.',
    challenge: 'For novel or costly molecules, material waste decides what gets tried at all.',
    use: `${name} screens new compounds such as MOFs, COFs and novel molecules across many substrates quickly — finding what deposits, and under which conditions.`,
    benefit: 'Milligram loads make broad substrate screens affordable for new chemistry.',
    published: true,
    order: 5,
  },
  {
    slug: 'surface-functionalisation',
    tag: 'sublimable materials',
    title: 'Surface functionalisation',
    shortDescription: 'Sublimable materials meeting the surface that matters.',
    challenge: 'Many surface-related questions start with the same need: getting a sublimable material onto a specific surface, cleanly.',
    use: `${name} covers any experiment where a sublimable material must meet a surface — solvent-free, with no transfer step, directly on the substrate you will measure.`,
    benefit: 'A first deposition without specialist infrastructure or training, in your own lab.',
    published: true,
    order: 6,
  },
];

export const applicationsPage = {
  meta: {
    title: `Applications — ${name} · Confirmed Use Cases`,
    description: `Where ${name} is used: OLEDs and organic electronics, perovskite and organic solar cells, chemical and biosensors, functional coatings, new surface materials and surface functionalisation.`,
  },
  hero: {
    kicker: 'Applications',
    kickerNote: 'confirmed use cases',
    title: 'Recognise your use case',
    lead: 'Six confirmed ways teams use the device today. Each one links to a conversation — tell us what you would deposit first.',
  },
  labels: {
    challenge: 'The need',
    use: `With ${name}`,
    benefit: 'For your team',
    cta: 'Discuss this application',
  },
  /** for R&D people who don't identify as surface scientists */
  broader: {
    kicker: 'Beyond surface science',
    title: 'Not a surface scientist? That is the point',
    text: 'R&D researchers and teams exploring surface-related topics or using surfaces as part of their research, across academia and industry — from groups specialised in surface science to researchers from other fields bringing surfaces into their work, in university and academic laboratories as well as corporate R&D departments.',
  },
  finalCta: {
    title: 'Your application is not on the list?',
    text: 'If a sublimable material must meet a surface, it is worth a conversation.',
    button: 'Discuss your application',
    href: '/contact?interest=application',
  },
} as const;
