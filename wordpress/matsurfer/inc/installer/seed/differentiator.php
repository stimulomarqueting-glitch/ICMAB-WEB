<?php
/**
 * Seed mapper: key differentiator. Used once in the Astro site, on the
 * product page — <section id="differentiator" aria-labelledby="diff-title">
 * with product.differentiator (kicker, title, text, features[]).
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

function mats_seed_attrs_differentiator( array $c, string $page, string $lang ): array {
	if ( 'product' !== $page ) {
		return array();
	}
	$copy     = $c['product']['differentiator'] ?? array();
	$features = array();
	foreach ( (array) ( $copy['features'] ?? array() ) as $feature ) {
		$features[] = array(
			'title' => (string) ( $feature['title'] ?? '' ),
			'text'  => (string) ( $feature['text'] ?? '' ),
		);
	}
	return array(
		'kicker'    => (string) ( $copy['kicker'] ?? '' ),
		'title'     => (string) ( $copy['title'] ?? '' ),
		'text'      => (string) ( $copy['text'] ?? '' ),
		'features'  => $features,
		'sectionId' => 'differentiator',
		'headingId' => 'diff-title',
	);
}
