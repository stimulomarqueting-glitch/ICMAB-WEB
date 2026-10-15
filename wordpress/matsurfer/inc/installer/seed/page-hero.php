<?php
/**
 * Seed mapper: interior page hero (PageHero.astro).
 *   product      → product.hero + CTA (ctaSource "product-hero") + photo product-table.jpg
 *   applications → applicationsPage.hero (with kickerNote), no CTA, no photo
 *   research     → researchPage kicker/title/lead
 *   team         → teamPage kicker/title/lead (Astro's fallback when there are no profiles)
 * Every Astro call uses the default heading id "page-title".
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

function mats_seed_attrs_page_hero( array $c, string $page, string $lang ): array {
	$attrs = array(
		'kicker'     => '',
		'kickerNote' => '',
		'title'      => '',
		'lead'       => '',
		'headingId'  => 'page-title',
	);

	switch ( $page ) {
		case 'product':
			$hero               = $c['product']['hero'] ?? array();
			$attrs['kicker']    = $hero['kicker'] ?? '';
			$attrs['title']     = $hero['title'] ?? '';
			$attrs['lead']      = $hero['lead'] ?? '';
			$attrs['cta']       = mats_seed_link( $hero['cta'] ?? '', $hero['ctaHref'] ?? '/contact?interest=product' );
			$attrs['ctaSource'] = 'product-hero';
			$attrs['photo']     = mats_seed_image( 'product-table.jpg', $hero['photoAlt'] ?? '' );
			break;

		case 'applications':
			$hero                = $c['applicationsPage']['hero'] ?? array();
			$attrs['kicker']     = $hero['kicker'] ?? '';
			$attrs['kickerNote'] = $hero['kickerNote'] ?? '';
			$attrs['title']      = $hero['title'] ?? '';
			$attrs['lead']       = $hero['lead'] ?? '';
			break;

		case 'research':
			$t               = $c['researchPage'] ?? array();
			$attrs['kicker'] = $t['kicker'] ?? '';
			$attrs['title']  = $t['title'] ?? '';
			$attrs['lead']   = $t['lead'] ?? '';
			break;

		case 'team':
			$t               = $c['teamPage'] ?? array();
			$attrs['kicker'] = $t['kicker'] ?? '';
			$attrs['title']  = $t['title'] ?? '';
			$attrs['lead']   = $t['lead'] ?? '';
			break;
	}

	return $attrs;
}
