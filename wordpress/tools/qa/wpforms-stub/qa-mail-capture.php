<?php
/**
 * Plugin Name: MatSurfer QA — mail capture (TEST ONLY — never ship)
 * Description: Captures every wp_mail() call into the option "wpforms_stub_mails" instead of sending it. Local QA only.
 * Version: 0.1.0
 *
 * A second plugin in the wpforms-stub folder so it can be activated in ONE
 * QA instance database only (mu-plugins would affect every instance).
 *
 * @package matsurfer-qa
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'pre_wp_mail',
	static function ( $short, $atts ) {
		$mails   = get_option( 'wpforms_stub_mails', array() );
		$mails   = is_array( $mails ) ? $mails : array();
		$mails[] = array(
			'time'    => time(),
			'to'      => $atts['to'] ?? '',
			'subject' => $atts['subject'] ?? '',
			'message' => $atts['message'] ?? '',
			'headers' => $atts['headers'] ?? '',
		);
		update_option( 'wpforms_stub_mails', array_slice( $mails, -20 ), false );
		return true; // pretend it was sent
	},
	10,
	2
);
