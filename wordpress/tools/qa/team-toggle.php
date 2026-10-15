<?php
/* argv[2] = draft|publish : toggles every team record (QA of the no-profile fallback) */
$status = $GLOBALS['argv'][2] ?? 'publish';
foreach ( get_posts( array( 'post_type' => 'mats_team', 'post_status' => 'any', 'numberposts' => -1 ) ) as $p ) {
	wp_update_post( array( 'ID' => $p->ID, 'post_status' => $status ) );
}
echo count( mats_get_team( 'en' ) ), " published\n";
