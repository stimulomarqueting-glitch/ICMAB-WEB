<?php
/**
 * Seed mapper: home hero (HeroField.astro, the live variant per
 * src/config/hero.ts). Only the home page uses it; the copy comes from
 * home.hero, the claim from the brand config (claim / claimEs) and the
 * marquee words from home.hero.field.marquee.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

function mats_seed_attrs_hero_field( array $c, string $page, string $lang ): array {
	$hero  = $c['home']['hero'] ?? array();
	$brand = $c['_brand'] ?? array();
	$claim = 'es' === $lang ? ( $brand['claimEs'] ?? '' ) : ( $brand['claim'] ?? '' );

	return array(
		'claim'              => (string) $claim,
		'headline'           => (string) ( $hero['headline'] ?? '' ),
		'descriptor'         => (string) ( $hero['descriptor'] ?? '' ),
		'lead'               => (string) ( $hero['lead'] ?? '' ),
		'ctaPrimary'         => mats_seed_link( (string) ( $hero['ctaPrimary'] ?? '' ), '/contact' ),
		'ctaSecondary'       => mats_seed_link( (string) ( $hero['ctaSecondary'] ?? '' ), '/product' ),
		'eyebrow'            => (string) ( $hero['eyebrow'] ?? '' ),
		'photo'              => mats_seed_image( 'product-table.jpg', (string) ( $hero['photoAlt'] ?? '' ) ),
		'vapour'             => true,
		'marquee'            => array_values( array_map( 'strval', (array) ( $hero['field']['marquee'] ?? array() ) ) ),
		'ctaSource'          => 'hero',
		'ctaSourceSecondary' => 'hero-product',
	);
}
