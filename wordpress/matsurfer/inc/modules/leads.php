<?php
/**
 * Contactos — private inbox for the leads sent through the MatSurfer
 * contact forms (WPForms Lite only emails them; this keeps a copy so no
 * lead is ever lost).
 *
 * STORAGE
 *   Post type 'mats_lead' (private: no front end, no REST, no search, no
 *   sitemap, no Polylang, no WXR export). One post per submission,
 *   post_status 'private', post_date = date received. Every value lives in
 *   protected meta '_mats_lead_<field>' (see mats_lead_fields()); the title
 *   "Name — Organisation" is only a label. Status: '_mats_lead_status' =
 *   new | read | answered. IP addresses and user agents are NEVER stored.
 *
 * WHO CAN SEE IT
 *   Capability 'mats_manage_leads' (administrator + editor, granted on theme
 *   activation and lazily when missing). Every capability of the post type
 *   maps to it; nobody can create or edit a lead by hand (leads only come
 *   from the form). The retention setting needs 'manage_options' too.
 *
 * ENTRY POINTS
 *   mats_lead_save( array $data ): int          save a lead (normalises + sanitises)
 *   mats_lead_from_wpforms( $fields, $entry, $form_data, $entry_id )
 *                                               hooked to wpforms_process_complete,
 *                                               only for mats_setting( 'form_en' | 'form_es' )
 *   mats_lead_get( int $id ): ?array            one lead, all fields
 *   mats_lead_set_status( int $id, string $status ): bool
 *   do_action( 'mats_lead_saved', $id, $lead )  after a lead is stored
 *
 *   Values are stored as plain text (tags stripped, no HTML entities):
 *   escape them on output.
 *
 *   Tracking posted with the form: $_POST['matsurfer_meta'][ cta_source |
 *   utm (JSON string) | application | language | source_page ].
 *   WPForms field IDs: 1 name, 2 email, 3 organisation, 4 role,
 *   5 interest, 6 area, 7 message, 8 consent. Other fields added later in
 *   WPForms are kept as "Otros campos".
 *
 * ADMIN (menu "Contactos", admin.php?page=mats-leads)
 *   list with views by status, language filter, search, bulk/row actions,
 *   CSV export (UTF-8 BOM, ';'), read-only detail (&lead=ID, marks as read),
 *   optional automatic deletion after N months (daily cron), GDPR exporter
 *   and eraser by email, dashboard widget with the latest leads.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

defined( 'MATS_LEAD_TYPE' ) || define( 'MATS_LEAD_TYPE', 'mats_lead' );
defined( 'MATS_LEAD_CAP' ) || define( 'MATS_LEAD_CAP', 'mats_manage_leads' );
defined( 'MATS_LEADS_PAGE' ) || define( 'MATS_LEADS_PAGE', 'mats-leads' );
defined( 'MATS_LEADS_CAPS_VERSION' ) || define( 'MATS_LEADS_CAPS_VERSION', '1' );
defined( 'MATS_LEADS_PER_PAGE' ) || define( 'MATS_LEADS_PER_PAGE', 20 ); // rows per page of the list

/* ═══════════════════════════════════════════════════════════════════════
 * Data model
 * ═════════════════════════════════════════════════════════════════════ */

/** Stored fields (meta '_mats_lead_<field>'), in display/export order. */
function mats_lead_fields(): array {
	return array( 'name', 'email', 'organisation', 'role', 'interest', 'area', 'message', 'consent', 'consent_date', 'language', 'cta_source', 'utm', 'application', 'source_page', 'form_id', 'extra' );
}

function mats_lead_meta_key( string $field ): string {
	return '_mats_lead_' . $field;
}

/** Status => Spanish label. */
function mats_lead_statuses(): array {
	return array(
		'new'      => 'Nuevo',
		'read'     => 'Leído',
		'answered' => 'Atendido',
	);
}

/** Status => one-line explanation for the status selector. */
function mats_lead_status_hints(): array {
	return array(
		'new'      => 'Nadie lo ha abierto todavía.',
		'read'     => 'Leído, pero todavía sin responder.',
		'answered' => 'Ya se le ha respondido.',
	);
}

/** Language => Spanish name. */
function mats_lead_languages(): array {
	return array(
		'en' => 'Inglés',
		'es' => 'Español',
	);
}

add_action(
	'init',
	static function () {
		$cap = MATS_LEAD_CAP;
		register_post_type(
			MATS_LEAD_TYPE,
			array(
				'labels'              => array(
					'name'          => 'Contactos',
					'singular_name' => 'Contacto',
					'menu_name'     => 'Contactos',
					'all_items'     => 'Todos los contactos',
					'search_items'  => 'Buscar contactos',
					'not_found'     => 'Todavía no ha llegado ningún contacto.',
				),
				'description'         => 'Mensajes recibidos por el formulario de contacto.',
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'can_export'          => false, // personal data: never in Herramientas → Exportar (WXR)
				'delete_with_user'    => false,
				'hierarchical'        => false,
				'supports'            => array( 'title' ),
				'map_meta_cap'        => false,
				'capabilities'        => array(
					'read'                   => $cap,
					'read_post'              => $cap,
					'edit_post'              => $cap,
					'delete_post'            => $cap,
					'edit_posts'             => $cap,
					'edit_others_posts'      => $cap,
					'edit_private_posts'     => $cap,
					'edit_published_posts'   => $cap,
					'read_private_posts'     => $cap,
					'delete_posts'           => $cap,
					'delete_others_posts'    => $cap,
					'delete_private_posts'   => $cap,
					'delete_published_posts' => $cap,
					'publish_posts'          => 'do_not_allow',
					'create_posts'           => 'do_not_allow', // no "Añadir nuevo": leads only come from the form
				),
			)
		);
	},
	5
);

/* Never translated by Polylang, never in sitemaps (also SEO plugins). */
add_filter(
	'pll_get_post_types',
	static function ( $types ) {
		unset( $types[ MATS_LEAD_TYPE ] );
		return $types;
	},
	30
);
add_filter(
	'wp_sitemaps_post_types',
	static function ( $types ) {
		unset( $types[ MATS_LEAD_TYPE ] );
		return $types;
	}
);
add_filter( 'wpseo_sitemap_exclude_post_type', static fn( $exclude, $type = '' ) => MATS_LEAD_TYPE === $type ? true : $exclude, 10, 2 );

/* ── capability ───────────────────────────────────────────────────────── */

/**
 * Grants 'mats_manage_leads' to administrator + editor and removes it from
 * every other role. Runs on theme activation and once per version later
 * (lazily, for sites where the theme was already active). On every other
 * admin request it is given back when it goes missing (a role reset, a
 * restored roles option, a migration…): always to administrators, and to
 * editors unless a role-editor plugin explicitly denies it (cap = false).
 */
function mats_leads_ensure_caps( bool $force = false ): void {
	$roles = wp_roles();
	if ( ! $force && MATS_LEADS_CAPS_VERSION === get_option( 'mats_leads_caps_version' ) ) {
		$changed = false;
		$admin   = $roles->get_role( 'administrator' );
		if ( $admin && ! $admin->has_cap( MATS_LEAD_CAP ) ) {
			$admin->add_cap( MATS_LEAD_CAP );
			$changed = true;
		}
		$editor = $roles->get_role( 'editor' );
		if ( $editor && ! array_key_exists( MATS_LEAD_CAP, (array) $editor->capabilities ) ) {
			$editor->add_cap( MATS_LEAD_CAP );
			$changed = true;
		}
		if ( $changed ) {
			mats_leads_refresh_user_caps();
		}
		return;
	}
	foreach ( $roles->role_objects as $name => $role ) {
		if ( in_array( $name, array( 'administrator', 'editor' ), true ) ) {
			if ( ! $role->has_cap( MATS_LEAD_CAP ) ) {
				$role->add_cap( MATS_LEAD_CAP );
			}
		} elseif ( $role->has_cap( MATS_LEAD_CAP ) ) {
			$role->remove_cap( MATS_LEAD_CAP );
		}
	}
	update_option( 'mats_leads_caps_version', MATS_LEADS_CAPS_VERSION, true ); // read on every admin request
	mats_leads_refresh_user_caps();
}

/** The current user object was built before the role changed: rebuild its caps. */
function mats_leads_refresh_user_caps(): void {
	$user = wp_get_current_user();
	if ( $user && $user->exists() ) {
		$user->get_role_caps();
	}
}

add_action( 'after_switch_theme', static fn() => mats_leads_ensure_caps( true ) );
add_action(
	'init',
	static function () {
		if ( is_admin() || wp_doing_cron() ) {
			mats_leads_ensure_caps();
		}
	},
	1
);

/* ── reading & writing ────────────────────────────────────────────────── */

function mats_lead_exists( int $id ): bool {
	return $id > 0 && MATS_LEAD_TYPE === get_post_type( $id );
}

/** Truthy form values ('yes', 'on', '1', the checkbox label…) → bool. */
function mats_lead_truthy( $value ): bool {
	if ( is_bool( $value ) ) {
		return $value;
	}
	if ( is_array( $value ) ) {
		return ! empty( array_filter( $value, 'mats_lead_truthy' ) );
	}
	$value = strtolower( trim( (string) $value ) );
	return ! in_array( $value, array( '', '0', 'no', 'false', 'off' ), true );
}

/**
 * Plain, single-line, length-limited text.
 *
 * Leads are stored as PLAIN TEXT (every screen escapes on output). The
 * WordPress sanitisers, used by WPForms and here, turn the rest of a text
 * after a lone '<' into HTML entities ('Tc < 300 K "x"' → 'Tc &lt; 300 K
 * &quot;x&quot;'); decoding them afterwards keeps the CSV, the reply email
 * and the privacy export readable and makes search match. Tags themselves
 * were already stripped by the sanitiser.
 */
function mats_lead_clean_text( $value, int $max = 200 ): string {
	$value = is_scalar( $value ) ? (string) $value : '';
	return mb_substr( trim( wp_specialchars_decode( sanitize_text_field( $value ), ENT_QUOTES ) ), 0, $max );
}

/** Plain, multi-line, length-limited text (same rules as mats_lead_clean_text()). */
function mats_lead_clean_textarea( $value, int $max = 10000 ): string {
	$value = is_scalar( $value ) ? (string) $value : '';
	return mb_substr( trim( wp_specialchars_decode( sanitize_textarea_field( $value ), ENT_QUOTES ) ), 0, $max );
}

/** UTM parameters from a JSON string or an array → [ source, medium, campaign, content, term ] (only the filled ones). */
function mats_lead_parse_utm( $raw ): array {
	if ( is_string( $raw ) ) {
		$raw = trim( $raw );
		if ( '' === $raw || strlen( $raw ) > 4000 ) {
			return array();
		}
		$decoded = json_decode( $raw, true );
		$raw     = is_array( $decoded ) ? $decoded : array();
	}
	if ( ! is_array( $raw ) ) {
		return array();
	}
	$out = array();
	foreach ( array( 'source', 'medium', 'campaign', 'content', 'term' ) as $key ) {
		$value = $raw[ $key ] ?? ( $raw[ 'utm_' . $key ] ?? '' );
		$value = mats_lead_clean_text( $value, 150 );
		if ( '' !== $value ) {
			$out[ $key ] = $value;
		}
	}
	return $out;
}

/** The page the visitor wrote from: a site path ('/es/contact/') or an http(s) URL. */
function mats_lead_clean_page( $raw ): string {
	$raw = is_scalar( $raw ) ? trim( (string) $raw ) : '';
	if ( '' === $raw ) {
		return '';
	}
	$raw = mb_substr( $raw, 0, 500 );
	if ( str_starts_with( $raw, '/' ) && ! str_starts_with( $raw, '//' ) ) {
		$path = preg_replace( '#[^A-Za-z0-9\-._~/%?=&+]#', '', $raw );
		return '/' . ltrim( (string) $path, '/' );
	}
	if ( preg_match( '#^https?://#i', $raw ) ) {
		return esc_url_raw( $raw, array( 'http', 'https' ) );
	}
	return '';
}

