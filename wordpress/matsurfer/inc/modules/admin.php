<?php
/**
 * Admin experience — a calm, obvious and safe back office for the ICMAB
 * team (role Editor), while administrators (Stimulo) keep full control.
 *
 *  - Escritorio: the default widgets go away; a full-width "Bienvenido/a"
 *    panel with big tiles (one per everyday task) + "Noticias recientes"
 *  - menu: Escritorio, Contactos, Noticias, Páginas, Equipo, Aplicaciones,
 *    Publicaciones, Valoraciones, Medios, Ajustes de la web … Guía de uso
 *    (last). "Entradas" are renamed "Noticias" everywhere. Editors do not
 *    see Herramientas, Apariencia, Plugins, Ajustes, Usuarios, Idiomas
 *    (Polylang) or WPForms; admins do not see the WPForms upsell pages.
 *  - comments and pingbacks are switched off site-wide (UI, feeds,
 *    wp-comments-post.php answers 403, XML-RPC pingbacks)
 *  - admin bar: no WordPress logo, comments or "new user/media" items
 *  - "Guía de uso" page (dashicons-sos) rendering inc/data/guide.php
 *  - login screen with the MatSurfer logo and colours, logo → home page
 *  - media: upload advice, big images scaled to 2560 px, JPEG quality 82,
 *    no SVG, very heavy images refused with a friendly message
 *  (XML-RPC, login errors and lock, user enumeration, headers, comment
 *  feeds and dangerous uploads are handled by inc/modules/security.php)
 *
 * The block editor lockdown (allowed blocks, structural pages…) lives in
 * inc/modules/editor.php. Styles: assets/css/admin.css; behaviour:
 * assets/js/admin.js.
 *
 * Filters for the person installing the site:
 *   mats_admin_settings_slug   slug of the "Ajustes de la web" screen ('mats-settings')
 *   mats_admin_tiles           the tiles of the welcome panel
 *   mats_guide_data            the guide content (inc/data/guide.php)
 *   mats_admin_max_image_mb    heaviest photo accepted (MB, default 20)
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

defined( 'MATS_GUIDE_PAGE' ) || define( 'MATS_GUIDE_PAGE', 'mats-guide' );

/* ═══════════════════════════════════════════════════════════════════════
 * Helpers
 * ═════════════════════════════════════════════════════════════════════ */

/** Is the (current) user an administrator of the site? */
function mats_admin_is_admin( $user_id = null ): bool {
	return null === $user_id ? current_user_can( 'manage_options' ) : user_can( (int) $user_id, 'manage_options' );
}

/** Language of the person using the admin ('es' when their admin is in Spanish). */
function mats_admin_user_lang(): string {
	$locale = function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
	return str_starts_with( (string) $locale, 'es' ) ? 'es' : 'en';
}

/** Slug of the "Ajustes de la web" screen (inc/modules/settings.php). */
function mats_admin_settings_slug(): string {
	return (string) apply_filters( 'mats_admin_settings_slug', 'mats-settings' );
}

/** URL of the in-admin guide, optionally at a section (#anchor). */
function mats_admin_guide_url( string $section = '' ): string {
	$url = admin_url( 'admin.php?page=' . MATS_GUIDE_PAGE );
	return $section ? $url . '#' . rawurlencode( $section ) : $url;
}

/**
 * Is an admin screen in the current user's menu? (After the menu is built,
 * WordPress has already removed what the user cannot open.)
 */
function mats_admin_menu_has( string $slug ): bool {
	global $menu, $submenu;
	foreach ( (array) $menu as $item ) {
		if ( isset( $item[2] ) && $slug === $item[2] ) {
			return true;
		}
	}
	foreach ( (array) $submenu as $children ) {
		foreach ( (array) $children as $item ) {
			if ( isset( $item[2] ) && $slug === $item[2] ) {
				return true;
			}
		}
	}
	return false;
}

/** URL of a menu page slug ('mats-settings' → admin.php?page=mats-settings). */
function mats_admin_page_url( string $slug ): string {
	return false !== strpos( $slug, '.php' ) ? admin_url( $slug ) : admin_url( 'admin.php?page=' . $slug );
}

/** "1 noticia" / "3 noticias". */
function mats_admin_plural( int $n, string $one, string $many ): string {
	return sprintf( 1 === $n ? $one : $many, $n );
}

/* ═══════════════════════════════════════════════════════════════════════
 * Admin language: Spanish by default when WordPress has it installed
 * ═════════════════════════════════════════════════════════════════════ */

/**
 * People who never chose a language in their profile get the admin in
 * Spanish when the Spanish language pack is installed (the site itself
 * stays bilingual). Anyone can still pick another language in "Perfil".
 */
function mats_admin_default_user_locale( $value, $user_id, $meta_key, $single ) {
	if ( 'locale' !== $meta_key || null !== $value ) {
		return $value;
	}
	static $spanish = null;
	if ( null === $spanish ) {
		$site    = (string) get_option( 'WPLANG' );
		$spanish = ! str_starts_with( $site, 'es' ) && in_array( 'es_ES', get_available_languages(), true );
	}
	if ( ! $spanish ) {
		return $value;
	}
	remove_filter( 'get_user_metadata', 'mats_admin_default_user_locale', 10 );
	$stored = get_user_meta( (int) $user_id, 'locale', true );
	add_filter( 'get_user_metadata', 'mats_admin_default_user_locale', 10, 4 );
	if ( '' !== (string) $stored ) {
		return $value;
	}
	return $single ? 'es_ES' : array( 'es_ES' );
}
add_filter( 'get_user_metadata', 'mats_admin_default_user_locale', 10, 4 );

/* ═══════════════════════════════════════════════════════════════════════
 * Comments and pingbacks: off everywhere
 * ═════════════════════════════════════════════════════════════════════ */

add_filter( 'comments_open', '__return_false', 20 );
add_filter( 'pings_open', '__return_false', 20 );
add_filter( 'comments_array', '__return_empty_array', 20 );
add_filter( 'get_comments_number', '__return_zero', 20 );
add_filter( 'feed_links_show_comments_feed', '__return_false' );
add_filter( 'pre_option_default_comment_status', static fn() => 'closed' );
add_filter( 'pre_option_default_ping_status', static fn() => 'closed' );
add_filter( 'pre_option_default_pingback_flag', '__return_zero' );

