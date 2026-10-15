<?php
/**
 * QA helper for fix-editor2-autosave.mjs: lists (default) or deletes
 * (second argument "delete") the autosaves of a page, as JSON.
 *
 *   MATS_WP_PORT=8985 php /home/user/wpsite-cli.php \
 *     /home/user/ICMAB-WEB/wordpress/tools/qa/fix-editor2-autosave.php <page id> [delete]
 *
 * @package matsurfer-qa
 */

$mats_qa_page = (int) ( $argv[2] ?? 0 );
$mats_qa_del  = 'delete' === ( $argv[3] ?? '' );
$mats_qa_out  = array();
foreach ( wp_get_post_revisions( $mats_qa_page, array( 'check_enabled' => false ) ) as $mats_qa_rev ) {
	if ( ! wp_is_post_autosave( $mats_qa_rev ) ) {
		continue;
	}
	if ( $mats_qa_del ) {
		wp_delete_post_revision( $mats_qa_rev->ID );
		continue;
	}
	$mats_qa_out[] = array(
		'id'      => $mats_qa_rev->ID,
		'author'  => get_the_author_meta( 'user_login', (int) $mats_qa_rev->post_author ),
		'title'   => $mats_qa_rev->post_title,
		'length'  => strlen( $mats_qa_rev->post_content ),
		'blocks'  => array_values( array_filter( array_map( static fn( $b ) => (string) $b['blockName'], parse_blocks( $mats_qa_rev->post_content ) ) ) ),
		'content' => $mats_qa_rev->post_content,
	);
}
echo wp_json_encode( $mats_qa_out );
