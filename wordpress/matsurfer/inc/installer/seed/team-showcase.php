<?php
/**
 * Seed mapper: team showcase. Only the team page uses it (team.astro
 * renders TeamShowcase with the published members, carrying the page h1;
 * the kicker/title/lead are those of the PageHero fallback). The people
 * themselves come from the Equipo records, not from these attributes.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

function mats_seed_attrs_team_showcase( array $c, string $page, string $lang ): array {
	$t        = $c['teamPage'] ?? array();
	$links    = $t['links'] ?? array();
	$showcase = $t['showcase'] ?? array();
	return array(
		'kicker'        => $t['kicker'] ?? '',
		'title'         => $t['title'] ?? '',
		'lead'          => $t['lead'] ?? '',
		'pickerLabel'   => $showcase['pickerLabel'] ?? '',
		'showLabel'     => $showcase['show'] ?? '',
		'noBio'         => $showcase['noBio'] ?? '',
		'linkedinLabel' => $links['linkedin'] ?? '',
		'websiteLabel'  => $links['website'] ?? '',
		'linkedinOf'    => $links['linkedinOf'] ?? '',
		'websiteOf'     => $links['websiteOf'] ?? '',
	);
}
