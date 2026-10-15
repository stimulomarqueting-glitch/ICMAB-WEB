<?php
/**
 * Seed mapper: stand-alone section introduction (SectionHeader.astro in its
 * own section). Used once in the Astro site: the "What it is" block of the
 * product page — <section id="what-it-is" aria-labelledby="what-title">.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

function mats_seed_attrs_section_intro( array $c, string $page, string $lang ): array {
	$map = array(
		'product' => array(
			'copy'      => $c['product']['whatItIs'] ?? array(),
			'sectionId' => 'what-it-is',
			'headingId' => 'what-title',
		),
	);
	if ( ! isset( $map[ $page ] ) ) {
		return array();
	}
	$copy = $map[ $page ]['copy'];
	return array(
		'kicker'     => $copy['kicker'] ?? '',
		'kickerNote' => $copy['kickerNote'] ?? '',
		'title'      => $copy['title'] ?? '',
		'lead'       => $copy['lead'] ?? '',
		'sectionId'  => $map[ $page ]['sectionId'],
		'headingId'  => $map[ $page ]['headingId'],
	);
}
