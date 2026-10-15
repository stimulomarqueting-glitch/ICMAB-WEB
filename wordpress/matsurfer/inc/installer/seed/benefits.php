<?php
/**
 * Seed mapper: outcome band (Benefits.astro). Used twice in the Astro site:
 *   home    → home.benefits,    <section id="benefits" aria-labelledby="benefits-title">
 *   product → product.benefits, <section id="benefits" aria-labelledby="product-benefits-title">
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

function mats_seed_attrs_benefits( array $c, string $page, string $lang ): array {
	$map = array(
		'home'    => array(
			'copy'      => $c['home']['benefits'] ?? array(),
			'headingId' => 'benefits-title',
		),
		'product' => array(
			'copy'      => $c['product']['benefits'] ?? array(),
			'headingId' => 'product-benefits-title',
		),
	);
	if ( ! isset( $map[ $page ] ) ) {
		return array();
	}
	$copy     = (array) $map[ $page ]['copy'];
	$outcomes = array();
	foreach ( (array) ( $copy['outcomes'] ?? array() ) as $o ) {
		$outcomes[] = array(
			'icon'  => (string) ( $o['icon'] ?? '' ),
			'title' => (string) ( $o['title'] ?? '' ),
			'text'  => (string) ( $o['text'] ?? '' ),
		);
	}
	return array(
		'kicker'    => (string) ( $copy['kicker'] ?? '' ),
		'title'     => (string) ( $copy['title'] ?? '' ),
		'outcomes'  => $outcomes,
		'sectionId' => 'benefits',
		'headingId' => $map[ $page ]['headingId'],
	);
}
