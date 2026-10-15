<?php
/**
 * WPForms integration — the contact form of the site (one WPForms form per
 * language, EN + ES) and everything around it.
 *
 *  - mats_wpforms_form_data( $lang )      full WPForms definition built from
 *                                          content.json[lang].contact.form,
 *                                          with FIXED field IDs (see below)
 *  - mats_wpforms_create_forms( $overwrite ) creates/updates both forms and
 *                                          stores their IDs in the settings
 *                                          (form_en / form_es, scope global)
 *  - mats_wpforms_render( $lang )          what the contact block prints in
 *                                          its form card
 *  - front end: assets only where the contact block is, ?interest= and
 *    ?application= preselection, hidden tracking inputs
 *    (matsurfer_meta[…]) added to the notification email, privacy link and
 *    autocomplete hints refreshed at render time
 *  - admin: a notice with a "Crear los formularios de contacto" button when
 *    WPForms is active but the forms are missing; notification address kept
 *    in sync with Ajustes de la web → Email de contacto.
 *
 * FIELD IDS (a contract with the CSS, the JS and the emails — never change):
 *   1 Name (name, simple)   2 Work email   3 Organisation   4 Role
 *   5 I am interested in (select)          6 Area of interest (select, optional)
 *   7 Message (textarea)                   8 Consent (checkbox, 1 required choice)
 *   901–905 tracking pseudo-fields, only in the notification email.
 *
 * Every call into WPForms is guarded (function_exists / method_exists), so a
 * missing or changed WPForms never breaks the page: the contact block then
 * shows the email address instead of the form.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

/* ── basics ──────────────────────────────────────────────────────────── */

/** Is WPForms (Lite or Pro) loaded? */
function mats_wpforms_active(): bool {
	return function_exists( 'wpforms' );
}

/** The WPForms form handler (wpforms()->obj( 'form' )), or null. */
function mats_wpforms_handler() {
	if ( ! mats_wpforms_active() ) {
		return null;
	}
	try {
		$wpforms = wpforms();
		if ( is_object( $wpforms ) && method_exists( $wpforms, 'obj' ) ) {
			$handler = $wpforms->obj( 'form' );
		} elseif ( is_object( $wpforms ) && method_exists( $wpforms, 'get' ) ) {
			$handler = $wpforms->get( 'form' );
		} else {
			$handler = is_object( $wpforms ) && isset( $wpforms->form ) ? $wpforms->form : null;
		}
	} catch ( Throwable $e ) {
		return null;
	}
	return is_object( $handler ) ? $handler : null;
}

/** WPForms' storage format: slashed JSON (same as wpforms_encode()). */
function mats_wpforms_encode( array $data ): string {
	if ( function_exists( 'wpforms_encode' ) ) {
		$encoded = wpforms_encode( $data );
		if ( is_string( $encoded ) && '' !== $encoded ) {
			return $encoded;
		}
	}
	return wp_slash( (string) wp_json_encode( $data ) );
}

function mats_wpforms_decode( string $content ): array {
	$data = json_decode( $content, true );
	return is_array( $data ) ? $data : array();
}

/** content.json → contact.form for one language. */
function mats_wpforms_copy( string $lang ): array {
	$data = function_exists( 'mats_content_data' ) ? mats_content_data() : array();
	$form = $data[ mats_normalize_lang( $lang ) ]['contact']['form'] ?? array();
	return is_array( $form ) ? $form : array();
}

/** content.json → interestParamMap (?interest=product → product-information). */
function mats_wpforms_interest_map(): array {
	$data = function_exists( 'mats_content_data' ) ? mats_content_data() : array();
	return is_array( $data['interestParamMap'] ?? null ) ? $data['interestParamMap'] : array();
}

/** value => label of a select's options in content.json. */
function mats_wpforms_options( string $lang, string $key ): array {
	$out = array();
	foreach ( (array) ( mats_wpforms_copy( $lang )[ $key ]['options'] ?? array() ) as $option ) {
		if ( isset( $option['value'], $option['label'] ) ) {
			$out[ (string) $option['value'] ] = (string) $option['label'];
		}
	}
	return $out;
}

/** The tracking pseudo-fields of the notification email: key => [ id, label ]. */
function mats_wpforms_meta_fields(): array {
	return array(
		'language'    => array( 'id' => 901, 'label' => 'Language' ),
		'source_page' => array( 'id' => 902, 'label' => 'Source page' ),
		'cta_source'  => array( 'id' => 903, 'label' => 'CTA' ),
		'application' => array( 'id' => 904, 'label' => 'Application' ),
		'utm'         => array( 'id' => 905, 'label' => 'UTM' ),
	);
}

/**
 * Consent sentence with the privacy link when the privacy page of that
 * language is published (plain text otherwise).
 */
