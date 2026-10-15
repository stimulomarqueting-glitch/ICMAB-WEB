<?php
/**
 * QA (secseo): capabilities, upload rules, auto-updates and sitemap
 * providers as the security/SEO modules leave them.
 *   MATS_WP_PORT=8916 php /home/user/wpsite-cli.php wordpress/tools/qa/secseo-caps.php
 *
 * @package matsurfer-qa
 */

$mats_fail = array();
$mats_ok   = static function ( bool $cond, string $label ) use ( &$mats_fail ) {
	echo ( $cond ? '[ok]   ' : '[FAIL] ' ), $label, "\n";
	if ( ! $cond ) {
		$mats_fail[] = $label;
	}
};

$mats_admin  = get_user_by( 'login', 'admin' );
$mats_editor = get_user_by( 'login', 'editora' );

foreach ( array( 'edit_themes', 'edit_plugins', 'edit_files' ) as $mats_cap ) {
	$mats_ok( ! user_can( $mats_admin, $mats_cap ), "admin cannot $mats_cap" );
	$mats_ok( ! user_can( $mats_editor, $mats_cap ), "editor cannot $mats_cap" );
}
foreach ( array( 'install_plugins', 'activate_plugins', 'update_plugins', 'delete_plugins', 'install_themes', 'switch_themes', 'update_themes', 'delete_themes', 'update_core', 'upload_plugins', 'install_languages' ) as $mats_cap ) {
	$mats_ok( user_can( $mats_admin, $mats_cap ), "admin keeps $mats_cap" );
	$mats_ok( ! user_can( $mats_editor, $mats_cap ), "editor cannot $mats_cap" );
}
$mats_ok( user_can( $mats_admin, 'activate_plugin', 'polylang/polylang.php' ), 'admin keeps activate_plugin (meta cap)' );
$mats_ok( ! user_can( $mats_editor, 'activate_plugin', 'polylang/polylang.php' ), 'editor cannot activate_plugin (meta cap)' );
$mats_ok( user_can( $mats_editor, 'edit_pages' ) && user_can( $mats_editor, 'publish_posts' ) && user_can( $mats_editor, 'upload_files' ), 'editor keeps editing caps (pages, posts, uploads)' );
$mats_ok( user_can( $mats_admin, 'manage_options' ), 'admin keeps manage_options' );

/* uploads */
wp_set_current_user( $mats_admin->ID );
$mats_mimes = get_allowed_mime_types();
foreach ( array( 'js', 'htm|html', 'svg', 'swf', 'exe', 'php' ) as $mats_ext ) {
	$mats_ok( ! isset( $mats_mimes[ $mats_ext ] ), "admin may not upload .$mats_ext" );
}
foreach ( array( 'jpg|jpeg|jpe', 'png', 'webp', 'pdf', 'mp4|m4v' ) as $mats_ext ) {
	$mats_ok( isset( $mats_mimes[ $mats_ext ] ), "uploads keep .$mats_ext" );
}
foreach ( array( 'photo.php.jpg', 'shell.phtml', 'x.PHP5', '.htaccess', 'a.svg', 'b.js', 'page.html', 'run.cgi.png', 'logo.svgz' ) as $mats_name ) {
	$mats_r = mats_sec_upload_prefilter( array( 'name' => $mats_name, 'size' => 100, 'error' => 0 ) );
	$mats_ok( ! empty( $mats_r['error'] ), "upload refused: $mats_name" );
}
foreach ( array( 'product-table.jpg', 'Foto equipo 2026.JPG', 'informe.v2.pdf', 'www.icmab.es.png', 'informe.pl.png', 'figura.py.png', 'matsurfer-device.mp4', 'matsurfer-1.0.0.zip' ) as $mats_name ) {
	$mats_r = mats_sec_upload_prefilter( array( 'name' => $mats_name, 'size' => 100, 'error' => 0 ) );
	$mats_ok( empty( $mats_r['error'] ), "upload accepted: $mats_name" );
}
wp_set_current_user( $mats_editor->ID );
$mats_r = mats_sec_upload_prefilter( array( 'name' => 'evil.php.zip', 'size' => 100, 'error' => 0 ) );
$mats_ok( ! empty( $mats_r['error'] ), 'editor upload refused: evil.php.zip' );
$mats_ok( ! isset( get_allowed_mime_types()['svg'] ), 'editor may not upload .svg' );
wp_set_current_user( $mats_admin->ID );
$mats_chk = wp_check_filetype_and_ext( __FILE__, 'photo.php.jpg' );
$mats_ok( false === $mats_chk['ext'] && false === $mats_chk['type'], 'wp_check_filetype_and_ext refuses photo.php.jpg' );

/* auto-updates */
foreach ( array( 'polylang/polylang.php', 'wpforms-lite/wpforms.php', 'wp-mail-smtp/wp_mail_smtp.php' ) as $mats_file ) {
	$mats_item = (object) array( 'slug' => '', 'plugin' => $mats_file );
	$mats_ok( true === apply_filters( 'auto_update_plugin', false, $mats_item ), "auto-update on: $mats_file" );
	$mats_item = (object) array( 'slug' => dirname( $mats_file ), 'plugin' => $mats_file );
	$mats_ok( true === apply_filters( 'auto_update_plugin', false, $mats_item ), "auto-update on (with slug): $mats_file" );
}
$mats_ok( false === apply_filters( 'auto_update_plugin', false, (object) array( 'slug' => 'hello-dolly', 'plugin' => 'hello-dolly/hello.php' ) ), 'other plugins keep the admin choice' );
$mats_ok( (bool) apply_filters( 'allow_minor_auto_core_updates', false ), 'minor core auto-updates on' );
$mats_ok( ! wp_is_application_passwords_available(), 'application passwords off' );

/* sitemaps */
$mats_server = wp_sitemaps_get_server();
$mats_providers = array_keys( $mats_server->registry->get_providers() );
$mats_ok( ! in_array( 'users', $mats_providers, true ), 'no users sitemap provider (' . implode( ',', $mats_providers ) . ')' );
$mats_posts = $mats_server->registry->get_provider( 'posts' );
$mats_types = $mats_posts ? array_keys( $mats_posts->get_object_subtypes() ) : array();
sort( $mats_types );
$mats_ok( array( 'page', 'post' ) === $mats_types, 'sitemap post types: ' . implode( ',', $mats_types ) );
$mats_tax = $mats_server->registry->get_provider( 'taxonomies' );
$mats_taxes = $mats_tax ? array_keys( $mats_tax->get_object_subtypes() ) : array();
$mats_ok( array( 'category' ) === $mats_taxes, 'sitemap taxonomies: ' . implode( ',', $mats_taxes ) );

echo $mats_fail ? "\nFAILED: " . count( $mats_fail ) . "\n" : "\nALL CHECKS PASSED\n";
