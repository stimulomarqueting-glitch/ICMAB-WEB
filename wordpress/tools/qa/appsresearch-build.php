<?php
/**
 * QA (group "appsresearch"): builds the EN/ES applications and research
 * test pages with their sections in the same order/context as
 *   src/pages/[...lang]/applications.astro:
 *     page-hero → applications-list → broader → page-cta
 *   src/pages/[...lang]/research.astro:
 *     page-hero → research-experiences → research-publications
 *     → research-validation → invite → page-cta
 * plus a contact page pair (CTA target) and an "edge" page (ES+EN) with
 * repeated sections and empty attributes.
 * Run: MATS_WP_PORT=8906 php /home/user/wpsite-cli.php wordpress/tools/qa/appsresearch-build.php
 * Prints JSON { role: { en: {id,url}, es: {id,url} } }.
 */

$layout = array(
	'applications' => array( 'page-hero', 'applications-list', 'broader', 'page-cta' ),
	'research'     => array( 'page-hero', 'research-experiences', 'research-publications', 'research-validation', 'invite', 'page-cta' ),
);
$slugs  = array(
	'applications' => array( 'en' => 'applications', 'es' => 'aplicaciones' ),
	'research'     => array( 'en' => 'research', 'es' => 'investigacion' ),
	'contact'      => array( 'en' => 'contact', 'es' => 'contacto' ),
	'edge'         => array( 'en' => 'appsresearch-edge', 'es' => 'appsresearch-edge-es' ),
);

/* start over: remove earlier QA pages of this group */
foreach ( get_posts( array( 'post_type' => 'page', 'numberposts' => -1, 'post_status' => 'any', 'lang' => '', 'meta_key' => '_mats_qa', 'meta_value' => 'appsresearch' ) ) as $old ) {
	wp_delete_post( $old->ID, true );
}

$registry = WP_Block_Type_Registry::get_instance();
$out      = array();
$role_ids = get_option( 'mats_page_roles', array() );
$role_ids = is_array( $role_ids ) ? $role_ids : array();

$make = static function ( string $title, string $slug, string $lang, string $content, string $role = '' ) {
	$id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => wp_slash( $content ),
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		fwrite( STDERR, $id->get_error_message() . "\n" );
		return 0;
	}
	update_post_meta( $id, '_mats_qa', 'appsresearch' );
	if ( $role ) {
		update_post_meta( $id, '_mats_role', $role );
	}
	pll_set_post_language( $id, $lang );
	return (int) $id;
};

foreach ( array( 'applications', 'research', 'contact' ) as $role ) {
	$ids = array();
	foreach ( array( 'en', 'es' ) as $lang ) {
		$content = '';
		foreach ( $layout[ $role ] ?? array() as $block ) {
			/* neighbours only when they already have a render.php */
			if ( ! $registry->get_registered( 'matsurfer/' . $block ) || ! is_readable( MATS_DIR . '/blocks/' . $block . '/render.php' ) ) {
				continue;
			}
			$content .= mats_seed_block( $block, $role, $lang ) . "\n\n";
		}
		if ( '' === $content ) {
			$content = '<!-- wp:paragraph --><p>QA placeholder.</p><!-- /wp:paragraph -->';
		}
		$id = $make( ucfirst( $role ) . ' (' . $lang . ')', $slugs[ $role ][ $lang ], $lang, $content, $role );
		if ( $id ) {
			$ids[ $lang ] = $id;
		}
	}
	pll_save_post_translations( $ids );
	$role_ids[ $role ] = $ids['en'];
	foreach ( $ids as $lang => $id ) {
		$out[ $role ][ $lang ] = array(
			'id'  => $id,
			'url' => get_permalink( $id ),
		);
	}
}

/* edge page: every section twice (unique ids), then empty attributes */
$ids = array();
foreach ( array( 'en', 'es' ) as $lang ) {
	$content = '';
	foreach ( array( 'applications' => array( 'applications-list', 'broader' ), 'research' => array( 'research-experiences', 'research-publications', 'research-validation', 'invite' ) ) as $role => $blocks ) {
		foreach ( array( 1, 2 ) as $n ) {
			foreach ( $blocks as $block ) {
				$content .= mats_seed_block( $block, $role, $lang ) . "\n\n";
			}
		}
	}
	foreach ( array( 'broader', 'research-validation', 'invite' ) as $block ) {
		$content .= mats_seed_block( $block, 'none', $lang ) . "\n\n";
	}
	$id = $make( 'AppsResearch edge (' . $lang . ')', $slugs['edge'][ $lang ], $lang, $content );
	if ( $id ) {
		$ids[ $lang ] = $id;
	}
}
pll_save_post_translations( $ids );
foreach ( $ids as $lang => $id ) {
	$out['edge'][ $lang ] = array(
		'id'  => $id,
		'url' => get_permalink( $id ),
	);
}

update_option( 'mats_page_roles', $role_ids );
flush_rewrite_rules( false );
echo wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), "\n";
