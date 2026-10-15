<?php
/**
 * admin module QA: language / translation / status of some posts, as JSON.
 *   MATS_WP_PORT=8915 php /home/user/wpsite-cli.php admin-langstate.php 10,11,55
 * With "restore <id>,<id>…" as arguments it puts the QA reading page back
 * (English, published) and re-links the EN/ES Product pages, in case a
 * check failed half-way:
 *   … admin-langstate.php restore 10,11,55
 *
 * @package matsurfer-qa
 */

global $argv;
$mats_qa_args = array_slice( (array) $argv, 2 );
$mats_qa_mode = 'restore' === ( $mats_qa_args[0] ?? '' ) ? array_shift( $mats_qa_args ) : 'show';
$mats_qa_ids  = array_filter( array_map( 'intval', explode( ',', (string) ( $mats_qa_args[0] ?? '' ) ) ) );

if ( 'restore' === $mats_qa_mode && count( $mats_qa_ids ) >= 3 ) {
	list( $mats_qa_en, $mats_qa_es, $mats_qa_reading ) = array_values( $mats_qa_ids );
	pll_set_post_language( $mats_qa_en, 'en' );
	pll_set_post_language( $mats_qa_es, 'es' );
	pll_save_post_translations(
		array(
			'en' => $mats_qa_en,
			'es' => $mats_qa_es,
		)
	);
	pll_set_post_language( $mats_qa_reading, 'en' );
	pll_save_post_translations( array( 'en' => $mats_qa_reading ) );
	wp_update_post(
		array(
			'ID'          => $mats_qa_reading,
			'post_status' => 'publish',
		)
	);
}

$mats_qa_out = array();
foreach ( $mats_qa_ids as $mats_qa_id ) {
	$mats_qa_post               = get_post( $mats_qa_id );
	$mats_qa_out[ $mats_qa_id ] = array(
		'lang'   => (string) pll_get_post_language( $mats_qa_id ),
		'tr'     => pll_get_post_translations( $mats_qa_id ),
		'status' => $mats_qa_post ? $mats_qa_post->post_status : '',
		'slug'   => $mats_qa_post ? $mats_qa_post->post_name : '',
		'parent' => $mats_qa_post ? (int) $mats_qa_post->post_parent : 0,
	);
}
echo wp_json_encode( $mats_qa_out );
