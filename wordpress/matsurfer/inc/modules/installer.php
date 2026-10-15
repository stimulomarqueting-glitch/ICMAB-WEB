<?php
/**
 * Setup wizard — "MatSurfer → Puesta en marcha" (administrators only).
 * Turns a fresh WordPress into the full MatSurfer site in a few clicks; safe
 * to repeat. The steps live in inc/installer/*.php (see runner.php for the
 * contract); this file only wires them into WordPress:
 *
 *  - admin menu (under the "MatSurfer" menu when another module created it,
 *    otherwise it creates that menu itself)
 *  - reminder notice after the theme is activated, until the setup is
 *    finished or the notice is hidden
 *  - warning when Polylang stops running after the setup (the Spanish half
 *    of the site is offline): administrators, editors and the block editor
 *  - the structural pages store their current version in «Revisiones»
 *    before any change (pages created by the setup had none)
 *  - handlers: admin-post.php?action=mats_installer_run (no-JS form),
 *    admin-ajax.php?action=mats_installer_step / mats_installer_status (JS),
 *    admin-post.php?action=mats_installer_notice (hide the reminder)
 *    every handler checks manage_options + the 'mats_installer' nonce.
 *
 * CLI / tests: mats_installer_run_all( $opts ) — see
 * wordpress/tools/qa/installer-run.php.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

foreach ( array( 'runner', 'plugins', 'languages', 'content', 'forms', 'review', 'admin-page' ) as $mats_installer_part ) {
	require_once MATS_DIR . '/inc/installer/' . $mats_installer_part . '.php';
}
unset( $mats_installer_part );

/* the structural pages keep their current version in «Revisiones» before they change (see content.php) */
add_action( 'pre_post_update', 'mats_installer_revision_before_update', 10, 1 );

/* ── theme activation → reminder ─────────────────────────────────────── */

add_action(
	'after_switch_theme',
	static function () {
		$state = mats_installer_state();
		if ( empty( $state['done'] ) ) {
			$state['notice'] = 'show';
			mats_installer_save_state( $state );
		}
	}
);

add_action(
	'admin_notices',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && ( false !== strpos( (string) $screen->id, MATS_SETUP_PAGE ) || $screen->is_block_editor() ) ) {
			return;
		}
		$state = mats_installer_state();
		if ( 'show' !== $state['notice'] || ! empty( $state['done'] ) ) {
			return;
		}
		/* every step already done (e.g. "Hacerlo todo" finished): the reminder goes away for good */
		if ( mats_installer_maybe_hide_notice() ) {
			return;
		}
		$hide = wp_nonce_url( add_query_arg( 'action', 'mats_installer_notice', admin_url( 'admin-post.php' ) ), MATS_SETUP_NONCE );
		$also = mats_installer_notice_also( $screen ? (string) $screen->id : '' );
		?>
		<div class="notice notice-info mats-setup-notice">
			<p><strong>El tema MatSurfer está activado.</strong> Para dejar la web lista (plugins, idiomas, páginas con los textos originales y formulario de contacto) abre la puesta en marcha: son unos clics y se puede repetir sin miedo.</p>
			<?php if ( $also ) : ?>
				<p><?php echo esc_html( sprintf( 'La puesta en marcha también %s, así que no hace falta seguir a mano los otros avisos de esta pantalla.', implode( ' y ', $also ) ) ); ?></p>
			<?php endif; ?>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( mats_installer_url() ); ?>">Abrir la puesta en marcha</a>
				<a class="button-link" href="<?php echo esc_url( $hide ); ?>" style="margin-left:.75em">Ocultar este aviso</a>
			</p>
		</div>
		<?php
	},
	1 // above the other notices it answers (WPForms missing, panel not in Spanish)
);

/**
 * Other notices on this screen that tell the administrator to do by hand
 * what the wizard does on its own: WPForms missing or its forms missing
 * (inc/modules/wpforms.php) and the panel not in Spanish
 * (inc/modules/admin.php, dashboard and guide). Returns what the reminder should add
 * ("instala y activa WPForms"…).
 */
