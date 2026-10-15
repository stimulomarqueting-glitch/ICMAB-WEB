<?php
/**
 * QA (module "forms", fix pass): saving the forms never loses their HTML.
 *
 *  1. an Editor (no unfiltered_html) changes the contact email in the
 *     settings → notification address follows, thank-you <h2> and the
 *     consent link stay, kses / balanceTags are back afterwards
 *  2. first save of the settings (add_option_mats_settings, option missing)
 *     → the forms (found by their _mats_form_lang mark) follow too
 *  3. an administrator without unfiltered_html (DISALLOW_UNFILTERED_HTML)
 *     rewrites the forms → written directly, HTML intact
 *  4. a WPForms handler that loses HTML while saving → detected, rewritten
 *     directly
 *  5. normal administrator overwrite → WPForms' handler path, HTML intact,
 *     same IDs
 * Restores the forms and the settings at the end.
 *
 * Run: MATS_WP_PORT=8911 php /home/user/wpsite-cli.php wordpress/tools/qa/forms-fixpass.php
 */

$ok   = true;
$fail = static function ( string $what ) use ( &$ok ) {
	$ok = false;
	echo "  FAIL: $what\n";
};
$form_state = static function ( int $id ): array {
	clean_post_cache( $id );
	$d = json_decode( (string) get_post_field( 'post_content', $id, 'raw' ), true );
	return array(
		'email'   => (string) ( $d['settings']['notifications'][1]['email'] ?? '' ),
		'conf'    => (string) ( $d['settings']['confirmations'][1]['message'] ?? '' ),
		'consent' => (string) ( $d['fields'][8]['choices'][1]['label'] ?? '' ),
	);
};
$html_ok = static function ( array $st ): bool {
	return false !== strpos( $st['conf'], '<h2>' ) && false !== strpos( $st['conf'], '<p>' ) && false !== strpos( $st['consent'], '<a href=' );
};

$ids = array(
	'en' => mats_wpforms_form_id( 'en' ),
	'es' => mats_wpforms_form_id( 'es' ),
);
if ( ! $ids['en'] || ! $ids['es'] ) {
	echo "The two forms must exist first (mats_wpforms_create_forms()).\n";
	return;
}
$backup_settings = get_option( 'mats_settings' );
$backup_forms    = array();
foreach ( $ids as $id ) {
	$backup_forms[ $id ] = get_post_field( 'post_content', $id, 'raw' );
}
$admin = get_user_by( 'login', 'admin' );

/* 1 — Editor */
echo "1. Editor changes the contact email\n";
$editor = get_user_by( 'login', 'qa-editor' );
if ( ! $editor ) {
	$editor = get_user_by( 'id', wp_insert_user( array( 'user_login' => 'qa-editor', 'user_pass' => 'qa-editor-pass', 'user_email' => 'qa-editor@example.org', 'role' => 'editor' ) ) );
}
wp_set_current_user( $editor->ID );
printf( "  unfiltered_html=%s\n", var_export( current_user_can( 'unfiltered_html' ), true ) );
$kses_before    = has_filter( 'content_save_pre', 'wp_filter_post_kses' );
$balance_before = has_filter( 'content_save_pre', 'balanceTags' );
$s              = get_option( 'mats_settings', array() );
$s['global']['contact_email'] = 'leads-editor@example.org';
update_option( 'mats_settings', $s );
foreach ( $ids as $lang => $id ) {
	$st = $form_state( $id );
	printf( "  %s #%d email=%s html=%s\n", $lang, $id, $st['email'], $html_ok( $st ) ? 'kept' : 'LOST' );
	'leads-editor@example.org' === $st['email'] || $fail( "$lang notification address not synced" );
	$html_ok( $st ) || $fail( "$lang HTML lost" );
}
has_filter( 'content_save_pre', 'wp_filter_post_kses' ) === $kses_before || $fail( 'kses filter not restored' );
has_filter( 'content_save_pre', 'balanceTags' ) === $balance_before || $fail( 'balanceTags filter not restored' );
printf( "  filters after: kses=%s balanceTags=%s\n", var_export( has_filter( 'content_save_pre', 'wp_filter_post_kses' ), true ), var_export( has_filter( 'content_save_pre', 'balanceTags' ), true ) );

/* back to the default address for the next checks */
wp_set_current_user( $admin->ID );
$s['global']['contact_email'] = '';
update_option( 'mats_settings', $s );
foreach ( $ids as $lang => $id ) {
	$st = $form_state( $id );
	'matsurfer@icmab.es' === $st['email'] || $fail( "$lang not back to the default address ({$st['email']})" );
}

