/**
 * Shared content shapes. The English files are the reference: each exports
 * its object's type so the Spanish files must match it key for key.
 */

export interface NavItem {
  label: string;
  href: string;
}

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

export interface ValidationItem {
  title: string;
  text: string;
  published: boolean;
}

export interface TeamMember {
  name: string;
  role: string;
  affiliation: string;
  /** grouping key — renders under the matching heading in `teamGroups` */
  group?: string;
  /** short bio, when available */
  bio?: string;
  /** path under /public, e.g. '/img/team/jane-doe.jpg' */
  image?: string;
  /**
   * Cut-out portrait with a transparent background for the full-viewport
   * showcase (WebP with alpha). Falls back to a monogram when missing.
   */
  showcaseImage?: string;
  linkedin?: string;
  website?: string;
  /** lower numbers render first */
  order: number;
  published: boolean;
}
