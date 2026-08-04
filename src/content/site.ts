import { brand } from '../config/brand';
import { pendingContent, pendingData } from '../config/pendingContent';

/**
 * Site-wide content: navigation, header, footer and default SEO metadata.
 * Page-specific copy lives in its own file (home.ts, product.ts, …).
 * The site ships in English only; Spanish content was retired to git history.
 */

export interface NavItem {
  label: string;
  href: string;
}

const name = brand.projectName;

export const site = {
  meta: {
    /** default/home title & description; interior pages override them */
    title: `${name} — Compact Surface Functionalization Device for Lab-Scale R&D`,
    description: `${name} is a compact lab-scale device for controlled deposition and functionalization of materials directly on final surfaces. Developed at ICMAB-CSIC for surface-related R&D.`,
    ogAlt: `${name} — compact device for direct deposition and functionalization on final surfaces`,
  },

  header: {
    nav: [
      { label: 'Product', href: '/product' },
      { label: 'Applications', href: '/applications' },
      // Label per the ICMAB review (comments #9–11); the URL stays /research.
      { label: 'Case Studies', href: '/research' },
      { label: 'Team', href: '/team' },
      { label: 'Contact', href: '/contact' },
    ] satisfies NavItem[],
    /** global primary CTA — stable across the whole site */
    cta: 'Talk to the team',
    ctaHref: '/contact',
    menuOpen: 'Open menu',
    menuClose: 'Close menu',
  },

  footer: {
    description: `Compact surface functionalization device for lab-scale R&D. ${brand.origin}, Barcelona.`,
    cta: 'Talk to the team',
    ctaHref: '/contact',
    columns: [
      {
        title: 'Product',
        links: [
          { label: 'The device', href: '/product' },
          { label: 'How it works', href: '/product#how-it-works' },
          { label: 'Parameters', href: '/product#parameters' },
          { label: 'FAQ', href: '/product#faq' },
        ],
      },
      {
        title: 'Explore',
        links: [
          { label: 'Applications', href: '/applications' },
          { label: 'Case studies', href: '/research' },
          { label: 'Team', href: '/team' },
          { label: 'Contact', href: '/contact' },
        ],
      },
      {
        title: 'Legal',
        links: [
          // TODO(client): link real privacy policy & legal notice pages
          { label: 'Privacy Policy', href: '#' },
          { label: 'Legal notice', href: '#' },
          { label: 'Contact', href: `mailto:${brand.contactEmail}` },
        ],
      },
    ] satisfies { title: string; links: NavItem[] }[],
    copyright: `© 2026 ${name} · ICMAB-CSIC · Name and brand are provisional.`,
    /* patent code renders only once the family is confirmed */
    bottomMono: pendingContent.patentFamilyConfirmed
      ? `${pendingData.patentFamilyCode} · ${brand.claim}`
      : brand.claim,
  },
} as const;
