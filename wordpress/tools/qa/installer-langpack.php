<?php
/**
 * QA: the "WordPress admin in Spanish" part of setup step 2 (languages).
 *
 *   MATS_WP_PORT=8913 php /home/user/ICMAB-WEB/wordpress/tools/qa/installer-langpack.php [--offline] [--keep]
 *
 * Default (online, simulated): wordpress.org is replaced by core filters —
 * 'translations_api' answers like api.wordpress.org/translations and
 * 'upgrader_pre_download' hands over small local language packs (es_ES core
 * + Polylang) built here — so the REAL code path runs: wp_download_language_pack()
 * → Language_Pack_Upgrader → WPLANG = es_ES → plugin translations through
 * translations_api() + Language_Pack_Upgrader::bulk_upgrade(). A second run
 * must download nothing. Then a new PHP process checks the admin really
 * speaks Spanish, and the public pages keep en-GB / es-ES (CLI render with
 * the language packs, plus HTTP on the running server).
 * --offline: no simulation, the sandbox's real block on wordpress.org:
 * the step must end with a friendly 'info' line, no error, WPLANG unchanged.
 *
 * WordPress' language folder is redirected to the scratchpad (WP_LANG_DIR),
 * so the shared /home/user/wpsite install is never touched. Not run through
 * wpsite-cli.php (WP_LANG_DIR must be defined before WordPress loads).
 * Restores WPLANG to '' at the end unless --keep.
 *
 * @package matsurfer-qa
 */

$mats_port = preg_replace( '/[^0-9]/', '', (string) getenv( 'MATS_WP_PORT' ) );
if ( ! $mats_port || '8881' === $mats_port || '8950' === $mats_port ) {
	fwrite( STDERR, "Set MATS_WP_PORT to YOUR instance port.\n" );
	exit( 2 );
}
$mats_args    = array_slice( $argv, 1 );
$mats_offline = in_array( '--offline', $mats_args, true );
$mats_child   = '';
foreach ( $mats_args as $mats_arg ) {
	if ( str_starts_with( $mats_arg, '--child=' ) ) {
		$mats_child = substr( $mats_arg, 8 );
	}
}
$mats_scratch = '/tmp/claude-0/-home-user-ICMAB-WEB/ae5d27f5-1af0-537f-966f-1d2257e6cfbd/scratchpad/installer/langpack-' . $mats_port;
if ( ! $mats_child ) {
	exec( 'rm -rf ' . escapeshellarg( $mats_scratch ) ); // phpcs:ignore
}
@mkdir( $mats_scratch . '/languages', 0777, true ); // phpcs:ignore
define( 'WP_LANG_DIR', $mats_scratch . '/languages' );

$_SERVER['HTTP_HOST']   = 'localhost:' . $mats_port;
$_SERVER['SERVER_PORT'] = $mats_port;
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';

/* ── child: front-end render of one URL with these language packs ─────── */
if ( str_starts_with( $mats_child, 'front:' ) ) {
	$_SERVER['REQUEST_URI']    = substr( $mats_child, 6 );
	$_SERVER['REQUEST_METHOD'] = 'GET';
	define( 'WP_USE_THEMES', true );
	ob_start();
	require '/home/user/wpsite/wp-blog-header.php';
	$mats_html = (string) ob_get_clean();
	preg_match( '/<html[^>]*\slang="([^"]+)"/', $mats_html, $mats_m );
	echo wp_json_encode(
		array(
			'url'    => $_SERVER['REQUEST_URI'],
			'lang'   => $mats_m[1] ?? '',
			'locale' => determine_locale(),
			'bytes'  => strlen( $mats_html ),
		)
	);
	exit( 0 );
}

define( 'WP_ADMIN', true );
require '/home/user/wpsite/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
wp_set_current_user( 1 );

