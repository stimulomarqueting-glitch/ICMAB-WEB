<?php
/**
 * QA: prints the state the setup wizard is responsible for, as JSON
 * (pages per role/language, translations, duplicates, options, languages,
 * categories, structured content, sample content, forms).
 *
 *   MATS_WP_PORT=8913 php /home/user/wpsite-cli.php /home/user/ICMAB-WEB/wordpress/tools/qa/installer-check.php
 *
 * Used by installer-verify.mjs.
 *
 * @package matsurfer-qa
 */

$mats_pll = function_exists( 'PLL' ) && PLL() && function_exists( 'pll_get_post_language' );
$mats_out = array(
	'polylang_active' => $mats_pll,
	'options'         => array(),
	'languages'       => array(),
	'pages'           => array(),
	'duplicates'      => array(),
	'roles'           => array(),
	'categories'      => array(),
	'cpts'            => array(),
	'samples'         => array(),
	'forms'           => function_exists( 'mats_wpforms_status' ) ? mats_wpforms_status() : null,
	'installer_state' => get_option( 'mats_installer_state' ),
	'attachments'     => (int) ( wp_count_posts( 'attachment' )->inherit ?? 0 ),
	'wp_languages'    => get_available_languages(),
);

foreach ( array( 'show_on_front', 'page_on_front', 'page_for_posts', 'wp_page_for_privacy_policy', 'permalink_structure', 'timezone_string', 'gmt_offset', 'date_format', 'start_of_week', 'default_comment_status', 'default_ping_status', 'blogname', 'blogdescription', 'admin_email', 'blog_public', 'mats_page_roles', 'polylang', 'default_category', 'active_plugins', 'WPLANG' ) as $mats_name ) {
	$mats_out['options'][ $mats_name ] = get_option( $mats_name );
}

if ( $mats_pll ) {
	foreach ( PLL()->model->languages->get_list() as $mats_lang ) {
		$mats_out['languages'][] = array(
			'slug'    => $mats_lang->slug,
			'name'    => $mats_lang->name,
			'locale'  => $mats_lang->locale,
			'flag'    => $mats_lang->flag_code,
			'order'   => (int) $mats_lang->term_group,
			'default' => (bool) $mats_lang->is_default,
			'home'    => $mats_lang->get_home_url(),
		);
	}
}

$mats_specs = function_exists( 'mats_installer_page_specs' ) ? mats_installer_page_specs() : array();
$mats_count = array();
foreach ( get_posts(
	array(
		'post_type'        => 'page',
		'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
		'numberposts'      => -1,
		'suppress_filters' => true,
		'lang'             => '',
		'orderby'          => 'ID',
		'order'            => 'ASC',
	)
) as $mats_p ) {
	$mats_role   = (string) get_post_meta( $mats_p->ID, '_mats_role', true );
	$mats_lang   = $mats_pll ? (string) pll_get_post_language( $mats_p->ID ) : '';
	$mats_blocks = array();
	$mats_empty  = array();
	foreach ( parse_blocks( $mats_p->post_content ) as $mats_b ) {
		if ( empty( $mats_b['blockName'] ) ) {
			continue;
		}
		$mats_blocks[] = $mats_b['blockName'];
		$mats_attrs    = (array) $mats_b['attrs'];
		unset( $mats_attrs['lock'] );
		if ( str_starts_with( $mats_b['blockName'], 'matsurfer/' ) && ! $mats_attrs ) {
			$mats_empty[] = substr( $mats_b['blockName'], 10 );
		}
		if ( str_starts_with( $mats_b['blockName'], 'matsurfer/' ) && empty( $mats_b['attrs']['lock']['remove'] ) ) {
			$mats_empty[] = 'UNLOCKED:' . substr( $mats_b['blockName'], 10 );
		}
	}
	$mats_expected = array_map( static fn( $b ) => 'matsurfer/' . $b, $mats_specs[ $mats_role ]['blocks'] ?? array() );
	$mats_out['pages'][] = array(
		'id'           => $mats_p->ID,
		'status'       => $mats_p->post_status,
		'slug'         => $mats_p->post_name,
		'title'        => $mats_p->post_title,
		'lang'         => $mats_lang,
		'role'         => $mats_role,
		'translations' => $mats_pll ? pll_get_post_translations( $mats_p->ID ) : array(),
		'url'          => get_permalink( $mats_p ),
		'blocks'       => $mats_blocks,
		'order_ok'     => $mats_role && ! empty( $mats_specs[ $mats_role ]['blocks'] ) ? $mats_blocks === $mats_expected : null,
		'empty'        => $mats_empty,
		'pending'      => get_post_meta( $mats_p->ID, '_mats_seed_pending', true ),
		'seo_title'    => get_post_meta( $mats_p->ID, '_mats_seo_title', true ),
		'seo_desc'     => get_post_meta( $mats_p->ID, '_mats_seo_description', true ),
		'excerpt'      => $mats_p->post_excerpt,
		'content'      => $mats_role && empty( $mats_specs[ $mats_role ]['blocks'] ) ? $mats_p->post_content : null,
	);
	if ( $mats_role && 'trash' !== $mats_p->post_status ) {
		$mats_key                = $mats_role . '.' . ( $mats_lang ? $mats_lang : '-' );
		$mats_count[ $mats_key ] = ( $mats_count[ $mats_key ] ?? 0 ) + 1;
		$mats_out['roles'][ $mats_role ][ $mats_lang ? $mats_lang : '-' ] = $mats_p->ID;
	}
}
$mats_out['duplicates'] = array_filter( $mats_count, static fn( $n ) => $n > 1 );

foreach ( get_terms(
	array(
		'taxonomy'   => 'category',
		'hide_empty' => false,
		'lang'       => '',
	)
) as $mats_t ) {
	$mats_out['categories'][] = array(
		'id'           => $mats_t->term_id,
		'name'         => $mats_t->name,
		'slug'         => $mats_t->slug,
		'lang'         => $mats_pll ? pll_get_term_language( $mats_t->term_id ) : '',
		'translations' => $mats_pll ? pll_get_term_translations( $mats_t->term_id ) : array(),
	);
}

foreach ( array( 'mats_team', 'mats_application', 'mats_publication', 'mats_expert' ) as $mats_type ) {
	$mats_out['cpts'][ $mats_type ] = (array) wp_count_posts( $mats_type );
}

foreach ( array( 1, 2, 3 ) as $mats_id ) {
	$mats_p                         = get_post( $mats_id );
	$mats_out['samples'][ $mats_id ] = $mats_p ? $mats_p->post_type . ':' . $mats_p->post_status . ':' . $mats_p->post_name : null;
}
$mats_out['comments'] = (int) wp_count_comments()->total_comments;

echo wp_json_encode( $mats_out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), "\n";
