<?php
/**
 * Site-wide settings storage ("Ajustes de la web"): header/footer copy,
 * contact email, form IDs, default SEO texts.
 *
 * Stored in ONE option, `mats_settings`:
 *   [ 'global' => [ key => value ], 'en' => [ key => value ], 'es' => [ … ] ]
 * Defaults come from inc/data/settings-defaults.php (generated from the
 * original site copy), so the site renders correctly before anyone saves.
 *
 * The admin screen that edits these values lives in inc/modules/settings.php.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

/** Default values: [ 'global' => [], 'en' => [], 'es' => [] ]. */
function mats_settings_defaults(): array {
	static $defaults = null;
	if ( null === $defaults ) {
		$file     = MATS_DIR . '/inc/data/settings-defaults.php';
		$defaults = is_readable( $file ) ? (array) require $file : array();
		$defaults += array(
			'global' => array(),
			'en'     => array(),
			'es'     => array(),
		);
	}
	return $defaults;
}

function mats_settings_all(): array {
	$saved    = get_option( 'mats_settings', array() );
	$saved    = is_array( $saved ) ? $saved : array();
	$defaults = mats_settings_defaults();
	$out      = array();
	foreach ( array( 'global', 'en', 'es' ) as $scope ) {
		$out[ $scope ] = array_merge( $defaults[ $scope ] ?? array(), is_array( $saved[ $scope ] ?? null ) ? $saved[ $scope ] : array() );
	}
	return $out;
}

/**
 * One setting. Looks in the language scope first (current language unless
 * given), then in the global scope. Empty strings fall back to the default.
 */
function mats_setting( string $key, string $lang = '' ) {
	$lang     = $lang ?: mats_lang();
	$all      = mats_settings_all();
	$defaults = mats_settings_defaults();
	foreach ( array( $lang, 'global' ) as $scope ) {
		if ( array_key_exists( $key, $all[ $scope ] ) ) {
			$value = $all[ $scope ][ $key ];
			if ( '' === $value && isset( $defaults[ $scope ][ $key ] ) ) {
				return $defaults[ $scope ][ $key ];
			}
			return $value;
		}
	}
	return '';
}

/** Saves one value (used by the importer and the WPForms setup). */
function mats_update_setting( string $key, $value, string $scope = 'global' ): void {
	$saved = get_option( 'mats_settings', array() );
	$saved = is_array( $saved ) ? $saved : array();
	if ( ! isset( $saved[ $scope ] ) || ! is_array( $saved[ $scope ] ) ) {
		$saved[ $scope ] = array();
	}
	$saved[ $scope ][ $key ] = $value;
	update_option( 'mats_settings', $saved, true );
}

/** The public contact address (Ajustes de la web → Email de contacto). */
function mats_contact_email(): string {
	$email = sanitize_email( (string) mats_setting( 'contact_email' ) );
	return $email ?: 'matsurfer@icmab.es';
}
