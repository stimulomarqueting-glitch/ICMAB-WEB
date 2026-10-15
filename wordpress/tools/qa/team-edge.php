<?php
/**
 * QA (group "team"): edge states of the showcase. argv[2]:
 *   on  → Daniel Herrera loses portrait, cut-out, bio and LinkedIn and gets a
 *         website; Núria loses her cut-out (the showcase must open on the
 *         first profile with a cut-out = Arántzazu)
 *   off → restores the originals (backup kept in an option)
 */
$mode = $GLOBALS['argv'][2] ?? 'off';
$find = static function ( string $name ): int {
	foreach ( get_posts( array( 'post_type' => 'mats_team', 'post_status' => 'any', 'numberposts' => -1 ) ) as $p ) {
		if ( $p->post_title === $name ) {
			return $p->ID;
		}
	}
	return 0;
};
$dan = $find( 'Daniel Herrera' );
$nur = $find( 'Núria Aliaga-Alcalde' );
$keys = array( '_mats_showcase_image', '_thumbnail_id', '_mats_bio_en', '_mats_bio_es', '_mats_linkedin', '_mats_website' );
if ( 'on' === $mode ) {
	$backup = array();
	foreach ( array( $dan, $nur ) as $id ) {
		foreach ( $keys as $k ) {
			$backup[ $id ][ $k ] = get_post_meta( $id, $k, true );
		}
	}
	update_option( 'mats_team_qa_backup', $backup, false );
	foreach ( array( '_mats_showcase_image', '_thumbnail_id', '_mats_bio_en', '_mats_bio_es', '_mats_linkedin' ) as $k ) {
		delete_post_meta( $dan, $k );
	}
	update_post_meta( $dan, '_mats_website', 'https://icmab.es/' );
	delete_post_meta( $nur, '_mats_showcase_image' );
} else {
	foreach ( (array) get_option( 'mats_team_qa_backup', array() ) as $id => $meta ) {
		foreach ( $meta as $k => $v ) {
			if ( '' === $v ) {
				delete_post_meta( $id, $k );
			} else {
				update_post_meta( $id, $k, $v );
			}
		}
	}
	delete_option( 'mats_team_qa_backup' );
}
foreach ( mats_get_team( 'en' ) as $m ) {
	echo $m['name'], ' | photo:', $m['image'] ? 'y' : 'n', ' cutout:', $m['showcaseImage'] ? 'y' : 'n', ' bio:', $m['bio'] ? 'y' : 'n', ' li:', $m['linkedin'] ? 'y' : 'n', ' web:', $m['website'], "\n";
}
