<?php
/**
 * Seed mapper: applications list (sticky nav + application blocks). Used
 * once in the Astro site, on the applications page, with the fixed labels
 * of applicationsPage.labels (section = aria-label of the nav and of the
 * list). The applications themselves come from the Aplicaciones records;
 * each CTA is tracked as "applications-<slug>" (set by render.php). The
 * text of the link to the Case Studies page (labelCase) is not in the
 * Astro copy: it is seeded with the theme's default per language.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

function mats_seed_attrs_applications_list( array $c, string $page, string $lang ): array {
	if ( 'applications' !== $page ) {
		return array();
	}
	$labels = $c['applicationsPage']['labels'] ?? array();
	return array(
		'labelSection'   => (string) ( $labels['section'] ?? '' ),
		'labelChallenge' => (string) ( $labels['challenge'] ?? '' ),
		'labelUse'       => (string) ( $labels['use'] ?? '' ),
		'labelBenefit'   => (string) ( $labels['benefit'] ?? '' ),
		'labelCta'       => (string) ( $labels['cta'] ?? '' ),
		/* not in the original copy: the theme's bilingual default */
		'labelCase'      => (string) ( $labels['caseLink'] ?? mats_t( 'case_link', $lang ) ),
	);
}
