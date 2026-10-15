<?php
/**
 * Security hardening — invisible to the ICMAB team, never in the way of the
 * block editor, the REST block renderer, Polylang, WPForms or the setup
 * wizard. Administrators (Stimulo) keep every capability they need.
 *
 *  FILES & PLUGINS  nobody edits theme or plugin files from the admin (the
 *                   file editors are off for everyone); installing,
 *                   updating, activating or deleting plugins and themes and
 *                   updating WordPress stay with administrators.
 *  XML-RPC          off entirely: xmlrpc.php answers 403, no methods, no
 *                   X-Pingback header, no RSD / WLW links.
 *  FOOTPRINT        no WordPress version in the head, the feeds or the
 *                   asset URLs (?ver=<core version> becomes a hash that is
 *                   unique to the site and changes with every update, so
 *                   browser caches still refresh); no REST / oEmbed
 *                   discovery links nor shortlink in the head (the REST API
 *                   itself keeps working); no X-Powered-By header.
 *  USERS            ?author=N and author archives → home page; REST
 *                   /wp/v2/users and /wp/v2/users/<id> → 401 for visitors
 *                   (logged-in users keep them: the block editor needs
 *                   them); no author names in oEmbed answers or feeds; no
 *                   users sitemap.
 *  LOGIN            one message for a wrong user or a wrong password; the
 *                   lost-password form never tells whether an account
 *                   exists; 5 failed attempts for one user from one IP (or
 *                   20 from one IP in total) lock the access for 15
 *                   minutes with a friendly Spanish message; a successful
 *                   login clears the counter and a new password (reset or
 *                   profile) lifts the account lock. No application
 *                   passwords.
 *  HEADERS          X-Content-Type-Options, Referrer-Policy,
 *                   X-Frame-Options and Permissions-Policy on the site, the
 *                   admin and the login screen. No Content-Security-Policy:
 *                   WPForms and the block editor rely on inline scripts.
 *  FEEDS            no comment feeds (the news feed stays).
 *  UPDATES          background auto-updates for Polylang, WPForms Lite and
 *                   WP Mail SMTP; minor (security) WordPress updates on.
 *  UPLOADS          no executable, script, HTML or SVG files — not even
 *                   hidden behind a double extension (photo.php.jpg).
 *
 * Filters for the person installing the site:
 *   mats_security_client_ip            visitor IP used by the login lock
 *                                      (REMOTE_ADDR; adapt it behind a
 *                                      proxy such as Cloudflare)
 *   mats_security_login_limits         [ 'user' => 5, 'ip' => 20, 'lock' => 900 ]
 *   mats_security_headers              [ header name => value ]
 *   mats_security_auto_update_plugins  plugin folder names
 *   mats_security_block_xmlrpc         false to let xmlrpc.php answer again
 *   mats_security_blocked_extensions   file extensions never accepted
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

/* ═══════════════════════════════════════════════════════════════════════
 * Files, plugins and themes
 * ═════════════════════════════════════════════════════════════════════ */

/** Capabilities (primitive and meta) reserved to administrators. */
function mats_sec_admin_only_caps(): array {
	return array(
		'install_plugins',
		'upload_plugins',
		'activate_plugins',
		'activate_plugin',
		'deactivate_plugin',
		'resume_plugin',
		'update_plugins',
		'delete_plugins',
		'install_themes',
		'upload_themes',
		'switch_themes',
		'resume_theme',
		'update_themes',
		'delete_themes',
		'update_core',
		'install_languages',
		'update_languages',
	);
}

/** Is this user an administrator? Reads the role caps (no capability recursion). */
function mats_sec_user_is_admin( int $user_id ): bool {
	if ( $user_id <= 0 ) {
		return false;
	}
	if ( is_multisite() && is_super_admin( $user_id ) ) {
		return true;
	}
	$user = get_userdata( $user_id );
	return $user instanceof WP_User && ! empty( $user->allcaps['manage_options'] );
}

add_filter(
	'map_meta_cap',
	static function ( $caps, $cap, $user_id ) {
		/* the theme / plugin file editors: off for everyone */
		if ( 'edit_themes' === $cap || 'edit_plugins' === $cap || 'edit_files' === $cap ) {
			return array( 'do_not_allow' );
		}
		if ( in_array( $cap, mats_sec_admin_only_caps(), true ) && ! mats_sec_user_is_admin( (int) $user_id ) ) {
			return array( 'do_not_allow' );
		}
		return $caps;
	},
	20,
	3
);