/* no post type supports comments or trackbacks (removes the Discussion panel and the column) */
add_action(
	'init',
	static function () {
		foreach ( get_post_types() as $type ) {
			if ( post_type_supports( $type, 'comments' ) ) {
				remove_post_type_support( $type, 'comments' );
			}
			if ( post_type_supports( $type, 'trackbacks' ) ) {
				remove_post_type_support( $type, 'trackbacks' );
			}
		}
	},
	100
);

/* posting a comment directly: 403 */
add_action(
	'init',
	static function () {
		if ( 'wp-comments-post.php' === ( $GLOBALS['pagenow'] ?? '' ) ) {
			wp_die(
				esc_html( 'Los comentarios están desactivados en esta web.' ),
				esc_html( 'Comentarios desactivados' ),
				array( 'response' => 403 )
			);
		}
	},
	0
);
add_action(
	'pre_comment_on_post',
	static function () {
		wp_die( esc_html( 'Los comentarios están desactivados en esta web.' ), esc_html( 'Comentarios desactivados' ), array( 'response' => 403 ) );
	},
	0
);

/* comment feeds: redirected to the home page by inc/modules/security.php */

/* pingbacks: no X-Pingback header (XML-RPC itself is off: inc/modules/security.php) */
add_filter(
	'wp_headers',
	static function ( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}
);
/* never ping the sites a news article links to */
add_action(
	'pre_ping',
	static function ( &$links ) {
		$links = array();
	}
);
add_action( 'widgets_init', static fn() => unregister_widget( 'WP_Widget_Recent_Comments' ), 20 );

/* the comments screen is not in the menu: anyone landing there goes to the dashboard */
add_action(
	'load-edit-comments.php',
	static function () {
		wp_safe_redirect( admin_url() );
		exit;
	}
);

/* ═══════════════════════════════════════════════════════════════════════
 * "Entradas" → "Noticias"
 * ═════════════════════════════════════════════════════════════════════ */

add_filter(
	'post_type_labels_post',
	static function ( $labels ) {
		$news = array(
			'name'                     => 'Noticias',
			'singular_name'            => 'Noticia',
			'menu_name'                => 'Noticias',
			'name_admin_bar'           => 'Noticia',
			'add_new'                  => 'Escribir una noticia',
			'add_new_item'             => 'Escribir una noticia',
			'edit_item'                => 'Editar noticia',
			'new_item'                 => 'Nueva noticia',
			'view_item'                => 'Ver noticia',
			'view_items'               => 'Ver noticias',
			'search_items'             => 'Buscar noticias',
			'not_found'                => 'Todavía no hay noticias.',
			'not_found_in_trash'       => 'No hay noticias en la papelera.',
			'all_items'                => 'Todas las noticias',
			'archives'                 => 'Archivo de noticias',
			'attributes'               => 'Atributos de la noticia',
			'insert_into_item'         => 'Insertar en la noticia',
			'uploaded_to_this_item'    => 'Subido a esta noticia',
			'featured_image'           => 'Foto destacada',
			'set_featured_image'       => 'Elegir la foto destacada',
			'remove_featured_image'    => 'Quitar la foto destacada',
			'use_featured_image'       => 'Usar como foto destacada',
			'filter_items_list'        => 'Filtrar la lista de noticias',
			'filter_by_date'           => 'Filtrar por fecha',
			'items_list_navigation'    => 'Navegación de la lista de noticias',
			'items_list'               => 'Lista de noticias',
			'item_published'           => 'Noticia publicada.',
			'item_published_privately' => 'Noticia publicada en privado.',
			'item_reverted_to_draft'   => 'La noticia ha vuelto a borrador.',
			'item_trashed'             => 'Noticia movida a la papelera.',
			'item_scheduled'           => 'Noticia programada.',
			'item_updated'             => 'Noticia actualizada.',
			'item_link'                => 'Enlace a la noticia',
			'item_link_description'    => 'Un enlace a una noticia.',
		);
		foreach ( $news as $key => $value ) {
			$labels->$key = $value;
		}
		return $labels;
	}
);

add_filter(
	'register_post_type_args',
	static function ( $args, $type ) {
		if ( 'post' === $type ) {
			$args['menu_icon'] = 'dashicons-megaphone';
		}
		return $args;
	},
	10,
	2
);

/* list screen messages ("1 noticia movida a la papelera") */
add_filter(
	'bulk_post_updated_messages',
	static function ( $messages, $counts ) {
		$n                = static fn( string $key ) => (int) ( $counts[ $key ] ?? 0 );
		$messages['post'] = array(
			'updated'   => mats_admin_plural( $n( 'updated' ), '%d noticia actualizada.', '%d noticias actualizadas.' ),
			'locked'    => mats_admin_plural( $n( 'locked' ), '%d noticia no se ha actualizado: alguien la está editando.', '%d noticias no se han actualizado: alguien las está editando.' ),
			'deleted'   => mats_admin_plural( $n( 'deleted' ), '%d noticia borrada para siempre.', '%d noticias borradas para siempre.' ),
			'trashed'   => mats_admin_plural( $n( 'trashed' ), '%d noticia movida a la papelera.', '%d noticias movidas a la papelera.' ),
			'untrashed' => mats_admin_plural( $n( 'untrashed' ), '%d noticia recuperada de la papelera.', '%d noticias recuperadas de la papelera.' ),
		);
		return $messages;
	},
	10,
	2
);

/* ═══════════════════════════════════════════════════════════════════════
 * Admin menu
 * ═════════════════════════════════════════════════════════════════════ */

/** WPForms screens that only advertise paid features or other products. */
function mats_admin_wpforms_promo_slugs(): array {
	return array( 'wpforms-addons', 'wpforms-analytics', 'wpforms-smtp', 'wpforms-about', 'wpforms-community', 'wpforms-payments', 'wpforms-entries', 'wpforms-templates', 'wpforms-page-upgrade', 'wpforms-upgrade' );
}

add_action(
	'admin_menu',
	static function () {
		/* the guide, at the bottom of the menu for everyone who writes */
		add_menu_page(
			'Guía de uso',
			'Guía de uso',
			'edit_posts',
			MATS_GUIDE_PAGE,
			'mats_admin_render_guide',
			'dashicons-sos',
			100
		);
	}
);

