<?php
/**
 * QA (group "home"): builds the home page test pages for the value-flow,
 * product-teaser, apps-teaser and explore sections, EN + ES linked as
 * translations, in the order of src/pages/[...lang]/index.astro (the
 * neighbouring sections that already have a render.php are included:
 * hero-field before, page-cta after), set as the front page, plus stub
 * pages for every role the section links point to.
 * Run: MATS_WP_PORT=8903 php /home/user/wpsite-cli.php wordpress/tools/qa/home-setup.php
 */
function home_qa_page( string $key, string $title, string $slug, string $content, string $lang, string $role = '' ): int {
	$existing = get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'meta_key' => '_home_qa', 'meta_value' => $key, 'numberposts' => 1, 'fields' => 'ids', 'lang' => '' ) );
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
		update_post_meta( $id, '_home_qa', $key );
	}
	pll_set_post_language( $id, $lang );
	if ( $role ) {
		update_post_meta( $id, '_mats_role', $role );
	}
	return (int) $id;
}

function home_qa_content( string $lang ): string {
	$blocks = array();
	foreach ( array( 'hero-field', 'value-flow', 'product-teaser', 'apps-teaser', 'explore', 'page-cta' ) as $block ) {
		if ( is_readable( MATS_DIR . '/blocks/' . $block . '/render.php' ) ) {
			$blocks[] = mats_seed_block( $block, 'home', $lang );
		}
	}
	return implode( "\n\n", $blocks );
}

$home_en = home_qa_page( 'home-en', 'Home', 'home', home_qa_content( 'en' ), 'en', 'home' );
$home_es = home_qa_page( 'home-es', 'Inicio', 'inicio', home_qa_content( 'es' ), 'es', 'home' );
pll_save_post_translations( array( 'en' => $home_en, 'es' => $home_es ) );

$roles = array( 'home' => $home_en );
$stubs = array(
	'product'      => array( 'Product', 'Producto', 'product', 'producto' ),
	'applications' => array( 'Applications', 'Aplicaciones', 'applications', 'aplicaciones' ),
	'research'     => array( 'Case studies', 'Casos de estudio', 'research', 'casos-de-estudio' ),
	'team'         => array( 'Team', 'Equipo', 'team', 'equipo' ),
	'contact'      => array( 'Contact', 'Contacto', 'contact', 'contacto' ),
);
foreach ( $stubs as $role => $t ) {
	$en = home_qa_page( $role . '-en', $t[0], $t[2], '<!-- wp:paragraph --><p>Stub</p><!-- /wp:paragraph -->', 'en', $role );
	$es = home_qa_page( $role . '-es', $t[1], $t[3], '<!-- wp:paragraph --><p>Stub</p><!-- /wp:paragraph -->', 'es', $role );
	pll_save_post_translations( array( 'en' => $en, 'es' => $es ) );
	$roles[ $role ] = $en;
}
update_option( 'mats_page_roles', $roles );
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $home_en );
flush_rewrite_rules();

echo wp_json_encode(
	array(
		'home_en' => $home_en,
		'home_es' => $home_es,
		'roles'   => $roles,
		'en'      => get_permalink( $home_en ),
		'es'      => get_permalink( $home_es ),
	)
), "\n";
