<?php
/**
 * Setup wizard ("MatSurfer → Puesta en marcha") — step registry, results,
 * saved state and the run-everything entry point.
 *
 * STEPS (in order; each one is idempotent and safe to repeat)
 *   plugins    Polylang + WPForms Lite (install from wordpress.org / activate),
 *              WP Mail SMTP offered separately (option 'smtp').
 *   languages  Polylang EN (default, /) + ES (/es/), its options, existing
 *              content → English, WordPress basics (permalinks, time zone…).
 *   content    the structural pages EN + ES (sections built from the original
 *              copy with mats_seed_block()), front/blog/privacy pages, SEO
 *              meta, team/applications/publications/evaluations, sample
 *              content removed when untouched, default category renamed.
 *   forms      the two WPForms contact forms (inc/modules/wpforms.php).
 *   review     final checklist (nothing to run; marks the wizard as done).
 *
 * A STEP RESULT is
 *   [ 'step' => key, 'ok' => bool, 'reload' => bool, 'skipped' => bool,
 *     'messages' => [ [ 'type' => ok|info|warn|error, 'text' => …, 'url' => …, 'label' => … ] ],
 *     'data' => [ machine-readable details for tests ] ]
 * 'reload' = a plugin was activated in this request: the next steps must run
 * in a new request (the plugin's code only loads on the next page load).
 *
 * PHP entry points
 *   mats_installer_run_step( $step, $opts ) one step
 *   mats_installer_run_all( $opts )         every step in order (CLI tests:
 *                                            wordpress/tools/qa/installer-run.php)
 *   mats_installer_status( $step )          ✓/✗ state + the checks shown on screen
 * Options: 'overwrite' (bool) replace the page texts with the originals,
 *          'install' (bool, default true) allow downloads from wordpress.org,
 *          'smtp' (bool) also install WP Mail SMTP, 'steps' (string[]),
 *          'from' (step key) start there.
 *
 * Saved state: option 'mats_installer_state' =
 *   [ 'steps' => [ key => [ 'time', 'ok', 'messages' ] ], 'done' => time,
 *     'notice' => 'show'|'hide', 'seeded' => [ post type => time ] ]
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

defined( 'MATS_SETUP_PAGE' ) || define( 'MATS_SETUP_PAGE', 'mats-setup' );
defined( 'MATS_SETUP_NONCE' ) || define( 'MATS_SETUP_NONCE', 'mats_installer' );

/** The wizard's steps, in order. */
function mats_installer_steps(): array {
	return array(
		'plugins'   => array(
			'title'  => 'Plugins necesarios',
			'intro'  => 'Instala y activa Polylang (los dos idiomas) y WPForms Lite (el formulario de contacto). Si ya están instalados, solo los activa.',
			'run'    => 'mats_installer_step_plugins',
			'status' => 'mats_installer_status_plugins',
			'button' => 'Instalar y activar los plugins',
		),
		'languages' => array(
			'title'  => 'Idiomas y ajustes básicos',
			'intro'  => 'Configura el inglés (idioma principal, en la dirección raíz) y el español (en /es/), y deja preparados los ajustes de WordPress: direcciones limpias, zona horaria de Madrid, formato de fecha y comentarios cerrados.',
			'run'    => 'mats_installer_step_languages',
			'status' => 'mats_installer_status_languages',
			'button' => 'Configurar los idiomas',
		),
		'content'   => array(
			'title'  => 'Páginas y contenido',
			'intro'  => 'Crea las páginas de la web en inglés y en español, enlazadas entre sí, con los textos y fotos originales. También carga el equipo, las aplicaciones, las publicaciones y las valoraciones de expertos. Las páginas que ya existen no se tocan.',
			'run'    => 'mats_installer_step_content',
			'status' => 'mats_installer_status_content',
			'button' => 'Crear las páginas',
		),
		'forms'     => array(
			'title'  => 'Formulario de contacto',
			'intro'  => 'Crea el formulario de contacto en inglés y en español dentro de WPForms, con los campos y textos de la web. Los mensajes llegan al email de contacto y se guardan también en «Contactos».',
			'run'    => 'mats_installer_step_forms',
			'status' => 'mats_installer_status_forms',
			'button' => 'Crear los formularios',
		),
		'review'    => array(
			'title'  => 'Revisión final',
			'intro'  => 'Unas comprobaciones que no se pueden hacer de forma automática. Revísalas antes de dar la web por terminada.',
			'run'    => 'mats_installer_step_review',
			'status' => 'mats_installer_status_review',
			'button' => 'Dar la puesta en marcha por terminada',
		),
	);
}

