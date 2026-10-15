<?php
/**
 * Setup step 3 — content.
 *
 *  - removes the WordPress sample content, only when nobody touched it
 *  - creates the structural pages in English and Spanish (Polylang
 *    translations linked), each one a list of locked MatSurfer sections
 *    built from the original copy: mats_seed_block( block, role, lang )
 *  - stores '_mats_role' on every page and role => English ID in the
 *    'mats_page_roles' option; sets the front page, the news page and the
 *    privacy page; SEO title/description from the original copy
 *  - seeds team, applications, publications and expert evaluations once
 *  - renames the default category to News / Noticias
 *
 * Re-running never duplicates anything: pages are found by role (then by
 * slug), kept as they are unless 'overwrite' is set. Sections that could not
 * be filled yet (their seed mapper did not exist) are remembered in the
 * page meta '_mats_seed_pending' and filled on a later run, without touching
 * the rest of the page.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

/** Placeholder of the legal pages (the texts come from ICMAB; never invent them). */
function mats_installer_legal_placeholder(): string {
	return "<!-- wp:paragraph -->\n<p>Pendiente: texto legal facilitado por el ICMAB.</p>\n<!-- /wp:paragraph -->";
}

/**
 * Pages by role: titles/slugs per language, sections in order (from the
 * Astro pages), draft flag, excerpt.
 */
function mats_installer_page_specs(): array {
	return array(
		'home'         => array(
			'en'     => array( 'Home', 'home' ),
			'es'     => array( 'Inicio', 'inicio' ),
			'blocks' => array( 'hero-field', 'value-flow', 'product-teaser', 'benefits', 'apps-teaser', 'audiences', 'explore', 'page-cta' ),
		),
		'product'      => array(
			'en'     => array( 'Product', 'product' ),
			'es'     => array( 'Producto', 'producto' ),
			'blocks' => array( 'page-hero', 'section-intro', 'differentiator', 'how-it-works', 'parameters', 'benefits', 'faq', 'page-cta' ),
		),
		'applications' => array(
			'en'     => array( 'Applications', 'applications' ),
			'es'     => array( 'Aplicaciones', 'aplicaciones' ),
			'blocks' => array( 'page-hero', 'applications-list', 'broader', 'page-cta' ),
		),
		'research'     => array(
			'en'     => array( 'Case studies', 'research' ),
			'es'     => array( 'Casos de estudio', 'casos-de-estudio' ),
			'blocks' => array( 'page-hero', 'research-experiences', 'research-publications', 'research-validation', 'invite', 'page-cta' ),
		),
		'team'         => array(
			'en'     => array( 'Team', 'team' ),
			'es'     => array( 'Equipo', 'equipo' ),
			'blocks' => array( 'team-showcase', 'team-journey', 'page-cta' ),
		),
		'contact'      => array(
			'en'     => array( 'Contact', 'contact' ),
			'es'     => array( 'Contacto', 'contacto' ),
			'blocks' => array( 'contact' ),
		),
		'news'         => array(
			'en'      => array( 'News', 'news' ),
			'es'      => array( 'Noticias', 'noticias' ),
			'blocks'  => array(),
			'excerpt' => array(
				'en' => 'News and updates from the MatSurfer team at ICMAB-CSIC.',
				'es' => 'Noticias y novedades del equipo de MatSurfer en el ICMAB-CSIC.',
			),
		),
		'privacy'      => array(
			'en'     => array( 'Privacy Policy', 'privacy-policy' ),
			'es'     => array( 'Política de privacidad', 'politica-de-privacidad' ),
			'blocks' => array(),
			'legal'  => true,
		),
		'legal'        => array(
			'en'     => array( 'Legal notice', 'legal-notice' ),
			'es'     => array( 'Aviso legal', 'aviso-legal' ),
			'blocks' => array(),
			'legal'  => true,
		),
	);
}

/** SEO title + description of a page from the original copy ([] if none). */
function mats_installer_seo( string $role, string $lang ): array {
	$c = mats_content_data()[ $lang ] ?? array();
	switch ( $role ) {
		case 'home':
			$meta = $c['site']['meta'] ?? array();
			return array( (string) ( $meta['title'] ?? '' ), (string) ( $meta['description'] ?? '' ) );
		case 'product':
			$meta = $c['product']['meta'] ?? array();
			return array( (string) ( $meta['title'] ?? '' ), (string) ( $meta['description'] ?? '' ) );
		case 'applications':
			$meta = $c['applicationsPage']['meta'] ?? array();
			return array( (string) ( $meta['title'] ?? '' ), (string) ( $meta['description'] ?? '' ) );
		case 'research':
			return array( (string) ( $c['researchPage']['metaTitle'] ?? '' ), (string) ( $c['researchPage']['metaDescription'] ?? '' ) );
		case 'team':
			return array( (string) ( $c['teamPage']['metaTitle'] ?? '' ), (string) ( $c['teamPage']['metaDescription'] ?? '' ) );
		case 'contact':
			$meta = $c['contact']['meta'] ?? array();
			return array( (string) ( $meta['title'] ?? '' ), (string) ( $meta['description'] ?? '' ) );
	}
	return array();
}

