<?php
/**
 * Seed mapper: publications. Used once in the Astro site, on the research
 * page (researchPage.publicationsTitle) —
 * <section aria-labelledby="publications-title" class="sec hairtop" id="publications">.
 * The papers themselves come from the Publicaciones records.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

function mats_seed_attrs_research_publications( array $c, string $page, string $lang ): array {
	if ( 'research' !== $page ) {
		return array();
	}
	return array(
		'title'     => (string) ( $c['researchPage']['publicationsTitle'] ?? '' ),
		'sectionId' => 'publications',
		'headingId' => 'publications-title',
	);
}
