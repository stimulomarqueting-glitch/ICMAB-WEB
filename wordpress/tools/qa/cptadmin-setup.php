<?php
/**
 * QA (module "cptadmin"): prepares an instance for the Equipo /
 * Aplicaciones / Publicaciones / Valoraciones admin screens.
 *   - an Editor user: editora / editora-local-pass
 *   - EN/ES pages for the roles applications, team, research (+ contact)
 *     built from the real sections (mats_seed_block), linked as
 *     translations and registered in mats_page_roles
 *   - clears Polylang's cached language list (home URLs of the golden DB)
 * Run: MATS_WP_PORT=8918 php /home/user/wpsite-cli.php wordpress/tools/qa/cptadmin-setup.php
 * Prints JSON { users, pages: { role: { en: url, es: url } } }.
 */

delete_transient( 'pll_languages_list' );
if ( function_exists( 'PLL' ) && isset( PLL()->model ) && method_exists( PLL()->model, 'clean_languages_cache' ) ) {
	PLL()->model->clean_languages_cache();
}

/* editor user */
$user = get_user_by( 'login', 'editora' );
if ( ! $user ) {
	$uid = wp_insert_user(
		array(
			'user_login' => 'editora',
			'user_pass'  => 'editora-local-pass',
			'user_email' => 'editora@example.org',
			'role'       => 'editor',
			'first_name' => 'Editora',
		)
	);
} else {
	$uid = $user->ID;
	wp_set_password( 'editora-local-pass', $uid );
	$user->set_role( 'editor' );
}

$layout = array(
	'applications' => array( 'page-hero', 'applications-list', 'broader', 'page-cta' ),
	'team'         => array( 'team-showcase', 'team-journey', 'page-cta' ),
	'research'     => array( 'page-hero', 'research-experiences', 'research-publications', 'research-validation', 'invite', 'page-cta' ),
	'contact'      => array(),
);
$slugs  = array(
	'applications' => array( 'en' => 'applications', 'es' => 'aplicaciones' ),
	'team'         => array( 'en' => 'team', 'es' => 'equipo' ),
	'research'     => array( 'en' => 'research', 'es' => 'investigacion' ),
	'contact'      => array( 'en' => 'contact', 'es' => 'contacto' ),
);

foreach ( get_posts( array( 'post_type' => 'page', 'numberposts' => -1, 'post_status' => 'any', 'lang' => '', 'meta_key' => '_mats_qa', 'meta_value' => 'cptadmin' ) ) as $old ) {
	wp_delete_post( $old->ID, true );
}

$registry = WP_Block_Type_Registry::get_instance();
$roles    = get_option( 'mats_page_roles', array() );
$roles    = is_array( $roles ) ? $roles : array();
$out      = array( 'editor' => (int) $uid );

foreach ( $layout as $role => $blocks ) {
	$ids = array();
	foreach ( array( 'en', 'es' ) as $lang ) {
		$content = '';
		foreach ( $blocks as $block ) {
			if ( $registry->get_registered( 'matsurfer/' . $block ) && is_readable( MATS_DIR . '/blocks/' . $block . '/render.php' ) ) {
				$content .= mats_seed_block( $block, $role, $lang ) . "\n\n";
			}
		}
		if ( '' === $content ) {
			$content = '<!-- wp:paragraph --><p>QA placeholder.</p><!-- /wp:paragraph -->';
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => ucfirst( $role ) . ' (' . $lang . ')',
				'post_name'    => $slugs[ $role ][ $lang ],
				'post_content' => wp_slash( $content ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			fwrite( STDERR, $id->get_error_message() . "\n" );
			continue;
		}
		update_post_meta( $id, '_mats_qa', 'cptadmin' );
		update_post_meta( $id, '_mats_role', $role );
		pll_set_post_language( $id, $lang );
		$ids[ $lang ] = (int) $id;
	}
	pll_save_post_translations( $ids );
	$roles[ $role ] = $ids['en'];
}
update_option( 'mats_page_roles', $roles );
flush_rewrite_rules( false );

foreach ( array_keys( $layout ) as $role ) {
	foreach ( array( 'en', 'es' ) as $lang ) {
		$out['pages'][ $role ][ $lang ] = mats_page_url( $role, $lang );
	}
}
echo wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), "\n";
