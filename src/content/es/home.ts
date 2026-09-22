import { brand } from '../../config/brand';
import { pendingContent, pendingData } from '../../config/pendingContent';
import type { HomeContent } from '../en/home';

/**
 * Portada en español. Misma línea editorial que el inglés: la
 * funcionalización de superficies como concepto central, sin afirmaciones
 * de caracterización ni comparaciones con instalaciones.
 */

const name = brand.projectName;

export const home: HomeContent = {
  hero: {
    eyebrow: pendingContent.patentFamilyConfirmed
      ? `Tecnología del ICMAB-CSIC · Patentada (${pendingData.patentFamilyCode})`
      : 'Tecnología del ICMAB-CSIC',
    headline: 'Funcionalización directa de superficies, en formato compacto',
    descriptor: 'Dispositivo compacto de funcionalización de superficies · I+D a escala de laboratorio',
    lead: 'Deposita y criba materiales directamente sobre superficies finales con un dispositivo compacto diseñado para la experimentación a escala de laboratorio.',
    ctaPrimary: 'Habla con el equipo',
    ctaSecondary: 'Explora el producto',
    photoAlt: `La cámara de vidrio de ${name} sobre una mesa, junto a su documentación y un sustrato de muestra`,
  },

  value: {
    label: 'Propuesta de valor',
    items: [
      {
        tag: 'El problema',
        text: 'Los primeros experimentos con superficies pueden depender de equipamiento especializado, de flujos de trabajo ya establecidos y de más material del que los equipos tienen disponible.',
      },
      {
        tag: name,
        text: 'Una cámara de vidrio compacta que deposita el material directamente sobre tu sustrato final, a escala reducida.',
      },
      {
        tag: 'El resultado',
        text: 'Combinaciones material–superficie exploradas en tu propio laboratorio, por todo el equipo.',
      },
    ],
  },

  product: {
    kicker: 'La tecnología',
    title: 'La funcionalización de superficies, en la mesa del laboratorio',
    lead: `Una distancia fuente–sustrato controlable da a los investigadores control directo sobre la geometría experimental. ${name} utiliza condiciones controladas de temperatura y presión para transferir el material al sustrato final en una cámara de vidrio compacta.`,
    cta: 'Explora el producto',
    ctaHref: '/product',
    photoAlt: `Detalle de la cámara de vidrio de ${name} y de sus componentes mecanizados`,
  },

  benefits: {
    kicker: 'Beneficios',
    title: 'Lo que gana tu equipo',
    outcomes: [
      { title: 'Menos material por respuesta', text: 'Unos pocos miligramos pueden ser suficientes: los materiales costosos o valiosos siguen siendo viables para ensayar.' },
      { title: 'Más condiciones ensayadas por semana', text: 'Deposiciones directas, rápidas y sencillas que acortan los ciclos de investigación.' },
      { title: 'Autonomía para todo el grupo', text: 'Un flujo de trabajo compacto que puede utilizar todo el equipo de investigación.' },
      { title: 'Resultados defendibles', text: 'La superficie que evalúas es la superficie que preparaste.' },
    ],
  },

  applications: {
    kicker: 'Aplicaciones',
    title: 'Un dispositivo, muchos objetivos de investigación',
    lead: 'Cinco líneas de aplicación confirmadas, de la electrónica orgánica a la preparación de muestras para SPM, con nuevas combinaciones material–superficie atravesándolas todas.',
    isotype: {
      alt: `El isotipo de la cápsula de ${name} con sus cinco campos de aplicación: electrónica orgánica, sensores, perovskitas, recubrimientos y SPM`,
      caption: 'Un dispositivo, distintos objetivos de investigación según el campo.',
      labels: ['Electrónica orgánica', 'Sensores', 'Perovskitas', 'Recubrimientos', 'SPM'] as [string, string, string, string, string],
    },
    cta: 'Explora las aplicaciones',
    ctaHref: '/applications',
  },

  audiences: {
    kicker: 'Para quién es',
    title: 'Pensado para cuatro tipos de equipo',
    groups: [
      { icon: 'surface', title: 'Grupos de investigación académicos', text: 'Experimentación a escala reducida que complementa los flujos de trabajo establecidos.' },
      { icon: 'lab', title: 'Investigadores que se inician en superficies', text: 'Un punto de partida accesible para equipos que exploran la investigación relacionada con superficies.' },
      { icon: 'insitu', title: 'I+D corporativa y laboratorios de ensayo', text: 'Preparación y cribado de superficies con una huella de laboratorio reducida.' },
      { icon: 'modularity', title: 'Socios de equipamiento de laboratorio', text: 'Una incorporación de sobremesa diferenciada para las carteras de equipamiento de laboratorio.' },
    ],
  },

  explore: {
    researchTitle: 'Casos de estudio',
    researchText: 'La investigación realizada con el dispositivo por el grupo FunNanoSurf del ICMAB-CSIC.',
    researchCta: 'Explora los casos de estudio',
    researchHref: '/research',
    teamTitle: 'El equipo',
    teamText: 'Las personas que unen investigación en superficies, transferencia de tecnología y desarrollo de producto.',
    teamCta: 'Conoce al equipo',
    teamHref: '/team',
  },

  finalCta: {
    title: 'Lleva los experimentos con superficies a tu propia mesa de laboratorio',
    text: 'Cuéntanos tus necesidades de investigación y qué te gustaría explorar.',
    button: 'Habla con el equipo',
    href: '/contact',
  },
};
