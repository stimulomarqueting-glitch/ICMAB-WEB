<?php
/**
 * QA (module "cptadmin"): unit checks of the sanitisers, the save handler's
 * guards (nonce, capability, autosave) and the configuration contract.
 *   MATS_WP_PORT=8918 php /home/user/wpsite-cli.php wordpress/tools/qa/cptadmin-unit.php
 * Prints "PASS …" / "FAIL …" lines and a summary; exit code 1 on failure.
 */

$fails = 0;
$check = static function ( string $name, $got, $want ) use ( &$fails ) {
	$ok = $got === $want;
	if ( ! $ok ) {
		++$fails;
	}
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $name . ( $ok ? '' : ' → got ' . var_export( $got, true ) . ', want ' . var_export( $want, true ) ) . "\n";
};

/* ── sanitisers ─────────────────────────────────────────────────────── */
$check( 'text strips tags', mats_cpta_sanitize( 'text', ' <b>Investigadora</b> <script>x</script> ' ), 'Investigadora' );
$check( 'text keeps R&D', mats_cpta_sanitize( 'text', 'R&D · OLEDs' ), 'R&D · OLEDs' );
$check( 'textarea keeps line breaks', mats_cpta_sanitize( 'textarea', "Línea 1\nLínea 2 <i>x</i>" ), "Línea 1\nLínea 2 x" );
$check( 'url adds https', mats_cpta_sanitize( 'url', 'www.linkedin.com/in/ana' ), 'https://www.linkedin.com/in/ana' );
$check( 'url keeps https', mats_cpta_sanitize( 'url', 'https://doi.org/10.1/abc' ), 'https://doi.org/10.1/abc' );
$check( 'url rejects javascript:', mats_cpta_sanitize( 'url', 'javascript:alert(1)' ), null );
$check( 'url rejects garbage', mats_cpta_sanitize( 'url', 'no es una web' ), null );
$check( 'url empty', mats_cpta_sanitize( 'url', '  ' ), '' );
$check( 'year ok', mats_cpta_sanitize( 'year', ' 2025 ' ), '2025' );
$check( 'year rejects 99', mats_cpta_sanitize( 'year', '99' ), null );
$check( 'year rejects 3000', mats_cpta_sanitize( 'year', '3000' ), null );
$check( 'year rejects text', mats_cpta_sanitize( 'year', '2025abc' ), null );
$check( 'year empty', mats_cpta_sanitize( 'year', '' ), '' );
$check( 'month ok', mats_cpta_sanitize( 'month', '2026-09' ), '2026-09' );
$check( 'month pads', mats_cpta_sanitize( 'month', '2026-9' ), '2026-09' );
$check( 'month MM/YYYY', mats_cpta_sanitize( 'month', '09/2026' ), '2026-09' );
$check( 'month rejects 13', mats_cpta_sanitize( 'month', '2026-13' ), null );
$check( 'month rejects date', mats_cpta_sanitize( 'month', '2026-09-01' ), null );
$check( 'month rejects text', mats_cpta_sanitize( 'month', 'septiembre' ), null );
$check( 'doi strips link', mats_cpta_sanitize( 'doi', 'https://doi.org/10.1021/acsami.4c19421' ), '10.1021/acsami.4c19421' );
$check( 'doi strips doi:', mats_cpta_sanitize( 'doi', 'doi: 10.1016/j.isci.2022.105686' ), '10.1016/j.isci.2022.105686' );
$check( 'slug', mats_cpta_sanitize( 'slug', 'Electrónica Orgánica!' ), 'electronica-organica' );
$check( 'image rejects non-image', mats_cpta_sanitize( 'image', '999999' ), null );
$check( 'image empty', mats_cpta_sanitize( 'image', '0' ), '' );
$check( 'array rejected', mats_cpta_sanitize( 'text', array( 'x' ) ), null );
$img = (int) get_posts( array( 'post_type' => 'attachment', 'post_mime_type' => 'image', 'post_status' => 'inherit', 'posts_per_page' => 1, 'fields' => 'ids' ) )[0];
$check( 'image accepts attachment', mats_cpta_sanitize( 'image', (string) $img ), (string) $img );
$check( 'check ticked', mats_cpta_sanitize( 'check', '1' ), '1' );
$check( 'check unticked (hidden 0)', mats_cpta_sanitize( 'check', '0' ), '0' );
$check( 'check other value = no', mats_cpta_sanitize( 'check', 'yes' ), '0' );
$check( 'check array rejected', mats_cpta_sanitize( 'check', array( '1' ) ), null );

/* ── contract: every field of mats_cpt_fields() has a definition ────── */
foreach ( mats_cpt_fields() as $type => $fields ) {
	foreach ( array_keys( $fields ) as $field ) {
		$check( "config $type.$field", isset( mats_cpta_config( $type )['fields'][ $field ] ), true );
	}
}

/* ── save guards ────────────────────────────────────────────────────── */
$id = wp_insert_post(
	array(
		'post_type'   => 'mats_team',
		'post_title'  => 'Unit Test',
		'post_status' => 'draft',
	)
);
update_post_meta( $id, '_mats_role_en', 'Original' );
$post = get_post( $id );

/* no nonce → untouched */
$_POST = array( 'mats_cpta' => array( 'role_en' => 'Hacked' ) );
mats_cpta_save( $id, $post );
$check( 'no nonce: unchanged', get_post_meta( $id, '_mats_role_en', true ), 'Original' );

