<?php
/**
 * Plugin Name: WPForms TEST STUB (MatSurfer QA only — never ship)
 * Description: LOCAL TEST STUB that emulates the small part of WPForms Lite the MatSurfer theme relies on (forms post type, wpforms() handlers, [wpforms] shortcode with "modern" markup, processing, notifications, hooks). It is NOT WPForms and must never be installed on a real site.
 * Version: 0.2.0
 * Author: MatSurfer QA
 *
 * ─────────────────────────────────────────────────────────────────────────
 * TEST STUB. Lives in wordpress/tools/qa/ (outside the theme, never zipped).
 * Install only in a local QA instance:
 *   ln -s …/wordpress/tools/qa/wpforms-stub /home/user/wpsite/wp-content/plugins/wpforms-stub
 *   activate it in ONE instance database (activation is per database).
 *
 * What it emulates (same names/signatures as WPForms Lite 1.8/1.9):
 *   - post type 'wpforms', form JSON in post_content (wpforms_encode/decode)
 *   - wpforms()->obj( 'form' ) / ->get( 'form' ): add(), update(), get()
 *   - [wpforms id="N" title="false"] with the modern markup structure
 *     (wpforms-container-full wpforms-render-modern, wpforms-field-container,
 *     wpforms-field-{type}, wpforms-submit-container…) or the classic one
 *     (option wpforms_stub_markup = 'classic')
 *   - field properties filters: wpforms_field_properties_{type},
 *     wpforms_field_properties
 *   - actions/filters around the submit button and processing:
 *     wpforms_display_submit_before/after, wpforms_process_before_form_data,
 *     wpforms_process_filter, wpforms_process, wpforms_entry_email,
 *     wpforms_entry_email_data, wpforms_process_complete( $fields, $entry,
 *     $form_data, $entry_id ), wpforms_frontend_confirmation_message,
 *     wpforms_smart_tag_process, wpforms_global_assets
 *   - AJAX submit (admin-ajax.php?action=wpforms_submit) and the jQuery
 *     events wpformsAjaxSubmitSuccess / wpformsAjaxSubmitFailed /
 *     wpformsAjaxSubmitSuccessConfirmation (assets/stub.js)
 *   - honeypot (wpforms[hp]) and a token check (data-token → wpforms[token])
 *   - notification email via wp_mail(), {all_fields} rendered like
 *     WPForms ≥ 1.8.5 (iterating $form_data['fields']); set the option
 *     wpforms_stub_email_iterate = 'fields' for the older behaviour.
 *   - QA switches (options): wpforms_stub_markup = 'classic',
 *     wpforms_stub_email_iterate = 'fields', wpforms_stub_ignore_defaults = 1
 *     (server-side preselection ignored → tests the theme's JS fallback)
 *   - a hostile approximation of WPForms' own front-end CSS
 *     (assets/stub-modern.css / stub-classic.css) so the theme's overrides
 *     are tested against competing selectors.
 * ─────────────────────────────────────────────────────────────────────────
 *
 * @package matsurfer-qa
 */

defined( 'ABSPATH' ) || exit;

/* the real plugin is active: stay out of the way. (Every function that
   shares a name with WPForms is declared conditionally below, so PHP does
   not hoist it and this check sees only the real plugin.) */
if ( function_exists( 'wpforms' ) || defined( 'WPFORMS_PLUGIN_DIR' ) ) {
	return;
}

define( 'WPFORMS_STUB', true );
define( 'WPFORMS_VERSION', '1.9.0-stub' );

/* ── helpers with the real names ─────────────────────────────────────── */

if ( ! function_exists( 'wpforms_encode' ) ) {
	function wpforms_encode( $data = false ) {
		if ( empty( $data ) ) {
			return false;
		}
		return wp_slash( wp_json_encode( $data ) );
	}
}

if ( ! function_exists( 'wpforms_decode' ) ) {
	function wpforms_decode( $data, $to_array = true ) {
		if ( ! $data || empty( $data ) ) {
			return false;
		}
		return json_decode( $data, $to_array );
	}
}

if ( ! function_exists( 'wpforms_setting' ) ) {
	function wpforms_setting( $key, $default = false ) {
		$options = get_option( 'wpforms_settings', array() );
		return is_array( $options ) && isset( $options[ $key ] ) ? $options[ $key ] : $default;
	}
}

if ( ! function_exists( 'wpforms_get_render_engine' ) ) {
	function wpforms_get_render_engine() {
		return 'classic' === get_option( 'wpforms_stub_markup', 'modern' ) ? 'classic' : 'modern';
	}
}

if ( ! function_exists( 'wpforms_html_attributes' ) ) {
	function wpforms_html_attributes( $id = '', $class = array(), $datas = array(), $atts = array() ) {
		$out = '';
		if ( $id ) {
			$out .= ' id="' . esc_attr( $id ) . '"';
		}
		$class = array_filter( array_map( 'trim', (array) $class ) );
		if ( $class ) {
			$out .= ' class="' . esc_attr( implode( ' ', $class ) ) . '"';
		}
		foreach ( (array) $datas as $key => $value ) {
			$out .= ' data-' . sanitize_html_class( $key ) . '="' . esc_attr( (string) $value ) . '"';
		}
		foreach ( (array) $atts as $key => $value ) {
			if ( '' === $value || null === $value || false === $value ) {
				continue;
			}
			$out .= ' ' . sanitize_key( $key ) . '="' . esc_attr( (string) $value ) . '"';
		}
		return $out;
	}
}

