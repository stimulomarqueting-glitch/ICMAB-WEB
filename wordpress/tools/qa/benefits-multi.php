<?php
/**
 * QA (group "benefits"): a page with each section twice (view.js must wire
 * every audiences row on its own; ids stay unique).
 * Run: MATS_WP_PORT=8904 php /home/user/wpsite-cli.php wordpress/tools/qa/benefits-multi.php
 */
$content  = implode(
	"\n\n",
	array(
		mats_seed_block( 'audiences', 'home', 'en' ),
		mats_seed_block( 'benefits', 'home', 'en' ),
		mats_seed_block( 'audiences', 'home', 'en' ),
		mats_seed_block( 'benefits', 'product', 'en' ),
	)
);
$existing = get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'meta_key' => '_benefits_qa', 'meta_value' => 'multi', 'numberposts' => 1, 'fields' => 'ids', 'lang' => '' ) );
$args     = array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Benefits multi', 'post_name' => 'benefits-multi', 'post_content' => wp_slash( $content ) );
if ( $existing ) {
	$args['ID'] = $existing[0];
	$id         = wp_update_post( $args );
} else {
	$id = wp_insert_post( $args );
	update_post_meta( $id, '_benefits_qa', 'multi' );
}
pll_set_post_language( $id, 'en' );
echo get_permalink( $id ), "\n";