/* ── child: what an admin sees with the packs installed ───────────────── */
if ( 'admin' === $mats_child ) {
	echo wp_json_encode(
		array(
			'WPLANG'      => get_option( 'WPLANG' ),
			'user_locale' => get_user_locale( 1 ),
			'Dashboard'   => __( 'Dashboard' ),
			'Pages'       => __( 'Pages' ),
			'polylang'    => is_textdomain_loaded( 'polylang' ) ? __( 'Languages', 'polylang' ) : '(polylang textdomain not loaded)',
		),
		JSON_UNESCAPED_UNICODE
	);
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

if ( ! function_exists( 'mats_installer_setup_admin_language' ) ) {
	fwrite( STDERR, "The MatSurfer installer is not loaded.\n" );
	exit( 2 );
}

/* ── language packs used by the simulation ────────────────────────────── */
require_once ABSPATH . WPINC . '/pomo/po.php';
require_once ABSPATH . WPINC . '/pomo/mo.php';

/** Builds a language pack zip (one .po + one .mo) with these strings. */
function mats_qa_pack( string $zip, string $base, array $strings ): void {
	$po = new PO();
	$mo = new MO();
	foreach ( array( $po, $mo ) as $file ) {
		$file->set_header( 'Language', 'es' );
		$file->set_header( 'Content-Type', 'text/plain; charset=UTF-8' );
		$file->set_header( 'PO-Revision-Date', '2026-10-01 10:00:00+0000' );
		foreach ( $strings as $en => $es ) {
			$file->add_entry(
				new Translation_Entry(
					array(
						'singular'     => $en,
						'translations' => array( $es ),
					)
				)
			);
		}
	}
	$dir = dirname( $zip );
	$po->export_to_file( "$dir/$base.po" );
	$mo->export_to_file( "$dir/$base.mo" );
	$z = new ZipArchive();
	$z->open( $zip, ZipArchive::CREATE | ZipArchive::OVERWRITE );
	$z->addFile( "$dir/$base.po", "$base.po" );
	$z->addFile( "$dir/$base.mo", "$base.mo" );
	$z->close();
	unlink( "$dir/$base.po" );
	unlink( "$dir/$base.mo" );
}

$mats_packs = array(
	'core/es_ES.zip'              => array( 'es_ES', array( 'Dashboard' => 'Escritorio', 'Pages' => 'Páginas', 'Settings' => 'Ajustes' ) ),
	'plugin/polylang/es_ES.zip'   => array( 'polylang-es_ES', array( 'Languages' => 'Idiomas' ) ),
);
$mats_downloads = array();
$mats_http      = array();

if ( ! $mats_offline ) {
	@mkdir( $mats_scratch . '/packs/plugin/polylang', 0777, true ); // phpcs:ignore
	@mkdir( $mats_scratch . '/packs/core', 0777, true ); // phpcs:ignore
	foreach ( $mats_packs as $mats_rel => list( $mats_base, $mats_strings ) ) {
		mats_qa_pack( $mats_scratch . '/packs/' . $mats_rel, $mats_base, $mats_strings );
	}

	add_filter(
		'translations_api',
		static function ( $res, $type, $args ) {
			$version = (string) ( $args['version'] ?? '' );
			if ( 'core' === $type ) {
				return array(
					'translations' => array(
						array(
							'language'     => 'es_ES',
							'version'      => $version,
							'updated'      => '2026-10-01 10:00:00',
							'english_name' => 'Spanish (Spain)',
							'native_name'  => 'Español',
							'package'      => "https://downloads.wordpress.org/translation/core/$version/es_ES.zip",
							'iso'          => array( '1' => 'es', '2' => 'spa' ),
							'strings'      => array( 'continue' => 'Continuar' ),
						),
					),
				);
			}
			if ( 'plugins' === $type && 'polylang' === ( $args['slug'] ?? '' ) ) {
				return array(
					'translations' => array(
						array(
							'language' => 'fr_FR',
							'version'  => $version,
							'updated'  => '2026-10-01 10:00:00',
							'package'  => "https://downloads.wordpress.org/translation/plugin/polylang/$version/fr_FR.zip",
						),
						array(
							'language' => 'es_ES',
							'version'  => $version,
							'updated'  => '2026-10-01 10:00:00',
							'package'  => "https://downloads.wordpress.org/translation/plugin/polylang/$version/es_ES.zip",
						),
					),
				);
			}
			return array( 'translations' => array() ); // e.g. a plugin with no Spanish translation
		},
		10,
		3
	);
	add_filter(
		'upgrader_pre_download',
		static function ( $reply, $package ) use ( $mats_scratch, &$mats_downloads ) {
			$mats_downloads[] = $package;
			$rel              = preg_match( '#/translation/(core)/[^/]+/(es_ES)\.zip$#', $package ) ? 'core/es_ES.zip' : ( preg_match( '#/translation/plugin/polylang/[^/]+/es_ES\.zip$#', $package ) ? 'plugin/polylang/es_ES.zip' : '' );
			if ( ! $rel ) {
				return new WP_Error( 'qa', 'unexpected package ' . $package );
			}
			$tmp = wp_tempnam( basename( $package ) );
			copy( $mats_scratch . '/packs/' . $rel, $tmp ); // the upgrader deletes the file after unpacking
			return $tmp;
		},
		10,
		2
	);
}
add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) use ( &$mats_http ) {
		$mats_http[] = $url;
		return $pre;
	},
	1,
	3
);

/* ── start clean ──────────────────────────────────────────────────────── */
/* changing WPLANG makes WordPress forget its update checks (core hook
   update_option_WPLANG → wp_clean_update_cache), so the next admin page
   checks wordpress.org again: fine on a real host, a logged warning in this
   offline sandbox. They are put back at the end. */
