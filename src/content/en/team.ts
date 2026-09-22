import { brand } from '../../config/brand';
import type { TeamMember } from '../types';

/**
 * /team data — add profiles here as they are confirmed by the client.
 * Only entries with `published: true` are rendered; unpublished drafts can
 * live here safely. Do NOT invent bios, roles, photos or links.
 */

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
 * Confirmed profiles from ICMAB (Material_Web, 2026-09-16): names, roles,
 * affiliations, approved bios and LinkedIn links exactly as supplied.
 * Members are left ungrouped on purpose — four profiles read better as a
 * single row than split across `teamGroups` headings.
 *
 * Photo–name mapping confirmed by the client (2026-09-22). Three portraits
 * arrived unnamed; Núria's is still pending, so her card falls back to a
 * monogram until it is supplied.
 */
export const teamMembers: TeamMember[] = [
  {
    // TODO(client): portrait pending — the card renders a monogram instead.
    name: 'Núria Aliaga-Alcalde',
    role: 'ICREA Professor · Consultant & co-founder',
    affiliation: 'ICMAB-CSIC',
    bio: 'PhD in Chemistry, dedicated to bridging the gap between single-molecule-level cutting-edge research and practical applications in technology.',
    linkedin: 'https://www.linkedin.com/in/n%C3%BAria-aliaga-alcalde-97bba517/',
    order: 1,
    published: true,
  },
  {
    // "Científica Titular" rendered with ICMAB's own English title, Tenured Scientist.
    name: 'Arántzazu González',
    role: 'Tenured Scientist · Consultant & co-founder',
    affiliation: 'ICMAB-CSIC',
    bio: 'PhD in Chemistry, surface functionalization dreamer, always ready for new material developments and socially conscious.',
    image: '/img/team/arantzazu-gonzalez-campo.jpg',
    linkedin: 'https://www.linkedin.com/in/arantzazu-gonzalez-campo-b500798/',
    order: 2,
    published: true,
  },
  {
    name: 'Daniel Herrera',
    role: 'Postdoctoral Researcher · CTO',
    affiliation: 'ICMAB-CSIC',
    bio: 'Chemist, PhD. Synthesis and catalysis. Organic semiconductors. Expertise in knowledge and technology transfer.',
    image: '/img/team/daniel-herrera.jpg',
    linkedin: 'https://www.linkedin.com/in/dherreramiranda',
    order: 3,
    published: true,
  },
  {
    name: 'Coral Herranz Lancho',
    role: 'Innovation and Research Manager · CEO',
    affiliation: 'ICMAB-CSIC',
    bio: 'PhD in Physical Chemistry, passionate about understanding what’s underneath nature. Bridging scientific results and the market creating social value.',
    image: '/img/team/coral-herranz-lancho.jpg',
    linkedin: 'https://www.linkedin.com/in/coral-herranz-lancho-phd/',
    order: 4,
    published: true,
  },
];

export const teamPage = {
  breadcrumb: 'Team',
  profilesLabel: 'Team profiles',
  metaTitle: `Team — ${brand.projectName}`,
  metaDescription: `The team behind ${brand.projectName}: surface research, technology transfer and product development around a technology born at ICMAB-CSIC.`,
  kicker: 'The team',
  title: 'The team behind MatSurfer',
  lead: `${brand.projectName} brings together surface research, technology transfer and product development around a technology born at ICMAB-CSIC in Barcelona.`,
  contactTitle: 'Want to talk to the team?',
  contactText: 'For scientific, partnership or early-access enquiries:',
  links: {
    linkedin: 'LinkedIn',
    website: 'Website',
    /** appended to the person's name in the link's accessible name */
    linkedinOf: 'on LinkedIn',
    websiteOf: 'website',
  },
  finalCta: {
    title: 'Talk to the team',
    text: 'Scientific, partnership or early-access enquiries all start the same way.',
    button: 'Talk to the team',
    href: '/contact?interest=partnership',
  },
};

export type TeamPageContent = typeof teamPage;
