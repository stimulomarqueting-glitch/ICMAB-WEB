<?php
/**
 * Block editor lockdown — editors change words and photos, never the
 * design. Administrators keep the escape hatches.
 *
 * ALLOWED BLOCKS (allowed_block_types_all, filter 'mats_allowed_blocks')
 *   news articles   core writing blocks (paragraph, heading, lists, quote,
 *                   pullquote, image, gallery, embed, video, audio, file,
 *                   table, separator, spacer, buttons, columns, group,
 *                   details) + the closing call to action matsurfer/page-cta
 *   pages           sections pages (built from MatSurfer sections): the
 *                   matsurfer/* sections; reading pages (legal texts and
 *                   any new page): paragraph, heading, list, quote,
 *                   separator, image, buttons, table. A brand-new page
 *                   offers both: typing makes it a reading page, choosing
 *                   a section from "+" makes it a sections page.
 *   administrators  everything above + Custom HTML and Shortcode
 *   never           HTML/code/shortcode for editors, widgets, navigation,
 *                   site/query/template blocks, synced patterns
 *
 * EDITOR SETTINGS (block_editor_settings_all)
 *   code editor and block locking only for administrators; no Openverse,
 *   no block directory, no remote/core patterns, no font library; colours,
 *   font sizes, spacing and borders come from the theme only (theme.json
 *   layer); MatSurfer sections cannot get CSS classes, anchors, HTML
 *   editing, renaming, hiding or locking options.
 *
 * STRUCTURAL PAGES (pages with a role: _mats_role, see inc/links.php)
 *   - nobody can delete them (map_meta_cap → do_not_allow), the list says why
 *   - editors cannot change their slug, parent, status or password (server
 *     side + the rows are hidden in the editor) nor their language
 *   - editors cannot add, move or remove sections (templateLock 'all')
 *   - a notice at the top of the editor explains what can be changed
 *   - editors may edit the Privacy Policy page and its translation (core
 *     and Polylang reserve it to administrators; here the ICMAB team pastes
 *     the legal texts)
 *
 * SAFE HTML
 *   Only administrators keep 'unfiltered_html'. Editors' content goes
 *   through WordPress' HTML filter; the plain-text attributes of MatSurfer
 *   sections are restored after it (R&D stays "R&D", not "R&amp;D") —
 *   every render.php escapes them on output.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

/** Is the current user an administrator? */
function mats_editor_is_admin(): bool {
	return function_exists( 'mats_admin_is_admin' ) ? mats_admin_is_admin() : current_user_can( 'manage_options' );
}

/* ═══════════════════════════════════════════════════════════════════════
 * Pages: what kind of page is this?
 * ═════════════════════════════════════════════════════════════════════ */

/**
 * Kind of a page in the editor:
 *   'sections' structural page built from MatSurfer sections
 *   'news'     the News page (its content is never shown: the list is automatic)
 *   'text'     structural reading page (privacy policy, legal notice)
 *   'landing'  another page built from sections
 *   'reading'  another text page
 *   'new'      a page that has no content yet
 * Returns [ 'kind' => …, 'role' => '' | role ].
 */
function mats_editor_page_info( $post ): array {
	$post = get_post( $post );
	if ( ! $post || 'page' !== $post->post_type ) {
		return array(
			'kind' => '',
			'role' => '',
		);
	}
	$role = function_exists( 'mats_role_of' ) ? mats_role_of( $post->ID ) : (string) get_post_meta( $post->ID, '_mats_role', true );
	if ( $role ) {
		if ( 'news' === $role ) {
			$kind = 'news';
		} elseif ( in_array( $role, array( 'privacy', 'legal' ), true ) ) {
			$kind = 'text';
		} else {
			$kind = 'sections';
		}
	} elseif ( 'auto-draft' === $post->post_status || '' === trim( (string) $post->post_content ) ) {
		$kind = 'new';
	} else {
		$kind = function_exists( 'mats_is_landing' ) && mats_is_landing( $post ) ? 'landing' : 'reading';
	}
	return array(
		'kind' => $kind,
		'role' => $role,
	);
}

