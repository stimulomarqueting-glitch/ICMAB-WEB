<?php
/**
 * QA (group "benefits"): edge cases of the benefits / audiences renderers —
 * empty attributes (public + editor preview), partial items, unknown icons,
 * object items, the same section twice on a page (unique ids).
 * Run: MATS_WP_PORT=8904 php /home/user/wpsite-cli.php wordpress/tools/qa/benefits-edge.php
 */
function bqa_ids( string $html ): string {
	preg_match_all( '/\sid="([^"]+)"/', $html, $m );
	preg_match_all( '/aria-labelledby="([^"]+)"/', $html, $l );
	return 'ids=' . implode( ',', $m[1] ) . ' | labelledby=' . implode( ',', $l[1] );
}
function bqa_check( string $label, bool $ok ): void {
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n";
}

/* 1. empty: nothing on the public site, an empty-state box in the editor */
foreach ( array( 'benefits', 'audiences' ) as $b ) {
	$GLOBALS['mats_preview'] = false;
	$pub                     = trim( mats_render_section( 'matsurfer/' . $b, array() ) );
	$GLOBALS['mats_preview'] = true;
	$prev                    = mats_render_section( 'matsurfer/' . $b, array() );
	$GLOBALS['mats_preview'] = false;
	bqa_check( "$b empty → public prints nothing", '' === $pub );
	bqa_check( "$b empty → editor empty-state", false !== strpos( $prev, 'mats-empty' ) );
}

/* 2. partial content: title only, items without text, unknown icon, stdClass items */
$html = mats_render_section(
	'matsurfer/benefits',
	array(
		'title'    => 'Only a title',
		'outcomes' => array(
			array( 'icon' => 'nope', 'title' => 'Unknown icon', 'text' => '' ),
			array( 'icon' => 'group', 'title' => '', 'text' => '' ),
			(object) array( 'icon' => 'defensible', 'title' => 'Object item', 'text' => 'Text <b>escaped</b>' ),
		),
	)
);
bqa_check( 'benefits: no kicker <p> when empty', false === strpos( $html, 'class="eyebrow"' ) );
bqa_check( 'benefits: empty outcome skipped (2 cards)', 2 === substr_count( $html, 'class="bene__outcome reveal"' ) );
bqa_check( 'benefits: unknown icon → dot matrix at 2.6', false !== strpos( $html, 'stroke-width="2.6"' ) );
bqa_check( 'benefits: no <p> for empty text', 1 === substr_count( $html, '<p>' ) );
bqa_check( 'benefits: text escaped', false !== strpos( $html, 'Text &lt;b&gt;escaped&lt;/b&gt;' ) );
bqa_check( 'benefits: --i follows rendered position', false !== strpos( $html, 'style="--i: 1"' ) && false === strpos( $html, 'style="--i: 2"' ) );

$html = mats_render_section(
	'matsurfer/audiences',
	array(
		'kicker' => 'K',
		'title'  => 'T',
		'groups' => array(
			array( 'icon' => '', 'title' => 'No icon', 'text' => '' ),
			(object) array( 'icon' => 'modularity', 'title' => '', 'text' => 'Text only' ),
		),
	)
);
bqa_check( 'audiences: unknown icon → new-to-surfaces svg', false !== strpos( $html, '<svg' ) && 2 === substr_count( $html, '<svg' ) );
bqa_check( 'audiences: no aud__more when text empty', 1 === substr_count( $html, 'class="aud__more"' ) );
bqa_check( 'audiences: panel without title has no aria-labelledby', 1 === substr_count( $html, 'aria-labelledby="aud-' ) );
bqa_check( 'audiences: first panel active only', 1 === substr_count( $html, 'data-active' ) );

/* 3. twice on a page: ids stay unique */
$GLOBALS['mats_used_ids'] = array();
$two_b                    = mats_seed_attrs( 'benefits', 'home', 'en' );
$two_a                    = mats_seed_attrs( 'audiences', 'home', 'en' );
$h1 = mats_render_section( 'matsurfer/benefits', $two_b );
$h2 = mats_render_section( 'matsurfer/benefits', $two_b );
$h3 = mats_render_section( 'matsurfer/audiences', $two_a );
$h4 = mats_render_section( 'matsurfer/audiences', $two_a );
echo 'benefits #1 ', bqa_ids( $h1 ), "\nbenefits #2 ", bqa_ids( $h2 ), "\naudiences #1 ", bqa_ids( $h3 ), "\naudiences #2 ", bqa_ids( $h4 ), "\n";
preg_match_all( '/\sid="([^"]+)"/', $h1 . $h2 . $h3 . $h4, $all );
bqa_check( 'all ids unique across two copies of each section', count( $all[1] ) === count( array_unique( $all[1] ) ) );
preg_match_all( '/aria-labelledby="([^"]+)"/', $h1 . $h2 . $h3 . $h4, $lab );
bqa_check( 'every aria-labelledby points to an id', ! array_diff( $lab[1], $all[1] ) );
$GLOBALS['mats_used_ids'] = array();

/* 4. seed mappers: roles without the section return nothing */
bqa_check( 'seed benefits on team → empty', array() === mats_seed_attrs( 'benefits', 'team', 'en' ) );
bqa_check( 'seed audiences on product → empty', array() === mats_seed_attrs( 'audiences', 'product', 'es' ) );
$p = mats_seed_attrs( 'benefits', 'product', 'es' );
bqa_check( 'seed benefits product ES heading id', 'product-benefits-title' === $p['headingId'] && 4 === count( $p['outcomes'] ) );
