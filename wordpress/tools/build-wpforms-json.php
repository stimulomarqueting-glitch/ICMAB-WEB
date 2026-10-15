<?php
/**
 * Writes the two contact forms as WPForms import files (WPForms → Tools →
 * Import accepts a JSON array of form objects):
 *   matsurfer/inc/data/wpforms-en.json
 *   matsurfer/inc/data/wpforms-es.json
 * They are the manual fallback of mats_wpforms_create_forms(): same
 * definition (inc/modules/wpforms.php → mats_wpforms_form_data()), with the
 * default contact address and the consent sentence unlinked (the theme adds
 * the privacy link when the form is shown, once the privacy page exists).
 *
 * Run inside a local WordPress with the theme active (after changing the
 * form definition or content.json):
 *   MATS_WP_PORT=<port> php /home/user/wpsite-cli.php wordpress/tools/build-wpforms-json.php
 */

if ( ! function_exists( 'mats_wpforms_form_data' ) ) {
	fwrite( STDERR, "Run this through wpsite-cli.php with the MatSurfer theme active.\n" );
	exit( 1 );
}

$defaults = mats_settings_defaults();
$email    = sanitize_email( (string) ( $defaults['global']['contact_email'] ?? '' ) );
$dir      = MATS_DIR . '/inc/data';

foreach ( mats_langs() as $lang ) {
	$form = mats_wpforms_form_data(
		$lang,
		array(
			'email'       => $email,
			'privacy_url' => '',
		)
	);
	unset( $form['id'] ); // WPForms gives the imported form a new ID
	$json = wp_json_encode( array( $form ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	$file = $dir . '/wpforms-' . $lang . '.json';
	file_put_contents( $file, $json . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	printf( "%s  %d fields, %d bytes\n", $file, count( $form['fields'] ), strlen( $json ) );
}