/** Normalises and sanitises a lead before it is stored. */
function mats_lead_normalize( array $data ): array {
	$lead = array(
		'name'         => mats_lead_clean_text( $data['name'] ?? '', 150 ),
		'email'        => strtolower( sanitize_email( is_scalar( $data['email'] ?? '' ) ? (string) ( $data['email'] ?? '' ) : '' ) ),
		'organisation' => mats_lead_clean_text( $data['organisation'] ?? '', 200 ),
		'role'         => mats_lead_clean_text( $data['role'] ?? '', 150 ),
		'interest'     => mats_lead_clean_text( $data['interest'] ?? '', 200 ),
		'area'         => mats_lead_clean_text( $data['area'] ?? '', 200 ),
		'message'      => mats_lead_clean_textarea( $data['message'] ?? '', 10000 ),
		'consent'      => mats_lead_truthy( $data['consent'] ?? false ) ? 'yes' : 'no',
		'consent_date' => '',
		'language'     => in_array( $data['language'] ?? '', mats_langs(), true ) ? (string) $data['language'] : 'en',
		'cta_source'   => mb_substr( (string) preg_replace( '/[^a-z0-9_\-.:|]/', '', strtolower( mats_lead_clean_text( $data['cta_source'] ?? '', 120 ) ) ), 0, 80 ),
		'utm'          => mats_lead_parse_utm( $data['utm'] ?? array() ),
		'application'  => mb_substr( sanitize_title( mats_lead_clean_text( $data['application'] ?? '', 120 ) ), 0, 100 ),
		'source_page'  => mats_lead_clean_page( $data['source_page'] ?? '' ),
		'form_id'      => absint( $data['form_id'] ?? 0 ),
		'extra'        => array(),
	);
	if ( 'yes' === $lead['consent'] ) {
		$given                = is_string( $data['consent_date'] ?? null ) ? trim( $data['consent_date'] ) : '';
		$lead['consent_date'] = preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $given ) ? $given : current_time( 'mysql' );
	}
	foreach ( (array) ( $data['extra'] ?? array() ) as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$label = mats_lead_clean_text( $item['label'] ?? '', 100 );
		$value = mats_lead_clean_textarea( $item['value'] ?? '', 2000 );
		if ( '' !== $value ) {
			$lead['extra'][] = array(
				'label' => '' !== $label ? $label : 'Campo',
				'value' => $value,
			);
		}
		if ( count( $lead['extra'] ) >= 20 ) {
			break;
		}
	}
	return $lead;
}

/**
 * Saves one lead. $data keys: name, email, organisation, role, interest,
 * area, message, consent (bool|string), language ('en'|'es'), cta_source,
 * utm (array or JSON string), application, source_page, form_id, extra
 * ([ [label, value], … ]). Returns the new post ID, 0 on failure.
 */
function mats_lead_save( array $data ): int {
	$lead = mats_lead_normalize( $data );
	if ( '' === $lead['name'] && '' === $lead['email'] && '' === $lead['message'] ) {
		return 0; // nothing worth keeping
	}

	$title = '' !== $lead['name'] ? $lead['name'] : ( '' !== $lead['email'] ? $lead['email'] : 'Contacto sin nombre' );
	if ( '' !== $lead['organisation'] ) {
		$title .= ' — ' . $lead['organisation'];
	}

	$meta = array();
	foreach ( mats_lead_fields() as $field ) {
		$meta[ mats_lead_meta_key( $field ) ] = $lead[ $field ];
	}
	$meta[ mats_lead_meta_key( 'status' ) ] = 'new';

	$id = wp_insert_post(
		wp_slash(
			array(
				'post_type'      => MATS_LEAD_TYPE,
				'post_status'    => 'private',
				'post_title'     => $title,
				'post_content'   => '',
				'post_name'      => 'contacto-' . gmdate( 'YmdHis' ) . '-' . wp_rand( 100, 999 ), // no personal data in the slug
				'post_author'    => 0,
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
				'meta_input'     => $meta,
			)
		),
		true
	);
	if ( is_wp_error( $id ) || ! $id ) {
		error_log( 'MatSurfer: no se ha podido guardar un contacto del formulario.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions -- no personal data logged
		return 0;
	}
	mats_leads_flush_counts();
	do_action( 'mats_lead_saved', (int) $id, $lead );
	return (int) $id;
}

/** One lead with every field, or null when the ID is not a lead. */
function mats_lead_get( $id ): ?array {
	$id = (int) ( $id instanceof WP_Post ? $id->ID : $id );
	if ( ! mats_lead_exists( $id ) ) {
		return null;
	}
	$lead = array( 'id' => $id );
	foreach ( mats_lead_fields() as $field ) {
		$lead[ $field ] = get_post_meta( $id, mats_lead_meta_key( $field ), true );
	}
	foreach ( array( 'name', 'email', 'organisation', 'role', 'interest', 'area', 'message', 'consent', 'consent_date', 'language', 'cta_source', 'application', 'source_page' ) as $field ) {
		$lead[ $field ] = is_scalar( $lead[ $field ] ) ? (string) $lead[ $field ] : '';
	}
	$lead['utm']         = is_array( $lead['utm'] ) ? $lead['utm'] : mats_lead_parse_utm( $lead['utm'] );
	$lead['extra']       = is_array( $lead['extra'] ) ? $lead['extra'] : array();
	$lead['form_id']     = (int) $lead['form_id'];
	$lead['language']    = in_array( $lead['language'], mats_langs(), true ) ? $lead['language'] : 'en';
	$status              = (string) get_post_meta( $id, mats_lead_meta_key( 'status' ), true );
	$lead['status']      = isset( mats_lead_statuses()[ $status ] ) ? $status : 'new';
	$lead['date']        = (int) get_post_timestamp( $id );
	$lead['answered_at'] = (int) get_post_meta( $id, mats_lead_meta_key( 'answered_at' ), true );
	$lead['answered_by'] = (int) get_post_meta( $id, mats_lead_meta_key( 'answered_by' ), true );
	return $lead;
}

/** Changes the status of a lead (new | read | answered). */
function mats_lead_set_status( int $id, string $status ): bool {
	if ( ! mats_lead_exists( $id ) || ! isset( mats_lead_statuses()[ $status ] ) ) {
		return false;
	}
	update_post_meta( $id, mats_lead_meta_key( 'status' ), $status );
	if ( 'answered' === $status ) {
		if ( ! get_post_meta( $id, mats_lead_meta_key( 'answered_at' ), true ) ) {
			update_post_meta( $id, mats_lead_meta_key( 'answered_at' ), time() );
			update_post_meta( $id, mats_lead_meta_key( 'answered_by' ), get_current_user_id() );
		}
	} else {
		delete_post_meta( $id, mats_lead_meta_key( 'answered_at' ) );
		delete_post_meta( $id, mats_lead_meta_key( 'answered_by' ) );
	}
	mats_leads_flush_counts();
	return true;
}

/** Deletes a lead for good (no trash: it is personal data). */
function mats_lead_delete( int $id ): bool {
	if ( ! mats_lead_exists( $id ) ) {
		return false;
	}
	$done = (bool) wp_delete_post( $id, true );
	mats_leads_flush_counts();
	return $done;
}

/** Number of leads (optionally of one status). Cached per request. */
function mats_leads_count( string $status = '' ): int {
	$cache = $GLOBALS['mats_leads_counts'] ?? array();
	$key   = '' === $status ? 'all' : $status;
	if ( ! isset( $cache[ $key ] ) ) {
		$args = array(
			'post_type'              => MATS_LEAD_TYPE,
			'post_status'            => 'any',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'suppress_filters'       => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);
		if ( '' !== $status ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'key'   => mats_lead_meta_key( 'status' ),
					'value' => $status,
				),
			);
		}
		$query                        = new WP_Query( $args );
		$cache[ $key ]                = (int) $query->found_posts;
		$GLOBALS['mats_leads_counts'] = $cache;
	}
	return $cache[ $key ];
}

function mats_leads_flush_counts(): void {
	unset( $GLOBALS['mats_leads_counts'] );
}

/**
 * WP_Query arguments for a set of filters:
 * status, lang, s (search), from / to (Y-m-d), orderby (date|name), order.
 */
function mats_leads_query_args( array $f ): array {
	$order = 'ASC' === strtoupper( (string) ( $f['order'] ?? '' ) ) ? 'ASC' : 'DESC';
	$args  = array(
		'post_type'        => MATS_LEAD_TYPE,
		'post_status'      => 'any',
		'suppress_filters' => true,
		'orderby'          => array(
			'date' => $order,
			'ID'   => $order,
		),
	);
	$meta = array( 'relation' => 'AND' );
	if ( ! empty( $f['status'] ) ) {
		$meta[] = array(
			'key'   => mats_lead_meta_key( 'status' ),
			'value' => (string) $f['status'],
		);
	}
	if ( ! empty( $f['lang'] ) ) {
		$meta[] = array(
			'key'   => mats_lead_meta_key( 'language' ),
			'value' => (string) $f['lang'],
		);
	}
	if ( isset( $f['s'] ) && '' !== $f['s'] ) {
		$search = array( 'relation' => 'OR' );
		foreach ( array( 'name', 'email', 'organisation', 'role', 'message' ) as $field ) {
			$search[] = array(
				'key'     => mats_lead_meta_key( $field ),
				'value'   => (string) $f['s'],
				'compare' => 'LIKE',
			);
		}
		$meta[] = $search;
	}
	if ( 'name' === ( $f['orderby'] ?? '' ) ) {
		$meta['mats_name'] = array(
			'key'     => mats_lead_meta_key( 'name' ),
			'compare' => 'EXISTS',
		);
		$args['orderby']   = array(
			'mats_name' => $order,
			'date'      => 'DESC',
		);
	}
	if ( count( $meta ) > 1 ) {
		$args['meta_query'] = $meta; // phpcs:ignore WordPress.DB.SlowDBQuery
	}
	$range = array();
	if ( ! empty( $f['from'] ) ) {
		$range['after'] = $f['from'] . ' 00:00:00';
	}
	if ( ! empty( $f['to'] ) ) {
		$range['before'] = $f['to'] . ' 23:59:59';
	}
	if ( $range ) {
		$range['inclusive'] = true;
		$args['date_query'] = array( $range );
	}
	return $args;
}

