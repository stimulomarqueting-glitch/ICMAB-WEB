import { brand } from '../config/brand';

/**
 * Home copy — strategic introduction, not the full dossier.
 * Rule: promise + summary + link. Full explanations live on /product,
 * /applications, /research and /team.
 */

const name = brand.projectName;

export const home = {
  hero: {
    eyebrow: 'ICMAB-CSIC technology · Patented (EP 23711114.1)',
    headline: 'Direct surface experimentation, made compact',
    descriptor: 'Compact surface deposition device · lab-scale R&D',
    lead: 'Deposit, screen and characterise materials by sublimation, directly on the surfaces that matter — on your bench, without a cleanroom or a large vacuum system.',
    ctaPrimary: 'Talk to the team',
    ctaSecondary: 'Explore the product',
    photoAlt: `The ${name} glass deposition device on a table, next to its documentation and a sample substrate`,
  },

  /** Problem → MatSurfer → Result, one sentence each */
  value: {
    items: [
      {
        tag: 'The problem',
        text: 'Depositing a material on a surface usually means big vacuum systems, cleanroom access, trained operators and queues.',
      },
      {
        tag: name,
        text: 'A benchtop glass chamber that sublimes milligrams of material directly onto your final substrate.',
      },
      {
        tag: 'The result',
        text: 'Surface experiments run in your own lab, the same day, by anyone in the group.',
      },
    ],
  },

  product: {
    kicker: 'The technology',
    title: 'Sublimation, brought to the bench',
    lead: `${name} heats a solid source until it sublimes and deposits the vapour straight onto your final substrate — with a controllable source–substrate distance that lets you adjust experimental conditions directly during preparation.`,
    cta: 'Explore the product',
    ctaHref: '/product',
    photoAlt: `Close-up of the ${name} glass chamber and its machined components`,
  },

  benefits: {
    kicker: 'Benefits',
    title: 'What your team gets',
    outcomes: [
      { title: 'Less material per answer', text: 'Screen a candidate molecule with milligrams, not grams.' },
      { title: 'More conditions tested per week', text: 'Direct cycles on the bench replace facility queues.' },
      { title: 'Autonomy for the whole group', text: 'Anyone can run a deposition in their own lab.' },
      { title: 'Defensible results', text: 'Films grown on the real substrate — what you measure is what you made.' },
    ],
  },

  applications: {
    kicker: 'Applications',
    title: 'One device, many materials',
    lead: 'The device stays the same; what changes is the reading of the interior. Six confirmed use cases, from OLEDs to new surface materials.',
    isotype: {
      alt: `The ${name} capsule isotype: the device stays the same while different points of its interior read as application fields — OLEDs, sensors, perovskites, coatings and SPM`,
      caption: 'Same device, different readings — the focus shifts to different points of the interior depending on the field.',
      labels: ['OLEDs', 'Sensors', 'Perovskites', 'Coatings', 'SPM'] as [string, string, string, string, string],
    },
    cta: 'Explore applications',
    ctaHref: '/applications',
  },

  audiences: {
    kicker: "Who it's for",
    title: 'Built for four kinds of teams',
    groups: [
      { icon: 'surface', title: 'Academic research groups', text: 'Reduced-scale testing beside existing deposition lines.' },
      { icon: 'lab', title: 'Researchers new to surfaces', text: 'A first deposition without specialist infrastructure.' },
      { icon: 'insitu', title: 'Corporate R&D and testing labs', text: 'Surface preparation and screening without a facility build-out.' },
      { icon: 'modularity', title: 'Lab equipment partners', text: 'A patent-protected, benchtop-format addition to a catalogue.' },
    ],
  },

  explore: {
    researchTitle: 'Research & validation',
    researchText: 'Expert experiences and published work developed with the technology.',
    researchCta: 'Explore the research',
    researchHref: '/research',
    teamTitle: 'The team',
    teamText: 'The people bringing surface research, technology transfer and product development together.',
    teamCta: 'Meet the team',
    teamHref: '/team',
  },

  finalCta: {
    title: 'Bring surface experiments to your own bench',
    text: 'Tell us about your lab and what you would deposit first.',
    button: 'Talk to the team',
    href: '/contact',
  },
} as const;
