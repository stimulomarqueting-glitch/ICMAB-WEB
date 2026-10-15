<?php
/**
 * QA (group "home"): edge cases of the value-flow, product-teaser,
 * apps-teaser and explore sections — empty attributes (public: nothing;
 * preview: empty-state box), partial content, missing photo, short label
 * list, the section placed twice (unique ids), editor markers only in
 * preview. Also builds a "home-multi" test page with every section twice
 * (for the view.js multi-instance check).
 * Run: MATS_WP_PORT=8903 php /home/user/wpsite-cli.php wordpress/tools/qa/home-edge.php
 */
$fail = 0;
function home_edge_ok( bool $cond, string $msg ): void {
	global $fail;
	echo ( $cond ? 'ok   ' : 'FAIL ' ) . $msg . "\n";
	if ( ! $cond ) {
		++$fail;
	}
}
function home_edge_render( string $block, array $attrs ): string {
	return mats_render_section( 'matsurfer/' . $block, $attrs );
}

$blocks = array( 'value-flow', 'product-teaser', 'apps-teaser', 'explore' );

/* 1. empty attributes */
foreach ( $blocks as $b ) {
	$GLOBALS['mats_preview'] = false;
	$public                  = trim( home_edge_render( $b, array() ) );
	$GLOBALS['mats_preview'] = true;
	$preview                 = home_edge_render( $b, array() );
	$GLOBALS['mats_preview'] = false;
	home_edge_ok( '' === $public, "$b: empty → nothing on the public site" );
	home_edge_ok( false !== strpos( $preview, 'mats-empty' ), "$b: empty → empty-state box in the editor" );
}

/* 2. seeded content: markers only in preview */
foreach ( $blocks as $b ) {
	foreach ( array( 'en', 'es' ) as $lang ) {
		$attrs                   = mats_seed_attrs( $b, 'home', $lang );
		$GLOBALS['mats_preview'] = false;
		$public                  = mats_with_lang( $lang, static fn() => home_edge_render( $b, $attrs ) );
		$GLOBALS['mats_preview'] = true;
		$preview                 = mats_with_lang( $lang, static fn() => home_edge_render( $b, $attrs ) );
		$GLOBALS['mats_preview'] = false;
		home_edge_ok( false === strpos( $public, 'data-mats-' ) && false === strpos( $public, 'mats-note' ), "$b/$lang: no editor markers on the public site" );
		home_edge_ok( substr_count( $preview, 'data-mats-edit=' ) >= 3, "$b/$lang: editable markers in preview (" . substr_count( $preview, 'data-mats-edit=' ) . ')' );
	}
	home_edge_ok( array() === mats_seed_attrs( $b, 'product', 'en' ), "$b: no seed outside the home page" );
}

/* 3. value flow with two steps: the roles stay on their own step (Astro:
   item 1 = core + isotype, item 2 = result), numbers follow the visible order */
$vf_li = static function ( string $html ): array {
	preg_match_all( '#<li class="([^"]*)"[^>]*>(.*?)</li>#s', $html, $m, PREG_SET_ORDER );
	return array_map(
		static fn( $li ) => array(
			'class' => $li[1],
			'step'  => preg_match( '#value__step">(\d+)<#', $li[2], $n ) ? $n[1] : '',
			'brand' => false !== strpos( $li[2], 'isotipo.svg' ),
			'body'  => $li[2],
		),
		$m
	);
};
/* 3a. the middle (MatSurfer) step cleared */
$two = home_edge_render(
	'value-flow',
	array( 'kicker' => 'K', 'items' => array( array( 'tag' => 'A', 'text' => 'a' ), array( 'tag' => '', 'text' => '' ), array( 'tag' => 'C', 'text' => 'c' ) ) )
);
$li  = $vf_li( $two );
home_edge_ok( 2 === count( $li ), 'value-flow: empty step skipped' );
home_edge_ok( 1 === substr_count( $two, 'value__arrow' ), 'value-flow: one arrow between two steps' );
home_edge_ok( false === strpos( $two, 'value__item--core' ) && false === strpos( $two, 'isotipo.svg' ), 'value-flow: middle step cleared → no core styling, no isotype on another step' );
home_edge_ok( 2 === count( $li ) && false !== strpos( $li[1]['class'], 'value__item--result' ) && false !== strpos( $li[1]['body'], '>C<' ) && '02' === $li[1]['step'], 'value-flow: "C" keeps the result line, numbered 02' );
home_edge_ok( false !== strpos( $two, 'aria-label="K"' ), 'value-flow: aria-label falls back to the kicker' );
/* 3b. the first step cleared (review case) */
$first = home_edge_render(
	'value-flow',
	array( 'items' => array( array( 'tag' => '', 'text' => '' ), array( 'tag' => 'MatSurfer', 'text' => 'b' ), array( 'tag' => 'The result', 'text' => 'c' ) ) )
);
$li    = $vf_li( $first );
home_edge_ok(
	2 === count( $li )
	&& false !== strpos( $li[0]['class'], 'value__item--core' ) && $li[0]['brand'] && false !== strpos( $li[0]['body'], 'MatSurfer' ) && '01' === $li[0]['step']
	&& false !== strpos( $li[1]['class'], 'value__item--result' ) && ! $li[1]['brand'] && false === strpos( $li[1]['class'], 'value__item--core' ),
	'value-flow: first step cleared → MatSurfer keeps core + isotype, "The result" keeps the result line'
);
home_edge_ok( 1 === substr_count( $first, 'value__tag--brand' ) && 1 === substr_count( $first, 'value__arrow' ), 'value-flow: one isotype, one arrow' );
/* 3c. the full three steps (seed) */
$full = $vf_li( home_edge_render( 'value-flow', mats_seed_attrs( 'value-flow', 'home', 'en' ) ) );
home_edge_ok(
	3 === count( $full )
	&& false === strpos( $full[0]['class'], '--' ) && ! $full[0]['brand']
	&& false !== strpos( $full[1]['class'], 'value__item--core' ) && $full[1]['brand']
	&& false !== strpos( $full[2]['class'], 'value__item--result' ) && ! $full[2]['brand']
	&& '01 02 03' === implode( ' ', array_column( $full, 'step' ) ),
	'value-flow: seeded three steps → plain, core + isotype, result; numbered 01-03'
);