/* ── the wpforms() container ─────────────────────────────────────────── */

final class WPForms_Stub {
	private static $instance = null;
	private $objs            = array();

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance                    = new self();
			self::$instance->objs['form']      = new WPForms_Stub_Form_Handler();
			self::$instance->objs['frontend']  = new WPForms_Stub_Frontend();
			self::$instance->objs['process']   = new WPForms_Stub_Process();
			self::$instance->objs['smart_tags'] = new WPForms_Stub_Smart_Tags();
		}
		return self::$instance;
	}

	public function obj( $name ) {
		return $this->objs[ $name ] ?? null;
	}

	/** Deprecated alias kept by WPForms for back compat. */
	public function get( $name ) {
		return $this->obj( $name );
	}

	public function __get( $name ) {
		return $this->obj( $name );
	}
}

if ( ! function_exists( 'wpforms' ) ) {
	function wpforms() {
		return WPForms_Stub::instance();
	}
}

/* ── forms storage ───────────────────────────────────────────────────── */

final class WPForms_Stub_Form_Handler {

	public function __construct() {
		add_action( 'init', array( $this, 'register_cpt' ) );
	}

	public function register_cpt() {
		register_post_type(
			'wpforms',
			array(
				'labels'              => array( 'name' => 'WPForms (stub)' ),
				'public'              => false,
				'exclude_from_search' => true,
				'show_ui'             => false,
				'show_in_admin_bar'   => false,
				'rewrite'             => false,
				'query_var'           => false,
				'can_export'          => false,
				'supports'            => array( 'title', 'author', 'revisions' ),
				'capability_type'     => 'wpforms_form',
				'map_meta_cap'        => false,
			)
		);
	}

	/** Same contract as WPForms_Form_Handler::get(). */
	public function get( $id = '', $args = array() ) {
		if ( $id ) {
			$post = get_post( absint( $id ) );
			if ( ! $post || 'wpforms' !== $post->post_type ) {
				return false;
			}
			if ( ! empty( $args['content_only'] ) ) {
				return wpforms_decode( $post->post_content );
			}
			return $post;
		}
		return get_posts(
			array(
				'post_type'   => 'wpforms',
				'numberposts' => -1,
				'post_status' => 'publish',
				'orderby'     => 'ID',
				'order'       => 'ASC',
			)
		);
	}

	/** Same contract as WPForms_Form_Handler::add( $title, $args, $data ). */
	public function add( $title = '', $args = array(), $data = array() ) {
		if ( empty( $title ) ) {
			return false;
		}
		remove_filter( 'content_save_pre', 'balanceTags', 50 );
		add_filter( 'wp_targeted_link_rel', '__return_empty_string', 50, 1 );

		$form_content   = array(
			'field_id' => '0',
			'settings' => array(
				'form_title' => sanitize_text_field( $title ),
				'form_desc'  => '',
			),
		);
		$args_form_data = isset( $args['post_content'] ) ? json_decode( wp_unslash( $args['post_content'] ), true ) : null;
		if ( is_array( $args_form_data ) ) {
			$form_content = wp_parse_args( $args_form_data, $form_content );
		}
		$form    = wp_parse_args(
			$args,
			array(
				'post_title'   => esc_html( $title ),
				'post_status'  => 'publish',
				'post_type'    => 'wpforms',
				'post_content' => wpforms_encode( $form_content ),
			)
		);
		$form['post_content'] = wpforms_encode( $form_content );
		$form_id              = wp_insert_post( $form );
		do_action( 'wpforms_create_form', $form_id, $form, $data );
		return $form_id;
	}

	/** Same contract as WPForms_Form_Handler::update( $form_id, $data, $args ). */
	public function update( $form_id = '', $data = array(), $args = array() ) {
		if ( empty( $data ) ) {
			return false;
		}
		if ( empty( $form_id ) && isset( $data['id'] ) ) {
			$form_id = $data['id'];
		}
		if ( ( ! isset( $args['cap'] ) || false !== $args['cap'] ) && ! current_user_can( 'manage_options' ) ) {
			return false; // the real handler checks wpforms_current_user_can( 'edit_form_single' )
		}
		$data = wp_unslash( $data );
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			$data = map_deep( $data, 'wp_strip_all_tags' );
		}
		$title = empty( $data['settings']['form_title'] ) ? get_the_title( $form_id ) : $data['settings']['form_title'];
		$desc  = empty( $data['settings']['form_desc'] ) ? '' : $data['settings']['form_desc'];

		$data['field_id'] = ! empty( $data['field_id'] ) ? wpforms_stub_validate_id( $data['field_id'] ) : '0';

		remove_filter( 'content_save_pre', 'balanceTags', 50 );
		add_filter( 'wp_targeted_link_rel', '__return_empty_string', 50, 1 );

		$form = array(
			'ID'           => $form_id,
			'post_title'   => esc_html( $title ),
			'post_excerpt' => $desc,
			'post_content' => wpforms_encode( $data ),
		);
		$form    = apply_filters( 'wpforms_save_form_args', $form, $data, $args );
		$form_id = wp_update_post( $form );
		do_action( 'wpforms_save_form', $form_id, $form );
		return $form_id;
	}
}

function wpforms_stub_validate_id( $id ) {
	return is_numeric( $id ) ? (string) absint( $id ) : '0';
}

/* ── smart tags ──────────────────────────────────────────────────────── */

