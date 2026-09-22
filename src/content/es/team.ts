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

export const teamPage: TeamPageContent = {
  breadcrumb: 'Equipo',
  profilesLabel: 'Perfiles del equipo',
  metaTitle: `Equipo — ${brand.projectName}`,
  metaDescription: `El equipo detrás de ${brand.projectName}: investigación en superficies, transferencia de tecnología y desarrollo de producto en torno a una tecnología nacida en el ICMAB-CSIC.`,
  kicker: 'El equipo',
  title: 'El equipo detrás de MatSurfer',
  lead: `${brand.projectName} reúne investigación en superficies, transferencia de tecnología y desarrollo de producto en torno a una tecnología nacida en el ICMAB-CSIC, en Barcelona.`,
  links: {
    linkedin: 'LinkedIn',
    website: 'Sitio web',
    linkedinOf: 'en LinkedIn',
    websiteOf: 'sitio web',
  },
  showcase: {
    pickerLabel: 'Miembros del equipo',
    show: 'Mostrar a',
    noBio: 'Perfil disponible próximamente.',
  },
  // TODO(client): texto PROVISIONAL, pendiente de reescritura (ver en/team.ts).
  journey: {
    kicker: 'Del laboratorio a la mesa de trabajo',
    title: 'Una tecnología, tres tipos de trabajo',
    lead: 'MatSurfer existe porque la investigación en superficies, la transferencia de tecnología y el desarrollo de producto ocurren en el mismo lugar.',
    steps: [
      {
        title: 'Investigación',
        text: 'El dispositivo nace del trabajo diario del grupo FunNanoSurf del ICMAB-CSIC, donde la funcionalización directa de superficies es una herramienta de investigación antes que un producto.',
      },
      {
        title: 'Protección y validación',
        text: 'La tecnología está protegida por una patente europea y otra española, y se ha validado externamente con grupos de investigación y una empresa, en sus propios laboratorios.',
      },
      {
        title: 'Producto',
        text: 'MatSurfer convierte esa experiencia en un dispositivo compacto de sobremesa para I+D a escala de laboratorio, con oportunidades de acceso anticipado que empiezan a abrirse.',
      },
    ],
    facts: [
      { value: '2', label: 'patentes' },
      { value: '8', label: 'grupos de investigación' },
      { value: '1', label: 'empresa' },
      { value: '2', label: 'publicaciones en acceso abierto' },
    ],
    factsNote: 'Validaciones externas y publicaciones tal como se recogen en la página de Casos de estudio.',
    factsLink: 'Ver los casos de estudio',
    factsHref: '/research',
  },
  finalCta: {
    title: 'Habla con el equipo',
    text: 'Las consultas científicas, de colaboración o de acceso anticipado empiezan con un mensaje.',
    button: 'Habla con el equipo',
    href: '/contact?interest=partnership',
    emailLead: 'O escríbenos directamente a',
  },
};
