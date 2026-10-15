<?php
/**
 * QA (group "hero"): builds the home page test pages for the hero-field
 * section, EN + ES linked as translations, set as the front page, plus stub
 * Product/Contact pages so the hero buttons resolve to real URLs.
 * Run: MATS_WP_PORT=8901 php /home/user/wpsite-cli.php wordpress/tools/qa/hero-setup.php
 */
$made = array();
function hero_qa_page( string $key, string $title, string $slug, string $content, string $lang, string $role = '' ): int {
	$existing = get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'meta_key' => '_hero_qa', 'meta_value' => $key, 'numberposts' => 1, 'fields' => 'ids', 'lang' => '' ) );
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
		update_post_meta( $id, '_hero_qa', $key );
	}
	pll_set_post_language( $id, $lang );
	if ( $role ) {
		update_post_meta( $id, '_mats_role', $role );
	}
	return (int) $id;
}

$home_en = hero_qa_page( 'home-en', 'Home', 'home', mats_seed_block( 'hero-field', 'home', 'en' ), 'en', 'home' );
$home_es = hero_qa_page( 'home-es', 'Inicio', 'inicio', mats_seed_block( 'hero-field', 'home', 'es' ), 'es', 'home' );
pll_save_post_translations( array( 'en' => $home_en, 'es' => $home_es ) );

$roles = array( 'home' => $home_en );
foreach ( array( 'product' => array( 'Product', 'Producto' ), 'contact' => array( 'Contact', 'Contacto' ) ) as $role => $titles ) {
	$en = hero_qa_page( $role . '-en', $titles[0], $role, '<!-- wp:paragraph --><p>Stub</p><!-- /wp:paragraph -->', 'en', $role );
	$es = hero_qa_page( $role . '-es', $titles[1], $role . '-es', '<!-- wp:paragraph --><p>Stub</p><!-- /wp:paragraph -->', 'es', $role );
	pll_save_post_translations( array( 'en' => $en, 'es' => $es ) );
	$roles[ $role ] = $en;
}
update_option( 'mats_page_roles', $roles );
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $home_en );
flush_rewrite_rules();

echo wp_json_encode( array( 'home_en' => $home_en, 'home_es' => $home_es, 'roles' => $roles, 'en' => get_permalink( $home_en ), 'es' => get_permalink( $home_es ) ) ), "\n";
