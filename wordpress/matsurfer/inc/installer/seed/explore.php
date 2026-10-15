<?php
/**
 * Seed mapper: home research + team strip. Used once in the Astro site, on
 * the home page, from home.explore; the two buttons are tracked as
 * "home-research" and "home-team".
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

function mats_seed_attrs_explore( array $c, string $page, string $lang ): array {
	if ( 'home' !== $page ) {
		return array();
	}
	$copy = $c['home']['explore'] ?? array();
	return array(
		'researchTitle'     => (string) ( $copy['researchTitle'] ?? '' ),
		'researchText'      => (string) ( $copy['researchText'] ?? '' ),
		'researchCta'       => mats_seed_link( (string) ( $copy['researchCta'] ?? '' ), (string) ( $copy['researchHref'] ?? '/research' ) ),
		'teamTitle'         => (string) ( $copy['teamTitle'] ?? '' ),
		'teamText'          => (string) ( $copy['teamText'] ?? '' ),
		'teamCta'           => mats_seed_link( (string) ( $copy['teamCta'] ?? '' ), (string) ( $copy['teamHref'] ?? '/team' ) ),
		'researchCtaSource' => 'home-research',
		'teamCtaSource'     => 'home-team',
	);
}