function mats_wpforms_consent_label( string $lang, ?string $privacy_url = null ): string {
	$consent = (array) ( mats_wpforms_copy( $lang )['consent'] ?? array() );
	$before  = trim( (string) ( $consent['labelBefore'] ?? '' ) );
	$label   = trim( (string) ( $consent['privacyLabel'] ?? '' ) );
	$after   = (string) ( $consent['labelAfter'] ?? '' );
	if ( null === $privacy_url ) {
		$privacy_url = mats_wpforms_privacy_url( $lang );
	}
	$link = $privacy_url
		? '<a href="' . esc_url( $privacy_url ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . '</a>'
		: esc_html( $label );
	return trim( esc_html( $before ) . ' ' . $link . esc_html( $after ) );
}

/** URL of the published privacy page in a language, '' when there is none. */
function mats_wpforms_privacy_url( string $lang ): string {
	if ( ! function_exists( 'mats_page_id' ) || ! mats_page_id( 'privacy', $lang ) ) {
		return '';
	}
	return mats_page_url( 'privacy', $lang );
}

/* ── the form definition ─────────────────────────────────────────────── */

/** Name of the form in WPForms' list (also how a hand-imported form is recognised). */
function mats_wpforms_form_title( string $lang ): string {
	return 'es' === mats_normalize_lang( $lang ) ? 'Contacto MatSurfer (ES)' : 'Contacto MatSurfer (EN)';
}

/**
 * The complete WPForms form (fields, settings, notification, confirmation)
 * for one language, built from the original copy.
 *
 * $args (optional, used by the JSON export): 'email' (notification address,
 * default mats_contact_email()), 'privacy_url' (default: the published
 * privacy page, '' = plain text).
 */
function mats_wpforms_form_data( string $lang, array $args = array() ): array {
	$lang  = mats_normalize_lang( $lang );
	$f     = mats_wpforms_copy( $lang );
	$is_es = 'es' === $lang;

	$email       = isset( $args['email'] ) ? sanitize_email( (string) $args['email'] ) : mats_contact_email();
	$privacy_url = array_key_exists( 'privacy_url', $args ) ? (string) $args['privacy_url'] : null;
	$select      = (string) ( $f['selectPlaceholder'] ?? 'Select…' );

	$choices = static function ( array $options ): array {
		$out = array();
		$i   = 1;
		foreach ( $options as $option ) {
			$out[ $i ] = array(
				'label'      => (string) ( $option['label'] ?? '' ),
				'value'      => '',
				'image'      => '',
				'icon'       => '',
				'icon_style' => '',
			);
			++$i;
		}
		return $out;
	};

	$text_field = static function ( int $id, string $type, string $label, string $css, array $extra = array() ): array {
		return array_merge(
			array(
				'id'            => (string) $id,
				'type'          => $type,
				'label'         => $label,
				'description'   => '',
				'required'      => '1',
				'size'          => 'large',
				'placeholder'   => '',
				'default_value' => '',
				'css'           => $css,
			),
			$extra
		);
	};

	$fields = array(
		1 => $text_field(
			1,
			'name',
			(string) ( $f['fullName']['label'] ?? 'Name' ),
			'lf-half',
			array(
				'format'             => 'simple',
				'simple_placeholder' => '',
				'simple_default'     => '',
				'first_placeholder'  => '',
				'first_default'      => '',
				'middle_placeholder' => '',
				'middle_default'     => '',
				'last_placeholder'   => '',
				'last_default'       => '',
			)
		),
		2 => $text_field(
			2,
			'email',
			(string) ( $f['email']['label'] ?? 'Email' ),
			'lf-half',
			array(
				'confirmation_placeholder' => '',
				'filter_type'              => '',
				'allowlist'                => '',
				'denylist'                 => '',
			)
		),
		3 => $text_field( 3, 'text', (string) ( $f['organization']['label'] ?? '' ), 'lf-half', array( 'limit_count' => '1', 'limit_mode' => 'characters', 'input_mask' => '' ) ),
		4 => $text_field( 4, 'text', (string) ( $f['role']['label'] ?? '' ), 'lf-half', array( 'limit_count' => '1', 'limit_mode' => 'characters', 'input_mask' => '' ) ),
		5 => array(
			'id'              => '5',
			'type'            => 'select',
			'label'           => (string) ( $f['interest']['label'] ?? '' ),
			'choices'         => $choices( (array) ( $f['interest']['options'] ?? array() ) ),
			'description'     => '',
			'required'        => '1',
			'style'           => 'classic',
			'size'            => 'large',
			'placeholder'     => $select,
			'default_value'   => '',
			'css'             => 'lf-half',
			'dynamic_choices' => '',
		),
		6 => array(
			'id'              => '6',
			'type'            => 'select',
			'label'           => (string) ( $f['areaOfInterest']['label'] ?? '' ),
			'choices'         => $choices( (array) ( $f['areaOfInterest']['options'] ?? array() ) ),
			'description'     => '',
			'required'        => '',
			'style'           => 'classic',
			'size'            => 'large',
			'placeholder'     => $select,
			'default_value'   => '',
			'css'             => 'lf-half',
			'dynamic_choices' => '',
		),
		7 => $text_field(
			7,
			'textarea',
			(string) ( $f['message']['label'] ?? '' ),
			'lf-full',
			array(
				'placeholder' => (string) ( $f['message']['placeholder'] ?? '' ),
				'limit_count' => '1',
				'limit_mode'  => 'characters',
			)
		),
		8 => array(
			'id'                   => '8',
			'type'                 => 'checkbox',
			'label'                => (string) ( $f['consent']['privacyLabel'] ?? '' ),
			'choices'              => array(
				1 => array(
					'label'      => mats_wpforms_consent_label( $lang, $privacy_url ),
					'value'      => '',
					'image'      => '',
					'icon'       => '',
					'icon_style' => '',
				),
			),
			'description'          => '',
			'required'             => '1',
			'label_hide'           => '1',
			'choices_images_style' => 'modern',
			'input_columns'        => '',
			'choice_limit'         => '',
			'dynamic_choices'      => '',
			'css'                  => 'lf-full lf-consent',
		),
	);

	$success = '<h2>' . esc_html( (string) ( $f['successTitle'] ?? '' ) ) . '</h2>' . "\n" . '<p>' . esc_html( (string) ( $f['successText'] ?? '' ) ) . '</p>';

	$data = array(
		'fields'   => $fields,
		'field_id' => 9,
		'settings' => array(
			'form_title'             => mats_wpforms_form_title( $lang ),
			'form_desc'              => $is_es ? 'Formulario de la página Contacto en español. Lo crea el tema MatSurfer.' : 'Formulario de la página Contacto en inglés. Lo crea el tema MatSurfer.',
			'submit_text'            => (string) ( $f['submit'] ?? 'Send' ),
			'submit_text_processing' => (string) ( $f['submitting'] ?? '…' ),
			'form_class'             => 'mats-lead-form',
			'submit_class'           => 'btn lf__submit',
			'ajax_submit'            => '1',
			'notification_enable'    => '1',
			'notifications'          => array(
				1 => array(
					'notification_name'             => $is_es ? 'Aviso al equipo MatSurfer' : 'Aviso al equipo MatSurfer (EN)',
					'email'                         => $email ? $email : '{admin_email}',
					'carboncopy'                    => '',
					'subject'                       => $is_es ? '[MatSurfer] Nueva consulta (ES)' : '[MatSurfer] New enquiry (EN)',
					'sender_name'                   => 'MatSurfer web',
					'sender_address'                => '{admin_email}',
					'replyto'                       => '{field_id="2"}',
					'message'                       => '{all_fields}',
					'file_upload_attachment_enable' => '',
					'entry_csv_attachment_enable'   => '',
				),
			),
			'confirmations'          => array(
				1 => array(
					'name'                        => $is_es ? 'Mensaje de gracias' : 'Mensaje de gracias (EN)',
					'type'                        => 'message',
					'message'                     => $success,
					'message_scroll'              => '1',
					'page'                        => '',
					'page_url_parameters'         => '',
					'redirect'                    => '',
					'message_entry_preview_style' => 'basic',
				),
			),
			/* spam protection: modern anti-spam (1.9+), classic token, honeypot */
			'antispam_v3'            => '1',
			'antispam'               => '1',
			'honeypot'               => '1',
			'store_spam_entries'     => '0',
			'anti_spam'              => array(
				'time_limit' => array(
					'enable'   => '0',
					'duration' => '2',
				),
			),
			'dynamic_population'     => '',
			'disable_entries'        => '',
			'form_tags'              => array(),
		),
		'meta'     => array(
			'template' => 'blank',
		),
	);

	/**
	 * Filters the MatSurfer contact form definition before it is saved.
	 *
	 * @param array  $data WPForms form data.
	 * @param string $lang 'en' | 'es'.
	 */
	return (array) apply_filters( 'mats_wpforms_form_data', $data, $lang );
}

/* ── which forms are ours ────────────────────────────────────────────── */

/** True when the post ID is a usable (published) WPForms form. */
function mats_wpforms_is_form( int $form_id ): bool {
	return $form_id > 0 && 'wpforms' === get_post_type( $form_id ) && 'publish' === get_post_status( $form_id );
}

/** Stored form ID for a language when it still exists, 0 otherwise. */
function mats_wpforms_form_id( string $lang ): int {
	$id = absint( mats_setting( 'form_' . mats_normalize_lang( $lang ), 'global' ) );
	return mats_wpforms_is_form( $id ) ? $id : 0;
}

/**
 * A form made for that language earlier: created by the theme (post meta
 * _mats_form_lang) or imported by hand from inc/data/wpforms-<lang>.json
 * (recognised by its title).
 */
function mats_wpforms_find_form( string $lang ): int {
	$lang = mats_normalize_lang( $lang );
	$ids  = get_posts(
		array(
			'post_type'        => 'wpforms',
			'post_status'      => 'publish',
			'numberposts'      => 1,
			'fields'           => 'ids',
			'orderby'          => 'ID',
			'order'            => 'ASC',
			'meta_key'         => '_mats_form_lang', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'       => $lang, // phpcs:ignore WordPress.DB.SlowDBQuery
			'suppress_filters' => true,
			'lang'             => '',
		)
	);
	if ( $ids ) {
		return (int) $ids[0];
	}
	$title = mats_wpforms_form_title( $lang );
	foreach ( get_posts(
		array(
			'post_type'        => 'wpforms',
			'post_status'      => 'publish',
			'numberposts'      => 20,
			'orderby'          => 'ID',
			'order'            => 'ASC',
			'suppress_filters' => true,
			'lang'             => '',
		)
	) as $post ) {
		if ( '' !== $title && html_entity_decode( $post->post_title, ENT_QUOTES, 'UTF-8' ) === $title && ! mats_wpforms_form_lang( (int) $post->ID ) ) {
			return (int) $post->ID;
		}
	}
	return 0;
}

/** 'en' | 'es' when the form ID is one of the site's contact forms, '' otherwise. */
function mats_wpforms_form_lang( int $form_id ): string {
	if ( $form_id <= 0 ) {
		return '';
	}
	foreach ( mats_langs() as $lang ) {
		if ( absint( mats_setting( 'form_' . $lang, 'global' ) ) === $form_id ) {
			return $lang;
		}
	}
	$meta = (string) get_post_meta( $form_id, '_mats_form_lang', true );
	return in_array( $meta, mats_langs(), true ) ? $meta : '';
}

/** Form data array → language of our form ('' when not ours). */
function mats_wpforms_data_lang( $form_data ): string {
	return is_array( $form_data ) ? mats_wpforms_form_lang( absint( $form_data['id'] ?? 0 ) ) : '';
}

/* ── create / update ─────────────────────────────────────────────────── */

/**
 * Saves a form definition. $form_id = 0 creates it. Uses WPForms' own
 * handler when available, otherwise writes the post directly.
 *
 * @return int Form ID, 0 on failure.
 */
function mats_wpforms_save( int $form_id, array $data ): int {
	$title   = (string) ( $data['settings']['form_title'] ?? 'Contacto MatSurfer' );
	$handler = mats_wpforms_handler();

	/* WPForms' path: add() creates the post, update() writes the full data */
	if ( $handler && method_exists( $handler, 'update' ) && ( $form_id || method_exists( $handler, 'add' ) ) ) {
		try {
			if ( ! $form_id ) {
				$form_id = absint( $handler->add( $title, array(), array( 'builder' => false ) ) );
			}
			if ( $form_id ) {
				$data['id'] = (string) $form_id;
				$saved      = $handler->update( $form_id, $data, array( 'cap' => false ) );
				if ( $saved && mats_wpforms_stored_ok( $form_id ) ) {
					return $form_id;
				}
			}
		} catch ( Throwable $e ) {
			unset( $e ); // fall back to writing the post ourselves
		}
	}

	/* fallback: the post itself (post type 'wpforms', JSON in post_content) */
	$filters_off = static function () {
		remove_filter( 'content_save_pre', 'balanceTags', 50 );
		remove_filter( 'content_save_pre', 'wp_filter_post_kses' );
		add_filter( 'wp_targeted_link_rel', '__return_empty_string', 50 );
	};
	$filters_on = static function () {
		remove_filter( 'wp_targeted_link_rel', '__return_empty_string', 50 );
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			add_filter( 'content_save_pre', 'wp_filter_post_kses' );
		}
	};

	$filters_off();
	if ( ! $form_id ) {
		$form_id = absint(
			wp_insert_post(
				array(
					'post_type'    => 'wpforms',
					'post_status'  => 'publish',
					'post_title'   => $title,
					'post_content' => '{}',
				),
				false
			)
		);
	}
	if ( $form_id ) {
		$data['id'] = (string) $form_id;
		wp_update_post(
			array(
				'ID'           => $form_id,
				'post_title'   => esc_html( $title ),
				'post_excerpt' => (string) ( $data['settings']['form_desc'] ?? '' ),
				'post_status'  => 'publish',
				'post_content' => mats_wpforms_encode( $data ),
			)
		);
	}
	$filters_on();

	return $form_id && mats_wpforms_stored_ok( $form_id ) ? $form_id : 0;
}