/** Is this a structural page (one of the site's fixed pages)? */
function mats_editor_is_structural( $post ): bool {
	$post = get_post( $post );
	if ( ! $post || 'page' !== $post->post_type ) {
		return false;
	}
	$role = function_exists( 'mats_role_of' ) ? mats_role_of( $post->ID ) : (string) get_post_meta( $post->ID, '_mats_role', true );
	return '' !== $role;
}

/* ═══════════════════════════════════════════════════════════════════════
 * Allowed blocks
 * ═════════════════════════════════════════════════════════════════════ */

/** Core blocks for reading pages (legal texts). */
function mats_editor_text_blocks(): array {
	return array( 'core/paragraph', 'core/heading', 'core/list', 'core/list-item', 'core/quote', 'core/separator', 'core/image', 'core/buttons', 'core/button', 'core/table' );
}

/** Core blocks for news articles. */
function mats_editor_post_blocks(): array {
	return array( 'core/paragraph', 'core/heading', 'core/list', 'core/list-item', 'core/quote', 'core/pullquote', 'core/image', 'core/gallery', 'core/embed', 'core/video', 'core/audio', 'core/file', 'core/table', 'core/separator', 'core/spacer', 'core/buttons', 'core/button', 'core/columns', 'core/column', 'core/group', 'core/details' );
}

/**
 * MatSurfer sections. $where = 'page' (all of them) or 'post' (only the
 * closing call to action and sections whose block.json says
 * "matsurfer": { "scope": "post" | "any" }).
 */
function mats_editor_section_blocks( string $where = 'page' ): array {
	$registry = function_exists( 'mats_blocks' ) ? mats_blocks() : array();
	if ( ! $registry ) {
		foreach ( WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $type ) {
			if ( str_starts_with( $name, 'matsurfer/' ) ) {
				$registry[ $name ] = array( 'scope' => 'page' );
			}
		}
	}
	if ( 'page' === $where ) {
		return array_keys( $registry );
	}
	$out = array();
	foreach ( $registry as $name => $def ) {
		if ( 'matsurfer/page-cta' === $name || in_array( $def['scope'] ?? 'page', array( 'post', 'any' ), true ) ) {
			$out[] = $name;
		}
	}
	return $out;
}

/** Allowed block names for a post being edited. */
function mats_editor_allowed_blocks( WP_Post $post, bool $admin ): array {
	$extra = $admin ? array( 'core/html', 'core/shortcode' ) : array();
	if ( 'post' === $post->post_type ) {
		$list = array_merge( mats_editor_post_blocks(), mats_editor_section_blocks( 'post' ), $extra );
	} elseif ( 'page' === $post->post_type ) {
		$info     = mats_editor_page_info( $post );
		$sections = mats_editor_section_blocks( 'page' );
		$text     = mats_editor_text_blocks();
		if ( $admin || in_array( $info['kind'], array( 'new', 'news' ), true ) ) {
			$list = array_merge( $sections, $text, $extra );
		} elseif ( in_array( $info['kind'], array( 'sections', 'landing' ), true ) ) {
			$list = $sections;
		} else {
			$list = $text;
		}
	} else {
		$list = array_merge( mats_editor_text_blocks(), $extra );
	}
	/**
	 * Filters the blocks the editor offers.
	 *
	 * @param string[] $list  Block names.
	 * @param WP_Post  $post  Post being edited.
	 * @param bool     $admin Whether the user is an administrator.
	 */
	return array_values( array_unique( (array) apply_filters( 'mats_allowed_blocks', $list, $post, $admin ) ) );
}

add_filter(
	'allowed_block_types_all',
	static function ( $allowed, $context ) {
		if ( ! $context instanceof WP_Block_Editor_Context || empty( $context->post ) || ! $context->post instanceof WP_Post ) {
			return $allowed;
		}
		return mats_editor_allowed_blocks( $context->post, mats_editor_is_admin() );
	},
	20,
	2
);

/* ═══════════════════════════════════════════════════════════════════════
 * Editor settings
 * ═════════════════════════════════════════════════════════════════════ */

/* No block directory (installing plugins from the inserter) and no remote patterns. */
remove_action( 'enqueue_block_editor_assets', 'wp_enqueue_editor_block_directory_assets' );
add_filter( 'should_load_remote_block_patterns', '__return_false' );

