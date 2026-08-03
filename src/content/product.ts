import { brand } from '../config/brand';

/**
 * /product copy — the main explanation page for the device and the
 * technology. Editorial line (ICMAB review): surface functionalization
 * first, sublimation AND evaporation named only where the techniques are
 * explained, no characterisation claims, no comparisons with facilities,
 * no unvalidated figures.
 */

const name = brand.projectName;

export const product = {
  meta: {
    title: `Product — ${name} · Compact Surface Functionalization Device`,
    description: `What ${name} is and how it works: controlled deposition with an adjustable source–substrate distance, transferring materials directly onto final substrates at lab scale.`,
  },

  hero: {
    kicker: 'The product',
    title: 'A compact device for direct surface functionalization',
    lead: `${name} deposits and functionalizes materials directly on final substrates, at lab scale — a compact benchtop glass chamber designed for surface-related R&D.`,
    cta: 'Request product information',
    ctaHref: '/contact?interest=product',
    photoAlt: `The ${name} glass chamber on a table, next to its documentation and a sample substrate`,
  },

  whatItIs: {
    kicker: 'What it is',
    title: 'The technology',
    lead: `${name} is a compact device for controlled deposition and functionalization of materials directly on final surfaces. A small amount of source material is transferred across an adjustable source–substrate distance, under controlled temperature and pressure conditions, onto the substrate selected for the experiment — through controlled sublimation or evaporation.`,
  },

  /** The differentiator comes first, with more visual weight than the rest */
  differentiator: {
    kicker: 'Key differentiator',
    title: 'Controllable source–substrate distance',
    text: 'The distance between the source and the substrate is adjustable, letting researchers tune the experimental geometry directly during preparation — one more parameter under control, on the same bench.',
    features: [
      { title: 'Direct deposition on the final substrate', text: 'The surface system you create is the surface system you measure.' },
      { title: 'Small-scale experimentation', text: 'Reduced-scale work on 1–3 cm² samples with small material quantities.' },
      { title: 'Compact laboratory format', text: 'A compact benchtop device, under 20 cm in size, designed for existing R&D laboratories.' },
      { title: 'Low sample consumption', text: 'A few milligrams can be enough — novel, costly or very valuable materials stay viable to test.' },
      { title: 'Modular configuration', text: 'A modular setup with a straightforward, adaptable workflow.' },
      { title: 'Solvent-free process', text: 'Material reaches the substrate without solvents or intermediate steps.' },
    ],
  },

  howItWorks: {
    kicker: 'How it works',
    title: 'From source material to prepared surface',
    video: {
      caption: `${name} in operation — from source material to prepared surface.`,
      placeholder: 'Device video coming soon',
      playLabel: 'Play the device video',
    },
    steps: [
      { title: 'Load the source material', text: 'Place a small amount of material in the source holder.' },
      { title: 'Set the distance and parameters', text: 'Adjust the source–substrate distance first, then set temperature, pressure and time.' },
      { title: 'Deposit directly on the final surface', text: 'The material is transferred directly onto the selected substrate through controlled sublimation or evaporation.' },
      { title: 'Remove the prepared sample', text: 'Take out the deposited sample for subsequent analysis, or prepare the next experimental condition.' },
    ],
  },

  parameters: {
    kicker: 'Parameters',
    title: 'Every deposition, defined by measurable values',
    lead: 'Each experiment is defined by a small set of controlled parameters. Specific ranges depend on the configuration and are shared during the technical conversation.',
    items: [
      { symbol: 'd', label: 'source–substrate distance' },
      { symbol: 'm', label: 'sample mass' },
      { symbol: 'P', label: 'pressure' },
      { symbol: 'T', label: 'temperature' },
      { symbol: 't', label: 'time' },
    ],
  },

  benefits: {
    kicker: 'Benefits',
    title: 'What the device changes for your team',
    outcomes: [
      {
        title: 'Less material per answer',
        text: 'Thanks to the reduced size of the technology, a few milligrams can be enough — decisive for novel, costly molecules or very valuable materials, where decisions are taken on milligrams.',
      },
      {
        title: 'More conditions tested per week',
        text: 'Fast and straightforward direct depositions make it easier to explore more experimental conditions in shorter research cycles.',
      },
      {
        title: 'Autonomy for the whole group',
        text: 'A compact and straightforward workflow makes surface deposition easier to integrate into the laboratory and use across the research team.',
      },
      {
        title: 'Defensible results',
        text: 'Material is deposited directly on the selected substrate, so the surface you evaluate is the surface you prepared.',
      },
    ],
  },

  faq: {
    kicker: 'FAQ',
    title: 'In more detail',
    items: [
      {
        q: 'How does it work?',
        a: `${name} creates controlled conditions for transferring material from a source directly onto a selected substrate. The adjustable source–substrate distance helps researchers define the experimental geometry, while temperature, pressure and time can be adapted to the material and objective. The process produces a prepared surface that can then be removed for analysis or used in the next stage of the research workflow. The surface system you create is the surface system you measure.`,
      },
      {
        q: 'What can it be used for?',
        a: `${name} supports R&D workflows that require controlled deposition or functionalization on selected surfaces. Potential fields include organic electronics, solar-cell research, chemical and biosensors, functional coatings, SPM (scanning probe microscopy) sample preparation and the exploration of new material–surface combinations.`,
      },
      {
        q: 'Who is it for?',
        a: `${name} is designed for researchers and R&D teams working with surfaces or exploring how surface-related processes can contribute to their research. This includes specialised surface-science groups, researchers from adjacent fields, corporate R&D departments, testing laboratories and potential laboratory-equipment partners.`,
      },
      {
        q: 'What makes it different?',
        a: `${name} combines direct work on the final substrate, controllable source–substrate distance and reduced-scale experimentation in a compact format. Its modular configuration and straightforward workflow are designed to make material–surface screening easier to integrate into R&D laboratories.`,
      },
      {
        q: 'How much material is required?',
        a: 'A small amount — thanks to the reduced size of the technology, a few milligrams can be enough. That matters for novel, costly molecules or very valuable materials, where decisions are taken on milligrams. Specific amounts depend on the material and the configuration, and are part of the technical conversation.',
      },
      {
        q: 'Can it be used in an existing R&D laboratory?',
        a: 'Yes — it is designed as compact benchtop equipment for existing laboratories, complementing the instruments you already use with a reduced laboratory footprint.',
      },
      {
        q: 'How can I discuss a potential application?',
        a: 'Tell us about the material, substrate or workflow you would like to explore. We review every enquiry and come back to you to discuss the next step — start from the contact page.',
      },
      // NOTE: the "Validation & IP" copy (pending Technology Transfer
      // approval) lives in src/content/research.ts as `validationItems`.
    ] as { q: string; a: string; published?: boolean }[],
  },

  finalCta: {
    title: 'Discuss your research',
    text: 'Tell us about the application you would like to explore with MatSurfer.',
    button: 'Discuss your research',
    href: '/contact?interest=product',
  },
} as const;