/** The stored post_content decodes to a form with our 8 fields. */
function mats_wpforms_stored_ok( int $form_id ): bool {
	clean_post_cache( $form_id );
	$data = mats_wpforms_decode( (string) get_post_field( 'post_content', $form_id, 'raw' ) );
	return isset( $data['fields']['1'], $data['fields']['8'], $data['settings'] );
}

/**
 * Creates the EN and ES contact forms when they are missing and stores
 * their IDs (settings form_en / form_es). Idempotent: an existing form is
 * kept as it is (staff may have edited it) unless $overwrite is true, in
 * which case it is rewritten in place (same ID).
 *
 * @return array{ok:bool, wpforms:bool, forms:array<string,array{id:int,status:string,message:string}>}
 */
function mats_wpforms_create_forms( bool $overwrite = false ): array {
	$report = array(
		'ok'      => true,
		'wpforms' => mats_wpforms_active(),
		'forms'   => array(),
	);
	$names  = array(
		'en' => 'inglés',
		'es' => 'español',
	);

	foreach ( mats_langs() as $lang ) {
		$id = mats_wpforms_form_id( $lang );
		if ( ! $id ) {
			$id = mats_wpforms_find_form( $lang );
		}

		if ( $id && ! $overwrite ) {
			mats_update_setting( 'form_' . $lang, $id, 'global' );
			update_post_meta( $id, '_mats_form_lang', $lang );
			$report['forms'][ $lang ] = array(
				'id'      => $id,
				'status'  => 'exists',
				'message' => sprintf( 'El formulario en %s ya existía (nº %d). No se ha cambiado.', $names[ $lang ], $id ),
			);
			continue;
		}

		$saved = mats_wpforms_save( $id, mats_wpforms_form_data( $lang ) );
		if ( ! $saved ) {
			$report['ok']             = false;
			$report['forms'][ $lang ] = array(
				'id'      => 0,
				'status'  => 'error',
				'message' => sprintf( 'No se ha podido crear el formulario en %s.', $names[ $lang ] ),
			);
			continue;
		}
		update_post_meta( $saved, '_mats_form_lang', $lang );
		mats_update_setting( 'form_' . $lang, $saved, 'global' );
		$report['forms'][ $lang ] = array(
			'id'      => $saved,
			'status'  => $id ? 'updated' : 'created',
			'message' => $id
				? sprintf( 'Formulario en %s actualizado con los textos originales (nº %d).', $names[ $lang ], $saved )
				: sprintf( 'Formulario en %s creado (nº %d).', $names[ $lang ], $saved ),
		);
	}

	if ( ! $report['wpforms'] ) {
		$report['notice'] = 'WPForms no está activo: los formularios están guardados y aparecerán en la web en cuanto actives WPForms.';
	}
	return $report;
}

