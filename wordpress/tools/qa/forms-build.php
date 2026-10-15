<?php
/**
 * QA (module "forms"): creates the WPForms contact forms and EN/ES test
 * pages for the contact section (+ privacy pages so the consent link can be
 * checked). Needs WPForms or the test stub (tools/qa/wpforms-stub) active.
 *
 * Run: MATS_WP_PORT=8911 php /home/user/wpsite-cli.php wordpress/tools/qa/forms-build.php [--no-privacy] [--overwrite]
 * Prints JSON { report, pages: { role: { en: {id,url}, es: {id,url} } } }.
 */

$args      = array_slice( $argv, 2 );
$privacy   = ! in_array( '--no-privacy', $args, true );
$overwrite = in_array( '--overwrite', $args, true );

/* start over: earlier QA pages of this module */
foreach ( get_posts( array( 'post_type' => 'page', 'numberposts' => -1, 'post_status' => 'any', 'lang' => '', 'meta_key' => '_mats_qa', 'meta_value' => 'forms' ) ) as $old ) {
	wp_delete_post( $old->ID, true );
}

$slugs = array(
	'contact' => array( 'en' => 'contact', 'es' => 'contacto' ),
	'privacy' => array( 'en' => 'privacy-policy-qa', 'es' => 'politica-de-privacidad-qa' ),
);
$roles = get_option( 'mats_page_roles', array() );
$roles = is_array( $roles ) ? $roles : array();
$out   = array();

foreach ( array( 'contact', 'privacy' ) as $role ) {
	if ( 'privacy' === $role && ! $privacy ) {
		unset( $roles['privacy'] );
		continue;
	}
	$ids = array();
	foreach ( array( 'en', 'es' ) as $lang ) {
		$content = 'contact' === $role
			? mats_seed_block( 'contact', 'contact', $lang )
			: '<!-- wp:paragraph --><p>QA privacy page (' . $lang . ').</p><!-- /wp:paragraph -->';
		$id      = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => ( 'contact' === $role ? ( 'es' === $lang ? 'Contacto' : 'Contact' ) : ( 'es' === $lang ? 'Política de privacidad' : 'Privacy Policy' ) ),
				'post_name'    => $slugs[ $role ][ $lang ],
				'post_content' => wp_slash( $content ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			fwrite( STDERR, $id->get_error_message() . "\n" );
			continue;
		}
		update_post_meta( $id, '_mats_qa', 'forms' );
		update_post_meta( $id, '_mats_role', $role );
		pll_set_post_language( $id, $lang );
		$ids[ $lang ] = $id;
	}
	pll_save_post_translations( $ids );
	$roles[ $role ] = $ids['en'];
	foreach ( $ids as $lang => $id ) {
		$out[ $role ][ $lang ] = array(
			'id'  => $id,
			'url' => get_permalink( $id ),
		);
	}
}
update_option( 'mats_page_roles', $roles );
flush_rewrite_rules( false );

$report = mats_wpforms_create_forms( $overwrite );
echo wp_json_encode(
	array(
		'report' => $report,
		'pages'  => $out,
	),
	JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
), "\n";