/** Steps that "Hacerlo todo" runs (the review is a checklist, not an action). */
function mats_installer_auto_steps(): array {
	return array( 'plugins', 'languages', 'content', 'forms' );
}

/* ── results ─────────────────────────────────────────────────────────── */

function mats_installer_result( string $step ): array {
	return array(
		'step'     => $step,
		'ok'       => true,
		'reload'   => false,
		'skipped'  => false,
		'messages' => array(),
		'data'     => array(),
	);
}

/** Adds a message to a result; an 'error' marks the step as failed. */
function mats_installer_add( array &$result, string $type, string $text, string $url = '', string $label = '' ): void {
	$msg = array(
		'type' => in_array( $type, array( 'ok', 'info', 'warn', 'error' ), true ) ? $type : 'info',
		'text' => $text,
	);
	if ( $url ) {
		$msg['url']   = $url;
		$msg['label'] = $label ? $label : 'Abrir';
	}
	$result['messages'][] = $msg;
	if ( 'error' === $msg['type'] ) {
		$result['ok'] = false;
	}
}

/* ── saved state ─────────────────────────────────────────────────────── */

function mats_installer_state(): array {
	$state = get_option( 'mats_installer_state', array() );
	$state = is_array( $state ) ? $state : array();
	return array_merge(
		array(
			'steps'  => array(),
			'done'   => 0,
			'notice' => '',
			'seeded' => array(),
			'links'  => array(),
		),
		$state
	);
}

function mats_installer_save_state( array $state ): void {
	update_option( 'mats_installer_state', $state, false );
}

/** Remembers the last result of a step (shown again when the page reloads). */
function mats_installer_remember( array $result ): void {
	$state                              = mats_installer_state();
	$state['steps'][ $result['step'] ] = array(
		'time'     => time(),
		'ok'       => (bool) $result['ok'],
		'messages' => array_slice( (array) $result['messages'], 0, 60 ),
	);
	mats_installer_save_state( $state );
}

/* ── plugin activation in the current request ────────────────────────── */

/** Called when this request activated a plugin whose code is not loaded yet. */
function mats_installer_flag_activation( string $plugin ): void {
	$GLOBALS['mats_installer_activated'][] = $plugin;
}

function mats_installer_needs_reload(): bool {
	return ! empty( $GLOBALS['mats_installer_activated'] );
}

/** Is Polylang loaded and usable in this request? */
function mats_installer_pll_ready(): bool {
	return function_exists( 'PLL' ) && PLL() && isset( PLL()->model ) && function_exists( 'pll_set_post_language' );
}

/** Polylang language object for a slug, or null. */
function mats_installer_pll_language( string $slug ) {
	if ( ! mats_installer_pll_ready() ) {
		return null;
	}
	$model = PLL()->model;
	if ( isset( $model->languages ) && is_object( $model->languages ) && method_exists( $model->languages, 'get' ) ) {
		$lang = $model->languages->get( $slug ); // Polylang 3.7+
	} else {
		$lang = is_callable( array( $model, 'get_language' ) ) ? $model->get_language( $slug ) : false;
	}
	return $lang ? $lang : null;
}

/** Languages the content step can create pages in right now. */
function mats_installer_content_langs(): array {
	if ( ! mats_installer_pll_ready() ) {
		return array( 'en' );
	}
	$langs = array();
	foreach ( mats_langs() as $slug ) {
		if ( mats_installer_pll_language( $slug ) ) {
			$langs[] = $slug;
		}
	}
	return $langs ? $langs : array( 'en' );
}

/* ── running ─────────────────────────────────────────────────────────── */

