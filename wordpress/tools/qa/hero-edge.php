<?php
/**
 * QA (group "hero"): renders hero-field with edge-case attributes (public +
 * editor preview) and prints a compact summary. Any PHP notice shows up here
 * (error_reporting E_ALL) and in debug-<port>.log.
 * Run: MATS_WP_PORT=8901 php /home/user/wpsite-cli.php wordpress/tools/qa/hero-edge.php
 */
error_reporting( E_ALL );
ini_set( 'display_errors', '1' );
$base  = mats_seed_attrs( 'hero-field', 'home', 'en' );
$cases = array(
	'seed'          => $base,
	'defaults-only' => array(),
	'no-headline'   => array_merge( $base, array( 'headline' => '  ' ) ),
	'minimal'       => array( 'headline' => 'Only a headline' ),
	'no-vapour'     => array_merge( $base, array( 'vapour' => false ) ),
	'no-photo'      => array_merge( $base, array( 'photo' => array() ) ),
	'url-photo'     => array_merge( $base, array( 'photo' => array( 'url' => 'https://example.org/x.jpg', 'alt' => 'x' ) ) ),
	'one-word-claim' => array_merge( $base, array( 'claim' => 'Surfaces' ) ),
	'gappy-marquee' => array_merge( $base, array( 'marquee' => array( 'A', '', 'C', null, array( 'x' ) ) ) ),
	'xss'           => array_merge( $base, array( 'headline' => '<script>alert(1)</script> "q"', 'claim' => 'a <b>b</b>', 'ctaPrimary' => array( 'label' => 'Go', 'url' => 'javascript:alert(1)' ) ) ),
);
foreach ( array( false, true ) as $preview ) {
	$GLOBALS['mats_preview'] = $preview;
	foreach ( $cases as $name => $attrs ) {
		$html = mats_render_section( 'matsurfer/hero-field', $attrs );
		$sum  = array(
			'len'     => strlen( $html ),
			'stage'   => substr_count( $html, 'field__stage' ),
			'pick'    => substr_count( $html, 'mats-image-empty' ),
			'canvas'  => substr_count( $html, '<canvas' ),
			'img'     => substr_count( $html, '<img' ),
			'li'      => substr_count( $html, '<li' ),
			'edit'    => preg_match_all( '/data-mats-edit="([^"]+)"/', $html, $m ) ? implode( ',', $m[1] ) : '',
			'empty'   => str_contains( $html, 'mats-empty' ),
			'script'  => str_contains( $html, '<script' ),
			'js-href' => str_contains( $html, 'javascript:' ),
		);
		echo ( $preview ? '[preview] ' : '[public]  ' ), str_pad( $name, 15 ), wp_json_encode( $sum ), "\n";
		if ( 'one-word-claim' === $name && ! $preview ) {
			preg_match( '/<p class="field__claim[^>]*>.*?<\/p>/s', $html, $c );
			echo '   claim: ', $c[0] ?? '-', "\n";
		}
		if ( 'xss' === $name && ! $preview ) {
			preg_match( '/<h1.*?<\/h1>/s', $html, $c );
			echo '   h1: ', substr( $c[0] ?? '-', 0, 300 ), "\n";
		}
		if ( 'url-photo' === $name && ! $preview ) {
			preg_match( '/<img[^>]*>/s', $html, $c );
			echo '   img: ', $c[0] ?? '-', "\n";
		}
	}
}
