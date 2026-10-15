<?php
/**
 * admin module QA: an 'editor' user, a few news articles (one translated,
 * one only in English, one draft), a non-structural reading page and the
 * WPForms stub active, so the admin lockdown can be checked.
 *
 *   MATS_WP_PORT=8915 php /home/user/wpsite-cli.php \
 *     /home/user/ICMAB-WEB/wordpress/tools/qa/admin-setup.php
 *
 * Login for the editor: editora / editora-local-pass
 *
 * @package matsurfer-qa
 */

$mats_qa_user = get_user_by( 'login', 'editora' );
if ( ! $mats_qa_user ) {
	$mats_qa_id = wp_insert_user(
		array(
			'user_login'   => 'editora',
			'user_pass'    => 'editora-local-pass',
			'user_email'   => 'editora@example.org',
			'first_name'   => 'Laura',
			'display_name' => 'Laura (ICMAB)',
			'role'         => 'editor',
		)
	);
	echo is_wp_error( $mats_qa_id ) ? 'user error: ' . $mats_qa_id->get_error_message() . "\n" : "editor user created: $mats_qa_id\n";
} else {
	echo "editor user exists: {$mats_qa_user->ID}\n";
}

if ( ! is_plugin_active( 'wpforms-stub/wpforms-stub.php' ) && file_exists( WP_PLUGIN_DIR . '/wpforms-stub/wpforms-stub.php' ) ) {
	$mats_qa_res = activate_plugin( 'wpforms-stub/wpforms-stub.php' );
	echo is_wp_error( $mats_qa_res ) ? 'stub error: ' . $mats_qa_res->get_error_message() . "\n" : "wpforms stub active\n";
}

/** Creates a post once (by title). */
function mats_qa_admin_post( string $title, string $lang, string $status = 'publish', string $type = 'post' ): int {
	$found = get_posts(
		array(
			'post_type'        => $type,
			'title'            => $title,
			'post_status'      => 'any',
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'suppress_filters' => true,
			'lang'             => '',
		)
	);
	if ( $found ) {
		return (int) $found[0];
	}
	$id = wp_insert_post(
		array(
			'post_type'    => $type,
			'post_title'   => $title,
			'post_status'  => $status,
			'post_content' => "<!-- wp:paragraph -->\n<p>QA text for {$title}. R&amp;D at ICMAB.</p>\n<!-- /wp:paragraph -->",
		)
	);
	if ( function_exists( 'pll_set_post_language' ) ) {
		pll_set_post_language( $id, $lang );
	}
	return (int) $id;
}

$mats_qa_en = mats_qa_admin_post( 'QA news: lab results', 'en' );
$mats_qa_es = mats_qa_admin_post( 'QA noticia: resultados del laboratorio', 'es' );
if ( function_exists( 'pll_save_post_translations' ) ) {
	pll_save_post_translations(
		array(
			'en' => $mats_qa_en,
			'es' => $mats_qa_es,
		)
	);
}
mats_qa_admin_post( 'QA news: English only', 'en' );
mats_qa_admin_post( 'QA noticia en borrador', 'es', 'draft' );
$mats_qa_page = mats_qa_admin_post( 'QA reading page', 'en', 'publish', 'page' );
echo "posts: en=$mats_qa_en es=$mats_qa_es page=$mats_qa_page\n";
echo 'roles: ' . wp_json_encode( get_option( 'mats_page_roles' ) ) . "\n";
