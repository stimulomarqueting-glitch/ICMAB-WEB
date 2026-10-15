<?php
/**
 * QA (module "settings"): test pages for the header / footer parity checks.
 * One page per role, EN + ES linked as Polylang translations, slugs as in
 * the installer (inc/installer/content.php). Every landing page holds only
 * its closing CTA band (page-cta); privacy / legal are reading pages. Home
 * is the front page, News the posts page, mats_page_roles points at the
 * EN pages, published
 * news are switched to draft (the Astro site has no News item) unless
 * SETTINGS_QA_NEWS=1, which publishes one EN + ES article instead.
 * Also creates the editor account used by the screen tests.
 * Run: MATS_WP_PORT=8917 php /home/user/wpsite-cli.php wordpress/tools/qa/settings-setup.php
 */

function settings_qa_page( string $key, string $title, string $slug, string $content, string $lang, string $role ): int {
	$existing = get_posts(
		array(
			'post_type'   => 'page',
			'post_status' => 'any',
			'meta_key'    => '_settings_qa',
			'meta_value'  => $key,
			'numberposts' => 1,
			'fields'      => 'ids',
			'lang'        => '',
		)
	);
	$args = array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_content' => wp_slash( $content ),
	);
	if ( $existing ) {
		$args['ID'] = $existing[0];
		$id         = wp_update_post( $args );
	} else {
		$id = wp_insert_post( $args );
		update_post_meta( $id, '_settings_qa', $key );
	}
	pll_set_post_language( $id, $lang );
	update_post_meta( $id, '_mats_role', $role );
	return (int) $id;
}

$specs = mats_installer_page_specs();
$roles = array();
foreach ( array( 'home', 'product', 'applications', 'research', 'team', 'contact', 'news', 'privacy', 'legal' ) as $role ) {
	$ids = array();
	foreach ( array( 'en', 'es' ) as $lang ) {
		if ( 'news' === $role ) {
			$content = '';
		} elseif ( in_array( $role, array( 'privacy', 'legal' ), true ) ) {
			$content = "<!-- wp:paragraph -->\n<p>QA legal text.</p>\n<!-- /wp:paragraph -->";
		} else {
			$content = mats_seed_block( 'page-cta', 'contact' === $role ? 'home' : $role, $lang );
		}
		$ids[ $lang ] = settings_qa_page( $role . '-' . $lang, $specs[ $role ][ $lang ][0], $specs[ $role ][ $lang ][1], $content, $lang, $role );
	}
	pll_save_post_translations( $ids );
	$roles[ $role ] = $ids['en'];
}
update_option( 'mats_page_roles', $roles );

/* a long page with several reveal sections, for the site.js checks */
$reveal_blocks = array();
foreach ( array( 'home', 'product', 'applications', 'research', 'team' ) as $page ) {
	$reveal_blocks[] = mats_seed_block( 'page-cta', $page, 'en' );
}
$reveal_id = settings_qa_page( 'reveal-en', 'QA reveal', 'qa-reveal', implode( "\n\n", $reveal_blocks ), 'en', '' );
delete_post_meta( $reveal_id, '_mats_role' );
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $roles['home'] );
update_option( 'page_for_posts', $roles['news'] );

/* news: none (parity with Astro) or one per language */
$with_news = '1' === getenv( 'SETTINGS_QA_NEWS' );
foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => array( 'publish', 'draft' ), 'numberposts' => -1, 'lang' => '' ) ) as $post ) {
	$is_qa = (bool) get_post_meta( $post->ID, '_settings_qa', true );
	wp_update_post(
		array(
			'ID'          => $post->ID,
			'post_status' => ( $with_news && $is_qa ) ? 'publish' : 'draft',
		)
	);
}
if ( $with_news ) {
	$news = array();
	foreach ( array( 'en' => 'QA news article', 'es' => 'Noticia de prueba' ) as $lang => $title ) {
		$found = get_posts( array( 'post_type' => 'post', 'post_status' => 'any', 'meta_key' => '_settings_qa', 'meta_value' => 'news-' . $lang, 'numberposts' => 1, 'fields' => 'ids', 'lang' => '' ) );
		$id    = $found ? (int) $found[0] : (int) wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => '<!-- wp:paragraph --><p>QA.</p><!-- /wp:paragraph -->',
			)
		);
		update_post_meta( $id, '_settings_qa', 'news-' . $lang );
		wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
		pll_set_post_language( $id, $lang );
		$news[ $lang ] = $id;
	}
	pll_save_post_translations( $news );
}

/* the Editor account (ICMAB staff) */
$editor = get_user_by( 'login', 'editora' );
if ( ! $editor ) {
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
	$uid = $editor->ID;
	wp_set_password( 'editora-local-pass', $uid );
	$editor->set_role( 'editor' );
}
/* an author must not reach the screen */
if ( ! get_user_by( 'login', 'autora' ) ) {
	wp_insert_user(
		array(
			'user_login' => 'autora',
			'user_pass'  => 'autora-local-pass',
			'user_email' => 'autora@example.org',
			'role'       => 'author',
		)
	);
}

flush_rewrite_rules();

$out = array();
foreach ( $roles as $role => $id ) {
	$out[ $role ] = array(
		'en' => get_permalink( $id ),
		'es' => get_permalink( pll_get_post( $id, 'es' ) ),
	);
}
echo wp_json_encode( array( 'pages' => $out, 'news' => $with_news, 'editor' => (int) $uid ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), "\n";