/** Room for slow steps (downloads, photo imports) where the host allows it. */
function mats_installer_prepare(): void {
	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'admin' );
	}
	if ( function_exists( 'set_time_limit' ) && false === strpos( (string) ini_get( 'disable_functions' ), 'set_time_limit' ) ) {
		@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- some hosts forbid it; never fatal.
	}
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	require_once ABSPATH . 'wp-admin/includes/post.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/misc.php';
}

/**
 * Runs a WordPress function that reports network problems as PHP warnings
 * too (plugins_api, translations_api…): the step explains the problem in
 * its own words, so those warnings are kept out of the error log.
 *
 * @param callable $fn    What to run.
 * @param array    $notes Receives the warning texts.
 * @return mixed What $fn returned.
 */
function mats_installer_quietly( callable $fn, array &$notes = array() ) {
	set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		static function ( $errno, $errstr ) use ( &$notes ) {
			$notes[] = wp_strip_all_tags( (string) $errstr );
			return true;
		},
		E_USER_WARNING | E_USER_NOTICE
	);
	try {
		return $fn();
	} finally {
		restore_error_handler();
	}
}

/** The lock row is written with SQL: whatever an object cache remembers about it is stale. */
function mats_installer_lock_forget_cache(): void {
	wp_cache_delete( 'mats_installer_lock', 'options' );
	$missing = wp_cache_get( 'notoptions', 'options' );
	if ( is_array( $missing ) && isset( $missing['mats_installer_lock'] ) ) {
		unset( $missing['mats_installer_lock'] );
		wp_cache_set( 'notoptions', $missing, 'options' );
	}
}

/**
 * Only one run at a time (two tabs or two admins at once could otherwise
 * both create the same pages). The lock is the row 'mats_installer_lock' in
 * the options table, taken with ONE SQL statement whose affected-row count
 * decides who got it (add_option() is not enough: it checks first and then
 * writes with INSERT … ON DUPLICATE KEY UPDATE, so two requests at the same
 * moment could both "win"). A lock older than 5 minutes is stale and is
 * taken over with a compare-and-swap UPDATE, again decided by the database.
 */
function mats_installer_lock(): bool {
	global $wpdb;
	$name = 'mats_installer_lock';

	for ( $attempt = 0; $attempt < 2; $attempt++ ) {
		$now = (string) time();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the lock must bypass the option cache.
		$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)", $name, $now, 'off' ) );
		if ( false === $inserted ) {
			/* the database refused the statement (very unusual): fall back to the WordPress API */
			mats_installer_lock_forget_cache();
			if ( add_option( $name, $now, '', false ) ) {
				return true;
			}
			$since = (int) get_option( $name );
			if ( ! $since || time() - $since > 5 * MINUTE_IN_SECONDS ) {
				update_option( $name, $now, false );
				return true;
			}
			return false;
		}
		mats_installer_lock_forget_cache();
		if ( 1 === (int) $inserted ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$since = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );
		if ( null === $since ) {
			continue; // released in between: try again
		}
		if ( (int) $since && time() - (int) $since <= 5 * MINUTE_IN_SECONDS ) {
			return false; // somebody else is running it right now
		}
		/* stale: take it over only if nobody else did in the meantime */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$swapped = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $now, $name, (string) $since ) );
		mats_installer_lock_forget_cache();
		return 1 === (int) $swapped;
	}
	return false;
}

function mats_installer_unlock(): void {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->delete( $wpdb->options, array( 'option_name' => 'mats_installer_lock' ) );
	mats_installer_lock_forget_cache();
}

