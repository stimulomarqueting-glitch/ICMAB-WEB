<?php
/**
 * QA (secseo): the classic-editor fallback box of "Google y redes sociales"
 * (nonce + capability checks, sanitising) and the REST meta registration.
 *   MATS_WP_PORT=8916 php /home/user/wpsite-cli.php wordpress/tools/qa/secseo-metabox.php <postId>
 *
 * @package matsurfer-qa
 */

$mats_id   = (int) ( $argv[2] ?? 53 );
$mats_fail = 0;
$mats_ok   = static function ( bool $cond, string $label ) use ( &$mats_fail ) {
	echo ( $cond ? '[ok]   ' : '[FAIL] ' ), $label, "\n";
	$mats_fail += $cond ? 0 : 1;
};
$mats_before = array( get_post_meta( $mats_id, '_mats_seo_title', true ), get_post_meta( $mats_id, '_mats_seo_description', true ) );

ob_start();
mats_seo_metabox( get_post( $mats_id ) );
$mats_box = ob_get_clean();
$mats_ok( false !== strpos( $mats_box, 'name="mats_seo_nonce"' ) && false !== strpos( $mats_box, 'name="mats_seo_title"' ), 'box prints the nonce and the fields' );

/* 1. no nonce → nothing saved */
$_POST = array( 'mats_seo_title' => 'Sin nonce' );
wp_update_post( array( 'ID' => $mats_id ) );
$mats_ok( 'Sin nonce' !== get_post_meta( $mats_id, '_mats_seo_title', true ), 'without nonce nothing is saved' );

/* 2. wrong nonce → nothing saved */
$_POST = array( 'mats_seo_nonce' => 'bad', 'mats_seo_title' => 'Nonce falso' );
wp_update_post( array( 'ID' => $mats_id ) );
$mats_ok( 'Nonce falso' !== get_post_meta( $mats_id, '_mats_seo_title', true ), 'with a wrong nonce nothing is saved' );

/* 3. valid nonce but a user who cannot edit → nothing saved */
$mats_sub = wp_insert_user( array( 'user_login' => 'secseo-sub-' . wp_rand( 100, 999 ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
wp_set_current_user( $mats_sub );
$_POST = array( 'mats_seo_nonce' => wp_create_nonce( 'mats_seo_save_' . $mats_id ), 'mats_seo_title' => 'Suscriptor' );
do_action( 'save_post', $mats_id, get_post( $mats_id ), true );
$mats_ok( 'Suscriptor' !== get_post_meta( $mats_id, '_mats_seo_title', true ), 'a subscriber cannot save' );
wp_set_current_user( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $mats_sub );

/* 4. valid → saved and sanitised */
$_POST = array(
	'mats_seo_nonce'       => wp_create_nonce( 'mats_seo_save_' . $mats_id ),
	'mats_seo_title'       => '  <b>Título</b> con   espacios <script>x</script> ',
	'mats_seo_description' => "Línea uno\n\nLínea   dos <a href='x'>enlace</a>",
);
wp_update_post( array( 'ID' => $mats_id ) );
$mats_t = get_post_meta( $mats_id, '_mats_seo_title', true );
$mats_d = get_post_meta( $mats_id, '_mats_seo_description', true );
echo "saved title: [$mats_t]\nsaved description: [$mats_d]\n";
$mats_ok( 'Título con espacios' === $mats_t, 'title saved without tags or extra spaces' );
$mats_ok( 'Línea uno Línea dos enlace' === $mats_d, 'description saved on one line without tags' );

/* 5. REST registration */
$mats_keys = array_keys( get_registered_meta_keys( 'post', 'post' ) );
$mats_ok( in_array( '_mats_seo_title', $mats_keys, true ) && in_array( '_mats_seo_description', $mats_keys, true ), 'meta registered for news articles' );
$mats_keys = array_keys( get_registered_meta_keys( 'post', 'page' ) );
$mats_ok( in_array( '_mats_seo_title', $mats_keys, true ), 'meta registered for pages' );
$mats_ok( ! in_array( '_mats_seo_title', array_keys( get_registered_meta_keys( 'post', 'mats_team' ) ), true ), 'not registered for private records' );

/* restore */
$_POST = array();
update_post_meta( $mats_id, '_mats_seo_title', $mats_before[0] );
update_post_meta( $mats_id, '_mats_seo_description', $mats_before[1] );
echo $mats_fail ? "\nFAILED: $mats_fail\n" : "\nALL META BOX CHECKS PASSED\n";