add_action(
	'admin_menu',
	static function () {
		global $submenu;

		remove_menu_page( 'edit-comments.php' );
		remove_submenu_page( 'options-general.php', 'options-discussion.php' );

		/* WPForms (admins): keep the forms, drop the upsell pages */
		foreach ( (array) ( $submenu['wpforms-overview'] ?? array() ) as $i => $item ) {
			$slug = (string) ( $item[2] ?? '' );
			if ( in_array( $slug, mats_admin_wpforms_promo_slugs(), true ) || str_starts_with( $slug, 'http' ) || false !== stripos( $slug, 'upgrade' ) ) {
				unset( $submenu['wpforms-overview'][ $i ] );
			}
		}

		if ( mats_admin_is_admin() ) {
			return;
		}
		foreach ( array( 'tools.php', 'themes.php', 'plugins.php', 'options-general.php', 'users.php', 'mlang', 'wpforms-overview', 'edit.php?post_type=wp_block', 'site-editor.php' ) as $slug ) {
			remove_menu_page( $slug );
		}
		/* any other Polylang screen ("Traducciones"…) */
		global $menu;
		foreach ( (array) $menu as $item ) {
			$slug = (string) ( $item[2] ?? '' );
			if ( str_starts_with( $slug, 'mlang' ) ) {
				remove_menu_page( $slug );
			}
		}
	},
	999
);

/* screens editors have no reason to open: back to the dashboard */
foreach ( array( 'load-tools.php', 'load-import.php', 'load-export.php', 'load-site-editor.php' ) as $mats_admin_hook ) {
	add_action(
		$mats_admin_hook,
		static function () {
			if ( ! mats_admin_is_admin() ) {
				wp_safe_redirect( admin_url() );
				exit;
			}
		}
	);
}
unset( $mats_admin_hook );

add_action(
	'load-edit.php',
	static function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only redirects.
		$type = isset( $_GET['post_type'] ) && is_scalar( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
		if ( 'wp_block' === $type && ! mats_admin_is_admin() ) {
			wp_safe_redirect( admin_url() );
			exit;
		}
	}
);

/** Top-level slug that holds "Ajustes de la web" ('' when the screen does not exist yet). */
function mats_admin_settings_menu_slug(): string {
	global $menu, $submenu;
	$slug = mats_admin_settings_slug();
	foreach ( (array) $menu as $item ) {
		if ( isset( $item[2] ) && $slug === $item[2] ) {
			return $slug;
		}
	}
	foreach ( (array) $submenu as $parent => $children ) {
		foreach ( (array) $children as $item ) {
			if ( isset( $item[2] ) && $slug === $item[2] ) {
				return (string) $parent;
			}
		}
	}
	return '';
}

add_filter( 'custom_menu_order', '__return_true' );
add_filter(
	'menu_order',
	static function ( $order ) {
		if ( ! is_array( $order ) ) {
			return $order;
		}
		$head = array(
			'index.php',
			'separator1',
			defined( 'MATS_LEADS_PAGE' ) ? MATS_LEADS_PAGE : 'mats-leads',
			'edit.php',
			'edit.php?post_type=page',
			'edit.php?post_type=mats_team',
			'edit.php?post_type=mats_application',
			'edit.php?post_type=mats_publication',
			'edit.php?post_type=mats_expert',
			'upload.php',
		);
		$settings = mats_admin_settings_menu_slug();
		if ( $settings ) {
			$head[] = $settings;
		}
		$head[] = 'separator2';
		$tail   = array( 'separator-last', 'profile.php', MATS_GUIDE_PAGE );
		$rest   = array_values( array_diff( $order, $head, $tail ) );
		$sorted = array_merge( $head, $rest, $tail );
		return array_values( array_filter( $sorted, static fn( $slug ) => in_array( $slug, $order, true ) ) );
	},
	99
);

/* ═══════════════════════════════════════════════════════════════════════
 * Admin bar
 * ═════════════════════════════════════════════════════════════════════ */

add_action(
	'admin_bar_menu',
	static function ( WP_Admin_Bar $bar ) {
		foreach ( array( 'wp-logo', 'comments', 'customize', 'search', 'wpforms-menu', 'new-user', 'new-media', 'new-wpforms', 'themes', 'widgets', 'menus', 'appearance' ) as $id ) {
			$bar->remove_node( $id );
		}
		/* "+ Nueva": only a news article or a page */
		foreach ( (array) $bar->get_nodes() as $node ) {
			if ( 'new-content' === $node->parent && ! in_array( $node->id, array( 'new-post', 'new-page' ), true ) ) {
				$bar->remove_node( $node->id );
			}
		}
		if ( ! mats_admin_is_admin() ) {
			$bar->remove_node( 'updates' );
		}
	},
	999
);

/* ═══════════════════════════════════════════════════════════════════════
 * Escritorio (dashboard)
 * ═════════════════════════════════════════════════════════════════════ */

add_action(
	'wp_dashboard_setup',
	static function () {
		global $wp_meta_boxes;
		remove_action( 'welcome_panel', 'wp_welcome_panel' );

		/* WordPress news, quick draft, at a glance, activity: never */
		$noise = array( 'dashboard_primary', 'dashboard_secondary', 'dashboard_quick_press', 'dashboard_recent_drafts', 'dashboard_right_now', 'dashboard_activity', 'dashboard_recent_comments', 'dashboard_incoming_links', 'dashboard_plugins' );
		$admin = mats_admin_is_admin();
		foreach ( (array) ( $wp_meta_boxes['dashboard'] ?? array() ) as $context => $priorities ) {
			foreach ( (array) $priorities as $priority => $boxes ) {
				foreach ( array_keys( (array) $boxes ) as $id ) {
					$id   = (string) $id;
					$ours = str_starts_with( $id, 'mats_' );
					$drop = in_array( $id, $noise, true ) || str_starts_with( $id, 'wpforms' ) || str_starts_with( $id, 'pll_' );
					/* editors only keep the MatSurfer widgets (no site health, PHP or plugin boxes) */
					if ( ! $admin && ! $ours ) {
						$drop = true;
					}
					if ( $drop ) {
						remove_meta_box( $id, 'dashboard', $context );
					}
				}
			}
		}

		if ( current_user_can( 'edit_posts' ) && post_type_exists( 'post' ) ) {
			wp_add_dashboard_widget( 'mats_news_widget', 'Noticias recientes', 'mats_admin_news_widget', null, null, 'side', 'high' );
		}
	},
	999
);

/* WPForms: no reports widget on the dashboard */
add_filter( 'wpforms_admin_dashboardwidget', '__return_false' );

