import { brand } from '../../config/brand';
import type { ProductContent } from '../en/product';

/**
 * /product en español. Sublimación y evaporación se nombran solo donde se
 * explica la técnica, como en la versión inglesa; sin cifras no validadas.
 */

const name = brand.projectName;

export const product: ProductContent = {
  meta: {
    breadcrumb: 'Producto',
    title: `Producto — ${name} · Dispositivo compacto de funcionalización de superficies`,
    description: `Qué es ${name} y cómo funciona: deposición controlada con una distancia fuente–sustrato ajustable, que transfiere materiales directamente sobre sustratos finales a escala de laboratorio.`,
  },

  hero: {
    kicker: 'El producto',
    title: 'Un dispositivo compacto para la funcionalización directa de superficies',
    lead: `${name} deposita y funcionaliza materiales directamente sobre sustratos finales, a escala de laboratorio: una cámara de vidrio compacta, de sobremesa, diseñada para la I+D relacionada con superficies.`,
    cta: 'Solicitar información del producto',
    ctaHref: '/contact?interest=product',
    photoAlt: `La cámara de vidrio de ${name} sobre una mesa, junto a su documentación y un sustrato de muestra`,
  },

  whatItIs: {
    kicker: 'Qué es',
    title: 'La tecnología',
    lead: `${name} es un dispositivo compacto para la deposición controlada y la funcionalización de materiales directamente sobre superficies finales. Una pequeña cantidad de material de partida se transfiere, a través de una distancia fuente–sustrato ajustable y en condiciones controladas de temperatura y presión, al sustrato seleccionado para el experimento, mediante sublimación o evaporación controladas.`,
  },

  differentiator: {
    kicker: 'Diferenciador clave',
    title: 'Distancia fuente–sustrato controlable',
    text: 'La distancia entre la fuente y el sustrato es ajustable, lo que permite a los investigadores afinar la geometría experimental directamente durante la preparación: un parámetro más bajo control, en la misma mesa de trabajo.',
    features: [
      { title: 'Deposición directa sobre el sustrato final', text: 'El sistema de superficie que creas es el sistema de superficie que mides.' },
      { title: 'Experimentación a pequeña escala', text: 'Trabajo a escala reducida sobre muestras de 1–3 cm² con pequeñas cantidades de material.' },
      { title: 'Formato compacto de laboratorio', text: 'Un dispositivo compacto de sobremesa, de menos de 20 cm, diseñado para los laboratorios de I+D existentes.' },
      { title: 'Bajo consumo de muestra', text: 'Unos pocos miligramos pueden ser suficientes: los materiales novedosos, costosos o muy valiosos siguen siendo viables para ensayar.' },
      { title: 'Configuración modular', text: 'Un montaje modular con un flujo de trabajo sencillo y adaptable.' },
      { title: 'Proceso sin disolventes', text: 'El material llega al sustrato sin disolventes ni pasos intermedios.' },
    ],
  },

  howItWorks: {
    kicker: 'Cómo funciona',
    title: 'Del material de partida a la superficie preparada',
    video: {
      caption: `${name} en funcionamiento: del material de partida a la superficie preparada.`,
      placeholder: 'Vídeo del dispositivo próximamente',
      playLabel: 'Reproducir el vídeo del dispositivo',
    },
    steps: [
      { title: 'Carga el material de partida', text: 'Coloca una pequeña cantidad de material en el soporte de la fuente.' },
      { title: 'Ajusta la distancia y los parámetros', text: 'Ajusta primero la distancia fuente–sustrato y, después, la temperatura, la presión y el tiempo.' },
      { title: 'Deposita directamente sobre la superficie final', text: 'El material se transfiere directamente al sustrato seleccionado mediante sublimación o evaporación controladas.' },
      { title: 'Retira la muestra preparada', text: 'Extrae la muestra depositada para su análisis posterior, o prepara la siguiente condición experimental.' },
    ],
  },

  parameters: {
    kicker: 'Parámetros',
    title: 'Cada deposición, definida por valores medibles',
    lead: 'Cada experimento queda definido por un pequeño conjunto de parámetros controlados. Los rangos concretos dependen de la configuración y se comparten durante la conversación técnica.',
    items: [
      { symbol: 'd', label: 'distancia fuente–sustrato' },
      { symbol: 'm', label: 'masa de muestra' },
      { symbol: 'P', label: 'presión' },
      { symbol: 'T', label: 'temperatura' },
      { symbol: 't', label: 'tiempo' },
    ],
  },

  benefits: {
    kicker: 'Beneficios',
    title: 'Lo que el dispositivo cambia para tu equipo',
    outcomes: [
      {
        icon: 'material', title: 'Menos material por respuesta',
        text: 'Gracias al tamaño reducido de la tecnología, unos pocos miligramos pueden ser suficientes: algo decisivo para moléculas novedosas y costosas o materiales muy valiosos, donde las decisiones se toman con miligramos.',
      },
      {
        icon: 'conditions', title: 'Más condiciones ensayadas por semana',
        text: 'Deposiciones directas, rápidas y sencillas que facilitan explorar más condiciones experimentales en ciclos de investigación más cortos.',
      },
      {
        icon: 'group', title: 'Autonomía para todo el grupo',
        text: 'Un flujo de trabajo compacto y sencillo facilita integrar la deposición sobre superficies en el laboratorio y utilizarla en todo el equipo de investigación.',
      },
      {
        icon: 'defensible', title: 'Resultados defendibles',
        text: 'El material se deposita directamente sobre el sustrato seleccionado, de modo que la superficie que evalúas es la superficie que preparaste.',
      },
    ],
  },

  faq: {
    kicker: 'Preguntas frecuentes',
    title: 'Con más detalle',
    items: [
      {
        q: '¿Cómo funciona?',
        a: `${name} crea condiciones controladas para transferir material desde una fuente directamente a un sustrato seleccionado. La distancia fuente–sustrato ajustable ayuda a los investigadores a definir la geometría experimental, mientras que la temperatura, la presión y el tiempo pueden adaptarse al material y al objetivo. El proceso produce una superficie preparada que después puede retirarse para su análisis o utilizarse en la siguiente etapa del flujo de trabajo de investigación. El sistema de superficie que creas es el sistema de superficie que mides.`,
      },
      {
        q: '¿Para qué puede utilizarse?',
        a: `${name} da soporte a flujos de trabajo de I+D que requieren deposición controlada o funcionalización sobre superficies seleccionadas. Entre los campos potenciales están la electrónica orgánica, la investigación en células solares, los sensores químicos y biosensores, los recubrimientos funcionales, la preparación de muestras para SPM (microscopía de sonda de barrido) y la exploración de nuevas combinaciones material–superficie.`,
      },
      {
        q: '¿Para quién es?',
        a: `${name} está diseñado para investigadores y equipos de I+D que trabajan con superficies o que exploran cómo los procesos relacionados con superficies pueden contribuir a su investigación. Esto incluye grupos especializados en ciencia de superficies, investigadores de campos afines, departamentos de I+D corporativos, laboratorios de ensayo y posibles socios de equipamiento de laboratorio.`,
      },
      {
        q: '¿Qué lo hace diferente?',
        a: `${name} combina el trabajo directo sobre el sustrato final, una distancia fuente–sustrato controlable y la experimentación a escala reducida en un formato compacto. Su configuración modular y su flujo de trabajo sencillo están pensados para facilitar la integración del cribado material–superficie en los laboratorios de I+D.`,
      },
      {
        q: '¿Cuánto material se necesita?',
        a: 'Una pequeña cantidad: gracias al tamaño reducido de la tecnología, unos pocos miligramos pueden ser suficientes. Esto importa para moléculas novedosas y costosas o materiales muy valiosos, donde las decisiones se toman con miligramos. Las cantidades concretas dependen del material y de la configuración, y forman parte de la conversación técnica.',
      },
      {
        q: '¿Puede utilizarse en un laboratorio de I+D existente?',
        a: 'Sí: está diseñado como equipamiento compacto de sobremesa para laboratorios existentes, y complementa los instrumentos que ya utilizas con una huella de laboratorio reducida.',
      },
      {
        q: '¿Cómo puedo plantear una posible aplicación?',
        a: 'Cuéntanos el material, el sustrato o el flujo de trabajo que te gustaría explorar. Revisamos cada solicitud y volvemos a contactar contigo para hablar del siguiente paso: empieza por la página de contacto.',
      },
    ] as { q: string; a: string; published?: boolean }[],
  },

  finalCta: {
    title: 'Hablemos de tu investigación',
    text: 'Cuéntanos la aplicación que te gustaría explorar con MatSurfer.',
    button: 'Hablemos de tu investigación',
    href: '/contact?interest=product',
  },
};
