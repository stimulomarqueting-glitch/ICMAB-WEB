<?php
/**
 * Admin screens for the structured content of inc/content.php:
 * Aplicaciones, Equipo, Publicaciones and Valoraciones (mats_application,
 * mats_team, mats_publication, mats_expert).
 *
 * Each record holds BOTH languages (Polylang does not translate these types,
 * see inc/content.php), so the edit screen shows English and Spanish side
 * by side.
 *
 * EDIT SCREEN (classic editor: these types are not in the block editor)
 *   - the title field gets a visible label per type ("Nombre y apellidos",
 *     "Nombre interno (solo para el panel)", "Título del artículo",
 *     "Nombre interno") and a help line; an empty internal name of an
 *     application / expert evaluation is filled from its English texts
 *   - ONE box, "Contenido", built from mats_cpt_fields(): translatable
 *     fields in two columns (EN | ES), language-neutral fields full width,
 *     each with the right input (long texts with a character guide, web
 *     addresses, year, year-month, photo picker with preview, the
 *     application's link identifier — locked once published unless
 *     "Cambiar" is pressed)
 *   - "Orden en la web" box (menu_order) instead of "Atributos"; the
 *     "Publicar" box explains Publicar / Borrador; the featured image box
 *     is "Retrato" / "Figura" / "Foto" with size advice
 *   - a warning while a text exists in one language only (the Spanish site
 *     falls back to English) or an important field is empty
 *   - saving: nonce + edit_post capability + sanitising per kind of field;
 *     a field that became translatable (the expert's name) shows its old
 *     language-neutral value in the boxes until it is saved per language
 *   - any change visible on the website (also ▲▼) empties the page cache
 *     (LiteSpeed Cache on Hostinger), since these records show on pages
 *     the cache plugin does not relate to them
 *
 * LIST SCREEN
 *   - columns: photo, key data, both-languages indicator ✓/!, order
 *     (sortable), visible/hidden; no date column nor month filter
 *   - the list follows the website order (team and applications by
 *     "Orden"; publications newest year first; evaluations newest date
 *     first); team and applications get ▲▼ buttons (admin-post handler,
 *     nonce + capability)
 *   - "Ver en la web (EN / ES)" row links to the page where the item shows
 *   - no Polylang language filter in the admin bar
 *
 * Assets: assets/css/admin-fields.css, assets/js/admin-fields.js.
 *
 * Filter for the person installing the site:
 *   mats_cpta_config  per-type configuration (labels, help texts, kinds of
 *                     field, columns). Field KEYS come from mats_cpt_fields().
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

/* ═══════════════════════════════════════════════════════════════════════
 * Configuration
 * ═════════════════════════════════════════════════════════════════════ */

/** The four post types handled here. */
function mats_cpta_types(): array {
	return function_exists( 'mats_cpt_fields' ) ? array_keys( mats_cpt_fields() ) : array();
}

function mats_cpta_is_type( $type ): bool {
	return is_string( $type ) && in_array( $type, mats_cpta_types(), true );
}

/**
 * Per-type configuration. Field entries:
 *   kind      text | textarea | url | year | month | doi | slug | image
 *   label     visible label
 *   help      help text under the label
 *   max       recommended length (character guide, never enforced)
 *   rows      textarea height
 *   required  true → warn when empty (English for translatable fields)
 *   shown     false → not displayed by the current design (no language
 *             warnings for it)
 */
function mats_cpta_config( string $type = '' ): array {
	static $config = null;
	if ( null === $config ) {
		$config = array(
			'mats_application' => array(
				'item'              => 'aplicación',
				'page'              => 'applications',
				'page_name'         => 'Aplicaciones',
				'where'             => 'la página Aplicaciones y los accesos de la página de inicio',
				'title_label'       => 'Nombre interno (solo para el panel)',
				'title_help'        => 'Solo se ve aquí, en la lista del panel. En la web se muestra el «Título» de abajo, en cada idioma. Si lo dejas vacío, se rellena solo con el título en inglés.',
				'title_placeholder' => 'Por ejemplo: Organic electronics',
				'title_required'    => false,
				'list_title'        => 'Nombre interno',
				'thumb'             => array(),
				'sort'              => 'order',
				'arrows'            => true,
				'order_help'        => 'Decide la posición en la página Aplicaciones y en los accesos de la página de inicio: el 1 va primero, luego el 2… Si dos tienen el mismo número, va primero la más antigua. También puedes usar las flechas ▲ ▼ de la lista.',
				'intro'             => 'Escribe cada texto en inglés (columna EN) y en español (columna ES). Si dejas vacío un texto en español, la web en español mostrará el inglés. El identificador para enlaces se escribe una sola vez.',
				'list_intro'        => 'Cada aplicación es una ficha con los textos en inglés y en español. Solo las que están «Visibles» se ven en la web, en el mismo orden que esta lista: para cambiarlo, usa las flechas ▲ ▼ o el campo «Orden» de la ficha.',
				'fields'            => array(
					'slug'      => array(
						'kind'  => 'slug',
						'label' => 'Identificador para enlaces',
						'help'  => 'Una o pocas palabras en minúsculas, sin acentos ni espacios (por ejemplo, organic-electronics). Forma parte del enlace a esta aplicación (…#organic-electronics) y le dice al formulario de contacto qué aplicación estaba mirando la persona. Se rellena solo a partir del título en inglés.',
					),
					'tag'       => array(
						'kind'  => 'text',
						'label' => 'Etiqueta',
						'help'  => 'Texto corto que aparece encima del título, en letra pequeña (por ejemplo, «molecular layers · OLEDs»).',
						'max'   => 32,
					),
					'title'     => array(
						'kind'     => 'text',
						'label'    => 'Título',
						'help'     => 'El nombre de la aplicación tal como se ve en la web.',
						'max'      => 36,
						'required' => true,
					),
					'short'     => array(
						'kind'  => 'textarea',
						'label' => 'Resumen en una línea',
						'help'  => 'Una frase que resume la aplicación. Ahora mismo el diseño no la muestra en ninguna página, pero se guarda con la ficha por si se usa más adelante.',
						'max'   => 80,
						'rows'  => 2,
						'shown' => false,
					),
					'challenge' => array(
						'kind'     => 'textarea',
						'label'    => 'La necesidad',
						'help'     => 'Qué necesita o qué problema tiene el equipo de investigación. Aparece bajo el rótulo «La necesidad».',
						'max'      => 170,
						'rows'     => 3,
						'required' => true,
					),
					'use'       => array(
						'kind'     => 'textarea',
						'label'    => 'Con MatSurfer',
						'help'     => 'Qué le permite hacer o explorar MatSurfer. Aparece bajo el rótulo «Con MatSurfer».',
						'max'      => 180,
						'rows'     => 3,
						'required' => true,
					),
					'benefit'   => array(
						'kind'     => 'textarea',
						'label'    => 'Para tu equipo',
						'help'     => 'El beneficio para el equipo de I+D. Aparece bajo el rótulo «Para tu equipo».',
						'max'      => 110,
						'rows'     => 2,
						'required' => true,
					),
				),
			),
			'mats_team'        => array(
				'item'              => 'persona',
				'page'              => 'team',
				'page_name'         => 'Equipo',
				'where'             => 'la página Equipo',
				'title_label'       => 'Nombre y apellidos',
				'title_help'        => 'Tal como debe aparecer en la web. Es igual en los dos idiomas.',
				'title_placeholder' => 'Por ejemplo: Núria Aliaga-Alcalde',
				'title_required'    => true,
				'list_title'        => 'Nombre',
				'thumb'             => array(
					'label'  => 'Retrato',
					'set'    => 'Elegir retrato',
					'remove' => 'Quitar retrato',
					'use'    => 'Usar como retrato',
					'help'   => 'Foto cuadrada de la cara, con su fondo: JPG de 800 × 800 px y menos de 300 KB. Se ve pequeña y redonda en el selector de personas de la página Equipo. Si falta, se muestran las iniciales. La foto grande sin fondo se elige en «Contenido» → «Recorte sin fondo».',
				),
				'sort'              => 'order',
				'arrows'            => true,
				'order_help'        => 'Decide en qué lugar aparece esta persona en la página Equipo: el 1 va primero, luego el 2… Si dos tienen el mismo número, va primero la más antigua. También puedes usar las flechas ▲ ▼ de la lista.',
				'intro'             => 'Escribe cada texto en inglés (columna EN) y en español (columna ES). Si dejas vacío un texto en español, la web en español mostrará el inglés. Cada persona tiene dos fotos: el «Retrato» (recuadro de la derecha) y el «Recorte sin fondo» (al final de este recuadro).',
				'list_intro'        => 'Cada persona es una ficha con sus textos en inglés y en español y dos fotos: el retrato (redondo, pequeño) y el recorte sin fondo (la foto grande del escaparate). Solo las que están «Visibles» se ven en la página Equipo, en el mismo orden que esta lista: para cambiarlo, usa las flechas ▲ ▼ o el campo «Orden» de la ficha.',
				'fields'            => array(
					'role'           => array(
						'kind'     => 'text',
						'label'    => 'Cargo',
						'help'     => 'Su puesto o papel en el proyecto (por ejemplo, «Investigadora principal»).',
						'max'      => 50,
						'required' => true,
					),
					'affiliation'    => array(
						'kind'  => 'text',
						'label' => 'Institución',
						'help'  => 'Por ejemplo, ICMAB-CSIC.',
						'max'   => 40,
					),
					'bio'            => array(
						'kind'  => 'textarea',
						'label' => 'Biografía breve',
						'help'  => 'Una o dos frases. Se muestra en grande junto a su foto en el escaparate de la página Equipo.',
						'max'   => 160,
						'rows'  => 3,
					),
					'linkedin'       => array(
						'kind'        => 'url',
						'label'       => 'Perfil de LinkedIn',
						'help'        => 'Copia la dirección completa del perfil.',
						'placeholder' => 'https://www.linkedin.com/in/…',
					),
					'website'        => array(
						'kind'        => 'url',
						'label'       => 'Web personal',
						'help'        => 'Solo se muestra cuando la persona no tiene LinkedIn.',
						'placeholder' => 'https://…',
					),
					'showcase_image' => array(
						'kind'   => 'image',
						'label'  => 'Recorte sin fondo (foto grande del escaparate)',
						'help'   => 'La persona recortada, con el fondo transparente: un archivo WebP o PNG (un JPG siempre tiene fondo). Vertical o cuadrada, de unos 1200–1600 px de alto y menos de 400 KB. Es la foto grande de la página Equipo; si falta, se muestran sus iniciales.',
						'choose' => 'Elegir recorte',
						'change' => 'Cambiar recorte',
						'empty'  => 'Sin recorte: en el escaparate se verán las iniciales.',
					),
				),
			),
			'mats_publication' => array(
				'item'              => 'publicación',
				'page'              => 'research',
				'page_name'         => 'Casos de estudio',
				'where'             => 'la página Casos de estudio',
				'title_label'       => 'Título del artículo',
				'title_help'        => 'Tal como se publicó (normalmente en inglés). Se muestra igual en las dos webs y enlaza al artículo.',
				'title_placeholder' => 'Título completo del artículo',
				'title_required'    => true,
				'list_title'        => 'Título del artículo',
				'thumb'             => array(
					'label'  => 'Figura',
					'set'    => 'Elegir figura',
					'remove' => 'Quitar figura',
					'use'    => 'Usar como figura',
					'help'   => 'Opcional. Una figura o gráfico del artículo (JPG o PNG de unos 1200 px de ancho). Se guarda con la publicación; el diseño actual de Casos de estudio no la muestra.',
				),
				'sort'              => 'year',
				'arrows'            => false,
				'order_help'        => 'Las publicaciones se ordenan solas por año, de la más reciente a la más antigua. Este número solo decide el orden entre publicaciones del mismo año (el 1 va primero).',
				'intro'             => 'Los datos del artículo (autores, revista, año, DOI y enlace) son iguales en los dos idiomas y se escriben una sola vez. Solo la descripción tiene una versión en inglés (EN) y otra en español (ES).',
				'list_intro'        => 'Los artículos científicos que aparecen en la página Casos de estudio. Solo los «Visibles» se ven en la web; se ordenan solos, del año más reciente al más antiguo (esta lista sigue el mismo orden).',
				'fields'            => array(
					'authors'     => array(
						'kind'     => 'textarea',
						'label'    => 'Autores',
						'help'     => 'Tal como aparecen en el artículo, separados por comas.',
						'rows'     => 2,
						'required' => true,
					),
					'journal'     => array(
						'kind'     => 'text',
						'label'    => 'Revista',
						'help'     => 'Nombre de la revista (por ejemplo, ACS Applied Materials & Interfaces).',
						'required' => true,
					),
					'year'        => array(
						'kind'     => 'year',
						'label'    => 'Año de publicación',
						'help'     => 'Cuatro cifras, por ejemplo 2025. Ordena la lista: el más reciente primero.',
						'required' => true,
					),
					'doi'         => array(
						'kind'        => 'doi',
						'label'       => 'DOI',
						'help'        => 'Solo el código, por ejemplo 10.1021/acsami.4c19421. Si pegas el enlace completo (https://doi.org/…), se queda solo con el código.',
						'placeholder' => '10.xxxx/…',
					),
					'url'         => array(
						'kind'        => 'url',
						'label'       => 'Enlace al artículo',
						'help'        => 'La dirección donde se puede leer el artículo. Si la dejas vacía y has escrito el DOI, se enlazará a https://doi.org/ + el DOI.',
						'placeholder' => 'https://doi.org/…',
					),
					'description' => array(
						'kind'  => 'textarea',
						'label' => 'Descripción breve',
						'help'  => 'Una o dos frases que expliquen el resultado. Si la dejas vacía, no se muestra nada.',
						'max'   => 300,
						'rows'  => 3,
					),
				),
			),
			'mats_expert'      => array(
				'item'              => 'valoración',
				'page'              => 'research',
				'page_name'         => 'Casos de estudio',
				'where'             => 'la página Casos de estudio',
				'title_label'       => 'Nombre interno',
				'title_help'        => 'Solo se ve aquí, en la lista del panel, para reconocer la valoración. Si lo dejas vacío, se rellena solo con el campo científico y el nombre.',
				'title_placeholder' => 'Por ejemplo: Películas finas — Dra. García',
				'title_required'    => false,
				'list_title'        => 'Nombre interno',
				'thumb'             => array(
					'label'  => 'Foto',
					'set'    => 'Elegir foto',
					'remove' => 'Quitar foto',
					'use'    => 'Usar como foto',
					'help'   => 'Opcional. Foto de la persona (JPG cuadrada, 800 × 800 px). Se guarda con la valoración; el diseño actual de Casos de estudio no la muestra.',
				),
				'sort'              => 'date',
				'arrows'            => false,
				'order_help'        => 'Las valoraciones se ordenan solas por fecha, de la más reciente a la más antigua. Este número solo decide el orden entre valoraciones del mismo mes (el 1 va primero).',
				'intro'             => 'Escribe cada texto en inglés (columna EN) y en español (columna ES). Si dejas vacío un texto en español, la web en español mostrará el inglés. El nombre también va en las dos columnas, porque el tratamiento puede cambiar (por ejemplo, «Dr» en inglés y «Dr.» en español); si la columna ES se queda vacía, se usa el de inglés. La institución, el enlace y la fecha se escriben una sola vez.',
				'list_intro'        => 'Las citas de investigadores que han probado el dispositivo, en la página Casos de estudio. Solo las «Visibles» se ven en la web; se ordenan solas por fecha, de la más reciente a la más antigua (esta lista sigue el mismo orden).',
				'fields'            => array(
					'name'        => array(
						'kind'  => 'text',
						'label' => 'Nombre de la persona',
						'help'  => 'Con su tratamiento en cada idioma (por ejemplo, «Dr» en inglés y «Dr.» en español). Si dejas vacías las dos columnas, la cita se publica sin firma (por ejemplo, mientras la persona confirma cómo quiere aparecer). Sin nombre tampoco se muestran el cargo ni la institución.',
						'max'   => 40,
					),
					'role'        => array(
						'kind'  => 'text',
						'label' => 'Cargo',
						'help'  => 'Se muestra junto al nombre.',
						'max'   => 50,
					),
					'institution' => array(
						'kind'  => 'text',
						'label' => 'Institución',
						'help'  => 'Se muestra junto al nombre (por ejemplo, ICMAB-CSIC).',
						'max'   => 40,
					),
					'field'       => array(
						'kind'     => 'text',
						'label'    => 'Campo científico',
						'help'     => 'Etiqueta corta que aparece encima de la cita (por ejemplo, «MOF & COF thin films»).',
						'max'      => 32,
						'required' => true,
					),
					'quote'       => array(
						'kind'     => 'textarea',
						'label'    => 'Cita',
						'help'     => 'Lo que dijo la persona. Escríbela sin comillas: la web las añade.',
						'max'      => 650,
						'rows'     => 6,
						'required' => true,
					),
					'device'      => array(
						'kind'  => 'text',
						'label' => 'Dispositivo o modelo usado',
						'help'  => 'Se muestra debajo de la cita.',
						'max'   => 60,
					),
					'link'        => array(
						'kind'        => 'url',
						'label'       => 'Enlace «Saber más»',
						'help'        => 'Una página con más información (un artículo, una noticia…).',
						'placeholder' => 'https://…',
					),
					'date'        => array(
						'kind'     => 'month',
						'label'    => 'Fecha (año y mes)',
						'help'     => 'Cuándo se hizo la valoración, con el formato año-mes (por ejemplo, 2026-09). Ordena la lista: la más reciente primero.',
						'required' => true,
					),
				),
			),
		);
		$config = (array) apply_filters( 'mats_cpta_config', $config );
	}
	if ( '' === $type ) {
		return $config;
	}
	return $config[ $type ] ?? array();
}

