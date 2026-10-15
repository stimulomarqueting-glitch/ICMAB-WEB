<?php
/**
 * QA (fix bucket "blog2"): an Editor account and a clean start for the
 * «Descripción de la foto destacada» checks. Run blog-setup.php (news) and
 * blog-latest.php («Últimas noticias» test pages) first.
 *   MATS_WP_PORT=8986 php /home/user/wpsite-cli.php wordpress/tools/qa/fix-blog2-setup.php
 * Login for the editor: editora / editora-local-pass. Removes every
 * _mats_thumb_alt and prints, as JSON, the EN/ES article pairs that share a
 * featured image (with the image's own alt) and the latest-posts pages.
 */

defined( 'ABSPATH' ) || exit;

$mats_qa_user = get_user_by( 'login', 'editora' );
if ( ! $mats_qa_user ) {
	$mats_qa_uid = wp_insert_user(
		array(
			'user_login'   => 'editora',
			'user_pass'    => 'editora-local-pass',
			'user_email'   => 'editora@example.org',
			'first_name'   => 'Laura',
			'display_name' => 'Laura (ICMAB)',
			'role'         => 'editor',
		)
	);
	if ( is_wp_error( $mats_qa_uid ) ) {
		echo 'user error: ', esc_html( $mats_qa_uid->get_error_message() ), "\n";
	}
}

delete_metadata( 'post', 0, '_mats_thumb_alt', '', true );

$mats_qa_pairs = array();
foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => -1, 'lang' => 'en', 'orderby' => 'date', 'order' => 'DESC' ) ) as $mats_qa_en ) {
	$mats_qa_es = (int) pll_get_post( $mats_qa_en->ID, 'es' );
	$mats_qa_th = (int) get_post_thumbnail_id( $mats_qa_en );
	if ( $mats_qa_es && $mats_qa_th && (int) get_post_thumbnail_id( $mats_qa_es ) === $mats_qa_th ) {
		$mats_qa_pairs[] = array(
			'en'     => $mats_qa_en->ID,
			'es'     => $mats_qa_es,
			'enUrl'  => get_permalink( $mats_qa_en ),
			'esUrl'  => get_permalink( $mats_qa_es ),
			'thumb'  => $mats_qa_th,
			'shared' => (string) get_post_meta( $mats_qa_th, '_wp_attachment_image_alt', true ),
		);
	}
}
$mats_qa_latest = array();
foreach ( array( 'en' => 'qa-latest', 'es' => 'qa-ultimas' ) as $mats_qa_lang => $mats_qa_slug ) {
	$mats_qa_page                    = get_posts( array( 'post_type' => 'page', 'name' => $mats_qa_slug, 'numberposts' => 1, 'lang' => '' ) );
	$mats_qa_latest[ $mats_qa_lang ] = $mats_qa_page ? $mats_qa_page[0]->ID : 0;
}
$mats_qa_other = (int) ( get_posts( array( 'post_type' => 'attachment', 'post_mime_type' => 'image', 'numberposts' => 1, 'fields' => 'ids', 'post__not_in' => array_column( $mats_qa_pairs, 'thumb' ), 'meta_key' => '_mats_media_source' ) )[0] ?? 0 );

echo wp_json_encode(
	array(
		'pairs'  => $mats_qa_pairs,
		'latest' => $mats_qa_latest,
		'other'  => $mats_qa_other,
	)
), "\n";
