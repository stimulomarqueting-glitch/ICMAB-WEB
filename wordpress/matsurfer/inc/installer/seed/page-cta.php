<?php
/**
 * Seed mapper: closing CTA band. Every page has one; the Astro pages call
 * PageCTA with their own finalCta copy and a ctaSource "<page>-final".
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

function mats_seed_attrs_page_cta( array $c, string $page, string $lang ): array {
	$map = array(
		'home'         => $c['home']['finalCta'] ?? array(),
		'product'      => $c['product']['finalCta'] ?? array(),
		'applications' => $c['applicationsPage']['finalCta'] ?? array(),
		'research'     => $c['researchPage']['finalCta'] ?? array(),
		'team'         => $c['teamPage']['finalCta'] ?? array(),
	);
	$cta   = $map[ $page ] ?? ( $c['home']['finalCta'] ?? array() );
	$attrs = array(
		'title'     => $cta['title'] ?? '',
		'text'      => $cta['text'] ?? '',
		'button'    => mats_seed_link( $cta['button'] ?? '', $cta['href'] ?? '/contact' ),
		'ctaSource' => $page . '-final',
	);
	/* the team page's CTA is the only contact block there: centred + email */
	if ( 'team' === $page ) {
		$attrs['centered']      = true;
		$attrs['secondaryLead'] = $cta['emailLead'] ?? '';
		$attrs['secondary']     = array(
			'label' => '',
			'page'  => 'email',
			'url'   => '',
			'query' => '',
			'hash'  => '',
		);
	}
	return $attrs;
}
