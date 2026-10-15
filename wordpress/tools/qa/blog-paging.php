<?php
/**
 * QA (module "blog"): pagination and empty states.
 *   MATS_WP_PORT=8914 php /home/user/wpsite-cli.php wordpress/tools/qa/blog-paging.php add      (+20 short EN articles)
 *   MATS_WP_PORT=8914 php /home/user/wpsite-cli.php wordpress/tools/qa/blog-paging.php remove
 *   MATS_WP_PORT=8914 php /home/user/wpsite-cli.php wordpress/tools/qa/blog-paging.php hide-es  (ES articles → draft: empty Spanish news)
 *   MATS_WP_PORT=8914 php /home/user/wpsite-cli.php wordpress/tools/qa/blog-paging.php show-es
 */

defined( 'ABSPATH' ) || exit;

$mode = $argv[2] ?? 'add';

if ( 'add' === $mode ) {
	for ( $i = 1; $i <= 20; $i++ ) {
		$id = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => sprintf( 'Paging test article %02d', $i ),
				'post_content' => "<!-- wp:paragraph -->\n<p>Short test article number {$i} for the pagination check.</p>\n<!-- /wp:paragraph -->",
				'post_date'    => gmdate( 'Y-m-d H:i:s', strtotime( '2024-01-01 +' . $i . ' days' ) ),
			)
		);
		pll_set_post_language( $id, 'en' );
		update_post_meta( $id, '_mats_qa_paging', 1 );
	}
	echo "added 20\n";
} elseif ( 'remove' === $mode ) {
	foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'lang' => '', 'meta_key' => '_mats_qa_paging' ) ) as $id ) {
		wp_delete_post( $id, true );
	}
	echo "removed\n";
} elseif ( 'hide-es' === $mode || 'show-es' === $mode ) {
	$ids = get_posts( array( 'post_type' => 'post', 'post_status' => array( 'publish', 'draft' ), 'numberposts' => -1, 'fields' => 'ids', 'lang' => 'es' ) );
	foreach ( $ids as $id ) {
		wp_update_post( array( 'ID' => $id, 'post_status' => 'hide-es' === $mode ? 'draft' : 'publish' ) );
	}
	echo $mode, ' ', count( $ids ), "\n";
}