/** The tiles of the welcome panel. */
function mats_admin_tiles(): array {
	$lang  = mats_admin_user_lang();
	$other = 'es' === $lang ? 'en' : 'es';
	$names = array(
		'en' => 'inglés',
		'es' => 'español',
	);
	$tiles = array();

	/* news */
	if ( current_user_can( 'edit_posts' ) ) {
		$counts = wp_count_posts( 'post' );
		$meta   = array();
		if ( ! empty( $counts->publish ) ) {
			$meta[] = mats_admin_plural( (int) $counts->publish, '%d publicada', '%d publicadas' );
		}
		if ( ! empty( $counts->draft ) ) {
			$meta[] = mats_admin_plural( (int) $counts->draft, '%d borrador', '%d borradores' );
		}
		$new_url = admin_url( 'post-new.php' );
		if ( mats_polylang_active() ) {
			$new_url = add_query_arg( 'new_lang', $lang, $new_url );
		}
		$tiles['news-new'] = array(
			'icon'    => 'edit',
			'title'   => 'Escribir una noticia',
			'text'    => 'Escribe una noticia nueva, ponle una foto y publícala cuando esté lista.',
			'url'     => $new_url,
			'meta'    => implode( ' · ', $meta ),
			'primary' => true,
		);
	}

	/* home page in the editor's language (+ the other language) */
	if ( current_user_can( 'edit_pages' ) ) {
		$home     = mats_page_id( 'home', $lang );
		$home_alt = mats_page_id( 'home', $other );
		$url      = $home ? (string) get_edit_post_link( $home, 'raw' ) : '';
		$alt_url  = $home_alt ? (string) get_edit_post_link( $home_alt, 'raw' ) : '';
		if ( '' === $url && '' !== $alt_url ) {
			list( $url, $alt_url, $lang, $other ) = array( $alt_url, '', $other, $lang );
		}
		if ( $url ) {
			$tiles['home'] = array(
				'icon'    => 'admin-home',
				'title'   => 'Editar la página de inicio',
				'text'    => 'Cambia sus textos y fotos haciendo clic sobre ellos (versión en ' . $names[ $lang ] . ').',
				'url'     => $url,
				'alt'     => $alt_url ? array( 'url' => $alt_url, 'label' => 'Versión en ' . $names[ $other ] ) : array(),
				'primary' => true,
			);
		}
		$tiles['pages'] = array(
			'icon'  => 'admin-page',
			'title' => 'Páginas',
			'text'  => 'Producto, Aplicaciones, Equipo, Contacto, textos legales… en inglés y en español.',
			'url'   => admin_url( 'edit.php?post_type=page' ),
		);
	}

	/* leads */
	$lead_cap = defined( 'MATS_LEAD_CAP' ) ? MATS_LEAD_CAP : 'edit_posts';
	if ( ( function_exists( 'mats_leads_url' ) || post_type_exists( 'mats_lead' ) ) && current_user_can( $lead_cap ) ) {
		$new            = function_exists( 'mats_leads_count' ) ? (int) mats_leads_count( 'new' ) : 0;
		$tiles['leads'] = array(
			'icon'  => 'email-alt',
			'title' => 'Contactos recibidos',
			'text'  => 'Los mensajes que llegan por el formulario de la web: léelos, respóndelos y márcalos como atendidos.',
			'url'   => function_exists( 'mats_leads_url' ) ? mats_leads_url() : admin_url( 'edit.php?post_type=mats_lead' ),
			'badge' => $new ? mats_admin_plural( $new, '%d nuevo', '%d nuevos' ) : '',
			'meta'  => $new ? '' : 'Todo al día',
		);
	}

	/* structured content */
	$types = array(
		'mats_team'        => array( 'groups', 'Equipo', 'Las personas del equipo: foto, cargo y biografía en los dos idiomas.' ),
		'mats_application' => array( 'screenoptions', 'Aplicaciones', 'Los campos de aplicación del dispositivo, con su reto, uso y beneficio.' ),
		'mats_publication' => array( 'media-document', 'Publicaciones', 'Los artículos científicos que aparecen en Casos de estudio.' ),
		'mats_expert'      => array( 'format-quote', 'Valoraciones', 'Las citas de investigadores que han probado el dispositivo.' ),
	);
	foreach ( $types as $type => $def ) {
		$object = get_post_type_object( $type );
		if ( ! $object || ! current_user_can( $object->cap->edit_posts ) ) {
			continue;
		}
		$counts = wp_count_posts( $type );
		$meta   = array();
		if ( ! empty( $counts->publish ) ) {
			$meta[] = sprintf( '%d en la web', (int) $counts->publish );
		}
		if ( ! empty( $counts->draft ) ) {
			$meta[] = mats_admin_plural( (int) $counts->draft, '%d en borrador', '%d en borrador' );
		}
		$tiles[ $type ] = array(
			'icon'  => $def[0],
			'title' => $def[1],
			'text'  => $def[2],
			'url'   => admin_url( 'edit.php?post_type=' . $type ),
			'meta'  => implode( ' · ', $meta ),
		);
	}

	if ( current_user_can( 'upload_files' ) ) {
		$tiles['media'] = array(
			'icon'  => 'format-gallery',
			'title' => 'Fotos y archivos',
			'text'  => 'La biblioteca con todas las fotos, vídeos y PDF de la web. Sube aquí los nuevos.',
			'url'   => admin_url( 'upload.php' ),
		);
	}

	$settings = mats_admin_settings_slug();
	if ( mats_admin_menu_has( $settings ) ) {
		$tiles['settings'] = array(
			'icon'  => 'admin-settings',
			'title' => 'Ajustes de la web',
			'text'  => 'Menú de arriba, pie de página y el email al que llegan los contactos.',
			'url'   => mats_admin_page_url( $settings ),
		);
	}

	$tiles['guide'] = array(
		'icon'  => 'sos',
		'title' => 'Guía de uso',
		'text'  => 'Paso a paso: cómo publicar, cambiar textos y fotos, y a quién pedir ayuda.',
		'url'   => mats_admin_guide_url(),
	);

	return (array) apply_filters( 'mats_admin_tiles', $tiles );
}

