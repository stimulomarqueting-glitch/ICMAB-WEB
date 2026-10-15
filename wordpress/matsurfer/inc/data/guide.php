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
 * Any block may add 'when' => 'es_admin' | 'no_es_admin' (shown only when
 * WordPress' Spanish translation is / is not installed).
 * Text supports **bold**, `Tecla`, [enlace](admin:pantalla.php | #seccion | site:/es/ | mailto:… | https://…)
 * and the placeholders {contact_email} {admin_email} {site_url} {site_url_es}
 * {login_url} {max_upload} {max_image} {settings_url} {user}.
 *
 * Keep it true to what the theme does: when a screen changes, change the
 * guide in the same commit. Screens described here: inc/modules/admin.php
 * and editor.php (dashboard, menu, editor rules), blog.php (news),
 * content-types-admin.php (Equipo, Aplicaciones, Publicaciones,
 * Valoraciones), leads.php (Contactos), wpforms.php (contact form),
 * settings.php (Ajustes de la web), seo.php (Google y redes sociales),
 * security.php (login, uploads) and installer.php (Puesta en marcha).
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
						'Pulsa **Acceder**. Llegarás al **Escritorio**: arriba está el panel «Bienvenido/a a la web de MatSurfer» con accesos grandes a lo que se hace a diario (escribir una noticia, editar la página de inicio, ver los contactos…). Debajo, **Contactos recientes** muestra los últimos mensajes recibidos y **Noticias recientes** las últimas noticias, con un aviso cuando a una le falta la versión en el otro idioma.',
						'Para salir, pasa el ratón por tu nombre (arriba a la derecha) y elige **Cerrar sesión**. Hazlo siempre si usas un ordenador compartido.',
					),
				),
				array(
					'type' => 'tip',
					'text' => '¿Has olvidado la contraseña? En la pantalla de entrada pulsa **¿Has olvidado la contraseña?** y escribe tu usuario o tu email: te llegará un email para crear una nueva (mira también en spam). Por seguridad, la pantalla siempre responde lo mismo, exista o no esa dirección, y nunca dice si lo que estaba mal era el usuario o la contraseña.',
				),
				array(
					'type' => 'warn',
					'text' => 'Si escribes mal la contraseña **5 veces seguidas**, el acceso de ese usuario se bloquea **15 minutos** («Acceso bloqueado un rato por seguridad»). Espera los minutos que indica o usa **¿Has olvidado la contraseña?**. Al entrar bien, la cuenta vuelve a empezar de cero.',
				),
				array(
					'type' => 'note',
					'text' => 'El menú de la izquierda solo muestra lo que necesitas: Contactos, Noticias, Páginas, Equipo, Aplicaciones, Publicaciones, Valoraciones, Medios, Ajustes de la web, Perfil y, abajo del todo, esta **Guía de uso**.',
				),
				array(
					'type' => 'note',
					'when' => 'es_admin',
					'text' => 'Si ves el panel en inglés, ve a **Perfil → Idioma**, elige **Español** y pulsa **Actualizar perfil**.',
				),
				array(
					'type' => 'note',
					'when' => 'no_es_admin',
					'text' => 'Si ves partes del panel en inglés (Dashboard, Pages, Media…), es porque en esta web todavía no está instalada la traducción de WordPress al español. Pídeselo al administrador de la web: [{admin_email}](mailto:{admin_email}). Los menús propios de la web (Contactos, Noticias, Equipo, Ajustes de la web, esta guía…) ya están en español.',
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
					'text' => 'Las noticias aparecen en la página **Noticias** de la web: la más reciente, en grande, arriba, y el resto en tres columnas (10 en la primera página y 9 en las siguientes, con los botones «Más recientes / Anteriores»). Cada noticia se escribe en un idioma; la versión en el otro idioma se crea con el botón **+** de **Idiomas** (ver más abajo). El enlace **Noticias** del menú y del pie de la web aparece solo cuando hay al menos una noticia publicada en ese idioma.',
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
						'Para añadir otra cosa (un **Encabezado** para separar apartados, una **Imagen**, una **Galería**, un vídeo, una **Cita**, una **Tabla**, un PDF con **Archivo**, **Botones** o un **Separador**) pulsa el **+** azul de arriba a la izquierda o el **+** que aparece entre párrafos, y elige.',
						'Para un vídeo de YouTube o Vimeo basta con **pegar el enlace del vídeo** en una línea vacía: se convierte solo en el reproductor.',
						'Para poner un enlace: selecciona las palabras, pulsa el icono de la cadena (o `Ctrl` + `K`), pega la dirección y pulsa `Intro`.',
					),
				),
				array(
					'type' => 'note',
					'text' => 'En el editor el texto se ve con la misma letra, los mismos tamaños y los mismos estilos de cita, tabla y botones que en la web. Solo se pueden usar los colores de la marca (**Tinta, Coral, Violeta, Ciruela y Crema**) y cuatro tamaños de letra (**S, M, L y XL**). No hay colores libres, degradados, sombras ni márgenes, para que el diseño no se rompa. No hace falta añadir una llamada final: cada noticia termina sola con la banda «Habla con el equipo».',
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
						'**Foto destacada**: pulsa **Elegir la foto destacada**, sube la foto o elige una de la biblioteca y pulsa el botón para usarla. Es la foto de la tarjeta de la noticia (se recorta a formato 16:10), la foto grande de la cabecera del artículo y la imagen que se ve al compartirla en redes sociales. Mejor horizontal (ver [Consejos para las fotos](#consejos-fotos)). Sin foto destacada, la tarjeta muestra un fondo suave con el símbolo de MatSurfer.',
						'**Categorías**: despliega **Categorías** y marca «News» (en inglés) o «Noticias» (en español). Si además marcas otra categoría, esa aparece como etiqueta morada sobre el título y como filtro encima de la lista de noticias. Puedes crear una con **Añadir nueva categoría** (recuerda crear también su versión en el otro idioma en **Noticias → Categorías**).',
						'**Extracto**: pulsa **Añadir un extracto…** y escribe un resumen de una o dos frases. Se ve como entradilla bajo el título del artículo, en la tarjeta de la noticia y en Google. Si lo dejas vacío, la tarjeta usa el principio del texto y el artículo no muestra entradilla.',
						'**Fijar en la parte superior del blog**: si la marcas, esa noticia sale la primera, en grande, en la página Noticias, aunque haya otras más nuevas.',
						'**Google y redes sociales**: el título y la descripción que se ven en Google (ver [Google y redes sociales](#google)). Es opcional.',
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
						'¿Quieres que salga otro día? Antes de publicar, en el panel de la derecha cambia **Publicar: Inmediatamente** por la fecha y la hora; el botón pasará a llamarse **Programar**.',
						'Para cambiar una noticia ya publicada: ábrela en [Noticias](admin:edit.php), corrige y pulsa **Guardar** (en versiones antiguas de WordPress se llama «Actualizar»).',
					),
				),
				array(
					'type' => 'p',
					'text' => 'Cada artículo muestra solo la categoría, la fecha, los minutos de lectura (se calculan solos), los botones para compartir en LinkedIn y por email, el artículo anterior y el siguiente, y la banda final para hablar con el equipo. El texto de esa banda se cambia en [Ajustes de la web](#ajustes) → **Cierre de las noticias**. Las noticias no tienen comentarios: están desactivados en toda la web.',
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
					'text' => 'Al abrir una página principal verás arriba el aviso «Esta página está formada por secciones bloqueadas: puedes cambiar textos y fotos, no la estructura». Es normal: las secciones no se pueden mover, borrar ni añadir (por eso no hay botón **+**), para que el diseño se mantenga.',
				),
				array(
					'type' => 'p',
					'text' => 'Algunas secciones muestran contenido que se guarda en otro sitio: el **equipo**, las **aplicaciones**, las **publicaciones** y las **valoraciones de expertos**. En el editor verás un aviso con un enlace a donde se cambian (ver [Equipo, aplicaciones, publicaciones y valoraciones](#fichas)). En la página **Contacto**, los textos de la izquierda (título, texto de entrada, «Qué pasa después», «¿Prefieres el email?») se cambian igual, haciendo clic sobre ellos; el formulario en sí está en [El formulario de contacto](#formularios).',
				),
				array(
					'type' => 'p',
					'text' => 'Los textos que se repiten en todas las páginas (el menú de arriba y el pie de página) no están en las páginas: se cambian en [Ajustes de la web](#ajustes). El antetítulo de la portada, en cambio, se cambia editando la página de Inicio.',
				),
				array(
					'type' => 'h3',
					'text' => 'Páginas especiales',
				),
				array(
					'type'  => 'list',
					'items' => array(
						'**Noticias**: la lista de noticias se rellena sola. En esta página solo cambias el título, el **extracto** (el texto de entrada bajo el título) y, si quieres, la **foto destacada** de la cabecera.',
						'**Política de privacidad** y **Aviso legal**: son páginas de texto normal. Se crearon como **borradores** con el texto «Pendiente: texto legal facilitado por el ICMAB.» Cuando el ICMAB tenga los textos legales, pégalos (usa **Encabezado** para cada apartado) en inglés y en español y publica las dos versiones. Mientras sean borradores, la web no las enlaza. El título grande sale del título de la página, el extracto sirve de entradilla, la fecha «Actualizado el» es automática y, si hay tres o más encabezados, aparece solo un índice «En esta página».',
					),
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
						'**Equipo**: cada persona tiene **dos fotos**. El **Retrato** (recuadro de la derecha, botón **Elegir retrato**) es la foto pequeña y redonda del selector de personas. El **Recorte sin fondo** (al final del recuadro «Contenido», botón **Elegir recorte**) es la foto grande del escaparate de la página Equipo: la persona recortada, con el fondo transparente. Si falta alguna, se ven sus iniciales.',
						'**Publicaciones**: el recuadro **Figura** guarda una figura del artículo; **Valoraciones**: el recuadro **Foto** guarda una foto de la persona. El diseño actual de Casos de estudio no muestra ninguna de las dos, así que son opcionales.',
						'**Cada idioma tiene su página**: si cambias una foto en la página en inglés, cámbiala también en la española. (Las fotos del equipo, las aplicaciones, las publicaciones y las valoraciones son una sola para los dos idiomas.)',
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
					'text' => 'El idioma de las páginas principales y su enlace con la otra versión no se pueden cambiar: de ellos dependen el menú y todos los botones de la web.',
				),
				array(
					'type' => 'note',
					'text' => 'El equipo, las aplicaciones, las publicaciones y las valoraciones funcionan distinto: **una sola ficha** guarda los dos idiomas, uno al lado del otro (ver [Equipo, aplicaciones, publicaciones y valoraciones](#fichas)). Lo mismo pasa en [Ajustes de la web](#ajustes): cada texto tiene una casilla EN y otra ES.',
				),
				array(
					'type' => 'tip',
					'text' => 'Cuando cambies algo importante en un idioma, apúntate hacer el mismo cambio en el otro. Así las dos webs dicen lo mismo.',
				),
			),
		),

		/* ── 6 ─────────────────────────────────────────────────────────── */
		array(
			'id'      => 'google',
			'icon'    => 'search',
			'title'   => 'Google y redes sociales',
			'summary' => 'El título y la descripción de cada página en Google y al compartirla.',
			'content' => array(
				array(
					'type' => 'p',
					'text' => 'Cada página y cada noticia tiene en el panel de la derecha del editor (pestaña **Página** o **Noticia**) el recuadro **Google y redes sociales**: pulsa su título para abrirlo. Muestra una vista previa de cómo saldrá en Google. Es opcional: si lo dejas vacío, la web usa textos razonables.',
				),
				array(
					'type'  => 'table',
					'head'  => array( 'Casilla', 'Qué es', 'Si la dejas vacía' ),
					'rows'  => array(
						array( '**Título en Google**', 'El título azul de Google y el de la pestaña del navegador. Recomendado: **60 caracteres como máximo** (el contador se pone naranja si se pasa).', 'La portada usa el título general de [Ajustes de la web](#ajustes); las demás páginas y las noticias, «Título de la página — MatSurfer».' ),
						array( '**Descripción en Google**', 'El texto gris bajo el título. También se usa al compartir en LinkedIn, X, WhatsApp… Recomendado: **entre 50 y 155 caracteres**.', 'Se usa el **extracto** de la página o la noticia; si no hay, la descripción general de la web (en las noticias, el principio del texto).' ),
					),
				),
				array(
					'type'  => 'list',
					'items' => array(
						'La imagen que se ve al compartir una página o una noticia en redes sociales es su **foto destacada** (panel de la derecha). Mejor horizontal, de **1200 × 630 px** o más: la web la recorta sola. Sin foto destacada se usa la imagen general de MatSurfer.',
						'La página en inglés y la española son distintas: escribe los textos de Google de cada idioma en su propia página.',
						'Algunos textos originales (la portada, páginas en español) son más largos de lo recomendado y el contador sale en naranja. Solo significa que Google puede cortar el final; no se rompe nada.',
						'Después de cambiar una imagen o un texto, LinkedIn o Facebook pueden seguir mostrando la vista previa antigua un tiempo. Se puede refrescar con LinkedIn Post Inspector o con el depurador de Facebook (Sharing Debugger).',
					),
				),
			),
		),

		/* ── 7 ─────────────────────────────────────────────────────────── */
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
						'Escribe el **título** y el **texto** (con encabezados, listas, citas, imágenes, tablas, separadores o botones si los necesitas).',
						'Opcional: en el panel de la derecha, **Añadir un extracto…** será la entradilla bajo el título y la **imagen destacada**, la foto de la cabecera.',
						'Pulsa **Publicar**. La página se verá con el diseño de lectura de la web: el título grande arriba y el texto en una columna cómoda de leer (como el Aviso legal).',
						'Crea su versión en el otro idioma con el **+** de **Idiomas** y publícala también.',
					),
				),
				array(
					'type' => 'note',
					'text' => 'Una página nueva no aparece sola en el menú de la web: comparte su enlace o pide al administrador que la enlace. Si en lugar de texto quieres montarla con secciones como las de la web, en la página todavía vacía pulsa **+** y elige una sección de «Secciones MatSurfer»; a partir de ahí será una página de secciones.',
				),
			),
		),

		/* ── 8 ─────────────────────────────────────────────────────────── */
		array(
			'id'      => 'fichas',
			'icon'    => 'groups',
			'title'   => 'Equipo, aplicaciones, publicaciones y valoraciones',
			'summary' => 'Fichas con inglés y español juntos, fotos, orden y publicar/borrador.',
			'content' => array(
				array(
					'type' => 'p',
					'text' => 'Estos cuatro menús guardan contenido que aparece en varias páginas a la vez. Cada elemento es una **ficha** con los dos idiomas en la misma pantalla: en el recuadro **Contenido**, los textos en inglés (columna **EN**, a la izquierda) y en español (columna **ES**, a la derecha) están uno al lado del otro. Lo que no depende del idioma (enlaces, año, fecha, DOI, identificador, fotos) se escribe una sola vez, a todo el ancho.',
				),
				array(
					'type'  => 'table',
					'head'  => array( 'Menú', 'Qué es', 'Dónde se ve' ),
					'rows'  => array(
						array( '[Equipo](admin:edit.php?post_type=mats_team)', 'Cada persona: nombre y apellidos, cargo, institución, biografía breve, LinkedIn, web personal (solo se muestra si no tiene LinkedIn), **Retrato** y **Recorte sin fondo**.', 'Página Equipo.' ),
						array( '[Aplicaciones](admin:edit.php?post_type=mats_application)', 'Cada campo de aplicación: etiqueta, título y los tres bloques «La necesidad», «Con MatSurfer» y «Para tu equipo». El «Resumen en una línea» se guarda, pero el diseño actual no lo muestra.', 'Página Aplicaciones y accesos de la página de inicio.' ),
						array( '[Publicaciones](admin:edit.php?post_type=mats_publication)', 'Cada artículo científico: título del artículo, autores, revista, año, DOI, enlace y descripción breve. La **Figura** se guarda, pero el diseño actual no la muestra.', 'Página Casos de estudio.' ),
						array( '[Valoraciones](admin:edit.php?post_type=mats_expert)', 'Cada cita de un investigador que ha probado el dispositivo: nombre, cargo, institución, campo científico, cita, enlace y fecha (año y mes). La **Foto** se guarda, pero el diseño actual no la muestra.', 'Página Casos de estudio.' ),
					),
				),
				array(
					'type' => 'h3',
					'text' => 'Crear o cambiar una ficha',
				),
				array(
					'type'  => 'steps',
					'items' => array(
						'Entra en el menú (por ejemplo **Equipo**) y pulsa el nombre de una ficha para cambiarla, o **Añadir persona** / **Añadir aplicación** / **Añadir publicación** / **Añadir valoración** para crear una nueva.',
						'Arriba, el primer campo depende del menú: en Equipo, **Nombre y apellidos** (igual en los dos idiomas); en Publicaciones, **Título del artículo** (igual en las dos webs); en Aplicaciones y Valoraciones, un **Nombre interno** que solo se ve en el panel (si lo dejas vacío, se rellena solo).',
						'Rellena los textos **en las dos columnas, EN y ES**. Si dejas vacío un texto en español, la web en español mostrará el inglés: el campo se marca en amarillo con «Sin traducir» y arriba aparece el aviso «Falta la traducción al español en …». Bajo cada texto, un contador («123 / 160 caracteres recomendados») avisa si es «un poco largo», pero deja guardar.',
						'Fotos: en **Equipo**, el **Retrato** está en el recuadro de la derecha (**Elegir retrato**) y el **Recorte sin fondo** al final de «Contenido» (**Elegir recorte**). En Publicaciones el recuadro se llama **Figura** y en Valoraciones **Foto**. Mira los tamaños en [Consejos para las fotos](#consejos-fotos).',
						'Pulsa **Publicar** (o **Actualizar** si ya estaba publicada). Solo las fichas **publicadas** se ven en la web: si quieres preparar una sin que se vea, pulsa **Guardar borrador**.',
					),
				),
				array(
					'type' => 'h3',
					'text' => 'El orden',
				),
				array(
					'type'  => 'list',
					'items' => array(
						'**Equipo** y **Aplicaciones**: en la lista, las flechas **▲ ▼** suben o bajan una ficha. También puedes escribir el número en el recuadro **Orden en la web** de la ficha (columna derecha): el 1 va primero. Las fichas nuevas se colocan al final solas.',
						'**Publicaciones** se ordenan solas por año (la más reciente primero) y **Valoraciones** por fecha (la más reciente primero). Ahí el número de **Orden en la web** solo desempata entre las del mismo año o mes.',
						'La lista del panel está siempre en el mismo orden que la web. **Edición rápida** (al pasar el ratón por una ficha de la lista) también permite cambiar el orden y el estado.',
					),
				),
				array(
					'type' => 'h3',
					'text' => 'Bueno saberlo',
				),
				array(
					'type'  => 'list',
					'items' => array(
						'La columna **En la web** de la lista dice si cada ficha está **Visible**, **Oculta · borrador** o **Programada**; la columna **Idiomas**, si están los dos («✓ EN · ES») o falta alguno («! Falta ES: …»).',
						'Debajo del nombre, cada ficha dice dónde se ve («Se ve en la página Equipo · Ver en inglés ↗ · Ver en español ↗»), y en la lista cada ficha publicada tiene los enlaces **Ver en la web (EN)** y **Ver en la web (ES)**.',
						'**Aplicaciones**: el **Identificador para enlaces** se rellena solo a partir del título en inglés y forma parte del enlace a la aplicación (…/applications/#identificador) y del formulario de contacto. Tras la primera publicación queda bloqueado; **Cambiar** lo desbloquea, pero los enlaces antiguos dejarán de funcionar.',
						'**Publicaciones**: en **DOI** va solo el código (10.xxxx/…); si pegas el enlace completo, se recorta solo. Si **Enlace al artículo** se queda vacío, se enlaza a https://doi.org/ + el DOI. El año, con cuatro cifras.',
						'**Valoraciones**: el nombre es opcional (sin nombre, la cita sale sin firma y tampoco se ven el cargo ni la institución). Escribe la cita sin comillas: la web las añade.',
						'Las direcciones web pueden pegarse sin «https://» (se añade solo). Si un dato no es válido (dirección web, año o fecha), no se guarda: aparece un aviso rojo «Revisa estos datos» y se mantiene el valor anterior.',
						'Si intentas salir de una ficha con cambios sin guardar, el navegador te pregunta antes.',
					),
				),
				array(
					'type' => 'tip',
					'text' => 'Para quitar algo de la web sin perderlo, cámbialo a **Borrador** (en el recuadro **Publicar**, «Estado» → **Editar** → Borrador → **Actualizar**). Si lo mueves a la **Papelera**, puedes recuperarlo durante 30 días.',
				),
			),
		),

		/* ── 9 ─────────────────────────────────────────────────────────── */
		array(
			'id'      => 'contactos',
			'icon'    => 'email-alt',
			'title'   => 'Contactos recibidos',
			'summary' => 'Leer, responder, marcar como atendido y exportar a Excel.',
			'content' => array(
				array(
					'type' => 'p',
					'text' => 'Cada vez que alguien envía el formulario de la página Contacto (en inglés o en español) pasan dos cosas: llega **un email de aviso** y el mensaje se guarda en **Contactos** (el sobre de arriba del menú), para que ninguno se pierda aunque el email falle o acabe en spam. El número rojo del menú dice cuántos contactos no ha abierto nadie todavía. Solo los administradores y los editores ven Contactos, y los contactos no se pueden crear ni cambiar a mano: solo llegan del formulario.',
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
						'Incluye todo lo que rellenó y, al final, de dónde viene: Language (idioma), Source page (página), CTA (el botón de la web que pulsó; «direct» si no pulsó ninguno), Application (la aplicación que estaba mirando) y UTM (la campaña, si venía de un enlace de campaña).',
						'Para contestar basta con pulsar **Responder**: la respuesta va **directamente a la persona** que escribió.',
					),
				),
				array(
					'type' => 'h3',
					'text' => 'La lista de Contactos',
				),
				array(
					'type'  => 'steps',
					'items' => array(
						'Entra en [Contactos](admin:admin.php?page=mats-leads). En la lista ves quién escribió (nombre y cargo), su email, la organización, qué le interesa, el idioma del formulario (EN/ES), el estado y cuándo llegó. Los que nadie ha abierto salen en negrita, con un punto rojo.',
						'Arriba puedes ver **Todos**, **Nuevos**, **Leídos** o **Atendidos**, filtrar por idioma (botón **Filtrar**), buscar (nombre, email, organización, cargo o mensaje) y ordenar por nombre o por fecha.',
						'Pulsa el nombre para ver el contacto completo: el mensaje, los datos, si aceptó la política de privacidad (y cuándo) y el idioma. Al abrirlo pasa a **Leído**. El recuadro **Cómo llegó** dice qué botón de la web pulsó, desde qué página escribió, qué aplicación miraba y la campaña, si venía de un enlace de newsletter, LinkedIn…',
						'Pulsa **Responder por email**: se abre tu programa de correo con la dirección, un asunto y un saludo ya escritos en el idioma de la persona, y su mensaje citado debajo. La página te recuerda si debes contestar en inglés o en español. **Copiar el email** copia la dirección.',
						'Cuando ya le hayas respondido, pulsa **Ya le he respondido: marcar como atendido** (o elige un estado y pulsa **Guardar estado**). La página dirá quién lo marcó y cuándo. Desde la lista también puedes usar **Marcar como atendido** bajo un nombre, o marcar varios con las casillas y elegir en **Acciones en lote**: marcar como leído, como atendido, como no leído o borrar definitivamente.',
					),
				),
				array(
					'type' => 'table',
					'head' => array( 'Estado', 'Qué significa' ),
					'rows' => array(
						array( '**Nuevo**', 'Nadie lo ha abierto todavía.' ),
						array( '**Leído**', 'Alguien lo ha abierto, pero aún no se ha respondido (cambia solo al abrirlo).' ),
						array( '**Atendido**', 'Ya se le ha respondido.' ),
					),
				),
				array(
					'type' => 'h3',
					'text' => 'Llevarlos a Excel',
				),
				array(
					'type'  => 'list',
					'items' => array(
						'El botón **Exportar CSV**, junto al título, descarga los contactos de la lista tal como la ves (con los filtros puestos).',
						'El recuadro **Exportar a Excel**, más abajo, permite elegir fechas (Desde / Hasta), estado e idioma.',
						'El archivo se abre en Excel, Numbers o Google Sheets con los acentos bien.',
					),
				),
				array(
					'type' => 'warn',
					'text' => 'Son datos personales: no los reenvíes fuera del equipo y borra los que ya no hagan falta (**Borrar este contacto**, o **Borrar** en la lista). Borrar es **definitivo**: no hay papelera, y la web siempre pide confirmación. El recuadro **Borrado automático (privacidad)** lo decide el administrador (por defecto, «No borrar nunca»). Si alguien pide una copia de sus datos o que los borremos, avisa al administrador de la web.',
				),
				array(
					'type' => 'note',
					'text' => 'La web nunca guarda la dirección IP ni el navegador de quien escribe. Si en Contactos aparece «Todavía no hay ningún formulario conectado», el formulario de contacto aún no está configurado: avisa al administrador.',
				),
			),
		),

		/* ── 10 ────────────────────────────────────────────────────────── */
		array(
			'id'      => 'formularios',
			'icon'    => 'feedback',
			'title'   => 'El formulario de contacto (WPForms)',
			'summary' => 'Dónde se cambian sus textos y a quién llega el aviso.',
			'content' => array(
				array(
					'type' => 'p',
					'text' => 'El formulario de la página Contacto lo hace el plugin **WPForms**. Hay uno por idioma: **Contacto MatSurfer (EN)** para la web en inglés y **Contacto MatSurfer (ES)** para la española. Se envía sin recargar la página y tiene activada la protección contra spam.',
				),
				array(
					'type'  => 'table',
					'head'  => array( 'Quiero cambiar…', 'Dónde' ),
					'rows'  => array(
						array( 'Los textos de la izquierda (Contacto, título, texto de entrada, «Qué pasa después», «¿Prefieres el email?»)', 'En la página **Contacto** (y en **Contact**, en inglés), en el bloque «Contacto con formulario», haciendo clic sobre ellos: [Cambiar los textos de una página](#textos).' ),
						array( 'A quién llega el aviso (y el email que se muestra en la web)', '**Email de contacto** en [Ajustes de la web](#ajustes). Los avisos de los dos formularios cambian solos.' ),
						array( 'Las etiquetas de los campos, las opciones de los desplegables, el botón Enviar o el mensaje de gracias', 'Solo el administrador de la web, en WPForms (cada idioma por separado). Pídeselo: [{admin_email}](mailto:{admin_email}).' ),
					),
				),
				array(
					'type' => 'p',
					'text' => 'Los enlaces de la web a Contacto dejan ya marcada la opción **Me interesa** (producto, aplicación, colaboración, distribución, validación) y, si la persona venía de una aplicación, también el **Área de interés**. El enlace a la Política de privacidad de la casilla de consentimiento aparece solo cuando esa página está publicada en ese idioma.',
				),
				array(
					'type' => 'note',
					'text' => 'Si el formulario no estuviera disponible (por ejemplo, porque WPForms está desactivado), la página Contacto muestra en su lugar el email de contacto, así que nadie se queda sin poder escribiros. Si un email de aviso no llega, mira en la carpeta de spam y en **Contactos**, donde siempre queda una copia.',
				),
			),
		),

		/* ── 11 ────────────────────────────────────────────────────────── */
		array(
			'id'      => 'ajustes',
			'icon'    => 'admin-settings',
			'title'   => 'Ajustes de la web: menú, pie, Google y email',
			'summary' => 'Lo que se repite en todas las páginas, en los dos idiomas.',
			'content' => array(
				array(
					'type' => 'p',
					'text' => '**Ajustes de la web** (el engranaje del menú de la izquierda) guarda los textos que no pertenecen a una página concreta porque salen en todas. Tiene cinco apartados:',
				),
				array(
					'type'  => 'list',
					'items' => array(
						'**Cabecera**: el botón de contacto y los nombres del menú de arriba. Cambiar un nombre solo cambia el texto que se ve: cada opción sigue llevando a su página. Mejor nombres cortos (una o dos palabras) para que quepan en una línea.',
						'**Pie de página**: la descripción, el botón, los títulos y enlaces de las tres columnas, el copyright y la línea final.',
						'**Textos por defecto para Google**: el título, la descripción y el texto alternativo de la imagen para redes que se usan en la portada y en las páginas o noticias que no tienen los suyos en su recuadro [Google y redes sociales](#google). El contador indica cuántos caracteres muestra Google (unos 60 en el título y unos 160 en la descripción).',
						'**Cierre de las noticias**: el título, el texto y el botón (lleva a Contacto) de la banda final de la página Noticias, de cada noticia y de los resultados de búsqueda.',
						'**Email de contacto y patente**: el email que se muestra en la web (página Contacto, enlaces «escríbenos», pie) y al que llegan los avisos del formulario, y el código de la patente (al cambiarlo, cambia también en la línea final del pie en los dos idiomas).',
					),
				),
				array(
					'type'  => 'steps',
					'items' => array(
						'Abre **Ajustes de la web** en el menú de la izquierda.',
						'Cada texto tiene dos casillas: **EN** (inglés) y **ES** (español). Cambia las dos. El email y el código de patente son únicos para los dos idiomas.',
						'Dentro de una casilla vacía ves en gris el texto original: si la dejas vacía, la web usa ese texto. Cuando un texto es distinto del original, debajo aparece «Original: …» con el enlace **Volver al original**.',
						'Pulsa **Guardar cambios** (el botón está siempre visible abajo; pulsar `Intro` en una casilla también guarda). Mientras haya cambios sin guardar verás «Tienes cambios sin guardar» y el navegador te avisará antes de salir.',
						'Los cambios se ven al momento en toda la web. El aviso verde trae los enlaces **Ver la web en inglés · en español** para comprobarlo.',
					),
				),
				array(
					'type' => 'note',
					'text' => 'Cada apartado tiene el botón **Restaurar valores originales**: pide confirmación y devuelve los textos originales de ese apartado en los dos idiomas (guardando también lo que hayas cambiado en los demás). Solo se admite texto normal: sin HTML ni saltos de línea, y cada casilla tiene un límite de caracteres. «Noticias» solo aparece en el menú y en el pie cuando hay noticias publicadas en ese idioma, y «Política de privacidad» y «Aviso legal», cuando esas páginas están publicadas.',
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
					'text' => 'Si cambias el **Email de contacto**, envía después una prueba desde la página Contacto para comprobar que el aviso llega (mira también en la carpeta de spam). Si el email no es válido, no se guarda y la pantalla explica por qué.',
				),
			),
		),

		/* ── 12 ────────────────────────────────────────────────────────── */
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
						array( 'Borrar las páginas principales, cambiar su dirección, su página superior, su idioma o su enlace con la otra versión, o cambiarlas en grupo desde la lista', 'Las usan el menú, los botones, los enlaces que ya circulan y Google. En la lista de Páginas aparecen como «Página fija: no se puede borrar» y no tienen casilla para las **Acciones en lote**.' ),
						array( 'Cambiar colores libres, tipos de letra o márgenes', 'La identidad visual de MatSurfer es la misma en toda la web. En las noticias solo están los colores de la marca y los tamaños S, M, L y XL.' ),
						array( 'Pegar código HTML, scripts o «shortcodes»', 'Por seguridad: un código pegado puede romper la página o abrir una puerta a ataques.' ),
						array( 'Subir imágenes SVG, archivos de programa (HTML, JavaScript, PHP…, aunque se les cambie el nombre) o fotos de más de {max_image}', 'Los SVG y los programas pueden llevar código oculto; las fotos enormes hacen la web lenta. Sube las fotos en JPG, PNG o WebP, los vídeos en MP4 y los documentos en PDF (ver [Consejos para las fotos](#consejos-fotos)).' ),
						array( 'Comentarios en las noticias', 'Están desactivados en toda la web: los mensajes llegan por el formulario de Contacto, que es más seguro y no recibe spam.' ),
						array( 'Instalar plugins, cambiar el tema, tocar los ajustes de WordPress, los formularios de WPForms o los usuarios', 'Es trabajo del administrador de la web, que se encarga de las copias de seguridad y las actualizaciones.' ),
					),
				),
			),
		),

		/* ── 13 ────────────────────────────────────────────────────────── */
		array(
			'id'      => 'consejos-fotos',
			'icon'    => 'camera',
			'title'   => 'Consejos para las fotos',
			'summary' => 'Tamaños, peso, formato y texto alternativo.',
			'content' => array(
				array(
					'type'  => 'table',
					'head'  => array( 'Para…', 'Forma, tamaño y formato recomendados' ),
					'rows'  => array(
						array( 'Foto destacada de una noticia', 'Horizontal, de al menos **1600 px** de ancho (por ejemplo 1600 × 1000). La tarjeta la recorta a 16:10 y al compartirla en redes se recorta a 1200 × 630: deja lo importante en el centro.' ),
						array( 'Foto destacada de una página (al compartir en redes)', 'Horizontal, **1200 × 630 px** o más.' ),
						array( 'Fotos de las secciones (cabeceras, producto…)', 'Horizontal, de **2000 a 2560 px** de ancho, en JPG.' ),
						array( 'Fotos dentro del texto de una noticia', 'Unos **1600 px** de ancho, en JPG.' ),
						array( 'Equipo: **Retrato**', 'Foto **cuadrada** de la cara, con su fondo: **JPG de 800 × 800 px** y menos de **300 KB**.' ),
						array( 'Equipo: **Recorte sin fondo**', 'La persona recortada, con el **fondo transparente**: **WebP o PNG** (un JPG siempre tiene fondo), vertical o cuadrada, de unos **1200–1600 px de alto** y menos de **400 KB**. Si eliges un JPG, la ficha te avisa.' ),
						array( 'Figuras de publicaciones, logos o gráficos con texto', '**PNG** (o JPG), de unos **1200 px** de ancho.' ),
					),
				),
				array(
					'type'  => 'list',
					'items' => array(
						'**Peso**: mejor por debajo de **1 MB** (entre 300 y 600 KB es ideal). Las fotos de más de 2560 px se reducen solas, pero tardan más en subir. Máximo por archivo: **{max_upload}**.',
						'**Formato**: **JPG** para fotos, excepto el **Recorte sin fondo** del equipo (WebP o PNG con fondo transparente); **PNG** para gráficos, capturas o imágenes con fondo transparente. WebP también vale. SVG no.',
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

		/* ── 14 ────────────────────────────────────────────────────────── */
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
						array( 'Tienes una duda sobre cómo hacer algo', 'Primero, mira esta guía (está siempre en el menú, abajo del todo: **Guía de uso**). La pestaña **Ayuda** de arriba a la derecha de las listas también lleva al apartado que toca.' ),
						array( 'Tienes una duda sobre qué publicar o cómo decirlo', 'Al equipo de MatSurfer del ICMAB: [{contact_email}](mailto:{contact_email}).' ),
						array( 'Algo no funciona, ves un error o has borrado algo por error', 'Al administrador de la web: [{admin_email}](mailto:{admin_email}).' ),
						array( 'Necesitas una sección nueva, un cambio de diseño, un cambio en el formulario o un usuario para otra persona', 'Al administrador de la web: [{admin_email}](mailto:{admin_email}).' ),
						array( 'Has olvidado tu contraseña', 'Nadie: pulsa **¿Has olvidado la contraseña?** en la pantalla de entrada.' ),
					),
				),
				array(
					'type' => 'tip',
					'text' => 'Para que te ayuden rápido, cuenta en qué página estabas, qué querías hacer y qué ha pasado, y adjunta una captura de pantalla. Esta guía se puede imprimir o guardar en PDF con el botón de arriba.',
				),
			),
		),

		/* ── 15 (administrators) ───────────────────────────────────────── */
		array(
			'id'       => 'administradores',
			'icon'     => 'shield',
			'title'    => 'Para administradores',
			'summary'  => 'Puesta en marcha, usuarios, formularios, bloqueos y escapes.',
			'audience' => 'admin',
			'content'  => array(
				array(
					'type' => 'h3',
					'text' => 'Puesta en marcha',
				),
				array(
					'type'  => 'list',
					'items' => array(
						'Está en **MatSurfer → Puesta en marcha** (solo administradores). **Hacerlo todo** hace los pasos 1–4 seguidos en uno o dos minutos (no cierres la pestaña). Se puede repetir cuando quieras: nunca duplica páginas, formularios ni contenido y nunca cambia lo que ya se ha editado. Cada paso tiene también su propio botón y muestra ✓ Hecho / ✗ Pendiente.',
						'**Paso 1 (Plugins)**: instala y activa Polylang y WPForms Lite desde wordpress.org (si el servidor no puede conectar, instálalos a mano desde Plugins → Añadir nuevo y repite el paso). WP Mail SMTP es recomendable: después de instalarlo, conéctalo a la cuenta de correo desde la que saldrán los avisos y envía una prueba.',
						'**Paso 2 (Idiomas)**: inglés en la raíz (/) y español en /es/, direcciones limpias, zona horaria de Madrid, comentarios cerrados y, si puede, descarga la traducción de WordPress al español.',
						'**Paso 3 (Páginas y contenido)**: crea las 9 páginas en inglés y en español, enlazadas, con los textos y fotos originales, y carga el equipo, las aplicaciones, las publicaciones y las valoraciones (solo la primera vez: si las borras, no vuelven). **Sobrescribir los textos con los originales** devuelve los textos originales a las secciones de todas las páginas (se pierden sus cambios; no toca fichas, noticias, textos legales ni formularios). Tras actualizar el tema, el aviso «Hay textos nuevos listos para la web» se resuelve con **Repetir este paso**.',
						'**Paso 4 (Formulario de contacto)**: con WPForms activo, crea los formularios EN y ES. **Paso 5 (Revisión final)**: email de contacto, WP Mail SMTP, usuarios, textos legales y **Dar la puesta en marcha por terminada**.',
						'Mientras la web sea una copia de pruebas, marca **Disuadir a los motores de búsqueda** en Ajustes → Lectura y desmárcalo el día del lanzamiento. Después, el mapa del sitio está en /wp-sitemap.xml (ya figura en robots.txt): envíalo a Google Search Console.',
					),
				),
				array(
					'type' => 'h3',
					'text' => 'Usuarios e idioma del panel',
				),
				array(
					'type'  => 'list',
					'items' => array(
						'Crea un usuario con el rol **Editor** para cada persona del ICMAB que vaya a publicar. Los editores ven solo el contenido (sin Herramientas, Apariencia, Plugins, Ajustes, Usuarios, Idiomas ni WPForms) y no pueden pegar HTML. No compartáis el usuario administrador.',
						'Si la traducción de WordPress al español está instalada, los usuarios que no han elegido idioma en su perfil ven el panel en español.',
					),
				),
				array(
					'type' => 'warn',
					'when' => 'no_es_admin',
					'text' => 'Ahora mismo **no está instalada** la traducción de WordPress al español, así que el equipo verá parte del panel en inglés y no tendrá la opción de idioma en su Perfil. Para instalarla: **Ajustes → Generales → Idioma del sitio → Español → Guardar cambios** (WordPress la descarga en ese momento; la web pública no cambia). Después repite el paso 2 de la Puesta en marcha para traducir también los plugins.',
				),
				array(
					'type' => 'h3',
					'text' => 'Formularios y contactos',
				),
				array(
					'type'  => 'list',
					'items' => array(
						'Los formularios **Contacto MatSurfer (EN)** y **(ES)** están en WPForms → Todos los formularios. Las etiquetas, el botón y el mensaje de gracias (**Ajustes → Confirmaciones**) se cambian ahí, en cada idioma. No borres ni cambies de tipo los ocho primeros campos (nombre, email, organización, cargo, interés, área, mensaje y consentimiento) ni quites las clases CSS «lf-half» de los seis primeros: el diseño, los emails y Contactos dependen de ellos. Se pueden añadir campos nuevos (salen a todo el ancho y se guardan con su nombre en el contacto).',
						'El destinatario del aviso es el **Email de contacto** de Ajustes de la web: los dos formularios se actualizan solos, salvo que alguien haya puesto otra dirección a mano en WPForms. Ajustes de la web te muestra, en **Formularios de contacto**, qué formulario usa cada idioma y a dónde llegan los avisos.',
						'Para que los avisos no acaben en spam, el email del administrador (Ajustes → Generales) debería ser del dominio de la web, o hay que configurar WP Mail SMTP.',
						'Si falta un formulario, el Escritorio muestra **Crear los formularios de contacto**, que lo vuelve a crear con los textos originales. Plan B: WPForms → Herramientas → Importar los archivos wpforms-en.json y wpforms-es.json (carpeta del tema, inc/data/) y después pulsar ese mismo botón.',
						'**Contactos → Borrado automático (privacidad)**: por defecto «No borrar nunca»; se puede elegir borrar los de más de 6 meses, 1 año, 18 meses, 2 o 3 años. Al guardar se borran ya los que pasan de esa edad y luego se revisa cada día. Lo borrado no se recupera: exporta antes si lo necesitas.',
						'Peticiones de protección de datos: **Herramientas → Exportar datos personales / Borrar datos personales** encuentra por email los mensajes de esa persona («Mensajes del formulario de contacto (MatSurfer)»). Ajustes → Privacidad → Guía incluye un texto sugerido sobre lo que guarda el formulario.',
					),
				),
				array(
					'type' => 'h3',
					'text' => 'Bloqueos y escapes',
				),
				array(
					'type'  => 'list',
					'items' => array(
						'**Secciones bloqueadas**: como administrador puedes desbloquear una sección (menú ⋮ de la sección → **Desbloquear**) para moverla o quitarla, y añadir otras con **+**. Los editores no pueden. También tienes el editor de código y los bloques **HTML personalizado** y **Shortcode**.',
						'**Páginas principales**: nadie puede borrarlas. Para retirar una, quítale antes su papel: borra el campo `_mats_role` de la página y de su traducción y su entrada en la opción `mats_page_roles`. Los editores no pueden cambiar su dirección, página superior, estado, contraseña, idioma ni su enlace con la otra versión; los administradores sí.',
						'**Ya desactivado**: comentarios y pingbacks, XML-RPC, subida de SVG y archivos de programa, edición del código del tema y los plugins desde el panel (para todos), listado público de usuarios, mensajes de acceso que revelan usuarios, etiquetas (Tags) de las noticias, colores y tamaños libres en el editor, patrones remotos, Openverse y el directorio de bloques.',
						'Polylang, WPForms Lite y WP Mail SMTP se actualizan solos, igual que las actualizaciones menores (de seguridad) de WordPress.',
						'Si se instala un plugin de SEO (Yoast, Rank Math, SEOPress, All in One SEO…), el SEO del tema se desactiva solo y desaparece el recuadro «Google y redes sociales» (lo escrito ahí no pasa al plugin). Si la web va detrás de Cloudflare u otro proxy, usa el filtro `mats_security_client_ip` para dar la IP real de las visitas; si no, el bloqueo de acceso trataría a todas como una.',
						'**Filtros** para ajustar el tema sin tocarlo: `mats_allowed_blocks`, `mats_admin_tiles`, `mats_guide_data`, `mats_admin_max_image_mb`, `mats_admin_disable_tags`, `mats_editor_lock_design`, `mats_editor_remove_patterns`, `mats_editor_restrict_unfiltered_html`, `mats_admin_settings_slug`.',
						'**Recomendado**: WP Mail SMTP conectado a una cuenta de correo, copias de seguridad automáticas en Hostinger y los plugins al día.',
					),
				),
			),
		),
	),
	'footer'   => 'Guía de la web de MatSurfer (ICMAB-CSIC). Si algo de esta guía no coincide con lo que ves en pantalla, avisa al administrador de la web: [{admin_email}](mailto:{admin_email}).',
);
