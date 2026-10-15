<?php
/**
 * QA (installer fix pass): prepares a cloned, fully set-up instance.
 *
 *   MATS_WP_PORT=8984 php /home/user/wpsite-cli.php wordpress/tools/qa/fix-installer-setup.php [--editor] [--done] [--pll-off] [--pll-on] [--report]
 *
 *   --editor   creates the Editor «qa-editor» / qa-editor-pass (role Editor)
 *   --done     runs step 5 («Dar la puesta en marcha por terminada»)
 *   --pll-off  deactivates Polylang (as an administrator troubleshooting would)
 *   --pll-on   activates it again
 *   --report   revisions per structural page, site icon, wizard state
 *
 * @package matsurfer-qa
 */

$mats_args = array_slice( $argv, 2 );

if ( in_array( '--editor', $mats_args, true ) && ! get_user_by( 'login', 'qa-editor' ) ) {
	$mats_uid = wp_insert_user(
		array(
			'user_login'   => 'qa-editor',
			'user_pass'    => 'qa-editor-pass',
			'user_email'   => 'qa-editor@example.test',
			'display_name' => 'QA Editor',
			'role'         => 'editor',
		)
	);
	echo 'editor: ', is_wp_error( $mats_uid ) ? $mats_uid->get_error_message() : 'created ' . $mats_uid, "\n";
}
if ( in_array( '--done', $mats_args, true ) ) {
	$mats_r = mats_installer_run_step( 'review', array() );
	echo 'review step: ', $mats_r['ok'] ? 'ok' : 'error', "\n";
}
if ( in_array( '--pll-off', $mats_args, true ) ) {
	deactivate_plugins( 'polylang/polylang.php' );
	echo 'polylang: deactivated', "\n";
}
if ( in_array( '--pll-on', $mats_args, true ) ) {
	$mats_on = activate_plugin( 'polylang/polylang.php' );
	echo 'polylang: ', is_wp_error( $mats_on ) ? $mats_on->get_error_message() : 'activated', "\n";
}
if ( in_array( '--report', $mats_args, true ) ) {
	$mats_ids = get_posts(
		array(
			'post_type'        => 'page',
			'post_status'      => 'any',
			'numberposts'      => 50,
			'fields'           => 'ids',
			'meta_key'         => '_mats_role', // phpcs:ignore WordPress.DB.SlowDBQuery
			'suppress_filters' => true,
			'lang'             => '',
		)
	);
	$mats_none = array();
	foreach ( $mats_ids as $mats_id ) {
		if ( ! wp_get_post_revisions( $mats_id ) ) {
			$mats_none[] = $mats_id;
		}
	}
	echo 'structural pages: ', count( $mats_ids ), ' | without a revision: ', $mats_none ? implode( ',', $mats_none ) : 'none', "\n";
	$mats_icon = (int) get_option( 'site_icon' );
	echo 'site_icon: ', $mats_icon, $mats_icon ? ' (' . get_post_meta( $mats_icon, '_mats_media_source', true ) . ')' : '', "\n";
	$mats_state = mats_installer_state();
	echo 'wizard done: ', $mats_state['done'] ? 'yes' : 'no', ' | spanish offline: ', mats_installer_spanish_offline() ? 'yes' : 'no', "\n";
	foreach ( array_keys( mats_installer_steps() ) as $mats_key ) {
		$mats_st = mats_installer_status( $mats_key );
		echo '  ', $mats_key, ': ', $mats_st['state'], ' (', $mats_st['label'], ')', "\n";
	}
}
