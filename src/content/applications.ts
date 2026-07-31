import { brand } from '../config/brand';

/**
 * /applications data — one entry per confirmed line of application
 * (ICMAB review): organic electronics (absorbing OLEDs), solar-cell
 * research, chemical & biosensors, functional coatings and SPM sample
 * preparation. "New material–surface combinations" runs through the
 * introduction as a transversal concept, not as a separate card.
 * The page renders entries with `published: true`, ordered by `order`.
 * Do NOT invent performance claims, scale-up promises or results.
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
    slug: 'organic-electronics',
    tag: 'molecular layers · OLEDs',
    title: 'Organic electronics',
    shortDescription: 'Molecular layers on device-relevant substrates.',
    challenge: 'Research into OLEDs and other organic electronic systems depends on preparing and comparing many layer variations on relevant substrates.',
    use: `${name} prepares and compares molecular layers on device-relevant substrates for research into OLEDs and other organic electronic systems.`,
    benefit: 'Layer variations are compared at reduced scale before committing to larger-scale processes.',
    published: true,
    order: 1,
  },
  {
    slug: 'solar-cells',
    tag: 'perovskite & organic',
    title: 'Solar-cell research',
    shortDescription: 'Material–surface combinations and interface preparation.',
    challenge: 'Screening candidate materials and interfaces is often limited by material availability and access to deposition equipment.',
    use: `${name} lets teams explore material–surface combinations and interface preparation for perovskite and organic solar-cell research.`,
    benefit: 'More candidate conditions explored with small material quantities.',
    published: true,
    order: 2,
  },
  {
    slug: 'sensors',
    tag: 'receptor materials',
    title: 'Chemical & biosensors',
    shortDescription: 'Sensing surfaces functionalized with candidate receptors.',
    challenge: 'Sensing layers benefit from being prepared directly on the surface that will later be evaluated.',
    use: `${name} functionalizes selected sensing surfaces with candidate receptor materials for subsequent evaluation.`,
    benefit: 'The prepared surface moves directly into the evaluation stage of your workflow.',
    published: true,
    order: 3,
  },
  {
    slug: 'coatings',
    tag: 'functional layers',
    title: 'Functional coatings',
    shortDescription: 'Candidate layers on representative substrates.',
    challenge: 'Coating candidates need early comparison on representative surfaces before any scale-up decision.',
    use: `${name} applies candidate functional layers to representative substrates and compares experimental conditions before moving to larger-scale processes.`,
    benefit: 'Experimental conditions are compared early, on the material that matters.',
    published: true,
    order: 4,
  },
  {
    slug: 'spm-sample-preparation',
    tag: 'scanning probe microscopy',
    title: 'SPM sample preparation',
    shortDescription: 'Deposited samples for scanning probe microscopy workflows.',
    challenge: 'Scanning probe microscopy (SPM) workflows need reproducible, well-defined deposited samples.',
    use: `${name} prepares deposited samples for scanning probe microscopy workflows under controlled experimental conditions.`,
    benefit: 'Samples prepared under controlled conditions, ready for the microscopy workflow.',
    published: true,
    order: 5,
  },
];

export const applicationsPage = {
  meta: {
    title: `Applications — ${name} · Confirmed Lines of Application`,
    description: `Where ${name} is used: organic electronics including OLEDs, perovskite and organic solar-cell research, chemical and biosensors, functional coatings and SPM sample preparation.`,
  },
  hero: {
    kicker: 'Applications',
    kickerNote: 'confirmed use cases',
    title: 'Recognise your use case',
    lead: 'Five confirmed lines of application — and the exploration of new material–surface combinations running through all of them. Each one links to a conversation.',
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
    text: `${name} is designed for researchers and R&D teams working with surfaces or exploring how surface-related processes can contribute to their research — specialised surface-science groups, researchers from adjacent fields, corporate R&D departments and testing laboratories alike.`,
  },
  finalCta: {
    title: 'Application not on the list?',
    text: 'Tell us about the application you would like to explore with MatSurfer.',
    button: 'Discuss your use case',
    href: '/contact?interest=application',
  },
} as const;
