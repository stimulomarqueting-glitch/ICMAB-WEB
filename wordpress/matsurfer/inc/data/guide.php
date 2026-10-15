<?php
/**
 * "Guía de uso" — the in-admin manual for the ICMAB team (rendered by
 * inc/modules/admin.php → Guía de uso). Plain data, no logic.
 *
 * Each section: id (anchor), icon (dashicon name), title, summary (one line
 * for the index), audience ('all' | 'admin'), content = list of blocks:
 *   [ 'type' => 'p'|'h3'|'tip'|'warn'|'note', 'text' => '…' ]
 *   [ 'type' => 'steps'|'list', 'items' => [ '…', … ] ]
 *   [ 'type' => 'table', 'head' => [ … ], 'rows' => [ [ … ], … ] ]
 *   [ 'type' => 'links', 'items' => [ [ 'label' => '…', 'href' => 'admin:edit.php' | 'settings' ], … ] ]
 * Text supports **bold**, `Tecla`, [enlace](admin:pantalla.php | #seccion | site:/es/ | mailto:… | https://…)
 * and the placeholders {contact_email} {admin_email} {site_url} {site_url_es}
 * {login_url} {max_upload} {max_image} {settings_url} {user}.
 *
 * Keep it true to what the theme does: when a screen changes, change the
 * guide in the same commit.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'    => 'Guía de uso de la web de MatSurfer',
	'intro'    => array(
		'Hola, {user}. Esta guía explica, paso a paso y sin tecnicismos, cómo cuidar la web de MatSurfer: publicar noticias, cambiar textos y fotos, mantener al día el equipo y las publicaciones y atender los mensajes que llegan por el formulario.',
		'La web está pensada para que **no se pueda romper**: el diseño, los colores y la estructura de las páginas están protegidos, así que puedes probar con tranquilidad. Si algo no sale como esperabas, casi todo se puede deshacer.',
	),
	'sections' => array(

		/* ── 1 ─────────────────────────────────────────────────────────── */
		array(
			'id'      => 'entrar',
			'icon'    => 'admin-network',
			'title'   => 'Cómo entrar (y salir)',
			'summary' => 'Dónde se entra, el Escritorio y cómo cerrar la sesión.',
			'content' => array(
				array(
					'type'  => 'steps',
					'items' => array(
						'Abre esta dirección en el navegador: **{login_url}** (guárdala en favoritos).',
						'Escribe tu **nombre de usuario o email** y tu **contraseña**. Te los da el administrador de la web; cada persona tiene los suyos.',
						'Pulsa **Acceder**. Llegarás al **Escritorio**: arriba tienes los accesos grandes a lo que se hace a diario (escribir una noticia, editar la página de inicio, ver los contactos…).',
						'Para salir, pasa el ratón por tu nombre (arriba a la derecha) y elige **Cerrar sesión**. Hazlo siempre si usas un ordenador compartido.',
					),
				),
				array(
					'type' => 'tip',
					'text' => '¿Has olvidado la contraseña? En la pantalla de entrada pulsa **¿Has olvidado tu contraseña?** y recibirás un email para crear una nueva. Si escribes mal la contraseña varias veces seguidas, el acceso se bloquea unos minutos por seguridad: espera un poco y vuelve a intentarlo.',
				),
				array(
					'type' => 'note',
					'text' => 'El menú de la izquierda solo muestra lo que necesitas: Contactos, Noticias, Páginas, Equipo, Aplicaciones, Publicaciones, Valoraciones, Medios, Ajustes de la web y esta Guía. Si ves el panel en inglés, ve a **Perfil → Idioma**, elige **Español** y pulsa **Actualizar perfil**.',
				),
				array(
					'type' => 'p',
					'text' => 'Mientras tengas la sesión abierta, verás una barra negra arriba también en la web pública: desde ahí puedes saltar a **Editar página** o **Editar noticia** de lo que estés mirando, y volver al panel con el nombre de la web.',
				),
			),
		),

		/* ── 2 ─────────────────────────────────────────────────────────── */
		array(
			'id'      => 'noticias',
			'icon'    => 'megaphone',
			'title'   => 'Escribir y publicar una noticia',
			'summary' => 'Título, texto, foto destacada, categoría, extracto y publicar.',
			'content' => array(
				array(
					'type' => 'p',
					'text' => 'Las noticias aparecen en la página **Noticias** de la web (la más reciente, grande, arriba) y las tres últimas también en la página de inicio. Cada noticia se escribe en un idioma; la versión en el otro idioma se enlaza con el botón **+** de Polylang (ver más abajo).',
				),
				array(
					'type' => 'h3',
					'text' => 'Escribirla',
				),
				array(
					'type'  => 'steps',
					'items' => array(
						'En el Escritorio pulsa **Escribir una noticia** (o en el menú: [Noticias → Escribir una noticia](admin:post-new.php)).',
						'Escribe el **título** arriba, donde pone «Título de la noticia».',
						'Haz clic debajo y escribe el texto. Cada vez que pulses `Intro` empieza un párrafo nuevo. Puedes pegar el texto desde Word o un email: se mantienen las negritas, los enlaces y las listas.',
						'Para añadir otra cosa (un **Encabezado** para separar apartados, una **Imagen**, una **Galería**, un vídeo de YouTube, una **Cita**, una **Tabla**, un PDF con **Archivo** o un **Botón**) pulsa el **+** azul de arriba a la izquierda o el **+** que aparece entre párrafos, y elige.',
						'Para un vídeo de YouTube o Vimeo basta con **pegar el enlace del vídeo** en una línea vacía: se convierte solo en el reproductor.',
						'Para poner un enlace: selecciona las palabras, pulsa el icono de la cadena (o `Ctrl` + `K`), pega la dirección y pulsa `Intro`.',
					),
				),
				array(
					'type' => 'h3',
					'text' => 'Foto destacada, categoría y extracto (panel de la derecha)',
				),
				array(
					'type' => 'p',
					'text' => 'Si no ves el panel de la derecha, pulsa el icono del recuadro con una columna (arriba a la derecha, junto a **Guardar**) y elige la pestaña **Noticia**.',
				),
				array(
					'type'  => 'list',
					'items' => array(
						'**Foto destacada**: pulsa **Elegir la foto destacada**, sube la foto o elige una de la biblioteca y pulsa el botón para usarla. Es la foto de la tarjeta de la noticia y de la cabecera del artículo. Mejor horizontal (ver [Consejos para las fotos](#consejos-fotos)).',
						'**Categoría**: despliega **Categorías** y marca la que corresponda («News» en inglés, «Noticias» en español, o la que hayáis creado). Puedes crear una nueva con **Añadir nueva categoría**.',
						'**Extracto**: debajo de la foto destacada pulsa **Añadir un extracto…** y escribe un resumen de una o dos frases. Se ve en la tarjeta de la noticia y en Google; si lo dejas vacío se usan las primeras palabras del texto.',
					),
				),
				array(
					'type' => 'h3',
					'text' => 'Publicarla',
				),
				array(
					'type'  => 'steps',
					'items' => array(
						'Mientras escribes, pulsa **Guardar borrador** de vez en cuando. Un borrador no se ve en la web.',
						'Para ver cómo quedará, pulsa el icono de **Vista previa** (la pantalla, arriba a la derecha) → **Vista previa en una pestaña nueva**.',
						'Cuando esté lista, pulsa **Publicar** y, en el panel que aparece («¿Listo para publicar?»), pulsa **Publicar** otra vez. Ya está en la web.',
						'¿Quieres que salga otro día? Antes de publicar, en **Publicar** (panel de la derecha) cambia «Inmediatamente» por la fecha y la hora; el botón pasará a llamarse **Programar**.',
						'Para cambiar una noticia ya publicada: ábrela en [Noticias](admin:edit.php), corrige y pulsa **Guardar** (en versiones antiguas de WordPress se llama «Actualizar»).',
					),
				),
				array(
					'type' => 'h3',
					'text' => 'Traducirla al otro idioma',
				),
				array(
					'type'  => 'steps',
					'items' => array(
						'Publica (o guarda) primero la noticia en su idioma.',
						'En el panel de la derecha busca el recuadro **Idiomas**. En **Traducciones** aparece la bandera del otro idioma con un **+**: púlsalo. (También puedes hacerlo desde la lista de [Noticias](admin:edit.php): en la columna con la bandera del otro idioma, pulsa el **+** de esa noticia.)',
						'Se abre una noticia nueva, ya enlazada con la original. Escribe el título y el texto traducidos, comprueba la **foto destacada**, la **categoría** («Noticias» en español, «News» en inglés) y el **extracto**, y pulsa **Publicar**.',
						'Para pasar de una versión a la otra, usa el lápiz que aparece junto a la bandera en **Idiomas → Traducciones**.',
					),
				),
				array(
					'type' => 'warn',
					'text' => 'La web en español solo muestra noticias en español, y la inglesa solo en inglés. Si una noticia existe en un solo idioma, en el otro no aparece. En el Escritorio, **Noticias recientes** te avisa cuando falta una versión.',
				),
				array(
					'type' => 'tip',
					'text' => '¿Te has equivocado? La flecha curva de arriba a la izquierda deshace el último cambio (`Ctrl` + `Z`). Si ya guardaste, en el panel de la derecha busca **Revisiones**: puedes volver a cualquier versión anterior. Y una noticia borrada pasa 30 días en la **Papelera** antes de desaparecer.',
				),
				array(
					'type'  => 'links',
					'items' => array(
						array(
							'label' => 'Escribir una noticia',
							'href'  => 'admin:post-new.php',
						),
						array(
							'label' => 'Ver todas las noticias',
							'href'  => 'admin:edit.php',
						),
					),
				),
			),
		),

		/* ── 3 ─────────────────────────────────────────────────────────── */
		array(
			'id'      => 'textos',
			'icon'    => 'edit-page',
			'title'   => 'Cambiar los textos de una página',
			'summary' => 'Clic sobre el texto o panel de la derecha, y Guardar.',
			'content' => array(
				array(
					'type' => 'p',
					'text' => 'Las páginas principales (Inicio, Producto, Aplicaciones, Casos de estudio, Equipo y Contacto) están hechas de **secciones**: la cabecera, los beneficios, las preguntas frecuentes, la llamada final… En el editor ves cada página **tal y como se ve en la web** y cambias sus textos directamente encima.',
				),
				array(
					'type'  => 'steps',
					'items' => array(
						'Ve a [Páginas](admin:edit.php?post_type=page) y pulsa el nombre de la página (por ejemplo **Producto**). Para la portada, en el Escritorio tienes **Editar la página de inicio**.',
						'**Clic sobre el texto**: pasa el ratón por encima de un título o un párrafo; si se puede editar aparece un recuadro. Haz clic, escribe y, para terminar, haz clic fuera o pulsa `Intro`. Si te arrepientes mientras escribes, `Esc` lo deja como estaba.',
						'**Panel de la derecha**: al hacer clic en una sección, a la derecha aparecen **todos** sus textos ordenados por grupos (Textos de la sección, Botón principal, Pasos…). Ahí están también los que no se pueden pulsar en la página, como el destino de los botones o la descripción de las fotos.',
						'**Botones**: en el panel cambias el **Texto del botón** y **Lleva a** (una página de la web, el email de contacto u «Otra dirección» para una web externa o un PDF). Los botones que llevan a páginas de la web nunca se rompen, aunque cambie el nombre de la página.',
						'**Listas** (pasos, preguntas, tarjetas…): ábrelas en el panel; con las flechas cambias el orden, con la papelera quitas un elemento y con **Añadir…** creas otro. Algunas secciones tienen un número fijo de elementos, porque el diseño está hecho para ese número.',
						'Cuando termines, pulsa **Guardar** (arriba a la derecha; en versiones antiguas de WordPress se llama «Actualizar»). Los cambios se ven en la web en ese momento.',
					),
				),
				array(
					'type' => 'note',
					'text' => 'Al abrir una página principal verás arriba el aviso «Esta página está formada por secciones bloqueadas: puedes cambiar textos y fotos, no la estructura». Es normal: las secciones no se pueden mover, borrar ni añadir, para que el diseño se mantenga.',
				),
				array(
					'type' => 'p',
					'text' => 'Algunas secciones muestran contenido que se guarda en otro sitio: el **equipo**, las **aplicaciones**, las **publicaciones**, las **valoraciones de expertos** y las **últimas noticias**. En el editor verás un aviso con un enlace a donde se cambian (ver [Equipo, aplicaciones, publicaciones y valoraciones](#fichas)).',
				),
				array(
					'type' => 'p',
					'text' => 'Los textos que se repiten en todas las páginas (el menú de arriba y el pie de página) no están en las páginas: se cambian en [Ajustes de la web](#ajustes).',
				),
				array(
					'type' => 'tip',
					'text' => 'Antes de guardar un cambio grande, pulsa el icono de **Vista previa** (arriba a la derecha) para verlo en una pestaña nueva. Y si ya guardaste y no te gusta, en el panel de la derecha busca **Revisiones** para recuperar una versión anterior.',
				),
			),
		),

		/* ── 4 ─────────────────────────────────────────────────────────── */
		array(
			'id'      => 'fotos',
			'icon'    => 'format-image',
			'title'   => 'Cambiar una foto',
			'summary' => 'Clic sobre la foto → biblioteca → subir o elegir.',
			'content' => array(
				array(
					'type'  => 'steps',
					'items' => array(
						'Abre la página en el editor y **haz clic sobre la foto** que quieres cambiar (al pasar el ratón pone «Haz clic para cambiar la foto»).',
						'Se abre la biblioteca de **Medios** («Elige una foto»). Para usar una foto nueva, ve a la pestaña **Subir archivos** y arrástrala o pulsa **Seleccionar archivos**. Si ya está en la biblioteca, haz clic en ella.',
						'Con la foto marcada, escribe a la derecha su **Texto alternativo**: una frase que diga lo que se ve («Investigadora colocando una muestra en el dispositivo»).',
						'Pulsa **Usar esta foto**. La verás ya en su sitio.',
						'Pulsa **Guardar**.',
					),
				),
				array(
					'type' => 'p',
					'text' => 'También puedes hacerlo desde el panel de la derecha: en la sección, busca la foto y pulsa **Cambiar foto**. Ahí mismo está la **Descripción de la foto** (el texto alternativo que se usa en esa página) y **Quitar**, si la foto es opcional. Donde falta una foto obligatoria, el editor muestra «Haz clic para elegir una foto».',
				),
				array(
					'type'  => 'list',
					'items' => array(
						'**Noticias**: la foto principal es la **Foto destacada** del panel de la derecha; las fotos dentro del texto se añaden con **+ → Imagen** o **Galería**.',
						'**Equipo, publicaciones, valoraciones**: la foto está en el recuadro **Foto** de cada ficha (**Elegir foto**).',
						'**Cada idioma tiene su página**: si cambias una foto en la página en inglés, cámbiala también en la española.',
					),
				),
				array(
					'type' => 'warn',
					'text' => 'No borres fotos de [Medios](admin:upload.php) si no estás segura/o de que no se usan: desaparecerían de las páginas donde están. Para cambiar una foto, sustitúyela en la página.',
				),
			),
		),

		/* ── 5 ─────────────────────────────────────────────────────────── */
		array(
			'id'      => 'idiomas',
			'icon'    => 'translation',
			'title'   => 'Inglés y español: dónde está cada versión',
			'summary' => 'Cada página y cada noticia existe una vez por idioma.',
			'content' => array(
				array(
					'type' => 'p',
					'text' => 'La web tiene dos idiomas: **inglés** en la dirección principal ({site_url}) y **español** en /es/ ({site_url_es}). Cada página existe dos veces, una por idioma, y se edita por separado: cambiar «Product» no cambia «Producto».',
				),
				array(
					'type'  => 'steps',
					'items' => array(
						'En [Páginas](admin:edit.php?post_type=page) hay dos columnas con las banderas de cada idioma. En la fila de cada página, la **bandera** indica su idioma; en la otra columna, el **lápiz** abre su traducción y un **+** significa que todavía no la tiene.',
						'Para ver solo las páginas de un idioma, usa el menú de idiomas de la barra negra de arriba («Mostrar todos los idiomas» → **Español** o **English**).',
						'Dentro del editor, el recuadro **Idiomas** (panel de la derecha) dice en qué idioma está la página y, en **Traducciones**, tiene el lápiz para saltar a la otra versión.',
						'Si una página o noticia no tiene todavía traducción, púlsale el **+** para crearla ya enlazada con la original.',
					),
				),
				array(
					'type' => 'note',
					'text' => 'El equipo, las aplicaciones, las publicaciones y las valoraciones funcionan distinto: **una sola ficha** guarda los dos idiomas, uno al lado del otro (ver la sección siguiente).',
				),
				array(
					'type' => 'tip',
					'text' => 'Cuando cambies algo importante en un idioma, apúntate hacer el mismo cambio en el otro. Así las dos webs dicen lo mismo.',
				),
			),
		),

		/* ── 6 ─────────────────────────────────────────────────────────── */
		array(
			'id'      => 'paginas-nuevas',
			'icon'    => 'welcome-add-page',
			'title'   => 'Crear una página nueva',
			'summary' => 'Las páginas nuevas son páginas de lectura: título y texto.',
			'content' => array(
				array(
					'type' => 'p',
					'text' => 'Casi nunca hace falta: las páginas de la web ya existen. Si necesitas una página de texto (por ejemplo, unas bases o un documento informativo), así se hace:',
				),
				array(
					'type'  => 'steps',
					'items' => array(
						'[Páginas → Añadir página](admin:post-new.php?post_type=page).',
						'Escribe el **título** y el **texto** (con encabezados, listas, imágenes, tablas o botones si los necesitas).',
						'Opcional: en el panel de la derecha, **Añadir un extracto…** será la entradilla bajo el título y la **imagen destacada**, la foto de la cabecera.',
						'Pulsa **Publicar**. La página se verá con el diseño de lectura de la web: el título grande arriba y el texto en una columna cómoda de leer (como el Aviso legal).',
						'Crea su versión en el otro idioma con el **+** de **Idiomas** y publícala también.',
					),
				),
				array(
					'type' => 'note',
					'text' => 'Una página nueva no aparece sola en el menú de la web: comparte su enlace o pide al administrador que la enlace. Si en lugar de texto quieres montarla con secciones como las de la web, en la página vacía pulsa **+** y elige una sección de «Secciones MatSurfer»; a partir de ahí será una página de secciones.',
				),
			),
		),

		/* ── 7 ─────────────────────────────────────────────────────────── */
		array(
			'id'      => 'fichas',
			'icon'    => 'groups',
			'title'   => 'Equipo, aplicaciones, publicaciones y valoraciones',
			'summary' => 'Fichas con inglés y español juntos, orden y publicar/borrador.',
			'content' => array(
				array(
					'type' => 'p',
					'text' => 'Estos cuatro menús guardan contenido que aparece en varias páginas a la vez. Cada elemento es una **ficha** con los dos idiomas en la misma pantalla: los textos en inglés (EN) y en español (ES) están en dos columnas, uno al lado del otro.',
				),
				array(
					'type'  => 'table',
					'head'  => array( 'Menú', 'Qué es', 'Dónde se ve' ),
					'rows'  => array(
						array( '[Equipo](admin:edit.php?post_type=mats_team)', 'Cada persona: nombre, foto, cargo, institución, biografía, LinkedIn y web.', 'Página Equipo.' ),
						array( '[Aplicaciones](admin:edit.php?post_type=mats_application)', 'Cada campo de aplicación: etiqueta, título, resumen, reto, uso y beneficio.', 'Página Aplicaciones y accesos de la página de inicio.' ),
						array( '[Publicaciones](admin:edit.php?post_type=mats_publication)', 'Cada artículo científico: título (en inglés), autores, revista, año, DOI o enlace, descripción y figura.', 'Página Casos de estudio.' ),
						array( '[Valoraciones](admin:edit.php?post_type=mats_expert)', 'Cada cita de un investigador que ha probado el dispositivo: nombre, cargo, institución, campo, cita, fecha y foto.', 'Página Casos de estudio.' ),
					),
				),
				array(
					'type'  => 'steps',
					'items' => array(
						'Entra en el menú (por ejemplo **Equipo**) y pulsa el nombre de una ficha para cambiarla, o **Añadir persona** / **Añadir aplicación** / **Añadir publicación** / **Añadir valoración** para crear una nueva.',
						'Rellena los textos **en las dos columnas, EN y ES**. Si dejas vacío un texto en español, la web española mostrará el inglés.',
						'Pon la foto en el recuadro **Foto** (pulsa **Elegir foto**).',
						'**Orden**: en el recuadro de atributos, el número de **Orden** decide la posición (el 1 va antes que el 2). Las publicaciones se ordenan solas, de la más reciente a la más antigua, y las valoraciones por su fecha.',
						'Pulsa **Publicar** (o **Actualizar** si ya estaba publicada). Solo las fichas **publicadas** se ven en la web: si quieres preparar una sin que se vea, pulsa **Guardar borrador**.',
					),
				),
				array(
					'type' => 'tip',
					'text' => 'Para quitar algo de la web sin perderlo, cámbialo a **Borrador** (en el recuadro **Publicar**, «Estado: Publicado» → **Editar** → Borrador → **Actualizar**). Si lo mueves a la **Papelera**, puedes recuperarlo durante 30 días.',
				),
			),
		),

		/* ── 8 ─────────────────────────────────────────────────────────── */
		array(
			'id'      => 'contactos',
			'icon'    => 'email-alt',
			'title'   => 'Contactos recibidos',
			'summary' => 'Leer, responder, marcar como atendido y exportar a Excel.',
			'content' => array(
				array(
					'type' => 'p',
					'text' => 'Cada vez que alguien envía el formulario de la página Contacto pasan dos cosas: llega **un email de aviso** y el mensaje se guarda en **Contactos**, para que ninguno se pierda aunque el email falle o acabe en spam.',
				),
				array(
					'type' => 'h3',
					'text' => 'El email que llega',
				),
				array(
					'type'  => 'list',
					'items' => array(
						'Llega a la dirección de **Email de contacto** de [Ajustes de la web](#ajustes) (ahora: **{contact_email}**).',
						'El asunto es **[MatSurfer] Nueva consulta (ES)** o **[MatSurfer] New enquiry (EN)**, según el idioma en que escribió la persona.',
						'Incluye todo lo que rellenó y, al final, desde qué página y qué botón llegó.',
						'Si respondes a ese email, la respuesta va **directamente a la persona** que escribió.',
					),
				),
				array(
					'type' => 'h3',
					'text' => 'La lista de Contactos',
				),
				array(
					'type'  => 'steps',
					'items' => array(
						'Entra en [Contactos](admin:admin.php?page=mats-leads). El número rojo junto al menú dice cuántos hay **nuevos** (sin leer).',
						'Arriba puedes ver **Todos**, **Nuevos**, **Leídos** o **Atendidos**, filtrar por idioma y buscar por nombre, email u organización.',
						'Pulsa el nombre para ver el mensaje completo. Al abrirlo pasa a **Leído**.',
						'Pulsa **Responder por email**: se abre tu programa de correo con la respuesta preparada. Fíjate en el aviso del idioma: responde en el idioma en que escribió.',
						'Cuando ya le hayáis respondido, en **Estado** elige **Atendido** y pulsa **Guardar estado**. (En la lista también puedes marcar varios a la vez con las casillas y **Acciones en lote → Marcar como atendido**.)',
						'Para tenerlos en Excel, pulsa **Exportar CSV** (arriba): se descarga un archivo que se abre con Excel, Numbers o Google Sheets. Puedes elegir unas fechas o descargarlos todos.',
					),
				),
				array(
					'type' => 'warn',
					'text' => 'Son datos personales: no los reenvíes fuera del equipo y borra los que ya no hagan falta (**Borrar este contacto**). Si alguien pide que borremos sus datos, avisa al administrador de la web.',
				),
			),
		),

		/* ── 9 ─────────────────────────────────────────────────────────── */
		array(
			'id'      => 'formularios',
			'icon'    => 'feedback',
			'title'   => 'El formulario de contacto (WPForms)',
			'summary' => 'Dónde se cambian sus textos y a quién llega el aviso.',
			'content' => array(
				array(
					'type' => 'p',
					'text' => 'El formulario de la página Contacto lo hace el plugin **WPForms**. Hay dos: **Contacto MatSurfer (EN)** para la web en inglés y **Contacto MatSurfer (ES)** para la española.',
				),
				array(
					'type'  => 'table',
					'head'  => array( 'Quiero cambiar…', 'Dónde' ),
					'rows'  => array(
						array( 'El título, el texto de entrada y los pasos que acompañan al formulario', 'En la página **Contacto** (y en **Contacto** en inglés), como cualquier otro texto: [Cambiar los textos de una página](#textos).' ),
						array( 'A quién llega el aviso', '**Email de contacto** en [Ajustes de la web](#ajustes). El aviso de los dos formularios cambia solo.' ),
						array( 'Las etiquetas de los campos, las opciones de los desplegables o el botón Enviar', 'Solo administradores: **WPForms → Todos los formularios →** el formulario del idioma → cambia el campo → **Guardar**.' ),
						array( 'El mensaje de gracias que aparece al enviar', 'Solo administradores: en el formulario, **Ajustes → Confirmaciones**.' ),
					),
				),
				array(
					'type' => 'warn',
					'text' => 'En WPForms no se deben **borrar ni cambiar de tipo** los campos (nombre, email, organización, cargo, interés, área, mensaje y la casilla de privacidad): la lista de Contactos y el diseño del formulario dependen de ellos. Cambiar sus textos sí se puede.',
				),
				array(
					'type' => 'note',
					'text' => 'Si el formulario no estuviera disponible, la página Contacto muestra en su lugar el email de contacto, así que nadie se queda sin poder escribiros.',
				),
			),
		),

		/* ── 10 ────────────────────────────────────────────────────────── */
		array(
			'id'      => 'ajustes',
			'icon'    => 'admin-settings',
			'title'   => 'Ajustes de la web: menú, pie y email',
			'summary' => 'Lo que se repite en todas las páginas, en los dos idiomas.',
			'content' => array(
				array(
					'type' => 'p',
					'text' => 'En **Ajustes de la web** están los textos que no pertenecen a una página concreta porque salen en todas:',
				),
				array(
					'type'  => 'list',
					'items' => array(
						'**Cabecera**: los nombres del menú de arriba y el texto de su botón («Talk to the team» / «Habla con el equipo»).',
						'**Pie de página**: la descripción, los títulos y enlaces de las columnas, el copyright y la línea final.',
						'**Email de contacto**: la dirección pública de la web y la que recibe los avisos del formulario.',
						'**Noticias y Google**: el cierre de las páginas de noticias y los textos que usan Google y las redes sociales cuando una página no tiene los suyos.',
					),
				),
				array(
					'type'  => 'steps',
					'items' => array(
						'Abre **Ajustes de la web** en el menú de la izquierda.',
						'Cada texto tiene su versión en **inglés** y en **español**: cambia los dos.',
						'Pulsa el botón de guardar al final de la pantalla. Los cambios se ven en toda la web al momento.',
					),
				),
				array(
					'type'  => 'links',
					'items' => array(
						array(
							'label' => 'Abrir Ajustes de la web',
							'href'  => 'settings',
						),
					),
				),
				array(
					'type' => 'tip',
					'text' => 'Si cambias el **Email de contacto**, envía después una prueba desde la página Contacto para comprobar que el aviso llega (mira también en la carpeta de spam).',
				),
			),
		),

		/* ── 11 ────────────────────────────────────────────────────────── */
		array(
			'id'      => 'no-se-puede',
			'icon'    => 'lock',
			'title'   => 'Qué no se puede hacer (y por qué)',
			'summary' => 'Lo que está bloqueado para proteger el diseño y la seguridad.',
			'content' => array(
				array(
					'type' => 'p',
					'text' => 'Algunas cosas están bloqueadas a propósito. No es un error: es lo que permite que cualquiera pueda editar sin miedo a estropear la web.',
				),
				array(
					'type'  => 'table',
					'head'  => array( 'No se puede…', 'Porque…' ),
					'rows'  => array(
						array( 'Añadir, mover o borrar secciones de las páginas principales', 'Cada página está diseñada como un conjunto. Si necesitáis una sección nueva, pedidla al administrador.' ),
						array( 'Borrar las páginas principales o cambiar su dirección o su idioma', 'Las usan el menú, los botones, los enlaces que ya circulan y Google. En la lista de Páginas aparecen como «Página fija: no se puede borrar».' ),
						array( 'Cambiar colores, tipos o tamaños de letra', 'La identidad visual de MatSurfer es la misma en toda la web. Los textos solo se escriben; el diseño lo pone la web.' ),
						array( 'Pegar código HTML, scripts o «shortcodes»', 'Por seguridad: un código pegado puede romper la página o abrir una puerta a ataques.' ),
						array( 'Subir imágenes SVG o fotos de más de {max_image}', 'Los SVG pueden llevar código oculto; las fotos enormes hacen la web lenta. Usa JPG o PNG (ver [Consejos para las fotos](#consejos-fotos)).' ),
						array( 'Comentarios en las noticias', 'Están desactivados en toda la web: los mensajes llegan por el formulario de Contacto, que es más seguro y no recibe spam.' ),
						array( 'Instalar plugins, cambiar el tema, tocar los ajustes de WordPress o crear usuarios', 'Es trabajo del administrador de la web, que se encarga de las copias de seguridad y las actualizaciones.' ),
					),
				),
			),
		),

		/* ── 12 ────────────────────────────────────────────────────────── */
		array(
			'id'      => 'consejos-fotos',
			'icon'    => 'camera',
			'title'   => 'Consejos para las fotos',
			'summary' => 'Tamaños, peso, formato y texto alternativo.',
			'content' => array(
				array(
					'type'  => 'table',
					'head'  => array( 'Para…', 'Forma y tamaño recomendado' ),
					'rows'  => array(
						array( 'Foto destacada de una noticia', 'Horizontal, unos **1600 × 900 px**. En las tarjetas se recorta un poco por arriba y por abajo: deja lo importante en el centro.' ),
						array( 'Fotos de las secciones (cabeceras, producto…)', 'Horizontal, de **2000 a 2560 px** de ancho.' ),
						array( 'Fotos dentro del texto de una noticia', 'Unos **1600 px** de ancho.' ),
						array( 'Retratos del equipo', 'Vertical o cuadrado, bien iluminado y con fondo neutro, de al menos **1200 px** de alto.' ),
						array( 'Figuras de publicaciones, logos o gráficos con texto', '**PNG**, al tamaño en que se van a ver.' ),
					),
				),
				array(
					'type'  => 'list',
					'items' => array(
						'**Peso**: mejor por debajo de **1 MB** (entre 300 y 600 KB es ideal). Las fotos más grandes de 2560 px se reducen solas, pero tardan más en subir. Máximo por archivo: **{max_upload}**.',
						'**Formato**: **JPG** para fotos; **PNG** para gráficos, capturas o imágenes con fondo transparente. WebP también vale. SVG no.',
						'**Texto alternativo**: escribe siempre una frase que describa lo que se ve, en el idioma de la página. Lo leen las personas ciegas y Google. No pongas «foto» ni el nombre del archivo.',
						'**Nombre del archivo**: antes de subirla, ponle un nombre que explique la foto (`matsurfer-dispositivo-laboratorio.jpg` mejor que `IMG_2045.jpg`).',
						'**Derechos**: usa solo fotos vuestras o con permiso, y con el consentimiento de las personas que aparecen.',
					),
				),
				array(
					'type' => 'tip',
					'text' => 'Para reducir una foto sin programas: en Windows, ábrela con **Fotos → Cambiar tamaño**; en Mac, con **Vista Previa → Herramientas → Ajustar tamaño**; o usa una web gratuita como squoosh.app.',
				),
			),
		),

		/* ── 13 ────────────────────────────────────────────────────────── */
		array(
			'id'      => 'ayuda',
			'icon'    => 'sos',
			'title'   => '¿A quién pido ayuda?',
			'summary' => 'Dudas de contenido, problemas técnicos y contraseñas.',
			'content' => array(
				array(
					'type'  => 'table',
					'head'  => array( 'Si…', 'Escribe a…' ),
					'rows'  => array(
						array( 'Tienes una duda sobre cómo hacer algo', 'Primero, mira esta guía (está siempre en el menú, abajo del todo: **Guía de uso**).' ),
						array( 'Tienes una duda sobre qué publicar o cómo decirlo', 'Al equipo de MatSurfer del ICMAB: [{contact_email}](mailto:{contact_email}).' ),
						array( 'Algo no funciona, ves un error o has borrado algo por error', 'Al administrador de la web: [{admin_email}](mailto:{admin_email}).' ),
						array( 'Necesitas una sección nueva, un cambio de diseño o un usuario para otra persona', 'Al administrador de la web: [{admin_email}](mailto:{admin_email}).' ),
						array( 'Has olvidado tu contraseña', 'Nadie: pulsa **¿Has olvidado tu contraseña?** en la pantalla de entrada.' ),
					),
				),
				array(
					'type' => 'tip',
					'text' => 'Para que te ayuden rápido, cuenta en qué página estabas, qué querías hacer y qué ha pasado, y adjunta una captura de pantalla.',
				),
			),
		),

		/* ── 14 (administrators) ───────────────────────────────────────── */
		array(
			'id'       => 'administradores',
			'icon'     => 'shield',
			'title'    => 'Para administradores',
			'summary'  => 'Puesta en marcha, usuarios, bloqueos y escapes.',
			'audience' => 'admin',
			'content'  => array(
				array(
					'type'  => 'list',
					'items' => array(
						'**Puesta en marcha** (menú MatSurfer): instala los plugins, configura los idiomas, crea las páginas con los textos originales y los formularios. Se puede repetir sin duplicar nada.',
						'**Usuarios**: crea un usuario con el rol **Editor** para cada persona del ICMAB. Los editores ven solo el contenido (sin Herramientas, Apariencia, Plugins, Ajustes, Usuarios, Idiomas ni WPForms) y no pueden pegar HTML. No compartáis el usuario administrador.',
						'**Idioma del panel**: si WordPress tiene instalado el español, los usuarios que no hayan elegido idioma ven el panel en español. Instálalo en **Ajustes → Generales → Idioma del sitio** (o en Escritorio → Actualizaciones) si no aparece.',
						'**Secciones bloqueadas**: como administrador puedes desbloquear una sección (menú ⋮ de la sección → Desbloquear), moverla o añadir otras; los editores no pueden. También tienes el editor de código y los bloques **HTML personalizado** y **Shortcode**.',
						'**Páginas principales**: nadie puede borrarlas. Para retirar una, quítale antes su papel: borra el campo `_mats_role` de la página y de su traducción y su entrada en la opción `mats_page_roles`.',
						'**Ya desactivado**: comentarios y pingbacks, XML-RPC, subida de SVG, listado público de usuarios, mensajes de error de acceso que revelan usuarios, colores y tamaños libres en el editor, patrones remotos, Openverse y el directorio de bloques.',
						'**Filtros** para ajustar el tema sin tocarlo: `mats_allowed_blocks`, `mats_admin_tiles`, `mats_guide_data`, `mats_admin_max_image_mb`, `mats_editor_lock_design`, `mats_editor_remove_patterns`, `mats_editor_restrict_unfiltered_html`, `mats_admin_settings_slug`.',
						'**Recomendado**: WP Mail SMTP conectado a una cuenta de correo para que los avisos del formulario no acaben en spam, copias de seguridad automáticas en Hostinger y actualizaciones de plugins al día.',
					),
				),
			),
		),
	),
	'footer'   => 'Guía de la web de MatSurfer (ICMAB-CSIC). Si algo de esta guía no coincide con lo que ves en pantalla, avisa al administrador de la web: [{admin_email}](mailto:{admin_email}).',
);
