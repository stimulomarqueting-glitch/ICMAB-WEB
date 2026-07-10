import type { SiteContent } from './types';
import { brand } from '../config/brand';
import { leadCapture } from '../config/leadCapture';

const name = brand.projectName;

export const es: SiteContent = {
  meta: {
    title: `${name} — Dispositivo compacto de deposición sobre superficies · I+D a escala de laboratorio`,
    description: `${name} es un dispositivo compacto para la deposición directa de materiales sobre superficies por sublimación, a escala de laboratorio. Desarrollado en el ICMAB-CSIC. Únete a la lista de acceso anticipado.`,
    ogAlt: `${name} — dispositivo compacto de deposición sobre superficies para I+D a escala de laboratorio`,
  },

  header: {
    nav: [
      { label: 'Tecnología', href: '#technology' },
      { label: 'Beneficios', href: '#benefits' },
      { label: 'Aplicaciones', href: '#applications' },
      { label: 'Para quién', href: '#who-is-it-for' },
    ],
    cta: 'Únete a la lista',
    menuOpen: 'Abrir menú',
    menuClose: 'Cerrar menú',
    langLabel: 'Idioma',
  },

  hero: {
    eyebrow: 'Tecnología ICMAB-CSIC · Patentada (EP 23711114.1)',
    headline: 'Experimentación directa con superficies, en formato compacto.',
    descriptor: 'Dispositivo compacto de deposición · I+D a escala de laboratorio',
    lead: 'Deposita, criba y caracteriza materiales por sublimación, directamente sobre las superficies que importan — en tu mesa, sin sala blanca ni grandes sistemas de vacío.',
    ctaPrimary: 'Solicita acceso anticipado',
    ctaSecondary: 'Cómo funciona',
    photoAlt: `El dispositivo de deposición de vidrio ${name} sobre una mesa de laboratorio, con un sustrato de muestra`,
  },

  problem: {
    kicker: 'El problema',
    title: 'Un experimento de superficie no debería exigir una instalación.',
    lead: 'Depositar un material sobre una superficie ha significado tradicionalmente grandes evaporadores de vacío, acceso a sala blanca, operadores formados y colas — infraestructura que muchos equipos no tienen, y experimentos que desperdician la mayor parte del material cargado.',
    cards: [
      {
        tag: 'Infraestructura',
        title: 'Sistemas grandes, colas compartidas',
        text: 'Las líneas de deposición de alto vacío son caras, centralizadas y están reservadas — un cuello de botella entre una idea y su primera prueba.',
      },
      {
        tag: 'Especialización',
        title: 'Requiere formación especializada',
        text: 'Las herramientas convencionales dejan el trabajo de superficies en manos de unos pocos operadores, fuera del alcance de campos vecinos.',
      },
      {
        tag: 'Material',
        title: 'Gramos gastados por cm² de resultado',
        text: 'La mayor parte del material cargado nunca llega a la muestra. Con moléculas nuevas o caras, ese desperdicio decide qué se prueba.',
      },
    ],
  },

  technology: {
    kicker: 'La tecnología',
    title: 'La sublimación, llevada a la mesa de laboratorio.',
    lead: `${name} calienta una fuente sólida hasta que sublima y deposita el vapor directamente sobre tu sustrato final a una distancia ajustable — en una cámara de vidrio de sobremesa, con mínimos pasos, energía y pérdida de material.`,
    video: {
      caption: `${name} en funcionamiento — del material sólido a la capa depositada.`,
      placeholder: 'Próximamente: vídeo del dispositivo.',
    },
    steps: [
      { title: 'Carga miligramos', text: 'Coloca una pequeña cantidad de tu material sólido en el portamuestras de la fuente.' },
      { title: 'Ajusta los parámetros', text: 'Temperatura, presión, tiempo — y la distancia fuente–sustrato d.' },
      { title: 'Sublima en directo', text: 'El vapor se deposita directamente sobre tu sustrato final. Sin transferencia, sin disolventes.' },
      { title: 'Caracteriza', text: 'Lleva la muestra de 1–3 cm² a análisis, o criba la siguiente condición.' },
    ],
    accordionTitle: 'En más detalle',
    accordion: [
      {
        q: '¿Cómo funciona?',
        a: `${name} es una cámara de sublimación compacta y controlada. Una pequeña cantidad de material sólido se calienta en la fuente hasta que sublima; el vapor recorre una distancia corta y ajustable y condensa directamente sobre tu sustrato objetivo como una capa fina. Como la geometría es de espacio reducido y la cámara es pequeña, la mayor parte del material acaba donde está la muestra — no en las paredes. El proceso no necesita disolventes ni pasos de transferencia: la superficie que creces es la superficie que mides.`,
      },
      {
        q: '¿Para qué puede usarse?',
        a: 'Para cualquier experimento donde un material sublimable deba encontrarse con una superficie: capas emisoras y de transporte para OLEDs, capas absorbentes y de interfaz para células solares de perovskita y orgánicas, capas receptoras para sensores químicos y biosensores, películas moleculares para electrónica orgánica, recubrimientos funcionales candidatos, y cribado de compuestos nuevos como MOFs, COFs y moléculas noveles sobre distintos sustratos.',
      },
      {
        q: '¿Para quién es?',
        a: 'Grupos de ciencia de superficies que quieren ensayos a escala reducida junto a sus líneas de deposición; investigadores de química, biología o física que hacen su primer trabajo de superficies sin infraestructura especializada; I+D corporativo y servicios de ensayo que añaden cribado de superficies a su portafolio; y distribuidores de equipamiento que buscan un producto de sobremesa protegido por patente.',
      },
      {
        q: '¿Qué lo hace diferente?',
        a: 'La deposición ocurre directamente sobre el sustrato final a escala de 1–3 cm², con cargas de miligramos en lugar de gramos, en un dispositivo de menos de 20 cm que funciona sin sala blanca ni línea de vacío grande. La distancia fuente–sustrato es ajustable, las piezas son modulares y reemplazables a bajo coste, y el flujo de trabajo es tan simple que no requiere operador especialista.',
      },
      {
        q: 'Validación y propiedad industrial',
        a: 'La tecnología se desarrolló en el ICMAB-CSIC (Institut de Ciència de Materials de Barcelona) y está protegida por una familia de patentes española y europea (EP 23711114.1). Los prototipos funcionales están en TRL 5 avanzando hacia 6, con el apoyo de programas competitivos de investigación y transferencia, y uso experimental real en el laboratorio.',
      },
    ],
    ctaBandText: 'Véelo primero en tu propia mesa.',
    ctaBandButton: 'Solicita acceso anticipado',
  },

  benefits: {
    kicker: 'Beneficios',
    title: 'Lo que gana tu equipo.',
    outcomes: [
      {
        title: 'Menos material por respuesta',
        text: 'Criba una molécula candidata con miligramos, no gramos — los compuestos caros siguen siendo viables de probar.',
      },
      {
        title: 'Más condiciones probadas por semana',
        text: 'Los ciclos directos en la mesa sustituyen a las colas de instalación — los barridos ocurren el mismo día.',
      },
      {
        title: 'Autonomía para todo el grupo',
        text: 'Cualquiera puede hacer una deposición en su propio laboratorio. Sin reservas, sin operador especialista.',
      },
      {
        title: 'Resultados defendibles',
        text: 'Películas crecidas sobre el sustrato real — lo que mides es lo que has hecho.',
      },
    ],
  },

  applications: {
    kicker: 'Aplicaciones',
    kickerNote: 'casos de uso confirmados',
    title: 'Para qué lo usarías, en concreto.',
    items: [
      {
        sub: 'pilas emisivas',
        name: 'OLEDs',
        text: 'Deposita capas emisoras y de transporte sobre sustratos de prueba y compara variaciones de pila antes de comprometer tiempo del evaporador compartido.',
      },
      {
        sub: 'perovskita y orgánicas',
        name: 'Células solares',
        text: 'Sublima capas absorbentes y de interfaz directamente sobre sustratos de dispositivo para cribar composiciones a coste de miligramos.',
      },
      {
        sub: 'capas receptoras',
        name: 'Sensores químicos y biosensores',
        text: 'Funcionaliza la superficie sensora con moléculas receptoras y prueba la respuesta sobre el propio transductor final.',
      },
      {
        sub: 'películas moleculares',
        name: 'Electrónica orgánica',
        text: 'Crece películas moleculares finas sobre sustratos listos para dispositivo, para transistores y prototipos de circuito, condición a condición.',
      },
      {
        sub: 'capas funcionales',
        name: 'Recubrimientos',
        text: 'Aplica recubrimientos funcionales candidatos sobre muestras de pieza reales y compara adhesión y cobertura antes de escalar.',
      },
      {
        sub: 'MOFs · COFs · moléculas noveles',
        name: 'Nuevos materiales de superficie',
        text: 'Criba compuestos nuevos sobre muchos sustratos rápidamente — descubre qué deposita y en qué condiciones.',
      },
    ],
    ctaButton: 'Comenta tu caso de uso',
    ctaNote: 'Cuéntanos qué depositarías primero — eso da forma al programa piloto.',
  },

  audiences: {
    kicker: 'Para quién',
    title: 'Pensado para cuatro tipos de equipos.',
    groups: [
      {
        icon: 'surface',
        title: 'Grupos de ciencia de superficies',
        text: 'Ensayos a escala reducida junto a tus líneas de deposición — reserva el sistema grande para lo que lo merece.',
      },
      {
        icon: 'lab',
        title: 'Investigadores nuevos en superficies',
        text: 'Una primera deposición sin infraestructura ni formación especializada — grupos de química, biología y física explorando superficies.',
      },
      {
        icon: 'insitu',
        title: 'I+D corporativo y servicios de ensayo',
        text: 'Un aparato compacto que añade preparación y cribado de superficies a tu portafolio sin construir una instalación.',
      },
      {
        icon: 'modularity',
        title: 'Distribuidores de equipamiento',
        text: 'Una incorporación de sobremesa, protegida por patente, para un catálogo de vidrio e instrumentación de laboratorio.',
      },
    ],
    ctaButton: 'Únete a la lista',
    ctaNote: 'Las unidades de acceso anticipado se asignan por encaje, por orden de solicitud.',
  },

  waitlist: {
    kicker: 'Acceso anticipado',
    title: 'Únete a la lista de espera.',
    lead: 'Cuéntanos sobre tu laboratorio y qué depositarías primero. Las unidades piloto son limitadas; las asignamos por encaje, por orden de solicitud.',
    steps: [
      'Revisamos tu solicitud y tu caso de uso.',
      'Te contactamos a medida que haya unidades piloto disponibles.',
      'Obtienes acceso prioritario a la primera serie de producción.',
    ],
    form: {
      fullName: { label: 'Nombre completo', error: 'Introduce tu nombre completo.' },
      email: { label: 'Email de trabajo', error: 'Introduce una dirección de email válida.' },
      organization: { label: 'Organización', error: 'Introduce tu organización.' },
      role: { label: 'Cargo' },
      country: {
        label: 'País',
        error: 'Selecciona tu país.',
        options: [
          ['Spain', 'España'], ['Portugal', 'Portugal'], ['France', 'Francia'], ['Germany', 'Alemania'],
          ['Italy', 'Italia'], ['United Kingdom', 'Reino Unido'], ['Netherlands', 'Países Bajos'],
          ['Belgium', 'Bélgica'], ['Switzerland', 'Suiza'], ['Austria', 'Austria'], ['Denmark', 'Dinamarca'],
          ['Sweden', 'Suecia'], ['Norway', 'Noruega'], ['Finland', 'Finlandia'], ['Ireland', 'Irlanda'],
          ['Poland', 'Polonia'], ['Czechia', 'Chequia'], ['United States', 'Estados Unidos'],
          ['Canada', 'Canadá'], ['Japan', 'Japón'], ['South Korea', 'Corea del Sur'], ['China', 'China'],
          ['Australia', 'Australia'], ['Other', 'Otro'],
        ].map(([value, label]) => ({ value, label })),
      },
      organizationType: {
        label: 'Tipo de organización',
        error: 'Selecciona el tipo de organización.',
        options: [
          { value: 'academic-research-group', label: 'Grupo de investigación académico' },
          { value: 'public-research-centre', label: 'Centro de investigación público' },
          { value: 'corporate-rd', label: 'I+D corporativo' },
          { value: 'testing-services', label: 'Servicios de ensayo y caracterización' },
          { value: 'distributor', label: 'Distribuidor de equipamiento de laboratorio' },
          { value: 'tech-transfer', label: 'Oficina de transferencia tecnológica' },
          { value: 'other', label: 'Otro' },
        ],
      },
      areaOfInterest: {
        label: 'Área de interés',
        error: 'Selecciona un área de interés.',
        options: [
          { value: 'oleds', label: 'OLEDs' },
          { value: 'solar-cells', label: 'Células solares (perovskita y orgánicas)' },
          { value: 'sensors', label: 'Sensores químicos y biosensores' },
          { value: 'organic-electronics', label: 'Electrónica orgánica' },
          { value: 'coatings', label: 'Recubrimientos' },
          { value: 'new-surface-materials', label: 'Nuevos materiales de superficie' },
          { value: 'other', label: 'Otra' },
        ],
      },
      currentNeed: {
        label: 'Necesidad actual',
        options: [
          { value: 'exploring', label: 'Explorando la tecnología' },
          { value: 'evaluating-project', label: 'Evaluando para un proyecto concreto' },
          { value: 'active-pilot', label: 'Necesidad activa — interesa una unidad piloto' },
          { value: 'distribution-partnership', label: 'Distribución o partnership' },
        ],
      },
      timeline: {
        label: 'Horizonte temporal',
        options: [
          { value: 'within-3-months', label: 'En los próximos 3 meses' },
          { value: '3-6-months', label: '3–6 meses' },
          { value: '6-12-months', label: '6–12 meses' },
          { value: 'beyond-12-months', label: 'Más de 12 meses' },
          { value: 'following', label: 'Solo sigo el proyecto' },
        ],
      },
      message: {
        label: '¿Qué depositarías primero?',
        placeholder: 'Material, sustrato y qué quieres aprender del experimento',
      },
      // TODO(client/legal): validar el texto de consentimiento definitivo con asesoría legal.
      consent: {
        labelBefore: `Acepto ser contactado/a sobre el acceso anticipado a ${name} y acepto la`,
        privacyLabel: 'Política de privacidad',
        labelAfter: '.',
        error: 'Debes aceptar la Política de privacidad para continuar.',
      },
      optionalTag: '(opcional)',
      selectPlaceholder: 'Selecciona…',
      submit: 'Solicita acceso anticipado',
      submitting: 'Enviando…',
      successTitle: 'Ya estás en la lista.',
      successText:
        'Gracias — hemos recibido tu solicitud. Revisamos los casos de uso por orden de llegada y te contactaremos a medida que haya unidades piloto disponibles.',
      errorText: 'Ha habido un problema al enviar tu solicitud. Inténtalo de nuevo o escríbenos directamente a',
    },
  },

  footer: {
    description: `Dispositivo compacto de deposición sobre superficies para I+D a escala de laboratorio. ${brand.originEs}, Barcelona.`,
    cta: 'Únete a la lista',
    columns: [
      {
        title: 'Producto',
        links: [
          { label: 'Tecnología', href: '#technology' },
          { label: 'Beneficios', href: '#benefits' },
          { label: 'Aplicaciones', href: '#applications' },
        ],
      },
      {
        title: 'Programa',
        links: [
          { label: 'Para quién', href: '#who-is-it-for' },
          { label: 'Acceso anticipado', href: '#waitlist' },
        ],
      },
      {
        title: 'Legal',
        links: [
          // TODO(client): enlazar páginas reales de privacidad y aviso legal
          { label: 'Política de privacidad', href: leadCapture.privacyUrl },
          { label: 'Aviso legal', href: '#' },
          { label: 'Contacto', href: `mailto:${brand.contactEmail}` },
        ],
      },
    ],
    copyright: `© 2026 ${name} · ICMAB-CSIC · El nombre y la marca son provisionales.`,
    bottomMono: `EP 23711114.1 · ${brand.claimEs}`,
  },
};
