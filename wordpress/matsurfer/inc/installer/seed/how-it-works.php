<?php
/**
 * Seed mapper: how it works. Used once in the Astro site, on the product
 * page — <section id="how-it-works" aria-labelledby="how-title"> with
 * product.howItWorks (kicker, title, video{caption, placeholder, playLabel},
 * steps[]). The Astro page plays /video/matsurfer-device.mp4 when the file
 * exists (it does), so the video is imported into the Media Library and
 * stored as {id, url}; without it the section shows the placeholder.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

function mats_seed_attrs_how_it_works( array $c, string $page, string $lang ): array {
	if ( 'product' !== $page ) {
		return array();
	}
	$copy  = $c['product']['howItWorks'] ?? array();
	$video = (array) ( $copy['video'] ?? array() );
	$steps = array();
	foreach ( (array) ( $copy['steps'] ?? array() ) as $step ) {
		$steps[] = array(
			'title' => (string) ( $step['title'] ?? '' ),
			'text'  => (string) ( $step['text'] ?? '' ),
		);
	}
	$video_id    = mats_import_media( 'matsurfer-device.mp4' );
	$video_value = $video_id ? array(
		'id'  => $video_id,
		'url' => (string) wp_get_attachment_url( $video_id ),
	) : array();
	return array(
		'kicker'      => (string) ( $copy['kicker'] ?? '' ),
		'title'       => (string) ( $copy['title'] ?? '' ),
		'video'       => $video_value,
		'caption'     => (string) ( $video['caption'] ?? '' ),
		'playLabel'   => (string) ( $video['playLabel'] ?? '' ),
		'placeholder' => (string) ( $video['placeholder'] ?? '' ),
		'steps'       => $steps,
		'sectionId'   => 'how-it-works',
		'headingId'   => 'how-title',
	);
}
