<?php
/* QA (group "team"): Person JSON-LD collected while the team page renders
   (once, even when the content is rendered twice), per language. */
foreach ( array( 'en', 'es' ) as $lang ) {
	$GLOBALS['mats_jsonld']      = array();
	$GLOBALS['mats_team_jsonld'] = false;
	$id = mats_page_id( 'team', $lang );
	mats_with_lang( $lang, static function () use ( $id ) {
		$c = (string) get_post_field( 'post_content', $id );
		do_blocks( $c );
		do_blocks( $c ); // second render must not duplicate
	} );
	echo $lang, ' (', count( mats_jsonld_nodes() ), " nodes)\n", wp_json_encode( mats_jsonld_nodes(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), "\n";
}
