<?php
/**
 * Structured content: Applications, Team, Publications and Expert
 * evaluations — data layer (registration, field storage, getters, seed).
 *
 * Each item is ONE bilingual record: language-neutral data (name, photo,
 * LinkedIn, DOI, order…) is stored once, translatable text is stored per
 * language in "<field>_en" / "<field>_es". These post types are NOT
 * translated by Polylang — editors fill both languages on the same screen
 * (admin screens: inc/modules/content-types-admin.php).
 *
 * Storage
 *   post_status 'publish' = shown on the site; menu_order = display order.
 *   meta '_mats_<field>'        language-neutral value
 *   meta '_mats_<field>_<lang>' translatable value ('en' | 'es')
 *   featured image             main photo (team portrait, publication figure)
 *
 * Getters return arrays already resolved for one language, shaped like the
 * original site's content types (src/content/types.ts).
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Field map per post type: field => true when translatable.
 * Order here is the order of the admin form.
 */
function mats_cpt_fields(): array {
	return array(
		'mats_application' => array(
			'slug'      => false,
			'tag'       => true,
			'title'     => true,
			'short'     => true,
			'challenge' => true,
			'use'       => true,
			'benefit'   => true,
		),
		'mats_team'        => array(
			'role'           => true,
			'affiliation'    => true,
			'bio'            => true,
			'linkedin'       => false,
			'website'        => false,
			'showcase_image' => false,
		),
		'mats_publication' => array(
			'authors'     => false,
			'journal'     => false,
			'year'        => false,
			'doi'         => false,
			'url'         => false,
			'description' => true,
		),
		'mats_expert'      => array(
			'name'        => true, // translatable: titles differ ('Dr' / 'Dr.')
			'role'        => true,
			'institution' => false,
			'field'       => true,
			'quote'       => true,
			'device'      => true,
			'link'        => false,
			'date'        => false,
		),
	);
}

function mats_meta_key( string $field, string $lang = '' ): string {
	return '_mats_' . $field . ( $lang ? '_' . $lang : '' );
}

/** Value of a field for a post (translatable fields fall back to English). */
function mats_field( int $post_id, string $field, string $lang = '' ) {
	$type   = get_post_type( $post_id );
	$fields = mats_cpt_fields()[ $type ] ?? array();
	if ( ! empty( $fields[ $field ] ) ) {
		$lang  = $lang ?: mats_lang();
		$value = get_post_meta( $post_id, mats_meta_key( $field, $lang ), true );
		if ( '' === $value || null === $value ) {
			$value = get_post_meta( $post_id, mats_meta_key( $field, 'en' ), true );
		}
		if ( '' === $value || null === $value ) {
			/* a field that was language-neutral before keeps working */
			$value = get_post_meta( $post_id, mats_meta_key( $field ), true );
		}
		return (string) $value;
	}
	return (string) get_post_meta( $post_id, mats_meta_key( $field ), true );
}

add_action(
	'init',
	static function () {
		$common = array(
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'show_in_rest'        => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'capability_type'     => 'page',
			'map_meta_cap'        => true,
			'hierarchical'        => false,
		);
		register_post_type(
			'mats_application',
			array_merge(
				$common,
				array(
					'labels'        => mats_cpt_labels( 'Aplicaciones', 'Aplicación', 'Añadir aplicación', 'f' ),
					'menu_icon'     => 'dashicons-screenoptions',
					'menu_position' => 21,
					'supports'      => array( 'title', 'page-attributes' ),
				)
			)
		);
		register_post_type(
			'mats_team',
			array_merge(
				$common,
				array(
					'labels'        => mats_cpt_labels( 'Equipo', 'Persona del equipo', 'Añadir persona', 'f' ),
					'menu_icon'     => 'dashicons-groups',
					'menu_position' => 22,
					'supports'      => array( 'title', 'thumbnail', 'page-attributes' ),
				)
			)
		);
		register_post_type(
			'mats_publication',
			array_merge(
				$common,
				array(
					'labels'        => mats_cpt_labels( 'Publicaciones', 'Publicación', 'Añadir publicación', 'f' ),
					'menu_icon'     => 'dashicons-media-document',
					'menu_position' => 23,
					'supports'      => array( 'title', 'thumbnail', 'page-attributes' ),
				)
			)
		);
		register_post_type(
			'mats_expert',
			array_merge(
				$common,
				array(
					'labels'        => mats_cpt_labels( 'Valoraciones', 'Valoración de experto', 'Añadir valoración', 'f' ),
					'menu_icon'     => 'dashicons-format-quote',
					'menu_position' => 24,
					'supports'      => array( 'title', 'thumbnail', 'page-attributes' ),
				)
			)
		);
	},
	5
);