/** Definition of one field (with defaults). */
function mats_cpta_field_def( string $type, string $field ): array {
	$def = mats_cpta_config( $type )['fields'][ $field ] ?? array();
	return array_merge(
		array(
			'kind'        => 'text',
			'label'       => ucfirst( str_replace( '_', ' ', $field ) ),
			'help'        => '',
			'max'         => 0,
			'rows'        => 3,
			'required'    => false,
			'shown'       => true,
			'placeholder' => '',
		),
		$def
	);
}

/** Field => is translatable, for a type. */
function mats_cpta_fields( string $type ): array {
	return mats_cpt_fields()[ $type ] ?? array();
}

/** Stored value (raw, no language fallback). */
function mats_cpta_value( int $post_id, string $field, string $lang = '' ): string {
	return (string) get_post_meta( $post_id, mats_meta_key( $field, $lang ), true );
}

/**
 * Stored values of a translatable field, [ lang => value ], as the boxes of
 * the form should show them. A record saved before the field had one box
 * per language (the expert's name) keeps its old language-neutral value,
 * which the website still shows (mats_field() falls back to it): it fills
 * the English box, and every box while no language has its own value.
 */
function mats_cpta_i18n_values( int $post_id, string $field ): array {
	$values = array();
	foreach ( mats_langs() as $lang ) {
		$values[ $lang ] = mats_cpta_value( $post_id, $field, $lang );
	}
	$legacy = mats_cpta_value( $post_id, $field );
	if ( '' === trim( $legacy ) || '' !== trim( (string) ( $values['en'] ?? '' ) ) ) {
		return $values;
	}
	$none = '' === trim( implode( '', $values ) );
	foreach ( array_keys( $values ) as $lang ) {
		if ( 'en' === $lang || $none ) {
			$values[ $lang ] = $legacy;
		}
	}
	return $values;
}

/** The current admin screen, when it is one of ours: [ base, post_type ]. */
function mats_cpta_screen(): array {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || ! mats_cpta_is_type( $screen->post_type ) || ! in_array( $screen->base, array( 'post', 'edit' ), true ) ) {
		return array();
	}
	return array( $screen->base, $screen->post_type );
}

