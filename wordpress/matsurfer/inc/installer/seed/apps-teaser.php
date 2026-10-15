<?php
/**
 * Seed mapper: home applications teaser (#applications). Used once in the
 * Astro site, on the home page, from home.applications (the isotype figure
 * copy included); the CTA is tracked as "home-applications". The chips
 * under the figure come from the Applications records, not from here.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

function mats_seed_attrs_apps_teaser( array $c, string $page, string $lang ): array {
	if ( 'home' !== $page ) {
		return array();
	}
	$copy    = $c['home']['applications'] ?? array();
	$isotype = $copy['isotype'] ?? array();
	return array(
		'kicker'         => (string) ( $copy['kicker'] ?? '' ),
		'title'          => (string) ( $copy['title'] ?? '' ),
		'lead'           => (string) ( $copy['lead'] ?? '' ),
		'cta'            => mats_seed_link( (string) ( $copy['cta'] ?? '' ), (string) ( $copy['ctaHref'] ?? '/applications' ) ),
		'isotypeLabels'  => array_values( array_map( 'strval', (array) ( $isotype['labels'] ?? array() ) ) ),
		'isotypeCaption' => (string) ( $isotype['caption'] ?? '' ),
		'isotypeAlt'     => (string) ( $isotype['alt'] ?? '' ),
		'sectionId'      => 'applications',
		'headingId'      => 'apps-teaser-title',
		'ctaSource'      => 'home-applications',
	);
}