/* nonce of another post → untouched */
$_POST = array(
	'mats_cpta_nonce' => wp_create_nonce( 'mats_cpta_save_' . ( $id + 1 ) ),
	'mats_cpta'       => array( 'role_en' => 'Hacked' ),
);
mats_cpta_save( $id, $post );
$check( 'wrong nonce: unchanged', get_post_meta( $id, '_mats_role_en', true ), 'Original' );

/* subscriber with a valid nonce → untouched */
$sub = wp_insert_user(
	array(
		'user_login' => 'cpta-sub-' . wp_rand( 1000, 9999 ),
		'user_pass'  => wp_generate_password(),
		'role'       => 'subscriber',
	)
);
wp_set_current_user( $sub );
$_POST = array(
	'mats_cpta_nonce' => wp_create_nonce( 'mats_cpta_save_' . $id ),
	'mats_cpta'       => array( 'role_en' => 'Hacked' ),
);
mats_cpta_save( $id, $post );
$check( 'subscriber: unchanged', get_post_meta( $id, '_mats_role_en', true ), 'Original' );
wp_set_current_user( 1 );
wp_delete_user( $sub );

/* valid request → sanitised, slashes kept */
$_POST = array(
	'mats_cpta_nonce' => wp_create_nonce( 'mats_cpta_save_' . $id ),
	'mats_cpta'       => wp_slash(
		array(
			'role_en'        => ' <em>Química</em> \\ test ',
			'role_es'        => 'Química',
			'bio_en'         => "A\nB",
			'linkedin'       => 'linkedin.com/in/x',
			'website'        => 'javascript:alert(1)',
			'showcase_image' => '999999',
		)
	),
);
update_post_meta( $id, '_mats_website', 'https://antes.example.org' );
mats_cpta_save( $id, $post );
$check( 'valid: text sanitised (backslash kept)', get_post_meta( $id, '_mats_role_en', true ), 'Química \\ test' );
$check( 'valid: textarea', get_post_meta( $id, '_mats_bio_en', true ), "A\nB" );
$check( 'valid: url fixed', get_post_meta( $id, '_mats_linkedin', true ), 'https://linkedin.com/in/x' );
$check( 'valid: bad url keeps old value', get_post_meta( $id, '_mats_website', true ), 'https://antes.example.org' );
$notes = get_transient( 'mats_cpta_notes_1' );
$check( 'valid: notes for bad url + image', is_array( $notes ) && 2 === count( $notes[ $id ] ?? array() ), true );
delete_transient( 'mats_cpta_notes_1' );

/* application «Enlace a Casos de estudio»: default by identifier until saved */
$app = wp_insert_post(
	array(
		'post_type'   => 'mats_application',
		'post_title'  => 'Unit Test App',
		'post_status' => 'draft',
	)
);
update_post_meta( $app, '_mats_slug', 'sensors' );
$check( 'case study: never saved, sensors → on', mats_application_case_study( $app ), true );
update_post_meta( $app, '_mats_slug', 'coatings' );
$check( 'case study: never saved, coatings → off', mats_application_case_study( $app ), false );
$_POST = array(
	'mats_cpta_nonce' => wp_create_nonce( 'mats_cpta_save_' . $app ),
	'mats_cpta'       => array( 'case_study' => '1' ),
);
mats_cpta_save( $app, get_post( $app ) );
$check( 'case study: ticked box saved', get_post_meta( $app, '_mats_case_study', true ), '1' );
$check( 'case study: saved yes wins over the default', mats_application_case_study( $app ), true );
update_post_meta( $app, '_mats_slug', 'sensors' );
$_POST['mats_cpta'] = array( 'case_study' => '0' );
mats_cpta_save( $app, get_post( $app ) );
$check( 'case study: unticked box saved', get_post_meta( $app, '_mats_case_study', true ), '0' );
$check( 'case study: saved no wins over the default', mats_application_case_study( $app ), false );
$_POST['mats_cpta'] = array( 'tag_en' => 'x' );
mats_cpta_save( $app, get_post( $app ) );
$check( 'case study: not in the form → untouched', get_post_meta( $app, '_mats_case_study', true ), '0' );
wp_delete_post( $app, true );

/* autosave → untouched */
define( 'DOING_AUTOSAVE', true );
$_POST['mats_cpta'] = array( 'role_en' => 'Autosave' );
mats_cpta_save( $id, $post );
$check( 'autosave: unchanged', get_post_meta( $id, '_mats_role_en', true ), 'Química \\ test' );

/* password never kept */
wp_update_post(
	array(
		'ID'            => $id,
		'post_password' => 'secreto',
	)
);
$check( 'no password on records', get_post_field( 'post_password', $id ), '' );

wp_delete_post( $id, true );
$_POST = array();

/* ── Polylang does not translate these types ────────────────────────── */
if ( function_exists( 'pll_is_translated_post_type' ) ) {
	foreach ( array_keys( mats_cpt_fields() ) as $type ) {
		$check( "polylang ignores $type", pll_is_translated_post_type( $type ), false );
	}
}

echo ( $fails ? "\n$fails FAILED\n" : "\nALL PASSED\n" );
exit( $fails ? 1 : 0 );
