<?php
/**
 * QA: the setup wizard's run lock (mats_installer_lock / mats_installer_unlock).
 *
 *   MATS_WP_PORT=8913 php /home/user/wpsite-cli.php /home/user/ICMAB-WEB/wordpress/tools/qa/installer-lock.php [--mysql] [--children=12]
 *
 * Default: this port's SQLite database. --mysql: the same tests on the real
 * MariaDB server (like Hostinger), in a scratch table 'matsqa8913_options'
 * of the otherwise empty 'wp_mysql' database (dropped at the end; no
 * instance uses that database).
 *
 *  1. semantics: take → held → stale (> 5 min) taken over → released
 *  2. compare-and-swap: when somebody else changes a stale lock between our
 *     read and our write, we do NOT get it
 *  3. concurrency: N PHP processes ask for the lock at the same instant:
 *     exactly ONE gets it. The old add_option() implementation is run the
 *     same way for comparison (--old in the children).
 *
 * @package matsurfer-qa
 */

$mats_args  = array_slice( $GLOBALS['argv'] ?? array(), 2 );
$mats_mysql = in_array( '--mysql', $mats_args, true );
$mats_child = in_array( '--child', $mats_args, true );
$mats_old   = in_array( '--old', $mats_args, true );
$mats_at    = 0.0;
$mats_n     = 12;
foreach ( $mats_args as $mats_arg ) {
	if ( str_starts_with( $mats_arg, '--at=' ) ) {
		$mats_at = (float) substr( $mats_arg, 5 );
	} elseif ( str_starts_with( $mats_arg, '--children=' ) ) {
		$mats_n = max( 2, (int) substr( $mats_arg, 11 ) );
	}
}
$mats_port = preg_replace( '/[^0-9]/', '', (string) getenv( 'MATS_WP_PORT' ) );
if ( ! $mats_port || in_array( $mats_port, array( '8881', '8950', '8960', '8961' ), true ) ) {
	fwrite( STDERR, "Run it on YOUR instance port.\n" );
	exit( 2 );
}

/* --mysql: point $wpdb at a scratch options table on MariaDB */
if ( $mats_mysql ) {
	$mats_sqlite_db = $GLOBALS['wpdb'];
	$mats_my        = new wpdb( 'wp', 'wp-local-only', 'wp_mysql', '127.0.0.1' );
	$mats_my->set_prefix( 'matsqa' . $mats_port . '_' );
	$mats_my->suppress_errors( false );
	if ( ! $mats_child ) {
		$mats_my->query( "CREATE TABLE IF NOT EXISTS {$mats_my->options} ( option_id bigint(20) unsigned NOT NULL auto_increment, option_name varchar(191) NOT NULL default '', option_value longtext NOT NULL, autoload varchar(20) NOT NULL default 'yes', PRIMARY KEY (option_id), UNIQUE KEY option_name (option_name), KEY autoload (autoload) ) DEFAULT CHARSET=utf8mb4" );
		/* a real options table always has autoloaded rows (with none, WordPress would autoload ALL options) */
		$mats_my->query( "INSERT IGNORE INTO {$mats_my->options} (option_name, option_value, autoload) VALUES ('matsqa_dummy', '1', 'yes')" );
	}
	$GLOBALS['wpdb'] = $mats_my; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	wp_cache_flush();
}

/** The previous implementation (add_option check-then-write), for comparison. */
function mats_qa_old_lock(): bool {
	if ( add_option( 'mats_installer_lock', time(), '', false ) ) {
		return true;
	}
	$since = (int) get_option( 'mats_installer_lock' );
	if ( ! $since || time() - $since > 5 * MINUTE_IN_SECONDS ) {
		update_option( 'mats_installer_lock', time(), false );
		return true;
	}
	return false;
}

if ( $mats_child ) {
	while ( microtime( true ) < $mats_at ) {
		usleep( 200 );
	}
	$GLOBALS['wpdb']->suppress_errors( true );
	echo ( $mats_old ? mats_qa_old_lock() : mats_installer_lock() ) ? '1' : '0';
	if ( $GLOBALS['wpdb']->last_error ) {
		echo ' ERR:', $GLOBALS['wpdb']->last_error;
	}
	exit( 0 );
}

$mats_fail = 0;
function mats_qa( bool $ok, string $what ): void {
	global $mats_fail;
	echo ( $ok ? '  PASS ' : '  FAIL ' ), $what, "\n";
	if ( ! $ok ) {
		++$mats_fail;
	}
}
function mats_qa_row() {
	global $wpdb;
	return $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'mats_installer_lock' ) );
}
function mats_qa_rows(): int {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s", 'mats_installer_lock' ) );
}
function mats_qa_set( string $value ): void {
	global $wpdb;
	$wpdb->update( $wpdb->options, array( 'option_value' => $value ), array( 'option_name' => 'mats_installer_lock' ) );
	wp_cache_delete( 'mats_installer_lock', 'options' );
}

echo 'database: ', $mats_mysql ? 'MariaDB ' . $GLOBALS['wpdb']->db_version() . " (scratch table {$GLOBALS['wpdb']->options})" : 'SQLite (port ' . $mats_port . ')', "\n";

