<?php
/**
 * QA: the wizard's saved state as JSON (reminder notice, finished flag,
 * time of each step's last run), plus the admin language.
 *
 *   MATS_WP_PORT=8913 php /home/user/wpsite-cli.php /home/user/ICMAB-WEB/wordpress/tools/qa/installer-state.php [--notice=show]
 *
 * --notice=show puts the post-activation reminder back (as right after
 * activating the theme) before printing.
 *
 * @package matsurfer-qa
 */

$mats_args = array_slice( $GLOBALS['argv'] ?? array(), 2 );
if ( in_array( '--notice=show', $mats_args, true ) ) {
	$mats_state           = mats_installer_state();
	$mats_state['notice'] = 'show';
	$mats_state['done']   = 0;
	mats_installer_save_state( $mats_state );
}
$mats_state = mats_installer_state();
$mats_auto  = array();
foreach ( mats_installer_auto_steps() as $mats_step ) {
	$mats_auto[ $mats_step ] = mats_installer_status( $mats_step )['state'];
}
echo wp_json_encode(
	array(
		'notice'    => $mats_state['notice'],
		'done'      => (int) $mats_state['done'],
		'times'     => array_map( static fn( $s ) => (int) ( $s['time'] ?? 0 ), $mats_state['steps'] ),
		'status'    => $mats_auto,
		'all_done'  => mats_installer_all_done(),
		'WPLANG'    => get_option( 'WPLANG' ),
		'languages' => get_available_languages(),
		'lock'      => get_option( 'mats_installer_lock' ),
	)
), "\n";