/* ═══════════════════════════════════════════════════════════════════════
 * XML-RPC (not used by this site: no apps, no pingbacks)
 * ═════════════════════════════════════════════════════════════════════ */

add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'xmlrpc_methods', static fn() => array(), 999 );
add_filter( 'pings_open', '__return_false', 20 );

add_action(
	'init',
	static function () {
		if ( ! defined( 'XMLRPC_REQUEST' ) || ! XMLRPC_REQUEST || ! apply_filters( 'mats_security_block_xmlrpc', true ) ) {
			return;
		}
		status_header( 403 );
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo 'XML-RPC is disabled on this site.';
		exit;
	},
	0
);

/* ═══════════════════════════════════════════════════════════════════════
 * Footprint: head links, generator, version strings
 * ═════════════════════════════════════════════════════════════════════ */

add_action(
	'init',
	static function () {
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
		remove_action( 'template_redirect', 'rest_output_link_header', 11 );
		remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
		remove_action( 'xmlrpc_rsd_apis', 'rest_output_rsd' );
	}
);

/* no "generator" line in the page head nor in the RSS/Atom feeds */
add_filter( 'the_generator', '__return_empty_string', 99 );

/**
 * Asset URLs: `?ver=<WordPress version>` (core styles and scripts) becomes a
 * short hash, unique to this site, that changes when WordPress is updated —
 * the version is not given away and caches still refresh. Every other
 * ?ver= (the theme's file dates, plugin versions) is left untouched.
 */
function mats_sec_hide_core_version( $src ) {
	static $version = null;
	static $hash    = null;
	if ( ! is_string( $src ) || '' === $src || is_admin() || false === strpos( $src, 'ver=' ) ) {
		return $src;
	}
	if ( null === $version ) {
		$version = (string) get_bloginfo( 'version' );
		$hash    = substr( wp_hash( 'mats-assets-' . $version ), 0, 10 );
	}
	$query = (string) wp_parse_url( $src, PHP_URL_QUERY );
	if ( '' === $query ) {
		return $src;
	}
	parse_str( $query, $args );
	if ( isset( $args['ver'] ) && (string) $args['ver'] === $version ) {
		$src = add_query_arg( 'ver', $hash, $src );
	}
	return $src;
}
add_filter( 'style_loader_src', 'mats_sec_hide_core_version', 9999 );
add_filter( 'script_loader_src', 'mats_sec_hide_core_version', 9999 );

/* ═══════════════════════════════════════════════════════════════════════
 * Users: no enumeration
 * ═════════════════════════════════════════════════════════════════════ */

/* ?author=N, ?author_name=… and /author/<name>/ → home page (also before
   WordPress' own canonical redirect, which would reveal the user name) */