/** Lead IDs whose email is $email (privacy tools). */
function mats_leads_ids_by_email( string $email, int $per_page = 50, int $page = 1 ): array {
	$email = strtolower( trim( sanitize_email( $email ) ) );
	if ( '' === $email ) {
		return array();
	}
	return array_map(
		'intval',
		get_posts(
			array(
				'post_type'        => MATS_LEAD_TYPE,
				'post_status'      => 'any',
				'posts_per_page'   => $per_page,
				'paged'            => max( 1, $page ),
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => true,
				'meta_key'         => mats_lead_meta_key( 'email' ), // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'       => $email, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		)
	);
}

/* ═══════════════════════════════════════════════════════════════════════
 * WPForms → lead
 * ═════════════════════════════════════════════════════════════════════ */

/** [ form ID => language ] of the MatSurfer contact forms (Ajustes de la web). */
function mats_lead_form_ids(): array {
	$map = array();
	foreach ( mats_langs() as $lang ) {
		$id = absint( mats_setting( 'form_' . $lang, $lang ) );
		if ( $id && ! isset( $map[ $id ] ) ) {
			$map[ $id ] = $lang;
		}
	}
	return (array) apply_filters( 'mats_lead_form_ids', $map );
}

/** Tracking posted with the form ($_POST['matsurfer_meta']), raw (sanitised in mats_lead_normalize()). */
function mats_lead_tracking_from_request(): array {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- runs inside WPForms' already verified submission; every value is sanitised in mats_lead_normalize().
	$raw = isset( $_POST['matsurfer_meta'] ) && is_array( $_POST['matsurfer_meta'] ) ? wp_unslash( $_POST['matsurfer_meta'] ) : array();
	$out = array();
	foreach ( array( 'cta_source', 'utm', 'application', 'language', 'source_page' ) as $key ) {
		$out[ $key ] = isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ? (string) $raw[ $key ] : '';
	}
	return $out;
}

/**
 * wpforms_process_complete: stores a copy of every submission of the
 * MatSurfer forms. Returns the lead ID (0 when the form is not ours).
 */
function mats_lead_from_wpforms( $fields, $entry = array(), $form_data = array(), $entry_id = 0 ): int {
	$form_id = absint( is_array( $form_data ) ? ( $form_data['id'] ?? 0 ) : 0 );
	$forms   = mats_lead_form_ids();
	if ( ! $form_id || ! isset( $forms[ $form_id ] ) ) {
		return 0;
	}
	$fields = is_array( $fields ) ? $fields : array();
	$value  = static function ( int $field_id ) use ( $fields ): string {
		$field = $fields[ $field_id ] ?? ( $fields[ (string) $field_id ] ?? null );
		if ( ! is_array( $field ) || ! isset( $field['value'] ) ) {
			return '';
		}
		return is_scalar( $field['value'] ) ? (string) $field['value'] : '';
	};

	$tracking = mats_lead_tracking_from_request();
	$lang     = $forms[ $form_id ];
	/* one single form for both languages: trust the language sent with it */
	if ( absint( mats_setting( 'form_en', 'en' ) ) === absint( mats_setting( 'form_es', 'es' ) ) && in_array( $tracking['language'], mats_langs(), true ) ) {
		$lang = $tracking['language'];
	}

	/* fields added later in WPForms are kept too */
	$extra = array();
	foreach ( $fields as $field_id => $field ) {
		if ( ! is_array( $field ) || ( (int) $field_id >= 1 && (int) $field_id <= 8 ) ) {
			continue;
		}
		if ( in_array( (string) ( $field['type'] ?? '' ), array( 'html', 'divider', 'pagebreak', 'captcha', 'content', 'entry-preview', 'layout', 'repeater' ), true ) ) {
			continue;
		}
		$extra[] = array(
			'label' => (string) ( $field['name'] ?? '' ) ?: 'Campo ' . (int) $field_id,
			'value' => isset( $field['value'] ) && is_scalar( $field['value'] ) ? (string) $field['value'] : '',
		);
	}

	return mats_lead_save(
		array(
			'name'         => $value( 1 ),
			'email'        => $value( 2 ),
			'organisation' => $value( 3 ),
			'role'         => $value( 4 ),
			'interest'     => $value( 5 ),
			'area'         => $value( 6 ),
			'message'      => $value( 7 ),
			'consent'      => '' !== trim( $value( 8 ) ),
			'language'     => $lang,
			'cta_source'   => $tracking['cta_source'],
			'utm'          => $tracking['utm'],
			'application'  => $tracking['application'],
			'source_page'  => $tracking['source_page'],
			'form_id'      => $form_id,
			'extra'        => $extra,
		)
	);
}
add_action( 'wpforms_process_complete', 'mats_lead_from_wpforms', 10, 4 );

/* ═══════════════════════════════════════════════════════════════════════
 * Display helpers
 * ═════════════════════════════════════════════════════════════════════ */

/** "15 oct 2026, 10:32" (always Spanish, site time zone). */
function mats_leads_date( int $ts, bool $with_time = true ): string {
	if ( ! $ts ) {
		return '';
	}
	$months = array( 1 => 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sept', 'oct', 'nov', 'dic' );
	$out    = wp_date( 'j', $ts ) . ' ' . $months[ (int) wp_date( 'n', $ts ) ] . ' ' . wp_date( 'Y', $ts );
	return $with_time ? $out . ', ' . wp_date( 'H:i', $ts ) : $out;
}

/** "Hoy, 10:32" / "Ayer, 18:05" / "15 oct 2026". */
function mats_leads_short_date( int $ts ): string {
	if ( ! $ts ) {
		return '';
	}
	$day = wp_date( 'Y-m-d', $ts );
	if ( wp_date( 'Y-m-d' ) === $day ) {
		return 'Hoy, ' . wp_date( 'H:i', $ts );
	}
	if ( wp_date( 'Y-m-d', time() - DAY_IN_SECONDS ) === $day ) {
		return 'Ayer, ' . wp_date( 'H:i', $ts );
	}
	return mats_leads_date( $ts, false );
}

/** "hace 5 minutos", "hace 3 días"… ('' after a month). */
function mats_leads_ago( int $ts ): string {
	$diff = time() - $ts;
	if ( $diff < 0 || $diff >= 30 * DAY_IN_SECONDS ) {
		return '';
	}
	if ( $diff < MINUTE_IN_SECONDS ) {
		return 'hace un momento';
	}
	$units = array(
		array( DAY_IN_SECONDS, 'día', 'días' ),
		array( HOUR_IN_SECONDS, 'hora', 'horas' ),
		array( MINUTE_IN_SECONDS, 'minuto', 'minutos' ),
	);
	foreach ( $units as $unit ) {
		if ( $diff >= $unit[0] ) {
			$n = (int) floor( $diff / $unit[0] );
			return 'hace ' . $n . ' ' . ( 1 === $n ? $unit[1] : $unit[2] );
		}
	}
	return '';
}

/** sprintf for "1 contacto" / "3 contactos". */
function mats_leads_plural( int $n, string $one, string $many ): string {
	return sprintf( 1 === $n ? $one : $many, $n );
}

/** Spanish label of a form choice (stored as the slug or the EN/ES label). */
function mats_lead_choice_label( string $group, string $value ): string {
	static $maps = array();
	$value = trim( $value );
	if ( '' === $value ) {
		return '';
	}
	if ( ! isset( $maps[ $group ] ) ) {
		$maps[ $group ] = array();
		$data           = function_exists( 'mats_content_data' ) ? mats_content_data() : array();
		$key            = 'area' === $group ? 'areaOfInterest' : 'interest';
		$es             = array();
		foreach ( (array) ( $data['es']['contact']['form'][ $key ]['options'] ?? array() ) as $option ) {
			if ( isset( $option['value'], $option['label'] ) ) {
				$es[ (string) $option['value'] ] = (string) $option['label'];
			}
		}
		foreach ( array( 'en', 'es' ) as $lang ) {
			foreach ( (array) ( $data[ $lang ]['contact']['form'][ $key ]['options'] ?? array() ) as $option ) {
				if ( ! isset( $option['value'], $option['label'] ) || ! isset( $es[ (string) $option['value'] ] ) ) {
					continue;
				}
				$label = $es[ (string) $option['value'] ];

				$maps[ $group ][ strtolower( (string) $option['value'] ) ] = $label;
				$maps[ $group ][ strtolower( (string) $option['label'] ) ] = $label;
			}
		}
	}
	return $maps[ $group ][ strtolower( $value ) ] ?? $value;
}

/**
 * Spanish description of the button that brought the visitor to the form
 * (its data-cta-source; '' when unknown). Covers every source the theme
 * prints: the Astro ones (header, footer, hero*, home-*, <page>-final,
 * applications-<slug>) and the WordPress-only ones (news templates, the
 * "Últimas noticias" section, the 404 page). $lang 'en' gives the English
 * description (notification email of the English form, inc/modules/wpforms.php).
 */
function mats_lead_cta_label( string $source, string $lang = 'es' ): string {
	if ( 'en' === $lang ) {
		return mats_lead_cta_label_en( $source );
	}
	$fixed = array(
		'direct'             => 'Ninguno: llegó directamente al formulario',
		'header'             => 'Botón de la cabecera (arriba del todo)',
		'footer'             => 'Botón del pie de página',
		'hero'               => 'Portada: botón principal',
		'hero-product'       => 'Portada: botón para ver el producto',
		'home-product'       => 'Portada: sección del producto',
		'home-applications'  => 'Portada: sección de aplicaciones',
		'home-research'      => 'Portada: sección de casos de estudio',
		'home-team'          => 'Portada: sección del equipo',
		'home-final'         => 'Portada: llamada final',
		'product-hero'       => 'Producto: botón de la cabecera',
		'product-final'      => 'Producto: llamada final',
		'applications-final' => 'Aplicaciones: llamada final',
		'research-final'     => 'Casos de estudio: llamada final',
		'team-final'         => 'Equipo: llamada final',
		'latest-news'        => 'Sección «Últimas noticias»: su botón (normalmente, ver todas las noticias)',
		'news-empty'         => 'Noticias: botón de contacto (cuando todavía no hay noticias)',
		'news-final'         => 'Noticias: llamada final (lista de noticias o de una categoría)',
		'news-article-final' => 'Noticia: llamada final (al pie de un artículo)',
		'search-final'       => 'Búsqueda: llamada final (página de resultados)',
		'404-home'           => 'Página no encontrada (error 404): botón para volver a la portada',
	);
	if ( isset( $fixed[ $source ] ) ) {
		return $fixed[ $source ];
	}
	if ( str_starts_with( $source, 'applications-' ) ) {
		$title = mats_lead_application_title( substr( $source, 13 ) );
		return $title ? 'Aplicaciones: botón de «' . $title . '»' : '';
	}
	/* closing band of any other page ("<page role>-final") */
	$pages = array(
		'home'         => 'Portada',
		'product'      => 'Producto',
		'applications' => 'Aplicaciones',
		'research'     => 'Casos de estudio',
		'team'         => 'Equipo',
		'contact'      => 'Contacto',
		'news'         => 'Noticias',
		'privacy'      => 'Política de privacidad',
		'legal'        => 'Aviso legal',
	);
	if ( str_ends_with( $source, '-final' ) && isset( $pages[ substr( $source, 0, -6 ) ] ) ) {
		return $pages[ substr( $source, 0, -6 ) ] . ': llamada final';
	}
	return '';
}

/** English version of mats_lead_cta_label() (same sources). */
function mats_lead_cta_label_en( string $source ): string {
	$fixed = array(
		'direct'             => 'None: came straight to the form',
		'header'             => 'Header button (top of the page)',
		'footer'             => 'Footer button',
		'hero'               => 'Home: main button',
		'hero-product'       => 'Home: button to see the product',
		'home-product'       => 'Home: product section',
		'home-applications'  => 'Home: applications section',
		'home-research'      => 'Home: case studies section',
		'home-team'          => 'Home: team section',
		'home-final'         => 'Home: closing call to action',
		'product-hero'       => 'Product: header button',
		'product-final'      => 'Product: closing call to action',
		'applications-final' => 'Applications: closing call to action',
		'research-final'     => 'Case Studies: closing call to action',
		'team-final'         => 'Team: closing call to action',
		'latest-news'        => '“Latest news” section: its button (usually, see all news)',
		'news-empty'         => 'News: contact button (while there is no news yet)',
		'news-final'         => 'News: closing call to action (news list or a category)',
		'news-article-final' => 'News article: closing call to action (end of an article)',
		'search-final'       => 'Search: closing call to action (results page)',
		'404-home'           => 'Page not found (404 error): button back to the home page',
	);
	if ( isset( $fixed[ $source ] ) ) {
		return $fixed[ $source ];
	}
	if ( str_starts_with( $source, 'applications-' ) ) {
		$title = mats_lead_application_title( substr( $source, 13 ), 'en' );
		return $title ? 'Applications: button of “' . $title . '”' : '';
	}
	$pages = array(
		'home'         => 'Home',
		'product'      => 'Product',
		'applications' => 'Applications',
		'research'     => 'Case Studies',
		'team'         => 'Team',
		'contact'      => 'Contact',
		'news'         => 'News',
		'privacy'      => 'Privacy Policy',
		'legal'        => 'Legal Notice',
	);
	if ( str_ends_with( $source, '-final' ) && isset( $pages[ substr( $source, 0, -6 ) ] ) ) {
		return $pages[ substr( $source, 0, -6 ) ] . ': closing call to action';
	}
	return '';
}

/** Title (in Spanish, or in $lang) of an application by slug, '' when unknown. */
function mats_lead_application_title( string $slug, string $lang = 'es' ): string {
	if ( '' === $slug || ! function_exists( 'mats_get_applications' ) ) {
		return '';
	}
	foreach ( mats_get_applications( 'en' === $lang ? 'en' : 'es' ) as $app ) {
		if ( $app['slug'] === $slug ) {
			return (string) $app['title'];
		}
	}
	return '';
}

/** Status pill. */
function mats_lead_status_badge( string $status ): string {
	$labels = mats_lead_statuses();
	$status = isset( $labels[ $status ] ) ? $status : 'new';
	return '<span class="mats-status mats-status--' . esc_attr( $status ) . '">' . esc_html( $labels[ $status ] ) . '</span>';
}

/** Language flag + code. */
function mats_lead_lang_badge( string $lang ): string {
	$names = mats_lead_languages();
	$lang  = isset( $names[ $lang ] ) ? $lang : 'en';
	return sprintf(
		'<span class="mats-lang mats-lang--%1$s" title="%2$s"><span class="mats-lang__flag" aria-hidden="true"></span><span aria-hidden="true">%3$s</span><span class="screen-reader-text">%4$s</span></span>',
		esc_attr( $lang ),
		esc_attr( 'Formulario en ' . strtolower( $names[ $lang ] ) ),
		esc_html( strtoupper( $lang ) ),
		esc_html( $names[ $lang ] )
	);
}

/** Absolute URL for a stored source page (only pages of this site are linked). */
function mats_lead_page_link( string $page ): string {
	if ( '' === $page ) {
		return '';
	}
	$home = wp_parse_url( home_url() );
	if ( str_starts_with( $page, '/' ) ) {
		return ( $home['scheme'] ?? 'https' ) . '://' . ( $home['host'] ?? '' ) . ( isset( $home['port'] ) ? ':' . $home['port'] : '' ) . $page;
	}
	return wp_parse_url( $page, PHP_URL_HOST ) === ( $home['host'] ?? '' ) ? $page : '';
}

/**
 * mailto: link to answer a lead, in the lead's language, quoting the
 * message. Built by hand because esc_url() strips encoded line breaks;
 * every part is percent-encoded and the address is validated, so the
 * result is safe inside esc_attr().
 */
function mats_lead_mailto( array $lead ): string {
	$email = (string) $lead['email'];
	if ( ! is_email( $email ) ) {
		return '';
	}
	list( $local, $domain ) = explode( '@', $email, 2 );
	$es      = 'es' === $lead['language'];
	$name    = (string) $lead['name'];
	$subject = $es ? 'Re: tu mensaje a MatSurfer' : 'Re: your message to MatSurfer';
	$hello   = $es ? ( $name ? 'Hola, ' . $name . ':' : 'Hola:' ) : ( $name ? 'Hello ' . $name . ',' : 'Hello,' );
	$message = (string) $lead['message'];
	if ( mb_strlen( $message ) > 700 ) {
		$message = rtrim( mb_substr( $message, 0, 700 ) ) . '…';
	}
	$date  = wp_date( 'd/m/Y', $lead['date'] );
	$quote = '';
	if ( '' !== $message ) {
		$quote = "\n\n\n\n" . ( $es ? 'El ' . $date . ' escribiste:' : 'On ' . $date . ' you wrote:' ) . "\n> " . str_replace( "\n", "\n> ", str_replace( "\r", '', $message ) );
	}
	return 'mailto:' . rawurlencode( $local ) . '@' . $domain . '?subject=' . rawurlencode( $subject ) . '&body=' . rawurlencode( $hello . $quote );
}

/* ═══════════════════════════════════════════════════════════════════════
 * Admin: menu, screens, actions
 * ═════════════════════════════════════════════════════════════════════ */

function mats_leads_url( array $args = array() ): string {
	return add_query_arg( array_merge( array( 'page' => MATS_LEADS_PAGE ), $args ), admin_url( 'admin.php' ) );
}

function mats_lead_url( int $id ): string {
	return mats_leads_url( array( 'lead' => $id ) );
}

/** List filters from the request (read-only, sanitised). */
function mats_leads_request_filters(): array {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters, sanitised below.
	$get    = static fn( string $key ): string => isset( $_REQUEST[ $key ] ) && is_scalar( $_REQUEST[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST[ $key ] ) ) : '';
	$status = sanitize_key( $get( 'lead_status' ) );
	$lang   = sanitize_key( $get( 'lead_lang' ) );
	$date   = static fn( string $v ): string => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : '';
	$filters = array(
		'status'  => isset( mats_lead_statuses()[ $status ] ) ? $status : '',
		'lang'    => in_array( $lang, mats_langs(), true ) ? $lang : '',
		's'       => mb_substr( trim( wp_specialchars_decode( $get( 's' ), ENT_QUOTES ) ), 0, 100 ), // plain text, like the stored leads
		'orderby' => 'name' === $get( 'orderby' ) ? 'name' : 'date',
		'order'   => '',
		'from'    => $date( $get( 'lead_from' ) ),
		'to'      => $date( $get( 'lead_to' ) ),
	);
	$order            = strtolower( $get( 'order' ) );
	$filters['order'] = in_array( $order, array( 'asc', 'desc' ), true ) ? $order : ( 'name' === $filters['orderby'] ? 'asc' : 'desc' );
	// phpcs:enable
	return $filters;
}

/** List URL keeping the given filters. */
function mats_leads_list_url( array $f = array(), array $extra = array() ): string {
	$args = array(
		'lead_status' => $f['status'] ?? '',
		'lead_lang'   => $f['lang'] ?? '',
		's'           => $f['s'] ?? '',
	);
	if ( 'name' === ( $f['orderby'] ?? '' ) ) {
		$args['orderby'] = 'name';
		$args['order']   = $f['order'] ?? 'asc';
	} elseif ( 'asc' === ( $f['order'] ?? '' ) ) {
		$args['orderby'] = 'date';
		$args['order']   = 'asc';
	}
	$args = array_filter( array_merge( $args, $extra ), static fn( $v ) => '' !== $v && null !== $v );
	return mats_leads_url( array_map( 'rawurlencode', array_map( 'strval', $args ) ) );
}

/** Lead being viewed (admin.php?page=mats-leads&lead=ID), 0 on the list. */
function mats_leads_current_lead_id(): int {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view parameter.
	return isset( $_GET['lead'] ) && is_scalar( $_GET['lead'] ) ? absint( $_GET['lead'] ) : 0;
}

/* ── menu with the red bubble ─────────────────────────────────────────── */

function mats_leads_menu_title(): string {
	$count = current_user_can( MATS_LEAD_CAP ) ? mats_leads_count( 'new' ) : 0;
	$title = 'Contactos';
	if ( $count > 0 ) {
		$title .= sprintf(
			' <span class="awaiting-mod count-%1$d"><span class="pending-count" aria-hidden="true">%1$d</span><span class="screen-reader-text">%2$s</span></span>',
			$count,
			esc_html( mats_leads_plural( $count, '%d contacto nuevo', '%d contactos nuevos' ) )
		);
	}
	return $title;
}

add_action(
	'admin_menu',
	static function () {
		$hook = add_menu_page(
			'Contactos',
			mats_leads_menu_title(),
			MATS_LEAD_CAP,
			MATS_LEADS_PAGE,
			'mats_leads_render_page',
			'dashicons-email-alt',
			3
		);
		if ( $hook ) {
			$GLOBALS['mats_leads_hook'] = $hook;
			add_action( 'load-' . $hook, 'mats_leads_load' );
		}
	}
);

/** Updates the bubble after a status change made while loading the page. */
function mats_leads_refresh_menu_bubble(): void {
	global $menu;
	if ( ! is_array( $menu ) ) {
		return;
	}
	foreach ( $menu as $position => $item ) {
		if ( isset( $item[2] ) && MATS_LEADS_PAGE === $item[2] ) {
			$menu[ $position ][0] = mats_leads_menu_title(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		}
	}
}

/* Old-style screens of the post type (typed by hand, old links) → our screens. */
add_action(
	'load-post.php',
	static function () {
		// phpcs:disable WordPress.Security.NonceVerification -- only redirects, never processes.
		$raw = $_REQUEST['post'] ?? ( $_POST['post_ID'] ?? 0 );
		// phpcs:enable
		$id = is_scalar( $raw ) ? absint( $raw ) : 0;
		if ( $id && mats_lead_exists( $id ) ) {
			wp_safe_redirect( mats_lead_url( $id ) );
			exit;
		}
	},
	1
);
foreach ( array( 'load-edit.php', 'load-post-new.php' ) as $mats_leads_screen ) {
	add_action(
		$mats_leads_screen,
		static function () {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only redirects.
			if ( isset( $_GET['post_type'] ) && is_scalar( $_GET['post_type'] ) && MATS_LEAD_TYPE === sanitize_key( wp_unslash( $_GET['post_type'] ) ) ) {
				wp_safe_redirect( mats_leads_url() );
				exit;
			}
		},
		1
	);
}
unset( $mats_leads_screen );

add_filter( 'get_edit_post_link', static fn( $link, $id = 0 ) => mats_lead_exists( (int) $id ) ? mats_lead_url( (int) $id ) : $link, 10, 2 );

add_filter(
	'removable_query_args',
	static function ( $args ) {
		$args[] = 'mats_done';
		$args[] = 'mats_n';
		return $args;
	}
);

/** Browser tab title on the detail screen. */
add_filter(
	'admin_title',
	static function ( $admin_title, $title ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$lead   = $screen && isset( $GLOBALS['mats_leads_hook'] ) && $screen->id === $GLOBALS['mats_leads_hook'] ? mats_lead_get( mats_leads_current_lead_id() ) : null;
		if ( $lead ) {
			$name = $lead['name'] ?: $lead['email'];
			return $name . ' ‹ ' . $admin_title;
		}
		return $admin_title;
	},
	10,
	2
);

/* ── actions on the Contactos screen (before any output) ──────────────── */

/** Applies an action to a set of leads; returns how many changed. */
function mats_leads_apply( string $action, array $ids ): int {
	$n = 0;
	foreach ( array_unique( array_filter( array_map( 'absint', $ids ) ) ) as $id ) {
		if ( ! mats_lead_exists( $id ) ) {
			continue;
		}
		if ( 'delete' === $action ) {
			$n += mats_lead_delete( $id ) ? 1 : 0;
		} elseif ( isset( mats_lead_statuses()[ $action ] ) ) {
			$n += mats_lead_set_status( $id, $action ) ? 1 : 0;
		}
	}
	return $n;
}

/** Last page of the list for a set of filters (1 when it is empty). */
function mats_leads_last_page( array $filters ): int {
	$args                           = mats_leads_query_args( $filters );
	$args['posts_per_page']         = 1;
	$args['paged']                  = 1;
	$args['fields']                 = 'ids';
	$args['update_post_meta_cache'] = false;
	$args['update_post_term_cache'] = false;
	$total                          = (int) ( new WP_Query( $args ) )->found_posts;
	return max( 1, (int) ceil( $total / MATS_LEADS_PER_PAGE ) );
}

/** Current URL without action parameters (to come back after an action). */
function mats_leads_clean_request_url(): string {
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	$keep = array();
	wp_parse_str( (string) wp_parse_url( $uri, PHP_URL_QUERY ), $query );
	foreach ( array( 'lead_status', 'lead_lang', 's', 'orderby', 'order', 'paged' ) as $key ) {
		if ( isset( $query[ $key ] ) && is_scalar( $query[ $key ] ) && '' !== (string) $query[ $key ] && ! ( 'paged' === $key && (int) $query[ $key ] < 2 ) ) {
			$keep[ $key ] = rawurlencode( wp_specialchars_decode( sanitize_text_field( (string) $query[ $key ] ), ENT_QUOTES ) );
		}
	}
	return mats_leads_url( $keep );
}

function mats_leads_load(): void {
	if ( ! current_user_can( MATS_LEAD_CAP ) ) {
		wp_die( esc_html( 'No tienes permiso para ver los contactos.' ), 403 );
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- nonces are checked below before anything changes.

	/* row action: ?mats_do=answered&lead_id=ID&_wpnonce=… */
	$do = isset( $_GET['mats_do'] ) ? sanitize_key( wp_unslash( $_GET['mats_do'] ) ) : '';
	if ( '' !== $do ) {
		$id = isset( $_GET['lead_id'] ) && is_scalar( $_GET['lead_id'] ) ? absint( $_GET['lead_id'] ) : 0;
		check_admin_referer( 'mats_lead_' . $id );
		$n = in_array( $do, array( 'new', 'read', 'answered', 'delete' ), true ) ? mats_leads_apply( $do, array( $id ) ) : 0;
		wp_safe_redirect(
			add_query_arg(
				array(
					'mats_done' => 'delete' === $do ? 'deleted' : $do,
					'mats_n'    => $n,
				),
				mats_leads_clean_request_url()
			)
		);
		exit;
	}

	/* bulk actions (list form, GET like the core lists) */
	$bulk = '';
	if ( empty( $_REQUEST['filter_action'] ) ) {
		foreach ( array( 'action', 'action2' ) as $field ) {
			$value = isset( $_REQUEST[ $field ] ) && is_scalar( $_REQUEST[ $field ] ) ? sanitize_key( wp_unslash( $_REQUEST[ $field ] ) ) : '';
			if ( in_array( $value, array( 'new', 'read', 'answered', 'delete' ), true ) ) {
				$bulk = $value;
				break;
			}
		}
	}
	if ( '' !== $bulk ) {
		check_admin_referer( 'bulk-mats_leads' );
		$ids  = isset( $_REQUEST['leads'] ) ? array_map( 'absint', (array) wp_unslash( $_REQUEST['leads'] ) ) : array();
		$done = $ids ? ( 'delete' === $bulk ? 'deleted' : $bulk ) : 'none';
		$n    = $ids ? mats_leads_apply( $bulk, $ids ) : 0;
		wp_safe_redirect(
			add_query_arg(
				array(
					'mats_done' => $done,
					'mats_n'    => $n,
				),
				mats_leads_clean_request_url()
			)
		);
		exit;
	}

	/* search / filter submissions: tidy URL like the core lists */
	$action = isset( $_GET['action'] ) && is_scalar( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
	if ( ! empty( $_GET['_wp_http_referer'] ) || '-1' === $action ) {
		wp_safe_redirect( mats_leads_clean_request_url() );
		exit;
	}

	/*
	 * A page past the end of the list (the last rows of the last page were
	 * just deleted or changed status out of the current view, or an old
	 * link): go to the last page that still has contacts, keeping the
	 * filters and the "done" message. Done here because the list itself is
	 * drawn after the admin header, too late for WP_List_Table's redirect.
	 */
	$paged = isset( $_GET['paged'] ) && is_scalar( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 0;
	if ( $paged > 1 && ! mats_leads_current_lead_id() ) {
		$last = mats_leads_last_page( mats_leads_request_filters() );
		if ( $paged > $last ) {
			$notice = array();
			if ( isset( $_GET['mats_done'] ) && is_scalar( $_GET['mats_done'] ) ) {
				$notice['mats_done'] = sanitize_key( wp_unslash( $_GET['mats_done'] ) );
				$notice['mats_n']    = isset( $_GET['mats_n'] ) && is_scalar( $_GET['mats_n'] ) ? absint( $_GET['mats_n'] ) : 0;
			}
			$url = remove_query_arg( 'paged', mats_leads_clean_request_url() );
			if ( $last > 1 ) {
				$notice['paged'] = $last;
			}
			wp_safe_redirect( add_query_arg( $notice, $url ) );
			exit;
		}
	}
	// phpcs:enable

	/* detail screen: opening a new lead marks it as read */
	$id = mats_leads_current_lead_id();
	if ( $id ) {
		if ( ! mats_lead_exists( $id ) ) {
			wp_safe_redirect( mats_leads_url( array( 'mats_done' => 'missing' ) ) );
			exit;
		}
		if ( 'new' === get_post_meta( $id, mats_lead_meta_key( 'status' ), true ) ) {
			mats_lead_set_status( $id, 'read' );
			mats_leads_refresh_menu_bubble();
		}
	}
}

/* ── admin-post handlers (detail screen, export, settings) ────────────── */

/** Lead ID posted to an admin-post handler. */
function mats_leads_posted_id(): int {
	// phpcs:ignore WordPress.Security.NonceVerification -- the caller verifies the nonce for this ID.
	return isset( $_REQUEST['lead'] ) && is_scalar( $_REQUEST['lead'] ) ? absint( $_REQUEST['lead'] ) : 0;
}

function mats_leads_deny(): void {
	wp_die( esc_html( 'No tienes permiso para hacer esto. Si crees que es un error, habla con quien administra la web.' ), esc_html( 'Sin permiso' ), array( 'response' => 403 ) );
}

add_action(
	'admin_post_mats_lead_status',
	static function () {
		if ( ! current_user_can( MATS_LEAD_CAP ) ) {
			mats_leads_deny();
		}
		$id = mats_leads_posted_id();
		check_admin_referer( 'mats_lead_status_' . $id );
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$ok     = mats_lead_set_status( $id, $status );
		wp_safe_redirect(
			mats_leads_url(
				array(
					'lead'      => $id,
					'mats_done' => $ok ? 'status' : 'error',
				)
			)
		);
		exit;
	}
);

add_action(
	'admin_post_mats_lead_delete',
	static function () {
		if ( ! current_user_can( MATS_LEAD_CAP ) ) {
			mats_leads_deny();
		}
		$id = mats_leads_posted_id();
		check_admin_referer( 'mats_lead_delete_' . $id );
		$n = mats_leads_apply( 'delete', array( $id ) );
		wp_safe_redirect(
			mats_leads_url(
				array(
					'mats_done' => 'deleted',
					'mats_n'    => $n,
				)
			)
		);
		exit;
	}
);

add_action( 'admin_post_mats_leads_export', 'mats_leads_export_csv' );

add_action(
	'admin_post_mats_leads_settings',
	static function () {
		if ( ! current_user_can( MATS_LEAD_CAP ) || ! current_user_can( 'manage_options' ) ) {
			mats_leads_deny();
		}
		check_admin_referer( 'mats_leads_settings' );
		$months = isset( $_POST['retention'] ) ? absint( wp_unslash( $_POST['retention'] ) ) : 0;
		if ( ! isset( mats_leads_retention_options()[ $months ] ) ) {
			$months = 0;
		}
		update_option( 'mats_leads_retention_months', $months, true ); // read on every admin request (cron sync)
		mats_leads_sync_cron();
		$deleted = $months ? mats_leads_cleanup( $months ) : 0;
		wp_safe_redirect(
			mats_leads_url(
				array(
					'mats_done' => 'settings',
					'mats_n'    => $deleted,
				)
			) . '#mats-leads-retention'
		);
		exit;
	}
);

/* ── CSV export ───────────────────────────────────────────────────────── */

/** Column headers of the CSV file. */
function mats_leads_csv_header(): array {
	return array( 'Fecha', 'Estado', 'Nombre', 'Email', 'Organización', 'Cargo', 'Le interesa', 'Área de interés', 'Mensaje', 'Acepta la privacidad', 'Fecha de aceptación', 'Idioma', 'Botón de origen', 'Botón de origen (qué es)', 'Página de origen', 'Aplicación', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'Otros campos', 'Atendido el', 'Referencia' );
}

function mats_leads_csv_row( array $lead ): array {
	$extra = array();
	foreach ( $lead['extra'] as $item ) {
		$extra[] = ( $item['label'] ?? '' ) . ': ' . ( $item['value'] ?? '' );
	}
	$consent_ts = $lead['consent_date'] ? strtotime( get_gmt_from_date( $lead['consent_date'] ) . ' UTC' ) : 0;
	return array(
		wp_date( 'd/m/Y H:i', $lead['date'] ),
		mats_lead_statuses()[ $lead['status'] ],
		$lead['name'],
		$lead['email'],
		$lead['organisation'],
		$lead['role'],
		mats_lead_choice_label( 'interest', $lead['interest'] ),
		mats_lead_choice_label( 'area', $lead['area'] ),
		$lead['message'],
		'yes' === $lead['consent'] ? 'Sí' : 'No',
		$consent_ts ? wp_date( 'd/m/Y H:i', $consent_ts ) : '',
		mats_lead_languages()[ $lead['language'] ],
		$lead['cta_source'],
		'' !== $lead['cta_source'] ? mats_lead_cta_label( $lead['cta_source'] ) : '',
		$lead['source_page'],
		$lead['application'],
		$lead['utm']['source'] ?? '',
		$lead['utm']['medium'] ?? '',
		$lead['utm']['campaign'] ?? '',
		$lead['utm']['content'] ?? '',
		$lead['utm']['term'] ?? '',
		implode( ' | ', $extra ),
		$lead['answered_at'] ? wp_date( 'd/m/Y H:i', $lead['answered_at'] ) : '',
		'#' . $lead['id'],
	);
}

/** Neutralises spreadsheet formulas (=, +, -, @ …) so a cell never runs as a formula in Excel. */
function mats_leads_csv_cell( $value ): string {
	$value = str_replace( "\r\n", "\n", (string) $value );
	if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
		$value = "'" . $value;
	}
	return $value;
}

function mats_leads_export_csv(): void {
	if ( ! current_user_can( MATS_LEAD_CAP ) ) {
		mats_leads_deny();
	}
	check_admin_referer( 'mats_leads_export' );

	$filters                = mats_leads_request_filters();
	$args                   = mats_leads_query_args( $filters );
	$args['posts_per_page'] = -1;
	$args['fields']         = 'ids';
	$args['no_found_rows']  = true;
	$ids                    = ( new WP_Query( $args ) )->posts;

	$name = 'contactos-matsurfer-' . wp_date( 'Y-m-d' );
	if ( $filters['status'] ) {
		$name .= '-' . sanitize_file_name( strtolower( mats_lead_statuses()[ $filters['status'] ] ) );
	}
	if ( $filters['lang'] ) {
		$name .= '-' . $filters['lang'];
	}

	while ( ob_get_level() > 0 ) {
		ob_end_clean();
	}
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . remove_accents( $name ) . '.csv"' );
	header( 'X-Content-Type-Options: nosniff' );

	$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- UTF-8 BOM: Excel then opens accents correctly
	fputcsv( $out, mats_leads_csv_header(), ';', '"', '', "\r\n" );
	foreach ( array_chunk( array_map( 'intval', $ids ), 200 ) as $chunk ) {
		_prime_post_caches( $chunk, false, false );
		update_meta_cache( 'post', $chunk );
		foreach ( $chunk as $id ) {
			$lead = mats_lead_get( $id );
			if ( $lead ) {
				fputcsv( $out, array_map( 'mats_leads_csv_cell', mats_leads_csv_row( $lead ) ), ';', '"', '', "\r\n" );
			}
		}
	}
	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}

/* ── automatic deletion (privacy) ─────────────────────────────────────── */

/** Months => label for the retention selector (0 = never). */
function mats_leads_retention_options(): array {
	return array(
		0  => 'No borrar nunca',
		6  => 'Borrar los que tengan más de 6 meses',
		12 => 'Borrar los que tengan más de 1 año',
		18 => 'Borrar los que tengan más de 18 meses',
		24 => 'Borrar los que tengan más de 2 años',
		36 => 'Borrar los que tengan más de 3 años',
	);
}

function mats_leads_retention(): int {
	$months = (int) get_option( 'mats_leads_retention_months', 0 );
	return isset( mats_leads_retention_options()[ $months ] ) ? $months : 0;
}

/** Deletes leads older than $months (default: the saved setting). Returns how many. */
function mats_leads_cleanup( ?int $months = null ): int {
	$months = null === $months ? mats_leads_retention() : $months;
	if ( $months < 1 ) {
		return 0;
	}
	$before  = gmdate( 'Y-m-d H:i:s', (int) strtotime( '-' . $months . ' months' ) );
	$deleted = 0;
	for ( $round = 0; $round < 20; $round++ ) {
		$ids = get_posts(
			array(
				'post_type'        => MATS_LEAD_TYPE,
				'post_status'      => 'any',
				'posts_per_page'   => 100,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'date_query'       => array(
					array(
						'column' => 'post_date_gmt',
						'before' => $before,
					),
				),
			)
		);
		foreach ( $ids as $id ) {
			$deleted += wp_delete_post( (int) $id, true ) ? 1 : 0;
		}
		if ( count( $ids ) < 100 ) {
			break;
		}
	}
	update_option(
		'mats_leads_cleanup_last',
		array(
			'time'    => time(),
			'deleted' => $deleted,
			'months'  => $months,
		),
		false
	);
	mats_leads_flush_counts();
	return $deleted;
}

/** Daily event only while automatic deletion is on. */
function mats_leads_sync_cron(): void {
	$scheduled = wp_next_scheduled( 'mats_leads_cleanup' );
	if ( mats_leads_retention() > 0 && ! $scheduled ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'mats_leads_cleanup' );
	} elseif ( 0 === mats_leads_retention() && $scheduled ) {
		wp_clear_scheduled_hook( 'mats_leads_cleanup' );
	}
}

add_action( 'mats_leads_cleanup', static fn() => mats_leads_cleanup() );
add_action( 'admin_init', 'mats_leads_sync_cron' );
add_action( 'after_switch_theme', 'mats_leads_sync_cron' );
add_action( 'switch_theme', static fn() => wp_clear_scheduled_hook( 'mats_leads_cleanup' ) );

/* ── GDPR: personal data exporter + eraser (Herramientas → Privacidad) ── */

add_filter(
	'wp_privacy_personal_data_exporters',
	static function ( $exporters ) {
		$exporters['matsurfer-leads'] = array(
			'exporter_friendly_name' => 'Mensajes del formulario de contacto (MatSurfer)',
			'callback'               => 'mats_leads_privacy_export',
		);
		return $exporters;
	}
);

add_filter(
	'wp_privacy_personal_data_erasers',
	static function ( $erasers ) {
		$erasers['matsurfer-leads'] = array(
			'eraser_friendly_name' => 'Mensajes del formulario de contacto (MatSurfer)',
			'callback'             => 'mats_leads_privacy_erase',
		);
		return $erasers;
	}
);

function mats_leads_privacy_export( $email, $page = 1 ): array {
	$per   = 50;
	$ids   = mats_leads_ids_by_email( (string) $email, $per, (int) $page );
	$items = array();
	foreach ( $ids as $id ) {
		$lead = mats_lead_get( $id );
		if ( ! $lead ) {
			continue;
		}
		$utm = array();
		foreach ( $lead['utm'] as $key => $value ) {
			$utm[] = 'utm_' . $key . '=' . $value;
		}
		$extra = array();
		foreach ( $lead['extra'] as $item ) {
			$extra[] = ( $item['label'] ?? '' ) . ': ' . ( $item['value'] ?? '' );
		}
		$pairs = array(
			'Fecha (Date)'                       => wp_date( 'd/m/Y H:i', $lead['date'] ),
			'Nombre (Name)'                      => $lead['name'],
			'Email'                              => $lead['email'],
			'Organización (Organisation)'        => $lead['organisation'],
			'Cargo (Role)'                       => $lead['role'],
			'Le interesa (Interest)'             => $lead['interest'],
			'Área de interés (Area of interest)' => $lead['area'],
			'Mensaje (Message)'                  => $lead['message'],
			'Acepta la privacidad (Consent)'     => 'yes' === $lead['consent'] ? 'Sí / Yes · ' . $lead['consent_date'] : 'No',
			'Idioma (Language)'                  => strtoupper( $lead['language'] ),
			'Página de origen (Source page)'     => $lead['source_page'],
			'Botón de origen (CTA source)'       => $lead['cta_source'],
			'Aplicación (Application)'           => $lead['application'],
			'Campaña (UTM)'                      => implode( ', ', $utm ),
			'Otros campos (Other fields)'        => implode( ' | ', $extra ),
		);
		$data  = array();
		foreach ( $pairs as $name => $value ) {
			if ( '' !== (string) $value ) {
				$data[] = array(
					'name'  => $name,
					/* WordPress treats exported values as HTML (wp_kses in the report): escape the plain text so 'Tc < 300 K' is not cut */
					'value' => esc_html( (string) $value ),
				);
			}
		}
		$items[] = array(
			'group_id'          => 'matsurfer-leads',
			'group_label'       => 'Formulario de contacto (Contact form)',
			'group_description' => 'Mensajes enviados a través del formulario de contacto de la web. (Messages sent through the website contact form.)',
			'item_id'           => 'mats-lead-' . $id,
			'data'              => $data,
		);
	}
	return array(
		'data' => $items,
		'done' => count( $ids ) < $per,
	);
}

function mats_leads_privacy_erase( $email, $page = 1 ): array {
	$per     = 50;
	$ids     = mats_leads_ids_by_email( (string) $email, $per, 1 ); // always page 1: erased items disappear
	$removed = 0;
	foreach ( $ids as $id ) {
		$removed += mats_lead_delete( $id ) ? 1 : 0;
	}
	return array(
		'items_removed'  => $removed > 0,
		'items_retained' => $removed < count( $ids ),
		'messages'       => $removed ? array( mats_leads_plural( $removed, 'Se ha borrado %d mensaje del formulario de contacto.', 'Se han borrado %d mensajes del formulario de contacto.' ) ) : array(),
		'done'           => count( $ids ) < $per,
	);
}

/* Suggested text for the privacy policy (Ajustes → Privacidad → Guía). */
add_action(
	'admin_init',
	static function () {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$text  = '<p>Cuando alguien escribe a través del formulario de contacto, la web guarda una copia del mensaje en el apartado privado «Contactos»: nombre, email, organización, cargo, qué le interesa, área de interés, mensaje, si ha aceptado la política de privacidad (y cuándo), el idioma del formulario y, para saber cómo ha llegado, la página y el botón desde los que escribió y los parámetros de campaña (UTM) del enlace.</p>';
		$text .= '<p>No se guarda la dirección IP ni los datos del navegador. Solo pueden ver estos mensajes las personas con cuenta de administrador o editor de la web.</p>';
		$text .= '<p>Los mensajes se conservan hasta que se borran a mano o, si está activado, se borran automáticamente pasado el plazo elegido en «Contactos». Cualquier persona puede pedir una copia de sus datos o que se borren (Herramientas → Exportar / Borrar datos personales).</p>';
		wp_add_privacy_policy_content( 'MatSurfer — formulario de contacto', wp_kses_post( $text ) );
	}
);

/* ── assets ───────────────────────────────────────────────────────────── */

add_action(
	'admin_enqueue_scripts',
	static function ( $hook ) {
		$ours = isset( $GLOBALS['mats_leads_hook'] ) && $hook === $GLOBALS['mats_leads_hook'];
		if ( ! $ours && ! ( 'index.php' === $hook && current_user_can( MATS_LEAD_CAP ) ) ) {
			return;
		}
		wp_enqueue_style( 'matsurfer-leads-admin', MATS_URI . '/assets/css/leads-admin.css', array(), mats_ver( 'assets/css/leads-admin.css' ) );
		if ( $ours && is_readable( MATS_DIR . '/assets/js/leads-admin.js' ) ) {
			wp_enqueue_script( 'matsurfer-leads-admin', MATS_URI . '/assets/js/leads-admin.js', array(), mats_ver( 'assets/js/leads-admin.js' ), true );
		}
	}
);

/* ═══════════════════════════════════════════════════════════════════════
 * Screens
 * ═════════════════════════════════════════════════════════════════════ */

function mats_leads_render_page(): void {
	if ( ! current_user_can( MATS_LEAD_CAP ) ) {
		mats_leads_deny();
	}
	$lead = mats_lead_get( mats_leads_current_lead_id() );
	if ( $lead ) {
		mats_leads_render_detail( $lead );
	} else {
		mats_leads_render_list();
	}
}

/** Success/error message after an action. */
function mats_leads_notices(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
	$done = isset( $_GET['mats_done'] ) ? sanitize_key( wp_unslash( $_GET['mats_done'] ) ) : '';
	$n    = isset( $_GET['mats_n'] ) ? absint( $_GET['mats_n'] ) : 0;
	// phpcs:enable
	if ( '' === $done ) {
		return;
	}
	$type = 'success';
	switch ( $done ) {
		case 'answered':
			$text = mats_leads_plural( $n, '%d contacto marcado como atendido.', '%d contactos marcados como atendidos.' );
			break;
		case 'read':
			$text = mats_leads_plural( $n, '%d contacto marcado como leído.', '%d contactos marcados como leídos.' );
			break;
		case 'new':
			$text = mats_leads_plural( $n, '%d contacto marcado como no leído.', '%d contactos marcados como no leídos.' );
			break;
		case 'deleted':
			$text = $n ? mats_leads_plural( $n, '%d contacto borrado definitivamente.', '%d contactos borrados definitivamente.' ) : 'No se ha borrado ningún contacto.';
			break;
		case 'status':
			$lead = mats_lead_get( mats_leads_current_lead_id() );
			$text = $lead ? 'Estado guardado: «' . mats_lead_statuses()[ $lead['status'] ] . '».' : 'Estado guardado.';
			break;
		case 'settings':
			$text = 'Ajuste de borrado automático guardado.' . ( $n ? ' ' . mats_leads_plural( $n, 'Se ha borrado %d contacto antiguo.', 'Se han borrado %d contactos antiguos.' ) : '' );
			break;
		case 'none':
			$type = 'warning';
			$text = 'No has marcado ningún contacto. Marca las casillas de la izquierda y vuelve a probar.';
			break;
		case 'missing':
			$type = 'warning';
			$text = 'Ese contacto ya no existe (puede que alguien lo haya borrado).';
			break;
		default:
			$type = 'error';
			$text = 'No se ha podido hacer el cambio. Vuelve a intentarlo.';
	}
	printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $type ), esc_html( $text ) );
}

/** Warning when no WPForms form is connected yet. */
function mats_leads_forms_notice(): void {
	if ( mats_lead_form_ids() ) {
		return;
	}
	echo '<div class="notice notice-info inline mats-leads-hint"><p><strong>Todavía no hay ningún formulario conectado.</strong> ';
	echo esc_html( 'Los contactos aparecerán aquí en cuanto el formulario de contacto de WPForms esté configurado (lo hace el asistente de instalación del tema).' );
	echo '</p></div>';
}

/* ── list ─────────────────────────────────────────────────────────────── */

/** The list table (declared on first use: WP_List_Table only exists in the admin). */
function mats_leads_table() {
	if ( ! class_exists( 'WP_List_Table', false ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	}
	if ( ! class_exists( 'Mats_Leads_List_Table', false ) ) {
		mats_leads_declare_table();
	}
	return new Mats_Leads_List_Table();
}

function mats_leads_declare_table(): void {
	/**
	 * Contactos list.
	 */
	class Mats_Leads_List_Table extends WP_List_Table { // phpcs:ignore Generic.Files.OneObjectStructurePerFile,Generic.Classes.OpeningBraceSameLine

		/** @var array Current filters. */
		public $filters = array();

		public function __construct() {
			parent::__construct(
				array(
					'singular' => 'mats_lead',
					'plural'   => 'mats_leads',
					'ajax'     => false,
					'screen'   => get_current_screen(),
				)
			);
		}

		public function get_columns() {
			return array(
				'cb'           => '<input type="checkbox" />',
				'name'         => 'Nombre', // first: on phones WordPress folds the columns after the primary one
				'email'        => 'Email',
				'organisation' => 'Organización',
				'interest'     => 'Le interesa',
				'language'     => 'Idioma',
				'status'       => 'Estado',
				'date'         => 'Recibido',
			);
		}

		protected function get_sortable_columns() {
			return array(
				'date' => array( 'date', true ),
				'name' => array( 'name', false ),
			);
		}

		protected function get_default_primary_column_name() {
			return 'name';
		}

		protected function get_bulk_actions() {
			return array(
				'read'     => 'Marcar como leído',
				'answered' => 'Marcar como atendido',
				'new'      => 'Marcar como no leído',
				'delete'   => 'Borrar definitivamente',
			);
		}

		protected function get_views() {
			$views   = array();
			$current = $this->filters['status'];
			$base    = $this->filters;
			$items   = array( '' => 'Todos' ) + array(
				'new'      => 'Nuevos',
				'read'     => 'Leídos',
				'answered' => 'Atendidos',
			);
			foreach ( $items as $status => $label ) {
				$count = mats_leads_count( (string) $status );
				$url   = mats_leads_list_url( array_merge( $base, array( 'status' => $status, 's' => '' ) ) );
				$attrs = $current === $status ? ' class="current" aria-current="page"' : '';
				$views[ $status ?: 'all' ] = sprintf(
					'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
					esc_url( $url ),
					$attrs,
					esc_html( $label ),
					esc_html( number_format_i18n( $count ) )
				);
			}
			return $views;
		}

		protected function extra_tablenav( $which ) {
			if ( 'top' !== $which ) {
				return;
			}
			echo '<div class="alignleft actions">';
			echo '<label class="screen-reader-text" for="mats-filter-lang">Filtrar por idioma</label>';
			echo '<select name="lead_lang" id="mats-filter-lang">';
			echo '<option value="">Todos los idiomas</option>';
			foreach ( mats_lead_languages() as $lang => $name ) {
				printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $lang ), selected( $this->filters['lang'], $lang, false ), esc_html( $name . ' (formulario ' . strtoupper( $lang ) . ')' ) );
			}
			echo '</select>';
			submit_button( 'Filtrar', '', 'filter_action', false, array( 'id' => 'mats-leads-filter' ) );
			if ( $this->filters['lang'] || '' !== $this->filters['s'] ) {
				printf( ' <a class="button-link mats-leads-reset" href="%s">Quitar filtros</a>', esc_url( mats_leads_list_url( array( 'status' => $this->filters['status'] ) ) ) );
			}
			echo '</div>';
		}

		public function no_items() {
			if ( '' !== $this->filters['s'] || $this->filters['lang'] || $this->filters['status'] ) {
				echo esc_html( 'No hay ningún contacto con estos filtros.' );
				return;
			}
			echo esc_html( 'Todavía no ha llegado ningún contacto. Cuando alguien rellene el formulario de la web, aparecerá aquí.' );
		}

		public function prepare_items() {
			$this->filters          = mats_leads_request_filters();
			$per_page               = MATS_LEADS_PER_PAGE;
			$args                   = mats_leads_query_args( $this->filters );
			$args['posts_per_page'] = $per_page;
			$args['paged']          = $this->get_pagenum();
			$args['fields']         = 'ids';
			$query                  = new WP_Query( $args );
			$ids                    = array_map( 'intval', $query->posts );
			if ( $ids ) {
				_prime_post_caches( $ids, false, false );
				update_meta_cache( 'post', $ids );
			}
			$this->items = array_values( array_filter( array_map( 'mats_lead_get', $ids ) ) );
			$this->set_pagination_args(
				array(
					'total_items' => (int) $query->found_posts,
					'per_page'    => $per_page,
					'total_pages' => (int) $query->max_num_pages,
				)
			);
			$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'name' );
		}

		public function single_row( $item ) {
			echo '<tr class="mats-row mats-row--' . esc_attr( $item['status'] ) . '">';
			$this->single_row_columns( $item );
			echo '</tr>';
		}

		protected function column_cb( $item ) {
			$label = $item['name'] ?: $item['email'];
			return sprintf(
				'<input type="checkbox" name="leads[]" id="mats-cb-%1$d" value="%1$d" /><label class="screen-reader-text" for="mats-cb-%1$d">%2$s</label>',
				(int) $item['id'],
				esc_html( 'Seleccionar ' . $label )
			);
		}

		protected function column_date( $item ) {
			return sprintf(
				'<time datetime="%1$s" title="%2$s">%3$s</time>',
				esc_attr( wp_date( 'c', $item['date'] ) ),
				esc_attr( mats_leads_date( $item['date'] ) ),
				esc_html( mats_leads_short_date( $item['date'] ) )
			);
		}

		protected function column_name( $item ) {
			$name = $item['name'] ?: '(sin nombre)';
			$out  = sprintf(
				'<strong><a class="row-title" href="%1$s">%2$s%3$s</a></strong>',
				esc_url( mats_lead_url( $item['id'] ) ),
				'new' === $item['status'] ? '<span class="mats-dot" aria-hidden="true"></span>' : '',
				esc_html( $name )
			);
			if ( '' !== $item['role'] ) {
				$out .= '<span class="mats-sub">' . esc_html( $item['role'] ) . '</span>';
			}
			return $out;
		}

		protected function column_email( $item ) {
			if ( ! is_email( $item['email'] ) ) {
				return esc_html( $item['email'] );
			}
			return sprintf( '<a href="%1$s">%2$s</a>', esc_url( 'mailto:' . $item['email'] ), esc_html( $item['email'] ) );
		}

		protected function column_organisation( $item ) {
			return esc_html( $item['organisation'] );
		}

		protected function column_interest( $item ) {
			$out  = esc_html( mats_lead_choice_label( 'interest', $item['interest'] ) );
			$area = mats_lead_choice_label( 'area', $item['area'] );
			if ( '' !== $area ) {
				$out .= '<span class="mats-sub">' . esc_html( $area ) . '</span>';
			}
			return $out;
		}

		protected function column_language( $item ) {
			return mats_lead_lang_badge( $item['language'] );
		}

		protected function column_status( $item ) {
			return mats_lead_status_badge( $item['status'] );
		}

		protected function column_default( $item, $column_name ) {
			return '';
		}

		protected function handle_row_actions( $item, $column_name, $primary ) {
			if ( $primary !== $column_name ) {
				return '';
			}
			$id      = (int) $item['id'];
			$link    = function ( string $do ) use ( $id ): string {
				return wp_nonce_url(
					mats_leads_list_url(
						$this->filters,
						array(
							'mats_do' => $do,
							'lead_id' => $id,
							'paged'   => $this->get_pagenum() > 1 ? $this->get_pagenum() : '',
						)
					),
					'mats_lead_' . $id
				);
			};
			$actions = array(
				'view' => sprintf( '<a href="%s">Ver mensaje</a>', esc_url( mats_lead_url( $id ) ) ),
			);
			if ( 'answered' !== $item['status'] ) {
				$actions['answered'] = sprintf( '<a href="%s">Marcar como atendido</a>', esc_url( $link( 'answered' ) ) );
			} else {
				$actions['read'] = sprintf( '<a href="%s">Volver a «Leído»</a>', esc_url( $link( 'read' ) ) );
			}
			$actions['delete'] = sprintf(
				'<a class="submitdelete" href="%1$s" data-mats-confirm="%2$s">Borrar</a>',
				esc_url( $link( 'delete' ) ),
				esc_attr( '¿Borrar definitivamente el contacto de ' . ( $item['name'] ?: $item['email'] ) . '? No se puede deshacer.' )
			);
			return $this->row_actions( $actions );
		}
	}
}

