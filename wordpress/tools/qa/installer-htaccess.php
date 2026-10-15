<?php
/**
 * QA: step 2 notices when an Apache / LiteSpeed server (Hostinger) did not
 * get WordPress's rewrite rules in .htaccess (clean addresses would 404).
 *
 *   MATS_WP_PORT=8913 php /home/user/wpsite-cli.php /home/user/ICMAB-WEB/wordpress/tools/qa/installer-htaccess.php
 *
 * The local server is PHP's built-in one (no .htaccess), so Apache is
 * simulated ($is_apache = true) and WordPress is stopped from writing the
 * file ('flush_rewrite_rules_hard' → false) — exactly what happens on a
 * host where .htaccess is not writable — without ever writing into the
 * shared /home/user/wpsite folder (checked at the end).
 *
 * @package matsurfer-qa
 */

$mats_fail = 0;
function mats_qa( bool $ok, string $what ): void {
	global $mats_fail;
	echo ( $ok ? '  PASS ' : '  FAIL ' ), $what, "\n";
	if ( ! $ok ) {
		++$mats_fail;
	}
}

$mats_tmp = '/tmp/claude-0/-home-user-ICMAB-WEB/ae5d27f5-1af0-537f-966f-1d2257e6cfbd/scratchpad/installer/htaccess';
@mkdir( $mats_tmp, 0777, true ); // phpcs:ignore
$mats_shared = ABSPATH . '.htaccess';
$mats_existed = file_exists( $mats_shared );

echo "== 1. detecting WordPress's rules in a .htaccess file\n";
file_put_contents( "$mats_tmp/none.htaccess", "Options -Indexes\n" );
file_put_contents( "$mats_tmp/empty.htaccess", "# BEGIN WordPress\n# END WordPress\n" );
file_put_contents( "$mats_tmp/ok.htaccess", "# BEGIN WordPress\n<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteBase /\nRewriteRule ^index\\.php$ - [L]\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule . /index.php [L]\n</IfModule>\n# END WordPress\n" );
mats_qa( false === mats_installer_htaccess_has_rules( "$mats_tmp/missing.htaccess" ), 'missing file → no rules' );
mats_qa( false === mats_installer_htaccess_has_rules( "$mats_tmp/none.htaccess" ), 'file without the WordPress block → no rules' );
mats_qa( false === mats_installer_htaccess_has_rules( "$mats_tmp/empty.htaccess" ), 'empty WordPress block → no rules' );
mats_qa( true === mats_installer_htaccess_has_rules( "$mats_tmp/ok.htaccess" ), 'WordPress block with RewriteRule → rules present' );

echo "\n== 2. PHP built-in server (no .htaccess used)\n";
mats_qa( '' === mats_installer_htaccess_problem(), 'no problem reported when the server is not Apache' );

echo "\n== 3. Apache / LiteSpeed where WordPress cannot write .htaccess\n";
$GLOBALS['is_apache'] = true; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
add_filter( 'flush_rewrite_rules_hard', '__return_false', 99 );
mats_qa( '' !== mats_installer_htaccess_problem(), 'problem detected: ' . mats_installer_htaccess_problem() );
$mats_r = mats_installer_run_step( 'languages', array() );
$mats_w = array_values( array_filter( $mats_r['messages'], static fn( $m ) => 'warn' === $m['type'] && str_contains( $m['text'], '.htaccess' ) ) );
echo '   ', $mats_w ? $mats_w[0]['text'] . ' <' . ( $mats_w[0]['url'] ?? '' ) . '>' : '(no warning)', "\n";
mats_qa( (bool) $mats_w, 'step 2 adds a warning about .htaccess' );
mats_qa( $mats_w && str_contains( (string) ( $mats_w[0]['url'] ?? '' ), 'options-permalink.php' ), 'with a link to Ajustes → Enlaces permanentes' );
mats_qa( (bool) $mats_r['ok'], 'it is a warning, not an error (the rest of the step is done)' );
$mats_s = mats_installer_status( 'languages' );
$mats_c = array_values( array_filter( $mats_s['checks'], static fn( $c ) => str_contains( $c['text'], '.htaccess' ) ) );
mats_qa( 'todo' === $mats_s['state'] && $mats_c && false === $mats_c[0]['ok'], 'status: step 2 shows ✗ with the .htaccess check, instead of a false ✓' );

echo "\n== 4. back to the real server\n";
remove_filter( 'flush_rewrite_rules_hard', '__return_false', 99 );
$GLOBALS['is_apache'] = false; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
mats_qa( 'done' === mats_installer_status( 'languages' )['state'], 'status ✓ again' );
mats_qa( file_exists( $mats_shared ) === $mats_existed, 'the shared ' . $mats_shared . ' was not created/removed' );

echo $mats_fail ? "\n$mats_fail FAILED\n" : "\nALL PASS\n";
exit( $mats_fail ? 1 : 0 );
