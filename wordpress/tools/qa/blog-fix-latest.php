<?php
/**
 * QA (module "blog", fix pass): "Últimas noticias" applies its standard words
 * field by field (a typed title no longer removes the kicker or the button).
 *   MATS_WP_PORT=8914 php /home/user/wpsite-cli.php wordpress/tools/qa/blog-fix-latest.php
 */
defined( 'ABSPATH' ) || exit;
$cases = array(
	'empty'          => array(),
	'title only'     => array( 'title' => 'Our latest stories' ),
	'kicker only'    => array( 'kicker' => 'From the lab' ),
	'label only'     => array( 'cta' => array( 'label' => 'See every story', 'page' => '', 'url' => '' ) ),
	'page only'      => array( 'cta' => array( 'label' => '', 'page' => 'research', 'url' => '' ) ),
	'everything'     => array( 'kicker' => 'K', 'title' => 'T', 'cta' => array( 'label' => 'L', 'page' => 'contact' ) ),
);
foreach ( $cases as $name => $attrs ) {
	$html = render_block( array( 'blockName' => 'matsurfer/latest-posts', 'attrs' => $attrs, 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
	preg_match( '#<p class="eyebrow"[^>]*>(.*?)</p>#s', $html, $k );
	preg_match( '#<h2 class="h2"[^>]*>(.*?)</h2>#s', $html, $t );
	preg_match( '#<a class="btn[^"]*lposts__cta[^"]*" href="([^"]*)"[^>]*>(.*?)</a>#s', $html, $b );
	printf( "%-12s kicker=%-14s title=%-22s button=%s -> %s | cards=%d edit=%d\n", $name, $k[1] ?? '-', $t[1] ?? '-', $b[2] ?? '-', $b[1] ?? '-', substr_count( $html, 'class="panel post-card' ), substr_count( $html, 'data-mats-edit' ) );
}