function mats_leads_render_list(): void {
	$table = mats_leads_table();
	$table->prepare_items();
	$f          = $table->filters;
	$export_url = wp_nonce_url(
		add_query_arg(
			array_filter(
				array(
					'action'      => 'mats_leads_export',
					'lead_status' => $f['status'],
					'lead_lang'   => $f['lang'],
					's'           => rawurlencode( $f['s'] ),
				)
			),
			admin_url( 'admin-post.php' )
		),
		'mats_leads_export'
	);
	$filtered   = $f['status'] || $f['lang'] || '' !== $f['s'];
	?>
	<div class="wrap mats-leads">
		<h1 class="wp-heading-inline">Contactos</h1>
		<a class="page-title-action" href="<?php echo esc_url( $export_url ); ?>" title="<?php echo esc_attr( $filtered ? 'Descarga los contactos de esta lista (con los filtros actuales) en un archivo para Excel' : 'Descarga todos los contactos en un archivo para Excel' ); ?>">Exportar CSV</a>
		<hr class="wp-header-end">
		<?php mats_leads_notices(); ?>
		<p class="mats-leads-intro">
			<?php echo esc_html( 'Aquí se guardan todos los mensajes que llegan por el formulario de contacto de la web, en inglés y en español (aunque el email de aviso se pierda). Haz clic en un nombre para leer el mensaje completo y responder.' ); ?>
		</p>
		<?php
		mats_leads_forms_notice();
		$table->views();
		?>
		<form id="mats-leads-form" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="<?php echo esc_attr( MATS_LEADS_PAGE ); ?>" />
			<?php if ( $f['status'] ) : ?>
				<input type="hidden" name="lead_status" value="<?php echo esc_attr( $f['status'] ); ?>" />
			<?php endif; ?>
			<?php
			$table->search_box( 'Buscar', 'mats-leads-search' );
			$table->display();
			?>
		</form>

		<div class="mats-leads-tools">
			<?php mats_leads_render_export_card( $f ); ?>
			<?php mats_leads_render_retention_card(); ?>
		</div>
	</div>
	<?php
}