function mats_installer_notice_also( string $screen_id ): array {
	$also  = array();
	$forms = function_exists( 'mats_wpforms_status' ) ? mats_wpforms_status() : null;
	if ( $forms && empty( $forms['active'] ) && in_array( $screen_id, array( 'dashboard', 'plugins' ), true ) ) {
		$also[] = 'instala y activa WPForms';
	} elseif ( $forms && ! empty( $forms['active'] ) && ! empty( $forms['missing'] ) && in_array( $screen_id, array( 'dashboard', 'edit-page', 'plugins', 'themes' ), true ) ) {
		$also[] = 'crea los formularios de contacto (después de crear las páginas)';
	}
	$es_screens = array( 'dashboard', defined( 'MATS_GUIDE_PAGE' ) ? 'toplevel_page_' . MATS_GUIDE_PAGE : 'dashboard' );
	if ( in_array( $screen_id, $es_screens, true ) && function_exists( 'mats_admin_spanish_available' ) && ! mats_admin_spanish_available() && ! get_user_meta( get_current_user_id(), 'mats_hide_es_notice', true ) ) {
		$also[] = 'pone el panel de WordPress en español (si el servidor puede descargar la traducción)';
	}
	return $also;
}

/* after a theme update: sections that were created empty can now be filled */
add_action(
	'admin_notices',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'edit-page', 'themes' ), true ) ) {
			return;
		}
		$pages = mats_installer_fillable_pending();
		if ( ! $pages ) {
			return;
		}
		?>
		<div class="notice notice-info">
			<p><strong>Hay textos nuevos listos para la web.</strong> Esta versión del tema trae los textos originales de secciones que se crearon vacías (<?php echo esc_html( (string) count( $pages ) ); ?> páginas). Abre la puesta en marcha y pulsa «Repetir este paso» en «Páginas y contenido»: se rellenarán sin tocar nada de lo que ya hayáis editado.</p>
			<p><a class="button button-primary" href="<?php echo esc_url( mats_installer_url() . '#mats-step-content' ); ?>">Abrir la puesta en marcha</a></p>
		</div>
		<?php
	}
);

/* ── Polylang switched off after the setup: the Spanish site is offline ── */

/**
 * [ url, label ] of the button that brings Polylang back: activates it
 * when it is installed, otherwise opens the wizard (its step 1 installs it).
 */
function mats_installer_polylang_fix_link(): array {
	$plugin = mats_installer_plugin_state( 'polylang' );
	if ( 'installed' === $plugin['state'] && $plugin['file'] && current_user_can( 'activate_plugin', $plugin['file'] ) ) {
		$url = add_query_arg(
			array(
				'action' => 'activate',
				'plugin' => $plugin['file'],
			),
			admin_url( 'plugins.php' )
		);
		/* raw URL (no &amp;): it is also handed to a block-editor notice in JS */
		return array( add_query_arg( '_wpnonce', wp_create_nonce( 'activate-plugin_' . $plugin['file'] ), $url ), 'Activar Polylang' );
	}
	return array( mats_installer_url() . '#mats-step-plugins', 'Abrir la puesta en marcha' );
}

/** Short sentence for the people who edit the content (they cannot activate plugins). */
function mats_installer_spanish_offline_editor_text(): string {
	return 'La web en español no se ve ahora mismo: el plugin de idiomas (Polylang) está desactivado. Avisa al administrador de la web; en cuanto lo active, todo vuelve a estar como antes (no se ha perdido nada).';
}

/* every admin screen (the block editor gets its own notice below) */
add_action(
	'admin_notices',
	static function () {
		if ( ! current_user_can( 'edit_posts' ) || ! mats_installer_spanish_offline() ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && $screen->is_block_editor() ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			$email = (string) get_option( 'admin_email' );
			echo '<div class="notice notice-warning mats-pll-off-notice"><p><strong>' . esc_html( 'La web en español no se ve ahora mismo.' ) . '</strong> ';
			echo esc_html( 'El plugin de idiomas (Polylang) está desactivado. Avisa al administrador de la web' );
			echo $email ? ' (<a href="' . esc_url( 'mailto:' . $email ) . '">' . esc_html( $email ) . '</a>)' : '';
			echo esc_html( ': en cuanto lo active, todo vuelve a estar como antes; no se ha perdido nada.' ) . '</p></div>';
			return;
		}
		$missing             = 'missing' === mats_installer_plugin_state( 'polylang' )['state'];
		list( $url, $label ) = mats_installer_polylang_fix_link();
		?>
		<div class="notice notice-error mats-pll-off-notice">
			<p>
				<strong><?php echo esc_html( $missing ? 'Falta el plugin Polylang: la web en español no se ve.' : 'Polylang está desactivado: la web en español no se ve.' ); ?></strong>
				Sin él, las páginas en español (las que empiezan por /es/) dan «página no encontrada» o llevan a la versión en inglés, y desaparece el botón EN / ES de la web. No se ha borrado nada: en cuanto Polylang vuelva a estar activo, la web en español vuelve tal como estaba.
				<?php echo esc_html( $missing ? 'Para recuperarlo, abre la puesta en marcha y pulsa el botón del paso 1 («Plugins necesarios»).' : 'Actívalo con el botón de abajo o en Plugins.' ); ?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php if ( ! $missing ) : ?>
					<a class="button" href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>">Ir a Plugins</a>
				<?php endif; ?>
			</p>
		</div>
		<?php
	},
	1
);