/** Spanish admin labels. $g = 'f' | 'm' (grammatical gender of the singular). */
function mats_cpt_labels( string $plural, string $singular, string $add, string $g = 'm' ): array {
	$new = 'f' === $g ? 'Nueva' : 'Nuevo';
	return array(
		'name'               => $plural,
		'singular_name'      => $singular,
		'menu_name'          => $plural,
		'add_new'            => $add,
		'add_new_item'       => $add,
		'edit_item'          => 'Editar ' . mb_strtolower( $singular ),
		'new_item'           => $new . ' ' . mb_strtolower( $singular ),
		'view_item'          => 'Ver ' . mb_strtolower( $singular ),
		'search_items'       => 'Buscar',
		'not_found'          => 'Todavía no hay ninguna.',
		'not_found_in_trash' => 'La papelera está vacía.',
		'all_items'          => 'Todas',
		'featured_image'     => 'Foto',
		'set_featured_image' => 'Elegir foto',
		'remove_featured_image' => 'Quitar foto',
		'use_featured_image' => 'Usar como foto',
	);
}

/** Polylang must not translate these: each record holds both languages. */
add_filter(
	'pll_get_post_types',
	static function ( $types ) {
		foreach ( array_keys( mats_cpt_fields() ) as $type ) {
			unset( $types[ $type ] );
		}
		unset( $types['mats_lead'] );
		return $types;
	},
	20
);

/** Published posts of a type, in display order. */
function mats_cpt_posts( string $type ): array {
	return get_posts(
		array(
			'post_type'        => $type,
			'post_status'      => 'publish',
			'posts_per_page'   => 100,
			'orderby'          => array(
				'menu_order' => 'ASC',
				'date'       => 'ASC',
			),
			'suppress_filters' => true,
			'no_found_rows'    => true,
		)
	);
}