function mats_leads_render_export_card( array $f ): void {
	?>
	<section class="mats-card" id="mats-leads-export" aria-labelledby="mats-leads-export-title">
		<h2 id="mats-leads-export-title"><span class="dashicons dashicons-download" aria-hidden="true"></span> Exportar a Excel</h2>
		<p class="mats-card__text">Descarga los contactos en un archivo CSV que se abre con Excel, Numbers o Google Sheets. Deja las fechas vacías para descargarlos todos.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mats-form">
			<input type="hidden" name="action" value="mats_leads_export" />
			<?php wp_nonce_field( 'mats_leads_export' ); ?>
			<div class="mats-form__row">
				<label>Desde<input type="date" name="lead_from" max="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>" /></label>
				<label>Hasta<input type="date" name="lead_to" max="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>" /></label>
			</div>
			<div class="mats-form__row">
				<label>Estado
					<select name="lead_status">
						<option value="">Todos</option>
						<?php foreach ( mats_lead_statuses() as $status => $label ) : ?>
							<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $f['status'], $status ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>Idioma
					<select name="lead_lang">
						<option value="">Los dos</option>
						<?php foreach ( mats_lead_languages() as $lang => $name ) : ?>
							<option value="<?php echo esc_attr( $lang ); ?>" <?php selected( $f['lang'], $lang ); ?>><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>
			<p><button type="submit" class="button button-primary mats-has-icon"><span class="dashicons dashicons-download" aria-hidden="true"></span> Descargar CSV</button></p>
		</form>
	</section>
	<?php
}