/* the block editor does not show the notices above: same warning inside it */
add_action(
	'enqueue_block_editor_assets',
	static function () {
		if ( ! current_user_can( 'edit_posts' ) || ! mats_installer_spanish_offline() ) {
			return;
		}
		$admin  = current_user_can( 'manage_options' );
		$notice = array(
			'text'   => $admin ? 'Polylang está desactivado: la web en español (/es/) no se ve. No se ha perdido nada: actívalo de nuevo y vuelve tal como estaba.' : mats_installer_spanish_offline_editor_text(),
			'action' => array(),
		);
		if ( $admin ) {
			list( $url, $label ) = mats_installer_polylang_fix_link();
			$notice['action']    = array(
				array(
					'label' => $label,
					'url'   => $url,
				),
			);
		}
		wp_register_script( 'matsurfer-pll-off', false, array( 'wp-data', 'wp-notices', 'wp-dom-ready' ), mats_ver( 'inc/modules/installer.php' ), true );
		wp_enqueue_script( 'matsurfer-pll-off' );
		wp_add_inline_script(
			'matsurfer-pll-off',
			'(function(wp,n){if(!wp||!wp.data||!wp.domReady){return;}wp.domReady(function(){var s=wp.data.dispatch("core/notices");if(s){s.createWarningNotice(n.text,{id:"mats-pll-off",isDismissible:true,actions:n.action});}});})(window.wp,' . wp_json_encode( $notice ) . ');'
		);
	}
);

add_action(
	'admin_post_mats_installer_notice',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( 'No tienes permiso para hacer esto.' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( MATS_SETUP_NONCE );
		$state           = mats_installer_state();
		$state['notice'] = 'hide';
		mats_installer_save_state( $state );
		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : admin_url() );
		exit;
	}
);

/* ── menu ────────────────────────────────────────────────────────────── */

/** Slug of an existing top-level "MatSurfer" menu, '' if none. */
function mats_installer_menu_parent(): string {
	global $menu;
	foreach ( (array) $menu as $item ) {
		$title = trim( wp_strip_all_tags( (string) ( $item[0] ?? '' ) ) );
		$slug  = (string) ( $item[2] ?? '' );
		if ( $slug && MATS_SETUP_PAGE !== $slug && 0 === strcasecmp( $title, 'MatSurfer' ) ) {
			return $slug;
		}
	}
	return '';
}

add_action(
	'admin_menu',
	static function () {
		$parent = mats_installer_menu_parent();
		if ( ! $parent ) {
			add_menu_page( 'MatSurfer', 'MatSurfer', 'manage_options', MATS_SETUP_PAGE, 'mats_installer_render_page', 'dashicons-admin-site-alt3', 58 );
			$parent = MATS_SETUP_PAGE;
		}
		$hook = add_submenu_page( $parent, 'Puesta en marcha', 'Puesta en marcha', 'manage_options', MATS_SETUP_PAGE, 'mats_installer_render_page', 0 );
		if ( $hook ) {
			$GLOBALS['mats_installer_hook'] = $hook;
		}
	},
	99
);

/* ── assets (only on the wizard screen) ──────────────────────────────── */

