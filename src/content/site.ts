import { brand } from '../config/brand';

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
    title: `${name} — Compact Surface Deposition Device · Lab-Scale R&D`,
    description: `${name} is a compact device for direct deposition of materials on surfaces by sublimation, at lab scale. Developed at ICMAB-CSIC. Talk to the team about your research.`,
    ogAlt: `${name} — compact surface deposition device for lab-scale R&D`,
  },

  header: {
    nav: [
      { label: 'Product', href: '/product' },
      { label: 'Applications', href: '/applications' },
      { label: 'Research', href: '/research' },
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
    description: `Compact surface deposition device for lab-scale R&D. ${brand.origin}, Barcelona.`,
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
          { label: 'Research & validation', href: '/research' },
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
    bottomMono: `EP 23711114.1 · ${brand.claim}`,
  },
} as const;
