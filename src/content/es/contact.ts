import { brand } from '../../config/brand';
import type { ContactContent } from '../en/contact';

/**
 * /contact en español. Los valores de las opciones (value) no se traducen:
 * son las claves que viajan en la URL y en el payload del formulario.
 * Misma prudencia que en inglés: sin prioridades ni plazos prometidos.
 */

const name = brand.projectName;

export const contact: ContactContent = {
  meta: {
    breadcrumb: 'Contacto',
    title: `Contacto — ${name} · Habla con el equipo`,
    description: `Habla con el equipo de ${name}: explora una aplicación, solicita información técnica, plantea una colaboración científica, participa en una validación o habla de distribución.`,
  },

  hero: {
    kicker: 'Contacto',
    title: 'Habla con el equipo',
    lead: `Cuéntanos sobre tu laboratorio, tus necesidades de investigación y cómo te planteas utilizar ${name}. Revisaremos tu solicitud y contactaremos contigo a medida que surjan oportunidades de acceso anticipado.`,
  },

  form: {
    fullName: { label: 'Nombre', error: 'Introduce tu nombre.' },
    email: { label: 'Correo electrónico profesional', error: 'Introduce una dirección de correo válida.' },
    organization: { label: 'Organización', error: 'Introduce tu organización.' },
    role: { label: 'Cargo', error: 'Introduce tu cargo.' },
    interest: {
      label: 'Me interesa',
      error: 'Selecciona qué te interesa.',
      options: [
        { value: 'product-information', label: 'Información del producto' },
        { value: 'research-application', label: 'Aplicación de investigación' },
        { value: 'scientific-collaboration', label: 'Colaboración científica' },
        { value: 'validation', label: 'Validación' },
        { value: 'distribution', label: 'Distribución' },
        { value: 'other', label: 'Otro' },
      ],
    },
    areaOfInterest: {
      label: 'Área de interés',
      options: [
        { value: 'organic-electronics', label: 'Electrónica orgánica, incluidos los OLED' },
        { value: 'solar-cells', label: 'Células solares: de perovskita y orgánicas' },
        { value: 'sensors', label: 'Sensores químicos y biosensores' },
        { value: 'coatings', label: 'Recubrimientos funcionales' },
        { value: 'spm-sample-preparation', label: 'Preparación de muestras para SPM' },
        { value: 'new-combinations', label: 'Nuevas combinaciones material–superficie' },
        { value: 'other', label: 'Otra' },
      ],
    },
    message: {
      label: `¿Qué te gustaría explorar con ${name}?`,
      placeholder: 'Cuéntanos tu material, tu sustrato, tu flujo de trabajo actual o tu reto de investigación',
      error: 'Cuéntanos brevemente qué te gustaría explorar.',
    },
    consent: {
      // TODO(client/legal): validar el texto definitivo del consentimiento.
      labelBefore: `Acepto que se me contacte en relación con ${name} y acepto la`,
      privacyLabel: 'Política de privacidad',
      labelAfter: '.',
      error: 'Acepta la Política de privacidad para continuar.',
    },
    optionalTag: '(opcional)',
    selectPlaceholder: 'Selecciona…',
    submit: 'Enviar mensaje',
    submitting: 'Enviando…',
    successTitle: 'Solicitud recibida',
    successText: `Gracias: hemos recibido tu solicitud. El equipo de ${name} revisará la información y contactará contigo a medida que se desarrolle el programa de acceso anticipado.`,
    errorText: 'Se ha producido un error al enviar tu mensaje. Inténtalo de nuevo o escríbenos directamente a',
  },

  aside: {
    title: 'Qué ocurre después',
    steps: [
      'Revisamos tu contexto de investigación y la aplicación prevista.',
      'Contactamos contigo para hablar del posible encaje y de la disponibilidad.',
      'Los proyectos adecuados podrán ser invitados a futuras oportunidades piloto o de acceso anticipado.',
    ],
    directTitle: '¿Prefieres el correo?',
    directText: 'También puedes escribir directamente a',
  },
};