/** Current state, for the setup wizard and the admin notice. */
function mats_wpforms_status(): array {
	$forms = array();
	foreach ( mats_langs() as $lang ) {
		$forms[ $lang ] = mats_wpforms_form_id( $lang );
	}
	return array(
		'active'  => mats_wpforms_active(),
		'forms'   => $forms,
		'missing' => array_keys( array_filter( $forms, static fn( $id ) => ! $id ) ),
	);
}

/* ── rendering (used by blocks/contact/render.php) ───────────────────── */

/**
 * The form for a language.
 *
 * @return array{html:string, form_id:int, reason:string} reason is '' when
 *         the form renders, otherwise 'inactive' | 'missing' | 'empty'.
 */
function mats_wpforms_render( string $lang ): array {
	$lang    = mats_normalize_lang( $lang );
	$form_id = mats_wpforms_form_id( $lang );
	$out     = array(
		'html'    => '',
		'form_id' => $form_id,
		'reason'  => '',
	);
	if ( ! mats_wpforms_active() || ! shortcode_exists( 'wpforms' ) ) {
		$out['reason'] = 'inactive';
		return $out;
	}
	if ( ! $form_id ) {
		$out['reason'] = 'missing';
		return $out;
	}
	$html = (string) do_shortcode( '[wpforms id="' . $form_id . '" title="false"]' );
	if ( '' === trim( wp_strip_all_tags( $html, true ) ) && false === strpos( $html, '<form' ) ) {
		$out['reason'] = 'empty';
		return $out;
	}
	$out['html'] = $html;
	return $out;
}

