<?php
/**
 * Seed mapper: FAQ. Used once in the Astro site, on the product page —
 * <section id="faq" aria-labelledby="faq-title"> with product.faq (kicker,
 * title) and <Accordion items={faqItems} idPrefix="faq" />, where faqItems
 * are the items not marked `published: false`.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

function mats_seed_attrs_faq( array $c, string $page, string $lang ): array {
	if ( 'product' !== $page ) {
		return array();
	}
	$copy  = $c['product']['faq'] ?? array();
	$items = array();
	foreach ( (array) ( $copy['items'] ?? array() ) as $item ) {
		if ( isset( $item['published'] ) && false === $item['published'] ) {
			continue;
		}
		$items[] = array(
			'q' => (string) ( $item['q'] ?? '' ),
			'a' => (string) ( $item['a'] ?? '' ),
		);
	}
	return array(
		'kicker'    => (string) ( $copy['kicker'] ?? '' ),
		'title'     => (string) ( $copy['title'] ?? '' ),
		'items'     => $items,
		'sectionId' => 'faq',
		'headingId' => 'faq-title',
		'idPrefix'  => 'faq',
	);
}
