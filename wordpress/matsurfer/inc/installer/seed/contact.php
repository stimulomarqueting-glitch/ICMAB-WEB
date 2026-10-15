<?php
/**
 * Seed mapper: contact section (src/pages/[...lang]/contact.astro).
 *   contact.hero  → kicker, title (H1), lead
 *   contact.aside → "what happens next" title + steps, direct email texts
 * The form itself is the WPForms form of the language (inc/modules/wpforms.php,
 * built from contact.form); the email address comes from Ajustes de la web.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

function mats_seed_attrs_contact( array $c, string $page, string $lang ): array {
	$hero  = (array) ( $c['contact']['hero'] ?? array() );
	$aside = (array) ( $c['contact']['aside'] ?? array() );

	return array(
		'kicker'      => (string) ( $hero['kicker'] ?? '' ),
		'title'       => (string) ( $hero['title'] ?? '' ),
		'lead'        => (string) ( $hero['lead'] ?? '' ),
		'asideTitle'  => (string) ( $aside['title'] ?? '' ),
		'steps'       => array_values( array_map( 'strval', (array) ( $aside['steps'] ?? array() ) ) ),
		'directTitle' => (string) ( $aside['directTitle'] ?? '' ),
		'directText'  => (string) ( $aside['directText'] ?? '' ),
	);
}
