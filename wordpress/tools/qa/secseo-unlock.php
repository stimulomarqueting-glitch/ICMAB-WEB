<?php
/** QA (secseo): removes every login-lock counter (transients mats_login_*). */
global $wpdb;
$mats_rows = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_mats\_login\_%'" );
foreach ( $mats_rows as $mats_row ) {
	delete_transient( substr( $mats_row, strlen( '_transient_' ) ) );
}
echo 'login counters removed: ', count( $mats_rows ), "\n";