$mats_update_cache = array();
foreach ( array( 'update_core', 'update_plugins', 'update_themes' ) as $mats_t ) {
	$mats_update_cache[ $mats_t ] = get_site_transient( $mats_t );
}
delete_option( 'WPLANG' );
delete_site_transient( 'available_translations' );
$mats_log      = '/home/user/wpsite-data/debug-' . $mats_port . '.log';
$mats_log_size = file_exists( $mats_log ) ? filesize( $mats_log ) : 0;
echo 'WP_LANG_DIR = ', WP_LANG_DIR, "\nlanguages before: ", wp_json_encode( get_available_languages() ), "\n";

function mats_qa_texts( array $result ): array {
	return array_map( static fn( $m ) => $m['type'] . ' ' . $m['text'], $result['messages'] );
}
function mats_qa_has( array $texts, string $needle ): bool {
	foreach ( $texts as $t ) {
		if ( false !== mb_stripos( $t, $needle ) ) {
			return true;
		}
	}
	return false;
}

echo "\n== run 1: step 2 (languages)", $mats_offline ? ' — OFFLINE (real block)' : ' — wordpress.org simulated', "\n";
$mats_r1 = mats_installer_run_step( 'languages', array() );
$mats_t1 = mats_qa_texts( $mats_r1 );
foreach ( $mats_t1 as $mats_t ) {
	echo '   ', $mats_t, "\n";
}
mats_qa( (bool) $mats_r1['ok'], 'step 2 has no errors' );
mats_qa( 'done' === mats_installer_status( 'languages' )['state'], 'step 2 status is ✓ done' );
$mats_check = mats_installer_admin_language_check();
echo '   admin language check: ', var_export( $mats_check['ok'], true ), ' — ', $mats_check['text'], "\n";

if ( $mats_offline ) {
	mats_qa( mats_qa_has( $mats_t1, 'info El escritorio de WordPress sigue en inglés' ), 'friendly info line (not an error) explaining it' );
	mats_qa( mats_qa_has( $mats_t1, 'Ajustes → Generales' ), 'it says how to do it by hand (Ajustes → Generales → Idioma del sitio)' );
	mats_qa( ! array_filter( $mats_r1['messages'], static fn( $m ) => in_array( $m['type'], array( 'error', 'warn' ), true ) ), 'no error / warning messages' );
	mats_qa( false === get_option( 'WPLANG' ), 'WPLANG left untouched' );
	mats_qa( null === $mats_check['ok'], 'admin language is an optional check (•), not ✗' );
} else {
	mats_qa( mats_qa_has( $mats_t1, 'ok Traducción al español de WordPress descargada' ), 'core es_ES pack downloaded' );
	mats_qa( in_array( 'es_ES', get_available_languages(), true ) && file_exists( WP_LANG_DIR . '/es_ES.mo' ), 'es_ES.mo installed in WP_LANG_DIR' );
	mats_qa( 'es_ES' === get_option( 'WPLANG' ), 'WPLANG = es_ES' );
	mats_qa( mats_qa_has( $mats_t1, 'ok El escritorio de WordPress queda en español' ), 'message: admin now in Spanish' );
	mats_qa( file_exists( WP_LANG_DIR . '/plugins/polylang-es_ES.mo' ), 'Polylang es_ES translation installed (languages/plugins/polylang-es_ES.mo)' );
	mats_qa( mats_qa_has( $mats_t1, 'ok Traducción al español de Polylang instalada' ), 'message: Polylang translation installed' );
	mats_qa( ! file_exists( WP_LANG_DIR . '/plugins/polylang-fr_FR.mo' ), 'only Spanish is installed (fr_FR ignored)' );
	mats_qa( true === $mats_check['ok'], 'admin language check is ✓' );
	mats_qa( 2 === count( $mats_downloads ), 'exactly 2 packages downloaded (core + Polylang): ' . count( $mats_downloads ) );

	echo "\n== run 2: idempotency\n";
	$mats_downloads = array();
	$mats_r2        = mats_installer_run_step( 'languages', array() );
	$mats_t2        = mats_qa_texts( $mats_r2 );
	foreach ( $mats_t2 as $mats_t ) {
		echo '   ', $mats_t, "\n";
	}
	mats_qa( 0 === count( $mats_downloads ), 'second run downloads nothing' );
	mats_qa( mats_qa_has( $mats_t2, 'ya estaba en español' ) && mats_qa_has( $mats_t2, 'Polylang ya tenía su traducción' ), 'second run reports "ya estaba"' );

	echo "\n== a language chosen by somebody is respected\n";
	/* WordPress only accepts an installed language in WPLANG: fake a Catalan pack */
	copy( WP_LANG_DIR . '/es_ES.mo', WP_LANG_DIR . '/ca.mo' );
	copy( WP_LANG_DIR . '/es_ES.po', WP_LANG_DIR . '/ca.po' );
	wp_cache_delete( md5( rtrim( WP_LANG_DIR, '/' ) . '/' ), 'translation_files' );
	update_option( 'WPLANG', 'ca' );
	$mats_r3 = mats_installer_run_step( 'languages', array() );
	mats_qa( 'ca' === get_option( 'WPLANG' ) && mats_qa_has( mats_qa_texts( $mats_r3 ), 'porque alguien lo eligió así' ), 'WPLANG=ca (chosen by hand) kept, explained: ' . get_option( 'WPLANG' ) );
	update_option( 'WPLANG', 'es_ES' );
	unlink( WP_LANG_DIR . '/ca.mo' );
	unlink( WP_LANG_DIR . '/ca.po' );
	wp_cache_delete( md5( rtrim( WP_LANG_DIR, '/' ) . '/' ), 'translation_files' );

	echo "\n== new PHP process: the admin speaks Spanish\n";
	$mats_cmd   = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' --child=admin';
	$mats_admin = json_decode( (string) shell_exec( 'MATS_WP_PORT=' . $mats_port . ' ' . $mats_cmd . ' 2>&1' ), true );
	echo '   ', wp_json_encode( $mats_admin, JSON_UNESCAPED_UNICODE ), "\n";
	mats_qa( 'es_ES' === ( $mats_admin['user_locale'] ?? '' ) && 'Escritorio' === ( $mats_admin['Dashboard'] ?? '' ), 'admin user locale es_ES, "Dashboard" → "Escritorio"' );

	echo "\n== the public site keeps its own languages (CLI render with the packs)\n";
	foreach ( array( '/' => 'en-GB', '/product/' => 'en-GB', '/es/' => 'es-ES', '/es/producto/' => 'es-ES' ) as $mats_url => $mats_want ) {
		$mats_cmd   = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' ' . escapeshellarg( '--child=front:' . $mats_url );
		$mats_front = json_decode( (string) shell_exec( 'MATS_WP_PORT=' . $mats_port . ' ' . $mats_cmd . ' 2>/dev/null' ), true );
		mats_qa( $mats_want === ( $mats_front['lang'] ?? '' ), sprintf( '%s → <html lang="%s"> (locale %s, %d bytes)', $mats_url, $mats_front['lang'] ?? '?', $mats_front['locale'] ?? '?', $mats_front['bytes'] ?? 0 ) );
	}
}