/** Data the contact-form script needs (preselection, messages, tracking). */
function mats_wpforms_client_config( string $lang, int $form_id ): array {
	$f        = mats_wpforms_copy( $lang );
	$messages = array(
		'1' => array( 'required' => (string) ( $f['fullName']['error'] ?? '' ) ),
		'2' => array(
			'required' => (string) ( $f['email']['error'] ?? '' ),
			'email'    => (string) ( $f['email']['error'] ?? '' ),
		),
		'3' => array( 'required' => (string) ( $f['organization']['error'] ?? '' ) ),
		'4' => array( 'required' => (string) ( $f['role']['error'] ?? '' ) ),
		'5' => array( 'required' => (string) ( $f['interest']['error'] ?? '' ) ),
		'7' => array( 'required' => (string) ( $f['message']['error'] ?? '' ) ),
		'8' => array( 'required' => (string) ( $f['consent']['error'] ?? '' ) ),
	);
	return array(
		'lang'         => $lang,
		'formId'       => $form_id,
		'interestMap'  => mats_wpforms_interest_map(),
		'interest'     => mats_wpforms_options( $lang, 'interest' ),
		'area'         => mats_wpforms_options( $lang, 'areaOfInterest' ),
		'messages'     => $messages,
		'autocomplete' => array(
			'1' => 'name',
			'2' => 'email',
			'3' => 'organization',
			'4' => 'organization-title',
		),
	);
}

/* ── assets: only where the contact block renders ────────────────────── */

function mats_wpforms_register_assets(): void {
	if ( ! wp_style_is( 'matsurfer-wpforms', 'registered' ) ) {
		/* on the site it must follow base.css; the editor loads base.css as an editor style */
		$deps = ! is_admin() && wp_style_is( 'matsurfer-base', 'registered' ) ? array( 'matsurfer-base' ) : array();
		wp_register_style( 'matsurfer-wpforms', MATS_URI . '/assets/css/wpforms.css', $deps, mats_ver( 'assets/css/wpforms.css' ) );
	}
	if ( ! wp_script_is( 'matsurfer-contact-form', 'registered' ) ) {
		wp_register_script(
			'matsurfer-contact-form',
			MATS_URI . '/assets/js/contact-form.js',
			array(),
			mats_ver( 'assets/js/contact-form.js' ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}
}

/** Enqueues the form styles + script (called upfront and from render.php). */
function mats_wpforms_enqueue_assets(): void {
	if ( is_admin() || mats_is_preview() ) {
		return;
	}
	mats_wpforms_register_assets();
	wp_enqueue_style( 'matsurfer-wpforms' );
	wp_enqueue_script( 'matsurfer-contact-form' );
}

/** Does the page being viewed contain the contact block? */
function mats_wpforms_page_has_contact(): bool {
	static $cache = array();
	if ( ! did_action( 'wp' ) || ! is_singular() ) {
		return false; // query not ready yet, or an archive
	}
	$id = (int) get_queried_object_id();
	if ( ! isset( $cache[ $id ] ) ) {
		$cache[ $id ] = false !== strpos( (string) get_post_field( 'post_content', $id ), '<!-- wp:matsurfer/contact' );
	}
	return $cache[ $id ];
}

/* late priority: printed after WPForms' own stylesheet */
add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( mats_wpforms_page_has_contact() && mats_wpforms_active() && mats_wpforms_form_id( mats_lang() ) ) {
			mats_wpforms_enqueue_assets();
		}
	},
	99
);

/* WPForms loads its CSS in <head> on the contact page (no flash of unstyled form) */
add_filter(
	'wpforms_global_assets',
	static function ( $global ) {
		return $global || ( ! is_admin() && mats_wpforms_page_has_contact() && mats_wpforms_form_id( mats_lang() ) );
	}
);

/* editor canvas: the real form previews with the site's look */
add_action(
	'enqueue_block_assets',
	static function () {
		if ( is_admin() ) {
			mats_wpforms_register_assets();
			wp_enqueue_style( 'matsurfer-wpforms' );
		}
	}
);

/* ── render-time adjustments of our two forms ────────────────────────── */

