<?php
/**
 * QA (formsec) helper for fix-formsec-lostpassword.mjs — TEST ONLY.
 *
 *   MATS_QA_MODE=<mode> MATS_WP_PORT=<port> php /home/user/wpsite-cli.php wordpress/tools/qa/fix-formsec-lostpassword.php
 *
 *   setup      creates the editor "formsec-ed" (formsec-ed@example.test,
 *              password formsec-pass-123), clears the reset key, the
 *              "mail failed" flag and the captured mails
 *   mail-on    activates the QA mail capture (every wp_mail() succeeds)
 *   mail-off   deactivates it (wp_mail() fails here: PHP has no mail server)
 *   state      JSON: reset key present?, "mail failed" flag, captured mails
 *   succeeded  fires wp_mail_succeeded (as a sent e-mail does) and prints
 *              the flag afterwards
 *   cleanup    deletes the user, the flag and the captured mails, mail off
 *
 * @package matsurfer-qa
 */

defined( 'ABSPATH' ) || exit;

$mats_qa_mode = (string) getenv( 'MATS_QA_MODE' );
$mats_qa_plug = 'wpforms-stub/qa-mail-capture.php';
$mats_qa_user = get_user_by( 'login', 'formsec-ed' );

switch ( $mats_qa_mode ) {
	case 'setup':
		if ( ! $mats_qa_user ) {
			$id = wp_insert_user(
				array(
					'user_login' => 'formsec-ed',
					'user_email' => 'formsec-ed@example.test',
					'user_pass'  => 'formsec-pass-123',
					'role'       => 'editor',
				)
			);
			$mats_qa_user = is_wp_error( $id ) ? null : get_user_by( 'id', $id );
		}
		if ( $mats_qa_user ) {
			wp_update_user(
				array(
					'ID'                  => $mats_qa_user->ID,
					'user_activation_key' => '',
				)
			);
		}
		delete_option( 'mats_sec_mail_failed' );
		delete_option( 'wpforms_stub_mails' );
		echo $mats_qa_user ? 'user formsec-ed ready' : 'ERROR: no user', "\n";
		break;

	case 'mail-on':
		$result = activate_plugin( $mats_qa_plug );
		echo is_wp_error( $result ) ? 'ERROR ' . $result->get_error_message() : 'mail capture on', "\n";
		break;

	case 'mail-off':
		deactivate_plugins( $mats_qa_plug );
		echo "mail capture off\n";
		break;

	case 'state':
		clean_user_cache( $mats_qa_user ? $mats_qa_user->ID : 0 );
		$user  = get_user_by( 'login', 'formsec-ed' );
		$mails = array();
		foreach ( (array) get_option( 'wpforms_stub_mails', array() ) as $mail ) {
			$mails[] = array(
				'to'       => is_array( $mail['to'] ) ? implode( ',', $mail['to'] ) : (string) $mail['to'],
				'subject'  => (string) $mail['subject'],
				'resetUrl' => false !== strpos( (string) $mail['message'], 'action=rp' ),
			);
		}
		echo wp_json_encode(
			array(
				'resetKey'   => $user && '' !== (string) $user->user_activation_key,
				'mailFailed' => (int) get_option( 'mats_sec_mail_failed', 0 ),
				'mails'      => $mails,
			)
		), "\n";
		break;

	case 'succeeded':
		do_action( 'wp_mail_succeeded', array() );
		echo wp_json_encode( array( 'mailFailed' => (int) get_option( 'mats_sec_mail_failed', 0 ) ) ), "\n";
		break;

	case 'cleanup':
		require_once ABSPATH . 'wp-admin/includes/user.php';
		if ( $mats_qa_user ) {
			wp_delete_user( $mats_qa_user->ID );
		}
		delete_option( 'mats_sec_mail_failed' );
		delete_option( 'wpforms_stub_mails' );
		deactivate_plugins( $mats_qa_plug );
		echo "cleaned up\n";
		break;

	default:
		echo "unknown MATS_QA_MODE\n";
}