echo "\n== the running server (http://localhost:$mats_port), WPLANG = ", var_export( get_option( 'WPLANG' ), true ), "\n";
foreach ( array( '/' => 'en-GB', '/product/' => 'en-GB', '/es/' => 'es-ES', '/es/producto/' => 'es-ES' ) as $mats_url => $mats_want ) {
	$mats_ctx  = stream_context_create( array( 'http' => array( 'ignore_errors' => true, 'timeout' => 20 ) ) );
	$mats_html = (string) @file_get_contents( 'http://localhost:' . $mats_port . $mats_url, false, $mats_ctx ); // phpcs:ignore
	$mats_code = isset( $http_response_header[0] ) ? (int) explode( ' ', $http_response_header[0] )[1] : 0;
	preg_match( '/<html[^>]*\slang="([^"]+)"/', $mats_html, $mats_m );
	mats_qa( 200 === $mats_code && $mats_want === ( $mats_m[1] ?? '' ), sprintf( 'HTTP %s %d lang=%s', $mats_url, $mats_code, $mats_m[1] ?? '?' ) );
}

$mats_new_log = file_exists( $mats_log ) ? (string) file_get_contents( $mats_log, false, null, $mats_log_size ) : '';
$mats_warn    = array_filter( explode( "\n", $mats_new_log ), static fn( $l ) => preg_match( '/PHP (Warning|Notice|Deprecated|Fatal)/', $l ) );
mats_qa( ! $mats_warn, 'no new PHP warnings in debug-' . $mats_port . '.log' . ( $mats_warn ? ': ' . implode( ' | ', array_slice( $mats_warn, 0, 3 ) ) : '' ) );
echo 'HTTP requests attempted: ', wp_json_encode( array_values( array_unique( $mats_http ) ) ), "\n";

if ( ! in_array( '--keep', $mats_args, true ) ) {
	delete_option( 'WPLANG' );
	delete_site_transient( 'available_translations' );
	echo "(WPLANG restored to '' — the server's shared language folder has no es_ES)\n";
}
foreach ( $mats_update_cache as $mats_t => $mats_v ) {
	if ( is_object( $mats_v ) && false === get_site_transient( $mats_t ) ) {
		$mats_v->last_checked = time();
		set_site_transient( $mats_t, $mats_v );
	}
}
echo $mats_fail ? "\n$mats_fail FAILED\n" : "\nALL PASS\n";
exit( $mats_fail ? 1 : 0 );