final class WPForms_Stub_Smart_Tags {

	public function process( $content, $form_data, $fields = array(), $entry_id = 0, $context = '' ) {
		$content = (string) $content;
		$content = preg_replace_callback(
			'/{field_id="(\d+)"}/',
			static function ( $m ) use ( $fields ) {
				return isset( $fields[ $m[1] ]['value'] ) ? (string) $fields[ $m[1] ]['value'] : '';
			},
			$content
		);
		$content = str_replace(
			array( '{admin_email}', '{site_name}' ),
			array( (string) get_option( 'admin_email' ), wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) ),
			$content
		);
		if ( preg_match_all( '/{([a-z0-9_]+)}/', $content, $tags ) ) {
			foreach ( array_unique( $tags[1] ) as $tag ) {
				if ( 'all_fields' === $tag ) {
					continue;
				}
				$content = apply_filters( 'wpforms_smart_tag_process', $content, $tag, $form_data, $fields, $entry_id, $context );
			}
		}
		return $content;
	}
}

/* ── front end ───────────────────────────────────────────────────────── */

final class WPForms_Stub_Frontend {

	/** Forms rendered on this request: id => form_data. */
	public $forms = array();

	/** Result of a non-AJAX submission processed earlier in this request. */
	public $result = array();

	public function __construct() {
		add_shortcode( 'wpforms', array( $this, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets_header' ) );
		add_action( 'wp_footer', array( $this, 'assets_footer' ), 15 );
	}

	public function assets_global() {
		return (bool) apply_filters( 'wpforms_global_assets', wpforms_setting( 'global-assets', false ) );
	}

	public function assets_header() {
		if ( ! $this->assets_global() ) {
			return;
		}
		$this->assets_css();
	}

	public function assets_css() {
		$file = 'modern' === wpforms_get_render_engine() ? 'stub-modern.css' : 'stub-classic.css';
		wp_enqueue_style( 'wpforms-stub-full', plugins_url( 'assets/' . $file, __FILE__ ), array(), (string) filemtime( __DIR__ . '/assets/' . $file ) );
	}

	public function assets_footer() {
		if ( empty( $this->forms ) ) {
			return;
		}
		$this->assets_css(); // late: printed in the footer, like WPForms without global assets
		wp_enqueue_script( 'wpforms-stub', plugins_url( 'assets/stub.js', __FILE__ ), array( 'jquery' ), (string) filemtime( __DIR__ . '/assets/stub.js' ), true );
		wp_localize_script(
			'wpforms-stub',
			'wpforms_settings',
			array(
				'ajaxurl'      => admin_url( 'admin-ajax.php' ),
				'val_required' => 'This field is required.',
				'val_email'    => 'Please enter a valid email address.',
				'engine'       => wpforms_get_render_engine(),
			)
		);
	}

	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'          => false,
				'title'       => false,
				'description' => false,
			),
			$atts,
			'output'
		);
		ob_start();
		$this->output( $atts['id'], $atts['title'], $atts['description'] );
		return ob_get_clean();
	}

	public static function token( $form_id ) {
		return md5( 'wpforms-stub-' . absint( $form_id ) . wp_salt( 'nonce' ) );
	}

	/** Field properties, built like WPForms does and passed through the same filters. */
	public function field_properties( array $field, array $form_data ) {
		$form_id  = absint( $form_data['id'] );
		$field_id = absint( $field['id'] );
		$type     = (string) $field['type'];
		$required = ! empty( $field['required'] );
		$base_id  = "wpforms-{$form_id}-field_{$field_id}";
		$posted   = $this->posted_value( $form_id, $field_id );

		$properties = array(
			'container'   => array(
				'attr'  => array( 'style' => '' ),
				'class' => array_merge( array( 'wpforms-field', 'wpforms-field-' . $type ), array_filter( explode( ' ', (string) ( $field['css'] ?? '' ) ) ) ),
				'data'  => array( 'field-id' => $field_id ),
				'id'    => "{$base_id}-container",
			),
			'label'       => array(
				'attr'     => array( 'for' => $base_id ),
				'class'    => array( 'wpforms-field-label' ),
				'data'     => array(),
				'disabled' => false,
				'hidden'   => ! empty( $field['label_hide'] ),
				'id'       => '',
				'required' => $required,
				'value'    => (string) ( $field['label'] ?? '' ),
			),
			'description' => array(
				'attr'     => array(),
				'class'    => array( 'wpforms-field-description' ),
				'data'     => array(),
				'id'       => "{$base_id}-description",
				'position' => 'after',
				'value'    => (string) ( $field['description'] ?? '' ),
			),
			'error'       => array(
				'attr'  => array( 'for' => $base_id ),
				'class' => array( 'wpforms-error' ),
				'data'  => array(),
				'id'    => '',
				'value' => $this->result['errors'][ $form_id ][ $field_id ] ?? '',
			),
			'inputs'      => array(
				'primary' => array(
					'attr'     => array(
						'name'        => "wpforms[fields][{$field_id}]",
						'value'       => is_string( $posted ) ? $posted : '',
						'placeholder' => (string) ( $field['placeholder'] ?? '' ),
					),
					'class'    => array( 'wpforms-field-' . ( $field['size'] ?? 'medium' ) ),
					'data'     => array(),
					'id'       => $base_id,
					'required' => $required ? 'required' : '',
				),
			),
		);
		if ( $properties['label']['hidden'] ) {
			$properties['label']['class'][] = 'wpforms-label-hide';
		}
		if ( $required ) {
			$properties['inputs']['primary']['class'][] = 'wpforms-field-required';
		}
		if ( ! empty( $properties['error']['value'] ) ) {
			$properties['container']['class'][]          = 'wpforms-has-error';
			$properties['inputs']['primary']['class'][] = 'wpforms-error';
		}

		if ( in_array( $type, array( 'select', 'checkbox' ), true ) ) {
			unset( $properties['inputs']['primary'] );
			$properties['input_container'] = array(
				'class' => 'checkbox' === $type ? ( $required ? array( 'wpforms-field-required' ) : array() ) : array( 'wpforms-field-' . ( $field['size'] ?? 'medium' ) ),
				'data'  => array(),
				'id'    => $base_id,
				'attr'  => array( 'name' => 'select' === $type ? "wpforms[fields][{$field_id}]" : '' ),
			);
			if ( 'select' === $type && $required ) {
				$properties['input_container']['class'][] = 'wpforms-field-required';
			}
			foreach ( (array) ( $field['choices'] ?? array() ) as $key => $choice ) {
				$label   = (string) ( $choice['label'] ?? '' );
				$checked = 'checkbox' === $type ? ( is_array( $posted ) && in_array( wp_strip_all_tags( $label ), array_map( 'strval', $posted ), true ) ) : ( is_string( $posted ) && $posted === $label );

				$properties['inputs'][ $key ] = array(
					'container' => array(
						'attr'  => array(),
						'class' => array( "choice-{$key}", 'depth-1' ),
						'data'  => array(),
						'id'    => '',
					),
					'label'     => array(
						'attr'  => array( 'for' => "{$base_id}_{$key}" ),
						'class' => array( 'wpforms-field-label-inline' ),
						'data'  => array(),
						'id'    => '',
						'text'  => $label,
					),
					'attr'      => array(
						'name'  => 'checkbox' === $type ? "wpforms[fields][{$field_id}][]" : "wpforms[fields][{$field_id}]",
						'value' => isset( $field['show_values'] ) ? (string) ( $choice['value'] ?? '' ) : wp_strip_all_tags( $label ),
					),
					'class'     => array(),
					'data'      => array(),
					'id'        => "{$base_id}_{$key}",
					'image'     => '',
					'required'  => $required ? 'required' : '',
					'default'   => $checked || ( null === $posted && isset( $choice['default'] ) ),
				);
			}
		}

		$properties = apply_filters( "wpforms_field_properties_{$type}", $properties, $field, $form_data );
		$properties = apply_filters( 'wpforms_field_properties', $properties, $field, $form_data );

		/* QA switch: behave like a WPForms version that ignores 'default' set by filters */
		if ( get_option( 'wpforms_stub_ignore_defaults' ) && null === $posted && ! empty( $properties['inputs'] ) ) {
			foreach ( $properties['inputs'] as $key => $input ) {
				if ( is_array( $input ) && isset( $input['default'] ) ) {
					$properties['inputs'][ $key ]['default'] = false;
				}
			}
		}
		return $properties;
	}

	private function posted_value( $form_id, $field_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification -- test stub re-displaying a failed submission
		if ( empty( $this->result['errors'][ $form_id ] ) || ! isset( $_POST['wpforms']['fields'][ $field_id ] ) ) {
			return null;
		}
		$value = wp_unslash( $_POST['wpforms']['fields'][ $field_id ] ); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
		return is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : sanitize_textarea_field( $value );
	}

	public function output( $id, $title = false, $description = false ) {
		$form = wpforms()->obj( 'form' )->get( absint( $id ) );
		if ( ! $form || 'publish' !== $form->post_status ) {
			return;
		}
		$form_data = apply_filters( 'wpforms_frontend_form_data', wpforms_decode( $form->post_content ) );
		if ( empty( $form_data['fields'] ) ) {
			return;
		}
		$form_id   = absint( $form->ID );
		$modern    = 'modern' === wpforms_get_render_engine();
		$settings  = $form_data['settings'] ?? array();
		$is_ajax   = ! empty( $settings['ajax_submit'] );
		$container = array( 'wpforms-container', 'wpforms-container-full' );
		if ( $modern ) {
			$container[] = 'wpforms-render-modern';
		}

		$this->forms[ $form_id ] = $form_data;

		/* confirmation after a successful non-AJAX submission */
		if ( ! empty( $this->result['success'][ $form_id ] ) ) {
			printf( '<div class="%s" id="wpforms-%d">', esc_attr( implode( ' ', $container ) ), $form_id );
			echo $this->result['success'][ $form_id ]; // phpcs:ignore WordPress.Security.EscapeOutput -- built by confirmation()
			echo '</div>';
			return;
		}

		$form_classes = array( 'wpforms-validate', 'wpforms-form' );
		if ( $is_ajax ) {
			$form_classes[] = 'wpforms-ajax-form';
		}
		if ( ! empty( $settings['form_class'] ) ) {
			$form_classes = array_merge( $form_classes, explode( ' ', $settings['form_class'] ) );
		}
		$antispam = ! empty( $settings['antispam'] ) || ! empty( $settings['antispam_v3'] );

		printf( '<div class="%s" id="wpforms-%d">', esc_attr( implode( ' ', $container ) ), $form_id );
		do_action( 'wpforms_frontend_output_before', $form_data, $form );
		printf(
			'<form id="wpforms-form-%1$d" class="%2$s" data-formid="%1$d" method="post" enctype="multipart/form-data" action="%3$s"%4$s>',
			$form_id,
			esc_attr( implode( ' ', array_filter( $form_classes ) ) ),
			esc_url( remove_query_arg( 'wpforms' ) ),
			$antispam ? ' data-token="' . esc_attr( self::token( $form_id ) ) . '" data-token-time="' . esc_attr( (string) time() ) . '"' : ''
		);
		if ( $modern ) {
			echo '<noscript class="wpforms-error-noscript">Please enable JavaScript in your browser to complete this form.</noscript>';
		}
		if ( ! empty( $this->result['errors'][ $form_id ]['header'] ) ) {
			echo '<div class="wpforms-error-container" role="alert"><p>' . esc_html( $this->result['errors'][ $form_id ]['header'] ) . '</p></div>';
		}
		if ( 'false' !== $title && $title ) {
			echo '<div class="wpforms-head-container"><div class="wpforms-title">' . esc_html( $settings['form_title'] ?? '' ) . '</div></div>';
		}

		echo '<div class="wpforms-field-container">';
		foreach ( $form_data['fields'] as $field ) {
			$this->field( $field, $form_data, $modern );
		}
		/* honeypot (WPForms prints it with a random-looking label) */
		printf(
			'<div class="wpforms-field wpforms-field-hp"><label for="wpforms-%1$d-field-hp" class="wpforms-field-label">Phone</label><input type="text" name="wpforms[hp]" id="wpforms-%1$d-field-hp" class="wpforms-field-medium"></div>',
			$form_id
		);
		echo '</div><!-- .wpforms-field-container -->';

		$submit = ! empty( $settings['submit_text'] ) ? $settings['submit_text'] : 'Submit';
		$data   = array();
		if ( ! empty( $settings['submit_text_processing'] ) ) {
			$data['alt-text']    = $settings['submit_text_processing'];
			$data['submit-text'] = $submit;
		}
		$classes = array( 'wpforms-submit' );
		if ( ! empty( $settings['submit_class'] ) ) {
			$classes = array_merge( $classes, array_map( 'sanitize_html_class', explode( ' ', $settings['submit_class'] ) ) );
		}

		echo '<div class="wpforms-submit-container">';
		printf( '<input type="hidden" name="wpforms[id]" value="%d">', $form_id );
		printf( '<input type="hidden" name="page_title" value="%s">', esc_attr( (string) get_the_title() ) );
		printf( '<input type="hidden" name="page_url" value="%s">', esc_url( home_url( add_query_arg( array() ) ) ) );
		printf( '<input type="hidden" name="url_referer" value="">' );
		if ( is_singular() ) {
			printf( '<input type="hidden" name="wpforms[post_id]" value="%d">', absint( get_the_ID() ) );
		}
		do_action( 'wpforms_display_submit_before', $form_data );
		printf(
			'<button type="submit" name="wpforms[submit]"%s>%s</button>',
			wpforms_html_attributes( "wpforms-submit-{$form_id}", $classes, $data, array( 'aria-live' => 'assertive', 'value' => 'wpforms-submit' ) ),
			esc_html( $submit )
		);
		if ( $is_ajax ) {
			echo '<img src="' . esc_url( includes_url( 'images/spinner.gif' ) ) . '" class="wpforms-submit-spinner" style="display: none;" width="26" height="26" alt="Loading">';
		}
		do_action( 'wpforms_display_submit_after', $form_data );
		echo '</div>';
		echo '</form>';
		do_action( 'wpforms_frontend_output_after', $form_data, $form );
		echo '</div><!-- .wpforms-container -->';
	}

	private function field( array $field, array $form_data, bool $modern ) {
		$type = (string) ( $field['type'] ?? '' );
		if ( ! in_array( $type, array( 'name', 'email', 'text', 'textarea', 'select', 'checkbox' ), true ) ) {
			return;
		}
		$p        = $this->field_properties( $field, $form_data );
		$required = $p['label']['required'] ? ' <span class="wpforms-required-label"' . ( $modern ? ' aria-hidden="true"' : '' ) . '>*</span>' : '';

		printf( '<div%s>', wpforms_html_attributes( $p['container']['id'], $p['container']['class'], $p['container']['data'], $p['container']['attr'] ) );

		if ( 'checkbox' === $type ) {
			if ( $modern ) {
				echo '<fieldset>';
				printf( '<legend%s>%s%s</legend>', wpforms_html_attributes( '', array_merge( array( 'wpforms-field-label' ), $p['label']['hidden'] ? array( 'wpforms-label-hide' ) : array() ), array(), array() ), esc_html( $p['label']['value'] ), $required ); // phpcs:ignore WordPress.Security.EscapeOutput
			} else {
				printf( '<label%s>%s%s</label>', wpforms_html_attributes( '', $p['label']['class'], $p['label']['data'], $p['label']['attr'] ), esc_html( $p['label']['value'] ), $required ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			$ic = $p['input_container'];
			printf( '<ul%s>', wpforms_html_attributes( $ic['id'], $ic['class'], $ic['data'], array() ) );
			foreach ( $p['inputs'] as $key => $choice ) {
				printf( '<li%s>', wpforms_html_attributes( '', array_merge( $choice['container']['class'], $choice['default'] ? array( 'wpforms-selected' ) : array() ), array(), array() ) );
				printf(
					'<input type="checkbox"%s%s%s>',
					wpforms_html_attributes( $choice['id'], $choice['class'], $choice['data'], $choice['attr'] ),
					$choice['required'] ? ' required' : '',
					$choice['default'] ? ' checked' : ''
				);
				printf( '<label%s>%s</label>', wpforms_html_attributes( $choice['label']['id'], $choice['label']['class'], $choice['label']['data'], $choice['label']['attr'] ), wp_kses_post( $choice['label']['text'] ) );
				echo '</li>';
			}
			echo '</ul>';
			$this->error( $p, $modern, $p['input_container']['id'] );
			if ( $modern ) {
				echo '</fieldset>';
			}
			echo '</div>';
			return;
		}

		if ( ! $p['label']['disabled'] && '' !== $p['label']['value'] ) {
			printf( '<label%s>%s%s</label>', wpforms_html_attributes( $p['label']['id'], $p['label']['class'], $p['label']['data'], $p['label']['attr'] ), esc_html( $p['label']['value'] ), $required ); // phpcs:ignore WordPress.Security.EscapeOutput
		}

		if ( 'select' === $type ) {
			$ic          = $p['input_container'];
			$has_default = false;
			foreach ( $p['inputs'] as $choice ) {
				$has_default = $has_default || ! empty( $choice['default'] );
			}
			printf(
				'<select%s%s>',
				wpforms_html_attributes( $ic['id'], array_merge( $ic['class'], ! empty( $p['error']['value'] ) ? array( 'wpforms-error' ) : array() ), $ic['data'], $ic['attr'] ),
				! empty( $field['required'] ) ? ' required="required"' : ''
			);
			if ( ! empty( $field['placeholder'] ) ) {
				printf( '<option value="" class="placeholder" disabled %s>%s</option>', $has_default ? '' : 'selected="selected"', esc_html( $field['placeholder'] ) );
			}
			foreach ( $p['inputs'] as $choice ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $choice['attr']['value'] ), ! empty( $choice['default'] ) ? ' selected="selected"' : '', esc_html( $choice['label']['text'] ) );
			}
			echo '</select>';
		} else {
			$in    = $p['inputs']['primary'];
			$attrs = $in['attr'];
			if ( 'textarea' === $type ) {
				$value = (string) $attrs['value'];
				unset( $attrs['value'] );
				printf( '<textarea%s%s>%s</textarea>', wpforms_html_attributes( $in['id'], $in['class'], $in['data'], $attrs ), $in['required'] ? ' required' : '', esc_textarea( $value ) );
			} else {
				$input_type = 'email' === $type ? 'email' : 'text';
				if ( 'email' === $type ) {
					$attrs['spellcheck'] = 'false';
				}
				printf( '<input type="%s"%s%s>', esc_attr( $input_type ), wpforms_html_attributes( $in['id'], $in['class'], $in['data'], $attrs ), $in['required'] ? ' required' : '' );
			}
		}
		$this->error( $p, $modern, $p['inputs']['primary']['id'] ?? $p['input_container']['id'] ?? '' );
		echo '</div>';
	}

	private function error( array $p, bool $modern, string $for ) {
		if ( empty( $p['error']['value'] ) ) {
			return;
		}
		if ( $modern ) {
			printf( '<em id="%s-error" class="wpforms-error" role="alert" aria-label="Error message">%s</em>', esc_attr( $for ), esc_html( $p['error']['value'] ) );
		} else {
			printf( '<label id="%1$s-error" class="wpforms-error" for="%1$s">%2$s</label>', esc_attr( $for ), esc_html( $p['error']['value'] ) );
		}
	}
}

