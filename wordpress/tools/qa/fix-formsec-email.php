<?php
/**
 * QA (formsec) helper for fix-formsec-email.mjs — TEST ONLY.
 *
 *   MATS_QA_MODE=on|read|off MATS_WP_PORT=<port> php /home/user/wpsite-cli.php wordpress/tools/qa/fix-formsec-email.php
 *
 *   on    activates the QA mail capture (wpforms-stub/qa-mail-capture.php)
 *         and empties the captured mails
 *   read  prints the captured mails as JSON (subject + rows label/value)
 *   off   deactivates the mail capture and deletes the test leads
 *         (emails @formsec.example.org) and the captured mails
 *
 * @package matsurfer-qa
 */

defined( 'ABSPATH' ) || exit;

$mats_qa_mode = (string) getenv( 'MATS_QA_MODE' );
$mats_qa_plug = 'wpforms-stub/qa-mail-capture.php';

if ( 'on' === $mats_qa_mode ) {
	$result = activate_plugin( $mats_qa_plug );
	delete_option( 'wpforms_stub_mails' );
	echo is_wp_error( $result ) ? 'ERROR ' . $result->get_error_message() : 'mail capture on', "\n";
} elseif ( 'read' === $mats_qa_mode ) {
	$out = array();
	foreach ( (array) get_option( 'wpforms_stub_mails', array() ) as $mail ) {
		$rows = array();
		preg_match_all( '#<td[^>]*font-weight:bold[^>]*>(.*?)</td></tr><tr><td[^>]*>(.*?)</td>#s', (string) $mail['message'], $m, PREG_SET_ORDER );
		foreach ( $m as $row ) {
			$rows[] = array( html_entity_decode( wp_strip_all_tags( $row[1] ), ENT_QUOTES ), html_entity_decode( wp_strip_all_tags( str_replace( '<br />', "\n", $row[2] ) ), ENT_QUOTES ) );
		}
		$out[] = array(
			'subject' => (string) $mail['subject'],
			'rows'    => $rows,
		);
	}
	/* the Contactos inbox keeps the raw codes (only the email shows words) */
	$leads = array();
	foreach ( get_posts( array( 'post_type' => 'mats_lead', 'post_status' => 'any', 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ids' ) ) as $id ) {
		if ( false !== strpos( (string) get_post_meta( $id, '_mats_lead_email', true ), '@formsec.example.org' ) ) {
			$leads[] = array(
				'cta_source'  => (string) get_post_meta( $id, '_mats_lead_cta_source', true ),
				'application' => (string) get_post_meta( $id, '_mats_lead_application', true ),
				'language'    => (string) get_post_meta( $id, '_mats_lead_language', true ),
			);
		}
	}
	echo wp_json_encode( array( 'mails' => $out, 'leads' => $leads ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ), "\n";
} elseif ( 'off' === $mats_qa_mode ) {
	deactivate_plugins( $mats_qa_plug );
	delete_option( 'wpforms_stub_mails' );
	$deleted = 0;
	$ids     = get_posts(
		array(
			'post_type'      => 'mats_lead',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'key'     => '_mats_lead_email',
					'value'   => '@formsec.example.org',
					'compare' => 'LIKE',
				),
			),
		)
	);
	foreach ( $ids as $id ) {
		$deleted += wp_delete_post( (int) $id, true ) ? 1 : 0;
	}
	echo 'mail capture off, test leads deleted: ', $deleted, "\n";
} else {
	echo "MATS_QA_MODE must be on, read or off\n";
}
