<?php
/**
 * QA helper for fix-editor2-pages.mjs: switches Polylang off or on
 * (second argument "off" | "on") and prints the resulting state.
 *
 *   MATS_WP_PORT=8985 php /home/user/wpsite-cli.php \
 *     /home/user/ICMAB-WEB/wordpress/tools/qa/fix-editor2-polylang.php off
 *
 * @package matsurfer-qa
 */

$mats_qa_file = 'polylang/polylang.php';
if ( 'off' === ( $argv[2] ?? '' ) ) {
	deactivate_plugins( $mats_qa_file, true );
} elseif ( 'on' === ( $argv[2] ?? '' ) ) {
	$mats_qa_res = activate_plugin( $mats_qa_file, '', false, true );
	if ( is_wp_error( $mats_qa_res ) ) {
		echo 'error: ' . $mats_qa_res->get_error_message() . "\n";
	}
}
echo is_plugin_active( $mats_qa_file ) ? 'polylang on' : 'polylang off';