add_action(
	'admin_enqueue_scripts',
	static function ( $hook_suffix ) {
		if ( empty( $GLOBALS['mats_installer_hook'] ) || $hook_suffix !== $GLOBALS['mats_installer_hook'] ) {
			return;
		}
		wp_enqueue_style( 'matsurfer-installer', MATS_URI . '/assets/css/installer.css', array(), mats_ver( 'assets/css/installer.css' ) );
		wp_enqueue_script( 'matsurfer-installer', MATS_URI . '/assets/js/installer.js', array(), mats_ver( 'assets/js/installer.js' ), true );
		wp_add_inline_script(
			'matsurfer-installer',
			'window.matsInstaller = ' . wp_json_encode(
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( MATS_SETUP_NONCE ),
					'steps'   => mats_installer_auto_steps(),
					'titles'  => wp_list_pluck( mats_installer_steps(), 'title' ),
					'text'    => array(
						'confirm'   => mats_installer_overwrite_question() . "\n\n" . mats_installer_overwrite_warning(),
						'working'   => 'Trabajando… no cierres esta pestaña.',
						'stepStart' => 'Haciendo: %s…',
						'allDone'   => 'Listo. Revisa el resultado de cada paso y termina con el paso 5 (Revisión final).',
						'allWarn'   => 'Terminado, pero algún paso necesita atención: busca los mensajes marcados con ✗ o !.',
						'failed'    => 'No se ha podido completar este paso (no hay respuesta del servidor o ha tardado demasiado). Recarga la página y vuelve a intentarlo: lo que ya esté hecho no se repite.',
						'stepDone'  => 'Paso terminado.',
					),
				)
			) . ';',
			'before'
		);
	}
);

/* ── handlers ────────────────────────────────────────────────────────── */

/** Steps a request may ask for (incl. the sub-actions 'smtp' and 'all'). */
function mats_installer_request_step(): string {
	// phpcs:ignore WordPress.Security.NonceVerification -- verified by the callers before use.
	$step = isset( $_REQUEST['step'] ) && is_scalar( $_REQUEST['step'] ) ? sanitize_key( wp_unslash( $_REQUEST['step'] ) ) : '';
	$ok   = array_merge( array_keys( mats_installer_steps() ), array( 'smtp', 'all' ) );
	return in_array( $step, $ok, true ) ? $step : '';
}

/** Is a request flag set to exactly '1'? */
function mats_installer_request_flag( string $name ): bool {
	// phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified by the callers; compared to a constant.
	return isset( $_REQUEST[ $name ] ) && is_scalar( $_REQUEST[ $name ] ) && '1' === (string) wp_unslash( $_REQUEST[ $name ] );
}

/** The "Sobrescribir los textos y las fotos" box was ticked (confirmed or not). */
function mats_installer_overwrite_requested(): bool {
	return mats_installer_request_flag( 'overwrite' );
}

/**
 * Options of a request. Overwriting the page texts needs BOTH the ticked
 * box (overwrite=1) and the confirmation (confirm=1): the JS dialog or the
 * server-rendered confirmation box when JavaScript is off.
 */
function mats_installer_request_opts(): array {
	// phpcs:ignore WordPress.Security.NonceVerification -- verified by the callers before use.
	$from = isset( $_REQUEST['from'] ) && is_scalar( $_REQUEST['from'] ) ? sanitize_key( wp_unslash( $_REQUEST['from'] ) ) : '';
	return array(
		'overwrite' => mats_installer_overwrite_requested() && mats_installer_request_flag( 'confirm' ),
		'install'   => true,
		'from'      => in_array( $from, mats_installer_auto_steps(), true ) ? $from : '',
	);
}

/** Does this step overwrite page texts when the box is ticked? */
function mats_installer_step_can_overwrite( string $step ): bool {
	return in_array( $step, array( 'content', 'all' ), true );
}

/** Runs what a request asked for: one step, the SMTP sub-action, or a single step of "all". */
function mats_installer_dispatch( string $step, array $opts ): array {
	if ( 'smtp' === $step ) {
		return mats_installer_run_step( 'plugins', array_merge( $opts, array( 'only' => 'smtp' ) ) );
	}
	return mats_installer_run_step( $step, $opts );
}

