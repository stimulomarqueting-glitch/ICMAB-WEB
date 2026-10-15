<?php
/**
 * QA: builds a BRAND-NEW WordPress database for one local instance, exactly
 * like a fresh install on Hostinger (wp_install(): "Hello world!", "Sample
 * Page", privacy draft, sample comment, default theme, no plugin active).
 * Polylang stays installed-but-inactive in the shared plugins folder and
 * WPForms Lite is not installed, so the wizard's two plugin paths are tested.
 *
 *   MATS_WP_PORT=8913 php /home/user/ICMAB-WEB/wordpress/tools/qa/installer-fresh.php [--title="My WordPress Website"]
 *   then: /home/user/wp-serve.sh 8913
 *
 * NOT run through wpsite-cli.php (WordPress is not installed yet).
 * Admin login afterwards: admin / admin-local-pass.
 *
 * @package matsurfer-qa
 */

$mats_port = preg_replace( '/[^0-9]/', '', (string) getenv( 'MATS_WP_PORT' ) );
if ( ! $mats_port || in_array( $mats_port, array( '8881' ), true ) ) {
	fwrite( STDERR, "Set MATS_WP_PORT to YOUR instance port (never the shared 8881).\n" );
	exit( 2 );
}
$mats_title = 'My WordPress Website';
foreach ( array_slice( $argv, 1 ) as $mats_arg ) {
	if ( str_starts_with( $mats_arg, '--title=' ) ) {
		$mats_title = substr( $mats_arg, 8 );
	}
}

$mats_db = '/home/user/wpsite-data/site-' . $mats_port . '.sqlite';
if ( file_exists( $mats_db ) ) {
	unlink( $mats_db );
}
@unlink( '/home/user/wpsite-data/debug-' . $mats_port . '.log' ); // phpcs:ignore

$_SERVER['HTTP_HOST']   = 'localhost:' . $mats_port;
$_SERVER['SERVER_PORT'] = $mats_port;
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
define( 'WP_INSTALLING', true );
require '/home/user/wpsite/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

add_filter( 'pre_wp_mail', '__return_true' ); // no "your new site" email
add_filter( 'pre_http_request', static fn( $pre, $args, $url ) => str_contains( $url, 'localhost' ) ? new WP_Error( 'qa', 'no loopback during install' ) : $pre, 10, 3 );

$mats_result = wp_install( $mats_title, 'admin', 'admin@example.test', true, '', 'admin-local-pass' );

/* This sandbox blocks wordpress.org (WP_HTTP_BLOCK_EXTERNAL): mark core's
   update checks as just done, otherwise WordPress itself logs a warning on
   every check and the debug log of the wizard test is not clean. Real
   hosts have internet; nothing of this is needed there. */
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/theme.php';
$mats_now     = time();
$mats_plugins = array_map( static fn( $p ) => $p['Version'], get_plugins() );
$mats_themes  = array();
foreach ( wp_get_themes() as $mats_slug => $mats_theme ) {
	$mats_themes[ $mats_slug ] = $mats_theme->get( 'Version' );
}
/* stored as options: while WP_INSTALLING, set_site_transient() only caches in memory */
update_option( '_site_transient_update_core', (object) array( 'updates' => array(), 'version_checked' => get_bloginfo( 'version' ), 'last_checked' => $mats_now, 'translations' => array() ), false );
update_option( '_site_transient_update_plugins', (object) array( 'last_checked' => $mats_now, 'checked' => $mats_plugins, 'response' => array(), 'no_update' => array(), 'translations' => array() ), false );
update_option( '_site_transient_update_themes', (object) array( 'last_checked' => $mats_now, 'checked' => $mats_themes, 'response' => array(), 'no_update' => array(), 'translations' => array() ), false );

echo 'Fresh WordPress ', get_bloginfo( 'version' ), ' installed in ', $mats_db, "\n";
echo '  title: ', get_option( 'blogname' ), ' | theme: ', get_stylesheet(), ' | plugins active: ', wp_json_encode( get_option( 'active_plugins' ) ), "\n";
echo '  permalinks: ', wp_json_encode( get_option( 'permalink_structure' ) ), ' | front: ', get_option( 'show_on_front' ), "\n";
foreach ( get_posts( array( 'post_type' => array( 'post', 'page' ), 'post_status' => 'any', 'numberposts' => -1 ) ) as $mats_p ) {
	echo '  ', $mats_p->ID, ' ', $mats_p->post_type, ' ', $mats_p->post_status, ' ', $mats_p->post_name, "\n";
}