/* ── processing ──────────────────────────────────────────────────────── */

final class WPForms_Stub_Process {

	/** Field types this stub knows (others are ignored, as WPForms does). */
	const KNOWN = array( 'name', 'email', 'text', 'textarea', 'select', 'checkbox' );

	public $errors = array();
	public $fields = array();
	public $form_data = array();

	public function __construct() {
		add_action( 'wp', array( $this, 'listen' ) );
		add_action( 'wp_ajax_wpforms_submit', array( $this, 'ajax_submit' ) );
		add_action( 'wp_ajax_nopriv_wpforms_submit', array( $this, 'ajax_submit' ) );
	}

	public function listen() {
		// phpcs:ignore WordPress.Security.NonceVerification -- WPForms forms carry no nonce (token antispam instead)
		if ( empty( $_POST['wpforms']['id'] ) || wp_doing_ajax() ) {
			return;
		}
		$entry   = wp_unslash( $_POST['wpforms'] ); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
		$form_id = absint( $entry['id'] );
		$result  = $this->process( $entry );
		$front   = wpforms()->obj( 'frontend' );
		if ( true === $result['ok'] ) {
			$front->result['success'][ $form_id ] = $result['confirmation'];
		} else {
			$front->result['errors'][ $form_id ] = $result['errors'];
		}
	}