function mats_leads_render_retention_card(): void {
	$months   = mats_leads_retention();
	$can_edit = current_user_can( 'manage_options' );
	$last     = get_option( 'mats_leads_cleanup_last' );
	$next     = wp_next_scheduled( 'mats_leads_cleanup' );
	?>
	<section class="mats-card" id="mats-leads-retention" aria-labelledby="mats-leads-retention-title">
		<h2 id="mats-leads-retention-title"><span class="dashicons dashicons-shield" aria-hidden="true"></span> Borrado automático (privacidad)</h2>
		<p class="mats-card__text">Por protección de datos, no conviene guardar los mensajes para siempre. Si lo activas, cada día se borran solos los contactos más antiguos que el plazo que elijas (y al guardar se borran ya los que lo superen). Lo que se borra no se puede recuperar: si los necesitas, expórtalos antes.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mats-form" data-mats-retention>
			<input type="hidden" name="action" value="mats_leads_settings" />
			<?php wp_nonce_field( 'mats_leads_settings' ); ?>
			<div class="mats-form__row">
				<label>Contactos antiguos
					<select name="retention" <?php disabled( ! $can_edit ); ?>>
						<?php foreach ( mats_leads_retention_options() as $value => $label ) : ?>
							<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $months, $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>
			<p class="mats-state">
				<?php if ( $months ) : ?>
					<span class="mats-state__on">Activado:</span>
					<?php echo esc_html( 'se borran los contactos con más de ' . ( 12 === $months ? '1 año' : ( 24 === $months ? '2 años' : ( 36 === $months ? '3 años' : $months . ' meses' ) ) ) . '.' ); ?>
					<?php if ( is_array( $last ) && ! empty( $last['time'] ) ) : ?>
						<?php echo esc_html( 'Última revisión: ' . mats_leads_date( (int) $last['time'] ) . ' (' . mats_leads_plural( (int) ( $last['deleted'] ?? 0 ), '%d borrado', '%d borrados' ) . ').' ); ?>
					<?php elseif ( $next ) : ?>
						<?php echo esc_html( 'Próxima revisión: ' . mats_leads_date( (int) $next ) . '.' ); ?>
					<?php endif; ?>
				<?php else : ?>
					<span class="mats-state__off">Desactivado:</span> los contactos se guardan hasta que los borréis a mano.
				<?php endif; ?>
			</p>
			<?php if ( $can_edit ) : ?>
				<p><button type="submit" class="button">Guardar</button></p>
			<?php else : ?>
				<p class="description">Solo una persona administradora de la web puede cambiar este ajuste.</p>
			<?php endif; ?>
		</form>
	</section>
	<?php
}