/* No core / plugin patterns: pages are made of MatSurfer sections. */
add_action(
	'init',
	static function () {
		if ( ! apply_filters( 'mats_editor_remove_patterns', true ) || ! class_exists( 'WP_Block_Patterns_Registry' ) ) {
			return;
		}
		$registry = WP_Block_Patterns_Registry::get_instance();
		foreach ( $registry->get_all_registered() as $pattern ) {
			if ( ! empty( $pattern['name'] ) ) {
				$registry->unregister( $pattern['name'] );
			}
		}
	},
	999
);

add_filter(
	'block_editor_settings_all',
	static function ( $settings, $context ) {
		$admin = mats_editor_is_admin();

		if ( ! $admin ) {
			$settings['codeEditingEnabled'] = false;
			$settings['canLockBlocks']      = false;
		}
		$settings['enableOpenverseMediaCategory'] = false;
		$settings['fontLibraryEnabled']           = false;

		$post = $context instanceof WP_Block_Editor_Context ? $context->post : null;
		if ( ! $post instanceof WP_Post ) {
			return $settings;
		}
		$settings['__experimentalAdditionalBlockPatterns']          = array();
		$settings['__experimentalAdditionalBlockPatternCategories'] = array();

		if ( 'post' === $post->post_type ) {
			$settings['titlePlaceholder'] = 'Título de la noticia';
			$settings['bodyPlaceholder']  = 'Escribe aquí el texto de la noticia. Pulsa + para añadir una foto, un vídeo o un título.';
		} elseif ( 'page' === $post->post_type ) {
			$info                         = mats_editor_page_info( $post );
			$settings['titlePlaceholder'] = 'Título de la página';
			$settings['bodyPlaceholder']  = 'Escribe aquí el texto de la página.';
			if ( ! $admin && in_array( $info['kind'], array( 'sections', 'news' ), true ) ) {
				/* nothing can be added, moved or removed: only texts and photos change */
				$settings['templateLock'] = 'all';
			}
			if ( 'new' === $info['kind'] && 'auto-draft' === $post->post_status ) {
				$settings['template']     = array(
					array(
						'core/paragraph',
						array( 'placeholder' => 'Escribe aquí el texto de la página… o pulsa + para montarla con secciones de la web.' ),
					),
				);
				$settings['templateLock'] = false;
			}
		}
		return $settings;
	},
	20,
	2
);

/*
 * Design tokens: colours, font sizes, spacing and borders are decided by
 * the theme. The theme layer of theme.json switches off every free-form
 * design control (and the core palettes/presets) in the block editor.
 */
add_filter(
	'wp_theme_json_data_theme',
	static function ( $theme_json ) {
		if ( ! apply_filters( 'mats_editor_lock_design', true ) || ! is_object( $theme_json ) || ! method_exists( $theme_json, 'update_with' ) ) {
			return $theme_json;
		}
		$border_off = array(
			'color'  => false,
			'radius' => false,
			'style'  => false,
			'width'  => false,
		);
		return $theme_json->update_with(
			array(
				'version'  => 3,
				'settings' => array(
					'appearanceTools' => false,
					'border'          => $border_off,
					'background'      => array(
						'backgroundImage' => false,
						'backgroundSize'  => false,
						'gradient'        => false,
					),
					'color'           => array(
						'background'       => false,
						'button'           => false,
						'caption'          => false,
						'custom'           => false,
						'customDuotone'    => false,
						'customGradient'   => false,
						'defaultDuotone'   => false,
						'defaultGradients' => false,
						'defaultPalette'   => false,
						'duotone'          => array(),
						'gradients'        => array(),
						'heading'          => false,
						'link'             => false,
						'palette'          => array(),
						'text'             => false,
					),
					'dimensions'      => array(
						'aspectRatio' => false,
						'minHeight'   => false,
					),
					'position'        => array( 'sticky' => false ),
					'shadow'          => array(
						'defaultPresets' => false,
						'presets'        => array(),
					),
					'spacing'         => array(
						'blockGap'            => false,
						'customSpacingSize'   => false,
						'defaultSpacingSizes' => false,
						'margin'              => false,
						'padding'             => false,
						'spacingSizes'        => array(),
					),
					'typography'      => array(
						'customFontSize'   => false,
						'defaultFontSizes' => false,
						'dropCap'          => false,
						'fontFamilies'     => array(),
						'fontSizes'        => array(),
						'fontStyle'        => false,
						'fontWeight'       => false,
						'letterSpacing'    => false,
						'lineHeight'       => false,
						'textColumns'      => false,
						'textDecoration'   => false,
						'textTransform'    => false,
						'writingMode'      => false,
					),
					'blocks'          => array(
						'core/button'    => array(
							'border'     => $border_off,
							'dimensions' => array( 'width' => false ),
						),
						'core/pullquote' => array( 'border' => $border_off ),
						'core/image'     => array( 'border' => $border_off ),
					),
				),
			)
		);
	}
);