/** Runs one step and remembers its result. */
function mats_installer_run_step( string $step, array $opts = array() ): array {
	$steps = mats_installer_steps();
	if ( ! isset( $steps[ $step ] ) ) {
		$result = mats_installer_result( $step );
		mats_installer_add( $result, 'error', 'Paso desconocido.' );
		return $result;
	}
	$opts = array_merge(
		array(
			'overwrite' => false,
			'install'   => true,
			'smtp'      => false,
		),
		$opts
	);
	mats_installer_prepare();

	$fn     = $steps[ $step ]['run'];
	$locked = 'review' !== $step;
	if ( $locked && ! mats_installer_lock() ) {
		$result            = mats_installer_result( $step );
		$result['skipped'] = true;
		mats_installer_add( $result, 'error', 'La puesta en marcha ya se está ejecutando (quizá en otra pestaña o la ha lanzado otra persona). Espera un par de minutos, recarga la página y vuelve a intentarlo.' );
		return $result;
	}
	try {
		$result = function_exists( $fn ) ? (array) $fn( $opts ) : mats_installer_result( $step );
	} catch ( Throwable $e ) {
		$result = mats_installer_result( $step );
		mats_installer_add(
			$result,
			'error',
			'Ha ocurrido un error inesperado y el paso no se ha completado. Vuelve a intentarlo; si se repite, pasa este detalle a Stimulo: ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')'
		);
	} finally {
		if ( $locked ) {
			mats_installer_unlock();
		}
	}
	$result         = array_merge( mats_installer_result( $step ), $result );
	$result['step'] = $step;
	if ( empty( $result['skipped'] ) ) {
		mats_installer_remember( $result );
	}

	/**
	 * Fires after a setup step ran (tests, logging).
	 *
	 * @param array $result The step result.
	 * @param array $opts   The options it ran with.
	 */
	do_action( 'mats_installer_step_done', $result, $opts );
	return $result;
}

/**
 * Runs the whole setup in order. When a plugin gets activated, the
 * remaining steps cannot run in the same request (the plugin is not loaded
 * yet): the result then has 'reload' => true and 'next' => the step to
 * continue from (call again with [ 'from' => next ]).
 *
 * @return array{ok:bool, reload:bool, next:string, steps:array}
 */
function mats_installer_run_all( array $opts = array() ): array {
	$order = isset( $opts['steps'] ) && is_array( $opts['steps'] ) ? array_values( array_intersect( array_keys( mats_installer_steps() ), $opts['steps'] ) ) : mats_installer_auto_steps();
	if ( ! empty( $opts['from'] ) && in_array( $opts['from'], $order, true ) ) {
		$order = array_slice( $order, (int) array_search( $opts['from'], $order, true ) );
	}
	$out = array(
		'ok'     => true,
		'reload' => false,
		'next'   => '',
		'steps'  => array(),
	);
	foreach ( $order as $i => $step ) {
		if ( mats_installer_needs_reload() ) {
			$out['reload'] = true;
			$out['next']   = $step;
			break;
		}
		$result                 = mats_installer_run_step( $step, $opts );
		$out['steps'][ $step ] = $result;
		if ( ! $result['ok'] ) {
			$out['ok'] = false;
		}
		if ( ! empty( $result['reload'] ) && isset( $order[ $i + 1 ] ) ) {
			$out['reload'] = true;
			$out['next']   = $order[ $i + 1 ];
			break;
		}
	}
	if ( ! $out['reload'] ) {
		mats_installer_maybe_hide_notice();
	}
	return $out;
}

/* ── status (✓ / ✗) ──────────────────────────────────────────────────── */

/**
 * State of one step for the screen:
 *   [ 'state' => done|todo|warn|info, 'label' => …, 'checks' => [ [ ok, text, url, label ] ] ]
 */
function mats_installer_status( string $step ): array {
	$steps = mats_installer_steps();
	$fn    = $steps[ $step ]['status'] ?? '';
	$out   = array(
		'state'  => 'todo',
		'label'  => 'Pendiente',
		'checks' => array(),
	);
	if ( $fn && function_exists( $fn ) ) {
		try {
			$out = array_merge( $out, (array) $fn() );
		} catch ( Throwable $e ) {
			$out['state']    = 'warn';
			$out['label']    = 'No se puede comprobar';
			$out['checks'][] = array(
				'ok'   => false,
				'text' => 'No se ha podido comprobar este paso: ' . $e->getMessage(),
			);
		}
	}
	return $out;
}

/** Builds a status from a list of checks (ok = true | false | null for optional). */
function mats_installer_status_from( array $checks, string $done_label = 'Hecho', string $todo_label = 'Pendiente' ): array {
	$all = true;
	foreach ( $checks as $check ) {
		if ( false === ( $check['ok'] ?? null ) ) {
			$all = false;
			break;
		}
	}
	return array(
		'state'  => $all ? 'done' : 'todo',
		'label'  => $all ? $done_label : $todo_label,
		'checks' => $checks,
	);
}

