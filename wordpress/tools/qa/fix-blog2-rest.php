<?php
/**
 * QA (fix bucket "blog2"): who may save «Descripción de la foto destacada»
 * (_mats_thumb_alt) through the REST API the block editor uses, and how it
 * is cleaned. Prints PASS/FAIL lines. Run fix-blog2-setup.php first.
 *   MATS_WP_PORT=8986 php /home/user/wpsite-cli.php wordpress/tools/qa/fix-blog2-rest.php
 */

defined( 'ABSPATH' ) || exit;

$mats_qa_fails = 0;
$mats_qa_check = static function ( bool $ok, string $label, string $detail = '' ) use ( &$mats_qa_fails ) {
	$mats_qa_fails += $ok ? 0 : 1;
	echo ( $ok ? 'PASS ' : 'FAIL ' ), $label, '' !== $detail ? ' — ' . $detail : '', "\n";
};

$mats_qa_post = (int) ( get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 'lang' => 'es', 'meta_key' => '_thumbnail_id' ) )[0] ?? 0 );
$mats_qa_page = (int) ( get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 'lang' => 'es' ) )[0] ?? 0 );
$mats_qa_old  = (string) get_post_meta( $mats_qa_post, '_mats_thumb_alt', true );

/* an author (may not edit other people's articles) */
$mats_qa_author = get_user_by( 'login', 'qa-blog2-autor' );
$mats_qa_aid    = $mats_qa_author ? $mats_qa_author->ID : wp_insert_user( array( 'user_login' => 'qa-blog2-autor', 'user_pass' => wp_generate_password(), 'user_email' => 'qa-blog2-autor@example.org', 'role' => 'author' ) );
$mats_qa_send   = static function ( int $user, int $post, string $value, string $route = 'posts' ) {
	wp_set_current_user( $user );
	$req = new WP_REST_Request( 'POST', '/wp/v2/' . $route . '/' . $post );
	$req->set_body_params( array( 'meta' => array( '_mats_thumb_alt' => $value ) ) );
	return rest_do_request( $req );
};

$mats_qa_res = $mats_qa_send( (int) $mats_qa_aid, $mats_qa_post, 'Hacked' );
$mats_qa_check( $mats_qa_res->get_status() >= 400 && get_post_meta( $mats_qa_post, '_mats_thumb_alt', true ) === $mats_qa_old, 'an Author cannot change it on someone else\'s article', (string) $mats_qa_res->get_status() );

$mats_qa_editor = get_user_by( 'login', 'editora' );
$mats_qa_res    = $mats_qa_send( (int) $mats_qa_editor->ID, $mats_qa_post, "  <script>x</script><em>Una cámara</em>\n de vidrio   sobre la mesa  " );
$mats_qa_saved  = (string) get_post_meta( $mats_qa_post, '_mats_thumb_alt', true );
$mats_qa_check( 200 === $mats_qa_res->get_status() && 'Una cámara de vidrio sobre la mesa' === $mats_qa_saved, 'an Editor saves it, as plain one-line text', $mats_qa_res->get_status() . ' "' . $mats_qa_saved . '"' );
$mats_qa_data = $mats_qa_res->get_data();
$mats_qa_check( isset( $mats_qa_data['meta']['_mats_thumb_alt'] ), 'returned in the article\'s REST data (the editor reads it)' );

$mats_qa_res = $mats_qa_send( (int) $mats_qa_editor->ID, $mats_qa_post, str_repeat( 'a', 400 ) );
$mats_qa_check( 300 === mb_strlen( (string) get_post_meta( $mats_qa_post, '_mats_thumb_alt', true ) ), 'at most 300 characters' );

if ( $mats_qa_page ) {
	$mats_qa_res  = $mats_qa_send( (int) $mats_qa_editor->ID, $mats_qa_page, 'x', 'pages' );
	$mats_qa_data = $mats_qa_res->get_data();
	$mats_qa_check( ! isset( $mats_qa_data['meta']['_mats_thumb_alt'] ) && '' === (string) get_post_meta( $mats_qa_page, '_mats_thumb_alt', true ), 'pages do not have it (news articles only)' );
}

/* back to what it was */
wp_set_current_user( 0 );
if ( '' === $mats_qa_old ) {
	delete_post_meta( $mats_qa_post, '_mats_thumb_alt' );
} else {
	update_post_meta( $mats_qa_post, '_mats_thumb_alt', $mats_qa_old );
}
echo $mats_qa_fails ? "\n{$mats_qa_fails} FAIL\n" : "\nall PASS\n";