/** The full-width welcome panel at the top of the dashboard. */
function mats_admin_welcome_panel(): void {
	$user  = wp_get_current_user();
	$name  = $user && $user->exists() ? ( $user->first_name ? $user->first_name : $user->display_name ) : '';
	$tiles = mats_admin_tiles();
	$site  = function_exists( 'mats_home_url' ) ? mats_home_url( mats_admin_user_lang() ) : home_url( '/' );
	?>
	<section class="mats-welcome" aria-labelledby="mats-welcome-title">
		<div class="mats-welcome__head">
			<img class="mats-welcome__mark" src="<?php echo esc_url( mats_asset( 'brand/isotipo.svg' ) ); ?>" width="31" height="50" alt="" />
			<div class="mats-welcome__intro">
				<h1 id="mats-welcome-title" class="mats-welcome__title">Bienvenido/a a la web de MatSurfer</h1>
				<p class="mats-welcome__lead">
					<?php if ( $name ) : ?>
						<?php echo esc_html( sprintf( 'Hola, %s.', $name ) ); ?>
					<?php endif; ?>
					Desde aquí publicas noticias, cambias los textos y las fotos de la web y lees los mensajes que llegan por el formulario. ¿Qué quieres hacer hoy?
				</p>
			</div>
			<a class="button mats-welcome__site" href="<?php echo esc_url( $site ); ?>" target="_blank" rel="noopener">Ver la web <span aria-hidden="true">↗</span><span class="screen-reader-text"> (se abre en una pestaña nueva)</span></a>
		</div>
		<ul class="mats-tiles" role="list">
			<?php foreach ( $tiles as $key => $tile ) : ?>
				<li class="mats-tile mats-tile--<?php echo esc_attr( sanitize_html_class( (string) $key ) ); ?><?php echo ! empty( $tile['primary'] ) ? ' mats-tile--primary' : ''; ?>">
					<a class="mats-tile__link" href="<?php echo esc_url( (string) $tile['url'] ); ?>">
						<span class="mats-tile__icon dashicons dashicons-<?php echo esc_attr( (string) $tile['icon'] ); ?>" aria-hidden="true"></span>
						<span class="mats-tile__title">
							<?php echo esc_html( (string) $tile['title'] ); ?>
							<?php if ( ! empty( $tile['badge'] ) ) : ?>
								<span class="mats-tile__badge"><?php echo esc_html( (string) $tile['badge'] ); ?></span>
							<?php endif; ?>
						</span>
						<span class="mats-tile__text"><?php echo esc_html( (string) $tile['text'] ); ?></span>
						<?php if ( ! empty( $tile['meta'] ) ) : ?>
							<span class="mats-tile__meta"><?php echo esc_html( (string) $tile['meta'] ); ?></span>
						<?php endif; ?>
					</a>
					<?php if ( ! empty( $tile['alt']['url'] ) ) : ?>
						<a class="mats-tile__alt" href="<?php echo esc_url( (string) $tile['alt']['url'] ); ?>"><?php echo esc_html( (string) $tile['alt']['label'] ); ?> <span aria-hidden="true">→</span></a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
	<?php
}

/*
 * The panel is printed before the dashboard's widget columns (the core
 * welcome panel slot only exists for administrators), so it is always
 * full width and cannot be hidden or dragged away.
 */
add_action(
	'admin_notices',
	static function () {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'dashboard' === $screen->id ) {
			mats_admin_welcome_panel();
		}
	},
	1
);

/** Spanish status label of a post. */
function mats_admin_status_label( string $status ): string {
	$labels = array(
		'publish' => 'Publicada',
		'draft'   => 'Borrador',
		'pending' => 'Pendiente de revisión',
		'future'  => 'Programada',
		'private' => 'Privada',
	);
	return $labels[ $status ] ?? $status;
}

/** Dashboard widget: the latest news articles, with status, language and missing translations. */
function mats_admin_news_widget(): void {
	$args = array(
		'post_type'      => 'post',
		'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
		'posts_per_page' => 6,
		'orderby'        => 'modified',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	);
	if ( mats_polylang_active() ) {
		$args['lang'] = ''; // every language
	}
	$posts = get_posts( $args );
	echo '<div class="mats-newsw">';
	if ( ! $posts ) {
		echo '<p class="mats-newsw__empty">' . esc_html( 'Todavía no hay noticias. Cuando escribas la primera aparecerá aquí.' ) . '</p>';
	} else {
		echo '<ul class="mats-newsw__list">';
		foreach ( $posts as $post ) {
			$lang    = mats_post_lang( $post->ID );
			$missing = array();
			if ( function_exists( 'pll_get_post_translations' ) ) {
				$translations = pll_get_post_translations( $post->ID );
				foreach ( mats_langs() as $code ) {
					if ( empty( $translations[ $code ] ) ) {
						$missing[] = strtoupper( $code );
					}
				}
			}
			$title = trim( wp_strip_all_tags( get_the_title( $post ) ) );
			$edit  = get_edit_post_link( $post->ID, 'raw' );
			$date  = function_exists( 'mats_blog_format_date' ) ? mats_blog_format_date( (int) get_post_modified_time( 'U', false, $post ), 'short', 'es' ) : get_the_modified_date( '', $post );
			printf(
				'<li class="mats-newsw__item"><span class="mats-newsw__lang">%1$s</span><span class="mats-newsw__main">%2$s<span class="mats-newsw__meta"><span class="mats-newsw__status mats-newsw__status--%3$s">%4$s</span> · %5$s%6$s</span></span></li>',
				esc_html( strtoupper( $lang ) ),
				$edit ? '<a class="mats-newsw__title" href="' . esc_url( $edit ) . '">' . esc_html( '' !== $title ? $title : '(sin título)' ) . '</a>' : '<span class="mats-newsw__title">' . esc_html( '' !== $title ? $title : '(sin título)' ) . '</span>',
				esc_attr( $post->post_status ),
				esc_html( mats_admin_status_label( $post->post_status ) ),
				esc_html( 'Cambiada el ' . $date ),
				$missing ? ' · <span class="mats-newsw__missing">' . esc_html( 'Falta la versión en ' . implode( ' y ', array_map( static fn( $c ) => 'ES' === $c ? 'español' : 'inglés', $missing ) ) ) . '</span>' : ''
			);
		}
		echo '</ul>';
	}
	printf(
		'<p class="mats-newsw__actions"><a class="button button-primary" href="%1$s">%2$s</a> <a class="button" href="%3$s">%4$s</a></p>',
		esc_url( admin_url( 'post-new.php' ) ),
		esc_html( 'Escribir una noticia' ),
		esc_url( admin_url( 'edit.php' ) ),
		esc_html( 'Ver todas las noticias' )
	);
	echo '</div>';
}

