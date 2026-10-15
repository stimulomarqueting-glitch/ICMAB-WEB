<?php
/**
 * QA setup for the "secseo" module: two linked news articles (EN/ES), one
 * with a featured image and an excerpt, one without; a test editor user.
 *   MATS_WP_PORT=8916 php /home/user/wpsite-cli.php wordpress/tools/qa/secseo-setup.php
 *
 * @package matsurfer-qa
 */

/** A 2400×1600 JPEG in the media library (made once with GD). */
function mats_secseo_big_image(): int {
	$found = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_mats_secseo_big', 'fields' => 'ids', 'posts_per_page' => 1 ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	if ( $found ) {
		return (int) $found[0];
	}
	$img = imagecreatetruecolor( 2400, 1600 );
	for ( $y = 0; $y < 1600; $y += 40 ) {
		imagefilledrectangle( $img, 0, $y, 2399, $y + 39, imagecolorallocate( $img, 40 + ( $y / 16 ) % 200, 90, 160 ) );
	}
	$tmp = wp_tempnam( 'secseo-big.jpg' );
	imagejpeg( $img, $tmp, 85 );
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$id = media_handle_sideload( array( 'name' => 'secseo-big.jpg', 'tmp_name' => $tmp ), 0 );
	update_post_meta( $id, '_mats_secseo_big', 1 );
	update_post_meta( $id, '_wp_attachment_image_alt', 'Franjas de color (imagen de prueba)' );
	return (int) $id;
}

$mats_ids = array();
foreach ( array( 'en', 'es' ) as $mats_lang ) {
	$mats_slug     = 'en' === $mats_lang ? 'secseo-test-article' : 'secseo-articulo-de-prueba';
	$mats_existing = get_page_by_path( $mats_slug, OBJECT, 'post' );
	$mats_postarr  = array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_name'    => $mats_slug,
		'post_title'   => 'en' === $mats_lang ? 'SecSEO test article: "quotes" & ampersands' : 'Artículo de prueba SecSEO: «comillas» y ampersands',
		'post_content' => "<!-- wp:paragraph -->\n<p>" . ( 'en' === $mats_lang ? 'Body of the English article. It talks about surfaces, coatings and deposition in a lab.' : 'Cuerpo del artículo en español. Habla de superficies, recubrimientos y deposición en el laboratorio.' ) . "</p>\n<!-- /wp:paragraph -->",
		'post_excerpt' => 'en' === $mats_lang ? 'Short summary of the English test article.' : '',
	);
	if ( $mats_existing ) {
		$mats_postarr['ID'] = $mats_existing->ID;
	}
	$mats_id = (int) wp_insert_post( $mats_postarr );
	pll_set_post_language( $mats_id, $mats_lang );
	/* EN: a shipped photo (1100×619); ES: a big generated photo (2400×1600) */
	delete_post_thumbnail( $mats_id );
	set_post_thumbnail( $mats_id, 'en' === $mats_lang ? mats_import_media( 'product-table.jpg' ) : mats_secseo_big_image() );
	$mats_ids[ $mats_lang ] = $mats_id;
}
pll_save_post_translations( $mats_ids );

if ( ! get_user_by( 'login', 'editora' ) ) {
	wp_insert_user(
		array(
			'user_login' => 'editora',
			'user_pass'  => 'editora-local-pass',
			'user_email' => 'editora@example.org',
			'role'       => 'editor',
		)
	);
}
echo wp_json_encode( array( 'posts' => $mats_ids, 'editor' => get_user_by( 'login', 'editora' )->ID, 'roles' => get_option( 'mats_page_roles' ) ) ), "\n";
foreach ( get_option( 'mats_page_roles' ) as $mats_role => $mats_pid ) {
	echo $mats_role, ' en=', get_permalink( $mats_pid ), ' es=', get_permalink( pll_get_post( $mats_pid, 'es' ) ), ' status=', get_post_status( $mats_pid ), "\n";
}
foreach ( $mats_ids as $mats_lang => $mats_id ) {
	$mats_meta = wp_get_attachment_metadata( get_post_thumbnail_id( $mats_id ) );
	echo $mats_lang, ' featured ', $mats_meta['width'], 'x', $mats_meta['height'], ' mats-og: ', isset( $mats_meta['sizes']['mats-og'] ) ? $mats_meta['sizes']['mats-og']['width'] . 'x' . $mats_meta['sizes']['mats-og']['height'] : 'none', "\n";
}
echo 'post en=', get_permalink( $mats_ids['en'] ), ' es=', get_permalink( $mats_ids['es'] ), "\n";
