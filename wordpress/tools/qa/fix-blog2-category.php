<?php
/**
 * QA (fix bucket "blog2"): empties / refills a news category in its language
 * to test the empty-category rules (noindex, sitemap, hreflang).
 *   MATS_QA_CAT="research-news:off" MATS_WP_PORT=8986 php /home/user/wpsite-cli.php wordpress/tools/qa/fix-blog2-category.php
 * Values: "<category slug>:off" (its published articles → draft, marked with
 * _mats_qa_blog2_off), "<slug>:on" (back to published), several separated by
 * commas. Run blog-setup.php first.
 */

defined( 'ABSPATH' ) || exit;

foreach ( array_filter( array_map( 'trim', explode( ',', (string) getenv( 'MATS_QA_CAT' ) ) ) ) as $mats_qa_rule ) {
	list( $mats_qa_slug, $mats_qa_state ) = array_pad( explode( ':', $mats_qa_rule ), 2, '' );
	$mats_qa_term = get_term_by( 'slug', $mats_qa_slug, 'category' );
	if ( ! $mats_qa_term ) {
		printf( "%s: no such category\n", $mats_qa_slug );
		continue;
	}
	$mats_qa_args = array(
		'post_type'   => 'post',
		'numberposts' => -1,
		'fields'      => 'ids',
		'lang'        => '',
		'cat'         => (int) $mats_qa_term->term_id,
	);
	if ( 'off' === $mats_qa_state ) {
		$mats_qa_ids = get_posts( $mats_qa_args + array( 'post_status' => 'publish' ) );
		foreach ( $mats_qa_ids as $mats_qa_id ) {
			update_post_meta( $mats_qa_id, '_mats_qa_blog2_off', 1 );
			wp_update_post( array( 'ID' => $mats_qa_id, 'post_status' => 'draft' ) );
		}
	} else {
		$mats_qa_ids = get_posts( $mats_qa_args + array( 'post_status' => 'draft', 'meta_key' => '_mats_qa_blog2_off' ) );
		foreach ( $mats_qa_ids as $mats_qa_id ) {
			wp_update_post( array( 'ID' => $mats_qa_id, 'post_status' => 'publish' ) );
			delete_post_meta( $mats_qa_id, '_mats_qa_blog2_off' );
		}
	}
	printf( "%s: %d articles %s\n", $mats_qa_slug, count( $mats_qa_ids ), 'off' === $mats_qa_state ? 'unpublished' : 'published again' );
}