/** Image value (same shape as block attributes) of an attachment ID. */
function mats_attachment_value( int $id, string $alt = '' ): array {
	if ( ! $id || ! wp_attachment_is_image( $id ) ) {
		return array();
	}
	return array(
		'id'  => $id,
		'url' => (string) wp_get_attachment_url( $id ),
		'alt' => $alt ?: (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
	);
}

/**
 * Applications, ordered. Each: id, slug, tag, title, shortDescription,
 * challenge, use, benefit, order.
 */
function mats_get_applications( string $lang = '' ): array {
	$lang = $lang ?: mats_lang();
	$out  = array();
	foreach ( mats_cpt_posts( 'mats_application' ) as $p ) {
		$slug  = sanitize_title( mats_field( $p->ID, 'slug' ) ?: $p->post_name ?: $p->post_title );
		$out[] = array(
			'id'               => $p->ID,
			'slug'             => $slug,
			'tag'              => mats_field( $p->ID, 'tag', $lang ),
			'title'            => mats_field( $p->ID, 'title', $lang ) ?: $p->post_title,
			'shortDescription' => mats_field( $p->ID, 'short', $lang ),
			'challenge'        => mats_field( $p->ID, 'challenge', $lang ),
			'use'              => mats_field( $p->ID, 'use', $lang ),
			'benefit'          => mats_field( $p->ID, 'benefit', $lang ),
			'order'            => (int) $p->menu_order,
		);
	}
	return $out;
}

/**
 * Team members, ordered. Each: id, name, role, affiliation, bio, image
 * (portrait), showcaseImage (cut-out WebP), linkedin, website, order.
 */
function mats_get_team( string $lang = '' ): array {
	$lang = $lang ?: mats_lang();
	$out  = array();
	foreach ( mats_cpt_posts( 'mats_team' ) as $p ) {
		$name  = get_the_title( $p );
		$out[] = array(
			'id'            => $p->ID,
			'name'          => $name,
			'role'          => mats_field( $p->ID, 'role', $lang ),
			'affiliation'   => mats_field( $p->ID, 'affiliation', $lang ),
			'bio'           => mats_field( $p->ID, 'bio', $lang ),
			'image'         => mats_attachment_value( (int) get_post_thumbnail_id( $p ), $name ),
			'showcaseImage' => mats_attachment_value( (int) mats_field( $p->ID, 'showcase_image' ), $name ),
			'linkedin'      => mats_field( $p->ID, 'linkedin' ),
			'website'       => mats_field( $p->ID, 'website' ),
			'order'         => (int) $p->menu_order,
		);
	}
	return $out;
}

/**
 * Publications: newest year first, then by order. Each: id, title, authors,
 * journal, year, doi, url, description, image, order.
 */
function mats_get_publications( string $lang = '' ): array {
	$lang = $lang ?: mats_lang();
	$out  = array();
	foreach ( mats_cpt_posts( 'mats_publication' ) as $p ) {
		$out[] = array(
			'id'          => $p->ID,
			'title'       => get_the_title( $p ),
			'authors'     => mats_field( $p->ID, 'authors' ),
			'journal'     => mats_field( $p->ID, 'journal' ),
			'year'        => (int) mats_field( $p->ID, 'year' ),
			'doi'         => mats_field( $p->ID, 'doi' ),
			'url'         => mats_field( $p->ID, 'url' ),
			'description' => mats_field( $p->ID, 'description', $lang ),
			'image'       => mats_attachment_value( (int) get_post_thumbnail_id( $p ) ),
			'order'       => (int) $p->menu_order,
		);
	}
	usort( $out, static fn( $a, $b ) => ( $b['year'] <=> $a['year'] ) ?: ( $a['order'] <=> $b['order'] ) );
	return $out;
}

/**
 * Expert evaluations: newest first (by 'date', YYYY-MM). Each: id, name,
 * role, institution, field, quote, device, image, link, date.
 */
function mats_get_experts( string $lang = '' ): array {
	$lang = $lang ?: mats_lang();
	$out  = array();
	foreach ( mats_cpt_posts( 'mats_expert' ) as $p ) {
		$out[] = array(
			'id'          => $p->ID,
			'name'        => mats_field( $p->ID, 'name', $lang ),
			'role'        => mats_field( $p->ID, 'role', $lang ),
			'institution' => mats_field( $p->ID, 'institution' ),
			'field'       => mats_field( $p->ID, 'field', $lang ),
			'quote'       => mats_field( $p->ID, 'quote', $lang ),
			'device'      => mats_field( $p->ID, 'device', $lang ),
			'image'       => mats_attachment_value( (int) get_post_thumbnail_id( $p ) ),
			'link'        => mats_field( $p->ID, 'link' ),
			'date'        => mats_field( $p->ID, 'date' ),
		);
	}
	usort( $out, static fn( $a, $b ) => strcmp( (string) $b['date'], (string) $a['date'] ) );
	return $out;
}

/** True when at least one news article is published in the language. */
function mats_has_news( string $lang = '' ): bool {
	static $cache = array();
	$lang = $lang ?: mats_lang();
	if ( ! isset( $cache[ $lang ] ) ) {
		$args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		);
		if ( function_exists( 'pll_current_language' ) ) {
			$args['lang'] = $lang;
		}
		$cache[ $lang ] = (bool) get_posts( $args );
	}
	return $cache[ $lang ];
}

/* ── import helpers (installer + seed) ───────────────────────────────── */

/**
 * Copies a file shipped in assets/media/ into the Media Library once and
 * returns its attachment ID (re-uses the earlier import on later calls).
 */