/** Spanish name of a month number. */
function mats_cpta_month_name( int $month ): string {
	$names = array( 1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre' );
	return $names[ $month ] ?? '';
}

/** "2026-09" → "septiembre de 2026". */
function mats_cpta_format_month( string $value ): string {
	if ( ! preg_match( '/^(\d{4})-(\d{2})$/', $value, $m ) ) {
		return $value;
	}
	return mats_cpta_month_name( (int) $m[2] ) . ' de ' . $m[1];
}

/**
 * What is incomplete in a record:
 *   'es'       labels of shown texts written in English only
 *   'en'       labels of shown texts written in Spanish only
 *   'required' labels of important fields that are empty
 */
function mats_cpta_missing( WP_Post $post ): array {
	$type = $post->post_type;
	$out  = array(
		'es'       => array(),
		'en'       => array(),
		'required' => array(),
	);
	$cfg  = mats_cpta_config( $type );
	if ( ! empty( $cfg['title_required'] ) && '' === trim( (string) $post->post_title ) ) {
		$out['required'][] = $cfg['title_label'];
	}
	foreach ( mats_cpta_fields( $type ) as $field => $i18n ) {
		$def = mats_cpta_field_def( $type, $field );
		if ( $i18n ) {
			$values = mats_cpta_i18n_values( $post->ID, $field );
			$en     = trim( (string) ( $values['en'] ?? '' ) );
			$es     = trim( (string) ( $values['es'] ?? '' ) );
			if ( $def['shown']&& '' !== $en && '' === $es ) {
				$out['es'][] = $def['label'];
			} elseif ( $def['shown'] && '' === $en && '' !== $es ) {
				$out['en'][] = $def['label'];
			}
			if ( $def['required'] && '' === $en && '' === $es ) {
				$out['required'][] = $def['label'];
			}
		} elseif ( $def['required'] && '' === trim( mats_cpta_value( $post->ID, $field ) ) ) {
			$out['required'][] = $def['label'];
		}
	}
	return $out;
}

/** URL where a record shows on the site, '' when that page does not exist yet. */
function mats_cpta_view_url( WP_Post $post, string $lang ): string {
	$cfg  = mats_cpta_config( $post->post_type );
	$role = (string) ( $cfg['page'] ?? '' );
	if ( '' === $role || ! function_exists( 'mats_page_id' ) || ! mats_page_id( $role, $lang ) ) {
		return '';
	}
	$url = mats_page_url( $role, $lang );
	if ( 'mats_application' === $post->post_type ) {
		$slug = sanitize_title( mats_cpta_value( $post->ID, 'slug' ) );
		if ( '' !== $slug ) {
			$url .= '#' . $slug;
		}
	}
	return $url;
}

/** "Visible" / "Oculta · borrador"… for a post status. */
function mats_cpta_status_info( WP_Post $post ): array {
	switch ( $post->post_status ) {
		case 'publish':
			return array( 'is-visible', 'Visible', 'Se ve en la web.' );
		case 'future':
			return array( 'is-future', 'Programada', 'Se verá en la web el ' . get_the_date( 'd/m/Y', $post ) . '.' );
		case 'pending':
			return array( 'is-hidden', 'Oculta · pendiente', 'Pendiente de revisión: no se ve en la web.' );
		case 'private':
			return array( 'is-hidden', 'Oculta · privada', 'Privada: no se ve en la web.' );
		default:
			return array( 'is-hidden', 'Oculta · borrador', 'Borrador: no se ve en la web.' );
	}
}

/* ═══════════════════════════════════════════════════════════════════════
 * Labels: featured image box, saved messages, title placeholder
 * ═════════════════════════════════════════════════════════════════════ */

add_action(
	'init',
	static function () {
		foreach ( mats_cpta_types() as $type ) {
			add_filter(
				'post_type_labels_' . $type,
				static function ( $labels ) use ( $type ) {
					$thumb = mats_cpta_config( $type )['thumb'] ?? array();
					if ( $thumb ) {
						$labels->featured_image        = $thumb['label'];
						$labels->set_featured_image    = $thumb['set'];
						$labels->remove_featured_image = $thumb['remove'];
						$labels->use_featured_image    = $thumb['use'];
					}
					$labels->attributes = 'Orden en la web';
					return $labels;
				}
			);
		}
	},
	4 // before inc/content.php registers the types (priority 5)
);

add_filter(
	'enter_title_here',
	static function ( $text, $post ) {
		if ( $post instanceof WP_Post && mats_cpta_is_type( $post->post_type ) ) {
			return (string) ( mats_cpta_config( $post->post_type )['title_placeholder'] ?? $text );
		}
		return $text;
	},
	10,
	2
);

add_filter(
	'post_updated_messages',
	static function ( $messages ) {
		foreach ( mats_cpta_types() as $type ) {
			$post   = get_post();
			$future = $post instanceof WP_Post ? get_the_date( 'd/m/Y H:i', $post ) : '';
			$messages[ $type ] = array(
				0  => '',
				1  => 'Cambios guardados. Ya se ven en la web.',
				2  => 'Cambios guardados.',
				3  => 'Cambios guardados.',
				4  => 'Cambios guardados.',
				5  => 'Versión anterior recuperada.',
				6  => 'Publicada: ya se ve en la web.',
				7  => 'Guardada.',
				8  => 'Enviada para revisión: todavía no se ve en la web.',
				9  => 'Programada: se verá en la web el ' . $future . '.',
				10 => 'Borrador guardado: todavía no se ve en la web.',
			);
		}
		return $messages;
	}
);

add_filter(
	'bulk_post_updated_messages',
	static function ( $messages, $counts ) {
		$n = static function ( string $key ) use ( $counts ): int {
			return (int) ( $counts[ $key ] ?? 0 );
		};
		foreach ( mats_cpta_types() as $type ) {
			$messages[ $type ] = array(
				'updated'   => 1 === $n( 'updated' ) ? 'Se ha actualizado 1 ficha.' : 'Se han actualizado %s fichas.',
				'locked'    => 1 === $n( 'locked' ) ? '1 ficha no se ha cambiado: otra persona la está editando.' : '%s fichas no se han cambiado: otra persona las está editando.',
				'deleted'   => 1 === $n( 'deleted' ) ? 'Se ha borrado 1 ficha para siempre.' : 'Se han borrado %s fichas para siempre.',
				'trashed'   => 1 === $n( 'trashed' ) ? '1 ficha movida a la papelera.' : '%s fichas movidas a la papelera.',
				'untrashed' => 1 === $n( 'untrashed' ) ? '1 ficha recuperada de la papelera.' : '%s fichas recuperadas de la papelera.',
			);
		}
		return $messages;
	},
	10,
	2
);

/* ═══════════════════════════════════════════════════════════════════════
 * Edit screen
 * ═════════════════════════════════════════════════════════════════════ */

/* side box "Orden en la web" instead of "Atributos"; no "Slug" / custom fields */
add_action(
	'add_meta_boxes',
	static function ( $post_type ) {
		if ( ! mats_cpta_is_type( $post_type ) ) {
			return;
		}
		remove_meta_box( 'pageparentdiv', $post_type, 'side' );
		remove_meta_box( 'slugdiv', $post_type, 'normal' );
		remove_meta_box( 'postcustom', $post_type, 'normal' );
		add_meta_box( 'mats-cpta-order', 'Orden en la web', 'mats_cpta_render_order_box', $post_type, 'side', 'low' );
	},
	99
);

/* nothing to hide or rearrange there: no "Opciones de pantalla" for editors */
add_filter(
	'screen_options_show_screen',
	static function ( $show, $screen ) {
		if ( $screen instanceof WP_Screen && 'post' === $screen->base && mats_cpta_is_type( $screen->post_type ) && ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		return $show;
	},
	10,
	2
);

/* visible label above the title field */
add_action(
	'edit_form_top',
	static function ( $post ) {
		if ( ! $post instanceof WP_Post || ! mats_cpta_is_type( $post->post_type ) ) {
			return;
		}
		$cfg = mats_cpta_config( $post->post_type );
		printf(
			'<label class="mats-cpta-title-label" for="title">%s%s</label>',
			esc_html( (string) $cfg['title_label'] ),
			empty( $cfg['title_required'] ) ? ' <span class="mats-cpta-optional">(opcional)</span>' : ''
		);
	}
);

/* help under the title + where the record shows on the site */
add_action(
	'edit_form_before_permalink',
	static function ( $post ) {
		if ( ! $post instanceof WP_Post || ! mats_cpta_is_type( $post->post_type ) ) {
			return;
		}
		$cfg = mats_cpta_config( $post->post_type );
		echo '<p class="mats-cpta-title-help" id="mats-cpta-title-help">' . esc_html( (string) $cfg['title_help'] ) . '</p>';

		$where = (string) ( $cfg['where'] ?? '' );
		echo '<p class="mats-cpta-where"><span class="dashicons dashicons-visibility" aria-hidden="true"></span> ';
		if ( 'publish' === $post->post_status ) {
			$links = array();
			foreach ( array(
				'en' => 'en inglés',
				'es' => 'en español',
			) as $lang => $label ) {
				$url = mats_cpta_view_url( $post, $lang );
				if ( $url ) {
					$links[] = '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( 'Ver ' . $label ) . ' <span aria-hidden="true">↗</span><span class="screen-reader-text"> (se abre en una pestaña nueva)</span></a>';
				}
			}
			echo esc_html( 'Se ve en ' . $where . '.' );
			if ( $links ) {
				echo ' ' . implode( ' · ', $links ); // phpcs:ignore WordPress.Security.EscapeOutput -- built above with esc_url / esc_html
			} else {
				echo ' ' . esc_html( '(La página ' . $cfg['page_name'] . ' todavía no está creada.)' );
			}
		} elseif ( 'future' === $post->post_status ) {
			echo esc_html( 'Programada: aparecerá en ' . $where . ' el ' . get_the_date( 'd/m/Y', $post ) . '.' );
		} else {
			echo esc_html( 'Todavía no se ve en la web. Cuando la publiques, aparecerá en ' . $where . '.' );
		}
		echo '</p>';
	}
);

/*
 * The "Contenido" box sits right under the title, where the text editor of
 * a news article would be: on phones and tablets it comes before the side
 * boxes, and it cannot be collapsed, hidden or dragged away by mistake.
 * It looks like a box but its heading is not a ".hndle": WordPress's
 * postbox.js turns every ".postbox .hndle" into a hide/show toggle.
 */
add_action(
	'edit_form_after_title',
	static function ( $post ) {
		if ( ! $post instanceof WP_Post || ! mats_cpta_is_type( $post->post_type ) ) {
			return;
		}
		echo '<div id="mats-cpta-content" class="postbox mats-cpta-box">';
		echo '<div class="postbox-header"><h2 class="mats-cpta-box__title">Contenido</h2></div>';
		echo '<div class="inside">';
		mats_cpta_render_content_box( $post );
		echo '</div></div>';
	}
);

/* "Publicar" box: what Publicar and Borrador mean */
add_action(
	'post_submitbox_misc_actions',
	static function ( $post ) {
		if ( ! $post instanceof WP_Post || ! mats_cpta_is_type( $post->post_type ) ) {
			return;
		}
		$now = '';
		if ( 'auto-draft' !== $post->post_status ) {
			list( $class, $label ) = mats_cpta_status_info( $post );
			$now                   = '<span class="mats-cpta-pill ' . esc_attr( $class ) . '">' . esc_html( 'Ahora: ' . $label ) . '</span>';
		}
		echo '<div class="misc-pub-section mats-cpta-status-help">';
		echo $now; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above
		echo '<p><strong>Publicar</strong> (o <strong>Actualizar</strong>): la ficha se ve en la web.</p>';
		echo '<p><strong>Guardar borrador</strong>: se guarda, pero no se ve. Para quitar de la web una ficha publicada sin perderla, cambia su «Estado» a Borrador y pulsa Actualizar.</p>';
		echo '</div>';
	}
);

/* featured image box: what the photo is for */
add_filter(
	'admin_post_thumbnail_html',
	static function ( $html, $post_id ) {
		$type  = get_post_type( (int) $post_id );
		$thumb = mats_cpta_is_type( $type ) ? ( mats_cpta_config( $type )['thumb'] ?? array() ) : array();
		if ( empty( $thumb['help'] ) ) {
			return $html;
		}
		return $html . '<p class="mats-cpta-thumb-help">' . esc_html( (string) $thumb['help'] ) . '</p>';
	},
	10,
	2
);

/** The "Orden en la web" box (menu_order: saved by WordPress itself). */
function mats_cpta_render_order_box( WP_Post $post ): void {
	$cfg   = mats_cpta_config( $post->post_type );
	$order = (int) $post->menu_order;
	if ( 'auto-draft' === $post->post_status && 0 === $order && 'order' === ( $cfg['sort'] ?? '' ) ) {
		/* a new item goes after the existing ones */
		global $wpdb;
		$max   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(menu_order) FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ('trash','auto-draft')", $post->post_type ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$order = $max + 1;
	}
	?>
	<div class="mats-cpta-order">
		<label for="mats-cpta-menu-order" class="mats-cpta-order__label">Posición</label>
		<input type="number" id="mats-cpta-menu-order" name="menu_order" value="<?php echo esc_attr( (string) $order ); ?>" min="0" max="9999" step="1" inputmode="numeric" aria-describedby="mats-cpta-order-help" />
		<p class="mats-cpta-order__help" id="mats-cpta-order-help"><?php echo esc_html( (string) ( $cfg['order_help'] ?? '' ) ); ?></p>
	</div>
	<?php
}

/** The "Contenido" box. */
function mats_cpta_render_content_box( WP_Post $post ): void {
	$type   = $post->post_type;
	$cfg    = mats_cpta_config( $type );
	$fields = mats_cpta_fields( $type );
	$has_i18n = in_array( true, $fields, true );

	wp_nonce_field( 'mats_cpta_save_' . $post->ID, 'mats_cpta_nonce', false );

	echo '<div class="mats-cpta" data-type="' . esc_attr( $type ) . '">';
	if ( ! empty( $cfg['intro'] ) ) {
		echo '<p class="mats-cpta__intro">' . esc_html( (string) $cfg['intro'] ) . '</p>';
	}
	$header = $has_i18n;
	foreach ( $fields as $field => $i18n ) {
		if ( $i18n && $header ) {
			/* column titles, sticky while scrolling, above the first bilingual text */
			echo '<div class="mats-cpta__langs" aria-hidden="true"><span class="mats-cpta__langs-col"><span class="mats-cpta__pill">EN</span> Web en inglés</span><span class="mats-cpta__langs-col"><span class="mats-cpta__pill">ES</span> Web en español</span></div>';
			$header = false;
		}
		mats_cpta_render_row( $post, $field, (bool) $i18n );
	}
	echo '</div>';
}

/** One row of the "Contenido" box. */
function mats_cpta_render_row( WP_Post $post, string $field, bool $i18n ): void {
	$type   = $post->post_type;
	$def    = mats_cpta_field_def( $type, $field );
	$base   = 'mats-cpta-' . sanitize_html_class( $field );
	$help   = '' !== $def['help'] ? $base . '-help' : '';
	$classes = 'mats-cpta__row mats-cpta__row--' . sanitize_html_class( $def['kind'] ) . ( $i18n ? ' mats-cpta__row--i18n' : '' );

	echo '<div class="' . esc_attr( $classes ) . '" data-field="' . esc_attr( $field ) . '">';

	$optional = $def['required'] || 'slug' === $def['kind'] ? '' : ' <span class="mats-cpta-optional">(opcional)</span>';
	if ( $i18n || 'image' === $def['kind'] ) {
		echo '<div class="mats-cpta__label" id="' . esc_attr( $base . '-label' ) . '">' . esc_html( $def['label'] ) . $optional . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- $optional is fixed markup
	} else {
		echo '<label class="mats-cpta__label" id="' . esc_attr( $base . '-label' ) . '" for="' . esc_attr( $base ) . '">' . esc_html( $def['label'] ) . $optional . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput -- $optional is fixed markup
	}
	if ( $help ) {
		echo '<p class="mats-cpta__help" id="' . esc_attr( $help ) . '">' . esc_html( $def['help'] ) . '</p>';
	}

	if ( $i18n ) {
		$values = mats_cpta_i18n_values( $post->ID, $field );
		$en     = (string) ( $values['en'] ?? '' );
		$es     = (string) ( $values['es'] ?? '' );
		echo '<div class="mats-cpta__cols">';
		foreach ( array(
			'en' => array( 'Inglés', $en, '' ),
			'es' => array( 'Español', $es, '' !== trim( $en ) && '' === trim( $es ) && $def['shown'] ? 'es' : '' ),
		) as $lang => $col ) {
			list( $lang_name, $value, $missing ) = $col;
			$id       = $base . '-' . $lang;
			$col_cls  = 'mats-cpta__col' . ( $missing ? ' is-missing' : '' );
			$describe = trim( $help . ( $def['max'] ? ' ' . $id . '-count' : '' ) . ( $missing ? ' ' . $id . '-missing' : '' ) );
			echo '<div class="' . esc_attr( $col_cls ) . '" data-lang="' . esc_attr( $lang ) . '">';
			echo '<label class="mats-cpta__lang" id="' . esc_attr( $id . '-lang' ) . '" for="' . esc_attr( $id ) . '"><span class="mats-cpta__pill">' . esc_html( strtoupper( $lang ) ) . '</span> ' . esc_html( $lang_name ) . '<span class="screen-reader-text"> — ' . esc_html( $def['label'] ) . '</span></label>';
			mats_cpta_render_input( $def, $field . '_' . $lang, $id, $value, $describe, $lang );
			echo '<p class="mats-cpta__missing" id="' . esc_attr( $id . '-missing' ) . '"' . ( 'es' === $lang && $missing ? '' : ' hidden' ) . '>' . esc_html( 'es' === $lang ? 'Sin traducir: en la web en español se verá el texto en inglés.' : 'Falta el inglés: en la web en inglés este texto se verá vacío.' ) . '</p>';
			echo '</div>';
		}
		echo '</div>';
	} else {
		$value = mats_cpta_value( $post->ID, $field );
		switch ( $def['kind'] ) {
			case 'slug':
				mats_cpta_render_slug( $post, $def, $base, $value, $help );
				break;
			case 'image':
				mats_cpta_render_media( $def, $field, $base, $value, $help );
				break;
			default:
				mats_cpta_render_input( $def, $field, $base, $value, trim( $help . ( $def['max'] ? ' ' . $base . '-count' : '' ) ), '' );
		}
	}
	echo '</div>';
}

/** A text / textarea / url / year / month / doi input. */
function mats_cpta_render_input( array $def, string $name, string $id, string $value, string $describe, string $lang ): void {
	$attrs = array(
		'id'               => $id,
		'name'             => 'mats_cpta[' . $name . ']',
		'class'            => 'mats-cpta__input',
		'aria-describedby' => $describe,
		'data-lang'        => $lang,
		'lang'             => $lang,
		'data-kind'        => $def['kind'],
		'placeholder'      => $def['placeholder'],
		'data-mats-max'    => $def['max'] ? (string) (int) $def['max'] : '',
	);
	switch ( $def['kind'] ) {
		case 'textarea':
			break;
		case 'url':
			$attrs['type']         = 'url';
			$attrs['inputmode']    = 'url';
			$attrs['autocomplete'] = 'off';
			$attrs['spellcheck']   = 'false';
			break;
		case 'year':
			$attrs['type']        = 'number';
			$attrs['min']         = '1900'; // same limits as mats_cpta_sanitize()
			$attrs['max']         = (string) ( (int) gmdate( 'Y' ) + 1 );
			$attrs['step']        = '1';
			$attrs['inputmode']   = 'numeric';
			$attrs['class']      .= ' mats-cpta__input--short';
			$attrs['placeholder'] = (string) gmdate( 'Y' );
			break;
		case 'month':
			/*
			 * Browsers without a month picker (desktop Firefox and Safari) show
			 * a plain text box: the pattern then accepts what the server
			 * accepts (2026-09, 2026-9, 09/2026…) and the title explains it.
			 */
			$attrs['type']        = 'month';
			$attrs['pattern']     = '\s*([0-9]{4}[\-\/.][0-9]{1,2}|[0-9]{1,2}[\-\/.][0-9]{4})\s*';
			$attrs['title']       = 'Año y mes, por ejemplo 2026-09';
			$attrs['placeholder'] = 'AAAA-MM';
			$attrs['class']      .= ' mats-cpta__input--short';
			break;
		case 'doi':
			$attrs['type']         = 'text';
			$attrs['spellcheck']   = 'false';
			$attrs['autocomplete'] = 'off';
			$attrs['class']       .= ' mats-cpta__input--mono';
			break;
		default:
			$attrs['type'] = 'text';
	}

	$html = '';
	foreach ( $attrs as $attr => $attr_value ) {
		if ( '' !== (string) $attr_value ) {
			$html .= ' ' . $attr . '="' . esc_attr( (string) $attr_value ) . '"';
		}
	}
	if ( 'textarea' === $def['kind'] ) {
		echo '<textarea' . $html . ' rows="' . (int) $def['rows'] . '">' . esc_textarea( $value ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput -- attributes escaped above
	} else {
		echo '<input' . $html . ' value="' . esc_attr( $value ) . '" />'; // phpcs:ignore WordPress.Security.EscapeOutput -- attributes escaped above
	}

	if ( $def['max'] ) {
		$len  = mb_strlen( $value );
		$over = $len > (int) $def['max'];
		printf(
			'<p class="mats-cpta__count%1$s" id="%2$s"><span class="mats-cpta__count-n">%3$d</span> / %4$d caracteres recomendados<span class="mats-cpta__count-over"%5$s> · un poco largo: puede no caber bien en el diseño</span></p>',
			$over ? ' is-over' : '',
			esc_attr( $id . '-count' ),
			(int) $len,
			(int) $def['max'],
			$over ? '' : ' hidden'
		);
	}
}

/** Is the application identifier locked (published before)? */
function mats_cpta_slug_locked( WP_Post $post ): bool {
	if ( '' === mats_cpta_value( $post->ID, 'slug' ) ) {
		return false;
	}
	return in_array( $post->post_status, array( 'publish', 'future', 'private' ), true ) || '' !== (string) get_post_meta( $post->ID, '_mats_cpta_published', true );
}

/** The application's identifier ("slug") field. */
function mats_cpta_render_slug( WP_Post $post, array $def, string $id, string $value, string $help ): void {
	$locked = mats_cpta_slug_locked( $post );
	$prefix = '';
	if ( function_exists( 'mats_page_id' ) && mats_page_id( 'applications', 'en' ) ) {
		$prefix = (string) wp_parse_url( mats_page_url( 'applications', 'en' ), PHP_URL_PATH );
	}
	$prefix = ( '' !== $prefix ? $prefix : '…/' ) . '#';
	?>
	<div class="mats-cpta__slug<?php echo $locked ? ' is-locked' : ''; ?>" data-mats-slug data-auto="<?php echo '' === $value ? '1' : '0'; ?>">
		<span class="mats-cpta__slug-prefix" aria-hidden="true"><?php echo esc_html( $prefix ); ?></span>
		<input type="text" class="mats-cpta__input mats-cpta__input--mono" id="<?php echo esc_attr( $id ); ?>" name="mats_cpta[slug]" value="<?php echo esc_attr( $value ); ?>" spellcheck="false" autocomplete="off" aria-describedby="<?php echo esc_attr( trim( $help . ' ' . $id . '-lock' ) ); ?>"<?php echo $locked ? ' readonly' : ''; ?> />
		<input type="hidden" name="mats_cpta_slug_open" value="<?php echo $locked ? '0' : '1'; ?>" data-mats-slug-open />
		<?php if ( $locked ) : ?>
			<button type="button" class="button mats-cpta__slug-change" data-mats-slug-change aria-controls="<?php echo esc_attr( $id ); ?>">Cambiar</button>
		<?php endif; ?>
	</div>
	<?php if ( $locked ) : ?>
		<p class="mats-cpta__slug-lock" id="<?php echo esc_attr( $id . '-lock' ); ?>" data-mats-slug-note>
			<span class="dashicons dashicons-lock" aria-hidden="true"></span>
			Está bloqueado porque la aplicación ya se ha publicado: si lo cambias, los enlaces que ya se hayan compartido dejarán de llevar a esta ficha. Pulsa «Cambiar» solo si es imprescindible.
		</p>
		<p class="mats-cpta__slug-warn" data-mats-slug-warn hidden>
			<span class="dashicons dashicons-warning" aria-hidden="true"></span>
			Ahora puedes cambiarlo. Recuerda: los enlaces antiguos a esta aplicación (…#<?php echo esc_html( $value ); ?>) dejarán de funcionar. Se guarda al pulsar «Actualizar».
		</p>
	<?php endif; ?>
	<?php
}

/** A photo picker (attachment ID) with preview. */
function mats_cpta_render_media( array $def, string $field, string $id, string $value, string $help ): void {
	$att_id = absint( $value );
	$img    = $att_id && wp_attachment_is_image( $att_id ) ? wp_get_attachment_image( $att_id, 'medium', false, array( 'alt' => '', 'loading' => false ) ) : '';
	$mime   = $img ? (string) get_post_mime_type( $att_id ) : '';
	$is_cut = in_array( $mime, array( 'image/webp', 'image/png', 'image/avif', 'image/gif' ), true );
	$file   = $img ? wp_basename( (string) get_attached_file( $att_id ) ) : '';
	?>
	<div class="mats-cpta__media" data-mats-media data-choose="<?php echo esc_attr( (string) ( $def['choose'] ?? 'Elegir foto' ) ); ?>" data-change="<?php echo esc_attr( (string) ( $def['change'] ?? 'Cambiar foto' ) ); ?>">
		<div class="mats-cpta__media-preview" data-mats-media-preview>
			<?php if ( $img ) : ?>
				<?php echo $img; // phpcs:ignore WordPress.Security.EscapeOutput -- wp_get_attachment_image() ?>
			<?php else : ?>
				<span class="mats-cpta__media-empty"><?php echo esc_html( (string) ( $def['empty'] ?? 'Sin foto.' ) ); ?></span>
			<?php endif; ?>
		</div>
		<div class="mats-cpta__media-side">
			<p class="mats-cpta__media-file" data-mats-media-file<?php echo $file ? '' : ' hidden'; ?>><?php echo esc_html( $file ); ?></p>
			<p class="mats-cpta__media-actions">
				<button type="button" class="button" data-mats-media-choose aria-describedby="<?php echo esc_attr( $help ); ?>"><?php echo esc_html( $img ? (string) ( $def['change'] ?? 'Cambiar foto' ) : (string) ( $def['choose'] ?? 'Elegir foto' ) ); ?></button>
				<button type="button" class="button-link mats-cpta__media-remove" data-mats-media-remove<?php echo $img ? '' : ' hidden'; ?>>Quitar</button>
			</p>
			<p class="mats-cpta__media-warn" data-mats-media-warn<?php echo ( $img && ! $is_cut ) ? '' : ' hidden'; ?>>
				<span class="dashicons dashicons-warning" aria-hidden="true"></span>
				<span data-mats-media-warn-text>Este archivo es un JPG: tiene fondo, así que no quedará recortado en el escaparate. Usa un WebP o PNG con el fondo transparente.</span>
			</p>
		</div>
		<input type="hidden" id="<?php echo esc_attr( $id ); ?>" name="mats_cpta[<?php echo esc_attr( $field ); ?>]" value="<?php echo $img ? esc_attr( (string) $att_id ) : ''; ?>" data-mats-media-input />
	</div>
	<?php
}

/* ═══════════════════════════════════════════════════════════════════════
 * Saving
 * ═════════════════════════════════════════════════════════════════════ */

/** Does this request carry a valid "Contenido" form for the post? */
function mats_cpta_verify_request( int $post_id ): bool {
	if ( ! $post_id || ! isset( $_POST['mats_cpta_nonce'] ) ) {
		return false;
	}
	return (bool) wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mats_cpta_nonce'] ) ), 'mats_cpta_save_' . $post_id );
}

/** The submitted fields (unslashed, still raw). */
function mats_cpta_posted(): array {
	// phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput -- verified by mats_cpta_verify_request(); each value is sanitised by mats_cpta_sanitize()
	$raw = isset( $_POST['mats_cpta'] ) && is_array( $_POST['mats_cpta'] ) ? wp_unslash( $_POST['mats_cpta'] ) : array();
	return is_array( $raw ) ? $raw : array();
}

/**
 * Sanitises one submitted value by kind of field.
 * Returns the clean string ('' = empty) or null when the value is not
 * valid (the stored value is then kept and the editor is told).
 */
function mats_cpta_sanitize( string $kind, $raw ) {
	if ( ! is_scalar( $raw ) ) {
		return null;
	}
	$raw = (string) $raw;
	switch ( $kind ) {
		case 'textarea':
			return trim( sanitize_textarea_field( $raw ) );

		case 'url':
			$raw = trim( $raw );
			if ( '' === $raw ) {
				return '';
			}
			if ( ! preg_match( '#^[a-z][a-z0-9+.\-]*:#i', $raw ) ) {
				$raw = 'https://' . ltrim( $raw, '/' ); // "www.linkedin.com/in/…"
			}
			$url  = esc_url_raw( $raw, array( 'http', 'https' ) );
			$host = '' !== $url ? (string) wp_parse_url( $url, PHP_URL_HOST ) : '';
			return ( '' !== $host && ( false !== strpos( $host, '.' ) || 'localhost' === $host ) ) ? $url : null;

		case 'year':
			$raw = trim( $raw );
			if ( '' === $raw ) {
				return '';
			}
			if ( ! preg_match( '/^\d{4}$/', $raw ) ) {
				return null;
			}
			$year = (int) $raw;
			return ( $year >= 1900 && $year <= (int) gmdate( 'Y' ) + 1 ) ? (string) $year : null;

		case 'month':
			$raw = trim( $raw );
			if ( '' === $raw ) {
				return '';
			}
			if ( preg_match( '/^(\d{4})[-\/.](\d{1,2})$/', $raw, $m ) ) {
				list( , $year, $month ) = $m;
			} elseif ( preg_match( '/^(\d{1,2})[-\/.](\d{4})$/', $raw, $m ) ) {
				list( , $month, $year ) = $m;
			} else {
				return null;
			}
			$month = (int) $month;
			$value = sprintf( '%04d-%02d', (int) $year, $month );
			return ( $month >= 1 && $month <= 12 && preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $value ) ) ? $value : null;

		case 'image':
			$id = absint( $raw );
			if ( ! $id ) {
				return '';
			}
			return wp_attachment_is_image( $id ) ? (string) $id : null;

		case 'slug':
			return sanitize_title( $raw );

		case 'doi':
			$doi = trim( sanitize_text_field( $raw ) );
			$doi = (string) preg_replace( '#^(https?://)?(dx\.)?doi\.org/#i', '', $doi );
			$doi = (string) preg_replace( '/^doi:\s*/i', '', $doi );
			return trim( $doi );

		default:
			return trim( sanitize_text_field( $raw ) );
	}
}

/** Unique application identifier (another application may use it). */
function mats_cpta_unique_slug( string $slug, int $post_id ): string {
	$slug = '' !== $slug ? $slug : 'aplicacion-' . $post_id;
	$base = $slug;
	$n    = 2;
	while ( true ) {
		$taken = get_posts(
			array(
				'post_type'        => 'mats_application',
				'post_status'      => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'post__not_in'     => array( $post_id ),
				'meta_key'         => mats_meta_key( 'slug' ), // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'       => $slug, // phpcs:ignore WordPress.DB.SlowDBQuery
				'suppress_filters' => true,
				'no_found_rows'    => true,
			)
		);
		if ( ! $taken ) {
			return $slug;
		}
		$slug = $base . '-' . $n;
		++$n;
	}
}

/** Remembers a message for the next screen of this user. */
function mats_cpta_add_note( int $post_id, string $note ): void {
	$key   = 'mats_cpta_notes_' . get_current_user_id();
	$notes = get_transient( $key );
	$notes = is_array( $notes ) ? $notes : array();
	$notes[ $post_id ][] = $note;
	set_transient( $key, $notes, 5 * MINUTE_IN_SECONDS );
}

/** Saves the "Contenido" box. */
function mats_cpta_save( int $post_id, WP_Post $post ): void {
	if ( ! mats_cpta_is_type( $post->post_type ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( ! mats_cpta_verify_request( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$type  = $post->post_type;
	$input = mats_cpta_posted();

	foreach ( mats_cpta_fields( $type ) as $field => $i18n ) {
		$def = mats_cpta_field_def( $type, $field );
		if ( 'slug' === $def['kind'] ) {
			continue; // below
		}
		$names = $i18n ? array_combine( mats_langs(), array_map( static fn( $l ) => $field . '_' . $l, mats_langs() ) ) : array( '' => $field );
		$saved = 0;
		foreach ( $names as $lang => $name ) {
			if ( ! array_key_exists( $name, $input ) ) {
				continue; // not in the form: untouched
			}
			$clean = mats_cpta_sanitize( $def['kind'], $input[ $name ] );
			if ( null === $clean ) {
				$what = $def['label'] . ( $lang ? ' (' . strtoupper( $lang ) . ')' : '' );
				switch ( $def['kind'] ) {
					case 'url':
						$why = 'no parece una dirección web (debe empezar por https://)';
						break;
					case 'year':
						$why = 'debe ser un año de cuatro cifras';
						break;
					case 'month':
						$why = 'debe tener el formato año-mes, por ejemplo 2026-09';
						break;
					case 'image':
						$why = 'el archivo elegido no es una imagen';
						break;
					default:
						$why = 'no es válido';
				}
				mats_cpta_add_note( $post_id, '«' . $what . '»: ' . $why . '. Se ha mantenido el valor anterior.' );
				continue;
			}
			update_post_meta( $post_id, mats_meta_key( $field, (string) $lang ), wp_slash( $clean ) );
			++$saved;
		}
		/*
		 * A translatable field saved in every language replaces its old
		 * language-neutral value (the expert's name of older records, shown in
		 * the boxes by mats_cpta_i18n_values()); otherwise the website would
		 * keep showing it when both boxes are emptied.
		 */
		if ( $i18n && count( $names ) === $saved && metadata_exists( 'post', $post_id, mats_meta_key( $field ) ) ) {
			delete_post_meta( $post_id, mats_meta_key( $field ) );
		}
	}

	/* publications: the DOI gives the link when none was written */
	if ( 'mats_publication' === $type && '' === mats_cpta_value( $post_id, 'url' ) && '' !== mats_cpta_value( $post_id, 'doi' ) ) {
		$url = esc_url_raw( 'https://doi.org/' . ltrim( mats_cpta_value( $post_id, 'doi' ), '/' ) );
		if ( $url ) {
			update_post_meta( $post_id, mats_meta_key( 'url' ), wp_slash( $url ) );
		}
	}

	/* applications: the link identifier (locked once published) */
	if ( 'mats_application' === $type ) {
		$stored = mats_cpta_value( $post_id, 'slug' );
		$open   = isset( $input['slug'] ) && isset( $_POST['mats_cpta_slug_open'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['mats_cpta_slug_open'] ) ); // phpcs:ignore WordPress.Security.NonceVerification -- verified above
		$slug   = $open ? (string) mats_cpta_sanitize( 'slug', $input['slug'] ) : $stored;
		if ( '' === $slug ) {
			$from = mats_cpta_value( $post_id, 'title', 'en' );
			$slug = sanitize_title( '' !== $from ? $from : (string) $post->post_title );
		}
		$unique = mats_cpta_unique_slug( $slug, $post_id );
		if ( $open && '' !== $slug && $unique !== $slug ) {
			mats_cpta_add_note( $post_id, 'El identificador «' . $slug . '» ya lo usa otra aplicación, así que se ha guardado como «' . $unique . '».' );
		}
		if ( $unique !== $stored ) {
			update_post_meta( $post_id, mats_meta_key( 'slug' ), wp_slash( $unique ) );
		}
	}
}
add_action( 'save_post', 'mats_cpta_save', 10, 2 );

/* identifiers lock after the first publication, even if it goes back to draft */
add_action(
	'transition_post_status',
	static function ( $new_status, $old_status, $post ) {
		if ( 'publish' === $new_status && $post instanceof WP_Post && 'mats_application' === $post->post_type ) {
			update_post_meta( $post->ID, '_mats_cpta_published', '1' );
		}
	},
	10,
	3
);

/**
 * Empties the page cache (LiteSpeed Cache on Hostinger…) once per request.
 * These records show on ordinary pages (Equipo, Aplicaciones, Casos de
 * estudio, the home page) that a cache plugin does not relate to them, so
 * without this the website could keep the old version after a change.
 * Does nothing when no such plugin is active.
 */
function mats_cpta_purge_page_cache(): void {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	do_action( 'litespeed_purge_all' );
}

/* a visible record changed: published, updated (also Quick Edit), hidden or trashed */
add_action(
	'transition_post_status',
	static function ( $new_status, $old_status, $post ) {
		if ( $post instanceof WP_Post && mats_cpta_is_type( $post->post_type ) && ( 'publish' === $new_status || 'publish' === $old_status ) ) {
			mats_cpta_purge_page_cache();
		}
	},
	10,
	3
);

/** The photo box saves on its own (no "Actualizar"): portrait / figure of a visible record. */
function mats_cpta_thumbnail_changed( $meta_id, $post_id, $meta_key ): void {
	if ( '_thumbnail_id' === $meta_key && 'publish' === get_post_status( (int) $post_id ) && mats_cpta_is_type( get_post_type( (int) $post_id ) ) ) {
		mats_cpta_purge_page_cache();
	}
}
add_action( 'added_post_meta', 'mats_cpta_thumbnail_changed', 10, 3 );
add_action( 'updated_post_meta', 'mats_cpta_thumbnail_changed', 10, 3 );
add_action( 'deleted_post_meta', 'mats_cpta_thumbnail_changed', 10, 3 );

/*
 * Before the post is written: an empty internal name is filled from the
 * texts (applications, evaluations) and no record is ever password
 * protected (the website would show it anyway).
 */
add_filter(
	'wp_insert_post_data',
	static function ( $data, $postarr ) {
		$type = (string) ( $data['post_type'] ?? '' );
		if ( ! mats_cpta_is_type( $type ) ) {
			return $data;
		}
		$data['post_password'] = '';
		$post_id               = (int) ( $postarr['ID'] ?? 0 );
		if ( '' !== trim( (string) ( $data['post_title'] ?? '' ) ) || ! in_array( $type, array( 'mats_application', 'mats_expert' ), true ) || ! mats_cpta_verify_request( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return $data;
		}
		$input = mats_cpta_posted();
		if ( 'mats_application' === $type ) {
			$title = (string) mats_cpta_sanitize( 'text', $input['title_en'] ?? '' );
			$title = '' !== $title ? $title : (string) mats_cpta_sanitize( 'text', $input['title_es'] ?? '' );
		} else {
			$field = (string) mats_cpta_sanitize( 'text', $input['field_en'] ?? '' );
			$field = '' !== $field ? $field : (string) mats_cpta_sanitize( 'text', $input['field_es'] ?? '' );
			$name  = (string) mats_cpta_sanitize( 'text', $input['name_en'] ?? '' );
			$name  = '' !== $name ? $name : (string) mats_cpta_sanitize( 'text', $input['name_es'] ?? '' );
			$title = trim( $field . ( '' !== $field && '' !== $name ? ' — ' : '' ) . $name );
		}
		if ( '' !== $title ) {
			$data['post_title'] = wp_slash( $title );
		}
		return $data;
	},
	10,
	2
);

/* ═══════════════════════════════════════════════════════════════════════
 * Notices on the edit screen
 * ═════════════════════════════════════════════════════════════════════ */

add_action(
	'admin_notices',
	static function () {
		$screen = mats_cpta_screen();
		if ( ! $screen || 'post' !== $screen[0] ) {
			return;
		}
		$post = get_post();
		if ( ! $post instanceof WP_Post || 'auto-draft' === $post->post_status ) {
			return;
		}

		/* messages from the last save */
		$key   = 'mats_cpta_notes_' . get_current_user_id();
		$notes = get_transient( $key );
		if ( is_array( $notes ) && ! empty( $notes[ $post->ID ] ) ) {
			echo '<div class="notice notice-error mats-cpta-notice"><p><strong>Revisa estos datos:</strong></p><ul>';
			foreach ( (array) $notes[ $post->ID ] as $note ) {
				echo '<li>' . esc_html( (string) $note ) . '</li>';
			}
			echo '</ul></div>';
			unset( $notes[ $post->ID ] );
			if ( $notes ) {
				set_transient( $key, $notes, 5 * MINUTE_IN_SECONDS );
			} else {
				delete_transient( $key );
			}
		}

		$missing = mats_cpta_missing( $post );
		$quote   = static fn( array $labels ): string => '«' . implode( '», «', $labels ) . '»';
		if ( $missing['es'] ) {
			echo '<div class="notice notice-warning mats-cpta-notice" data-mats-missing="es"><p><strong>Falta la traducción al español</strong> en ' . esc_html( $quote( $missing['es'] ) ) . '. Mientras esté vacía, la web en español mostrará el texto en inglés. Rellénala en la columna <strong>ES</strong> del recuadro «Contenido».</p></div>';
		}
		if ( $missing['en'] ) {
			echo '<div class="notice notice-warning mats-cpta-notice" data-mats-missing="en"><p><strong>Falta el texto en inglés</strong> en ' . esc_html( $quote( $missing['en'] ) ) . '. En la web en inglés se verá vacío. Rellénalo en la columna <strong>EN</strong>.</p></div>';
		}
		if ( $missing['required'] ) {
			echo '<div class="notice notice-warning mats-cpta-notice" data-mats-missing="required"><p><strong>Faltan datos importantes:</strong> ' . esc_html( $quote( $missing['required'] ) ) . '. Sin ellos la ficha puede verse incompleta en la web.</p></div>';
		}
	}
);

/* ═══════════════════════════════════════════════════════════════════════
 * List screens
 * ═════════════════════════════════════════════════════════════════════ */

/** Does any record of the type have a featured image? (no empty photo column otherwise) */
function mats_cpta_type_has_thumbnails( string $type ): bool {
	return (bool) get_posts(
		array(
			'post_type'        => $type,
			'post_status'      => 'any',
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'meta_key'         => '_thumbnail_id', // phpcs:ignore WordPress.DB.SlowDBQuery
			'suppress_filters' => true,
			'no_found_rows'    => true,
		)
	);
}

/** Columns per type (after the checkbox). */
function mats_cpta_list_columns( string $type ): array {
	$cfg     = mats_cpta_config( $type );
	$columns = array();
	if ( ! empty( $cfg['thumb'] ) && ( 'mats_team' === $type || mats_cpta_type_has_thumbnails( $type ) ) ) {
		$columns['mats_thumb'] = $cfg['thumb']['label'];
	}
	$columns['title'] = (string) ( $cfg['list_title'] ?? 'Nombre' );
	switch ( $type ) {
		case 'mats_team':
			$columns['mats_role']    = 'Cargo';
			$columns['mats_cutout']  = 'Recorte';
			break;
		case 'mats_application':
			$columns['mats_app_title'] = 'Título en la web';
			$columns['mats_slug']      = 'Identificador';
			break;
		case 'mats_publication':
			$columns['mats_journal'] = 'Revista';
			$columns['mats_year']    = 'Año';
			break;
		case 'mats_expert':
			$columns['mats_who']   = 'Persona';
			$columns['mats_field'] = 'Campo';
			$columns['mats_date']  = 'Fecha';
			break;
	}
	$columns['mats_langs']  = 'Idiomas';
	$columns['mats_order']  = 'Orden';
	$columns['mats_status'] = 'En la web';
	return $columns;
}

add_action(
	'admin_init',
	static function () {
		foreach ( mats_cpta_types() as $type ) {
			add_filter(
				'manage_' . $type . '_posts_columns',
				static function ( $columns ) use ( $type ) {
					$out = array();
					if ( isset( $columns['cb'] ) ) {
						$out['cb'] = $columns['cb'];
					}
					return $out + mats_cpta_list_columns( $type );
				},
				99
			);
			add_action( 'manage_' . $type . '_posts_custom_column', 'mats_cpta_render_column', 10, 2 );
			add_filter(
				'manage_edit-' . $type . '_sortable_columns',
				static function ( $columns ) use ( $type ) {
					$sort                 = mats_cpta_config( $type )['sort'] ?? 'order';
					$columns['title']     = array( 'title', false, 'Nombre', 'Ordenar por nombre' );
					$columns['mats_order'] = 'order' === $sort ? array( 'menu_order', false, 'Orden', 'Ordenar por orden', 'asc' ) : array( 'menu_order', false, 'Orden', 'Ordenar por orden' );
					if ( 'year' === $sort ) {
						$columns['mats_year'] = array( 'mats_year', true, 'Año', 'Ordenar por año', 'desc' );
					}
					if ( 'date' === $sort ) {
						$columns['mats_date'] = array( 'mats_date', true, 'Fecha', 'Ordenar por fecha', 'desc' );
					}
					unset( $columns['date'] );
					return $columns;
				}
			);
		}
	}
);

/* no month filter (these records are not dated) */
add_filter(
	'disable_months_dropdown',
	static function ( $disable, $post_type ) {
		return mats_cpta_is_type( $post_type ) ? true : $disable;
	},
	10,
	2
);

/* Polylang's language filter means nothing here: each record holds both languages */
add_filter(
	'pll_admin_languages_filter',
	static function ( $items ) {
		return mats_cpta_screen() ? array() : $items;
	}
);

/**
 * Is the list showing EVERY record in the website order (▲▼ make sense)?
 * Not in "Mías", a status view, a search or any other filter: there the
 * rows side by side on screen are not neighbours on the website.
 */
function mats_cpta_list_is_default_view(): bool {
	static $cache = array();
	// phpcs:disable WordPress.Security.NonceVerification -- read-only list filters
	$status  = isset( $_GET['post_status'] ) ? sanitize_key( wp_unslash( $_GET['post_status'] ) ) : '';
	$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '';
	$order   = isset( $_GET['order'] ) ? strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) : 'asc';
	$search  = isset( $_GET['s'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) : '';
	$author  = isset( $_GET['author'] ) || isset( $_GET['author_name'] ) || ! empty( $_GET['m'] );
	// phpcs:enable
	if ( $author || ( '' !== $status && 'all' !== $status ) || '' !== $search || ( '' !== $orderby && ( 'menu_order' !== $orderby || 'asc' !== $order ) ) ) {
		return false;
	}
	/* whatever else filters the list (another plugin…): it must hold all the records */
	global $wp_query;
	$type = $wp_query instanceof WP_Query ? (string) $wp_query->get( 'post_type' ) : '';
	if ( ! mats_cpta_is_type( $type ) ) {
		return false;
	}
	if ( ! isset( $cache[ $type ] ) ) {
		$cache[ $type ] = (int) $wp_query->found_posts === count( mats_cpta_ordered_ids( $type ) );
	}
	return $cache[ $type ];
}

/** IDs of a type in display order (every status but trash). */
function mats_cpta_ordered_ids( string $type ): array {
	static $cache = array();
	if ( ! isset( $cache[ $type ] ) ) {
		$cache[ $type ] = array_map(
			'intval',
			get_posts(
				array(
					'post_type'        => $type,
					'post_status'      => array( 'publish', 'future', 'draft', 'pending', 'private' ),
					'posts_per_page'   => -1,
					'fields'           => 'ids',
					'orderby'          => array(
						'menu_order' => 'ASC',
						'date'       => 'ASC',
						'ID'         => 'ASC',
					),
					'suppress_filters' => true,
					'no_found_rows'    => true,
				)
			)
		);
	}
	return $cache[ $type ];
}

/** Two short lines: EN value and ES value. */
function mats_cpta_bilingual_cell( int $post_id, string $field ): string {
	$out    = '';
	$values = mats_cpta_i18n_values( $post_id, $field );
	foreach ( mats_langs() as $lang ) {
		$value = trim( (string) ( $values[ $lang ] ?? '' ) );
		$out  .= '<span class="mats-cpta-cell__line' . ( 'en' === $lang ? '' : ' is-secondary' ) . '"><span class="mats-cpta__pill mats-cpta__pill--small">' . esc_html( strtoupper( $lang ) ) . '</span> ' . ( '' !== $value ? esc_html( $value ) : '<span class="mats-cpta-cell__empty">' . esc_html( 'es' === $lang ? 'sin traducir' : 'vacío' ) . '</span>' ) . '</span>';
	}
	return $out;
}

/** One cell of our list columns. */
function mats_cpta_render_column( $column, $post_id ): void {
	$post = get_post( (int) $post_id );
	if ( ! $post instanceof WP_Post ) {
		return;
	}
	$type = $post->post_type;
	switch ( $column ) {
		case 'mats_thumb':
			$thumb = (int) get_post_thumbnail_id( $post );
			if ( $thumb && wp_attachment_is_image( $thumb ) ) {
				echo wp_get_attachment_image( $thumb, 'thumbnail', false, array( 'class' => 'mats-cpta-thumb' . ( 'mats_team' === $type ? ' is-round' : '' ), 'alt' => '', 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			} else {
				$label = mats_cpta_config( $type )['thumb']['label'] ?? 'foto';
				echo '<span class="mats-cpta-thumb is-empty' . ( 'mats_team' === $type ? ' is-round' : '' ) . '" title="' . esc_attr( 'Sin ' . mb_strtolower( $label ) ) . '"><span class="screen-reader-text">' . esc_html( 'Sin ' . mb_strtolower( $label ) ) . '</span></span>';
			}
			break;

		case 'mats_cutout':
			$id = absint( mats_cpta_value( $post->ID, 'showcase_image' ) );
			if ( $id && wp_attachment_is_image( $id ) ) {
				echo '<span class="mats-cpta-cutout">' . wp_get_attachment_image( $id, 'thumbnail', false, array( 'alt' => '', 'loading' => 'lazy' ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
			} else {
				echo '<span class="mats-cpta-cell__empty" title="Sin recorte: en el escaparate se verán las iniciales">—<span class="screen-reader-text"> Sin recorte</span></span>';
			}
			break;

		case 'mats_role':
			echo mats_cpta_bilingual_cell( $post->ID, 'role' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper
			break;

		case 'mats_app_title':
			echo mats_cpta_bilingual_cell( $post->ID, 'title' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper
			break;

		case 'mats_field':
			echo mats_cpta_bilingual_cell( $post->ID, 'field' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper
			break;

		case 'mats_slug':
			$slug = mats_cpta_value( $post->ID, 'slug' );
			echo '' !== $slug ? '<code class="mats-cpta-code">' . esc_html( $slug ) . '</code>' : '<span class="mats-cpta-cell__empty">—</span>';
			break;

		case 'mats_journal':
			$journal = mats_cpta_value( $post->ID, 'journal' );
			echo '' !== $journal ? '<em>' . esc_html( $journal ) . '</em>' : '<span class="mats-cpta-cell__empty">—</span>';
			break;

		case 'mats_year':
			$year = mats_cpta_value( $post->ID, 'year' );
			echo '' !== $year ? esc_html( $year ) : '<span class="mats-cpta-cell__empty">—</span>';
			break;

		case 'mats_who':
			/* the name as the English site shows it (or the Spanish one when only that exists) */
			$names = mats_cpta_i18n_values( $post->ID, 'name' );
			$name  = trim( (string) ( $names['en'] ?? '' ) );
			$name  = '' !== $name ? $name : trim( (string) ( $names['es'] ?? '' ) );
			$inst  = mats_cpta_value( $post->ID, 'institution' );
			echo '' !== $name ? '<strong>' . esc_html( $name ) . '</strong>' : '<span class="mats-cpta-cell__empty">Sin firma</span>';
			if ( '' !== $inst ) {
				echo '<span class="mats-cpta-cell__line is-secondary">' . esc_html( $inst ) . '</span>';
			}
			break;

		case 'mats_date':
			$date = mats_cpta_value( $post->ID, 'date' );
			echo '' !== $date ? esc_html( mats_cpta_format_month( $date ) ) : '<span class="mats-cpta-cell__empty">Sin fecha</span>';
			break;

		case 'mats_langs':
			$missing = mats_cpta_missing( $post );
			if ( ! $missing['es'] && ! $missing['en'] ) {
				echo '<span class="mats-cpta-langs is-ok" title="Inglés y español completos"><span aria-hidden="true">✓</span> EN · ES</span>';
			} else {
				$parts = array();
				if ( $missing['es'] ) {
					$parts[] = 'Falta ES: ' . implode( ', ', $missing['es'] );
				}
				if ( $missing['en'] ) {
					$parts[] = 'Falta EN: ' . implode( ', ', $missing['en'] );
				}
				echo '<span class="mats-cpta-langs is-missing"><span class="mats-cpta-langs__icon" aria-hidden="true">!</span> ' . esc_html( implode( ' · ', $parts ) ) . '</span>';
			}
			break;

		case 'mats_order':
			echo '<span class="mats-cpta-order-cell"><span class="mats-cpta-order-cell__n">' . (int) $post->menu_order . '</span>';
			$cfg = mats_cpta_config( $type );
			if ( ! empty( $cfg['arrows'] ) && mats_cpta_list_is_default_view() && current_user_can( 'edit_post', $post->ID ) ) {
				$ids = mats_cpta_ordered_ids( $type );
				$pos = array_search( $post->ID, $ids, true );
				$title = get_the_title( $post );
				foreach ( array(
					'up'   => array( '▲', 'Subir', 0 !== $pos ),
					'down' => array( '▼', 'Bajar', false !== $pos && $pos < count( $ids ) - 1 ),
				) as $dir => $btn ) {
					list( $icon, $verb, $enabled ) = $btn;
					if ( false === $pos || ! $enabled ) {
						echo '<span class="mats-cpta-move is-disabled" aria-hidden="true">' . esc_html( $icon ) . '</span>';
						continue;
					}
					$url = wp_nonce_url(
						add_query_arg(
							array(
								'action' => 'mats_cpta_move',
								'post'   => $post->ID,
								'dir'    => $dir,
							),
							admin_url( 'admin-post.php' )
						),
						'mats_cpta_move_' . $post->ID
					);
					echo '<a class="mats-cpta-move" href="' . esc_url( $url ) . '" title="' . esc_attr( $verb ) . '"><span aria-hidden="true">' . esc_html( $icon ) . '</span><span class="screen-reader-text">' . esc_html( $verb . ' «' . $title . '»' ) . '</span></a>';
				}
			}
			echo '</span>';
			break;

		case 'mats_status':
			list( $class, $label, $text ) = mats_cpta_status_info( $post );
			echo '<span class="mats-cpta-pill ' . esc_attr( $class ) . '" title="' . esc_attr( $text ) . '">' . esc_html( $label ) . '</span>';
			break;
	}
}

/* list order = website order unless the person sorts by a column */
add_action(
	'pre_get_posts',
	static function ( $query ) {
		global $pagenow;
		if ( ! is_admin() || 'edit.php' !== $pagenow || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
			return;
		}
		$type = $query->get( 'post_type' );
		if ( ! mats_cpta_is_type( $type ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification -- read-only list sorting
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '';
		$order   = isset( $_GET['order'] ) && 'desc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ? 'DESC' : 'ASC';
		// phpcs:enable
		$sort = mats_cpta_config( $type )['sort'] ?? 'order';

		/* by a stored value (year, date): see the posts_clauses filter below */
		$by_meta = static function ( string $field, string $dir ) use ( $query ) {
			$query->set( 'orderby', 'none' );
			$query->set(
				'mats_cpta_sort',
				array(
					'key' => mats_meta_key( $field ),
					'dir' => 'DESC' === $dir ? 'DESC' : 'ASC',
				)
			);
		};

		if ( '' === $orderby ) {
			if ( 'year' === $sort ) {
				$by_meta( 'year', 'DESC' );
			} elseif ( 'date' === $sort ) {
				$by_meta( 'date', 'DESC' );
			} else {
				$query->set(
					'orderby',
					array(
						'menu_order' => 'ASC',
						'date'       => 'ASC',
					)
				);
			}
		} elseif ( 'mats_year' === $orderby ) {
			$by_meta( 'year', $order );
		} elseif ( 'mats_date' === $orderby ) {
			$by_meta( 'date', $order );
		} elseif ( 'menu_order' === $orderby ) {
			$query->set(
				'orderby',
				array(
					'menu_order' => $order,
					'date'       => 'ASC',
				)
			);
		}
	}
);

/*
 * Sorting by year / date: records without the value go last (like on the
 * website), whatever the direction. A sub-select per record instead of a
 * meta_query: with EXISTS / NOT EXISTS the records without the value were
 * sorted by whatever other meta row the database picked (e.g. the edit
 * lock time), so a new draft could jump to the top. Years (4 digits) and
 * dates (YYYY-MM) sort correctly as text, in MySQL and SQLite alike.
 */
add_filter(
	'posts_clauses',
	static function ( $clauses, $query ) {
		$sort = $query instanceof WP_Query ? $query->get( 'mats_cpta_sort' ) : null;
		if ( ! is_array( $sort ) || empty( $sort['key'] ) || ! is_admin() ) {
			return $clauses;
		}
		global $wpdb;
		$dir   = 'DESC' === ( $sort['dir'] ?? '' ) ? 'DESC' : 'ASC';
		$value = $wpdb->prepare(
			"(SELECT MAX(mats_sort.meta_value) FROM {$wpdb->postmeta} AS mats_sort WHERE mats_sort.post_id = {$wpdb->posts}.ID AND mats_sort.meta_key = %s AND mats_sort.meta_value <> '')",
			(string) $sort['key']
		);
		$clauses['orderby'] = "CASE WHEN {$value} IS NULL THEN 1 ELSE 0 END ASC, {$value} {$dir}, {$wpdb->posts}.menu_order ASC, {$wpdb->posts}.post_date ASC, {$wpdb->posts}.ID ASC";
		return $clauses;
	},
	10,
	2
);

/* row links: "Ver en la web (EN / ES)"; no "Ver"/"Vista previa" */
add_filter(
	'post_row_actions',
	static function ( $actions, $post ) {
		if ( ! $post instanceof WP_Post || ! mats_cpta_is_type( $post->post_type ) ) {
			return $actions;
		}
		unset( $actions['view'], $actions['preview'] );
		if ( 'publish' === $post->post_status ) {
			foreach ( array(
				'en' => 'Ver en la web (EN)',
				'es' => 'Ver en la web (ES)',
			) as $lang => $label ) {
				$url = mats_cpta_view_url( $post, $lang );
				if ( $url ) {
					$actions[ 'mats_view_' . $lang ] = '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . '<span class="screen-reader-text"> (se abre en una pestaña nueva)</span></a>';
				}
			}
		}
		return $actions;
	},
	20,
	2
);

/* ▲▼: swap with the neighbour and renumber 1…n (admin-post, nonce + capability) */
add_action(
	'admin_post_mats_cpta_move',
	static function () {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification -- checked right below
		check_admin_referer( 'mats_cpta_move_' . $post_id );
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || ! mats_cpta_is_type( $post->post_type ) || empty( mats_cpta_config( $post->post_type )['arrows'] ) ) {
			wp_die( esc_html( 'No se ha podido cambiar el orden: la ficha no existe.' ), '', array( 'response' => 400, 'back_link' => true ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html( 'No tienes permiso para cambiar el orden de estas fichas.' ), '', array( 'response' => 403, 'back_link' => true ) );
		}
		$dir = isset( $_GET['dir'] ) && 'up' === sanitize_key( wp_unslash( $_GET['dir'] ) ) ? 'up' : 'down';
		$ids = mats_cpta_ordered_ids( $post->post_type );
		$i   = array_search( $post_id, $ids, true );
		$j   = false === $i ? false : ( 'up' === $dir ? $i - 1 : $i + 1 );
		if ( false !== $i && false !== $j && isset( $ids[ $j ] ) ) {
			if ( ! current_user_can( 'edit_post', $ids[ $j ] ) ) {
				wp_die( esc_html( 'No tienes permiso para cambiar el orden de estas fichas.' ), '', array( 'response' => 403, 'back_link' => true ) );
			}
			$ids[ $i ] = $ids[ $j ];
			$ids[ $j ] = $post_id;
			global $wpdb;
			foreach ( $ids as $n => $id ) {
				if ( (int) get_post_field( 'menu_order', $id ) !== $n + 1 ) {
					/* only the number changes: no new revision nor "last modified" date */
					$wpdb->update( $wpdb->posts, array( 'menu_order' => $n + 1 ), array( 'ID' => $id ), array( '%d' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					clean_post_cache( $id );
				}
			}
			/* those direct writes run no save hook: tell the page cache */
			mats_cpta_purge_page_cache();
		}
		$back = wp_get_referer();
		$back = $back ? $back : admin_url( 'edit.php?post_type=' . $post->post_type );
		wp_safe_redirect( add_query_arg( 'mats_moved', $post_id, remove_query_arg( array( 'mats_moved' ), $back ) ) );
		exit;
	}
);

/* list notices: what the list is + "moved" confirmation */
add_action(
	'admin_notices',
	static function () {
		$screen = mats_cpta_screen();
		if ( ! $screen || 'edit' !== $screen[0] ) {
			return;
		}
		$type = $screen[1];
		$cfg  = mats_cpta_config( $type );

		$moved = isset( $_GET['mats_moved'] ) ? absint( $_GET['mats_moved'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification -- display only
		if ( $moved && get_post_type( $moved ) === $type ) {
			$ids = mats_cpta_ordered_ids( $type );
			$pos = array_search( $moved, $ids, true );
			echo '<div class="notice notice-success is-dismissible mats-cpta-notice" data-mats-moved="' . (int) $moved . '"><p>' . esc_html( 'Orden cambiado: «' . get_the_title( $moved ) . '» está ahora en la posición ' . ( false === $pos ? '?' : $pos + 1 ) . '.' ) . '</p></div>';
		}

		$links = array();
		foreach ( array(
			'en' => 'en inglés',
			'es' => 'en español',
		) as $lang => $label ) {
			if ( function_exists( 'mats_page_id' ) && mats_page_id( (string) $cfg['page'], $lang ) ) {
				$links[] = '<a href="' . esc_url( mats_page_url( (string) $cfg['page'], $lang ) ) . '" target="_blank" rel="noopener">' . esc_html( 'Ver la página ' . $cfg['page_name'] . ' ' . $label ) . ' <span aria-hidden="true">↗</span></a>';
			}
		}
		echo '<div class="notice mats-cpta-intro"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span><p>' . esc_html( (string) $cfg['list_intro'] ) . ( $links ? ' ' . implode( ' · ', $links ) : '' ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- links escaped above
	}
);

/* ═══════════════════════════════════════════════════════════════════════
 * Assets
 * ═════════════════════════════════════════════════════════════════════ */

add_filter(
	'admin_body_class',
	static function ( $classes ) {
		$screen = mats_cpta_screen();
		if ( $screen ) {
			$classes .= ' mats-cpta-screen mats-cpta-' . ( 'edit' === $screen[0] ? 'list' : 'edit' );
		}
		return $classes;
	}
);

add_action(
	'admin_enqueue_scripts',
	static function () {
		$screen = mats_cpta_screen();
		if ( ! $screen ) {
			return;
		}
		list( $base, $type ) = $screen;
		wp_enqueue_style( 'matsurfer-admin-fields', MATS_URI . '/assets/css/admin-fields.css', array(), mats_ver( 'assets/css/admin-fields.css' ) );
		if ( 'post' === $base && 'mats_team' === $type ) {
			wp_enqueue_media();
		}
		wp_enqueue_script( 'matsurfer-admin-fields', MATS_URI . '/assets/js/admin-fields.js', 'post' === $base && 'mats_team' === $type ? array( 'media-editor' ) : array(), mats_ver( 'assets/js/admin-fields.js' ), true );
		wp_add_inline_script(
			'matsurfer-admin-fields',
			'window.matsCptAdmin = ' . wp_json_encode(
				array(
					'screen'     => $base,
					'type'       => $type,
					'mediaTitle' => 'Elige el recorte sin fondo (WebP o PNG)',
					'mediaButton' => 'Usar este recorte',
					'notCutout'  => 'Este archivo es un %s: tiene fondo, así que no quedará recortado en el escaparate. Usa un WebP o PNG con el fondo transparente.',
					'unsaved'    => 'Hay cambios sin guardar. Si sales ahora, se perderán.',
				)
			) . ';',
			'before'
		);
	}
);
