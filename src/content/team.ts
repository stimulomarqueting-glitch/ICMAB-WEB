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
  /** short bio, when available */
  bio?: string;
  /** path under /public, e.g. '/img/team/jane-doe.jpg' */
  image?: string;
  linkedin?: string;
  /** lower numbers render first */
  order: number;
  published: boolean;
}

/**
 * TODO(client): add confirmed team profiles. Example entry:
 * {
 *   name: 'Full Name',
 *   role: 'Principal Investigator',
 *   affiliation: 'ICMAB-CSIC',
 *   bio: 'One or two confirmed sentences.',
 *   image: '/img/team/full-name.jpg',
 *   linkedin: 'https://www.linkedin.com/in/…',
 *   order: 1,
 *   published: true,
 * },
 */
export const teamMembers: TeamMember[] = [];

/** Page copy (headings without trailing periods, per style guide). */
export const teamPage = {
  metaTitle: `Team — ${brand.projectName}`,
  metaDescription: `The team behind ${brand.projectName}: surface research, technology transfer and product development around a technology born at ICMAB-CSIC.`,
  kicker: 'The team',
  title: 'The people behind the device',
  lead: `${brand.projectName} brings together surface research, technology transfer and product development around a technology born at ICMAB-CSIC in Barcelona.`,
  contactTitle: 'Want to talk to the team?',
  contactText: 'For scientific, partnership or early-access enquiries:',
};