function mats_import_media( string $relative, string $alt = '', string $title = '' ): int {
	$relative = ltrim( str_replace( array( '/img/', '/video/' ), array( '', '' ), $relative ), '/' );
	$existing = get_posts(
		array(
			'post_type'        => 'attachment',
			'post_status'      => 'inherit',
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'meta_key'         => '_mats_media_source', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'       => $relative, // phpcs:ignore WordPress.DB.SlowDBQuery
			'suppress_filters' => true,
		)
	);
	if ( $existing ) {
		return (int) $existing[0];
	}
	$source = MATS_DIR . '/assets/media/' . $relative;
	if ( ! is_readable( $source ) ) {
		return 0;
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$tmp = wp_tempnam( basename( $source ) );
	if ( ! $tmp || ! copy( $source, $tmp ) ) {
		return 0;
	}
	$id = media_handle_sideload(
		array(
			'name'     => basename( $source ),
			'tmp_name' => $tmp,
		),
		0,
		$title ?: null
	);
	if ( is_wp_error( $id ) ) {
		wp_delete_file( $tmp );
		return 0;
	}
	update_post_meta( $id, '_mats_media_source', $relative );
	if ( $alt ) {
		update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	}
	return (int) $id;
}

/** Finds a seeded record by its seed key. */
function mats_seed_find( string $type, string $key ): int {
	$ids = get_posts(
		array(
			'post_type'        => $type,
			/* 'any' skips the trash: a record someone binned must not come back */
			'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'meta_key'         => '_mats_seed_key', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'       => $key, // phpcs:ignore WordPress.DB.SlowDBQuery
			'suppress_filters' => true,
		)
	);
	return $ids ? (int) $ids[0] : 0;
}

/**
 * Creates/updates one record. $neutral = [ field => value ],
 * $i18n = [ field => [ 'en' => …, 'es' => … ] ].
 */
function mats_seed_upsert( string $type, string $key, string $title, int $order, bool $publish, array $neutral, array $i18n, int $thumb = 0, bool $overwrite = false ): int {
	$id = mats_seed_find( $type, $key );
	if ( $id && ! $overwrite ) {
		return $id;
	}
	$postarr = array(
		'post_type'   => $type,
		'post_title'  => $title,
		'post_status' => $publish ? 'publish' : 'draft',
		'menu_order'  => $order,
	);
	if ( $id ) {
		$postarr['ID'] = $id;
		wp_update_post( $postarr );
	} else {
		$id = (int) wp_insert_post( $postarr );
		update_post_meta( $id, '_mats_seed_key', $key );
	}
	foreach ( $neutral as $field => $value ) {
		update_post_meta( $id, mats_meta_key( $field ), $value );
	}
	foreach ( $i18n as $field => $pair ) {
		foreach ( mats_langs() as $lang ) {
			update_post_meta( $id, mats_meta_key( $field, $lang ), (string) ( $pair[ $lang ] ?? '' ) );
		}
	}
	if ( $thumb ) {
		set_post_thumbnail( $id, $thumb );
	}
	return $id;
}

/**
 * Seeds the four post types from the original site copy
 * (inc/data/content.json). Existing records are kept unless $overwrite.
 * Returns counts per type.
 */
function mats_seed_cpts( array $content, bool $overwrite = false ): array {
	$en     = $content['en'] ?? array();
	$es     = $content['es'] ?? array();
	$report = array();

	/* applications — paired by slug */
	$es_apps = array_column( $es['applications'] ?? array(), null, 'slug' );
	$n       = 0;
	foreach ( $en['applications'] ?? array() as $a ) {
		$b = $es_apps[ $a['slug'] ] ?? array();
		mats_seed_upsert(
			'mats_application',
			'app:' . $a['slug'],
			$a['title'],
			(int) $a['order'],
			! empty( $a['published'] ),
			array( 'slug' => $a['slug'] ),
			array(
				'tag'       => array( 'en' => $a['tag'], 'es' => $b['tag'] ?? '' ),
				'title'     => array( 'en' => $a['title'], 'es' => $b['title'] ?? '' ),
				'short'     => array( 'en' => $a['shortDescription'], 'es' => $b['shortDescription'] ?? '' ),
				'challenge' => array( 'en' => $a['challenge'], 'es' => $b['challenge'] ?? '' ),
				'use'       => array( 'en' => $a['use'], 'es' => $b['use'] ?? '' ),
				'benefit'   => array( 'en' => $a['benefit'], 'es' => $b['benefit'] ?? '' ),
			),
			0,
			$overwrite
		);
		++$n;
	}
	$report['mats_application'] = $n;

	/* team — paired by name */
	$es_team = array_column( $es['teamMembers'] ?? array(), null, 'name' );
	$n       = 0;
	foreach ( $en['teamMembers'] ?? array() as $m ) {
		$b        = $es_team[ $m['name'] ] ?? array();
		$photo    = ! empty( $m['image'] ) ? mats_import_media( $m['image'], $m['name'] ) : 0;
		$showcase = ! empty( $m['showcaseImage'] ) ? mats_import_media( $m['showcaseImage'], $m['name'] ) : 0;
		mats_seed_upsert(
			'mats_team',
			'team:' . sanitize_title( $m['name'] ),
			$m['name'],
			(int) $m['order'],
			! empty( $m['published'] ),
			array(
				'linkedin'       => $m['linkedin'] ?? '',
				'website'        => $m['website'] ?? '',
				'showcase_image' => $showcase ?: '',
			),
			array(
				'role'        => array( 'en' => $m['role'] ?? '', 'es' => $b['role'] ?? '' ),
				'affiliation' => array( 'en' => $m['affiliation'] ?? '', 'es' => $b['affiliation'] ?? '' ),
				'bio'         => array( 'en' => $m['bio'] ?? '', 'es' => $b['bio'] ?? '' ),
			),
			$photo,
			$overwrite
		);
		++$n;
	}
	$report['mats_team'] = $n;

	/* publications — paired by position (titles stay in English) */
	$n = 0;
	foreach ( $en['publications'] ?? array() as $i => $p ) {
		$b = $es['publications'][ $i ] ?? array();
		mats_seed_upsert(
			'mats_publication',
			'pub:' . sanitize_title( $p['doi'] ?? $p['title'] ),
			$p['title'],
			(int) ( $p['order'] ?? $i ),
			! empty( $p['published'] ),
			array(
				'authors' => $p['authors'] ?? '',
				'journal' => $p['journal'] ?? '',
				'year'    => (string) ( $p['year'] ?? '' ),
				'doi'     => $p['doi'] ?? '',
				'url'     => $p['url'] ?? '',
			),
			array(
				'description' => array( 'en' => $p['description'] ?? '', 'es' => $b['description'] ?? '' ),
			),
			0,
			$overwrite
		);
		++$n;
	}
	$report['mats_publication'] = $n;

	/* expert evaluations — paired by id */
	$es_exp = array_column( $es['expertExperiences'] ?? array(), null, 'id' );
	$n      = 0;
	foreach ( $en['expertExperiences'] ?? array() as $i => $e ) {
		$b = $es_exp[ $e['id'] ] ?? array();
		mats_seed_upsert(
			'mats_expert',
			'expert:' . $e['id'],
			$e['field'] . ( ! empty( $e['name'] ) ? ' — ' . $e['name'] : '' ),
			$i,
			! empty( $e['published'] ),
			array(
				'institution' => $e['institution'] ?? '',
				'link'        => $e['link'] ?? '',
				'date'        => $e['date'] ?? '',
			),
			array(
				'name'   => array( 'en' => $e['name'] ?? '', 'es' => $b['name'] ?? ( $e['name'] ?? '' ) ),
				'role'   => array( 'en' => $e['role'] ?? '', 'es' => $b['role'] ?? ( $e['role'] ?? '' ) ),
				'field'  => array( 'en' => $e['field'] ?? '', 'es' => $b['field'] ?? '' ),
				'quote'  => array( 'en' => $e['quote'] ?? '', 'es' => $b['quote'] ?? '' ),
				'device' => array( 'en' => $e['device'] ?? '', 'es' => $b['device'] ?? '' ),
			),
			0,
			$overwrite
		);
		++$n;
	}
	$report['mats_expert'] = $n;

	return $report;
}

/** The original site copy shipped with the theme. */
function mats_content_data(): array {
	static $data = null;
	if ( null === $data ) {
		$file = MATS_DIR . '/inc/data/content.json';
		$data = is_readable( $file ) ? (array) json_decode( (string) file_get_contents( $file ), true ) : array();
	}
	return $data;
}

/** Image attribute value for a shipped photo (imports it on first use). */
function mats_seed_image( string $relative, string $alt = '' ): array {
	$id = mats_import_media( $relative, $alt );
	if ( ! $id ) {
		return array();
	}
	return array(
		'id'  => $id,
		'url' => (string) wp_get_attachment_url( $id ),
		'alt' => $alt,
	);
}

/**
 * Link attribute value from an Astro href:
 *   '/contact?interest=product' → page contact + query
 *   '/product#faq'              → page product + hash
 *   'mailto:…' / 'https://…'    → url
 */
function mats_seed_link( string $label, string $href ): array {
	$link = array(
		'label' => $label,
		'page'  => '',
		'url'   => '',
		'query' => '',
		'hash'  => '',
	);
	if ( str_starts_with( $href, 'mailto:' ) ) {
		$link['page'] = 'email';
		return $link;
	}
	if ( ! str_starts_with( $href, '/' ) ) {
		$link['url'] = $href;
		return $link;
	}
	$hash = '';
	if ( false !== strpos( $href, '#' ) ) {
		list( $href, $hash ) = explode( '#', $href, 2 );
		$hash                = '#' . $hash;
	}
	$query = '';
	if ( false !== strpos( $href, '?' ) ) {
		list( $href, $query ) = explode( '?', $href, 2 );
		$query                = '?' . $query;
	}
	$path  = trim( $href, '/' );
	$roles = array(
		''             => 'home',
		'product'      => 'product',
		'applications' => 'applications',
		'research'     => 'research',
		'team'         => 'team',
		'contact'      => 'contact',
	);
	$link['page']  = $roles[ $path ] ?? 'home';
	$link['query'] = $query;
	$link['hash']  = $hash;
	return $link;
}

/**
 * Attributes for one section on one page, from the original copy.
 * Mappers live in inc/installer/seed/<block>.php and define
 * mats_seed_attrs_<block_with_underscores>( array $c, string $page, string $lang ).
 * $c = content.json for one language + '_brand', '_pending', '_pendingData'.
 */
function mats_seed_attrs( string $block, string $page, string $lang ): array {
	$file = MATS_DIR . '/inc/installer/seed/' . sanitize_file_name( $block ) . '.php';
	if ( is_readable( $file ) ) {
		require_once $file;
	}
	$fn = 'mats_seed_attrs_' . str_replace( '-', '_', $block );
	if ( ! function_exists( $fn ) ) {
		return array();
	}
	$data                = mats_content_data();
	$c                   = $data[ $lang ] ?? array();
	$c['_brand']         = $data['brand'] ?? array();
	$c['_pending']       = $data['pendingContent'] ?? array();
	$c['_pendingData']   = $data['pendingData'] ?? array();
	$c['_interestMap']   = $data['interestParamMap'] ?? array();
	return mats_with_lang( $lang, static fn() => (array) $fn( $c, $page, $lang ) );
}

/** A serialized, locked section block ready for post_content. */
function mats_seed_block( string $block, string $page, string $lang, array $extra = array() ): string {
	$attrs         = array_merge( mats_seed_attrs( $block, $page, $lang ), $extra );
	$attrs['lock'] = array(
		'move'   => true,
		'remove' => true,
	);
	return serialize_block(
		array(
			'blockName'    => 'matsurfer/' . $block,
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		)
	);
}
