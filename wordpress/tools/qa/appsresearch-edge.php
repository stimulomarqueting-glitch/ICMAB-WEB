<?php
/**
 * QA (group "appsresearch"): JSON-LD collection of the research page and
 * the empty states of the data-driven sections (public + editor preview).
 * Run: MATS_WP_PORT=8906 php /home/user/wpsite-cli.php wordpress/tools/qa/appsresearch-edge.php <researchPageId>
 */
$research = (int) ( $argv[2] ?? 0 );

/* 1. JSON-LD nodes collected while the research page renders (public) */
$GLOBALS['mats_jsonld'] = array();
$html                   = apply_filters( 'the_content', get_post( $research )->post_content );
echo "JSON-LD nodes:\n", wp_json_encode( mats_jsonld_nodes(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), "\n";
/* rendering the page twice in one request must not duplicate the nodes */
apply_filters( 'the_content', get_post( $research )->post_content );
echo 'after a second render: ', count( mats_jsonld_nodes() ), " nodes\n";

/* 2. empty lists: hide every record for a moment */
$hidden = array();
foreach ( array( 'mats_application', 'mats_expert', 'mats_publication' ) as $type ) {
	foreach ( mats_cpt_posts( $type ) as $p ) {
		$hidden[] = $p->ID;
		wp_update_post( array( 'ID' => $p->ID, 'post_status' => 'draft' ) );
	}
}
$blocks = array( 'applications-list' => 'applications', 'research-experiences' => 'research', 'research-publications' => 'research' );
foreach ( array( false, true ) as $preview ) {
	$GLOBALS['mats_preview'] = $preview;
	foreach ( $blocks as $block => $role ) {
		$out = trim( do_blocks( mats_seed_block( $block, $role, 'en' ) ) );
		echo ( $preview ? 'editor ' : 'public ' ), $block, ': ', '' === $out ? '(nothing)' : $out, "\n";
	}
}
foreach ( $hidden as $id ) {
	wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
}

/* 3. empty attributes */
foreach ( array( false, true ) as $preview ) {
	$GLOBALS['mats_preview'] = $preview;
	foreach ( array( 'broader', 'research-validation', 'invite' ) as $block ) {
		$out = trim( mats_render_section( 'matsurfer/' . $block, array() ) );
		echo ( $preview ? 'editor ' : 'public ' ), $block, ' (empty): ', '' === $out ? '(nothing)' : $out, "\n";
	}
}
/* 4. validation with an empty paragraph in the middle keeps the editing paths */
$GLOBALS['mats_preview'] = true;
echo mats_render_section( 'matsurfer/research-validation', array( 'title' => 'T', 'paragraphs' => array( 'one', '', 'three' ) ) ), "\n";
$GLOBALS['mats_preview'] = false;
echo 'apps after restore: ', count( mats_get_applications() ), ', experts: ', count( mats_get_experts() ), ', pubs: ', count( mats_get_publications() ), "\n";
