<?php
/**
 * QA (fix bucket "seoblog"): switches the news of a language off/on to test
 * the empty-News-page rules (noindex, sitemap, hreflang).
 *   MATS_QA_NEWS="es:off" MATS_WP_PORT=8982 php /home/user/wpsite-cli.php wordpress/tools/qa/fix-seoblog-state.php
 * Values: "<lang>:off" (published articles of that language → draft, marked
 * with _mats_qa_seoblog_off), "<lang>:on" (back to published), several
 * separated by commas ("en:off,es:off"). Run blog-setup.php first.
 */

defined( 'ABSPATH' ) || exit;

foreach ( array_filter( array_map( 'trim', explode( ',', (string) getenv( 'MATS_QA_NEWS' ) ) ) ) as $mats_qa_rule ) {
	list( $mats_qa_lang, $mats_qa_state ) = array_pad( explode( ':', $mats_qa_rule ), 2, '' );
	if ( 'off' === $mats_qa_state ) {
		$mats_qa_ids = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'lang' => $mats_qa_lang ) );
		foreach ( $mats_qa_ids as $mats_qa_id ) {
			update_post_meta( $mats_qa_id, '_mats_qa_seoblog_off', 1 );
			wp_update_post( array( 'ID' => $mats_qa_id, 'post_status' => 'draft' ) );
		}
	} else {
		$mats_qa_ids = get_posts( array( 'post_type' => 'post', 'post_status' => 'draft', 'numberposts' => -1, 'fields' => 'ids', 'lang' => $mats_qa_lang, 'meta_key' => '_mats_qa_seoblog_off' ) );
		foreach ( $mats_qa_ids as $mats_qa_id ) {
			wp_update_post( array( 'ID' => $mats_qa_id, 'post_status' => 'publish' ) );
			delete_post_meta( $mats_qa_id, '_mats_qa_seoblog_off' );
		}
	}
	printf( "%s: %d articles %s\n", $mats_qa_lang, count( $mats_qa_ids ), 'off' === $mats_qa_state ? 'unpublished' : 'published again' );
}
