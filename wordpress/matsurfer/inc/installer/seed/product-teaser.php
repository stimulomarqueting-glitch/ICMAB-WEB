<?php
/**
 * Seed mapper: home product teaser (#technology). Used once in the Astro
 * site, on the home page, from home.product, with the tech-detail.jpg photo
 * and the CTA tracked as "home-product".
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

function mats_seed_attrs_product_teaser( array $c, string $page, string $lang ): array {
	if ( 'home' !== $page ) {
		return array();
	}
	$copy = $c['home']['product'] ?? array();
	return array(
		'kicker'    => (string) ( $copy['kicker'] ?? '' ),
		'title'     => (string) ( $copy['title'] ?? '' ),
		'lead'      => (string) ( $copy['lead'] ?? '' ),
		'cta'       => mats_seed_link( (string) ( $copy['cta'] ?? '' ), (string) ( $copy['ctaHref'] ?? '/product' ) ),
		'photo'     => mats_seed_image( 'tech-detail.jpg', (string) ( $copy['photoAlt'] ?? '' ) ),
		'sectionId' => 'technology',
		'headingId' => 'product-teaser-title',
		'ctaSource' => 'home-product',
	);
}
