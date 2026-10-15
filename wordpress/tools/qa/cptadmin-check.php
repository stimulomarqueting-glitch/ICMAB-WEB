<?php
/**
 * QA (module "cptadmin"): prints what the getters return, as JSON, so the
 * Playwright test can compare it with what was typed in the admin.
 *   MATS_WP_PORT=8918 php /home/user/wpsite-cli.php wordpress/tools/qa/cptadmin-check.php [lang]
 * Output: { team, applications, publications, experts, raw: { type: [ {id,title,status,order,meta} ] } }
 */

$lang = isset( $argv[2] ) && in_array( $argv[2], array( 'en', 'es' ), true ) ? $argv[2] : 'es';
$out  = array(
	'lang'         => $lang,
	'team'         => mats_get_team( $lang ),
	'applications' => mats_get_applications( $lang ),
	'publications' => mats_get_publications( $lang ),
	'experts'      => mats_get_experts( $lang ),
	'raw'          => array(),
);
foreach ( array_keys( mats_cpt_fields() ) as $type ) {
	foreach ( get_posts( array( 'post_type' => $type, 'post_status' => array( 'publish', 'draft', 'pending', 'future', 'private' ), 'posts_per_page' => -1, 'orderby' => array( 'menu_order' => 'ASC', 'date' => 'ASC' ), 'suppress_filters' => true ) ) as $p ) {
		$meta = array();
		foreach ( get_post_meta( $p->ID ) as $k => $v ) {
			if ( str_starts_with( $k, '_mats_' ) ) {
				$meta[ $k ] = $v[0];
			}
		}
		$out['raw'][ $type ][] = array(
			'id'       => $p->ID,
			'title'    => $p->post_title,
			'status'   => $p->post_status,
			'order'    => (int) $p->menu_order,
			'password' => $p->post_password,
			'thumb'    => (int) get_post_thumbnail_id( $p ),
			'meta'     => $meta,
		);
	}
}
echo wp_json_encode( $out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