/* ═══════════════════════════════════════════════════════════════════════
 * Guía de uso
 * ═════════════════════════════════════════════════════════════════════ */

/** Guide content (inc/data/guide.php), filterable. */
function mats_admin_guide_data(): array {
	$file = MATS_DIR . '/inc/data/guide.php';
	$data = is_readable( $file ) ? (array) require $file : array();
	return (array) apply_filters( 'mats_guide_data', $data );
}

/** Values of the {placeholders} used in the guide. */
function mats_admin_guide_vars(): array {
	$settings = mats_admin_settings_slug();
	return array(
		'contact_email' => function_exists( 'mats_contact_email' ) ? mats_contact_email() : (string) get_option( 'admin_email' ),
		'admin_email'   => (string) get_option( 'admin_email' ),
		'site_url'      => function_exists( 'mats_home_url' ) ? mats_home_url( 'en' ) : home_url( '/' ),
		'site_url_es'   => function_exists( 'mats_home_url' ) ? mats_home_url( 'es' ) : home_url( '/es/' ),
		'login_url'     => wp_login_url(),
		'max_upload'    => size_format( wp_max_upload_size() ),
		'max_image'     => (string) mats_admin_max_image_mb() . ' MB',
		'settings_url'  => mats_admin_page_url( $settings ),
		'user'          => wp_get_current_user()->display_name,
	);
}

/**
 * Formats one line of guide text (trusted, shipped with the theme):
 * escapes it, fills {placeholders}, **bold**, `keys` and
 * [label](target) links where target is admin:screen.php?…, #section,
 * site:/path, mailto:… or https://….
 */
