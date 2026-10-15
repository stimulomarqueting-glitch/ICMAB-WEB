<?php
/**
 * QA: the REAL download → install → activate path of setup step 1
 * (plugins_api + Plugin_Upgrader + activate_plugin), offline.
 *
 * wordpress.org is unreachable here, so plugins_api() is answered through
 * its own 'plugins_api' filter with a local zip of a dummy plugin laid out
 * like WP Mail SMTP (wp-mail-smtp/wp_mail_smtp.php). Plugin_Upgrader
 * installs local packages exactly like downloaded ones. The dummy is
 * deactivated and deleted at the end (the plugins folder is shared by all
 * local instances).
 *
 *   MATS_WP_PORT=8913 php /home/user/wpsite-cli.php /home/user/ICMAB-WEB/wordpress/tools/qa/installer-plugin-install.php
 *
 * @package matsurfer-qa
 */

$mats_dir = WP_PLUGIN_DIR . '/wp-mail-smtp';
if ( file_exists( $mats_dir ) ) {
	echo "SKIP: a real wp-mail-smtp folder exists; not touching it.\n";
	exit( 0 );
}

/* the dummy package */
$mats_zip = wp_tempnam( 'mats-qa-smtp' ) . '.zip';
$mats_z   = new ZipArchive();
$mats_z->open( $mats_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE );
$mats_z->addFromString( 'wp-mail-smtp/wp_mail_smtp.php', "<?php\n/**\n * Plugin Name: WP Mail SMTP (MatSurfer QA dummy, deleted by the test)\n * Version: 0.0.1\n */\n" );
$mats_z->close();

add_filter(
	'plugins_api',
	static function ( $res, $action, $args ) use ( $mats_zip ) {
		if ( 'plugin_information' === $action && isset( $args->slug ) && 'wp-mail-smtp' === $args->slug ) {
			return (object) array(
				'name'          => 'WP Mail SMTP',
				'slug'          => 'wp-mail-smtp',
				'version'       => '0.0.1',
				'download_link' => $mats_zip,
			);
		}
		return $res;
	},
	10,
	3
);

$mats_fail  = 0;
$mats_check = static function ( bool $ok, string $what ) use ( &$mats_fail ) {
	echo ( $ok ? '  ok   ' : '  FAIL ' ), $what, "\n";
	$mats_fail += $ok ? 0 : 1;
};

$mats_r = mats_installer_run_step( 'plugins', array( 'only' => 'smtp' ) );
foreach ( $mats_r['messages'] as $mats_m ) {
	echo '  [', $mats_m['type'], '] ', $mats_m['text'], "\n";
}
$mats_check( 'installed' === ( $mats_r['data']['smtp'] ?? '' ), 'result says installed: ' . wp_json_encode( $mats_r['data'] ) );
$mats_check( is_plugin_active( 'wp-mail-smtp/wp_mail_smtp.php' ), 'plugin file present and active' );
$mats_check( ! empty( $mats_r['reload'] ), 'result asks for a reload (plugin loads on the next request)' );
$mats_check( 'active' === mats_installer_plugin_state( 'smtp' )['state'], 'step status sees it active' );

$GLOBALS['mats_installer_activated'] = array();
$mats_r2 = mats_installer_run_step( 'plugins', array( 'only' => 'smtp' ) );
$mats_check( 'already-active' === ( $mats_r2['data']['smtp'] ?? '' ), 'second run: already active, nothing reinstalled' );

/* deactivated → the "installed but inactive" path */
deactivate_plugins( 'wp-mail-smtp/wp_mail_smtp.php' );
$mats_r3 = mats_installer_run_step( 'plugins', array( 'only' => 'smtp' ) );
$mats_check( 'activated' === ( $mats_r3['data']['smtp'] ?? '' ), 'inactive plugin is just activated: ' . wp_json_encode( $mats_r3['data'] ) );

/* cleanup */
deactivate_plugins( 'wp-mail-smtp/wp_mail_smtp.php' );
$mats_del = delete_plugins( array( 'wp-mail-smtp/wp_mail_smtp.php' ) );
wp_delete_file( $mats_zip );
$mats_check( true === $mats_del && ! file_exists( $mats_dir ), 'dummy plugin deleted again' );
echo $mats_fail ? "FAIL\n" : "PASS\n";
exit( $mats_fail ? 1 : 0 );
