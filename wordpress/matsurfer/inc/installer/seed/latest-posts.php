<?php
/**
 * Seed mapper: "Últimas noticias" (latest news cards). The original site
 * has no news, so no page uses this section by default; when an editor adds
 * it, it starts with these words (the fixed UI strings of the page language)
 * and a button to the News page.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

function mats_seed_attrs_latest_posts( array $c, string $page, string $lang ): array {
	return array(
		'kicker' => mats_t( 'news', $lang ),
		'title'  => mats_t( 'latest_news', $lang ),
		'count'  => '3',
		'cta'    => array(
			'label' => mats_t( 'all_news', $lang ),
			'page'  => 'news',
			'url'   => '',
			'query' => '',
			'hash'  => '',
		),
	);
}