function mats_admin_guide_format( string $text ): string {
	$vars = mats_admin_guide_vars();
	$text = preg_replace_callback(
		'/\{([a-z_]+)\}/',
		static fn( $m ) => array_key_exists( $m[1], $vars ) ? (string) $vars[ $m[1] ] : $m[0],
		$text
	);
	$html = esc_html( (string) $text );
	$html = preg_replace( '/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $html );
	$html = preg_replace( '/`([^`]+)`/u', '<kbd>$1</kbd>', $html );
	$html = preg_replace_callback(
		'/\[([^\]]+)\]\(([^)\s]+)\)/u',
		static function ( $m ) {
			$target = html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' );
			$attrs  = '';
			if ( str_starts_with( $target, 'admin:' ) ) {
				$href = admin_url( substr( $target, 6 ) );
			} elseif ( str_starts_with( $target, 'site:' ) ) {
				$href  = home_url( substr( $target, 5 ) );
				$attrs = ' target="_blank" rel="noopener"';
			} elseif ( str_starts_with( $target, '#' ) || str_starts_with( $target, 'mailto:' ) ) {
				$href = $target;
			} elseif ( preg_match( '#^https?://#', $target ) ) {
				$href  = $target;
				$attrs = ' target="_blank" rel="noopener"';
			} else {
				return $m[1];
			}
			return '<a href="' . esc_url( $href ) . '"' . $attrs . '>' . $m[1] . '</a>';
		},
		$html
	);
	return (string) $html;
}

/** One content block of a guide section. */
function mats_admin_guide_block( array $block ): string {
	$type = (string) ( $block['type'] ?? 'p' );
	$fmt  = 'mats_admin_guide_format';
	switch ( $type ) {
		case 'h3':
			return '<h3 class="mats-guide__h3">' . $fmt( (string) $block['text'] ) . '</h3>';
		case 'steps':
		case 'list':
			$tag = 'steps' === $type ? 'ol' : 'ul';
			$out = '<' . $tag . ' class="mats-guide__' . $type . '">';
			foreach ( (array) $block['items'] as $item ) {
				$out .= '<li>' . $fmt( (string) $item ) . '</li>';
			}
			return $out . '</' . $tag . '>';
		case 'tip':
		case 'warn':
		case 'note':
			$labels = array(
				'tip'  => 'Consejo',
				'warn' => 'Importante',
				'note' => 'Bueno saberlo',
			);
			$icons  = array(
				'tip'  => 'lightbulb',
				'warn' => 'warning',
				'note' => 'info-outline',
			);
			$label  = (string) ( $block['label'] ?? $labels[ $type ] );
			return '<div class="mats-guide__callout mats-guide__callout--' . $type . '"><span class="dashicons dashicons-' . $icons[ $type ] . '" aria-hidden="true"></span><p><strong>' . esc_html( $label ) . ':</strong> ' . $fmt( (string) $block['text'] ) . '</p></div>';
		case 'table':
			$out = '<div class="mats-guide__table-wrap"><table class="mats-guide__table"><thead><tr>';
			foreach ( (array) ( $block['head'] ?? array() ) as $cell ) {
				$out .= '<th scope="col">' . $fmt( (string) $cell ) . '</th>';
			}
			$out .= '</tr></thead><tbody>';
			foreach ( (array) ( $block['rows'] ?? array() ) as $row ) {
				$out .= '<tr>';
				foreach ( (array) $row as $i => $cell ) {
					$out .= 0 === $i ? '<th scope="row">' . $fmt( (string) $cell ) . '</th>' : '<td>' . $fmt( (string) $cell ) . '</td>';
				}
				$out .= '</tr>';
			}
			return $out . '</tbody></table></div>';
		case 'links':
			$out = '<p class="mats-guide__links">';
			foreach ( (array) $block['items'] as $link ) {
				$href = (string) ( $link['href'] ?? '' );
				if ( str_starts_with( $href, 'admin:' ) ) {
					$href = admin_url( substr( $href, 6 ) );
				} elseif ( 'settings' === $href ) {
					$slug = mats_admin_settings_slug();
					if ( ! mats_admin_menu_has( $slug ) ) {
						continue;
					}
					$href = mats_admin_page_url( $slug );
				}
				$out .= '<a class="button" href="' . esc_url( $href ) . '">' . esc_html( (string) ( $link['label'] ?? '' ) ) . '</a> ';
			}
			return $out . '</p>';
		case 'p':
		default:
			return '<p>' . $fmt( (string) ( $block['text'] ?? '' ) ) . '</p>';
	}
}

/** The "Guía de uso" screen. */
function mats_admin_render_guide(): void {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html( 'No tienes permiso para ver esta página.' ), '', array( 'response' => 403 ) );
	}
	$data     = mats_admin_guide_data();
	$admin    = mats_admin_is_admin();
	$sections = array_values(
		array_filter(
			(array) ( $data['sections'] ?? array() ),
			static fn( $s ) => $admin || 'admin' !== ( $s['audience'] ?? 'all' )
		)
	);
	?>
	<div class="wrap mats-guide">
		<header class="mats-guide__hero">
			<img class="mats-guide__logo" src="<?php echo esc_url( mats_asset( 'brand/logotipo.svg' ) ); ?>" width="196" height="35" alt="MatSurfer" />
			<h1 class="mats-guide__title"><?php echo esc_html( (string) ( $data['title'] ?? 'Guía de uso' ) ); ?></h1>
			<?php foreach ( (array) ( $data['intro'] ?? array() ) as $line ) : ?>
				<p class="mats-guide__intro"><?php echo mats_admin_guide_format( (string) $line ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the formatter ?></p>
			<?php endforeach; ?>
			<p class="mats-guide__tools">
				<button type="button" class="button" data-mats-print><span class="dashicons dashicons-printer" aria-hidden="true"></span> Imprimir o guardar en PDF</button>
				<a class="button" href="<?php echo esc_url( admin_url() ); ?>">Volver al escritorio</a>
			</p>
		</header>

		<nav class="mats-guide__toc" aria-labelledby="mats-guide-toc-title">
			<h2 id="mats-guide-toc-title" class="mats-guide__toc-title">¿Qué quieres hacer?</h2>
			<ol class="mats-guide__toc-list">
				<?php foreach ( $sections as $i => $section ) : ?>
					<li>
						<a href="#<?php echo esc_attr( (string) $section['id'] ); ?>">
							<span class="mats-guide__num" aria-hidden="true"><?php echo esc_html( (string) ( $i + 1 ) ); ?></span>
							<span class="mats-guide__toc-text">
								<strong><?php echo esc_html( (string) $section['title'] ); ?></strong>
								<?php if ( ! empty( $section['summary'] ) ) : ?>
									<span><?php echo esc_html( (string) $section['summary'] ); ?></span>
								<?php endif; ?>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ol>
		</nav>

		<?php foreach ( $sections as $i => $section ) : ?>
			<section class="mats-guide__section" id="<?php echo esc_attr( (string) $section['id'] ); ?>" aria-labelledby="<?php echo esc_attr( $section['id'] . '-title' ); ?>">
				<header class="mats-guide__section-head">
					<span class="mats-guide__num" aria-hidden="true"><?php echo esc_html( (string) ( $i + 1 ) ); ?></span>
					<span class="mats-guide__section-icon dashicons dashicons-<?php echo esc_attr( (string) ( $section['icon'] ?? 'info-outline' ) ); ?>" aria-hidden="true"></span>
					<h2 class="mats-guide__h2" id="<?php echo esc_attr( $section['id'] . '-title' ); ?>"><?php echo esc_html( (string) $section['title'] ); ?></h2>
					<?php if ( 'admin' === ( $section['audience'] ?? 'all' ) ) : ?>
						<span class="mats-guide__badge">Solo administradores</span>
					<?php endif; ?>
				</header>
				<div class="mats-guide__body">
					<?php
					foreach ( (array) ( $section['content'] ?? array() ) as $block ) {
						echo mats_admin_guide_block( (array) $block ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the formatter
					}
					?>
				</div>
				<p class="mats-guide__top"><a href="#mats-guide-toc-title">↑ Volver al índice</a></p>
			</section>
		<?php endforeach; ?>

		<?php if ( ! empty( $data['footer'] ) ) : ?>
			<p class="mats-guide__footer"><?php echo mats_admin_guide_format( (string) $data['footer'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the formatter ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/* ═══════════════════════════════════════════════════════════════════════
 * Contextual help (the "Ayuda" tab of list screens) → the guide
 * ═════════════════════════════════════════════════════════════════════ */

add_action(
	'current_screen',
	static function ( $screen ) {
		$map = array(
			'edit-post'             => array( 'noticias', 'Noticias', 'Para escribir una noticia pulsa «Escribir una noticia». Para traducirla, usa el «+» de la columna del otro idioma.' ),
			'edit-page'             => array( 'textos', 'Páginas', 'Abre una página para cambiar sus textos y fotos. Las páginas principales están protegidas: no se pueden borrar ni cambiar de dirección.' ),
			'upload'                => array( 'consejos-fotos', 'Fotos y archivos', 'Sube fotos JPG de unos 2000 px de ancho y menos de 1 MB, y escribe siempre su «Texto alternativo».' ),
			'edit-mats_team'        => array( 'fichas', 'Equipo', 'Cada persona tiene sus textos en inglés y en español en la misma ficha. Solo se ven en la web las publicadas.' ),
			'edit-mats_application' => array( 'fichas', 'Aplicaciones', 'Cada aplicación tiene sus textos en inglés y en español en la misma ficha. El campo «Orden» decide la posición.' ),
			'edit-mats_publication' => array( 'fichas', 'Publicaciones', 'Las publicaciones se ordenan solas: primero las más recientes.' ),
			'edit-mats_expert'      => array( 'fichas', 'Valoraciones', 'Cada valoración tiene la cita en inglés y en español. Solo se ven en la web las publicadas.' ),
		);
		if ( ! $screen instanceof WP_Screen || ! isset( $map[ $screen->id ] ) ) {
			return;
		}
		list( $anchor, $title, $text ) = $map[ $screen->id ];
		if ( ! mats_admin_is_admin() ) {
			$screen->remove_help_tabs();
			$screen->set_help_sidebar( '' );
		}
		$screen->add_help_tab(
			array(
				'id'       => 'mats-help',
				'title'    => 'Ayuda: ' . $title,
				'content'  => '<p>' . esc_html( $text ) . '</p><p><a class="button button-primary" href="' . esc_url( mats_admin_guide_url( $anchor ) ) . '">Abrir la Guía de uso</a></p>',
				'priority' => 1,
			)
		);
	},
	20
);

/* ═══════════════════════════════════════════════════════════════════════
 * Small things that keep the admin calm
 * ═════════════════════════════════════════════════════════════════════ */

add_filter(
	'admin_footer_text',
	static function () {
		return 'Web de MatSurfer · ICMAB-CSIC. ¿Dudas? Abre la <a href="' . esc_url( mats_admin_guide_url() ) . '">Guía de uso</a>.';
	},
	20
);
add_filter( 'update_footer', static fn( $text ) => mats_admin_is_admin() ? $text : '', 20 );

/* "Please notify the site administrator" banners are not for editors */
add_action(
	'admin_init',
	static function () {
		if ( mats_admin_is_admin() ) {
			return;
		}
		remove_action( 'admin_notices', 'update_nag', 3 );
		remove_action( 'network_admin_notices', 'update_nag', 3 );
		remove_action( 'admin_notices', 'maintenance_nag', 10 );
		remove_action( 'admin_notices', 'site_admin_notice' );
	}
);

/* the visual editor can never be switched off by mistake */
add_filter( 'user_can_richedit', static fn( $can ) => mats_admin_is_admin() ? $can : true, 20 );

/* Polylang: no review / upsell / setup-wizard notices (the MatSurfer setup configures it); editors: none at all */
add_filter(
	'pll_can_display_notice',
	static function ( $display, $notice ) {
		if ( ! mats_admin_is_admin() || in_array( $notice, array( 'review', 'pllwc', 'lingotek', 'wizard' ), true ) ) {
			return false;
		}
		return $display;
	},
	20,
	2
);

/* body classes the stylesheet uses (role, MatSurfer screens) */
add_filter(
	'admin_body_class',
	static function ( $classes ) {
		$classes .= mats_admin_is_admin() ? ' mats-is-admin' : ' mats-is-editor';
		return $classes;
	}
);

/* ═══════════════════════════════════════════════════════════════════════
 * Assets
 * ═════════════════════════════════════════════════════════════════════ */

add_action(
	'admin_enqueue_scripts',
	static function () {
		wp_enqueue_style( 'matsurfer-admin', MATS_URI . '/assets/css/admin.css', array( 'dashicons' ), mats_ver( 'assets/css/admin.css' ) );
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && $screen->is_block_editor() ) {
			return; // the block editor gets the editor flavour (inc/modules/editor.php)
		}
		wp_enqueue_script( 'matsurfer-admin', MATS_URI . '/assets/js/admin.js', array(), mats_ver( 'assets/js/admin.js' ), true );
	}
);

/* ═══════════════════════════════════════════════════════════════════════
 * Login screen
 * ═════════════════════════════════════════════════════════════════════ */

add_action(
	'login_enqueue_scripts',
	static function () {
		wp_enqueue_style( 'matsurfer-login', MATS_URI . '/assets/css/admin.css', array(), mats_ver( 'assets/css/admin.css' ) );
	}
);
add_filter( 'login_headerurl', static fn() => function_exists( 'mats_home_url' ) ? mats_home_url() : home_url( '/' ) );
add_filter( 'login_headertext', static fn() => 'MatSurfer · ICMAB-CSIC' );
add_filter( 'login_body_class', static fn( $classes ) => array_merge( (array) $classes, array( 'mats-login' ) ) );

/* wrong user / wrong password messages and the login lock: inc/modules/security.php */

/* ═══════════════════════════════════════════════════════════════════════
 * Media
 * ═════════════════════════════════════════════════════════════════════ */

/** Heaviest photo accepted, in MB (bigger photos are refused with an explanation). */
function mats_admin_max_image_mb(): int {
	return max( 1, (int) apply_filters( 'mats_admin_max_image_mb', 20 ) );
}

add_filter( 'big_image_size_threshold', static fn() => 2560, 20 );
add_filter( 'jpeg_quality', static fn() => 82, 20 );
add_filter(
	'wp_editor_set_quality',
	static fn( $quality, $mime = '' ) => in_array( $mime, array( 'image/jpeg', 'image/jpg' ), true ) ? 82 : $quality,
	20,
	2
);

/* no SVG (they can carry scripts) */
add_filter(
	'upload_mimes',
	static function ( $mimes ) {
		unset( $mimes['svg'], $mimes['svgz'] );
		return $mimes;
	},
	99
);

add_filter(
	'wp_handle_upload_prefilter',
	static function ( $file ) {
		if ( ! empty( $file['error'] ) ) {
			return $file; // already refused (inc/modules/security.php)
		}
		$name = strtolower( (string) ( $file['name'] ?? '' ) );
		if ( preg_match( '/\.svgz?$/', $name ) ) {
			$file['error'] = 'Por seguridad no se pueden subir imágenes SVG. Usa una foto JPG o PNG.';
			return $file;
		}
		$is_image = (bool) preg_match( '/\.(jpe?g|png|gif|webp|avif|heic)$/', $name );
		$limit    = mats_admin_max_image_mb();
		if ( $is_image && (int) ( $file['size'] ?? 0 ) > $limit * MB_IN_BYTES ) {
			$file['error'] = sprintf(
				'Esta foto pesa %1$s y es demasiado grande (máximo %2$d MB). Redúcela antes de subirla: con unos 2000 píxeles de ancho es suficiente.',
				size_format( (int) $file['size'], 1 ),
				$limit
			);
		}
		return $file;
	}
);

/** Upload advice, shown in the uploader (Medios → Añadir y the "Subir archivos" tab). */
function mats_admin_upload_tip(): void {
	printf(
		'<p class="mats-upload-tip"><span class="dashicons dashicons-lightbulb" aria-hidden="true"></span> <span><strong>%1$s</strong> %2$s</span></p>',
		esc_html( 'Antes de subir una foto:' ),
		esc_html(
			sprintf(
				'mejor en JPG, de unos 2000 píxeles de ancho y que pese menos de 1 MB (las más grandes se reducen solas a 2560 px). Máximo por archivo: %s. No se aceptan SVG. Después de subirla escribe su «Texto alternativo»: qué se ve en la foto.',
				size_format( wp_max_upload_size() )
			)
		)
	);
}
add_action( 'post-upload-ui', 'mats_admin_upload_tip' );

/* the same advice on top of the media library */
add_action(
	'admin_notices',
	static function () {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'upload' !== $screen->id ) {
			return;
		}
		echo '<div class="notice notice-info mats-media-notice"><p>';
		echo esc_html( 'Fotos: JPG de unos 2000 px de ancho y menos de 1 MB. Escribe siempre el «Texto alternativo» (describe lo que se ve). Una foto que se usa en la web no se debe borrar: sustitúyela en la página.' );
		echo ' <a href="' . esc_url( mats_admin_guide_url( 'consejos-fotos' ) ) . '">' . esc_html( 'Más consejos en la guía' ) . '</a></p></div>';
	}
);
