<?php
/**
 * Seed mapper: "From the lab to the bench" (TeamJourney.astro). Only the
 * team page uses it, right under the showcase; copy from teamPage.journey.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

function mats_seed_attrs_team_journey( array $c, string $page, string $lang ): array {
	$j     = $c['teamPage']['journey'] ?? array();
	$steps = array();
	foreach ( (array) ( $j['steps'] ?? array() ) as $step ) {
		$steps[] = array(
			'title' => (string) ( $step['title'] ?? '' ),
			'text'  => (string) ( $step['text'] ?? '' ),
		);
	}
	$facts = array();
	foreach ( (array) ( $j['facts'] ?? array() ) as $fact ) {
		$facts[] = array(
			'value' => (string) ( $fact['value'] ?? '' ),
			'label' => (string) ( $fact['label'] ?? '' ),
		);
	}
	return array(
		'kicker'    => $j['kicker'] ?? '',
		'title'     => $j['title'] ?? '',
		'lead'      => $j['lead'] ?? '',
		'steps'     => $steps,
		'facts'     => $facts,
		'factsNote' => $j['factsNote'] ?? '',
		'factsLink' => mats_seed_link( (string) ( $j['factsLink'] ?? '' ), (string) ( $j['factsHref'] ?? '/research' ) ),
	);
}