/** Human (Spanish) name of a section, from its block.json title. */
function mats_installer_block_title( string $block ): string {
	$registry = mats_blocks();
	if ( isset( $registry[ 'matsurfer/' . $block ]['title'] ) ) {
		return (string) $registry[ 'matsurfer/' . $block ]['title'];
	}
	$file = MATS_DIR . '/blocks/' . $block . '/block.json';
	if ( is_readable( $file ) ) {
		$meta = json_decode( (string) file_get_contents( $file ), true );
		if ( ! empty( $meta['title'] ) ) {
			return (string) $meta['title'];
		}
	}
	return $block;
}

/** Spanish name of a role + language, for messages ("Producto (ES)"). */
function mats_installer_page_label( string $role, string $lang = '' ): string {
	$name = mats_page_roles()[ $role ] ?? $role;
	return $lang ? $name . ' (' . strtoupper( $lang ) . ')' : $name;
}

/* ── low level ───────────────────────────────────────────────────────── */

/**
 * Inserts or updates a post with trusted theme content: slashed as
 * wp_insert_post() expects (the block JSON contains backslashes) and with
 * the HTML filter paused (the content comes from the theme, not a user).
 *
 * @return int|WP_Error
 */
function mats_installer_save_post( array $postarr ) {
	$kses = false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );
	if ( $kses ) {
		kses_remove_filters();
	}
	try {
		$id = empty( $postarr['ID'] ) ? wp_insert_post( wp_slash( $postarr ), true ) : wp_update_post( wp_slash( $postarr ), true );
	} finally {
		if ( $kses ) {
			kses_init_filters();
		}
	}
	return $id;
}

/**
 * Stores the current version of a page in its «Revisiones» when it is not
 * there yet. WordPress never saves a revision when a page is CREATED (only
 * on later saves), so without this the first change somebody saves to a
 * page made by the setup would be its only version and the original text
 * could not be brought back. Does nothing when that version is already
 * stored (or revisions are switched off on the server).
 */
function mats_installer_keep_revision( int $id ): void {
	if ( $id && function_exists( 'wp_save_post_revision' ) ) {
		wp_save_post_revision( $id );
	}
}

/**
 * 'pre_post_update': just before a structural page changes, its current
 * version is stored if «Revisiones» does not have it (pages created before
 * this theme version, or changed without a revision). Normal saves already
 * have it, so then nothing is added.
 *
 * @param int $post_id Post being updated.
 */
function mats_installer_revision_before_update( $post_id ): void {
	$post_id = (int) $post_id;
	if ( ! $post_id || 'page' !== get_post_type( $post_id ) || '' === (string) get_post_meta( $post_id, '_mats_role', true ) ) {
		return;
	}
	mats_installer_keep_revision( $post_id );
}

/** Language slug of a post ('' when Polylang is off or it has none). */
function mats_installer_post_lang( int $id ): string {
	if ( function_exists( 'pll_get_post_language' ) && mats_installer_pll_ready() ) {
		return (string) pll_get_post_language( $id, 'slug' );
	}
	return '';
}

/** Is this an existing, non-trashed page? */
function mats_installer_is_live_page( int $id ): bool {
	$post = $id ? get_post( $id ) : null;
	return $post && 'page' === $post->post_type && ! in_array( $post->post_status, array( 'trash', 'auto-draft', 'inherit' ), true );
}

/**
 * The page of a role in a language. Returns [ id, how ] where how is
 * 'role' (it is ours) or 'slug' (a page with our slug and no role) or ''.
 */
function mats_installer_find_page( string $role, string $lang ): array {
	$pll   = mats_installer_pll_ready();
	$roles = get_option( 'mats_page_roles', array() );
	$en_id = is_array( $roles ) ? (int) ( $roles[ $role ] ?? 0 ) : 0;

	if ( $en_id && mats_installer_is_live_page( $en_id ) ) {
		if ( 'en' === $lang && ( ! $pll || in_array( mats_installer_post_lang( $en_id ), array( '', 'en' ), true ) ) ) {
			return array( $en_id, 'role' );
		}
		if ( $pll && 'en' !== $lang ) {
			$tr = (int) pll_get_post( $en_id, $lang );
			if ( $tr && mats_installer_is_live_page( $tr ) ) {
				return array( $tr, 'role' );
			}
		}
	}

	$ids = get_posts(
		array(
			'post_type'        => 'page',
			'post_status'      => 'any',
			'numberposts'      => 20,
			'fields'           => 'ids',
			'meta_key'         => '_mats_role', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'       => $role, // phpcs:ignore WordPress.DB.SlowDBQuery
			'orderby'          => 'ID',
			'order'            => 'ASC',
			'suppress_filters' => true,
			'lang'             => '',
		)
	);
	foreach ( $ids as $id ) {
		$id_lang = mats_installer_post_lang( (int) $id );
		if ( $id_lang === $lang || ( '' === $id_lang && 'en' === $lang ) ) {
			return array( (int) $id, 'role' );
		}
	}

	$spec = mats_installer_page_specs()[ $role ] ?? array();
	$slug = $spec[ $lang ][1] ?? '';
	if ( $slug ) {
		$ids = get_posts(
			array(
				'post_type'        => 'page',
				'post_status'      => 'any',
				'name'             => $slug,
				'numberposts'      => 5,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'lang'             => '',
			)
		);
		foreach ( $ids as $id ) {
			$id_lang = mats_installer_post_lang( (int) $id );
			if ( '' === (string) get_post_meta( (int) $id, '_mats_role', true ) && ( $id_lang === $lang || ( '' === $id_lang && 'en' === $lang ) ) ) {
				return array( (int) $id, 'slug' );
			}
		}
	}
	return array( 0, '' );
}

