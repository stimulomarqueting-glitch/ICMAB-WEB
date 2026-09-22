import { brand } from '../../config/brand';
import type { TeamMember } from '../types';

/**
 * /team data — add profiles here as they are confirmed by the client.
 * Only entries with `published: true` are rendered; unpublished drafts can
 * live here safely. Do NOT invent bios, roles, photos or links.
 */

/**
 * Confirmed profiles from ICMAB (Material_Web, 2026-09-16): names, roles,
 * affiliations, approved bios and LinkedIn links exactly as supplied.
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
    showcaseImage: '/img/team/showcase/arantzazu-gonzalez-campo.webp',
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
    showcaseImage: '/img/team/showcase/daniel-herrera.webp',
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
    showcaseImage: '/img/team/showcase/coral-herranz-lancho.webp',
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
  /** full-viewport showcase at the top of the page */
  showcase: {
    /** accessible name of the avatar picker */
    pickerLabel: 'Team members',
    /** prefixed to the person's name on each avatar button */
    show: 'Show',
    /** shown instead of a bio when a profile has none yet */
    noBio: 'Profile coming soon.',
  },
  /**
   * "From the lab to the bench" — the section under the showcase.
   * TODO(client): DRAFT copy. The facts repeat figures already published in
   * the approved IP & validation text and the publications list; the step
   * texts are placeholders for Stimulo/ICMAB to rewrite.
   */
  journey: {
    kicker: 'From the lab to the bench',
    title: 'One technology, three kinds of work',
    lead: 'MatSurfer exists because surface research, technology transfer and product development happen in the same place.',
    steps: [
      {
        title: 'Research',
        text: 'The device grows out of the day-to-day work of the FunNanoSurf group at ICMAB-CSIC, where direct surface functionalization is a research tool before it is a product.',
      },
      {
        title: 'Protection and validation',
        text: 'The technology is protected by European and Spanish patents and has been validated externally by research groups and a company, in their own laboratories.',
      },
      {
        title: 'Product',
        text: 'MatSurfer turns that experience into a compact benchtop device for lab-scale R&D, with early-access opportunities opening up.',
      },
    ],
    facts: [
      { value: '2', label: 'patents' },
      { value: '8', label: 'research groups' },
      { value: '1', label: 'company' },
      { value: '2', label: 'open-access publications' },
    ],
    factsNote: 'External validations and publications as listed on the Case Studies page.',
    factsLink: 'See the case studies',
    factsHref: '/research',
  },
  finalCta: {
    title: 'Talk to the team',
    text: 'Scientific, partnership or early-access enquiries all start the same way.',
    button: 'Talk to the team',
    href: '/contact?interest=partnership',
  },
};

export type TeamPageContent = typeof teamPage;
