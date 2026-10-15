<?php
/**
 * QA for the "leads" module: GDPR eraser, automatic deletion cron,
 * capability grant (activation + lazy), privacy policy suggestion.
 * Run: MATS_WP_PORT=8912 php /home/user/wpsite-cli.php wordpress/tools/qa/leads-privacy.php
 * (non-destructive for the leads created by leads-setup.php except its own test leads)
 */

$checks = array();
$ok     = static function ( string $name, bool $pass, $info = '' ) use ( &$checks ) {
	$checks[] = array( $pass ? 'PASS' : 'FAIL', $name, is_scalar( $info ) ? (string) $info : wp_json_encode( $info ) );
};

/* ── eraser ─────────────────────────────────────────────────────────── */
$a     = mats_lead_save( array( 'name' => 'Erase Me', 'email' => 'Erase.Me@example.test', 'message' => 'first', 'language' => 'en' ) );
$b     = mats_lead_save( array( 'name' => 'Erase Me', 'email' => 'erase.me@example.test', 'message' => 'second', 'language' => 'es' ) );
$other = mats_lead_save( array( 'name' => 'Keep Me', 'email' => 'keep.me@example.test', 'message' => 'stay', 'language' => 'en' ) );
$erasers = apply_filters( 'wp_privacy_personal_data_erasers', array() );
$export  = mats_leads_privacy_export( 'erase.me@example.test', 1 );
$ok( 'exporter: 2 items for the email', 2 === count( $export['data'] ), count( $export['data'] ) );
$res = call_user_func( $erasers['matsurfer-leads']['callback'], 'ERASE.me@example.test', 1 );
$ok( 'eraser: removed + done', true === $res['items_removed'] && false === $res['items_retained'] && $res['done'], $res );
$ok( 'eraser: message in Spanish', isset( $res['messages'][0] ) && str_contains( $res['messages'][0], '2 mensajes' ), $res['messages'] );
$ok( 'eraser: posts gone (and meta)', ! get_post( $a ) && ! get_post( $b ) && ! get_post_meta( $a ) );
$ok( 'eraser: other lead kept', (bool) mats_lead_get( $other ) );
$res2 = mats_leads_privacy_erase( 'nobody@example.test', 1 );
$ok( 'eraser: unknown email → nothing, done', false === $res2['items_removed'] && $res2['done'] );

/* ── retention + cron ───────────────────────────────────────────────── */
update_option( 'mats_leads_retention_months', 12, true );
mats_leads_sync_cron();
$ok( 'cron scheduled when retention on', (bool) wp_next_scheduled( 'mats_leads_cleanup' ) );
$schedule = wp_get_schedule( 'mats_leads_cleanup' );
$ok( 'cron daily', 'daily' === $schedule, $schedule );
$old = mats_lead_save( array( 'name' => 'Ancient', 'email' => 'ancient@example.test', 'message' => 'x', 'language' => 'en' ) );
$gmt = gmdate( 'Y-m-d H:i:s', strtotime( '-13 months' ) );
wp_update_post( array( 'ID' => $old, 'post_date' => get_date_from_gmt( $gmt ), 'post_date_gmt' => $gmt ) );
$recent = mats_lead_save( array( 'name' => 'Recent', 'email' => 'recent@example.test', 'message' => 'x', 'language' => 'en' ) );
$gmt2   = gmdate( 'Y-m-d H:i:s', strtotime( '-11 months' ) );
wp_update_post( array( 'ID' => $recent, 'post_date' => get_date_from_gmt( $gmt2 ), 'post_date_gmt' => $gmt2 ) );
$expected = count(
	get_posts(
		array(
			'post_type'        => 'mats_lead',
			'post_status'      => 'any',
			'numberposts'      => -1,
			'fields'           => 'ids',
			'suppress_filters' => true,
			'date_query'       => array( array( 'column' => 'post_date_gmt', 'before' => gmdate( 'Y-m-d H:i:s', strtotime( '-12 months' ) ) ) ),
		)
	)
);
do_action( 'mats_leads_cleanup' ); // what WP-Cron runs every day
$ok( 'cleanup: 13-month lead deleted', ! get_post( $old ) );
$ok( 'cleanup: 11-month lead kept', (bool) get_post( $recent ) );
$last = get_option( 'mats_leads_cleanup_last' );
$ok( 'cleanup: last run recorded', is_array( $last ) && $expected === (int) $last['deleted'] && $expected >= 1 && 12 === (int) $last['months'], array( $last, $expected ) );
update_option( 'mats_leads_retention_months', 0, true );
mats_leads_sync_cron();
$ok( 'cron cleared when retention off', ! wp_next_scheduled( 'mats_leads_cleanup' ) );
$ok( 'cleanup does nothing when off', 0 === mats_leads_cleanup() && (bool) get_post( $recent ) );
wp_delete_post( $recent, true );
wp_delete_post( $other, true );

/* ── capabilities: lazy grant + activation ──────────────────────────── */
get_role( 'editor' )->remove_cap( 'mats_manage_leads' );
get_role( 'author' )->add_cap( 'mats_manage_leads' ); // e.g. a role plugin gave it away
delete_option( 'mats_leads_caps_version' );
mats_leads_ensure_caps(); // what the next admin request does (init, priority 1)
$ok( 'lazy: editor re-granted', get_role( 'editor' )->has_cap( 'mats_manage_leads' ) );
$ok( 'lazy: author denied again', ! get_role( 'author' )->has_cap( 'mats_manage_leads' ) );
$ok( 'lazy: version stored', MATS_LEADS_CAPS_VERSION === get_option( 'mats_leads_caps_version' ) );
get_role( 'administrator' )->remove_cap( 'mats_manage_leads' );
mats_leads_ensure_caps();
$ok( 'admins always get it back', get_role( 'administrator' )->has_cap( 'mats_manage_leads' ) && current_user_can( 'mats_manage_leads' ) );
get_role( 'editor' )->remove_cap( 'mats_manage_leads' );
do_action( 'after_switch_theme', 'twentytwentyfive', wp_get_theme( 'twentytwentyfive' ) );
$ok( 'activation grants editor', get_role( 'editor' )->has_cap( 'mats_manage_leads' ) );

/* ── privacy policy suggestion ──────────────────────────────────────── */
do_action( 'admin_init' );
$policy = '';
if ( class_exists( 'WP_Privacy_Policy_Content' ) ) {
	$prop = new ReflectionProperty( 'WP_Privacy_Policy_Content', 'policy_content' );
	$prop->setAccessible( true );
	$policy = wp_json_encode( $prop->getValue(), JSON_UNESCAPED_UNICODE );
}
$ok( 'privacy policy suggestion added', str_contains( $policy, 'MatSurfer' ) && str_contains( $policy, 'No se guarda la direcci' ), strlen( $policy ) );

$fail = 0;
foreach ( $checks as $c ) {
	echo $c[0] . '  ' . $c[1] . ( 'FAIL' === $c[0] && '' !== $c[2] ? '  → ' . $c[2] : '' ) . "\n";
	$fail += 'FAIL' === $c[0] ? 1 : 0;
}
echo $fail ? "\n$fail FAILED\n" : "\nALL PASSED (" . count( $checks ) . ")\n";
