import { brand } from '../config/brand';

/**
 * /research data — expert experiences and publications grow from here.
 * Only entries with `published: true` are rendered. Do NOT invent
 * testimonials, publications, DOIs or links; add them as they are
 * confirmed by the client.
 */

export interface ExpertExperience {
  name: string;
  role: string;
  institution: string;
  /** scientific field, shown as a mono tag (e.g. 'Organic electronics') */
  field: string;
  quote: string;
  /** device/model used, when relevant */
  device?: string;
  /** path under /public, e.g. '/img/research/jane-doe.jpg' */
  image?: string;
  link?: string;
  /** ISO date, used for ordering (newest first) */
  date?: string;
  published: boolean;
}

export interface Publication {
  title: string;
  authors: string;
  journal: string;
  year: number;
  doi?: string;
  url?: string;
  description?: string;
  /** path under /public, when a figure is available */
  image?: string;
  /** lower numbers render first within the same year */
  order?: number;
  published: boolean;
}

/**
 * TODO(client): add confirmed expert experiences. Example entry:
 * {
 *   name: 'Full Name',
 *   role: 'Group Leader',
 *   institution: 'University of …',
 *   field: 'Organic electronics',
 *   quote: 'Confirmed quote, verbatim.',
 *   device: 'MatSurfer prototype',
 *   order/date/link/image optional,
 *   published: true,
 * },
 */
export const expertExperiences: ExpertExperience[] = [];

/**
 * TODO(client): add confirmed publications. Example entry:
 * {
 *   title: 'Paper title',
 *   authors: 'A. Author, B. Author',
 *   journal: 'Journal Name',
 *   year: 2026,
 *   doi: '10.xxxx/xxxxx',
 *   url: 'https://doi.org/10.xxxx/xxxxx',
 *   description: 'One confirmed sentence on what was done with the device.',
 *   published: true,
 * },
 */
export const publications: Publication[] = [];

/**
 * Validation & IP block — content pending confirmation by ICMAB Technology
 * Transfer. The section renders ONLY when at least one item has
 * `published: true`; no "coming soon" placeholder is shown.
 * TODO(client): flip `published` when Technology Transfer approves the copy.
 */
export interface ValidationItem {
  title: string;
  text: string;
  published: boolean;
}

export const validationItems: ValidationItem[] = [
  {
    title: 'Validation & IP',
    text: 'The technology was developed at ICMAB-CSIC (Institut de Ciència de Materials de Barcelona) and is protected by a Spanish and European patent family (EP 23711114.1). Working prototypes are at TRL 5 moving towards 6, supported by competitive research and technology-transfer programmes, with real experimental use in the lab.',
    published: false,
  },
];

/** Page copy (headings without trailing periods, per style guide). */
export const researchPage = {
  metaTitle: `Research & validation — ${brand.projectName}`,
  metaDescription: `The research carried out with ${brand.projectName} by the Functional Nanomaterials and Surfaces group — FunNanoSurf — at ICMAB-CSIC.`,
  kicker: 'Research & validation',
  title: 'Built in the lab',
  lead: `${brand.projectName} grows out of the day-to-day research of the Functional Nanomaterials and Surfaces group — FunNanoSurf — at ICMAB-CSIC. This page follows the research carried out with the device and the experiences of the researchers who work with it.`,
  experiencesTitle: 'Expert experiences',
  publicationsTitle: 'Publications',
  inviteTitle: 'Have you worked with the device?',
  inviteText: 'We would like to feature your experience or your publication here:',
  finalCta: {
    title: 'Explore a collaboration',
    text: 'Validations, researcher experiences and published work all grow from conversations.',
    button: 'Explore a collaboration',
    href: '/contact?interest=research',
  },
};
