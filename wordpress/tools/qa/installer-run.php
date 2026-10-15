<?php
/**
 * Runs the MatSurfer setup wizard from the command line (automated tests).
 *
 *   MATS_WP_PORT=8913 php /home/user/wpsite-cli.php \
 *     /home/user/ICMAB-WEB/wordpress/tools/qa/installer-run.php [flags]
 *
 * Flags:
 *   --overwrite      replace the page texts with the originals
 *   --steps=a,b      only these steps (plugins, languages, content, forms, review)
 *   --from=step      start at this step
 *   --smtp           also install WP Mail SMTP
 *   --no-install     never download plugins (only activate installed ones)
 *   --json           print the full result as JSON at the end
 *
 * When a step activates a plugin, the plugin only loads in a new PHP
 * process: the script then re-runs itself (same flags, --from=<next step>).
 * Exit code: 0 when every step ended without errors, 1 otherwise.
 *
 * @package matsurfer-qa
 */

if ( ! function_exists( 'mats_installer_run_all' ) ) {
	fwrite( STDERR, "The MatSurfer theme (with its installer module) is not active.\n" );
	exit( 2 );
}

$mats_args = array_slice( $GLOBALS['argv'] ?? array(), 2 );
$mats_opts = array(
	'overwrite' => in_array( '--overwrite', $mats_args, true ),
	'smtp'      => in_array( '--smtp', $mats_args, true ),
	'install'   => ! in_array( '--no-install', $mats_args, true ),
);
$mats_json = in_array( '--json', $mats_args, true );
$mats_pass = 1;
foreach ( $mats_args as $mats_arg ) {
	if ( str_starts_with( $mats_arg, '--steps=' ) ) {
		$mats_opts['steps'] = array_filter( array_map( 'trim', explode( ',', substr( $mats_arg, 8 ) ) ) );
	} elseif ( str_starts_with( $mats_arg, '--from=' ) ) {
		$mats_opts['from'] = substr( $mats_arg, 7 );
	} elseif ( str_starts_with( $mats_arg, '--pass=' ) ) {
		$mats_pass = max( 1, (int) substr( $mats_arg, 7 ) );
	}
}

$mats_run = mats_installer_run_all( $mats_opts );

$mats_icons = array(
	'ok'    => '[ok]   ',
	'info'  => '[info] ',
	'warn'  => '[warn] ',
	'error' => '[ERROR]',
);
foreach ( $mats_run['steps'] as $mats_step => $mats_result ) {
	printf( "== %s: %s%s\n", $mats_step, $mats_result['ok'] ? 'OK' : 'ERRORS', $mats_result['skipped'] ? ' (skipped)' : '' );
	foreach ( $mats_result['messages'] as $mats_msg ) {
		printf( "   %s %s%s\n", $mats_icons[ $mats_msg['type'] ] ?? '[?]    ', $mats_msg['text'], ! empty( $mats_msg['url'] ) ? ' <' . $mats_msg['url'] . '>' : '' );
	}
}
if ( $mats_json ) {
	echo wp_json_encode( $mats_run, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), "\n";
}

$mats_exit = $mats_run['ok'] ? 0 : 1;

if ( $mats_run['reload'] && $mats_run['next'] ) {
	if ( $mats_pass >= 4 ) {
		fwrite( STDERR, "Stopping: too many reloads.\n" );
		exit( 1 );
	}
	printf( "-- a plugin was activated: continuing from '%s' in a new PHP process\n", $mats_run['next'] );
	$mats_keep = array_filter(
		$mats_args,
		static fn( $a ) => ! str_starts_with( $a, '--from=' ) && ! str_starts_with( $a, '--pass=' )
	);
	$mats_cmd  = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( (string) $GLOBALS['argv'][0] ) . ' ' . escapeshellarg( __FILE__ );
	foreach ( $mats_keep as $mats_arg ) {
		$mats_cmd .= ' ' . escapeshellarg( $mats_arg );
	}
	$mats_cmd .= ' ' . escapeshellarg( '--from=' . $mats_run['next'] ) . ' ' . escapeshellarg( '--pass=' . ( $mats_pass + 1 ) );
	passthru( $mats_cmd, $mats_child ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- QA tool.
	exit( $mats_exit ? $mats_exit : (int) $mats_child );
}
exit( $mats_exit );