/* 2 — first save of the settings */
echo "2. Settings saved for the first time (add_option_mats_settings)\n";
delete_option( 'mats_settings' );
wp_set_current_user( $editor->ID );
update_option( 'mats_settings', array( 'global' => array( 'contact_email' => 'first-save@example.org' ) ) );
foreach ( $ids as $lang => $id ) {
	$st = $form_state( $id );
	printf( "  %s #%d email=%s html=%s\n", $lang, $id, $st['email'], $html_ok( $st ) ? 'kept' : 'LOST' );
	'first-save@example.org' === $st['email'] || $fail( "$lang not synced on add_option" );
	$html_ok( $st ) || $fail( "$lang HTML lost on add_option" );
}
update_option( 'mats_settings', $backup_settings );
foreach ( $ids as $id ) {
	wp_set_current_user( $admin->ID );
	mats_wpforms_write_post( $id, json_decode( $backup_forms[ $id ], true ), (string) get_post_field( 'post_title', $id, 'raw' ) );
}

/* 3 — administrator without unfiltered_html */
echo "3. Administrator without unfiltered_html rewrites the forms\n";
wp_set_current_user( $admin->ID );
$deny = static function ( $caps, $cap ) {
	return 'unfiltered_html' === $cap ? array( 'do_not_allow' ) : $caps;
};
add_filter( 'map_meta_cap', $deny, 99, 2 );
printf( "  unfiltered_html=%s\n", var_export( current_user_can( 'unfiltered_html' ), true ) );
$report = mats_wpforms_create_forms( true );
remove_filter( 'map_meta_cap', $deny, 99 );
foreach ( $ids as $lang => $id ) {
	$st = $form_state( $id );
	printf( "  %s: %s html=%s\n", $lang, $report['forms'][ $lang ]['message'] ?? '?', $html_ok( $st ) ? 'kept' : 'LOST' );
	( (int) ( $report['forms'][ $lang ]['id'] ?? 0 ) === $id ) || $fail( "$lang ID changed" );
	$html_ok( $st ) || $fail( "$lang HTML lost (no unfiltered_html)" );
}

/* 4 — handler that loses HTML */
echo "4. WPForms handler loses HTML while saving\n";
$lossy = static function ( $form ) {
	$form['post_content'] = str_replace( array( '<h2>', '<\/h2>' ), '', (string) $form['post_content'] );
	return $form;
};
add_filter( 'wpforms_save_form_args', $lossy, 10, 1 );
$hits = 0;
$spy  = static function ( $form ) use ( &$hits ) {
	++$hits;
	return $form;
};
add_filter( 'wpforms_save_form_args', $spy, 11, 1 );
$report = mats_wpforms_create_forms( true );
remove_filter( 'wpforms_save_form_args', $lossy, 10 );
remove_filter( 'wpforms_save_form_args', $spy, 11 );
printf( "  handler used %d time(s)\n", $hits );
$hits > 0 || $fail( 'the handler path was not tried' );
foreach ( $ids as $lang => $id ) {
	$st = $form_state( $id );
	printf( "  %s: %s html=%s\n", $lang, $report['forms'][ $lang ]['status'] ?? '?', $html_ok( $st ) ? 'kept' : 'LOST' );
	$html_ok( $st ) || $fail( "$lang lossy handler not caught" );
}

/* 5 — normal administrator */
echo "5. Administrator overwrite (WPForms handler path)\n";
$hits = 0;
add_filter( 'wpforms_save_form_args', $spy, 11, 1 );
$report = mats_wpforms_create_forms( true );
remove_filter( 'wpforms_save_form_args', $spy, 11 );
printf( "  handler used %d time(s)\n", $hits );
2 === $hits || $fail( 'expected the handler to save both forms once' );
foreach ( $ids as $lang => $id ) {
	$st = $form_state( $id );
	printf( "  %s: %s html=%s\n", $lang, $report['forms'][ $lang ]['message'] ?? '?', $html_ok( $st ) ? 'kept' : 'LOST' );
	$html_ok( $st ) || $fail( "$lang HTML lost (admin)" );
}

/* restore */
update_option( 'mats_settings', $backup_settings );
foreach ( $ids as $id ) {
	mats_wpforms_write_post( $id, json_decode( $backup_forms[ $id ], true ), (string) get_post_field( 'post_title', $id, 'raw' ) );
	clean_post_cache( $id );
	get_post_field( 'post_content', $id, 'raw' ) === $backup_forms[ $id ] || $fail( "form $id not restored byte for byte" );
}
echo $ok ? "ALL OK\n" : "SOME CHECKS FAILED\n";