	public function ajax_submit() {
		// phpcs:ignore WordPress.Security.NonceVerification
		$entry  = isset( $_POST['wpforms'] ) ? wp_unslash( $_POST['wpforms'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$result = $this->process( (array) $entry );
		if ( true === $result['ok'] ) {
			wp_send_json_success( array( 'confirmation' => $result['confirmation'] ) );
		}
		$field_errors = $result['errors'];
		$header       = $field_errors['header'] ?? '';
		unset( $field_errors['header'] );
		wp_send_json_error(
			array(
				'errors' => array(
					'general' => array( 'header' => $header ),
					'field'   => $field_errors,
				),
			)
		);
	}

	/** @return array{ok:bool, errors?:array, confirmation?:string} */
	public function process( array $entry ) {
		$this->errors = array();
		$form_id      = absint( $entry['id'] ?? 0 );
		$form         = wpforms()->obj( 'form' )->get( $form_id );
		if ( ! $form || 'publish' !== $form->post_status ) {
			return array(
				'ok'     => false,
				'errors' => array( 'header' => 'Invalid form.' ),
			);
		}
		$form_data = apply_filters( 'wpforms_process_before_form_data', wpforms_decode( $form->post_content ), $entry );
		$settings  = $form_data['settings'] ?? array();

		/* antispam: honeypot + token */
		if ( ! empty( $entry['hp'] ) ) {
			return array(
				'ok'     => false,
				'errors' => array( 'header' => 'WPForms honeypot field triggered.' ),
			);
		}
		if ( ( ! empty( $settings['antispam'] ) || ! empty( $settings['antispam_v3'] ) ) && ( $entry['token'] ?? '' ) !== WPForms_Stub_Frontend::token( $form_id ) ) {
			return array(
				'ok'     => false,
				'errors' => array( 'header' => 'The form was unable to submit. Please contact the site administrator.' ),
			);
		}

		/* validate + format, field by field, firing the same hooks */
		$fields = array();
		foreach ( (array) $form_data['fields'] as $field ) {
			$id     = absint( $field['id'] );
			$type   = (string) $field['type'];
			$submit = $entry['fields'][ $id ] ?? '';
			do_action( "wpforms_process_validate_{$type}", $id, $submit, $form_data );
			if ( ! in_array( $type, self::KNOWN, true ) ) {
				continue;
			}

			$empty = is_array( $submit ) ? ! array_filter( $submit, 'strlen' ) : '' === trim( (string) $submit );
			if ( ! empty( $field['required'] ) && $empty ) {
				$this->errors[ $form_id ][ $id ] = 'This field is required.';
			} elseif ( 'email' === $type && ! $empty && ! is_email( (string) $submit ) ) {
				$this->errors[ $form_id ][ $id ] = 'Please enter a valid email address.';
			}
		}
		if ( ! empty( $this->errors[ $form_id ] ) ) {
			$errors           = $this->errors[ $form_id ];
			$errors['header'] = 'Form has not been submitted, please see the errors below.';
			return array(
				'ok'     => false,
				'errors' => $errors,
			);
		}
		foreach ( (array) $form_data['fields'] as $field ) {
			$id     = absint( $field['id'] );
			$type   = (string) $field['type'];
			$submit = $entry['fields'][ $id ] ?? '';
			$name   = sanitize_text_field( (string) ( $field['label'] ?? '' ) );
			do_action( "wpforms_process_format_{$type}", $id, $submit, $form_data );
			if ( ! in_array( $type, self::KNOWN, true ) ) {
				continue; // like WPForms: only registered field types produce a value
			}
			switch ( $type ) {
				case 'checkbox':
					$values = array_map( 'sanitize_text_field', (array) $submit );
					$fields[ $id ] = array(
						'name'      => $name,
						'value'     => implode( "\n", $values ),
						'value_raw' => implode( "\n", $values ),
						'id'        => $id,
						'type'      => $type,
					);
					break;
				case 'textarea':
					$fields[ $id ] = array(
						'name'  => $name,
						'value' => sanitize_textarea_field( (string) $submit ),
						'id'    => $id,
						'type'  => $type,
					);
					break;
				case 'email':
					$fields[ $id ] = array(
						'name'  => $name,
						'value' => sanitize_email( (string) $submit ),
						'id'    => $id,
						'type'  => $type,
					);
					break;
				default:
					$fields[ $id ] = array(
						'name'  => $name,
						'value' => sanitize_text_field( (string) $submit ),
						'id'    => $id,
						'type'  => $type,
					);
			}
		}

		$fields          = apply_filters( 'wpforms_process_filter', $fields, $entry, $form_data );
		$this->fields    = $fields;
		$this->form_data = $form_data;
		do_action( 'wpforms_process', $fields, $entry, $form_data );
		if ( ! empty( $this->errors[ $form_id ] ) ) {
			$errors           = $this->errors[ $form_id ];
			$errors['header'] = 'Form has not been submitted, please see the errors below.';
			return array(
				'ok'     => false,
				'errors' => $errors,
			);
		}

		$entry_id = 0; // Lite does not store entries
		do_action( 'wpforms_process_entry_save', $fields, $entry, $form_id, $form_data );
		$this->entry_email( $fields, $entry, $form_data, $entry_id, 'entry' );
		do_action( 'wpforms_process_complete', $fields, $entry, $form_data, $entry_id );
		do_action( "wpforms_process_complete_{$form_id}", $fields, $entry, $form_data, $entry_id );

		return array(
			'ok'           => true,
			'confirmation' => $this->confirmation( $form_data, $fields, $entry_id ),
		);
	}

	private function confirmation( array $form_data, array $fields, $entry_id ) {
		$form_id = absint( $form_data['id'] );
		$conf    = $form_data['settings']['confirmations'][1] ?? array();
		$message = (string) ( $conf['message'] ?? 'Thanks for contacting us! We will be in touch with you shortly.' );
		$message = wpforms()->obj( 'smart_tags' )->process( $message, $form_data, $fields, $entry_id, 'confirmation' );
		$message = apply_filters( 'wpforms_frontend_confirmation_message', wpautop( $message ), $form_data, $fields, $entry_id );
		$class   = array( 'wpforms-confirmation-container-full' );
		if ( ! empty( $conf['message_scroll'] ) ) {
			$class[] = 'wpforms-confirmation-scroll';
		}
		return sprintf( '<div class="%s" id="wpforms-confirmation-%d">%s</div>', esc_attr( implode( ' ', $class ) ), $form_id, wp_kses_post( $message ) );
	}

	public function entry_email( $fields, $entry, $form_data, $entry_id, $context = '' ) {
		if ( empty( $form_data['settings']['notification_enable'] ) ) {
			return;
		}
		if ( ! apply_filters( 'wpforms_entry_email', true, $fields, $entry, $form_data ) ) {
			return;
		}
		$fields = apply_filters( 'wpforms_entry_email_data', $fields, $entry, $form_data );
		$tags   = wpforms()->obj( 'smart_tags' );

		foreach ( (array) ( $form_data['settings']['notifications'] ?? array() ) as $notification ) {
			$to = array_filter( array_map( 'trim', explode( ',', $tags->process( (string) ( $notification['email'] ?? '' ), $form_data, $fields, $entry_id, 'notification' ) ) ), 'is_email' );
			if ( ! $to ) {
				continue;
			}
			$subject   = $tags->process( (string) ( $notification['subject'] ?? 'New Entry' ), $form_data, $fields, $entry_id, 'notification' );
			$from_name = $tags->process( (string) ( $notification['sender_name'] ?? '{site_name}' ), $form_data, $fields, $entry_id, 'notification' );
			$from_addr = $tags->process( (string) ( $notification['sender_address'] ?? '{admin_email}' ), $form_data, $fields, $entry_id, 'notification' );
			$reply_to  = $tags->process( (string) ( $notification['replyto'] ?? '' ), $form_data, $fields, $entry_id, 'notification' );
			$message   = (string) ( $notification['message'] ?? '{all_fields}' );
			$message   = str_replace( '{all_fields}', $this->all_fields_html( $fields, $form_data ), $message );
			$message   = $tags->process( $message, $form_data, $fields, $entry_id, 'notification' );

			$headers = array( 'Content-Type: text/html; charset=UTF-8', sprintf( 'From: %s <%s>', $from_name, is_email( $from_addr ) ? $from_addr : get_option( 'admin_email' ) ) );
			if ( is_email( $reply_to ) ) {
				$headers[] = 'Reply-To: ' . $reply_to;
			}
			wp_mail( $to, wp_specialchars_decode( $subject, ENT_QUOTES ), $message, $headers );
		}
	}

	/** {all_fields}: WPForms ≥ 1.8.5 walks $form_data['fields'] and looks each one up in $fields. */
	private function all_fields_html( array $fields, array $form_data ) {
		$rows    = '';
		$iterate = 'fields' === get_option( 'wpforms_stub_email_iterate', 'form_data' ) ? $fields : (array) $form_data['fields'];
		foreach ( $iterate as $field_id => $unused ) {
			if ( empty( $fields[ $field_id ] ) ) {
				continue;
			}
			$field = $fields[ $field_id ];
			if ( ! isset( $field['value'] ) || '' === (string) $field['value'] ) {
				continue;
			}
			$rows .= '<tr><td style="padding:8px 0;font-weight:bold">' . esc_html( (string) $field['name'] ) . '</td></tr>';
			$rows .= '<tr><td style="padding:0 0 16px">' . nl2br( esc_html( (string) $field['value'] ) ) . '</td></tr>';
		}
		return '<table class="wpforms-stub-all-fields">' . $rows . '</table>';
	}
}

wpforms();

/* ── minimal admin screens (so links to wpforms-overview / builder resolve) ── */
add_action(
	'admin_menu',
	static function () {
		add_menu_page( 'WPForms (stub)', 'WPForms (stub)', 'manage_options', 'wpforms-overview', 'wpforms_stub_overview', 'dashicons-feedback', 58 );
		add_submenu_page( 'options.php', 'Builder (stub)', 'Builder (stub)', 'manage_options', 'wpforms-builder', 'wpforms_stub_builder' );
	}
);

function wpforms_stub_overview() {
	echo '<div class="wrap"><h1>WPForms TEST STUB</h1><p>Local QA only.</p><table class="widefat"><thead><tr><th>ID</th><th>Title</th><th>Shortcode</th></tr></thead><tbody>';
	foreach ( (array) wpforms()->obj( 'form' )->get() as $form ) {
		printf(
			'<tr><td>%1$d</td><td><a href="%2$s">%3$s</a></td><td><code>[wpforms id="%1$d"]</code></td></tr>',
			absint( $form->ID ),
			esc_url( admin_url( 'admin.php?page=wpforms-builder&view=fields&form_id=' . $form->ID ) ),
			esc_html( $form->post_title )
		);
	}
	echo '</tbody></table></div>';
}

function wpforms_stub_builder() {
	$id   = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	$data = $id ? wpforms()->obj( 'form' )->get( $id, array( 'content_only' => true ) ) : array();
	echo '<div class="wrap"><h1>Builder (stub) — form ' . absint( $id ) . '</h1><pre style="white-space:pre-wrap">' . esc_html( (string) wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) . '</pre></div>';
}
