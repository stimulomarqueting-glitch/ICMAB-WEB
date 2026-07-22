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

/** Page copy (headings without trailing periods, per style guide). */
export const researchPage = {
  metaTitle: `Research & validation — ${brand.projectName}`,
  metaDescription: `Expert experiences and published work developed with ${brand.projectName}, the compact surface deposition device from ICMAB-CSIC.`,
  kicker: 'Research & validation',
  title: 'Built in the lab, proven in the lab',
  lead: `This page gathers the scientific footprint of ${brand.projectName}: experiences from researchers who have worked with the device, and the published work it contributes to. It grows as the early-access programme advances.`,
  experiencesTitle: 'Expert experiences',
  publicationsTitle: 'Publications',
  inviteTitle: 'Have you worked with the device?',
  inviteText: 'We would like to feature your experience or your publication here:',
};
