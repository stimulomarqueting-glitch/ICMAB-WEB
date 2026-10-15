<?php
/**
 * QA (group "hero"): a throw-away page with two hero sections (second one
 * without vapour) to check view.js handles repeated sections. Prints the URL.
 * Run with "delete" as 2nd arg to remove it.
 */
$existing = get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'meta_key' => '_hero_qa', 'meta_value' => 'multi', 'numberposts' => 1, 'fields' => 'ids', 'lang' => '' ) );
if ( ( $argv[2] ?? '' ) === 'delete' ) {
	foreach ( $existing as $id ) {
		wp_delete_post( $id, true );
	}
	echo "deleted\n";
	return;
}
$content = mats_seed_block( 'hero-field', 'home', 'en' ) . "\n\n" . mats_seed_block( 'hero-field', 'home', 'en', array( 'vapour' => false ) );
$args    = array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'QA hero multi', 'post_name' => 'qa-hero-multi', 'post_content' => wp_slash( $content ) );
if ( $existing ) {
	$args['ID'] = $existing[0];
	$id         = wp_update_post( $args );
} else {
	$id = wp_insert_post( $args );
	update_post_meta( $id, '_hero_qa', 'multi' );
}
pll_set_post_language( $id, 'en' );
echo get_permalink( $id ), "\n";
