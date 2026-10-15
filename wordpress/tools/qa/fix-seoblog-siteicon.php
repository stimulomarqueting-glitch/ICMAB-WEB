<?php
/**
 * QA (fix bucket "seoblog"): sets (MATS_QA_ICON=on) or clears (=off) a
 * WordPress Site Icon made from assets/brand/icon-512.png, to check that the
 * theme's own favicon fallback steps aside when a Site Icon exists.
 *   MATS_QA_ICON=on MATS_WP_PORT=8982 php /home/user/wpsite-cli.php wordpress/tools/qa/fix-seoblog-siteicon.php
 */

defined( 'ABSPATH' ) || exit;

if ( 'off' === getenv( 'MATS_QA_ICON' ) ) {
	update_option( 'site_icon', 0 );
	echo "site_icon cleared\n";
	return;
}
require_once ABSPATH . 'wp-admin/includes/image.php';
$mats_qa_upload = wp_upload_bits( 'qa-site-icon.png', null, (string) file_get_contents( get_template_directory() . '/assets/brand/icon-512.png' ) );
$mats_qa_id     = wp_insert_attachment(
	array(
		'post_mime_type' => 'image/png',
		'post_title'     => 'QA site icon',
		'post_status'    => 'inherit',
	),
	$mats_qa_upload['file']
);
wp_update_attachment_metadata( $mats_qa_id, wp_generate_attachment_metadata( $mats_qa_id, $mats_qa_upload['file'] ) );
update_option( 'site_icon', $mats_qa_id );
echo 'site_icon = ', (int) get_option( 'site_icon' ), ' ', get_site_icon_url( 32 ), "\n";
