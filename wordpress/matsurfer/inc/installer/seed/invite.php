<?php
/**
 * Seed mapper: invitation panel ("Have you worked with the device?"). Used
 * once in the Astro site, on the research page, from researchPage.inviteTitle
 * / inviteText; the contact email is added after the text by render.php —
 * <section class="sec hairtop" aria-labelledby="invite-title">.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

function mats_seed_attrs_invite( array $c, string $page, string $lang ): array {
	if ( 'research' !== $page ) {
		return array();
	}
	$t = $c['researchPage'] ?? array();
	return array(
		'title'     => (string) ( $t['inviteTitle'] ?? '' ),
		'text'      => (string) ( $t['inviteText'] ?? '' ),
		'headingId' => 'invite-title',
	);
}
