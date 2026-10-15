<?php
/**
 * QA (group "benefits"): builds the test pages for the benefits and
 * audiences sections, EN + ES linked as translations, in the order of
 * src/pages/[...lang]/index.astro and product.astro. Neighbouring sections
 * are included only when they already have a render.php. Home is set as
 * the front page; stub pages cover every other role the links point to.
 * Run: MATS_WP_PORT=8904 php /home/user/wpsite-cli.php wordpress/tools/qa/benefits-setup.php
 */
function benefits_qa_page( string $key, string $title, string $slug, string $content, string $lang, string $role = '' ): int {
	$existing = get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'meta_key' => '_benefits_qa', 'meta_value' => $key, 'numberposts' => 1, 'fields' => 'ids', 'lang' => '' ) );
	$args     = array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_content' => wp_slash( $content ),
	);
	if ( $existing ) {
		$args['ID'] = $existing[0];
		$id         = wp_update_post( $args );
	} else {
		$id = wp_insert_post( $args );
		update_post_meta( $id, '_benefits_qa', $key );
	}
	pll_set_post_language( $id, $lang );
	if ( $role ) {
		update_post_meta( $id, '_mats_role', $role );
	}
	return (int) $id;
}

function benefits_qa_content( string $page, array $order, string $lang ): string {
	$blocks = array();
	foreach ( $order as $block ) {
		if ( is_readable( MATS_DIR . '/blocks/' . $block . '/render.php' ) ) {
			$blocks[] = mats_seed_block( $block, $page, $lang );
		}
	}
	return implode( "\n\n", $blocks );
}

/* Astro order: home = hero → value → product teaser → benefits → applications
   teaser → who it's for → research/team strip → final CTA */
$home_order    = array( 'hero-field', 'value-flow', 'product-teaser', 'benefits', 'apps-teaser', 'audiences', 'explore', 'page-cta' );
/* product = hero → what it is → differentiator → how it works → parameters
   → benefits → FAQ → final CTA */
$product_order = array( 'page-hero', 'section-intro', 'differentiator', 'how-it-works', 'parameters', 'benefits', 'faq', 'page-cta' );

$home_en = benefits_qa_page( 'home-en', 'Home', 'home', benefits_qa_content( 'home', $home_order, 'en' ), 'en', 'home' );
$home_es = benefits_qa_page( 'home-es', 'Inicio', 'inicio', benefits_qa_content( 'home', $home_order, 'es' ), 'es', 'home' );
pll_save_post_translations( array( 'en' => $home_en, 'es' => $home_es ) );

$prod_en = benefits_qa_page( 'product-en', 'Product', 'product', benefits_qa_content( 'product', $product_order, 'en' ), 'en', 'product' );
$prod_es = benefits_qa_page( 'product-es', 'Producto', 'producto', benefits_qa_content( 'product', $product_order, 'es' ), 'es', 'product' );
pll_save_post_translations( array( 'en' => $prod_en, 'es' => $prod_es ) );

$roles = array(
	'home'    => $home_en,
	'product' => $prod_en,
);
$stubs = array(
	'applications' => array( 'Applications', 'Aplicaciones', 'applications', 'aplicaciones' ),
	'research'     => array( 'Case studies', 'Casos de estudio', 'research', 'casos-de-estudio' ),
	'team'         => array( 'Team', 'Equipo', 'team', 'equipo' ),
	'contact'      => array( 'Contact', 'Contacto', 'contact', 'contacto' ),
);
foreach ( $stubs as $role => $t ) {
	$en = benefits_qa_page( $role . '-en', $t[0], $t[2], '<!-- wp:paragraph --><p>Stub</p><!-- /wp:paragraph -->', 'en', $role );
	$es = benefits_qa_page( $role . '-es', $t[1], $t[3], '<!-- wp:paragraph --><p>Stub</p><!-- /wp:paragraph -->', 'es', $role );
	pll_save_post_translations( array( 'en' => $en, 'es' => $es ) );
	$roles[ $role ] = $en;
}
update_option( 'mats_page_roles', $roles );
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $home_en );
flush_rewrite_rules();

echo wp_json_encode(
	array(
		'home_en'    => $home_en,
		'home_es'    => $home_es,
		'product_en' => $prod_en,
		'product_es' => $prod_es,
		'urls'       => array( get_permalink( $home_en ), get_permalink( $home_es ), get_permalink( $prod_en ), get_permalink( $prod_es ) ),
	),
	JSON_UNESCAPED_SLASHES
), "\n";
