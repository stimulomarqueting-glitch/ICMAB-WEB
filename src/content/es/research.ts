import { brand } from '../../config/brand';
import {
  expertExperiences as expertsEn,
  validationItems as validationEn,
} from '../en/research';
import type { ExpertExperience, ValidationItem } from '../types';
import type { ResearchPageContent } from '../en/research';

/**
 * /research en español. Las publicaciones se comparten con el inglés (los
 * títulos son los originales). La valoración del experto se publica en su
 * castellano original, con la misma selección de pasajes que la versión
 * inglesa (ver el comentario en en/research.ts).
 */

export { publications } from '../en/research';

const expertsEs: Record<string, Pick<ExpertExperience, 'name' | 'role' | 'field' | 'quote'>> = {
  'Dr Diego Gutiérrez Yatacue': {
    name: 'Dr. Diego Gutiérrez Yatacue',
    // Cargo tal como figura en el documento de ICMAB (en inglés).
    role: 'Innovation and Technology Manager',
    field: 'Evaluación tecnológica',
    quote:
      'La experiencia general con MatSurfer ha sido positiva. El equipo cumple adecuadamente su propósito como herramienta de cribado rápido y de bajo coste para experimentos de sublimación en laboratorio, especialmente en entornos académicos. Creo que vendría muy bien a la hora de hacer experimentos en paralelo sobre diferentes superficies o condiciones de sublimación para optimizar condiciones de manera rápida; creo que esto presenta una ventaja en relación con otras técnicas. Me sorprendió la robustez que tiene frente a la temperatura. El equipo tiene potencial para utilidad científica más que de escalado.',
  },
};

export const expertExperiences: ExpertExperience[] = expertsEn.map((e) => {
  const t = expertsEs[e.name];
  if (!t) throw new Error(`[content/es/research] falta la traducción de "${e.name}"`);
  return { ...e, ...t };
});

/**
 * Traducción del texto aprobado por Transferencia de Tecnología (el original
 * aprobado es el inglés). TODO(client): que ICMAB valide esta versión.
 */
const validationEs: Pick<ValidationItem, 'title' | 'text'>[] = [
  {
    title: 'Propiedad intelectual y validación tecnológica',
    text: 'La tecnología se ha desarrollado en el Instituto de Ciencia de Materiales de Barcelona (ICMAB-CSIC) y está protegida por las siguientes patentes: la patente unitaria europea EP4483979B1 y la patente española ES2949292B2. Se han validado prototipos operativos de forma externa, en su entorno operacional, con 8 grupos de investigación y 1 empresa en los siguientes campos: electrónica orgánica, biosensores, nuevos materiales de superficie y preparación de muestras para EPR. La tecnología también se ha probado en operación real a lo largo del tiempo en laboratorios de I+D de electrónica orgánica y nuevos materiales de superficie. Se requiere, y se está desarrollando, una validación externa adicional en entorno operacional y a lo largo del tiempo en otros campos de aplicación. Además, la tecnología ha contado con el apoyo de programas competitivos de investigación y de transferencia tecnológica: ERC-PoC SmolSUB 101138186, Impulsa-T CSIC, 2025 LLAV 00099 y SUBLIMAT-COMTE.',
  },
];

export const validationItems: ValidationItem[] = validationEn.map((v, i) => {
  const t = validationEs[i];
  if (!t) throw new Error(`[content/es/research] falta la traducción del bloque de validación ${i}`);
  return { ...v, ...t };
});

export const researchPage: ResearchPageContent = {
  breadcrumb: 'Casos de estudio',
  metaTitle: `Casos de estudio — ${brand.projectName}`,
  metaDescription: `La investigación realizada con ${brand.projectName} por el grupo de Nanomateriales y Superficies Funcionales (FunNanoSurf) del ICMAB-CSIC.`,
  kicker: 'Casos de estudio',
  title: 'Nacido en el laboratorio',
  lead: `${brand.projectName} nace de la investigación diaria del grupo de Nanomateriales y Superficies Funcionales (FunNanoSurf) del ICMAB-CSIC. Esta página recoge la investigación realizada con el dispositivo y las experiencias de los investigadores que trabajan con él.`,
  experiencesTitle: 'Experiencias de expertos',
  publicationsTitle: 'Publicaciones',
  moreLink: 'Más sobre esta experiencia',
  inviteTitle: '¿Has trabajado con el dispositivo?',
  inviteText: 'Nos gustaría recoger aquí tu experiencia o tu publicación:',
  finalCta: {
    title: 'Explora una colaboración',
    text: 'Las validaciones, las experiencias de los investigadores y los trabajos publicados nacen de conversaciones.',
    button: 'Explora una colaboración',
    href: '/contact?interest=research',
  },
};