/* MatSurfer sections: no CSS class, anchor, HTML editing, renaming, hiding or lock options. */
add_filter(
	'register_block_type_args',
	static function ( $args, $name ) {
		if ( ! is_string( $name ) || ! str_starts_with( $name, 'matsurfer/' ) ) {
			return $args;
		}
		$args['supports'] = array_merge(
			is_array( $args['supports'] ?? null ) ? $args['supports'] : array(),
			array(
				'html'            => false,
				'className'       => false,
				'customClassName' => false,
				'anchor'          => false,
				'reusable'        => false,
				'renaming'        => false,
				'lock'            => false,
				'visibility'      => false,
			)
		);
		return $args;
	},
	10,
	2
);

/* ═══════════════════════════════════════════════════════════════════════
 * Capabilities: structural pages can't be deleted; unfiltered HTML only for admins
 * ═════════════════════════════════════════════════════════════════════ */

add_filter(
	'map_meta_cap',
	static function ( $caps, $cap, $user_id, $args ) {
		if ( ( 'delete_post' === $cap || 'delete_page' === $cap ) && ! empty( $args[0] ) ) {
			$post = get_post( (int) $args[0] );
			if ( $post && mats_editor_is_structural( $post ) ) {
				return array( 'do_not_allow' );
			}
		}
		if ( 'unfiltered_html' === $cap && apply_filters( 'mats_editor_restrict_unfiltered_html', true ) && ! user_can( (int) $user_id, 'manage_options' ) ) {
			return array( 'do_not_allow' );
		}
		/*
		 * WordPress only lets administrators edit the page set as "Privacy
		 * Policy". Here the ICMAB team pastes the legal texts themselves, so
		 * editing that page needs the same rights as any other page.
		 */
		if ( ( 'edit_post' === $cap || 'edit_page' === $cap ) && ! empty( $args[0] ) && mats_editor_is_privacy_page( (int) $args[0] ) && apply_filters( 'mats_editor_editors_edit_privacy_page', true ) ) {
			$caps = array_values( array_diff( $caps, map_meta_cap( 'manage_privacy_options', (int) $user_id ) ) );
			return $caps ? $caps : array( 'edit_pages' );
		}
		return $caps;
	},
	20, // after Polylang, which extends the privacy page rule to its translations
	4
);

/** Is this the "Privacy Policy" page or one of its translations? */
function mats_editor_is_privacy_page( int $post_id ): bool {
	$privacy = (int) get_option( 'wp_page_for_privacy_policy' );
	if ( ! $privacy || ! $post_id ) {
		return false;
	}
	if ( $post_id === $privacy ) {
		return true;
	}
	return function_exists( 'pll_get_post_translations' ) && in_array( $post_id, array_map( 'intval', (array) pll_get_post_translations( $privacy ) ), true );
}

/*
 * Editors cannot move a structural page (slug, parent), take it offline
 * (draft, pending, private, scheduled, trash) or password-protect it.
 * Administrators can.
 */