/* 4. product teaser without photo / button */
$GLOBALS['mats_preview'] = false;
$nophoto                 = home_edge_render( 'product-teaser', array( 'title' => 'T' ) );
home_edge_ok( false === strpos( $nophoto, '<img' ) && false === strpos( $nophoto, 'pt__cta' ), 'product-teaser: no photo/button → none rendered' );
$GLOBALS['mats_preview'] = true;
$nophoto_preview         = home_edge_render( 'product-teaser', array( 'title' => 'T' ) );
$GLOBALS['mats_preview'] = false;
home_edge_ok( false !== strpos( $nophoto_preview, 'mats-image-empty' ), 'product-teaser: no photo → photo placeholder in the editor' );

/* 5. apps teaser with a short label list */
$short = home_edge_render( 'apps-teaser', array( 'title' => 'T', 'isotypeLabels' => array( 'One', 'Two' ) ) );
home_edge_ok( 2 === substr_count( $short, 'class="iso__label"' ) && 5 === substr_count( $short, 'class="iso__ring"' ), 'apps-teaser: 2 labels → 2 callout labels, 5 rings' );
home_edge_ok( false !== strpos( $short, 'aria-hidden="true" class="isotype__svg"' ), 'apps-teaser: no description → figure hidden from screen readers' );

/* 6. explore with one card */
$one = home_edge_render( 'explore', array( 'teamTitle' => 'Team', 'teamText' => 't' ) );
home_edge_ok( 1 === substr_count( $one, 'explore__card' ) && false !== strpos( $one, 'aria-label="Team"' ), 'explore: one card' );

/* 7. placed twice: unique ids */
$GLOBALS['mats_used_ids'] = array();
$double                   = '';
foreach ( array( 1, 2 ) as $n ) {
	$double .= home_edge_render( 'product-teaser', mats_seed_attrs( 'product-teaser', 'home', 'en' ) );
	$double .= home_edge_render( 'apps-teaser', mats_seed_attrs( 'apps-teaser', 'home', 'en' ) );
}
preg_match_all( '/\sid="([^"]+)"/', $double, $m );
home_edge_ok( count( $m[1] ) === count( array_unique( $m[1] ) ), 'twice on a page: all ids unique (' . implode( ', ', $m[1] ) . ')' );
preg_match_all( '/aria-labelledby="([^"]+)"/', $double, $lb );
home_edge_ok( ! array_diff( $lb[1], $m[1] ), 'twice on a page: every aria-labelledby points to an id' );

/* 8. multi-instance test page */
$content = '';
foreach ( array( 1, 2 ) as $n ) {
	foreach ( $blocks as $b ) {
		$content .= mats_seed_block( $b, 'home', 'en' ) . "\n\n";
	}
}
$existing = get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'meta_key' => '_home_qa', 'meta_value' => 'home-multi', 'numberposts' => 1, 'fields' => 'ids', 'lang' => '' ) );
$args     = array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Home multi', 'post_name' => 'home-multi', 'post_content' => wp_slash( $content ) );
if ( $existing ) {
	$args['ID'] = $existing[0];
	$id         = wp_update_post( $args );
} else {
	$id = wp_insert_post( $args );
	update_post_meta( $id, '_home_qa', 'home-multi' );
}
pll_set_post_language( $id, 'en' );
echo 'multi page: ', get_permalink( $id ), "\n";
echo $fail ? "$fail FAILED\n" : "all passed\n";
