<?php
/**
 * Seed mapper: expert experiences. Used once in the Astro site, on the
 * research page (researchPage.experiencesTitle + researchPage.moreLink) —
 * <section aria-labelledby="experiences-title" class="sec hairtop">.
 * The evaluations themselves come from the Valoraciones records.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

function mats_seed_attrs_research_experiences( array $c, string $page, string $lang ): array {
	if ( 'research' !== $page ) {
		return array();
	}
	$t = $c['researchPage'] ?? array();
	return array(
		'title'     => (string) ( $t['experiencesTitle'] ?? '' ),
		'moreLink'  => (string) ( $t['moreLink'] ?? '' ),
		'headingId' => 'experiences-title',
	);
}
