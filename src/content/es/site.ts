import { brand } from '../../config/brand';
import { pendingContent, pendingData } from '../../config/pendingContent';
import type { NavItem } from '../types';
import type { SiteContent } from '../en/site';

/**
 * Contenido transversal en español: navegación, cabecera, pie y metadatos
 * por defecto. Los hrefs no llevan prefijo de idioma; lo añaden los
 * componentes al renderizar.
 */

const name = brand.projectName;

export const site: SiteContent = {
  meta: {
    title: `${name} — Dispositivo compacto de funcionalización de superficies para I+D a escala de laboratorio`,
    description: `${name} es un dispositivo compacto, a escala de laboratorio, para la deposición controlada y la funcionalización de materiales directamente sobre superficies finales. Desarrollado en el ICMAB-CSIC para la I+D relacionada con superficies.`,
    ogAlt: `${name} — dispositivo compacto para la deposición y la funcionalización directas sobre superficies finales`,
  },

  header: {
    nav: [
      { label: 'Producto', href: '/product' },
      { label: 'Aplicaciones', href: '/applications' },
      { label: 'Casos de estudio', href: '/research' },
      { label: 'Equipo', href: '/team' },
      { label: 'Contacto', href: '/contact' },
    ] satisfies NavItem[],
    cta: 'Habla con el equipo',
    ctaHref: '/contact',
    menuOpen: 'Abrir menú',
    menuClose: 'Cerrar menú',
    language: 'Idioma',
  },

  a11y: {
    skipToContent: 'Saltar al contenido',
    mainNav: 'Principal',
    footerNav: 'Pie de página',
    breadcrumbHome: 'Inicio',
  },

  footer: {
    description: `Dispositivo compacto de funcionalización de superficies para I+D a escala de laboratorio. ${brand.originEs}, Barcelona.`,
    cta: 'Habla con el equipo',
    ctaHref: '/contact',
    columns: [
      {
        title: 'Producto',
        links: [
          { label: 'El dispositivo', href: '/product' },
          { label: 'Cómo funciona', href: '/product#how-it-works' },
          { label: 'Parámetros', href: '/product#parameters' },
          { label: 'Preguntas frecuentes', href: '/product#faq' },
        ],
      },
      {
        title: 'Explora',
        links: [
          { label: 'Aplicaciones', href: '/applications' },
          { label: 'Casos de estudio', href: '/research' },
          { label: 'Equipo', href: '/team' },
          { label: 'Contacto', href: '/contact' },
        ],
      },
      {
        title: 'Legal',
        links: [
          // TODO(client): enlazar las páginas reales de privacidad y aviso legal
          { label: 'Política de privacidad', href: '#' },
          { label: 'Aviso legal', href: '#' },
          { label: 'Contacto', href: `mailto:${brand.contactEmail}` },
        ],
      },
    ] satisfies { title: string; links: NavItem[] }[],
    copyright: `© 2026 ${name} · ICMAB-CSIC · Nombre y marca provisionales.`,
    bottomMono: pendingContent.patentFamilyConfirmed
      ? `${pendingData.patentFamilyCode} · ${brand.claimEs}`
      : brand.claimEs,
  },
};
