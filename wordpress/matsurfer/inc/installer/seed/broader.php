<?php
/**
 * Seed mapper: dark highlighted band ("beyond surface science"). Used once
 * in the Astro site, on the applications page, from applicationsPage.broader
 * — <section class="sec broader--dark" aria-labelledby="broader-title">.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

function mats_seed_attrs_broader( array $c, string $page, string $lang ): array {
	if ( 'applications' !== $page ) {
		return array();
	}
	$copy = $c['applicationsPage']['broader'] ?? array();
	return array(
		'kicker'    => (string) ( $copy['kicker'] ?? '' ),
		'title'     => (string) ( $copy['title'] ?? '' ),
		'text'      => (string) ( $copy['text'] ?? '' ),
		'headingId' => 'broader-title',
	);
}
