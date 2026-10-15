<?php
/**
 * Seed mapper: home value flow (Problem → MatSurfer → Result). Used once in
 * the Astro site, on the home page, from home.value.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

function mats_seed_attrs_value_flow( array $c, string $page, string $lang ): array {
	if ( 'home' !== $page ) {
		return array();
	}
	$value = $c['home']['value'] ?? array();
	$items = array();
	foreach ( (array) ( $value['items'] ?? array() ) as $item ) {
		$items[] = array(
			'tag'  => (string) ( $item['tag'] ?? '' ),
			'text' => (string) ( $item['text'] ?? '' ),
		);
	}
	return array(
		'label'  => (string) ( $value['label'] ?? '' ),
		'kicker' => (string) ( $value['kicker'] ?? '' ),
		'items'  => $items,
	);
}