add_filter(
	'wp_insert_post_data',
	static function ( $data, $postarr ) {
		$id = (int) ( $postarr['ID'] ?? 0 );
		if ( ! $id || 'page' !== ( $data['post_type'] ?? '' ) || mats_editor_is_admin() || ! is_user_logged_in() ) {
			return $data;
		}
		$old = get_post( $id );
		if ( ! $old || 'auto-draft' === $old->post_status || ! mats_editor_is_structural( $old ) ) {
			return $data;
		}
		$data['post_name']     = $old->post_name;
		$data['post_parent']   = (int) $old->post_parent;
		$data['post_password'] = $old->post_password;
		if ( 'publish' === $old->post_status && 'publish' !== ( $data['post_status'] ?? '' ) ) {
			$data['post_status'] = 'publish';
		}
		return $data;
	},
	20,
	2
);

/* ── page list: say why the main pages have no "Papelera" link ─────────── */

add_filter(
	'display_post_states',
	static function ( $states, $post ) {
		if ( $post instanceof WP_Post && mats_editor_is_structural( $post ) ) {
			$role  = function_exists( 'mats_role_of' ) ? mats_role_of( $post->ID ) : '';
			$names = function_exists( 'mats_page_roles' ) ? mats_page_roles() : array();
			if ( isset( $names[ $role ] ) && ! isset( $states['page_on_front'] ) ) {
				$states['mats_role'] = 'Página principal: ' . $names[ $role ];
			}
		}
		return $states;
	},
	10,
	2
);

add_filter(
	'page_row_actions',
	static function ( $actions, $post ) {
		if ( ! $post instanceof WP_Post || ! mats_editor_is_structural( $post ) ) {
			return $actions;
		}
		if ( ! mats_editor_is_admin() ) {
			unset( $actions['inline hide-if-no-js'] ); // quick edit (slug, parent, status)
		}
		$actions['mats_locked'] = '<span class="mats-row-locked" title="' . esc_attr( 'La usan el menú y los botones de la web. Puedes cambiar sus textos y fotos.' ) . '"><span class="dashicons dashicons-lock" aria-hidden="true"></span> ' . esc_html( 'Página fija: no se puede borrar' ) . '</span>';
		return $actions;
	},
	20,
	2
);

add_action(
	'admin_notices',
	static function () {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-page' !== $screen->id ) {
			return;
		}
		echo '<div class="notice notice-info mats-pages-notice"><p>';
		echo '<span class="dashicons dashicons-lock" aria-hidden="true"></span> ';
		echo esc_html( 'Las páginas principales (Inicio, Producto, Aplicaciones, Casos de estudio, Equipo, Contacto, Noticias y los textos legales) están protegidas: puedes cambiar sus textos y fotos, pero no borrarlas ni cambiar su dirección, porque las usan el menú y los botones de la web. Cada una existe en inglés y en español: las columnas con banderas llevan a cada versión.' );
		if ( mats_editor_is_admin() ) {
			echo ' ' . esc_html( 'Administradores: para retirar una página principal, quítale antes su papel (el campo _mats_role de la página y de su traducción, y su entrada en la opción mats_page_roles); después ya se podrá borrar.' );
		}
		echo '</p></div>';
	}
);

/* ═══════════════════════════════════════════════════════════════════════
 * Safe HTML for editors: restore the plain text of section attributes
 * ═════════════════════════════════════════════════════════════════════ */

/** Decodes the entities WordPress' HTML filter adds to plain-text attribute values. */
function mats_editor_decode_attrs( $value ) {
	if ( is_array( $value ) ) {
		foreach ( $value as $key => $inner ) {
			$value[ $key ] = mats_editor_decode_attrs( $inner );
		}
		return $value;
	}
	return is_string( $value ) ? wp_specialchars_decode( $value, ENT_QUOTES ) : $value;
}

/** Walks parsed blocks and decodes the attributes of MatSurfer sections. */
function mats_editor_decode_blocks( array $blocks ): array {
	foreach ( $blocks as $i => $block ) {
		if ( ! empty( $block['blockName'] ) && str_starts_with( (string) $block['blockName'], 'matsurfer/' ) && ! empty( $block['attrs'] ) ) {
			$blocks[ $i ]['attrs'] = mats_editor_decode_attrs( $block['attrs'] );
		}
		if ( ! empty( $block['innerBlocks'] ) ) {
			$blocks[ $i ]['innerBlocks'] = mats_editor_decode_blocks( $block['innerBlocks'] );
		}
	}
	return $blocks;
}