add_action(
	'template_redirect',
	static function () {
		/* the query vars too: for an unknown name WordPress turns the request
		   into a 404 (is_author() false), which would tell names apart */
		$asked = '' !== (string) get_query_var( 'author_name' ) || '' !== (string) get_query_var( 'author' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check of public query vars.
		if ( $asked || is_author() || isset( $_GET['author'] ) || isset( $_GET['author_name'] ) ) {
			wp_safe_redirect( function_exists( 'mats_home_url' ) ? mats_home_url() : home_url( '/' ), 301 );
			exit;
		}
	},
	0
);

/* REST API users list / user profiles: 401 for visitors (before routing) */
add_filter(
	'rest_pre_dispatch',
	static function ( $result, $server, $request ) {
		if ( null !== $result || is_user_logged_in() || ! $request instanceof WP_REST_Request ) {
			return $result;
		}
		if ( preg_match( '#^/wp/v2/users(?:/|$)#i', (string) $request->get_route() ) ) {
			return new WP_Error(
				'rest_forbidden',
				'Sorry, you are not allowed to do that.',
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return $result;
	},
	5,
	3
);

/* oEmbed answers: no author name / author URL */
add_filter(
	'oembed_response_data',
	static function ( $data ) {
		if ( is_array( $data ) ) {
			unset( $data['author_name'], $data['author_url'] );
		}
		return $data;
	},
	20
);

/* feeds: the site name instead of the user who published the article */
add_filter(
	'the_author',
	static function ( $name ) {
		return is_feed() ? (string) get_bloginfo( 'name', 'display' ) : $name;
	},
	20
);

/* … and not the "Web" of that user's profile either (Atom <author><uri>:
   empty → WordPress leaves the line out) */
add_filter(
	'get_the_author_user_url', // get_the_author_meta( 'url' ) reads the field user_url
	static function ( $url ) {
		return is_feed() ? '' : $url;
	},
	20
);

/* no users sitemap (wp-sitemap-users-1.xml) */
add_filter(
	'wp_sitemaps_add_provider',
	static function ( $provider, $name ) {
		return 'users' === $name ? false : $provider;
	},
	20,
	2
);

/* no application passwords (nobody uses the API from outside the site) */
add_filter( 'wp_is_application_passwords_available', '__return_false' );

/* ═══════════════════════════════════════════════════════════════════════
 * Login: generic errors, no hints, brute-force lock
 * ═════════════════════════════════════════════════════════════════════ */

/** Message shown for a wrong user name / e-mail or a wrong password. */
function mats_sec_login_failed_message(): string {
	return 'El usuario o la contraseña no son correctos. <a href="' . esc_url( wp_lostpassword_url() ) . '">¿Has olvidado la contraseña?</a>';
}

/* the same answer for a wrong user and a wrong password (no account guessing) */
add_filter(
	'wp_login_errors',
	static function ( $errors ) {
		if ( ! $errors instanceof WP_Error ) {
			return $errors;
		}
		$codes = array( 'invalid_username', 'invalid_email', 'incorrect_password', 'invalidcombo' );
		if ( ! array_intersect( $codes, $errors->get_error_codes() ) ) {
			return $errors;
		}
		foreach ( $codes as $code ) {
			$errors->remove( $code );
		}
		if ( ! in_array( 'mats_login_failed', $errors->get_error_codes(), true ) ) {
			$errors->add( 'mats_login_failed', mats_sec_login_failed_message() );
		}
		return $errors;
	},
	20
);

/*
 * "¿Has olvidado la contraseña?": an unknown user or e-mail gets exactly the
 * same answer as a real one ("check your e-mail"), so the form cannot be
 * used to find out which accounts exist. The redirect is a copy of the one
 * wp-login.php sends for a real account (same relative Location header,
 * same use of redirect_to), so not even the raw HTTP answer differs.
 */
add_action(
	'lostpassword_post',
	static function ( $errors, $user_data = false ) {
		if ( ! did_action( 'login_init' ) ) {
			return;
		}
		/* a redirect_to that is not text would break core's redirect for real
		   accounts only: ignore it for every account */
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- core's lost-password form has no nonce; read-only type check.
		if ( isset( $_REQUEST['redirect_to'] ) && ! is_string( $_REQUEST['redirect_to'] ) ) {
			unset( $_REQUEST['redirect_to'] );
		}
		if ( $user_data ) {
			return; // a real account: WordPress sends the e-mail and redirects
		}
		if ( $errors instanceof WP_Error && array_diff( $errors->get_error_codes(), array( 'invalid_email', 'invalidcombo' ) ) ) {
			return; // e.g. the field was empty: WordPress explains it
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- same as wp-login.php; wp_safe_redirect() validates it.
		$to = ! empty( $_REQUEST['redirect_to'] ) ? wp_unslash( $_REQUEST['redirect_to'] ) : 'wp-login.php?checkemail=confirm';
		wp_safe_redirect( $to );
		exit;
	},
	99,
	2
);

/** Lock thresholds: failures per user+IP, failures per IP, lock length (s). */
function mats_sec_login_limits(): array {
	$limits = wp_parse_args(
		(array) apply_filters( 'mats_security_login_limits', array() ),
		array(
			'user' => 5,
			'ip'   => 20,
			'lock' => 15 * MINUTE_IN_SECONDS,
		)
	);
	return array(
		'user' => max( 1, (int) $limits['user'] ),
		'ip'   => max( 1, (int) $limits['ip'] ),
		'lock' => max( MINUTE_IN_SECONDS, (int) $limits['lock'] ),
	);
}

/**
 * Visitor IP. REMOTE_ADDR only: forwarded headers can be faked by anyone.
 * Behind a proxy (Cloudflare…) adapt it with `mats_security_client_ip`.
 */
function mats_sec_client_ip(): string {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$ip = (string) apply_filters( 'mats_security_client_ip', $ip );
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
}

/**
 * Transient keys of the counters for a login attempt: one per IP + account
 * (the account is the same whether the person typed the user name or the
 * e-mail) and one per IP.
 */
function mats_sec_login_keys( string $username ): array {
	$who  = strtolower( trim( $username ) );
	$id   = mats_sec_login_user_id( $who );
	$who  = $id ? 'id:' . $id : 'name:' . $who;
	$ip   = mats_sec_client_ip();
	return array(
		'user' => 'mats_login_' . md5( $ip . '|' . $who ),
		'ip'   => 'mats_login_ip_' . md5( $ip ),
	);
}

/** ID of the account a user name or e-mail belongs to (0 = no such account). */
function mats_sec_login_user_id( string $username ): int {
	$who = strtolower( trim( $username ) );
	if ( '' === $who ) {
		return 0;
	}
	$user = is_email( $who ) ? get_user_by( 'email', $who ) : get_user_by( 'login', $who );
	return $user instanceof WP_User ? (int) $user->ID : 0;
}

/**
 * Current lock of a login attempt: [ 'until' => unix time (0 = not locked),
 * 'kind' => 'user' (that account from this IP) | 'ip' (this IP: a new
 * password would not help, so it takes precedence) | '' ].
 */
function mats_sec_login_lock( string $username ): array {
	$now  = time();
	$lock = array(
		'until' => 0,
		'kind'  => '',
	);
	foreach ( mats_sec_login_keys( $username ) as $kind => $key ) {
		$data  = get_transient( $key );
		$until = is_array( $data ) ? (int) ( $data['until'] ?? 0 ) : 0;
		if ( $until > $now ) {
			$lock['until'] = max( $lock['until'], $until );
			$lock['kind']  = ( 'ip' === $kind || 'ip' === $lock['kind'] ) ? 'ip' : (string) $kind;
		}
	}
	return $lock;
}

/** Unix time until which this attempt is locked (0 = not locked). */
function mats_sec_login_locked_until( string $username ): int {
	return mats_sec_login_lock( $username )['until'];
}

/**
 * Transient name that remembers every account counter of a user (one per IP
 * it was attacked from), so that changing the password can clear them all.
 */
function mats_sec_login_index_key( int $user_id ): string {
	return 'mats_login_idx_' . $user_id;
}

/** Remembers an account counter of a user (at most 50, the newest). */
function mats_sec_login_index_add( int $user_id, string $key, int $ends ): void {
	if ( $user_id <= 0 ) {
		return;
	}
	$now   = time();
	$index = get_transient( mats_sec_login_index_key( $user_id ) );
	$index = is_array( $index ) ? $index : array();
	$index = array_filter( $index, static fn( $until ) => (int) $until > $now );
	unset( $index[ $key ] );
	$index[ $key ] = $ends;
	$index         = array_slice( $index, -50, null, true );
	set_transient( mats_sec_login_index_key( $user_id ), $index, max( 1, max( $index ) - $now ) );
}

/**
 * Lifts the lock of an account on every IP (its password has just been
 * changed: the old guesses no longer matter). The per-IP counters stay.
 */
function mats_sec_login_unlock_user( int $user_id ): void {
	if ( $user_id <= 0 ) {
		return;
	}
	$index = get_transient( mats_sec_login_index_key( $user_id ) );
	foreach ( is_array( $index ) ? array_keys( $index ) : array() as $key ) {
		if ( is_string( $key ) && 0 === strpos( $key, 'mats_login_' ) ) {
			delete_transient( $key );
		}
	}
	delete_transient( mats_sec_login_index_key( $user_id ) );
}

/**
 * Counts one failed attempt; locks when a threshold is reached. Counters
 * live in a fixed window: they start with the first failure and vanish
 * `lock` seconds later, so occasional typos never add up over a day.
 */
function mats_sec_login_register_failure( string $username ): void {
	$limits  = mats_sec_login_limits();
	$now     = time();
	$user_id = mats_sec_login_user_id( $username );
	foreach ( mats_sec_login_keys( $username ) as $kind => $key ) {
		$data = get_transient( $key );
		$data = is_array( $data ) ? $data : array();
		if ( ! empty( $data['until'] ) && (int) $data['until'] > $now ) {
			continue; // already locked: the lock is not extended
		}
		$ends = (int) ( $data['ends'] ?? 0 );
		if ( $ends <= $now ) {
			$data = array();
			$ends = $now + $limits['lock'];
		}
		$count = (int) ( $data['count'] ?? 0 ) + 1;
		$data  = array(
			'count' => $count,
			'until' => 0,
			'ends'  => $ends,
		);
		if ( $count >= $limits[ $kind ] ) {
			$ends = $now + $limits['lock'];
			$data = array(
				'count' => 0,
				'until' => $ends,
				'ends'  => $ends,
			);
		}
		set_transient( $key, $data, max( 1, $ends - $now ) );
		if ( 'user' === $kind && $user_id ) {
			mats_sec_login_index_add( $user_id, $key, $ends );
		}
	}
}

/**
 * Friendly message for a locked login. An account lock is lifted by
 * changing the password («¿Has olvidado la contraseña?»); a lock of the
 * whole connection (too many failures with several names) only by waiting.
 */
function mats_sec_lock_message( int $until, string $kind = 'user' ): string {
	$minutes = max( 1, (int) ceil( ( $until - time() ) / MINUTE_IN_SECONDS ) );
	$wait    = 1 === $minutes ? '1 minuto' : $minutes . ' minutos';
	if ( 'ip' === $kind ) {
		return sprintf(
			'<strong>Acceso bloqueado un rato por seguridad.</strong> Desde esta conexión se han hecho demasiados intentos fallidos. Vuelve a intentarlo dentro de %s.',
			$wait
		);
	}
	return sprintf(
		'<strong>Acceso bloqueado un rato por seguridad.</strong> Se ha escrito mal la contraseña demasiadas veces. Vuelve a intentarlo dentro de %1$s. Si no recuerdas la contraseña, pide una nueva en <a href="%2$s">¿Has olvidado la contraseña?</a>: en cuanto la cambies podrás entrar.',
		$wait,
		esc_url( wp_lostpassword_url() )
	);
}

/* locked? refuse the attempt, even with the right password (runs last) */
add_filter(
	'authenticate',
	static function ( $user, $username = '', $password = '' ) {
		$username = is_string( $username ) ? $username : '';
		if ( '' === trim( $username ) ) {
			return $user;
		}
		$lock = mats_sec_login_lock( $username );
		if ( $lock['until'] ) {
			return new WP_Error( 'mats_login_locked', mats_sec_lock_message( $lock['until'], $lock['kind'] ) );
		}
		return $user;
	},
	99,
	3
);

add_action(
	'wp_login_failed',
	static function ( $username, $error = null ) {
		if ( $error instanceof WP_Error && in_array( $error->get_error_code(), array( 'mats_login_locked', 'empty_username', 'empty_password' ), true ) ) {
			return;
		}
		mats_sec_login_register_failure( (string) $username );
	},
	10,
	2
);

/* a successful login clears the counter of that account from that IP */
add_action(
	'wp_login',
	static function ( $user_login ) {
		$keys = mats_sec_login_keys( (string) $user_login );
		delete_transient( $keys['user'] );
	},
	10,
	1
);

/* a new password («¿Has olvidado la contraseña?», or set in the profile)
   lifts the lock of that account at once, from every IP */
add_action(
	'after_password_reset',
	static function ( $user ) {
		if ( $user instanceof WP_User ) {
			mats_sec_login_unlock_user( (int) $user->ID );
		}
	},
	10,
	1
);
/* (not 'wp_set_password': WordPress also fires it when it re-encrypts the
   password during a login, which would let a locked attempt through) */
add_action(
	'profile_update',
	static function ( $user_id, $old_user_data = null, $userdata = array() ) {
		if ( $old_user_data instanceof WP_User && is_array( $userdata ) && ! empty( $userdata['user_pass'] ) && $userdata['user_pass'] !== $old_user_data->user_pass ) {
			mats_sec_login_unlock_user( (int) $user_id );
		}
	},
	10,
	3
);

/* ═══════════════════════════════════════════════════════════════════════
 * Security headers (site, admin, login)
 * ═════════════════════════════════════════════════════════════════════ */

/** Headers sent with every page. */
function mats_sec_headers(): array {
	return (array) apply_filters(
		'mats_security_headers',
		array(
			'X-Content-Type-Options' => 'nosniff',
			'Referrer-Policy'        => 'strict-origin-when-cross-origin',
			'X-Frame-Options'        => 'SAMEORIGIN',
			'Permissions-Policy'     => 'camera=(), microphone=(), geolocation=(), interest-cohort=()',
		)
	);
}

/* public site */
add_filter(
	'wp_headers',
	static function ( $headers, $wp = null ) {
		$headers = is_array( $headers ) ? $headers : array();
		unset( $headers['X-Pingback'] );
		/* WordPress' own embed cards (/embed/) are meant to be shown inside other sites */
		$embed = $wp instanceof WP && ! empty( $wp->query_vars['embed'] );
		foreach ( mats_sec_headers() as $name => $value ) {
			if ( $embed && 'X-Frame-Options' === $name ) {
				continue;
			}
			$headers[ $name ] = $value;
		}
		return $headers;
	},
	20,
	2
);

add_action(
	'send_headers',
	static function () {
		if ( ! headers_sent() ) {
			header_remove( 'X-Powered-By' );
		}
	}
);

/** Admin screens, admin-ajax / admin-post and the login screen. */
function mats_sec_send_headers(): void {
	if ( headers_sent() ) {
		return;
	}
	foreach ( mats_sec_headers() as $name => $value ) {
		header( $name . ': ' . $value );
	}
	header_remove( 'X-Powered-By' );
}
add_action( 'admin_init', 'mats_sec_send_headers', 1 );
add_action( 'login_init', 'mats_sec_send_headers', 1 );

/* ═══════════════════════════════════════════════════════════════════════
 * Feeds: the news feed stays, comment feeds go
 * ═════════════════════════════════════════════════════════════════════ */

add_filter( 'feed_links_show_comments_feed', '__return_false' );
add_filter( 'feed_links_extra_show_post_comments_feed', '__return_false' );

add_action(
	'template_redirect',
	static function () {
		if ( is_comment_feed() ) {
			wp_safe_redirect( function_exists( 'mats_home_url' ) ? mats_home_url() : home_url( '/' ), 301 );
			exit;
		}
	},
	1
);

/* ═══════════════════════════════════════════════════════════════════════
 * Automatic updates
 * ═════════════════════════════════════════════════════════════════════ */

/** Plugins (folder names) that always update themselves in the background. */
function mats_sec_auto_update_plugins(): array {
	return array_map( 'strval', (array) apply_filters( 'mats_security_auto_update_plugins', array( 'polylang', 'wpforms-lite', 'wp-mail-smtp' ) ) );
}

add_filter(
	'auto_update_plugin',
	static function ( $update, $item = null ) {
		if ( ! is_object( $item ) ) {
			return $update;
		}
		$slug = isset( $item->slug ) ? (string) $item->slug : '';
		if ( '' === $slug && ! empty( $item->plugin ) ) {
			$slug = dirname( (string) $item->plugin );
		}
		return in_array( $slug, mats_sec_auto_update_plugins(), true ) ? true : $update;
	},
	20,
	2
);

/* minor WordPress releases (security fixes) keep installing themselves */
add_filter( 'allow_minor_auto_core_updates', '__return_true', 20 );

/* ═══════════════════════════════════════════════════════════════════════
 * Uploads: no executable or script files
 * ═════════════════════════════════════════════════════════════════════ */

/** File extensions never accepted by the media library. */
function mats_sec_blocked_extensions(): array {
	return array_map(
		'strtolower',
		(array) apply_filters(
			'mats_security_blocked_extensions',
			array(
				'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phar', 'phps',
				'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'asp', 'aspx', 'jsp', 'exe', 'com', 'bat', 'cmd',
				'msi', 'dll', 'vbs', 'ps1', 'jar', 'js', 'mjs', 'htm', 'html', 'shtml', 'xhtml',
				'svg', 'svgz', 'swf', 'htaccess', 'htpasswd',
			)
		)
	);
}

/* not even administrators get them in the list of allowed types */
add_filter(
	'upload_mimes',
	static function ( $mimes ) {
		$blocked = mats_sec_blocked_extensions();
		foreach ( (array) $mimes as $exts => $mime ) {
			if ( array_intersect( explode( '|', strtolower( (string) $exts ) ), $blocked ) ) {
				unset( $mimes[ $exts ] );
			}
		}
		return $mimes;
	},
	999
);

/**
 * Extensions a web server may run even in the middle of a name
 * ("photo.php.jpg": PHP handlers, server-side includes, CGI). Not .pl / .py:
 * servers do not run them from uploads, and names such as "informe.pl.png"
 * (Polish version) or "figura.py.png" are normal.
 */
function mats_sec_server_extensions(): array {
	return array( 'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phar', 'phps', 'cgi', 'asp', 'aspx', 'jsp', 'shtml' );
}

/**
 * Why a file name is refused: '' (accepted), 'hidden' (".htaccess"),
 * 'svg', 'type' (blocked final extension) or 'middle:<ext>' (a server-run
 * extension after the first dot, "photo.php.jpg").
 */
function mats_sec_filename_problem( string $name ): string {
	$name = strtolower( trim( wp_basename( $name ) ) );
	if ( '' === $name ) {
		return '';
	}
	if ( '.' === $name[0] ) {
		return 'hidden';
	}
	$parts = explode( '.', $name );
	array_shift( $parts );
	if ( ! $parts ) {
		return '';
	}
	$last = (string) end( $parts );
	if ( in_array( $last, mats_sec_blocked_extensions(), true ) ) {
		return in_array( $last, array( 'svg', 'svgz' ), true ) ? 'svg' : 'type';
	}
	$middle = array_values( array_intersect( $parts, mats_sec_server_extensions() ) );
	return $middle ? 'middle:' . $middle[0] : '';
}

/** Is this file name dangerous? (see mats_sec_filename_problem()) */
function mats_sec_is_dangerous_filename( string $name ): bool {
	return '' !== mats_sec_filename_problem( $name );
}

/** Spanish message for a refused file name. */
function mats_sec_filename_message( string $name, string $problem ): string {
	if ( 'svg' === $problem ) {
		return 'Por seguridad no se pueden subir imágenes SVG. Usa una foto JPG o PNG.';
	}
	if ( 0 === strpos( $problem, 'middle:' ) ) {
		$ext  = substr( $problem, 7 );
		$safe = (string) preg_replace_callback(
			'/\.(' . implode( '|', array_map( static fn( $e ) => preg_quote( $e, '/' ), mats_sec_server_extensions() ) ) . ')(?=\.)/i',
			static fn( $m ) => '-' . $m[1],
			wp_basename( $name )
		);
		$safe = sanitize_file_name( $safe );
		return sprintf(
			'El nombre del archivo lleva «.%1$s.» en medio y, por seguridad, no se acepta. Cámbiale el nombre (por ejemplo, «%2$s») y vuelve a subirlo.',
			$ext,
			$safe
		);
	}
	return 'Por seguridad no se puede subir este tipo de archivo. Sube fotos (JPG, PNG o WebP), vídeos MP4 o documentos PDF.';
}

/** wp_handle_upload / wp_handle_sideload prefilter. */
function mats_sec_upload_prefilter( $file ) {
	if ( ! is_array( $file ) || ! empty( $file['error'] ) ) {
		return $file;
	}
	$name = (string) ( $file['name'] ?? '' );
	/* plugin / theme packages uploaded by an administrator are WordPress' business */
	if ( preg_match( '/\.zip$/i', $name ) && ( current_user_can( 'install_plugins' ) || current_user_can( 'install_themes' ) ) ) {
		return $file;
	}
	$problem = mats_sec_filename_problem( $name );
	if ( '' !== $problem ) {
		$file['error'] = mats_sec_filename_message( $name, $problem );
	}
	return $file;
}
add_filter( 'wp_handle_upload_prefilter', 'mats_sec_upload_prefilter', 5 );
add_filter( 'wp_handle_sideload_prefilter', 'mats_sec_upload_prefilter', 5 );

/* last line of defence when WordPress checks the real type of a file */
add_filter(
	'wp_check_filetype_and_ext',
	static function ( $data, $file = '', $filename = '' ) {
		if ( is_string( $filename ) && '' !== $filename && mats_sec_is_dangerous_filename( $filename ) && ! preg_match( '/\.zip$/i', $filename ) ) {
			return array(
				'ext'             => false,
				'type'            => false,
				'proper_filename' => false,
			);
		}
		return $data;
	},
	99,
	3
);