/** Request parameter (read-only preselection, no state change → no nonce). */
function mats_wpforms_query_param( string $name ): string {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_GET[ $name ] ) || ! is_string( $_GET[ $name ] ) ) {
		return '';
	}
	return sanitize_title( wp_unslash( $_GET[ $name ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}

/** Label of the option to preselect in field 5 / 6 for this request, '' if none. */
function mats_wpforms_preselect_label( string $lang, int $field_id ): string {
	if ( 5 === $field_id ) {
		$raw = mats_wpforms_query_param( 'interest' );
		if ( '' === $raw ) {
			return '';
		}
		$map   = mats_wpforms_interest_map();
		$value = isset( $map[ $raw ] ) ? (string) $map[ $raw ] : $raw;
		return mats_wpforms_options( $lang, 'interest' )[ $value ] ?? '';
	}
	if ( 6 === $field_id ) {
		$slug = mats_wpforms_query_param( 'application' );
		return '' === $slug ? '' : ( mats_wpforms_options( $lang, 'areaOfInterest' )[ $slug ] ?? '' );
	}
	return '';
}

add_filter(
	'wpforms_field_properties',
	static function ( $properties, $field, $form_data ) {
		if ( ! is_array( $properties ) || ! is_array( $field ) ) {
			return $properties;
		}
		$lang = mats_wpforms_data_lang( $form_data );
		if ( '' === $lang ) {
			return $properties;
		}
		$field_id = absint( $field['id'] ?? 0 );
		$type     = (string) ( $field['type'] ?? '' );

		/* "(optional)" tag on fields people may skip (CSS draws it) */
		if ( empty( $field['required'] ) && in_array( $type, array( 'text', 'textarea', 'select', 'email', 'name', 'number', 'url', 'phone' ), true ) && isset( $properties['container']['class'] ) && is_array( $properties['container']['class'] ) ) {
			$properties['container']['class'][] = 'lf-optional';
		}

		/* browser autofill, like the original form */
		$autocomplete = array(
			1 => 'name',
			2 => 'email',
			3 => 'organization',
			4 => 'organization-title',
		);
		if ( isset( $autocomplete[ $field_id ], $properties['inputs']['primary']['attr'] ) && is_array( $properties['inputs']['primary']['attr'] ) ) {
			$properties['inputs']['primary']['attr']['autocomplete'] = $autocomplete[ $field_id ];
		}

		/* ?interest= / ?application= preselect the option (labels are the values) */
		if ( 'select' === $type && in_array( $field_id, array( 5, 6 ), true ) && ! empty( $properties['inputs'] ) && is_array( $properties['inputs'] ) ) {
			$wanted = mats_wpforms_preselect_label( $lang, $field_id );
			if ( '' !== $wanted ) {
				$found = false;
				foreach ( $properties['inputs'] as $key => $input ) {
					if ( is_array( $input ) && trim( wp_strip_all_tags( (string) ( $input['label']['text'] ?? '' ) ) ) === $wanted ) {
						$found = $key;
						break;
					}
				}
				if ( false !== $found ) {
					foreach ( $properties['inputs'] as $key => $input ) {
						if ( is_array( $input ) && array_key_exists( 'default', $input ) ) {
							$properties['inputs'][ $key ]['default'] = ( $key === $found );
						}
					}
					$properties['inputs'][ $found ]['default'] = true;
				}
			}
		}

		/* consent: the privacy link always points at the current privacy page */
		if ( 'checkbox' === $type && 8 === $field_id && ! empty( $properties['inputs'] ) && is_array( $properties['inputs'] ) ) {
			foreach ( $properties['inputs'] as $key => $input ) {
				if ( is_array( $input ) && isset( $input['label']['text'] ) ) {
					$properties['inputs'][ $key ]['label']['text'] = mats_wpforms_refresh_privacy_link( (string) $input['label']['text'], $lang );
				}
			}
		}
		return $properties;
	},
	20,
	3
);

/**
 * Consent label with the privacy link pointing at the current privacy page
 * (or unlinked when that page is not published). Leaves labels that staff
 * rewrote without the "Privacy Policy" words untouched.
 */
function mats_wpforms_refresh_privacy_link( string $text, string $lang ): string {
	$consent = (array) ( mats_wpforms_copy( $lang )['consent'] ?? array() );
	$label   = trim( (string) ( $consent['privacyLabel'] ?? '' ) );
	if ( '' === $label ) {
		return $text;
	}
	$url     = mats_wpforms_privacy_url( $lang );
	$escaped = esc_html( $label );
	$link    = $url ? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . $escaped . '</a>' : $escaped;
	$quoted  = preg_quote( $escaped, '#' ) . '|' . preg_quote( $label, '#' );

	$linked = preg_replace( '#<a\b[^>]*>\s*(?:' . $quoted . ')\s*</a>#u', $link, $text, 1, $count );
	if ( $count && is_string( $linked ) ) {
		return $linked;
	}
	if ( $url && false === stripos( $text, '<a ' ) ) {
		$pos = strpos( $text, $escaped );
		$len = strlen( $escaped );
		if ( false === $pos ) {
			$pos = strpos( $text, $label );
			$len = strlen( $label );
		}
		if ( false !== $pos ) {
			return substr_replace( $text, $link, $pos, $len );
		}
	}
	return $text;
}

/* ── tracking: hidden inputs → notification email ────────────────────── */

/** Path of the page being rendered (server-side default for source_page). */
function mats_wpforms_current_path(): string {
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
	$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
	return '' === $path ? '/' : $path;
}

add_action(
	'wpforms_display_submit_before',
	static function ( $form_data = array() ) {
		$lang = mats_wpforms_data_lang( $form_data );
		if ( '' === $lang ) {
			return;
		}
		$values = array(
			'cta_source'  => '',
			'utm'         => '',
			'application' => mats_wpforms_query_param( 'application' ),
			'language'    => $lang,
			'source_page' => mats_is_preview() ? '' : mats_wpforms_current_path(),
		);
		foreach ( $values as $key => $value ) {
			printf(
				'<input type="hidden" name="matsurfer_meta[%1$s]" value="%2$s" data-mats-meta="%1$s">',
				esc_attr( $key ),
				esc_attr( $value )
			);
		}
	},
	10,
	1
);

/**
 * Tracking data of the submission being processed, sanitized. Usable by
 * other modules from wpforms_process_complete.
 *
 * @return array{language:string, source_page:string, cta_source:string, application:string, utm:string}
 */
function mats_wpforms_submission_meta( $form_data = array() ): array {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- WPForms validated this submission (its own token / anti-spam); values are only sanitized and shown in the email.
	$raw = isset( $_POST['matsurfer_meta'] ) && is_array( $_POST['matsurfer_meta'] ) ? wp_unslash( $_POST['matsurfer_meta'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$page_url = isset( $_POST['page_url'] ) && is_string( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '';
	// phpcs:enable

	$text = static function ( $key, $max = 200 ) use ( $raw ): string {
		$value = isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ? sanitize_text_field( (string) $raw[ $key ] ) : '';
		return mb_substr( $value, 0, $max );
	};

	/* language: the form decides (each language has its own form) */
	$lang = mats_wpforms_data_lang( $form_data );
	if ( '' === $lang ) {
		$lang = in_array( $text( 'language', 5 ), mats_langs(), true ) ? $text( 'language', 5 ) : '';
	}

	/* source page: a path on this site, shown as a full address */
	$path = (string) wp_parse_url( $text( 'source_page', 300 ), PHP_URL_PATH );
	if ( '' === $path && $page_url ) {
		$path = (string) wp_parse_url( $page_url, PHP_URL_PATH );
	}
	$source = '';
	if ( '' !== $path ) {
		$path   = '/' . ltrim( preg_replace( '#[^A-Za-z0-9/_\-.~%]#', '', $path ), '/' );
		$home   = wp_parse_url( home_url() );
		$origin = ( $home['scheme'] ?? 'https' ) . '://' . ( $home['host'] ?? '' ) . ( isset( $home['port'] ) ? ':' . $home['port'] : '' );
		$source = esc_url_raw( $origin . $path );
	}

	/* CTA that brought the visitor (data-cta-source), 'direct' when none */
	$cta = strtolower( preg_replace( '/[^A-Za-z0-9_\-:.]/', '', $text( 'cta_source', 64 ) ) );

	/* application slug (?application=), with its name when it exists */
	$app_slug = sanitize_title( $text( 'application', 80 ) );
	$app      = $app_slug;
	if ( $app_slug && function_exists( 'mats_get_applications' ) ) {
		foreach ( (array) mats_get_applications( $lang ? $lang : 'en' ) as $application ) {
			if ( is_array( $application ) && ( $application['slug'] ?? '' ) === $app_slug && ! empty( $application['title'] ) ) {
				$app = sanitize_text_field( (string) $application['title'] ) . ' (' . $app_slug . ')';
				break;
			}
		}
	}

	/* UTM: JSON from sessionStorage 'lead:utm' → "source: x · medium: y" */
	$utm      = '';
	$utm_raw  = isset( $raw['utm'] ) && is_string( $raw['utm'] ) ? $raw['utm'] : '';
	$utm_data = '' !== $utm_raw ? json_decode( mb_substr( $utm_raw, 0, 2000 ), true ) : null;
	if ( is_array( $utm_data ) ) {
		$parts = array();
		foreach ( array( 'source', 'medium', 'campaign', 'content', 'term' ) as $key ) {
			$value = isset( $utm_data[ $key ] ) && is_scalar( $utm_data[ $key ] ) ? mb_substr( sanitize_text_field( (string) $utm_data[ $key ] ), 0, 100 ) : '';
			if ( '' !== $value ) {
				$parts[] = $key . ': ' . $value;
			}
		}
		$utm = implode( ' · ', $parts );
	}

	$lang_label = '';
	if ( $lang ) {
		$meta       = mats_lang_meta( $lang );
		$lang_label = $meta['name'] . ' (' . $lang . ')';
	}

	return array(
		'language'    => $lang_label,
		'source_page' => $source,
		'cta_source'  => '' !== $cta ? $cta : 'direct',
		'application' => $app,
		'utm'         => $utm,
	);
}

/** Pseudo-fields (WPForms field format) carrying the tracking data. */
function mats_wpforms_meta_entries( $form_data ): array {
	$meta = mats_wpforms_submission_meta( $form_data );
	$out  = array();
	foreach ( mats_wpforms_meta_fields() as $key => $def ) {
		$out[ $def['id'] ] = array(
			'name'  => $def['label'],
			'value' => (string) ( $meta[ $key ] ?? '' ),
			'id'    => $def['id'],
			'type'  => 'mats_meta',
		);
	}
	return $out;
}

/*
 * The pseudo-fields only exist in the notification email:
 *  - their values are added to the email data (wpforms_entry_email_data);
 *    WPForms before 1.8.5 builds {all_fields} from that list;
 *  - newer WPForms builds {all_fields} by walking the form definition, so
 *    while a submission is processed the definition also lists them, with a
 *    type WPForms does not process (no empty values are created, nothing is
 *    validated, and the submitted fields that other code receives in
 *    wpforms_process_complete — e.g. the Contactos inbox — stay clean).
 * Either way they appear once, at the end of the email.
 */
add_filter(
	'wpforms_process_before_form_data',
	static function ( $form_data, $entry = array() ) {
		if ( ! is_array( $form_data ) || '' === mats_wpforms_data_lang( $form_data ) ) {
			return $form_data;
		}
		foreach ( mats_wpforms_meta_fields() as $def ) {
			if ( ! isset( $form_data['fields'][ $def['id'] ] ) ) {
				$form_data['fields'][ $def['id'] ] = array(
					'id'         => (string) $def['id'],
					'type'       => 'mats_meta',
					'label'      => $def['label'],
					'required'   => '',
					'label_hide' => '1',
					'css'        => '',
				);
			}
		}
		return $form_data;
	},
	10,
	2
);

add_filter(
	'wpforms_entry_email_data',
	static function ( $fields, $entry = array(), $form_data = array() ) {
		if ( ! is_array( $fields ) || '' === mats_wpforms_data_lang( $form_data ) ) {
			return $fields;
		}
		foreach ( mats_wpforms_meta_entries( $form_data ) as $id => $pseudo ) {
			$fields[ $id ] = $pseudo;
		}
		return $fields;
	},
	20,
	3
);

/* ── keep the notification address in sync with Ajustes de la web ───── */

add_action(
	'update_option_mats_settings',
	static function ( $old, $new ) {
		$defaults = mats_settings_defaults();
		$fallback = sanitize_email( (string) ( $defaults['global']['contact_email'] ?? '' ) );
		$before   = sanitize_email( (string) ( is_array( $old ) ? ( $old['global']['contact_email'] ?? '' ) : '' ) );
		$after    = sanitize_email( (string) ( is_array( $new ) ? ( $new['global']['contact_email'] ?? '' ) : '' ) );
		$before   = $before ? $before : $fallback;
		$after    = $after ? $after : $fallback;
		if ( ! $after || strtolower( $before ) === strtolower( $after ) ) {
			return;
		}
		foreach ( mats_langs() as $lang ) {
			$id = mats_wpforms_form_id( $lang );
			if ( ! $id ) {
				continue;
			}
			$data    = mats_wpforms_decode( (string) get_post_field( 'post_content', $id, 'raw' ) );
			$changed = false;
			foreach ( (array) ( $data['settings']['notifications'] ?? array() ) as $key => $notification ) {
				if ( is_array( $notification ) && strtolower( trim( (string) ( $notification['email'] ?? '' ) ) ) === strtolower( $before ) ) {
					$data['settings']['notifications'][ $key ]['email'] = $after;
					$changed = true;
				}
			}
			if ( $changed ) {
				mats_wpforms_save( $id, $data );
			}
		}
	},
	10,
	2
);

/* ── admin: notice + "create the forms" action ───────────────────────── */

add_action(
	'admin_notices',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$id     = $screen ? (string) $screen->id : '';
		$where  = in_array( $id, array( 'dashboard', 'edit-page', 'page', 'plugins', 'themes' ), true ) || false !== strpos( $id, 'wpforms' ) || false !== strpos( $id, 'mats' ) || false !== strpos( $id, 'matsurfer' );

		/* result of the button below */
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only
		$done = isset( $_GET['mats_wpforms'] ) ? sanitize_key( wp_unslash( $_GET['mats_wpforms'] ) ) : '';
		if ( 'created' === $done ) {
			echo '<div class="notice notice-success is-dismissible"><p><strong>Formularios de contacto listos.</strong> La página Contacto ya muestra el formulario en inglés y en español. Los mensajes llegarán a ' . esc_html( mats_contact_email() ) . '.</p></div>';
		} elseif ( 'error' === $done ) {
			echo '<div class="notice notice-error"><p><strong>No se han podido crear los formularios de contacto.</strong> Comprueba que WPForms está activo y vuelve a intentarlo. Si sigue fallando, impórtalos a mano en WPForms → Herramientas → Importar (archivos wpforms-en.json y wpforms-es.json de la carpeta del tema, inc/data/).</p></div>';
		}

		if ( ! $where ) {
			return;
		}
		$status = mats_wpforms_status();
		if ( ! $status['active'] ) {
			if ( in_array( $id, array( 'dashboard', 'plugins' ), true ) ) {
				echo '<div class="notice notice-warning"><p><strong>Falta el plugin WPForms.</strong> Sin él, la página Contacto solo muestra el email de contacto. Instálalo y actívalo en <a href="' . esc_url( admin_url( 'plugin-install.php?s=wpforms&tab=search&type=term' ) ) . '">Plugins → Añadir nuevo</a> (busca «WPForms»).</p></div>';
			}
			return;
		}
		if ( ! $status['missing'] ) {
			return;
		}
		$which = count( $status['missing'] ) > 1 ? 'en inglés y en español' : ( 'es' === $status['missing'][0] ? 'en español' : 'en inglés' );
		?>
		<div class="notice notice-warning">
			<p><strong>Falta el formulario de contacto <?php echo esc_html( $which ); ?>.</strong> Mientras no exista, la página Contacto muestra solo el email de contacto. Pulsa el botón y el tema lo crea con los textos y campos de la web.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0 0 .75em">
				<input type="hidden" name="action" value="mats_wpforms_create">
				<?php wp_nonce_field( 'mats_wpforms_create' ); ?>
				<button type="submit" class="button button-primary">Crear los formularios de contacto</button>
			</form>
		</div>
		<?php
	}
);

/* the result flag disappears from the address bar after the notice shows */
add_filter(
	'removable_query_args',
	static function ( $args ) {
		$args[] = 'mats_wpforms';
		return $args;
	}
);

add_action(
	'admin_post_mats_wpforms_create',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( 'No tienes permiso para hacer esto.' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'mats_wpforms_create' );
		$report = mats_wpforms_create_forms( false );
		$back   = wp_get_referer();
		$back   = $back ? $back : admin_url( 'index.php' );
		wp_safe_redirect( add_query_arg( 'mats_wpforms', $report['ok'] ? 'created' : 'error', remove_query_arg( 'mats_wpforms', $back ) ) );
		exit;
	}
);
