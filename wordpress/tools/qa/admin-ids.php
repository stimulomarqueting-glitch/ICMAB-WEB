<?php
/**
 * admin module QA: prints the ids the Playwright checks use, as JSON
 * (they differ between a builder database and a cloned full install).
 *
 *   MATS_WP_PORT=8915 php /home/user/wpsite-cli.php \
 *     /home/user/ICMAB-WEB/wordpress/tools/qa/admin-ids.php
 *
 * @package matsurfer-qa
 */

/** First page (any status) holding a role in a language. */
function mats_qa_ids_role( string $role, string $lang ): int {
	$ids = get_posts(
		array(
			'post_type'   => 'page',
			'post_status' => 'any',
			'numberposts' => 5,
			'fields'      => 'ids',
			'lang'        => '',
			'meta_key'    => '_mats_role', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'  => $role, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	foreach ( $ids as $id ) {
		if ( pll_get_post_language( $id ) === $lang ) {
			return (int) $id;
		}
	}
	return 0;
}

/** A post by title (any status, any language). */
function mats_qa_ids_title( string $title, string $type = 'post' ): int {
	$ids = get_posts(
		array(
			'post_type'   => $type,
			'title'       => $title,
			'post_status' => 'any',
			'numberposts' => 1,
			'fields'      => 'ids',
			'lang'        => '',
		)
	);
	return $ids ? (int) $ids[0] : 0;
}

echo wp_json_encode(
	array(
		'product'    => mats_qa_ids_role( 'product', 'en' ),
		'product_es' => mats_qa_ids_role( 'product', 'es' ),
		'team'       => mats_qa_ids_role( 'team', 'en' ),
		'legal'      => mats_qa_ids_role( 'legal', 'en' ),
		'news_page'  => mats_qa_ids_role( 'news', 'en' ),
		'post'       => mats_qa_ids_title( 'QA news: English only' ),
		'post_en'    => mats_qa_ids_title( 'QA news: lab results' ),
		'reading'    => mats_qa_ids_title( 'QA reading page', 'page' ),
	)
);
