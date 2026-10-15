<?php
/**
 * MatSurfer theme bootstrap.
 *
 * Core helpers load first, in a fixed order; every feature module in
 * inc/modules/ is self-contained (hooks only) and loads alphabetically.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

define( 'MATS_VERSION', '1.0.0' );
define( 'MATS_DIR', get_template_directory() );
define( 'MATS_URI', get_template_directory_uri() );

/* PHP 8.1+ is required (Hostinger default is 8.2+). Fail soft, never fatal. */
if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>El tema MatSurfer necesita PHP 8.1 o superior. Cámbialo en el panel de Hostinger (Avanzado → Configuración de PHP).</p></div>';
		}
	);
	return;
}

foreach ( array( 'i18n', 'settings-api', 'links', 'helpers', 'content', 'blocks', 'assets', 'setup' ) as $mats_core ) {
	require_once MATS_DIR . '/inc/' . $mats_core . '.php';
}

$mats_modules = glob( MATS_DIR . '/inc/modules/*.php' ) ?: array();
sort( $mats_modules );
foreach ( $mats_modules as $mats_module ) {
	require_once $mats_module;
}
unset( $mats_core, $mats_modules, $mats_module );