/* ── detail ───────────────────────────────────────────────────────────── */

/** One "label: value" row of the detail screen ('' values are skipped unless $always). */
function mats_leads_detail_row( string $label, string $html, bool $always = false ): void {
	if ( '' === $html && ! $always ) {
		return;
	}
	printf( '<div class="mats-dl__row"><dt>%1$s</dt><dd>%2$s</dd></div>', esc_html( $label ), '' !== $html ? $html : '<span class="mats-muted">—</span>' ); // phpcs:ignore WordPress.Security.EscapeOutput -- $html is escaped by every caller
}

function mats_leads_render_detail( array $lead ): void {
	$id       = (int) $lead['id'];
	$name     = $lead['name'] ?: '(sin nombre)';
	$mailto   = mats_lead_mailto( $lead );
	$lang     = mats_lead_languages()[ $lead['language'] ];
	$statuses = mats_lead_statuses();
	$hints    = mats_lead_status_hints();
	$ago      = mats_leads_ago( $lead['date'] );
	?>
	<div class="wrap mats-leads mats-lead">
		<p class="mats-back"><a href="<?php echo esc_url( mats_leads_url() ); ?>"><span aria-hidden="true">←</span> Volver a Contactos</a></p>

		<header class="mats-lead__head">
			<div class="mats-lead__who">
				<h1 class="wp-heading-inline"><?php echo esc_html( $name ); ?></h1>
				<?php if ( $lead['organisation'] || $lead['role'] ) : ?>
					<p class="mats-lead__org"><?php echo esc_html( implode( ' · ', array_filter( array( $lead['role'], $lead['organisation'] ) ) ) ); ?></p>
				<?php endif; ?>
				<p class="mats-lead__meta">
					<?php echo mats_lead_status_badge( $lead['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
					<?php echo mats_lead_lang_badge( $lead['language'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
					<span><?php echo esc_html( 'Recibido el ' . mats_leads_date( $lead['date'] ) . ( $ago ? ' (' . $ago . ')' : '' ) ); ?></span>
				</p>
			</div>
			<div class="mats-lead__actions">
				<?php if ( $mailto ) : ?>
					<a class="button button-primary button-hero mats-reply mats-has-icon" href="<?php echo esc_attr( $mailto ); ?>"><span class="dashicons dashicons-email-alt" aria-hidden="true"></span> Responder por email</a>
					<button type="button" class="button-link mats-copy" data-mats-copy="<?php echo esc_attr( $lead['email'] ); ?>" data-mats-copied="Email copiado">Copiar el email</button>
					<p class="mats-lead__langhint"><?php echo esc_html( 'Escribió desde la web en ' . strtolower( $lang ) . ': responde en ' . strtolower( $lang ) . '.' ); ?></p>
				<?php endif; ?>
			</div>
		</header>
		<hr class="wp-header-end">
		<?php mats_leads_notices(); ?>

		<div class="mats-lead__grid">
			<div class="mats-lead__main">
				<section class="mats-card">
					<h2>Mensaje</h2>
					<?php if ( '' !== $lead['message'] ) : ?>
						<div class="mats-lead__message"><?php echo esc_html( $lead['message'] ); ?></div>
					<?php else : ?>
						<p class="mats-muted">No escribió ningún mensaje.</p>
					<?php endif; ?>
				</section>

				<section class="mats-card">
					<h2>Datos de contacto</h2>
					<dl class="mats-dl">
						<?php
						mats_leads_detail_row( 'Nombre', esc_html( $lead['name'] ), true );
						mats_leads_detail_row( 'Email', is_email( $lead['email'] ) ? '<a href="' . esc_url( 'mailto:' . $lead['email'] ) . '">' . esc_html( $lead['email'] ) . '</a>' : esc_html( $lead['email'] ), true );
						mats_leads_detail_row( 'Organización', esc_html( $lead['organisation'] ), true );
						mats_leads_detail_row( 'Cargo', esc_html( $lead['role'] ), true );
						mats_leads_detail_row( 'Le interesa', esc_html( mats_lead_choice_label( 'interest', $lead['interest'] ) ), true );
						mats_leads_detail_row( 'Área de interés', esc_html( mats_lead_choice_label( 'area', $lead['area'] ) ), true );
						foreach ( $lead['extra'] as $item ) {
							mats_leads_detail_row( (string) ( $item['label'] ?? 'Campo' ), nl2br( esc_html( (string) ( $item['value'] ?? '' ) ) ) );
						}
						if ( 'yes' === $lead['consent'] ) {
							$consent_ts = $lead['consent_date'] ? (int) strtotime( get_gmt_from_date( $lead['consent_date'] ) . ' UTC' ) : 0;
							$consent    = '<span class="mats-yes">Sí</span>' . ( $consent_ts ? esc_html( ', aceptada el ' . mats_leads_date( $consent_ts ) ) : '' );
						} else {
							$consent = '<span class="mats-no">No consta</span>';
						}
						mats_leads_detail_row( 'Política de privacidad', $consent, true );
						mats_leads_detail_row( 'Idioma del formulario', esc_html( $lang ), true );
						?>
					</dl>
				</section>
			</div>

			<aside class="mats-lead__side">
				<section class="mats-card">
					<h2>Estado</h2>
					<?php if ( 'answered' !== $lead['status'] ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mats-quick">
							<input type="hidden" name="action" value="mats_lead_status" />
							<input type="hidden" name="lead" value="<?php echo esc_attr( (string) $id ); ?>" />
							<input type="hidden" name="status" value="answered" />
							<?php wp_nonce_field( 'mats_lead_status_' . $id ); ?>
							<button type="submit" class="button button-secondary mats-quick__btn mats-has-icon"><span class="dashicons dashicons-yes" aria-hidden="true"></span> Ya le he respondido: marcar como atendido</button>
						</form>
					<?php elseif ( $lead['answered_at'] ) : ?>
						<?php $by = $lead['answered_by'] ? get_userdata( $lead['answered_by'] ) : false; ?>
						<p class="mats-answered"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <?php echo esc_html( 'Atendido el ' . mats_leads_date( $lead['answered_at'] ) . ( $by ? ' por ' . $by->display_name : '' ) . '.' ); ?></p>
					<?php endif; ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mats-status-form">
						<input type="hidden" name="action" value="mats_lead_status" />
						<input type="hidden" name="lead" value="<?php echo esc_attr( (string) $id ); ?>" />
						<?php wp_nonce_field( 'mats_lead_status_' . $id ); ?>
						<fieldset>
							<legend class="screen-reader-text">Estado del contacto</legend>
							<?php foreach ( $statuses as $status => $label ) : ?>
								<label class="mats-radio">
									<input type="radio" name="status" value="<?php echo esc_attr( $status ); ?>" <?php checked( $lead['status'], $status ); ?> />
									<span class="mats-radio__text"><?php echo mats_lead_status_badge( $status ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?><span class="mats-radio__hint"><?php echo esc_html( $hints[ $status ] ); ?></span></span>
								</label>
							<?php endforeach; ?>
						</fieldset>
						<p><button type="submit" class="button">Guardar estado</button></p>
					</form>
				</section>

				<section class="mats-card">
					<h2>Cómo llegó</h2>
					<dl class="mats-dl mats-dl--stack">
						<?php
						$any = false;
						if ( '' !== $lead['cta_source'] ) {
							$any   = true;
							$label = mats_lead_cta_label( $lead['cta_source'] );
							mats_leads_detail_row( 'Botón que pulsó', ( $label ? esc_html( $label ) . ' ' : '' ) . '<code>' . esc_html( $lead['cta_source'] ) . '</code>' );
						}
						if ( '' !== $lead['source_page'] ) {
							$any  = true;
							$link = mats_lead_page_link( $lead['source_page'] );
							mats_leads_detail_row( 'Página desde la que escribió', $link ? '<a href="' . esc_url( $link ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $lead['source_page'] ) . '</a>' : esc_html( $lead['source_page'] ) );
						}
						if ( '' !== $lead['application'] ) {
							$any   = true;
							$title = mats_lead_application_title( $lead['application'] );
							mats_leads_detail_row( 'Aplicación que estaba viendo', ( $title ? esc_html( $title ) . ' ' : '' ) . '<code>' . esc_html( $lead['application'] ) . '</code>' );
						}
						if ( $lead['utm'] ) {
							$any    = true;
							$names  = array(
								'source'   => 'Fuente',
								'medium'   => 'Medio',
								'campaign' => 'Campaña',
								'content'  => 'Contenido',
								'term'     => 'Término',
							);
							$parts  = array();
							foreach ( $lead['utm'] as $key => $value ) {
								$parts[] = '<span class="mats-utm"><span class="mats-utm__k">' . esc_html( $names[ $key ] ?? $key ) . '</span> ' . esc_html( (string) $value ) . '</span>';
							}
							mats_leads_detail_row( 'Campaña (enlace con UTM)', implode( '', $parts ) );
						}
						if ( ! $any ) {
							echo '<p class="mats-muted">' . esc_html( 'No hay datos de origen para este contacto.' ) . '</p>';
						}
						?>
					</dl>
					<p class="mats-ref"><?php echo esc_html( 'Referencia #' . $id . ( $lead['form_id'] ? ' · formulario WPForms ' . $lead['form_id'] : '' ) ); ?></p>
				</section>

				<section class="mats-card mats-card--danger">
					<h2>Borrar</h2>
					<p class="mats-card__text">Borra este contacto para siempre (por ejemplo, si es spam o si la persona pide que borremos sus datos).</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="mats_lead_delete" />
						<input type="hidden" name="lead" value="<?php echo esc_attr( (string) $id ); ?>" />
						<?php wp_nonce_field( 'mats_lead_delete_' . $id ); ?>
						<button type="submit" class="button mats-delete" data-mats-confirm="<?php echo esc_attr( '¿Borrar definitivamente el contacto de ' . ( $lead['name'] ?: $lead['email'] ) . '? No se puede deshacer.' ); ?>">Borrar este contacto</button>
					</form>
				</section>
			</aside>
		</div>
	</div>
	<?php
}

/* ── dashboard widget ─────────────────────────────────────────────────── */

add_action(
	'wp_dashboard_setup',
	static function () {
		if ( current_user_can( MATS_LEAD_CAP ) ) {
			wp_add_dashboard_widget( 'mats_leads_widget', 'Contactos recientes', 'mats_leads_dashboard_widget' );
		}
	}
);

function mats_leads_dashboard_widget(): void {
	$new = mats_leads_count( 'new' );
	$ids = get_posts(
		array(
			'post_type'        => MATS_LEAD_TYPE,
			'post_status'      => 'any',
			'posts_per_page'   => 5,
			'fields'           => 'ids',
			'suppress_filters' => true,
		)
	);
	echo '<div class="mats-widget">';
	if ( $new ) {
		printf(
			'<p class="mats-widget__summary"><a href="%1$s"><strong>%2$s</strong></a> %3$s</p>',
			esc_url( mats_leads_url( array( 'lead_status' => 'new' ) ) ),
			esc_html( mats_leads_plural( $new, '%d contacto nuevo', '%d contactos nuevos' ) ),
			esc_html( 'sin leer.' )
		);
	} elseif ( $ids ) {
		echo '<p class="mats-widget__summary">' . esc_html( 'No hay contactos nuevos: todo al día.' ) . '</p>';
	}
	if ( ! $ids ) {
		echo '<p class="mats-widget__summary">' . esc_html( 'Todavía no ha llegado ningún contacto por el formulario de la web.' ) . '</p>';
	} else {
		echo '<ul class="mats-widget__list">';
		foreach ( $ids as $id ) {
			$lead = mats_lead_get( (int) $id );
			if ( ! $lead ) {
				continue;
			}
			printf(
				'<li class="mats-widget__item mats-row--%1$s"><a href="%2$s"><span class="mats-widget__name">%3$s</span><span class="mats-widget__org">%4$s</span></a><span class="mats-widget__meta">%5$s %6$s</span></li>',
				esc_attr( $lead['status'] ),
				esc_url( mats_lead_url( $lead['id'] ) ),
				esc_html( $lead['name'] ?: $lead['email'] ),
				esc_html( $lead['organisation'] ),
				mats_lead_status_badge( $lead['status'] ), // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside
				'<span class="mats-widget__date">' . esc_html( mats_leads_short_date( $lead['date'] ) ) . '</span>'
			);
		}
		echo '</ul>';
	}
	printf( '<p class="mats-widget__all"><a class="button" href="%s">Ver todos los contactos</a></p>', esc_url( mats_leads_url() ) );
	echo '</div>';
}