echo "\n== 1. semantics\n";
mats_installer_unlock();
mats_qa( null === mats_qa_row(), 'no lock row at start' );
mats_qa( true === mats_installer_lock(), 'first take → true' );
mats_qa( abs( (int) mats_qa_row() - time() ) <= 2, 'lock row holds the current time' );
mats_qa( false === mats_installer_lock(), 'second take while held → false' );
mats_qa( false === get_option( 'mats_installer_lock' ) ? false : true, 'get_option() sees the lock (cache kept in sync)' );
mats_qa_set( (string) ( time() - 299 ) );
mats_qa( false === mats_installer_lock(), 'lock 4 min 59 s old → still held → false' );
mats_qa_set( (string) ( time() - 301 ) );
mats_qa( true === mats_installer_lock(), 'lock 5 min 1 s old (stale) → taken over → true' );
mats_qa( abs( (int) mats_qa_row() - time() ) <= 2 && 1 === mats_qa_rows(), 'takeover rewrote the time, still one row' );
mats_qa( false === mats_installer_lock(), 'after takeover → held → false' );
mats_qa_set( 'garbage' );
mats_qa( true === mats_installer_lock(), 'unreadable value counts as stale → true' );
mats_installer_unlock();
$mats_after = array( mats_qa_row(), get_option( 'mats_installer_lock' ) );
mats_qa( null === $mats_after[0] && false === $mats_after[1], 'unlock removes the row (and the cached value): ' . wp_json_encode( $mats_after ) );
mats_qa( true === mats_installer_lock(), 'free again → true' );
mats_installer_unlock();

echo "\n== 2. compare-and-swap on a stale lock\n";
mats_installer_lock();
mats_qa_set( (string) ( time() - 900 ) );
$mats_cas = static function ( $sql ) {
	/* somebody else takes the stale lock between our SELECT and our UPDATE */
	if ( str_starts_with( ltrim( $sql ), 'UPDATE' ) && str_contains( $sql, 'mats_installer_lock' ) && str_contains( $sql, 'AND option_value' ) ) {
		global $wpdb;
		remove_all_filters( 'query' );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = 'mats_installer_lock'", (string) ( time() - 1 ) ) );
	}
	return $sql;
};
add_filter( 'query', $mats_cas );
mats_qa( false === mats_installer_lock(), 'the other request won the stale lock → we get false' );
remove_all_filters( 'query' );
mats_qa( abs( (int) mats_qa_row() - ( time() - 1 ) ) <= 2, 'the winner\'s time is kept (not overwritten)' );
mats_installer_unlock();

echo "\n== 3. concurrency: $mats_n processes at the same instant\n";
foreach ( array( 'new' => false, 'old' => true ) as $mats_label => $mats_use_old ) {
	$mats_runs = 5;
	$mats_bad  = 0;
	$mats_errs = array();
	for ( $mats_i = 0; $mats_i < $mats_runs; $mats_i++ ) {
		mats_installer_unlock();
		$mats_at    = microtime( true ) + 4;
		$mats_procs = array();
		for ( $mats_c = 0; $mats_c < $mats_n; $mats_c++ ) {
			$mats_cmd     = 'MATS_WP_PORT=' . $mats_port . ' ' . escapeshellarg( PHP_BINARY ) . ' /home/user/wpsite-cli.php ' . escapeshellarg( __FILE__ ) . ' --child --at=' . sprintf( '%.6F', $mats_at ) . ( $mats_mysql ? ' --mysql' : '' ) . ( $mats_use_old ? ' --old' : '' );
			$mats_procs[] = popen( $mats_cmd . ' 2>&1', 'r' );
		}
		$mats_wins = 0;
		foreach ( $mats_procs as $mats_p ) {
			$mats_out = trim( (string) stream_get_contents( $mats_p ) );
			pclose( $mats_p );
			if ( str_starts_with( $mats_out, '1' ) ) {
				++$mats_wins;
			}
			if ( str_contains( $mats_out, 'ERR:' ) || ! preg_match( '/^[01]/', $mats_out ) ) {
				$mats_errs[] = substr( $mats_out, 0, 160 );
			}
		}
		if ( 1 !== $mats_wins ) {
			++$mats_bad;
		}
		echo "   $mats_label implementation, round ", $mats_i + 1, ": $mats_wins winner(s)\n";
	}
	if ( 'new' === $mats_label ) {
		mats_qa( 0 === $mats_bad, "new lock: exactly one winner in each of $mats_runs rounds" );
		mats_qa( ! $mats_errs, 'no database errors in the children' . ( $mats_errs ? ': ' . implode( ' | ', array_slice( array_unique( $mats_errs ), 0, 3 ) ) : '' ) );
	} else {
		echo '   (old add_option() lock: ', $mats_bad, " of $mats_runs rounds had more than one winner — for comparison, not a test)\n";
	}
}
mats_installer_unlock();

if ( $mats_mysql ) {
	$GLOBALS['wpdb']->query( "DROP TABLE IF EXISTS {$GLOBALS['wpdb']->options}" );
	$GLOBALS['wpdb'] = $mats_sqlite_db; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	wp_cache_flush();
	echo "(scratch table dropped)\n";
}
echo $mats_fail ? "\n$mats_fail FAILED\n" : "\nALL PASS\n";
exit( $mats_fail ? 1 : 0 );
