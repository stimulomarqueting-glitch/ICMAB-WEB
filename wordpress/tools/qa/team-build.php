<?php
/**
 * QA (group "team"): builds the EN/ES team test pages (team-showcase +
 * team-journey + the closing CTA band, as in src/pages/[...lang]/team.astro)
 * plus contact/research placeholders so the links resolve to real pages.
 * Run: MATS_WP_PORT=8907 php /home/user/wpsite-cli.php wordpress/tools/qa/team-build.php
 * Prints JSON { role: { en: {id,url}, es: {id,url} } }.
 */

$roles_layout = array(
	'team' => array( 'team-showcase', 'team-journey', 'page-cta' ),
);
$slugs = array(
	'team'     => array( 'en' => 'team', 'es' => 'equipo' ),
	'research' => array( 'en' => 'research', 'es' => 'investigacion' ),
	'contact'  => array( 'en' => 'contact', 'es' => 'contacto' ),
);

/* start over: remove earlier QA pages of this group */
foreach ( get_posts( array( 'post_type' => 'page', 'numberposts' => -1, 'post_status' => 'any', 'lang' => '', 'meta_key' => '_mats_qa', 'meta_value' => 'team' ) ) as $old ) {
	wp_delete_post( $old->ID, true );
}

$out      = array();
$role_ids = get_option( 'mats_page_roles', array() );
$role_ids = is_array( $role_ids ) ? $role_ids : array();

foreach ( array_keys( $slugs ) as $role ) {
	$ids = array();
	foreach ( array( 'en', 'es' ) as $lang ) {
		$content = '';
		foreach ( $roles_layout[ $role ] ?? array() as $block ) {
			$content .= mats_seed_block( $block, $role, $lang ) . "\n\n";
		}
		if ( '' === $content ) {
			$content = '<!-- wp:paragraph --><p>QA placeholder.</p><!-- /wp:paragraph -->';
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => ucfirst( $role ) . ' (' . $lang . ')',
				'post_name'    => $slugs[ $role ][ $lang ],
				'post_content' => wp_slash( $content ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			fwrite( STDERR, $id->get_error_message() . "\n" );
			continue;
		}
		update_post_meta( $id, '_mats_qa', 'team' );
		update_post_meta( $id, '_mats_role', $role );
		pll_set_post_language( $id, $lang );
		$ids[ $lang ] = $id;
	}
	pll_save_post_translations( $ids );
	$role_ids[ $role ] = $ids['en'];
	foreach ( $ids as $lang => $id ) {
		$out[ $role ][ $lang ] = array( 'id' => $id, 'url' => get_permalink( $id ) );
	}
}
update_option( 'mats_page_roles', $role_ids );
flush_rewrite_rules( false );
echo wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), "\n";
