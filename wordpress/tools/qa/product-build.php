<?php
/**
 * QA (group "product"): builds the EN/ES product test pages with the
 * product sections in the same order/context as src/pages/[...lang]/product.astro:
 *   page-hero → section-intro (what it is) → differentiator → how-it-works
 *   → parameters → [benefits: no render.php yet] → faq → page-cta
 * Also a contact page pair (CTA target) and an "edge" page with empty /
 * repeated sections and the no-video placeholder.
 * Run: MATS_WP_PORT=8905 php /home/user/wpsite-cli.php wordpress/tools/qa/product-build.php
 * Prints JSON { role: { en: {id,url}, es: {id,url} } }.
 */

$layout = array(
	'product' => array( 'page-hero', 'section-intro', 'differentiator', 'how-it-works', 'parameters', 'benefits', 'faq', 'page-cta' ),
);
$slugs  = array(
	'product' => array( 'en' => 'product', 'es' => 'producto' ),
	'contact' => array( 'en' => 'contact', 'es' => 'contacto' ),
);

/* start over: remove earlier QA pages of this group */
foreach ( get_posts( array( 'post_type' => 'page', 'numberposts' => -1, 'post_status' => 'any', 'lang' => '', 'meta_key' => '_mats_qa', 'meta_value' => 'product' ) ) as $old ) {
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
	update_post_meta( $id, '_mats_qa', 'product' );
	if ( $role ) {
		update_post_meta( $id, '_mats_role', $role );
	}
	pll_set_post_language( $id, $lang );
	return (int) $id;
};

foreach ( array( 'product', 'contact' ) as $role ) {
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
update_option( 'mats_page_roles', $role_ids );

/* edge cases: no video (placeholder branch), sections repeated twice, empty sections */
$edge = array();
foreach ( array( 'en', 'es' ) as $lang ) {
	$content  = mats_seed_block( 'how-it-works', 'product', $lang, array( 'video' => array() ) ) . "\n\n";
	$content .= mats_seed_block( 'how-it-works', 'product', $lang ) . "\n\n";
	$content .= mats_seed_block( 'faq', 'product', $lang ) . "\n\n";
	$content .= mats_seed_block( 'faq', 'product', $lang ) . "\n\n";
	$content .= mats_seed_block( 'differentiator', 'product', $lang ) . "\n\n";
	$content .= mats_seed_block( 'differentiator', 'product', $lang ) . "\n\n";
	$content .= mats_seed_block( 'parameters', 'product', $lang ) . "\n\n";
	$content .= mats_seed_block( 'parameters', 'product', $lang ) . "\n\n";
	/* empty sections print nothing on the public site */
	$content .= mats_seed_block( 'differentiator', 'home', $lang ) . "\n\n";
	$content .= mats_seed_block( 'faq', 'home', $lang ) . "\n\n";
	$content .= mats_seed_block( 'parameters', 'home', $lang ) . "\n\n";
	$content .= mats_seed_block( 'how-it-works', 'home', $lang, array( 'video' => array() ) ) . "\n\n";
	$id = $make( 'Product QA edge (' . $lang . ')', 'product-qa-edge' . ( 'es' === $lang ? '-es' : '' ), $lang, $content );
	if ( $id ) {
		$edge[ $lang ] = $id;
	}
}
pll_save_post_translations( $edge );
foreach ( $edge as $lang => $id ) {
	$out['edge'][ $lang ] = array(
		'id'  => $id,
		'url' => get_permalink( $id ),
	);
}

flush_rewrite_rules( false );
echo wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), "\n";
