<?php
/**
 * Seed mapper: parameters. Used once in the Astro site, on the product page
 * — <section class="sec hairtop mist" id="parameters" aria-labelledby="params-title">
 * with product.parameters (kicker, title, lead, items[ {symbol, label} ]).
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

function mats_seed_attrs_parameters( array $c, string $page, string $lang ): array {
	if ( 'product' !== $page ) {
		return array();
	}
	$copy  = $c['product']['parameters'] ?? array();
	$items = array();
	foreach ( (array) ( $copy['items'] ?? array() ) as $item ) {
		$items[] = array(
			'symbol' => (string) ( $item['symbol'] ?? '' ),
			'label'  => (string) ( $item['label'] ?? '' ),
		);
	}
	return array(
		'kicker'    => (string) ( $copy['kicker'] ?? '' ),
		'title'     => (string) ( $copy['title'] ?? '' ),
		'lead'      => (string) ( $copy['lead'] ?? '' ),
		'items'     => $items,
		'sectionId' => 'parameters',
		'headingId' => 'params-title',
	);
}
