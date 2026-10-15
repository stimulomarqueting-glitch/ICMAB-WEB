<?php
/**
 * Seed mapper: IP & validation band. Used once in the Astro site, on the
 * research page, from validationItems (published only): the title of the
 * first item heads the band, and every item adds one glass panel with its
 * text — <section aria-labelledby="validation-title" class="sec validation--dark" id="validation">.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

function mats_seed_attrs_research_validation( array $c, string $page, string $lang ): array {
	if ( 'research' !== $page ) {
		return array();
	}
	$items = array_values(
		array_filter(
			(array) ( $c['validationItems'] ?? array() ),
			static fn( $v ) => is_array( $v ) && ! empty( $v['published'] )
		)
	);
	return array(
		'title'      => (string) ( $items[0]['title'] ?? '' ),
		'paragraphs' => array_values( array_map( static fn( $v ) => (string) ( $v['text'] ?? '' ), $items ) ),
		'sectionId'  => 'validation',
		'headingId'  => 'validation-title',
	);
}
