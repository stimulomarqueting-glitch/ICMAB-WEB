import { brand } from '../../config/brand';
import { applications as en } from '../en/applications';
import type { Application } from '../types';
import type { ApplicationsPageContent } from '../en/applications';

/**
 * /applications en español. Los slugs, el orden y el estado de publicación
 * se heredan del inglés; aquí solo se traducen los textos de cada línea.
 */

const name = brand.projectName;

const translated: Record<string, Pick<Application, 'tag' | 'title' | 'shortDescription' | 'challenge' | 'use' | 'benefit'>> = {
  'organic-electronics': {
    tag: 'capas moleculares · OLED',
    title: 'Electrónica orgánica',
    shortDescription: 'Capas moleculares sobre sustratos relevantes para dispositivos.',
    challenge: 'La investigación en OLED y otros sistemas de electrónica orgánica depende de preparar y comparar muchas variaciones de capa sobre sustratos relevantes.',
    use: `${name} prepara y compara capas moleculares sobre sustratos relevantes para dispositivos, para la investigación en OLED y otros sistemas de electrónica orgánica.`,
    benefit: 'Las variaciones de capa se comparan a escala reducida antes de comprometerse con procesos a mayor escala.',
  },
  'solar-cells': {
    tag: 'perovskita y orgánicas',
    title: 'Investigación en células solares',
    shortDescription: 'Combinaciones material–superficie y preparación de interfaces.',
    challenge: 'El cribado de materiales e interfaces candidatos suele estar limitado por la disponibilidad de material y el acceso a equipos de deposición.',
    use: `${name} permite a los equipos explorar combinaciones material–superficie y la preparación de interfaces para la investigación en células solares de perovskita y orgánicas.`,
    benefit: 'Más condiciones candidatas exploradas con pequeñas cantidades de material.',
  },
  sensors: {
    tag: 'materiales receptores',
    title: 'Sensores químicos y biosensores',
    shortDescription: 'Superficies sensoras funcionalizadas con receptores candidatos.',
    challenge: 'Las capas sensoras se benefician de prepararse directamente sobre la superficie que se evaluará después.',
    use: `${name} funcionaliza superficies sensoras seleccionadas con materiales receptores candidatos para su evaluación posterior.`,
    benefit: 'La superficie preparada pasa directamente a la etapa de evaluación de tu flujo de trabajo.',
  },
  coatings: {
    tag: 'capas funcionales',
    title: 'Recubrimientos funcionales',
    shortDescription: 'Capas candidatas sobre sustratos representativos.',
    challenge: 'Los recubrimientos candidatos necesitan compararse pronto sobre superficies representativas, antes de cualquier decisión de escalado.',
    use: `${name} aplica capas funcionales candidatas sobre sustratos representativos y compara condiciones experimentales antes de pasar a procesos a mayor escala.`,
    benefit: 'Las condiciones experimentales se comparan pronto, sobre el material que importa.',
  },
  'spm-sample-preparation': {
    tag: 'microscopía de sonda de barrido',
    title: 'Preparación de muestras para SPM',
    shortDescription: 'Muestras depositadas para flujos de trabajo de microscopía de sonda de barrido.',
    challenge: 'Los flujos de trabajo de microscopía de sonda de barrido (SPM) necesitan muestras depositadas reproducibles y bien definidas.',
    use: `${name} prepara muestras depositadas para flujos de trabajo de microscopía de sonda de barrido en condiciones experimentales controladas.`,
    benefit: 'Muestras preparadas en condiciones controladas, listas para el flujo de trabajo de microscopía.',
  },
};

export const applications: Application[] = en.map((app) => {
  const t = translated[app.slug];
  if (!t) throw new Error(`[content/es/applications] falta la traducción de "${app.slug}"`);
  return { ...app, ...t };
});

export const applicationsPage: ApplicationsPageContent = {
  meta: {
    breadcrumb: 'Aplicaciones',
    title: `Aplicaciones — ${name} · Líneas de aplicación confirmadas`,
    description: `Dónde se utiliza ${name}: electrónica orgánica, incluidos los OLED, investigación en células solares de perovskita y orgánicas, sensores químicos y biosensores, recubrimientos funcionales y preparación de muestras para SPM.`,
  },
  hero: {
    kicker: 'Aplicaciones',
    kickerNote: 'casos de uso confirmados',
    title: 'Reconoce tu caso de uso',
    lead: 'Cinco líneas de aplicación confirmadas, y la exploración de nuevas combinaciones material–superficie atravesándolas todas. Cada una lleva a una conversación.',
  },
  labels: {
    section: 'Casos de uso',
    challenge: 'La necesidad',
    use: `Con ${name}`,
    benefit: 'Para tu equipo',
    cta: 'Hablar de esta aplicación',
  },
  broader: {
    kicker: 'Más allá de la ciencia de superficies',
    title: '¿No eres especialista en superficies? Esa es la idea',
    text: `${name} está diseñado para investigadores y equipos de I+D que trabajan con superficies o que exploran cómo los procesos relacionados con superficies pueden contribuir a su investigación: tanto grupos especializados en ciencia de superficies como investigadores de campos afines, departamentos de I+D corporativos y laboratorios de ensayo.`,
  },
  finalCta: {
    title: '¿Tu aplicación no está en la lista?',
    text: 'Cuéntanos la aplicación que te gustaría explorar con MatSurfer.',
    button: 'Hablar de tu caso de uso',
    href: '/contact?interest=application',
  },
};
