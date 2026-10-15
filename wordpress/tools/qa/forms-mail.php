<?php
/**
 * QA (module "forms"): prints (and optionally clears) the emails captured by
 * the test-only plugin wpforms-stub/qa-mail-capture.php.
 * Run: MATS_WP_PORT=8911 php /home/user/wpsite-cli.php wordpress/tools/qa/forms-mail.php [--clear]
 */

if ( in_array( '--clear', array_slice( $argv, 2 ), true ) ) {
	delete_option( 'wpforms_stub_mails' );
	echo "cleared\n";
	return;
}
$mails = get_option( 'wpforms_stub_mails', array() );
foreach ( (array) $mails as $i => $mail ) {
	echo "── mail #$i ──\n";
	echo 'To: ', implode( ', ', (array) $mail['to'] ), "\n";
	echo 'Subject: ', $mail['subject'], "\n";
	echo 'Headers: ', implode( ' | ', (array) $mail['headers'] ), "\n";
	$text = preg_replace( '#</tr>#', "\n", (string) $mail['message'] );
	echo trim( html_entity_decode( wp_strip_all_tags( $text ) ) ), "\n";
}
echo count( (array) $mails ), " mail(s)\n";