/**
 * Builds the sections of a page. Returns
 *   [ 'content' => serialized blocks, 'pending' => blocks without copy yet,
 *     'no_mapper' => …, 'no_render' => …, 'errors' => [ block => message ] ]
 */
function mats_installer_build_sections( string $role, string $lang ): array {
	$out = array(
		'content'   => '',
		'pending'   => array(),
		'no_mapper' => array(),
		'no_render' => array(),
		'errors'    => array(),
	);
	$parts = array();
	foreach ( mats_installer_page_specs()[ $role ]['blocks'] ?? array() as $block ) {
		$section = mats_installer_seed_section( $block, $role, $lang );
		$parts[] = $section['html'];
		if ( $section['empty'] ) {
			$out['pending'][] = $block;
		}
		if ( ! $section['mapper'] ) {
			$out['no_mapper'][] = $block;
		}
		if ( ! $section['render'] ) {
			$out['no_render'][] = $block;
		}
		if ( $section['error'] ) {
			$out['errors'][ $block ] = $section['error'];
		}
	}
	$out['content'] = implode( "\n\n", $parts );
	return $out;
}

/**
 * One section: [ html, empty (no copy), mapper (exists), render (template
 * exists), error ]. Never throws: a failing mapper gives an empty section.
 */
function mats_installer_seed_section( string $block, string $role, string $lang ): array {
	$mapper_file = MATS_DIR . '/inc/installer/seed/' . sanitize_file_name( $block ) . '.php';
	$out         = array(
		'html'   => '',
		'empty'  => true,
		'mapper' => is_readable( $mapper_file ),
		'render' => is_readable( MATS_DIR . '/blocks/' . $block . '/render.php' ),
		'error'  => '',
	);
	try {
		$html = mats_seed_block( $block, $role, $lang );
	} catch ( Throwable $e ) {
		$out['error'] = $e->getMessage();
		$html         = serialize_block(
			array(
				'blockName'    => 'matsurfer/' . $block,
				'attrs'        => array(
					'lock' => array(
						'move'   => true,
						'remove' => true,
					),
				),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
	}
	$out['html']  = $html;
	$out['empty'] = ! mats_installer_block_has_copy( parse_blocks( $html )[0] ?? array() );
	return $out;
}

/** Does a parsed block carry any attribute besides its lock? */
function mats_installer_block_has_copy( array $block ): bool {
	$attrs = (array) ( $block['attrs'] ?? array() );
	unset( $attrs['lock'] );
	return ! empty( $attrs );
}

/**
 * Fills the sections recorded as pending on an existing page, now that
 * their copy exists. Returns the names of the sections filled.
 */
function mats_installer_fill_pending( int $id, string $role, string $lang ): array {
	$pending = get_post_meta( $id, '_mats_seed_pending', true );
	$pending = is_array( $pending ) ? array_values( array_unique( $pending ) ) : array();
	if ( ! $pending ) {
		return array();
	}
	$blocks  = parse_blocks( (string) get_post_field( 'post_content', $id ) );
	$filled  = array();
	$still   = array();
	$changed = false;
	foreach ( $blocks as $i => $block ) {
		$name = (string) ( $block['blockName'] ?? '' );
		if ( ! str_starts_with( $name, 'matsurfer/' ) ) {
			continue;
		}
		$short = substr( $name, 10 );
		if ( ! in_array( $short, $pending, true ) ) {
			continue;
		}
		if ( mats_installer_block_has_copy( $block ) ) {
			continue; // somebody filled it by hand meanwhile: theirs stays
		}
		$section = mats_installer_seed_section( $short, $role, $lang );
		if ( $section['empty'] ) {
			$still[] = $short;
			continue;
		}
		$blocks[ $i ] = parse_blocks( $section['html'] )[0];
		$filled[]     = $short;
		$changed      = true;
	}
	if ( $changed ) {
		mats_installer_save_post(
			array(
				'ID'           => $id,
				'post_content' => serialize_blocks( $blocks ),
			)
		);
	}
	if ( $still ) {
		update_post_meta( $id, '_mats_seed_pending', $still );
	} else {
		delete_post_meta( $id, '_mats_seed_pending' );
	}
	return $filled;
}

/** Content of a page as the originals define it. */
function mats_installer_page_content( string $role, string $lang, array &$build ): string {
	$spec = mats_installer_page_specs()[ $role ];
	if ( ! empty( $spec['legal'] ) ) {
		$build = array(
			'pending'   => array(),
			'no_mapper' => array(),
			'no_render' => array(),
			'errors'    => array(),
		);
		return mats_installer_legal_placeholder();
	}
	$build = mats_installer_build_sections( $role, $lang );
	return $build['content'];
}

/**
 * SEO meta (title + description for Google): filled when empty, replaced
 * only when $overwrite. The content step never overwrites them: what
 * somebody wrote in «Google y redes sociales» is not kept in «Revisiones»,
 * so "Sobrescribir los textos y las fotos" leaves it alone.
 */
function mats_installer_set_seo( int $id, string $role, string $lang, bool $overwrite ): void {
	$seo = mats_installer_seo( $role, $lang );
	if ( ! $seo ) {
		return;
	}
	foreach ( array( '_mats_seo_title' => $seo[0], '_mats_seo_description' => $seo[1] ) as $key => $value ) {
		if ( '' === $value ) {
			continue;
		}
		if ( $overwrite || '' === (string) get_post_meta( $id, $key, true ) ) {
			update_post_meta( $id, $key, $value );
		}
	}
}

/**
 * Creates / keeps / restores the page of a role in a language.
 * Returns [ 'id' => int, 'action' => created|kept|adopted|overwritten|error,
 *           'build' => section report, 'filled' => sections filled now, 'error' => '' ].
 */
function mats_installer_ensure_page( string $role, string $lang, bool $overwrite ): array {
	$spec  = mats_installer_page_specs()[ $role ];
	$title = $spec[ $lang ][0];
	$slug  = $spec[ $lang ][1];
	$order = (int) array_search( $role, array_keys( mats_installer_page_specs() ), true );
	$out   = array(
		'id'     => 0,
		'action' => '',
		'build'  => array(),
		'filled' => array(),
		'error'  => '',
	);

	list( $id, $how ) = mats_installer_find_page( $role, $lang );
	$build            = array();

	/* "overwrite" restores section copy only: never the legal texts (their
	   original is a placeholder) nor the news page */
	$overwrite = $overwrite && ! empty( $spec['blocks'] );

	if ( ! $id ) {
		$content = mats_installer_page_content( $role, $lang, $build );
		$saved   = mats_installer_save_post(
			array(
				'post_type'      => 'page',
				'post_status'    => empty( $spec['legal'] ) ? 'publish' : 'draft',
				'post_title'     => $title,
				'post_name'      => $slug,
				'post_content'   => $content,
				'post_excerpt'   => (string) ( $spec['excerpt'][ $lang ] ?? '' ),
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
				'menu_order'     => $order,
				'post_author'    => get_current_user_id() ? get_current_user_id() : 1,
			)
		);
		if ( is_wp_error( $saved ) || ! $saved ) {
			$out['action'] = 'error';
			$out['error']  = is_wp_error( $saved ) ? $saved->get_error_message() : 'wp_insert_post';
			return $out;
		}
		$id            = (int) $saved;
		$out['action'] = 'created';
	} else {
		$post  = get_post( $id );
		$empty = '' === trim( (string) $post->post_content );
		if ( $overwrite || ( 'slug' === $how && $empty ) || ( $empty && ( $spec['blocks'] || ! empty( $spec['legal'] ) ) ) ) {
			$content = mats_installer_page_content( $role, $lang, $build );
			$update  = array(
				'ID'           => $id,
				'post_content' => $content,
			);
			if ( isset( $spec['excerpt'][ $lang ] ) ) {
				$update['post_excerpt'] = $spec['excerpt'][ $lang ];
			}
			$saved = mats_installer_save_post( $update );
			if ( is_wp_error( $saved ) ) {
				$out['action'] = 'error';
				$out['error']  = $saved->get_error_message();
				return $out;
			}
			$out['action'] = $overwrite ? 'overwritten' : ( 'slug' === $how ? 'adopted' : 'filled' );
			delete_post_meta( $id, '_mats_seed_pending' );
		} else {
			$out['action'] = 'slug' === $how ? 'adopted-kept' : 'kept';
			$out['filled'] = mats_installer_fill_pending( $id, $role, $lang );
		}
	}

	if ( ! empty( $build['pending'] ) ) {
		update_post_meta( $id, '_mats_seed_pending', $build['pending'] );
	}
	update_post_meta( $id, '_mats_role', $role );
	if ( function_exists( 'pll_set_post_language' ) && mats_installer_pll_ready() && mats_installer_post_lang( $id ) !== $lang ) {
		pll_set_post_language( $id, $lang );
	}
	mats_installer_set_seo( $id, $role, $lang, 'created' === $out['action'] );
	/* the version the setup leaves is the first one in «Revisiones» */
	mats_installer_keep_revision( $id );

	$out['id']    = $id;
	$out['build'] = $build;
	return $out;
}

/* ── WordPress sample content ────────────────────────────────────────── */

/** True when a post is still exactly as wp_install() left it. */
function mats_installer_is_pristine( ?WP_Post $post, string $type, string $guid_end ): bool {
	if ( ! $post || $type !== $post->post_type ) {
		return false;
	}
	if ( ! str_ends_with( (string) $post->guid, $guid_end ) ) {
		return false;
	}
	if ( $post->post_modified_gmt !== $post->post_date_gmt ) {
		return false; // edited at least once
	}
	return '' === (string) get_post_meta( $post->ID, '_mats_role', true );
}

/** Deletes "Hello world!", "Sample Page" and WP's privacy draft when untouched. */
function mats_installer_remove_samples( array &$result ): array {
	$removed = array();

	/* "Hello world!": untouched post with only WordPress' own sample comment */
	$hello = get_post( 1 );
	if ( mats_installer_is_pristine( $hello, 'post', '?p=1' ) && 'publish' === $hello->post_status && 1 === substr_count( (string) $hello->post_content, '<!-- wp:paragraph' ) ) {
		$comments = get_comments(
			array(
				'post_id' => 1,
				'status'  => 'all',
				'number'  => 5,
			)
		);
		$sample   = count( $comments ) <= 1;
		foreach ( $comments as $comment ) {
			if ( 'wapuu@wordpress.example' !== $comment->comment_author_email && $comment->comment_date_gmt !== $hello->post_date_gmt ) {
				$sample = false;
			}
		}
		if ( $sample && wp_delete_post( 1, true ) ) {
			$removed[] = '«' . $hello->post_title . '» (entrada de ejemplo y su comentario)';
		}
	}

	/* "Sample Page" */
	$sample_page = get_post( 2 );
	if ( mats_installer_is_pristine( $sample_page, 'page', '?page_id=2' ) && 'publish' === $sample_page->post_status && false !== strpos( (string) $sample_page->post_content, '<!-- wp:quote' ) ) {
		if ( wp_delete_post( 2, true ) ) {
			$removed[] = '«' . $sample_page->post_title . '» (página de ejemplo)';
		}
	}

	/* WordPress' privacy policy draft (its template text, never the real one) */
	$privacy = get_post( 3 );
	if ( mats_installer_is_pristine( $privacy, 'page', '?page_id=3' ) && 'draft' === $privacy->post_status && false !== strpos( (string) $privacy->post_content, '<!-- wp:heading' ) ) {
		if ( wp_delete_post( 3, true ) ) {
			$removed[] = '«' . $privacy->post_title . '» (borrador de privacidad de WordPress)';
		}
	}

	if ( $removed ) {
		mats_installer_add( $result, 'ok', 'Contenido de ejemplo de WordPress borrado (nadie lo había tocado): ' . implode( ', ', $removed ) . '.' );
	}
	return $removed;
}

/* ── default category → News / Noticias ──────────────────────────────── */

function mats_installer_is_default_cat_name( string $name ): bool {
	$name = mb_strtolower( trim( $name ) );
	return in_array( $name, array( 'uncategorized', 'uncategorised', 'sin categoría', 'sin categoria', 'sin categorizar', mb_strtolower( __( 'Uncategorized' ) ) ), true );
}

/**
 * Renames a still-default category. Returns missing|same|custom|error|renamed;
 * $before receives the name it had (for the message: «Uncategorized» on an
 * English WordPress, «Sin categoría» on one installed in Spanish…).
 */
function mats_installer_rename_category( int $term_id, string $name, string $slug, ?string &$before = null ): string {
	$term = get_term( $term_id, 'category' );
	if ( ! $term || is_wp_error( $term ) ) {
		return 'missing';
	}
	$before = (string) $term->name;
	if ( $term->name === $name ) {
		return 'same';
	}
	if ( ! mats_installer_is_default_cat_name( $term->name ) ) {
		return 'custom'; // renamed by someone: keep it
	}
	$updated = wp_update_term(
		$term_id,
		'category',
		array(
			'name' => $name,
			'slug' => $slug,
		)
	);
	return is_wp_error( $updated ) ? 'error' : 'renamed';
}

function mats_installer_news_category( array &$result ): void {
	$default = (int) get_option( 'default_category' );
	if ( ! $default ) {
		return;
	}
	$names = array(
		'en' => array( 'News', 'news' ),
		'es' => array( 'Noticias', 'noticias' ),
	);
	$pll   = mats_installer_pll_ready() && function_exists( 'pll_get_term' );
	$en_id = $default;
	if ( $pll ) {
		$en_id = (int) pll_get_term( $default, 'en' );
		if ( ! $en_id ) {
			$en_id = $default;
			if ( ! pll_get_term_language( $default ) ) {
				pll_set_term_language( $default, 'en' );
			}
		}
	}
	$done   = array();
	$before = '';
	$status = mats_installer_rename_category( $en_id, $names['en'][0], $names['en'][1], $before );
	if ( 'renamed' === $status ) {
		$done[] = sprintf( '«News» (antes «%s»)', $before );
	}

	if ( $pll && mats_installer_pll_language( 'es' ) ) {
		$es_id = (int) pll_get_term( $en_id, 'es' );
		if ( ! $es_id ) {
			$made = wp_insert_term( $names['es'][0], 'category', array( 'slug' => $names['es'][1] ) );
			if ( is_wp_error( $made ) && 'term_exists' === $made->get_error_code() ) {
				$es_id = (int) $made->get_error_data();
			} elseif ( ! is_wp_error( $made ) ) {
				$es_id  = (int) $made['term_id'];
				$done[] = '«Noticias» (nueva, para las noticias en español)';
			}
			if ( $es_id ) {
				pll_set_term_language( $es_id, 'es' );
				pll_save_term_translations(
					array(
						'en' => $en_id,
						'es' => $es_id,
					)
				);
			}
		} elseif ( 'renamed' === mats_installer_rename_category( $es_id, $names['es'][0], $names['es'][1], $before ) ) {
			$done[] = sprintf( '«Noticias» (antes «%s»)', $before );
		}
	}
	if ( $done ) {
		mats_installer_add( $result, 'ok', 'Categoría de las noticias: ' . implode( ' y ', $done ) . '.' );
	}
}

/* ── structured content (team, applications…) ────────────────────────── */

/** post type => [ content.json key, Spanish label ] */
function mats_installer_cpt_map(): array {
	return array(
		'mats_team'        => array( 'teamMembers', 'Equipo' ),
		'mats_application' => array( 'applications', 'Aplicaciones' ),
		'mats_publication' => array( 'publications', 'Publicaciones' ),
		'mats_expert'      => array( 'expertExperiences', 'Valoraciones de expertos' ),
	);
}

/** Records of a type in any status, trash included (abandoned auto-drafts do not count). */
function mats_installer_count_posts( string $type ): int {
	$counts = (array) wp_count_posts( $type );
	unset( $counts['auto-draft'] );
	return (int) array_sum( array_map( 'intval', $counts ) );
}

/**
 * Seeds each type once: a type that already has records (or that the
 * wizard seeded before and someone emptied on purpose) is left alone.
 */
function mats_installer_seed_structured( array &$result ): array {
	if ( ! function_exists( 'mats_seed_cpts' ) ) {
		mats_installer_add( $result, 'warn', 'Esta versión del tema no puede cargar el equipo, las aplicaciones ni las publicaciones.' );
		return array();
	}
	$state  = mats_installer_state();
	$data   = mats_content_data();
	$todo   = array();
	$kept   = array();
	foreach ( mats_installer_cpt_map() as $type => $info ) {
		$count = mats_installer_count_posts( $type );
		if ( $count > 0 ) {
			$kept[] = $info[1] . ' (' . $count . ')';
		} elseif ( ! empty( $state['seeded'][ $type ] ) ) {
			$kept[] = $info[1] . ' (vacío a propósito)';
		} else {
			$todo[ $type ] = $info;
		}
	}

	$report = array();
	if ( $todo ) {
		$subset = $data;
		foreach ( array( 'en', 'es' ) as $lang ) {
			foreach ( mats_installer_cpt_map() as $type => $info ) {
				if ( ! isset( $todo[ $type ] ) ) {
					unset( $subset[ $lang ][ $info[0] ] );
				}
			}
		}
		$report = (array) mats_seed_cpts( $subset, false );
		$state  = mats_installer_state();
		$loaded = array();
		foreach ( $todo as $type => $info ) {
			$state['seeded'][ $type ] = time();
			$loaded[]                 = $info[1] . ': ' . (int) ( $report[ $type ] ?? 0 );
		}
		mats_installer_save_state( $state );
		mats_installer_add( $result, 'ok', 'Contenido cargado desde la web original — ' . implode( ', ', $loaded ) . '.' );
	}
	if ( $kept ) {
		mats_installer_add( $result, 'info', 'Ya tenían contenido y no se han tocado: ' . implode( ', ', $kept ) . '.' );
	}
	return $report;
}

/**
 * Polylang keeps each language's front page and posts page in its cached
 * languages list; it is only refreshed when those options CHANGE, so after
 * linking new translations of the same pages the cache must be cleared or
 * /es/ would redirect to /es/inicio/.
 */
function mats_installer_refresh_polylang(): void {
	if ( ! mats_installer_pll_ready() ) {
		return;
	}
	$model = PLL()->model;
	if ( isset( $model->languages ) && is_object( $model->languages ) && method_exists( $model->languages, 'clean_cache' ) ) {
		$model->languages->clean_cache();
	} elseif ( method_exists( $model, 'clean_languages_cache' ) ) {
		$model->clean_languages_cache();
	}
	delete_transient( 'pll_languages_list' );
}

/**
 * Pages with empty sections whose original copy the theme now ships (after
 * a theme update): [ page ID => [ block, … ] ]. Cached until the list of
 * seed mappers changes or the content step runs.
 */
function mats_installer_fillable_pending(): array {
	$files = glob( MATS_DIR . '/inc/installer/seed/*.php' ) ?: array();
	$key   = md5( implode( ',', array_map( 'basename', $files ) ) );
	$cache = get_transient( 'mats_installer_fillable' );
	if ( is_array( $cache ) && ( $cache['key'] ?? '' ) === $key ) {
		return (array) $cache['pages'];
	}
	$pages = array();
	$ids   = get_posts(
		array(
			'post_type'        => 'page',
			'post_status'      => 'any',
			'numberposts'      => 50,
			'fields'           => 'ids',
			'meta_key'         => '_mats_seed_pending', // phpcs:ignore WordPress.DB.SlowDBQuery
			'suppress_filters' => true,
			'lang'             => '',
		)
	);
	foreach ( $ids as $id ) {
		$pending = get_post_meta( (int) $id, '_mats_seed_pending', true );
		$ready   = array_values( array_filter( (array) $pending, static fn( $b ) => is_readable( MATS_DIR . '/inc/installer/seed/' . sanitize_file_name( (string) $b ) . '.php' ) ) );
		if ( $ready ) {
			$pages[ (int) $id ] = $ready;
		}
	}
	set_transient(
		'mats_installer_fillable',
		array(
			'key'   => $key,
			'pages' => $pages,
		),
		DAY_IN_SECONDS
	);
	return $pages;
}

/* ── the step ────────────────────────────────────────────────────────── */

function mats_installer_step_content( array $opts ): array {
	$result    = mats_installer_result( 'content' );
	$overwrite = ! empty( $opts['overwrite'] );

	if ( mats_installer_needs_reload() ) {
		$result['reload']  = true;
		$result['skipped'] = true;
		mats_installer_add( $result, 'info', 'Este paso se hará en cuanto se recargue la página (se acaba de activar un plugin).' );
		return $result;
	}

	$langs = mats_installer_content_langs();
	if ( ! in_array( 'es', $langs, true ) ) {
		mats_installer_add( $result, 'warn', 'El español todavía no está configurado (pasos 1 y 2), así que solo se crean las páginas en inglés. Cuando esté, repite este paso y se crearán las páginas en español, enlazadas con las inglesas.' );
	}

	$removed = mats_installer_remove_samples( $result );

	$pages   = array();
	$by      = array(
		'created'     => array(),
		'kept'        => array(),
		'overwritten' => array(),
		'adopted'     => array(),
		'filled'      => array(),
	);
	$pending = array();
	$norend  = array();
	$errors  = array();

	foreach ( mats_installer_page_specs() as $role => $spec ) {
		foreach ( $langs as $lang ) {
			$page = mats_installer_ensure_page( $role, $lang, $overwrite );
			if ( 'error' === $page['action'] ) {
				mats_installer_add( $result, 'error', sprintf( 'No se ha podido crear la página %1$s: %2$s', mats_installer_page_label( $role, $lang ), $page['error'] ) );
				continue;
			}
			$pages[ $role ][ $lang ] = $page['id'];
			$label                   = mats_installer_page_label( $role, $lang );
			switch ( $page['action'] ) {
				case 'created':
					$by['created'][] = $label;
					break;
				case 'overwritten':
					$by['overwritten'][] = $label;
					break;
				case 'adopted':
				case 'filled':
					$by['adopted'][] = $label;
					break;
				case 'adopted-kept':
					mats_installer_add( $result, 'warn', sprintf( 'Ya existía una página «%1$s» con contenido propio: ahora es la página %2$s de la web, pero su contenido no se ha cambiado. Marca «%3$s» si quieres el diseño original.', get_the_title( $page['id'] ), $label, mats_installer_overwrite_label() ) );
					break;
				default:
					$by['kept'][] = $label;
			}
			if ( $page['filled'] ) {
				$by['filled'][] = $label . ': ' . implode( ', ', array_map( 'mats_installer_block_title', $page['filled'] ) );
			}
			foreach ( (array) ( $page['build']['errors'] ?? array() ) as $block => $message ) {
				$errors[] = $label . ' — ' . mats_installer_block_title( $block ) . ': ' . $message;
			}
			$still = get_post_meta( $page['id'], '_mats_seed_pending', true );
			if ( is_array( $still ) && $still ) {
				$pending[ $role . '.' . $lang ] = $still;
			}
			foreach ( (array) ( $page['build']['no_render'] ?? array() ) as $block ) {
				$norend[ $block ] = mats_installer_block_title( $block );
			}
		}

		/* EN ↔ ES translations */
		if ( isset( $pages[ $role ]['en'], $pages[ $role ]['es'] ) && function_exists( 'pll_save_post_translations' ) && mats_installer_pll_ready() ) {
			if ( (int) pll_get_post( $pages[ $role ]['en'], 'es' ) !== (int) $pages[ $role ]['es'] ) {
				pll_save_post_translations(
					array(
						'en' => $pages[ $role ]['en'],
						'es' => $pages[ $role ]['es'],
					)
				);
			}
		}
	}

	/* roles + WordPress reading settings */
	$roles = get_option( 'mats_page_roles', array() );
	$roles = is_array( $roles ) ? $roles : array();
	foreach ( $pages as $role => $ids ) {
		if ( ! empty( $ids['en'] ) ) {
			$roles[ $role ] = (int) $ids['en'];
		}
	}
	update_option( 'mats_page_roles', $roles );
	if ( ! empty( $roles['home'] ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', (int) $roles['home'] );
	}
	if ( ! empty( $roles['news'] ) ) {
		update_option( 'page_for_posts', (int) $roles['news'] );
	}
	if ( ! empty( $roles['privacy'] ) ) {
		update_option( 'wp_page_for_privacy_policy', (int) $roles['privacy'] );
	}

	mats_installer_news_category( $result );
	$cpts = mats_installer_seed_structured( $result );

	mats_installer_refresh_polylang();
	delete_option( 'rewrite_rules' );
	delete_transient( 'mats_installer_fillable' );

	/* report */
	$both = static fn( array $labels ) => implode( ', ', $labels );
	if ( $by['created'] ) {
		mats_installer_add( $result, 'ok', 'Páginas creadas con los textos originales: ' . $both( $by['created'] ) . '.' );
	}
	if ( $by['overwritten'] ) {
		mats_installer_add( $result, 'ok', 'Textos originales restaurados, con sus fotos, en: ' . $both( $by['overwritten'] ) . '. El título y la descripción para Google que hubierais escrito se mantienen.' );
	}
	if ( $by['adopted'] ) {
		mats_installer_add( $result, 'ok', 'Páginas que ya existían vacías, ahora con los textos originales: ' . $both( $by['adopted'] ) . '.' );
	}
	if ( $by['kept'] ) {
		mats_installer_add( $result, 'info', 'Ya existían y no se han tocado: ' . $both( $by['kept'] ) . '.' );
	}
	if ( $by['filled'] ) {
		mats_installer_add( $result, 'ok', 'Secciones que estaban vacías y ahora tienen sus textos: ' . implode( '; ', $by['filled'] ) . '.' );
	}
	if ( $pending ) {
		$lines = array();
		foreach ( $pending as $key => $blocks ) {
			list( $role, $lang ) = explode( '.', $key );
			$lines[]             = mats_installer_page_label( $role, $lang ) . ': ' . implode( ', ', array_map( 'mats_installer_block_title', $blocks ) );
		}
		mats_installer_add( $result, 'warn', 'Algunas secciones se han creado vacías porque esta versión del tema todavía no trae sus textos originales — ' . implode( '; ', $lines ) . '. Cuando se actualice el tema, repite este paso: se rellenarán solas sin tocar el resto.' );
	}
	if ( $norend ) {
		mats_installer_add( $result, 'warn', 'Estas secciones todavía no se ven en la web porque el tema no incluye aún su diseño: ' . implode( ', ', $norend ) . '. Aparecerán al actualizar el tema.' );
	}
	foreach ( $errors as $error ) {
		mats_installer_add( $result, 'warn', 'Error al preparar una sección (se ha dejado vacía): ' . $error );
	}
	if ( isset( $pages['privacy'] ) || isset( $pages['legal'] ) ) {
		mats_installer_add( $result, 'info', '«Política de privacidad» y «Aviso legal» están en borrador, a la espera del texto legal del ICMAB. Pega el texto y publícalas cuando lo tengas.', admin_url( 'edit.php?post_type=page&post_status=draft' ), 'Ver los borradores' );
	}
	if ( ! empty( $roles['home'] ) ) {
		mats_installer_add( $result, 'ok', 'Portada de la web: «Inicio». Las noticias se publican en «News» / «Noticias».', home_url( '/' ), 'Ver la web' );
	}

	$result['data'] = array(
		'pages'   => $pages,
		'roles'   => $roles,
		'created' => $by['created'],
		'kept'    => $by['kept'],
		'pending' => $pending,
		'removed' => $removed,
		'cpts'    => $cpts,
	);
	return $result;
}

function mats_installer_status_content(): array {
	$checks  = array();
	$langs   = mats_installer_content_langs();
	$missing = array();
	$pending = array();
	foreach ( mats_installer_page_specs() as $role => $spec ) {
		foreach ( mats_langs() as $lang ) {
			if ( 'es' === $lang && ! in_array( 'es', $langs, true ) ) {
				$missing[] = mats_installer_page_label( $role, $lang );
				continue;
			}
			list( $id, $how ) = mats_installer_find_page( $role, $lang );
			if ( ! $id || 'role' !== $how ) {
				$missing[] = mats_installer_page_label( $role, $lang );
				continue;
			}
			$still = get_post_meta( $id, '_mats_seed_pending', true );
			if ( is_array( $still ) && $still ) {
				$pending[] = mats_installer_page_label( $role, $lang );
			}
		}
	}
	$checks[] = mats_installer_check( ! $missing, $missing ? 'Faltan páginas: ' . implode( ', ', $missing ) . '.' : 'Las 9 páginas existen en inglés y en español.', admin_url( 'edit.php?post_type=page' ), 'Páginas' );
	if ( $pending ) {
		$checks[] = mats_installer_check( false, 'Páginas con secciones todavía vacías (esta versión del tema aún no trae sus textos originales; al actualizarlo, repite este paso): ' . implode( ', ', $pending ) . '.' );
	}
	$front    = 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === mats_page_id( 'home', 'en' ) && (int) get_option( 'page_on_front' ) > 0;
	$checks[] = mats_installer_check( $front, $front ? 'La portada es la página «Inicio» y las noticias van en «News».' : 'La portada todavía no es la página «Inicio».', admin_url( 'options-reading.php' ), 'Ajustes de lectura' );

	$counts = array();
	$empty  = array();
	foreach ( mats_installer_cpt_map() as $type => $info ) {
		$n = post_type_exists( $type ) ? (int) ( wp_count_posts( $type )->publish ?? 0 ) : 0;
		if ( $n ) {
			$counts[] = $info[1] . ' ' . $n;
		} else {
			$empty[] = $info[1];
		}
	}
	$checks[] = mats_installer_check( ! $empty, $empty ? 'Sin contenido todavía: ' . implode( ', ', $empty ) . '.' : 'Contenido publicado — ' . implode( ', ', $counts ) . '.' );
	$status   = mats_installer_status_from( $checks );

	/* everything is in place except sections whose original copy the theme does not ship yet */
	$others_ok = ! $missing && ! $empty && $front;
	if ( $pending && $others_ok ) {
		$status['state'] = 'warn';
		$status['label'] = 'Faltan textos';
	}
	return $status;
}