/*
 * Runs right after core filtered the block attributes (pre_kses, priority
 * 10): "R&amp;D" → "R&D". The serialised attributes are JSON with
 * unicode escapes, so the rest of the HTML filter leaves them alone.
 */
add_filter(
	'pre_kses',
	static function ( $content ) {
		if ( ! is_string( $content ) || false === strpos( $content, '<!-- wp:matsurfer/' ) ) {
			return $content;
		}
		return serialize_blocks( mats_editor_decode_blocks( parse_blocks( $content ) ) );
	},
	11
);

/* ═══════════════════════════════════════════════════════════════════════
 * Editor UI: notices, hidden panels, body classes
 * ═════════════════════════════════════════════════════════════════════ */

/** Message shown at the top of the editor for a page kind ('' for none). */
function mats_editor_notice( array $info, bool $admin ): string {
	switch ( $info['kind'] ) {
		case 'sections':
			return $admin
				? 'Página principal formada por secciones bloqueadas. Como administrador puedes desbloquearlas (menú ⋮ de la sección); el equipo del ICMAB solo puede cambiar textos y fotos.'
				: 'Esta página está formada por secciones bloqueadas: puedes cambiar textos y fotos, no la estructura.';
		case 'news':
			return 'Esta es la página de Noticias: la lista de noticias se rellena sola con las noticias publicadas. Aquí solo se cambian el título, el extracto (la entradilla de la cabecera) y la imagen destacada.';
		case 'text':
			return 'Página de texto legal: escribe o pega aquí el texto (usa «Encabezado» para cada apartado). Es una página fija de la web: no se puede borrar ni cambiar su dirección.';
		case 'new':
			return 'Página nueva: escribe el título y el texto y se verá con el diseño de lectura de la web. El «Extracto» será la entradilla de la cabecera y la «Imagen destacada», su foto.';
	}
	return '';
}

add_action(
	'enqueue_block_editor_assets',
	static function () {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! $screen->is_block_editor() || ! in_array( $screen->base, array( 'post' ), true ) ) {
			return;
		}
		$post  = get_post();
		$admin = mats_editor_is_admin();
		$info  = $post ? mats_editor_page_info( $post ) : array(
			'kind' => '',
			'role' => '',
		);

		$structural = '' !== $info['role'];
		$hide       = array();
		if ( $structural && ! $admin ) {
			$hide = array( 'page-attributes', 'template' );
		}

		wp_enqueue_script(
			'matsurfer-editor-guard',
			MATS_URI . '/assets/js/admin.js',
			array( 'wp-data', 'wp-dom-ready', 'wp-blocks', 'wp-notices', 'wp-editor' ),
			mats_ver( 'assets/js/admin.js' ),
			true
		);
		wp_add_inline_script(
			'matsurfer-editor-guard',
			'window.matsEditorGuard = ' . wp_json_encode(
				array(
					'isAdmin'    => $admin,
					'postType'   => $post ? $post->post_type : '',
					'kind'       => $info['kind'],
					'role'       => $info['role'],
					'structural' => $structural,
					'notice'     => mats_editor_notice( $info, $admin ),
					'hidePanels' => $hide,
					'guideUrl'   => function_exists( 'mats_admin_guide_url' ) ? mats_admin_guide_url( 'post' === ( $post->post_type ?? '' ) ? 'noticias' : 'textos' ) : '',
				)
			) . ';',
			'before'
		);
	}
);

/* body classes for the editor stylesheet (assets/css/admin.css) */
add_filter(
	'admin_body_class',
	static function ( $classes ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! $screen->is_block_editor() ) {
			return $classes;
		}
		$post = get_post();
		if ( ! $post ) {
			return $classes;
		}
		$info = mats_editor_page_info( $post );
		if ( $info['kind'] ) {
			$classes .= ' mats-page-kind-' . sanitize_html_class( $info['kind'] );
		}
		if ( '' !== $info['role'] ) {
			$classes .= ' mats-structural';
		}
		return $classes;
	}
);