function mats_installer_check( $ok, string $text, string $url = '', string $label = '' ): array {
	$check = array(
		'ok'   => $ok,
		'text' => $text,
	);
	if ( $url ) {
		$check['url']   = $url;
		$check['label'] = $label ? $label : 'Abrir';
	}
	return $check;
}

/** Is everything the automatic steps do in place? */
function mats_installer_all_done(): bool {
	foreach ( mats_installer_auto_steps() as $step ) {
		if ( 'done' !== mats_installer_status( $step )['state'] ) {
			return false;
		}
	}
	return true;
}

/**
 * The reminder shown after activating the theme ("…abre la puesta en
 * marcha") is hidden for good as soon as every automatic step is done: the
 * site is ready, so the reminder would say the opposite of the wizard.
 * Returns true when the reminder is (now) hidden.
 *
 * @param bool|null $all_done Already known result of mats_installer_all_done().
 */
function mats_installer_maybe_hide_notice( ?bool $all_done = null ): bool {
	$state = mats_installer_state();
	if ( 'show' !== $state['notice'] ) {
		return true;
	}
	if ( ! ( null === $all_done ? mats_installer_all_done() : $all_done ) ) {
		return false;
	}
	$state           = mats_installer_state(); // the status checks may have saved something meanwhile
	$state['notice'] = 'hide';
	mats_installer_save_state( $state );
	return true;
}

/** URL of the wizard screen. */
function mats_installer_url( array $args = array() ): string {
	return add_query_arg( array_merge( array( 'page' => MATS_SETUP_PAGE ), $args ), admin_url( 'admin.php' ) );
}

/**
 * URL of a screen of another module: 'settings' (Ajustes de la web) or
 * 'guide' (Guía). Found by menu title while the admin menu exists (normal
 * admin pages) and remembered for AJAX requests, where it does not.
 */
function mats_installer_known_page( string $which ): string {
	$needles = array(
		'settings' => array( 'Ajustes de la web' ),
		'guide'    => array( 'Guía', 'Guia', 'Ayuda' ),
	);
	if ( ! isset( $needles[ $which ] ) ) {
		return '';
	}
	$state = mats_installer_state();
	if ( ! empty( $GLOBALS['menu'] ) ) {
		$url = mats_installer_admin_page_url( $needles[ $which ] );
		if ( ( $state['links'][ $which ] ?? null ) !== $url ) {
			$state['links'][ $which ] = $url;
			mats_installer_save_state( $state );
		}
		return $url;
	}
	return (string) ( $state['links'][ $which ] ?? '' );
}

/**
 * URL of an admin screen registered by another module, found by its menu
 * title (e.g. 'Ajustes de la web', 'Guía'). '' when it does not exist.
 */
function mats_installer_admin_page_url( array $needles ): string {
	global $submenu, $menu;
	$items = array();
	foreach ( (array) $submenu as $parent => $children ) {
		foreach ( (array) $children as $child ) {
			$items[] = array( $child[0] ?? '', $child[2] ?? '', $parent );
		}
	}
	foreach ( (array) $menu as $item ) {
		$items[] = array( $item[0] ?? '', $item[2] ?? '', '' );
	}
	foreach ( $items as $item ) {
		$title = trim( wp_strip_all_tags( (string) $item[0] ) );
		foreach ( $needles as $needle ) {
			if ( '' !== $title && false !== mb_stripos( $title, $needle ) && MATS_SETUP_PAGE !== $item[1] ) {
				$slug = (string) $item[1];
				if ( false !== strpos( $slug, '.php' ) ) {
					return admin_url( $slug );
				}
				$parent = (string) $item[2];
				$base   = ( $parent && false !== strpos( $parent, '.php' ) && 'admin.php' !== $parent ) ? $parent : 'admin.php';
				return add_query_arg( 'page', $slug, admin_url( $base ) );
			}
		}
	}
	return '';
}