/* no-JS: one form post; "all" continues on its own after plugin activation */
add_action(
	'admin_post_mats_installer_run',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( 'No tienes permiso para hacer esto.' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( MATS_SETUP_NONCE );
		$step = mats_installer_request_step();
		$opts = mats_installer_request_opts();
		if ( ! $step ) {
			wp_safe_redirect( mats_installer_url() );
			exit;
		}
		if ( mats_installer_overwrite_requested() && ! $opts['overwrite'] && mats_installer_step_can_overwrite( $step ) ) {
			/* no JavaScript, so no dialog: ask on the screen first, nothing runs yet */
			wp_safe_redirect( mats_installer_url( array( 'mats_confirm' => $step ) ) . '#mats-confirm' );
			exit;
		}
		if ( 'all' === $step ) {
			$run = mats_installer_run_all( $opts );
			if ( $run['reload'] && $run['next'] ) {
				/* the next steps need the plugin just activated: continue in a new request */
				wp_safe_redirect(
					add_query_arg(
						array(
							'action'    => 'mats_installer_run',
							'step'      => 'all',
							'from'      => $run['next'],
							'overwrite' => $opts['overwrite'] ? '1' : '0',
							'confirm'   => $opts['overwrite'] ? '1' : '0',
							'_wpnonce'  => wp_create_nonce( MATS_SETUP_NONCE ),
						),
						admin_url( 'admin-post.php' )
					)
				);
				exit;
			}
			wp_safe_redirect( mats_installer_url( array( 'mats_ran' => 'all' ) ) . '#mats-step-review' );
			exit;
		}
		mats_installer_dispatch( $step, $opts );
		$anchor = 'smtp' === $step ? 'plugins' : $step;
		wp_safe_redirect( mats_installer_url( array( 'mats_ran' => $anchor ) ) . '#mats-step-' . $anchor );
		exit;
	}
);

add_filter(
	'removable_query_args',
	static function ( $args ) {
		$args[] = 'mats_ran';
		$args[] = 'mats_confirm';
		return $args;
	}
);

/* JS: one step per request (a plugin activated in one request is loaded in the next) */
add_action(
	'wp_ajax_mats_installer_step',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permiso para hacer esto.' ), 403 );
		}
		check_ajax_referer( MATS_SETUP_NONCE );
		$step = mats_installer_request_step();
		if ( ! $step || 'all' === $step ) {
			wp_send_json_error( array( 'message' => 'Paso desconocido.' ), 400 );
		}
		$opts = mats_installer_request_opts();
		if ( mats_installer_overwrite_requested() && ! $opts['overwrite'] && mats_installer_step_can_overwrite( $step ) ) {
			wp_send_json_error( array( 'message' => 'Falta confirmar que quieres sobrescribir los textos y las fotos. Recarga la página y vuelve a intentarlo.' ), 400 );
		}
		$result = mats_installer_dispatch( $step, $opts );
		$card   = 'smtp' === $step ? 'plugins' : $step;
		wp_send_json_success(
			array(
				'step'   => $card,
				'ok'     => (bool) $result['ok'],
				'reload' => (bool) $result['reload'],
				'html'   => mats_installer_messages_html( (array) $result['messages'] ),
				'result' => array(
					'messages' => $result['messages'],
					'data'     => $result['data'],
				),
			)
		);
	}
);

/* JS: fresh ✓/✗ state of every card after running */
add_action(
	'wp_ajax_mats_installer_status',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permiso para hacer esto.' ), 403 );
		}
		check_ajax_referer( MATS_SETUP_NONCE );
		mats_installer_prepare();
		$steps    = array();
		$all_done = true;
		foreach ( array_keys( mats_installer_steps() ) as $key ) {
			$status        = mats_installer_status( $key );
			$steps[ $key ] = array(
				'state'  => $status['state'],
				'pill'   => mats_installer_pill_html( $status ),
				'checks' => mats_installer_checks_html( $status ),
				'button' => 'done' === $status['state'] && 'review' !== $key ? 'Repetir este paso' : mats_installer_steps()[ $key ]['button'],
			);
			if ( in_array( $key, mats_installer_auto_steps(), true ) && 'done' !== $status['state'] ) {
				$all_done = false;
			}
		}
		/* end of "Hacerlo todo" (or of any step) with everything done: no more reminder */
		mats_installer_maybe_hide_notice( $all_done );
		wp_send_json_success(
			array(
				'steps'   => $steps,
				'summary' => mats_installer_summary_html(),
				'smtp'    => 'active' === mats_installer_plugin_state( 'smtp' )['state'],
			)
		);
	}
);
