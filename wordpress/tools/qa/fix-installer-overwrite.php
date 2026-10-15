<?php
/**
 * QA (installer fix pass): «Sobrescribir los textos y las fotos con los
 * originales» does exactly what its label says.
 *
 *   MATS_WP_PORT=8984 php /home/user/wpsite-cli.php wordpress/tools/qa/fix-installer-overwrite.php
 *
 * On a set-up clone: Home EN gets its own Google title/description; the
 * Editor (qa-editor, see fix-installer-setup.php) changes the hero title and
 * photo of Product EN through the REST API, like the block editor. Then the
 * content step runs with overwrite. Expected: the Google texts are KEPT,
 * the Product text and photo are back to the originals, and the Editor's
 * version can still be restored from «Revisiones».
 *
 * @package matsurfer-qa
 */

$mats_fail = 0;
$mats_ok   = static function ( bool $cond, string $text ) use ( &$mats_fail ) {
	echo ( $cond ? '  ✓ ' : '  ✗ FAIL ' ), $text, "\n";
	if ( ! $cond ) {
		++$mats_fail;
	}
};

$mats_home    = mats_page_id( 'home', 'en' );
$mats_product = mats_page_id( 'product', 'en' );
update_post_meta( $mats_home, '_mats_seo_title', 'QA custom Google title' );
update_post_meta( $mats_home, '_mats_seo_description', 'QA custom Google description' );

$mats_editor = get_user_by( 'login', 'qa-editor' );
wp_set_current_user( $mats_editor ? $mats_editor->ID : 1 );
$mats_before = get_post_field( 'post_content', $mats_product );
preg_match( '/"id":(\d+)/', $mats_before, $mats_m );
$mats_photo = (int) ( $mats_m[1] ?? 0 );
$mats_other = (int) get_posts(
	array(
		'post_type'        => 'attachment',
		'post_mime_type'   => 'image',
		'numberposts'      => 1,
		'fields'           => 'ids',
		'post__not_in'     => array( $mats_photo ),
		'suppress_filters' => true,
	)
)[0];
$mats_edit = preg_replace( '/"id":' . $mats_photo . '\b/', '"id":' . $mats_other, $mats_before, 1 );
$mats_edit = preg_replace( '/"title":"[^"]*"/', '"title":"QA EDITED TITLE"', $mats_edit, 1 );
$mats_req  = new WP_REST_Request( 'POST', '/wp/v2/pages/' . $mats_product );
$mats_req->set_param( 'content', $mats_edit );
$mats_res = rest_do_request( $mats_req );
echo 'editor save of Product EN: HTTP ', $mats_res->get_status(), " (photo $mats_photo → $mats_other, hero title → QA EDITED TITLE)\n";

wp_set_current_user( 1 );
$mats_run = mats_installer_run_step( 'content', array( 'overwrite' => true ) );
foreach ( $mats_run['messages'] as $mats_msg ) {
	if ( false !== strpos( $mats_msg['text'], 'restaurados' ) ) {
		echo '  message: ', $mats_msg['text'], "\n";
	}
}

$mats_ok( 'QA custom Google title' === get_post_meta( $mats_home, '_mats_seo_title', true ) && 'QA custom Google description' === get_post_meta( $mats_home, '_mats_seo_description', true ), 'Google title and description written by staff are kept' );
$mats_after = get_post_field( 'post_content', $mats_product );
preg_match( '/"id":(\d+)/', $mats_after, $mats_m );
$mats_ok( (int) ( $mats_m[1] ?? 0 ) === $mats_photo, 'Product photo back to the original (' . ( $mats_m[1] ?? '-' ) . ')' );
$mats_ok( false === strpos( $mats_after, 'QA EDITED TITLE' ), 'Product text back to the original' );
$mats_found = false;
foreach ( wp_get_post_revisions( $mats_product ) as $mats_rev ) {
	if ( false !== strpos( $mats_rev->post_content, 'QA EDITED TITLE' ) && false !== strpos( $mats_rev->post_content, '"id":' . $mats_other ) ) {
		$mats_found = $mats_rev->ID;
	}
}
$mats_ok( (bool) $mats_found, 'the Editor\'s version (text + photo) is in «Revisiones» (revision ' . (int) $mats_found . ')' );
$mats_ok( count( wp_get_post_revisions( $mats_product ) ) >= 2, 'Product has ' . count( wp_get_post_revisions( $mats_product ) ) . ' revisions' );
echo $mats_fail ? "$mats_fail FAILURE(S)\n" : "all checks passed\n";
