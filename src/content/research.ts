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
 * Confirmed open-access publications from the FunNanoSurf group linked to
 * the device (provided by Coral). More are in preparation — add them here
 * as they are confirmed.
 */
export const publications: Publication[] = [
  {
    title: 'Curcuminoid-Based Responsive Surfaces for Fluorescent BF3 Detection, a Fast and Reversible Approach',
    authors: 'R. Gimeno-Muñoz, R. Díaz-Torres, S. Gómez-Coca, O. Roubeau, J. M. Díaz-Cruz, N. Aliaga-Alcalde, A. González-Campo',
    journal: 'ACS Applied Materials & Interfaces',
    year: 2025,
    doi: '10.1021/acsami.4c19421',
    url: 'https://doi.org/10.1021/acsami.4c19421',
    published: true,
  },
  {
    title: 'Dielectric behavior of curcuminoid polymorphs on different substrates by direct soft vacuum deposition',
    authors: 'D. Riba-López, R. Zaffino, D. Herrera, R. Matheu, F. Silvestri, J. Ferreira da Silva, E. C. Sañudo, M. Mas-Torrent, E. Barrena, R. Pfattner, E. Ruiz, A. González-Campo, N. Aliaga-Alcalde',
    journal: 'iScience',
    year: 2022,
    doi: '10.1016/j.isci.2022.105686',
    url: 'https://doi.org/10.1016/j.isci.2022.105686',
    published: true,
  },
];

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
    // Approved copy from the Technology Transfer office (via Coral, 2025-09).
    title: 'IP & Technological Validation',
    text: 'The technology was developed at The Institute of Materials Science of Barcelona (ICMAB-CSIC) and is protected by the following patents: European Unitary Patent EP4483979B1 and Spanish Patent ES2949292B2. Working prototypes have been externally validated in their operational environments for 8 research groups and 1 company across the following fields: organic electronics, biosensing, new surface materials, and EPR sample preparation. The technology has also been proven in actual operations over time in R&D laboratories of organic electronics and new surface materials. Further external validation in an operational environment and over time in extra fields of application is required and is being developed. Additionally, the technology has been supported by competitive research and technological-transfer programmes: ERC-PoC SmolSUB 101138186, Impulsa-T CSIC, 2025 LLAV 00099 and SUBLIMAT-COMTE.',
    published: true,
  },
];

/** Page copy (headings without trailing periods, per style guide). */
export const researchPage = {
  metaTitle: `Case Studies — ${brand.projectName}`,
  metaDescription: `The research carried out with ${brand.projectName} by the Functional Nanomaterials and Surfaces group — FunNanoSurf — at ICMAB-CSIC.`,
  kicker: 'Case studies',
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
