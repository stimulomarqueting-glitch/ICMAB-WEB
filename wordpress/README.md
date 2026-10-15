# MatSurfer — tema de WordPress

Versión WordPress de la web de MatSurfer (ICMAB-CSIC). Mismo diseño y mismos
textos que la web Astro de este repositorio. Inglés en la raíz y español en
`/es/` (Polylang); formularios con WPForms Lite; todo editable por el equipo
del ICMAB sin conocimientos técnicos y sin poder romper el diseño.

- `matsurfer/` — el tema (lo que se sube a WordPress).
- `tools/` — scripts para regenerar los datos y el zip, y la batería de
  pruebas (`tools/qa/`).
- `ARCHITECTURE.md` — cómo está construido (para desarrolladores).

## Qué hay dentro

| Para el equipo del ICMAB | Dónde |
| --- | --- |
| Editar los textos y las fotos de cualquier página (clic sobre el texto o la foto, o panel de la derecha) | Páginas |
| Escribir noticias en inglés y español, con foto, categoría y extracto | Noticias |
| Equipo, aplicaciones, publicaciones y valoraciones de expertos (fichas con inglés y español lado a lado) | Menús propios |
| Ver los contactos que llegan por el formulario, marcarlos como atendidos y exportarlos a Excel (CSV) | Contactos |
| Menú, pie de página, email de contacto y textos para Google | Ajustes de la web |
| Guía paso a paso dentro del propio panel | Guía de uso |

Las páginas están formadas por **secciones bloqueadas**: se cambian textos y
fotos, pero no se pueden mover ni borrar secciones, ni cambiar colores o
tipografías. Las páginas estructurales (Inicio, Producto, etc.) no se pueden
borrar.

## Requisitos

- WordPress 6.6 o superior (probado con 7.1.3).
- PHP 8.1 o superior (probado con 8.4; revisado para 8.1–8.3).
- MySQL o MariaDB (probado con MariaDB 10.11 y con SQLite).
- Plugins (el asistente los instala solo): **Polylang** y **WPForms Lite**.
  Recomendado: **WP Mail SMTP** para que los avisos de contacto no acaben en
  spam.

## Instalación en Hostinger (paso a paso)

1. **Crear el WordPress.** En hPanel → Sitios web → (el dominio o subdominio
   de staging) → Instalador automático → WordPress. Idioma: Español. Apunta
   el usuario y la contraseña de administrador.
2. **Subir el tema.** Escritorio de WordPress → Apariencia → Temas → Añadir
   nuevo → Subir tema → elige `matsurfer.zip` → Instalar ahora → **Activar**.
3. **Puesta en marcha.** Aparece un aviso «El tema MatSurfer está activado…»
   → *Abrir la puesta en marcha* (o menú **MatSurfer → Puesta en marcha**).
   Pulsa **Hacerlo todo**. El asistente:
   - instala y activa Polylang y WPForms Lite;
   - configura los idiomas (inglés en `/`, español en `/es/`), las
     direcciones limpias, la zona horaria de Madrid y cierra los comentarios;
   - crea todas las páginas en inglés y español con los textos originales,
     importa las fotos, el vídeo, el equipo, las aplicaciones, las
     publicaciones y las valoraciones;
   - crea los dos formularios de contacto (EN/ES) en WPForms, que avisan a
     `matsurfer@icmab.es`.
   Se puede repetir sin miedo: lo que ya está hecho no se duplica.
4. **Revisión final** (último paso del asistente):
   - Email de contacto en *Ajustes de la web* (por defecto
     `matsurfer@icmab.es`, pendiente de confirmar por el ICMAB).
   - Instalar y configurar **WP Mail SMTP** con el buzón de Hostinger.
   - Mientras sea staging: Ajustes → Lectura → marcar *Disuadir a los motores
     de búsqueda*. Desmarcarlo al publicar en el dominio final.
   - Crear los usuarios del ICMAB con el rol **Editor** (Usuarios → Añadir
     nuevo). El rol Editor puede hacer todo lo del día a día y no puede tocar
     plugins, tema ni ajustes técnicos.
5. **Textos legales.** «Política de privacidad» y «Aviso legal» se crean como
   borradores. Cuando el ICMAB facilite los textos: pegar, publicar, y el pie
   de página y la casilla del formulario los enlazan solos.

## Comprobaciones después de instalar (5 minutos)

- [ ] Abrir la web en inglés y en español (`/` y `/es/`) y recorrer las 6
      páginas con el menú; probar el selector EN/ES.
- [ ] Enviar el formulario de contacto en inglés y en español: llega el email
      a la dirección de contacto y aparece en **Contactos**.
- [ ] Entrar desde `/contact/?interest=product` y comprobar que el desplegable
      «I am interested in» aparece preseleccionado.
- [ ] Entrar como Editor, cambiar un texto de Inicio haciendo clic encima,
      pulsar *Actualizar* y verlo en la web.
- [ ] Publicar una noticia de prueba: aparece «News/Noticias» en el menú;
      borrarla después.

> WPForms Lite no se pudo descargar en el entorno donde se construyó el tema:
> la integración se probó con un emulador de WPForms. La primera prueba real
> del formulario (punto 2) es imprescindible.

## Actualizar el tema más adelante

1. Generar el zip: `bash wordpress/tools/build-zip.sh` → `wordpress/dist/matsurfer.zip`.
2. Apariencia → Temas → Añadir nuevo → Subir tema → elegir el zip →
   *Reemplazar el actual con el subido*. Los contenidos no se tocan.

Los textos originales viven en `src/content` (web Astro) y se exportan con
`node wordpress/tools/export-content.mjs`. Una vez instalada la web, los
textos se editan en WordPress; el asistente solo los vuelve a poner si se
marca «Sobrescribir los textos con los originales».

## Pendiente del cliente

- Confirmar el buzón `matsurfer@icmab.es` (o cambiarlo en *Ajustes de la web*).
- Textos legales (privacidad y aviso legal).
- Dominio final (cambiar la URL del sitio en Hostinger al pasar a producción).
- Autor de la valoración «MOF & COF thin films» (ahora sin firma).
