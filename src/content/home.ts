import { brand } from '../config/brand';
import { pendingContent, pendingData } from '../config/pendingContent';

/**
 * Home copy — strategic introduction, not the full dossier.
 * Rule: promise + summary + link. Full explanations live on /product,
 * /applications, /research and /team.
 * Editorial line (ICMAB review): surface functionalization as the central
 * concept, no characterisation claims, no comparisons with facilities.
 */

const name = brand.projectName;

export const home = {
  hero: {
    /* patent code returns here once Technology Transfer confirms it */
    eyebrow: pendingContent.patentFamilyConfirmed
      ? `ICMAB-CSIC technology · Patented (${pendingData.patentFamilyCode})`
      : 'ICMAB-CSIC technology',
    headline: 'Direct surface functionalization, made compact',
    descriptor: 'Compact surface functionalization device · lab-scale R&D',
    lead: 'Deposit and screen materials directly on final surfaces with a compact device designed for lab-scale experimentation.',
    ctaPrimary: 'Talk to the team',
    ctaSecondary: 'Explore the product',
    photoAlt: `The ${name} glass chamber on a table, next to its documentation and a sample substrate`,
  },

  /** Problem → MatSurfer → Result, one sentence each */
  value: {
    items: [
      {
        tag: 'The problem',
        text: 'Early-stage surface experiments can depend on specialised equipment, established workflows and more material than teams have available.',
      },
      {
        tag: name,
        text: 'A compact glass chamber that deposits material directly onto your final substrate, at reduced scale.',
      },
      {
        tag: 'The result',
        text: 'Material–surface combinations explored in your own laboratory, across the whole team.',
      },
    ],
  },

  product: {
    kicker: 'The technology',
    title: 'Surface functionalization, brought to the bench',
    lead: `A controllable source–substrate distance gives researchers direct control over the experimental geometry. ${name} uses controlled temperature and pressure conditions to transfer material onto the final substrate in a compact glass chamber.`,
    cta: 'Explore the product',
    ctaHref: '/product',
    photoAlt: `Close-up of the ${name} glass chamber and its machined components`,
  },

  benefits: {
    kicker: 'Benefits',
    title: 'What your team gets',
    outcomes: [
      { title: 'Less material per answer', text: 'A few milligrams can be enough — costly or valuable materials stay viable to test.' },
      { title: 'More conditions tested per week', text: 'Fast, straightforward direct depositions shorten research cycles.' },
      { title: 'Autonomy for the whole group', text: 'A compact workflow the whole research team can use.' },
      { title: 'Defensible results', text: 'The surface you evaluate is the surface you prepared.' },
    ],
  },

  applications: {
    kicker: 'Applications',
    title: 'One device, many research objectives',
    lead: 'Five confirmed lines of application, from organic electronics to SPM sample preparation — with new material–surface combinations running through all of them.',
    isotype: {
      alt: `The ${name} capsule isotype with its five application fields: organic electronics, sensors, perovskites, coatings and SPM`,
      caption: 'One device, different research objectives depending on the field.',
      labels: ['Organic electronics', 'Sensors', 'Perovskites', 'Coatings', 'SPM'] as [string, string, string, string, string],
    },
    cta: 'Explore applications',
    ctaHref: '/applications',
  },

  audiences: {
    kicker: "Who it's for",
    title: 'Built for four kinds of teams',
    groups: [
      { icon: 'surface', title: 'Academic research groups', text: 'Reduced-scale experimentation that complements established workflows.' },
      { icon: 'lab', title: 'Researchers new to surfaces', text: 'An accessible starting point for teams exploring surface-related research.' },
      { icon: 'insitu', title: 'Corporate R&D and testing labs', text: 'Surface preparation and screening with a reduced laboratory footprint.' },
      { icon: 'modularity', title: 'Lab equipment partners', text: 'A differentiated benchtop addition to laboratory equipment portfolios.' },
    ],
  },

  explore: {
    researchTitle: 'Case studies',
    researchText: 'The research carried out with the device by the FunNanoSurf group at ICMAB-CSIC.',
    researchCta: 'Explore the case studies',
    researchHref: '/research',
    teamTitle: 'The team',
    teamText: 'The people bringing surface research, technology transfer and product development together.',
    teamCta: 'Meet the team',
    teamHref: '/team',
  },

  finalCta: {
    title: 'Bring surface experiments to your own bench',
    text: 'Tell us about your research needs and what you would like to explore.',
    button: 'Talk to the team',
    href: '/contact',
  },
} as const;
