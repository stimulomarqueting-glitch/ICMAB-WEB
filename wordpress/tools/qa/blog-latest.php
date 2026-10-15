<?php
/**
 * QA (module "blog"): test pages for the "Últimas noticias" section.
 *   MATS_WP_PORT=8914 php /home/user/wpsite-cli.php wordpress/tools/qa/blog-latest.php
 * EN + ES landing pages (slug qa-latest / qa-ultimas) with: the seeded
 * section (3 cards), a 6-card one, a freshly inserted one (no attributes)
 * and the closing band. Prints the page IDs.
 */

defined( 'ABSPATH' ) || exit;

$ids = array();
foreach ( array( 'en' => 'qa-latest', 'es' => 'qa-ultimas' ) as $lang => $slug ) {
	foreach ( get_posts( array( 'post_type' => 'page', 'name' => $slug, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'lang' => '' ) ) as $old ) {
		wp_delete_post( $old, true );
	}
	$fresh   = serialize_block(
		array(
			'blockName'    => 'matsurfer/latest-posts',
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		)
	);
	$content = mats_seed_block( 'page-hero', 'research', $lang ) . "\n\n"
		. mats_seed_block( 'latest-posts', 'home', $lang ) . "\n\n"
		. mats_seed_block( 'latest-posts', 'home', $lang, array( 'count' => '6', 'title' => 'es' === $lang ? 'Seis noticias' : 'Six articles' ) ) . "\n\n"
		. $fresh . "\n\n"
		. mats_seed_block( 'page-cta', 'home', $lang );
	kses_remove_filters();
	$id = wp_insert_post(
		wp_slash(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'QA latest news',
				'post_name'    => $slug,
				'post_content' => $content,
			)
		)
	);
	kses_init_filters();
	pll_set_post_language( $id, $lang );
	$ids[ $lang ] = $id;
}
pll_save_post_translations( $ids );
echo wp_json_encode( $ids ), "\n";
