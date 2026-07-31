import { brand } from '../config/brand';

/**
 * /team data — add profiles here as they are confirmed by the client.
 * Only entries with `published: true` are rendered; unpublished drafts can
 * live here safely. Do NOT invent bios, roles, photos or links.
 */

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
  linkedin?: string;
  website?: string;
  /** lower numbers render first */
  order: number;
  published: boolean;
}

/**
 * Profile groups, rendered in this order when they contain published members.
 * TODO(client): confirm which groups apply once profiles are approved.
 */
export const teamGroups: { key: string; title: string }[] = [
  { key: 'scientific-leadership', title: 'Scientific leadership' },
  { key: 'technology-product', title: 'Technology and product' },
  { key: 'technology-transfer', title: 'Technology transfer' },
  { key: 'advisory', title: 'Business and advisory support' },
];

/**
 * TODO(client): add confirmed team profiles. Example entry:
 * {
 *   name: 'Full Name',
 *   role: 'Principal Investigator',
 *   affiliation: 'ICMAB-CSIC',
 *   group: 'scientific-leadership',
 *   bio: 'One or two confirmed sentences.',
 *   image: '/img/team/full-name.jpg',
 *   linkedin: 'https://www.linkedin.com/in/…',
 *   order: 1,
 *   published: true,
 * },
 */
export const teamMembers: TeamMember[] = [];

export const teamPage = {
  metaTitle: `Team — ${brand.projectName}`,
  metaDescription: `The team behind ${brand.projectName}: surface research, technology transfer and product development around a technology born at ICMAB-CSIC.`,
  kicker: 'The team',
  title: 'The team behind MatSurfer',
  lead: `${brand.projectName} brings together surface research, technology transfer and product development around a technology born at ICMAB-CSIC in Barcelona.`,
  contactTitle: 'Want to talk to the team?',
  contactText: 'For scientific, partnership or early-access enquiries:',
  finalCta: {
    title: 'Talk to the team',
    text: 'Scientific, partnership or early-access enquiries all start the same way.',
    button: 'Talk to the team',
    href: '/contact?interest=partnership',
  },
} as const;
