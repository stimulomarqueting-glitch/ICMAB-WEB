<?php
/* QA (group "team"): reference page with the verified page-hero block carrying
   the team copy, to compare the showcase's no-profile fallback against. */
foreach ( get_posts( array( 'post_type' => 'page', 'numberposts' => -1, 'post_status' => 'any', 'lang' => '', 'meta_key' => '_mats_qa', 'meta_value' => 'team-ref' ) ) as $old ) {
	wp_delete_post( $old->ID, true );
}
$ids = array();
foreach ( array( 'en', 'es' ) as $lang ) {
	$c   = mats_content_data()[ $lang ]['teamPage'];
	$blk = serialize_block( array( 'blockName' => 'matsurfer/page-hero', 'attrs' => array( 'kicker' => $c['kicker'], 'title' => $c['title'], 'lead' => $c['lead'] ), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
	$blk .= "\n\n" . mats_seed_block( 'team-journey', 'team', $lang );
	$id  = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Team ref ' . $lang, 'post_name' => 'team-ref-' . $lang, 'post_content' => wp_slash( $blk ) ) );
	update_post_meta( $id, '_mats_qa', 'team-ref' );
	pll_set_post_language( $id, $lang );
	$ids[ $lang ] = $id;
	echo get_permalink( $id ), "\n";
}
pll_save_post_translations( $ids );
