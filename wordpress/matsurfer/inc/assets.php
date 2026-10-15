<?php
/**
 * Front-end and editor assets.
 *
 *  assets/css/base.css     design tokens, reset, typography, buttons, panels,
 *                          motion utilities (port of src/styles/global.css)
 *  assets/css/chrome.css   header, footer, page/blog templates
 *  assets/css/editor-canvas.css  editor-only preview adjustments
 *  assets/js/site.js       reveal-on-scroll, header menu, lead tracking
 *  blocks/<name>/style.css loaded only on pages that use the section
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

function mats_ver( string $relative ): string {
	$file = MATS_DIR . '/' . ltrim( $relative, '/' );
	return is_readable( $file ) ? (string) filemtime( $file ) : MATS_VERSION;
}

add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_enqueue_style( 'matsurfer-base', MATS_URI . '/assets/css/base.css', array(), mats_ver( 'assets/css/base.css' ) );
		wp_enqueue_style( 'matsurfer-chrome', MATS_URI . '/assets/css/chrome.css', array( 'matsurfer-base' ), mats_ver( 'assets/css/chrome.css' ) );
		wp_enqueue_script(
			'matsurfer-site',
			MATS_URI . '/assets/js/site.js',
			array(),
			mats_ver( 'assets/js/site.js' ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	},
	5
);

/** Preload the two fonts used above the fold. */
add_action(
	'wp_head',
	static function () {
		foreach ( array( 'sora-latin-600-normal.woff2', 'manrope-latin-400-normal.woff2' ) as $font ) {
			printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( mats_asset( 'fonts/' . $font ) ) );
		}
		printf( '<link rel="icon" type="image/svg+xml" href="%s">' . "\n", esc_url( mats_asset( 'brand/favicon.svg' ) ) );
		echo '<meta name="theme-color" content="#FDFBF8">' . "\n";
	},
	2
);

/** Editor canvas: same base styles as the site + preview helpers. */
add_action(
	'after_setup_theme',
	static function () {
		add_theme_support( 'editor-styles' );
		add_editor_style( array( 'assets/css/base.css', 'assets/css/editor-canvas.css' ) );
	}
);

/* Leaner front end: no emoji script, no oEmbed discovery script. */
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'admin_print_styles', 'print_emoji_styles' );
remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
add_filter( 'emoji_svg_url', '__return_false' );

/* Core block CSS only for the blocks a page actually uses. */
add_filter( 'should_load_separate_core_block_assets', '__return_true' );
