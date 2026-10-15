<?php
/**
 * Theme supports and content defaults.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	static function () {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support(
			'html5',
			array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' )
		);
		/* no core block patterns: editors only see MatSurfer sections */
		remove_theme_support( 'core-block-patterns' );
		remove_theme_support( 'widgets-block-editor' );

		/* blog card / single-post image sizes */
		add_image_size( 'mats-card', 800, 520, true );
		add_image_size( 'mats-wide', 1600, 900, true );
	}
);

/* Excerpts used on blog cards. */
add_filter( 'excerpt_length', static fn() => 28, 99 );
add_filter( 'excerpt_more', static fn() => '…', 99 );

/* Typographic quote replacement only for news articles; section pages keep
   their copy exactly as written. */
add_filter( 'run_wptexturize', static fn( $run ) => is_page() ? false : $run );

/**
 * Landing pages (built from MatSurfer sections) render edge to edge; any
 * other page (legal texts…) gets the reading layout with a title header.
 */
function mats_is_landing( $post = null ): bool {
	$post = get_post( $post );
	if ( ! $post ) {
		return false;
	}
	$blocks = parse_blocks( (string) $post->post_content );
	foreach ( $blocks as $block ) {
		if ( empty( $block['blockName'] ) ) {
			continue;
		}
		return str_starts_with( $block['blockName'], 'matsurfer/' );
	}
	return false;
}
