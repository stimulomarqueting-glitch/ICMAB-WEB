<?php
/* QA (group "team"): a page with each team section twice (ids stay unique,
   view.js handles both showcases independently). */
foreach ( get_posts( array( 'post_type' => 'page', 'numberposts' => -1, 'post_status' => 'any', 'lang' => '', 'meta_key' => '_mats_qa', 'meta_value' => 'team-multi' ) ) as $old ) {
	wp_delete_post( $old->ID, true );
}
$c  = mats_seed_block( 'team-showcase', 'team', 'en' ) . "\n\n" . mats_seed_block( 'team-journey', 'team', 'en' ) . "\n\n" . mats_seed_block( 'team-showcase', 'team', 'en' ) . "\n\n" . mats_seed_block( 'team-journey', 'team', 'en' );
$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Team multi', 'post_name' => 'team-multi', 'post_content' => wp_slash( $c ) ) );
update_post_meta( $id, '_mats_qa', 'team-multi' );
pll_set_post_language( $id, 'en' );
echo get_permalink( $id ), "\n";
