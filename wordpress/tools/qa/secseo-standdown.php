<?php
/**
 * QA (secseo): renders a public page as if Yoast SEO were active and
 * checks that the theme's SEO stands down (no title override, no meta /
 * Open Graph, only the sections' JSON-LD nodes that SEO plugins lack).
 *   php wordpress/tools/qa/secseo-standdown.php <port> <path>
 * (standalone: simulates a front-end request, does not use wpsite-cli.php)
 *
 * @package matsurfer-qa
 */

$mats_port = $argv[1] ?? '8916';
$mats_path = $argv[2] ?? '/research/';
define( 'WPSEO_VERSION', '99.0-qa' ); // what Yoast SEO defines when active
define( 'WP_USE_THEMES', true );

$_SERVER['HTTP_HOST']       = 'localhost:' . $mats_port;
$_SERVER['SERVER_NAME']     = 'localhost';
$_SERVER['SERVER_PORT']     = $mats_port;
$_SERVER['REQUEST_URI']     = $mats_path;
$_SERVER['REQUEST_METHOD']  = 'GET';
$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['PHP_SELF']        = '/index.php';
putenv( 'MATS_WP_PORT=' . $mats_port );

ob_start();
require '/home/user/wpsite/wp-blog-header.php';
$mats_html = ob_get_clean();

$mats_fail = 0;
$mats_ok   = static function ( bool $cond, string $label ) use ( &$mats_fail ) {
	echo ( $cond ? '[ok]   ' : '[FAIL] ' ), $label, "\n";
	$mats_fail += $cond ? 0 : 1;
};
preg_match( '#<title>(.*?)</title>#s', $mats_html, $mats_t );
echo 'title: ', $mats_t[1] ?? '(none)', "\n";
$mats_ok( 'Yoast SEO' === mats_seo_plugin() && ! mats_seo_active(), 'SEO plugin detected, theme SEO off' );
$mats_ok( false === strpos( $mats_html, 'name="description"' ), 'no meta description from the theme' );
$mats_ok( false === strpos( $mats_html, 'property="og:' ), 'no Open Graph from the theme' );
$mats_ok( false === strpos( $mats_html, 'twitter:card' ), 'no Twitter card from the theme' );
$mats_ok( ! empty( $mats_t[1] ) && false === strpos( $mats_t[1], 'Case Studies — MatSurfer' ) || '/research/' !== $mats_path, 'document title not overridden by the theme' );
preg_match_all( '#<script type="application/ld\+json">(.*?)</script>#s', $mats_html, $mats_ld );
$mats_types = array();
foreach ( $mats_ld[1] as $mats_json ) {
	$mats_data = json_decode( $mats_json, true );
	foreach ( (array) ( $mats_data['@graph'] ?? array() ) as $mats_node ) {
		$mats_types[] = $mats_node['@type'] ?? '?';
	}
}
echo 'JSON-LD types: ', implode( ', ', $mats_types ), "\n";
$mats_ok( ! array_intersect( $mats_types, array( 'WebSite', 'Organization', 'BreadcrumbList', 'BlogPosting', 'Product' ) ), 'no site / breadcrumb / article nodes (the plugin prints them)' );
if ( '/research/' === $mats_path ) {
	$mats_ok( in_array( 'ScholarlyArticle', $mats_types, true ), 'section nodes kept (ScholarlyArticle)' );
}
$mats_ok( false !== strpos( $mats_html, 'hreflang="x-default"' ), 'Polylang hreflang + x-default still printed' );
/* robots.txt: the plugin lists its own sitemap (and usually turns wp-sitemap.xml off) */
remove_filter( 'robots_txt', array( wp_sitemaps_get_server(), 'add_robots' ), 0 );
$mats_ok( false === stripos( (string) apply_filters( 'robots_txt', "User-agent: *\nDisallow: /wp-admin/\n", '1' ), 'sitemap:' ), 'robots.txt: no Sitemap line from the theme' );
echo $mats_fail ? "\nFAILED: $mats_fail\n" : "\nALL STAND-DOWN CHECKS PASSED\n";
