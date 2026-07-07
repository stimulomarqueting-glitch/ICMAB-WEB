import type { SiteContent } from './types';
import { brand } from '../config/brand';
import { leadCapture } from '../config/leadCapture';

const name = brand.projectName;

export const es: SiteContent = {
  meta: {
    title: `${name} — Deposición compacta sobre superficies para investigación en materiales avanzados`,
    description: `${name} es un dispositivo compacto de escala de laboratorio para la deposición directa de materiales sobre superficies, que ayuda a los equipos de investigación a explorar OLEDs, sensores, electrónica orgánica y materiales avanzados más rápido.`,
    ogAlt: `${name} — dispositivo compacto de deposición sobre superficies para laboratorios de investigación`,
  },

  header: {
    nav: [
      { label: 'Tecnología', href: '#technology' },
      { label: 'Aplicaciones', href: '#applications' },
      { label: 'Beneficios', href: '#benefits' },
      { label: 'Para quién', href: '#who-is-it-for' },
      { label: 'Contacto', href: '#waitlist' },
    ],
    cta: 'Únete a la lista de espera',
    menuOpen: 'Abrir menú',
    menuClose: 'Cerrar menú',
    langLabel: 'Idioma',
  },

  hero: {
    eyebrow: brand.claimEs,
    headline: 'Experimentar con superficies,',
    headlineAccent: 'ahora es simple.',
    subheadline: `${name} es un dispositivo compacto de escala de laboratorio diseñado para hacer la deposición directa de materiales sobre superficies más rápida, sencilla y accesible para equipos de investigación — sin infraestructuras complejas ni instalaciones especializadas.`,
    ctaPrimary: 'Únete a la lista de espera',
    ctaSecondary: 'Descubre la tecnología',
    microcopy: `${brand.originEs} · Tecnología protegida por patente`,
    stats: [
      { value: '1–3 cm²', label: 'escala de trabajo de las muestras' },
      { value: '< 20 cm', label: 'formato de sobremesa' },
      { value: 'Directa', label: 'deposición sobre el sustrato final' },
    ],
    visualCaption: 'Visual conceptual — próximamente fotografías reales del dispositivo.',
    visualAlt: `Esquema abstracto del dispositivo ${name}: partículas de material depositándose desde una fuente sobre una superficie objetivo`,
  },

  problem: {
    kicker: 'El problema',
    title: 'Trabajar con superficies sigue siendo más difícil de lo que debería.',
    intro:
      'Las superficies funcionales están detrás de OLEDs, sensores, recubrimientos y dispositivos de nueva generación. Sin embargo, para muchos equipos, probar un material sobre una superficie real sigue siendo un proceso lento y caro.',
    points: [
      {
        icon: 'building',
        title: 'Infraestructura pesada',
        text: 'El trabajo avanzado con superficies suele depender de equipos complejos, instalaciones dedicadas y personal experimentado — recursos de los que muchos laboratorios no disponen.',
      },
      {
        icon: 'clock',
        title: 'Validación inicial lenta',
        text: 'Los primeros ensayos sobre superficies suelen ser lentos y consumir mucho material, convirtiendo la pregunta “¿funciona sobre mi sustrato?” en un proyecto largo.',
      },
      {
        icon: 'flask',
        title: 'Material valioso desperdiciado',
        text: 'Los flujos de trabajo estándar consumen mucho más compuesto del que necesita el experimento — un coste real cuando el material es caro o solo hay miligramos.',
      },
      {
        icon: 'gap',
        title: 'Un hueco en el flujo de trabajo',
        text: 'Entre descubrir un material prometedor y validarlo sobre una superficie práctica falta una herramienta: algo simple, directo y a escala de laboratorio.',
      },
    ],
    bridge: `${name} nace para cerrar ese hueco.`,
  },

  technology: {
    kicker: 'La tecnología',
    title: 'Un dispositivo compacto de deposición que lleva el experimento a tu mesa de laboratorio.',
    intro: `${name} es una familia de dispositivos compactos para la deposición directa de materiales sobre superficies, nacida de la investigación en sublimación del ICMAB-CSIC y diseñada a partir de necesidades reales de laboratorio.`,
    whatIsIt: {
      title: '¿Qué es?',
      text: `En esencia, ${name} te permite tomar un material de interés y depositarlo directamente sobre la superficie, sustrato o dispositivo que realmente te importa — dentro de un entorno compacto y controlado que cabe en una mesa de laboratorio estándar. Sin reservar sala blanca. Sin grandes sistemas de vacío. Sin operador dedicado.`,
    },
    stepsTitle: 'Cómo funciona — en cuatro pasos',
    steps: [
      {
        title: 'Carga el material',
        text: 'Coloca una pequeña cantidad de tu compuesto en la fuente de material. Los experimentos están pensados para un consumo mínimo.',
      },
      {
        title: 'Deposición controlada',
        text: 'El material se transfiere por sublimación/deposición controlada en una cámara compacta, con distancia fuente-sustrato ajustable.',
      },
      {
        title: 'Directo a tu superficie',
        text: 'El material se deposita directamente sobre tu sustrato objetivo — una superficie de prueba, un electrodo o un dispositivo en desarrollo.',
      },
      {
        title: 'Explora y valida',
        text: 'Caracteriza la superficie funcional, itera condiciones, cambia sustratos y pasa rápido de la idea a la evidencia.',
      },
    ],
    featuresTitle: 'Diseñado para el trabajo real de laboratorio',
    features: [
      {
        icon: 'resize',
        title: 'Pequeña escala por diseño',
        text: 'Trabaja con muestras de 1–3 cm² — el tamaño adecuado para cribar materiales y validar condiciones antes de escalar.',
      },
      {
        icon: 'tune',
        title: 'Geometría ajustable',
        text: 'Ajusta la distancia entre la fuente de material y el sustrato para explorar distintas condiciones experimentales.',
      },
      {
        icon: 'modular',
        title: 'Modular y adaptable',
        text: 'Un formato compacto y modular con piezas reemplazables, diseñado para integrarse en procedimientos y equipos ya existentes.',
      },
      {
        icon: 'user',
        title: 'Flujo de trabajo accesible',
        text: 'Pensado para usarse sin una especialización profunda en técnicas de superficie, para que más equipos hagan sus propios experimentos.',
      },
    ],
    schematicAlt:
      'Diagrama conceptual del flujo: fuente de material, sublimación y deposición controlada, sustrato objetivo, exploración de la superficie funcional',
    schematicNote: 'Esquema conceptual simplificado — se sustituirá por el esquema técnico del dispositivo.',
  },

  benefits: {
    kicker: 'Beneficios clave',
    title: 'Por qué interesa a los equipos de investigación.',
    intro: 'Un camino más simple de la idea de material a la validación en superficie.',
    items: [
      {
        icon: 'target',
        title: 'Deposición directa sobre superficie',
        text: 'Deposita el material directamente sobre el sustrato o dispositivo que importa — sin pasos intermedios de transferencia.',
      },
      {
        icon: 'bench',
        title: 'Flujo compacto a escala de laboratorio',
        text: 'Un formato de sobremesa que no exige salas dedicadas, grandes sistemas de vacío ni instalaciones especializadas.',
      },
      {
        icon: 'drop',
        title: 'Menor consumo de material',
        text: 'Realiza experimentos significativos con pequeñas cantidades de compuesto — ideal para materiales caros o escasos.',
      },
      {
        icon: 'fast',
        title: 'Iteraciones más rápidas',
        text: 'Prepara, deposita, caracteriza, repite. Ciclos más cortos significan más condiciones probadas y decisiones más rápidas.',
      },
      {
        icon: 'tune',
        title: 'Configuración experimental ajustable',
        text: 'Controla parámetros clave como la distancia fuente-sustrato para explorar cómo las condiciones definen tu superficie.',
      },
      {
        icon: 'unlock',
        title: 'I+D de superficies accesible',
        text: 'Abre la experimentación con superficies a equipos sin infraestructura previa — exploración antes de escalar.',
      },
    ],
  },

  applications: {
    kicker: 'Aplicaciones',
    title: 'Superficies para…',
    intro:
      'La deposición es el punto de partida — no el límite. La misma plataforma compacta permite explorar campos muy distintos. Allí donde un material se encuentra con una superficie, hay un experimento que merece la pena.',
    surfacesForLabel: 'Superficies para',
    items: [
      {
        name: 'OLEDs',
        short: 'OLEDs',
        text: 'Explora la deposición de moléculas orgánicas para dispositivos emisores de luz y cribado de materiales.',
      },
      {
        name: 'Sensores',
        short: 'Sensores',
        text: 'Prueba capas funcionales directamente sobre sustratos objetivo para investigación en sensores químicos y biosensores.',
      },
      {
        name: 'Electrónica orgánica',
        short: 'Electrónica orgánica',
        text: 'Prototipa interacciones con superficies y comportamientos de materiales para semiconductores orgánicos.',
      },
      {
        name: 'Recubrimientos',
        short: 'Recubrimientos',
        text: 'Experimenta con capas finas funcionales y estudia combinaciones material–superficie.',
      },
      {
        name: 'Biomateriales',
        short: 'Biomateriales',
        text: 'Explora rutas de modificación de superficies en contextos de investigación, a una escala manejable.',
      },
      {
        name: 'Investigación en materiales avanzados',
        short: 'Materiales avanzados',
        text: 'Pasa del concepto de material a la validación en superficie — de moléculas nuevas a MOFs y más allá.',
      },
    ],
    openEnded:
      '¿Trabajas en algo distinto? La plataforma es deliberadamente abierta — cuéntanos tu reto con superficies.',
  },

  audiences: {
    kicker: 'Para quién',
    title: 'Pensado para quienes trabajan con superficies — y para quienes quieren empezar.',
    intro:
      'Desde especialistas en ciencia de superficies que necesitan una herramienta rápida a pequeña escala, hasta equipos de otros campos que buscan una primera vía sencilla hacia la experimentación con superficies.',
    groups: [
      { icon: 'atom', title: 'Grupos de investigación', text: 'Grupos de química de superficies, física de superficies y ciencia de materiales en universidades y centros de investigación.' },
      { icon: 'school', title: 'Laboratorios universitarios', text: 'Laboratorios docentes y de investigación que quieren experimentación práctica con superficies sin grandes infraestructuras.' },
      { icon: 'rnd', title: 'Equipos de I+D', text: 'Departamentos de I+D industriales en electrónica orgánica, fotovoltaica, sensores y recubrimientos.' },
      { icon: 'layers', title: 'Empresas de materiales', text: 'Equipos que desarrollan o prueban nuevos compuestos y necesitan validación rápida sobre sustratos reales.' },
      { icon: 'chip', title: 'Desarrolladores de dispositivos', text: 'Equipos de prototipado que quieren depositar materiales directamente sobre dispositivos en desarrollo.' },
      { icon: 'handshake', title: 'Partners de equipamiento', text: 'Distribuidores y empresas de suministro de laboratorio que buscan ampliar su catálogo.' },
      { icon: 'transfer', title: 'Transferencia e innovación', text: 'Oficinas de transferencia tecnológica, inversores y equipos de innovación que exploran tecnologías emergentes de laboratorio.' },
    ],
    quote: `Tanto si exploras una molécula nueva, validas un tratamiento de superficie o buscas una forma más accesible de probar capas funcionales, ${name} está diseñado para reducir la barrera entre las ideas de materiales y los experimentos en superficie.`,
  },

  credibility: {
    kicker: 'Origen y credibilidad',
    title: 'Nacido en el laboratorio. Hecho para el laboratorio.',
    intro: `${name} surge de la investigación del Institut de Ciència de Materials de Barcelona (ICMAB-CSIC), moldeado por las necesidades cotidianas de científicos en activo y no por un diseño de producto abstracto.`,
    facts: [
      {
        icon: 'lab',
        title: 'Investigación ICMAB-CSIC',
        text: 'Desarrollado a partir de la investigación del ICMAB-CSIC, uno de los centros de referencia en ciencia de materiales en Europa.',
      },
      {
        icon: 'shield',
        title: 'Protegido por patente',
        text: 'La tecnología está protegida por una familia de patentes española y europea.',
      },
      {
        icon: 'gear',
        title: 'Prototipos funcionales',
        text: 'Existen prototipos funcionales de laboratorio en desarrollo y mejora activa, con uso experimental real.',
      },
      {
        icon: 'rocket',
        title: 'Fase de transferencia',
        text: 'Actualmente en fase de acceso anticipado y transferencia tecnológica, con el apoyo de programas competitivos de investigación y emprendimiento.',
      },
    ],
    logosTitle: 'Entorno institucional',
    logoPlaceholders: ['ICMAB', 'CSIC', 'FGCSIC'],
    phaseNote:
      'Logos mostrados como placeholders a la espera de aprobación institucional. Aquí se añadirán los logos definitivos de partners y programas.',
  },

  conversion: {
    technology: {
      title: '¿No sabes si encaja en tu flujo de trabajo?',
      text: 'Cada laboratorio y cada material son distintos. Cuéntanos qué quieres depositar, sobre qué sustrato y a qué escala — el equipo te ayudará a evaluar si la deposición compacta encaja en tu caso.',
      button: 'Comenta tu aplicación',
    },
    applications: { button: 'Cuéntanos tu reto con superficies' },
    audiences: { button: `Descubre si ${name} encaja en tu laboratorio` },
    footer: { button: 'Contacta con el equipo' },
  },

  waitlist: {
    kicker: 'Acceso anticipado',
    title: 'Trae tu reto de superficies al equipo.',
    intro: `${name} está recogiendo el interés de investigadores, equipos de I+D y partners que exploran la deposición compacta sobre superficies. Cuéntanos en qué estás trabajando y te ayudaremos a evaluar si la tecnología encaja con tu aplicación.`,
    reasons: [
      'Acceso anticipado a medida que se abran oportunidades de prueba',
      'Conversación técnica directa con el equipo investigador',
      'Posibilidad de comentar pilotos, validaciones y aplicaciones concretas',
      'Estar entre los primeros laboratorios y partners implicados',
    ],
    form: {
      groups: { about: 'Sobre ti', organisation: 'Tu organización', project: 'Tu proyecto' },
      fullName: { label: 'Nombre completo', placeholder: 'Tu nombre completo', error: 'Introduce tu nombre.' },
      email: { label: 'Email de trabajo', placeholder: 'tu@institucion.org', error: 'Introduce una dirección de email válida.' },
      organization: { label: 'Organización', placeholder: 'Universidad, empresa o institución', error: 'Introduce tu organización.' },
      role: { label: 'Cargo / posición', placeholder: 'p. ej. Investigador/a principal, Ingeniero/a de I+D' },
      country: { label: 'País', placeholder: 'p. ej. España' },
      organizationType: {
        label: 'Tipo de organización',
        error: 'Elige una opción.',
        options: [
          { value: 'university-research', label: 'Universidad / centro de investigación' },
          { value: 'corporate-rd', label: 'I+D corporativo' },
          { value: 'startup-spinoff', label: 'Startup / spin-off' },
          { value: 'distributor', label: 'Distribuidor de equipamiento de laboratorio' },
          { value: 'investor-tt', label: 'Inversor / transferencia tecnológica' },
          { value: 'other', label: 'Otro' },
        ],
      },
      areaOfInterest: {
        label: 'Área de interés',
        options: [
          { value: 'oleds', label: 'OLEDs' },
          { value: 'sensors', label: 'Sensores' },
          { value: 'organic-electronics', label: 'Electrónica orgánica' },
          { value: 'coatings', label: 'Recubrimientos' },
          { value: 'biomaterials', label: 'Biomateriales' },
          { value: 'advanced-materials', label: 'Investigación en materiales avanzados' },
          { value: 'other', label: 'Otra' },
        ],
      },
      currentNeed: {
        label: '¿Qué describe mejor tu necesidad actual?',
        error: 'Elige una opción.',
        options: [
          { value: 'test-device', label: 'Quiero probar el dispositivo' },
          { value: 'technical-info', label: 'Quiero información técnica' },
          { value: 'research-application', label: 'Quiero comentar una aplicación de investigación' },
          { value: 'distribution-partnership', label: 'Me interesa la distribución / partnership' },
          { value: 'evaluating-equipment', label: 'Estoy evaluando equipamiento de laboratorio' },
          { value: 'other', label: 'Otra' },
        ],
      },
      timeline: {
        label: 'Horizonte temporal',
        options: [
          { value: 'now-3-months', label: 'Ahora / próximos 3 meses' },
          { value: '3-6-months', label: '3–6 meses' },
          { value: '6-12-months', label: '6–12 meses' },
          { value: 'exploring', label: 'Solo explorando' },
        ],
      },
      message: {
        label: 'Cuéntanos tu reto de superficie, material o aplicación',
        placeholder: '¿Qué te gustaría depositar, probar o explorar? ¿Qué sustratos o dispositivos te importan? (opcional)',
      },
      // TODO(client/legal): validar el texto de consentimiento definitivo con asesoría legal.
      consent: {
        labelBefore: `Acepto que la información enviada sea utilizada por el equipo de ${name} para responder a mi solicitud, tal y como se describe en la`,
        privacyLabel: 'Política de privacidad',
        labelAfter: '.',
        error: 'Debes aceptar la política de privacidad para continuar.',
      },
      submit: 'Únete a la lista de espera',
      submitting: 'Enviando…',
      noSpam: 'Sin spam. Tu solicitud será revisada por el equipo del proyecto.',
      successTitle: 'Gracias. Tu interés ha quedado registrado.',
      successText: `El equipo de ${name} revisará tu solicitud y te responderá si hay un encaje relevante.`,
      errorText: 'Ha habido un problema al enviar tu solicitud. Inténtalo de nuevo o escríbenos directamente a',
      requiredHint: 'Los campos obligatorios están marcados con *',
    },
    leadMagnet: {
      title: '¿Prefieres un documento para compartir con tu equipo?',
      text: 'Estamos preparando un one-pager técnico que estará disponible para descarga próximamente.',
      button: 'Descargar el one-pager técnico',
    },
  },

  footer: {
    description: `Una forma compacta de explorar, probar y crear superficies funcionales. ${brand.originEs}.`,
    navTitle: 'Explorar',
    nav: [
      { label: 'Tecnología', href: '#technology' },
      { label: 'Aplicaciones', href: '#applications' },
      { label: 'Beneficios', href: '#benefits' },
      { label: 'Contacto', href: '#waitlist' },
    ],
    contactTitle: 'Contacto',
    contactText: 'Para consultas sobre la tecnología, colaboraciones o acceso anticipado:',
    legalTitle: 'Legal',
    legal: [
      { label: 'Política de privacidad', href: leadCapture.privacyUrl },
      { label: 'Aviso legal', href: '#' },
    ],
    languageTitle: 'Idioma',
    copyright: `${name} es un proyecto de transferencia tecnológica surgido de la investigación del ICMAB-CSIC. El nombre y la marca son provisionales.`,
  },
};
