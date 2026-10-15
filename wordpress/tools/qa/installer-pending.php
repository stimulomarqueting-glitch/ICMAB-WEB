<?php
/**
 * QA: sections created empty (their seed mapper did not exist yet) are
 * filled on a later run of the content step, WITHOUT touching sections an
 * editor filled by hand meanwhile, nor the rest of the page.
 *
 *   MATS_WP_PORT=8913 php /home/user/wpsite-cli.php /home/user/ICMAB-WEB/wordpress/tools/qa/installer-pending.php
 *
 * Uses a temporary in-process mapper for one block that has none yet (so it
 * only works while such a block exists), and edits one ES section by hand
 * first. Afterwards restore the test site with:
 *   … installer-run.php --steps=content --overwrite   (in a NEW process)
 *
 * @package matsurfer-qa
 */

$mats_role  = 'applications';
$mats_specs = mats_installer_page_specs();
$mats_block = '';
foreach ( $mats_specs[ $mats_role ]['blocks'] as $mats_b ) {
	if ( ! is_readable( MATS_DIR . '/inc/installer/seed/' . $mats_b . '.php' ) && ! function_exists( 'mats_seed_attrs_' . str_replace( '-', '_', $mats_b ) ) ) {
		$mats_block = $mats_b;
		break;
	}
}
if ( ! $mats_block ) {
	echo "SKIP: every section of the applications page already has its mapper.\n";
	exit( 0 );
}
echo "testing with section '$mats_block'\n";

$mats_en = mats_page_id( $mats_role, 'en' );
$mats_es = mats_page_id( $mats_role, 'es' );
$mats_get = static function ( int $id, string $block ) {
	foreach ( parse_blocks( (string) get_post_field( 'post_content', $id ) ) as $b ) {
		if ( 'matsurfer/' . $block === $b['blockName'] ) {
			return $b['attrs'];
		}
	}
	return null;
};
$mats_before_hero = $mats_get( $mats_en, 'page-hero' );
echo 'pending before: EN ', wp_json_encode( get_post_meta( $mats_en, '_mats_seed_pending', true ) ), ' ES ', wp_json_encode( get_post_meta( $mats_es, '_mats_seed_pending', true ) ), "\n";

/* an editor fills the ES section by hand */
$mats_blocks = parse_blocks( (string) get_post_field( 'post_content', $mats_es ) );
foreach ( $mats_blocks as $mats_i => $mats_b ) {
	if ( 'matsurfer/' . $mats_block === $mats_b['blockName'] ) {
		$mats_blocks[ $mats_i ]['attrs']['title'] = 'Escrito a mano por un editor';
	}
}
wp_update_post(
	wp_slash(
		array(
			'ID'           => $mats_es,
			'post_content' => serialize_blocks( $mats_blocks ),
		)
	)
);

/* the mapper "arrives" */
$mats_tmp = wp_tempnam( 'mats-qa-mapper' );
file_put_contents( $mats_tmp, '<?php function mats_seed_attrs_' . str_replace( '-', '_', $mats_block ) . '( array $c, string $page, string $lang ): array { return array( "title" => "QA filled " . $lang ); }' );
require $mats_tmp;
wp_delete_file( $mats_tmp );

$mats_result = mats_installer_run_step( 'content', array() );
foreach ( $mats_result['messages'] as $mats_msg ) {
	if ( false !== strpos( $mats_msg['text'], 'Secciones que estaban vacías' ) ) {
		echo 'message: ', $mats_msg['text'], "\n";
	}
}
$mats_en_attrs = $mats_get( $mats_en, $mats_block );
$mats_es_attrs = $mats_get( $mats_es, $mats_block );
$mats_fail     = 0;
$mats_check    = static function ( bool $ok, string $what ) use ( &$mats_fail ) {
	echo ( $ok ? '  ok   ' : '  FAIL ' ), $what, "\n";
	$mats_fail += $ok ? 0 : 1;
};
$mats_check( 'QA filled en' === ( $mats_en_attrs['title'] ?? '' ), 'EN section filled by the new mapper: ' . wp_json_encode( $mats_en_attrs ) );
$mats_check( ! empty( $mats_en_attrs['lock']['remove'] ), 'EN section still locked' );
$mats_check( 'Escrito a mano por un editor' === ( $mats_es_attrs['title'] ?? '' ), 'ES section filled by hand kept: ' . wp_json_encode( $mats_es_attrs ) );
$mats_check( $mats_before_hero === $mats_get( $mats_en, 'page-hero' ), 'rest of the EN page untouched' );
$mats_check( ! in_array( $mats_block, (array) get_post_meta( $mats_en, '_mats_seed_pending', true ), true ), 'EN pending list updated: ' . wp_json_encode( get_post_meta( $mats_en, '_mats_seed_pending', true ) ) );
$mats_check( ! in_array( $mats_block, (array) get_post_meta( $mats_es, '_mats_seed_pending', true ), true ), 'ES pending list updated: ' . wp_json_encode( get_post_meta( $mats_es, '_mats_seed_pending', true ) ) );
echo $mats_fail ? "FAIL\n" : "PASS\n";
exit( $mats_fail ? 1 : 0 );
