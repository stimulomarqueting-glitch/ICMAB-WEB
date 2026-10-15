<?php
/**
 * Seed mapper: "Who it's for" (Audiences.astro). Used once in the Astro
 * site, on the home page, from home.audiences —
 * <section id="who-is-it-for" aria-labelledby="audiences-title">.
 * The icon keys (surface, lab, insitu, modularity) pick the client's brand
 * icons in render.php.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

function mats_seed_attrs_audiences( array $c, string $page, string $lang ): array {
	if ( 'home' !== $page ) {
		return array();
	}
	$copy   = (array) ( $c['home']['audiences'] ?? array() );
	$groups = array();
	foreach ( (array) ( $copy['groups'] ?? array() ) as $g ) {
		$groups[] = array(
			'icon'  => (string) ( $g['icon'] ?? '' ),
			'title' => (string) ( $g['title'] ?? '' ),
			'text'  => (string) ( $g['text'] ?? '' ),
		);
	}
	return array(
		'kicker'    => (string) ( $copy['kicker'] ?? '' ),
		'title'     => (string) ( $copy['title'] ?? '' ),
		'groups'    => $groups,
		'sectionId' => 'who-is-it-for',
		'headingId' => 'audiences-title',
	);
}
