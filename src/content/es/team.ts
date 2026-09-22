import { brand } from '../../config/brand';
import { teamMembers as en } from '../en/team';
import type { TeamMember } from '../types';
import type { TeamPageContent } from '../en/team';

/**
 * /team en español. Nombres, afiliaciones, fotos y enlaces se heredan del
 * inglés. Los cargos y las bios son traducciones de los textos aprobados
 * por ICMAB en inglés. TODO(client): que cada persona valide su versión.
 */

const translated: Record<string, Pick<TeamMember, 'role' | 'bio'>> = {
  'Núria Aliaga-Alcalde': {
    role: 'Profesora ICREA · Consultora y cofundadora',
    bio: 'Doctora en Química, dedicada a tender puentes entre la investigación puntera a nivel de molécula individual y las aplicaciones prácticas en tecnología.',
  },
  'Arántzazu González': {
    role: 'Científica Titular · Consultora y cofundadora',
    bio: 'Doctora en Química, soñadora de la funcionalización de superficies, siempre dispuesta a nuevos desarrollos de materiales y con conciencia social.',
  },
  'Daniel Herrera': {
    role: 'Investigador posdoctoral · CTO',
    bio: 'Químico, doctor. Síntesis y catálisis. Semiconductores orgánicos. Experiencia en transferencia de conocimiento y tecnología.',
  },
  'Coral Herranz Lancho': {
    role: 'Responsable de Innovación e Investigación · CEO',
    bio: 'Doctora en Química Física, apasionada por entender lo que hay debajo de la naturaleza. Une los resultados científicos y el mercado creando valor social.',
  },
};

export const teamMembers: TeamMember[] = en.map((m) => {
  const t = translated[m.name];
  if (!t) throw new Error(`[content/es/team] falta la traducción de "${m.name}"`);
  return { ...m, ...t };
});

export const teamGroups: { key: string; title: string }[] = [
  { key: 'scientific-leadership', title: 'Dirección científica' },
  { key: 'technology-product', title: 'Tecnología y producto' },
  { key: 'technology-transfer', title: 'Transferencia de tecnología' },
  { key: 'advisory', title: 'Apoyo empresarial y asesoramiento' },
];

export const teamPage: TeamPageContent = {
  breadcrumb: 'Equipo',
  profilesLabel: 'Perfiles del equipo',
  metaTitle: `Equipo — ${brand.projectName}`,
  metaDescription: `El equipo detrás de ${brand.projectName}: investigación en superficies, transferencia de tecnología y desarrollo de producto en torno a una tecnología nacida en el ICMAB-CSIC.`,
  kicker: 'El equipo',
  title: 'El equipo detrás de MatSurfer',
  lead: `${brand.projectName} reúne investigación en superficies, transferencia de tecnología y desarrollo de producto en torno a una tecnología nacida en el ICMAB-CSIC, en Barcelona.`,
  contactTitle: '¿Quieres hablar con el equipo?',
  contactText: 'Para consultas científicas, de colaboración o de acceso anticipado:',
  links: {
    linkedin: 'LinkedIn',
    website: 'Sitio web',
    linkedinOf: 'en LinkedIn',
    websiteOf: 'sitio web',
  },
  finalCta: {
    title: 'Habla con el equipo',
    text: 'Las consultas científicas, de colaboración o de acceso anticipado empiezan todas de la misma manera.',
    button: 'Habla con el equipo',
    href: '/contact?interest=partnership',
  },
};
