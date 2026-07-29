import { brand } from '../config/brand';

/**
 * /product copy — the main explanation page for the device and the
 * technology. Every claim here comes from copy already approved for the
 * one-page site; nothing new has been asserted.
 */

const name = brand.projectName;

export const product = {
  meta: {
    title: `Product — ${name} · Compact Surface Deposition Device`,
    description: `What ${name} is and how it works: controlled sublimation with an adjustable source–substrate distance, depositing materials directly on the final substrate at lab scale.`,
  },

  hero: {
    kicker: 'The product',
    title: 'A compact device for direct surface deposition',
    lead: `${name} deposits materials on surfaces by controlled sublimation, directly on the final substrate, at lab scale — in a benchtop glass chamber that runs without a cleanroom or a large vacuum line.`,
    cta: 'Request product information',
    ctaHref: '/contact?interest=product',
    photoAlt: `The ${name} glass deposition device on a table, next to its documentation and a sample substrate`,
  },

  whatItIs: {
    kicker: 'What it is',
    title: 'The technology',
    lead: `${name} is a compact, controlled sublimation chamber. A small amount of solid material is heated in the source holder until it sublimes; the vapour travels a short, adjustable distance and condenses directly on your target substrate as a thin deposited layer. Because the geometry is close-spaced and the chamber is small, most of the material ends up where the sample is — not on the chamber walls.`,
  },

  /** The differentiator comes first, with more visual weight than the rest */
  differentiator: {
    kicker: 'Key differentiator',
    title: 'Controllable source–substrate distance',
    text: 'The distance between the source and the substrate is adjustable, letting researchers tune experimental conditions directly during preparation — one more parameter under control, on the same bench.',
    features: [
      { title: 'Direct deposition on the final substrate', text: 'The surface you grow is the surface you measure. No transfer step.' },
      { title: 'Small-scale experimentation', text: 'Samples of 1–3 cm² with milligram loads instead of grams.' },
      { title: 'Compact laboratory format', text: 'A device under 20 cm that runs on a standard lab bench.' },
      { title: 'Low sample consumption', text: 'Costly or novel compounds stay viable to test.' },
      { title: 'Modular, replaceable components', text: 'The pieces are modular and economically replaceable.' },
      { title: 'Solvent-free process', text: 'The process needs no solvents and no transfer step.' },
    ],
  },

  howItWorks: {
    kicker: 'How it works',
    title: 'From solid material to deposited layer',
    video: {
      caption: `${name} in operation — from solid material to deposited layer.`,
      placeholder: 'Device video coming soon',
      playLabel: 'Play the device video',
    },
    steps: [
      { title: 'Place the material', text: 'Load a small amount of your solid material in the source holder.' },
      { title: 'Position the substrate', text: 'Set your final substrate and adjust the source–substrate distance d.' },
      { title: 'Apply controlled conditions', text: 'Temperature, pressure and time — the vapour rises a short, precise distance.' },
      { title: 'Deposit on the final surface', text: 'The layer condenses directly on your substrate, ready for characterisation.' },
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
        text: 'Screen a candidate molecule with milligrams, not grams — costly compounds stay viable to test.',
      },
      {
        title: 'More conditions tested per week',
        text: 'Direct cycles on the bench replace facility queues — sweeps happen the same day.',
      },
      {
        title: 'Autonomy for the whole group',
        text: 'The workflow is simple enough that no specialist operator is needed. No booking.',
      },
      {
        title: 'Defensible results',
        text: 'Films grown on the real substrate — what you measure is what you made.',
      },
    ],
  },

  faq: {
    kicker: 'FAQ',
    title: 'In more detail',
    items: [
      {
        q: 'What can it be used for?',
        a: 'Any experiment where a sublimable material must meet a surface: emitter and transport layers for OLEDs, absorber and interface layers for perovskite and organic solar cells, receptor layers for chemical and biosensors, molecular films for organic electronics, candidate functional coatings, and screening of new compounds such as MOFs, COFs and novel molecules across different substrates.',
      },
      {
        q: 'How much material is required?',
        a: 'Milligram loads instead of grams. Deposition happens at 1–3 cm² scale directly on the final substrate, so most of the loaded material ends up where the sample is.',
      },
      {
        q: 'Does it require specialised infrastructure?',
        a: 'No cleanroom and no large vacuum line. The device is under 20 cm and runs on a standard lab bench, and the workflow is simple enough that no specialist operator is needed.',
      },
      {
        q: 'Who is it designed for?',
        a: 'R&D researchers and teams exploring surface-related topics or using surfaces as part of their research, across academia and industry — from groups specialised in surface science to researchers from other fields bringing surfaces into their work.',
      },
      {
        q: 'Can it be used in an existing R&D laboratory?',
        a: 'Yes — it is designed as benchtop equipment for existing laboratories, beside the instruments you already use, without a facility build-out.',
      },
      {
        q: 'How can I discuss a potential application?',
        a: 'Tell us what you would deposit first and on which substrate. We review use cases and come back to discuss fit — start from the contact page.',
      },
      // NOTE: the "Validation & IP" copy (pending Technology Transfer
      // approval) lives in src/content/research.ts as `validationItems`.
    ] as { q: string; a: string; published?: boolean }[],
  },

  finalCta: {
    title: 'Discuss your research',
    text: 'Tell us what you would deposit first — it shapes the pilot programme.',
    button: 'Discuss your research',
    href: '/contact?interest=product',
  },
} as const;
