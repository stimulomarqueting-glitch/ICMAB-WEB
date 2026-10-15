<?php
/**
 * Setup step 1 — plugins: Polylang and WPForms Lite (required), WP Mail SMTP
 * (recommended, only when asked). Missing plugins are downloaded from
 * wordpress.org with the core installer (plugins_api + Plugin_Upgrader);
 * installed but inactive ones are activated. When the server cannot reach
 * wordpress.org the step explains how to install them by hand.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

/** The plugins the site uses. 'files' = accepted main files (free / Pro). */
function mats_installer_plugin_specs(): array {
	return array(
		'polylang' => array(
			'name'     => 'Polylang',
			'slug'     => 'polylang',
			'files'    => array( 'polylang/polylang.php', 'polylang-pro/polylang.php' ),
			'required' => true,
			'why'      => 'gestiona los dos idiomas de la web (inglés y español)',
			'loaded'   => static fn() => defined( 'POLYLANG_VERSION' ) || function_exists( 'PLL' ),
		),
		'wpforms'  => array(
			'name'     => 'WPForms Lite',
			'slug'     => 'wpforms-lite',
			'files'    => array( 'wpforms-lite/wpforms.php', 'wpforms/wpforms.php' ),
			'required' => true,
			'why'      => 'muestra el formulario de contacto y envía los mensajes',
			'loaded'   => static fn() => function_exists( 'wpforms' ),
		),
		'smtp'     => array(
			'name'     => 'WP Mail SMTP',
			'slug'     => 'wp-mail-smtp',
			'files'    => array( 'wp-mail-smtp/wp_mail_smtp.php', 'wp-mail-smtp-pro/wp_mail_smtp.php' ),
			'required' => false,
			'why'      => 'hace que los correos del formulario lleguen bien y no acaben en spam',
			'loaded'   => static fn() => function_exists( 'wp_mail_smtp' ),
		),
	);
}

/**
 * [ 'state' => active|installed|missing, 'file' => main file or '' ].
 * A plugin counts as active when its code is loaded (covers Pro versions).
 */
function mats_installer_plugin_state( string $key ): array {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$spec = mats_installer_plugin_specs()[ $key ] ?? null;
	if ( ! $spec ) {
		return array(
			'state' => 'missing',
			'file'  => '',
		);
	}
	$installed = get_plugins();
	$file      = '';
	foreach ( $spec['files'] as $candidate ) {
		if ( isset( $installed[ $candidate ] ) ) {
			$file = $candidate;
			if ( is_plugin_active( $candidate ) ) {
				return array(
					'state' => 'active',
					'file'  => $candidate,
				);
			}
		}
	}
	if ( ( $spec['loaded'] )() ) {
		return array(
			'state' => 'active',
			'file'  => $file,
		);
	}
	return array(
		'state' => $file ? 'installed' : 'missing',
		'file'  => $file,
	);
}

/** "Install it by hand" instructions (message with a link). */
function mats_installer_manual_plugin_msg( array &$result, string $key ): void {
	$spec = mats_installer_plugin_specs()[ $key ];
	mats_installer_add(
		$result,
		'info',
		sprintf(
			'Cómo instalar %1$s a mano: ve a Plugins → Añadir nuevo, busca «%2$s», pulsa «Instalar ahora» y después «Activar». Luego vuelve a esta pantalla y repite este paso. Si el servidor no tiene acceso a internet, descarga el archivo zip desde wordpress.org/plugins/%3$s en tu ordenador y súbelo en Plugins → Añadir nuevo → Subir plugin.',
			$spec['name'],
			'wpforms' === $key ? 'WPForms' : $spec['name'],
			$spec['slug']
		),
		admin_url( 'plugin-install.php?tab=search&type=term&s=' . rawurlencode( 'wpforms' === $key ? 'wpforms' : $spec['slug'] ) ),
		'Ir a Plugins → Añadir nuevo'
	);
}

/** Keeps the plugins' own welcome screens from hijacking the next page load. */
function mats_installer_skip_plugin_welcome( string $key ): void {
	$transients = array(
		'polylang' => array( 'pll_activation_redirect' ),
		'wpforms'  => array( 'wpforms_activation_redirect' ),
		'smtp'     => array( 'wp_mail_smtp_activation_redirect' ),
	);
	foreach ( $transients[ $key ] ?? array() as $transient ) {
		delete_transient( $transient );
	}
}

/** Activates an installed plugin. True on success. */
function mats_installer_activate_plugin( array &$result, string $key, string $file ): bool {
	$spec = mats_installer_plugin_specs()[ $key ];
	if ( ! current_user_can( 'activate_plugins' ) ) {
		mats_installer_add( $result, 'error', sprintf( 'Tu usuario no tiene permiso para activar plugins, así que %s sigue desactivado. Pide a un administrador que lo active.', $spec['name'] ) );
		return false;
	}
	$activated = activate_plugin( $file, '', false, false );
	if ( is_wp_error( $activated ) ) {
		mats_installer_add( $result, 'error', sprintf( 'No se ha podido activar %1$s: %2$s', $spec['name'], wp_strip_all_tags( $activated->get_error_message() ) ), admin_url( 'plugins.php' ), 'Ir a Plugins' );
		return false;
	}
	mats_installer_skip_plugin_welcome( $key );
	mats_installer_flag_activation( $key );
	$result['reload'] = true;
	return true;
}

/**
 * Downloads and installs a plugin from wordpress.org. Returns its main file,
 * or '' (the reason is added to $result).
 */
function mats_installer_install_plugin( array &$result, string $key ): string {
	$spec = mats_installer_plugin_specs()[ $key ];

	if ( ! current_user_can( 'install_plugins' ) ) {
		mats_installer_add( $result, 'error', sprintf( 'Tu usuario no tiene permiso para instalar plugins, así que no se ha podido instalar %s.', $spec['name'] ) );
		return '';
	}
	if ( ! wp_is_file_mod_allowed( 'mats_installer_plugins' ) ) {
		mats_installer_add( $result, 'error', sprintf( 'Esta instalación de WordPress no permite instalar plugins desde el escritorio (está bloqueado en su configuración), así que no se ha podido instalar %s.', $spec['name'] ) );
		return '';
	}

	require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';

	/* plugins_api() reports network problems as PHP warnings too: keep them out of the log, the message below says it */
	$notes = array();
	set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		static function ( $errno, $errstr ) use ( &$notes ) {
			$notes[] = wp_strip_all_tags( (string) $errstr );
			return true;
		},
		E_USER_WARNING | E_USER_NOTICE
	);
	try {
		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => $spec['slug'],
				'fields' => array(
					'sections'          => false,
					'short_description' => false,
					'description'       => false,
					'reviews'           => false,
					'banners'           => false,
					'icons'             => false,
					'tags'              => false,
					'ratings'           => false,
					'contributors'      => false,
					'versions'          => false,
					'screenshots'       => false,
					'compatibility'     => false,
					'donate_link'       => false,
				),
			)
		);
	} finally {
		restore_error_handler();
	}

	if ( is_wp_error( $api ) || empty( $api->download_link ) ) {
		$http = function_exists( '_wp_http_get_object' ) ? _wp_http_get_object() : null;
		if ( $http && $http->block_request( 'https://api.wordpress.org/plugins/info/1.2/' ) ) {
			mats_installer_add( $result, 'error', sprintf( 'No se ha podido descargar %s: este servidor tiene bloqueadas las conexiones con wordpress.org.', $spec['name'] ) );
			return '';
		}
		$detail = is_wp_error( $api ) ? wp_strip_all_tags( (string) $api->get_error_data() ) : '';
		mats_installer_add(
			$result,
			'error',
			sprintf( 'No se ha podido conectar con wordpress.org para descargar %s. Puede ser un corte momentáneo o que el servidor no tenga salida a internet.', $spec['name'] ) . ( $detail ? ' (Detalle técnico: ' . $detail . ')' : '' )
		);
		return '';
	}

	ob_start();
	$creds = request_filesystem_credentials( mats_installer_url(), '', false, false, null );
	ob_end_clean();
	if ( false === $creds || ! WP_Filesystem( $creds ) ) {
		mats_installer_add( $result, 'error', sprintf( 'El servidor no deja que WordPress escriba en la carpeta de plugins (pide datos de FTP), así que no se ha podido instalar %s. En Hostinger no debería pasar: revisa los permisos de la carpeta wp-content o instálalo a mano.', $spec['name'] ) );
		return '';
	}

	$skin     = new WP_Ajax_Upgrader_Skin();
	$upgrader = new Plugin_Upgrader( $skin );
	ob_start();
	$installed = $upgrader->install( $api->download_link );
	ob_end_clean();

	$errors = $skin->get_errors();
	if ( is_wp_error( $installed ) || ! $installed || ( is_wp_error( $errors ) && $errors->has_errors() ) ) {
		$error = is_wp_error( $installed ) ? $installed : ( is_wp_error( $errors ) && $errors->has_errors() ? $errors : null );
		/* the folder already exists: the plugin is there after all */
		if ( $error && 'folder_exists' === $error->get_error_code() ) {
			wp_clean_plugins_cache();
			$state = mats_installer_plugin_state( $key );
			if ( $state['file'] ) {
				return $state['file'];
			}
		}
		mats_installer_add(
			$result,
			'error',
			sprintf( 'La descarga de %s ha empezado pero no se ha podido instalar.', $spec['name'] ) . ( $error ? ' (Detalle técnico: ' . wp_strip_all_tags( $error->get_error_message() ) . ')' : '' )
		);
		return '';
	}

	wp_clean_plugins_cache();
	$file = (string) $upgrader->plugin_info();
	if ( ! $file ) {
		$file = mats_installer_plugin_state( $key )['file'];
	}
	if ( ! $file ) {
		mats_installer_add( $result, 'error', sprintf( '%s se ha descargado pero WordPress no lo encuentra en la lista de plugins.', $spec['name'] ), admin_url( 'plugins.php' ), 'Ir a Plugins' );
	}
	return $file;
}

/** Makes one plugin present and active. True when it ends up active. */
function mats_installer_ensure_plugin( array &$result, string $key, bool $install ): bool {
	$spec  = mats_installer_plugin_specs()[ $key ];
	$state = mats_installer_plugin_state( $key );

	if ( 'active' === $state['state'] ) {
		mats_installer_add( $result, 'ok', sprintf( '%s ya estaba instalado y activo.', $spec['name'] ) );
		$result['data'][ $key ] = 'already-active';
		return true;
	}

	if ( 'installed' === $state['state'] ) {
		if ( mats_installer_activate_plugin( $result, $key, $state['file'] ) ) {
			mats_installer_add( $result, 'ok', sprintf( '%1$s ya estaba instalado pero desactivado: ahora está activado. (Sirve para: %2$s.)', $spec['name'], $spec['why'] ) );
			$result['data'][ $key ] = 'activated';
			return true;
		}
		$result['data'][ $key ] = 'activation-failed';
		return false;
	}

	if ( ! $install ) {
		mats_installer_add( $result, 'error', sprintf( '%s no está instalado.', $spec['name'] ) );
		mats_installer_manual_plugin_msg( $result, $key );
		$result['data'][ $key ] = 'missing';
		return false;
	}

	$file = mats_installer_install_plugin( $result, $key );
	if ( ! $file ) {
		mats_installer_manual_plugin_msg( $result, $key );
		$result['data'][ $key ] = 'install-failed';
		return false;
	}
	if ( mats_installer_activate_plugin( $result, $key, $file ) ) {
		mats_installer_add( $result, 'ok', sprintf( '%1$s se ha descargado de wordpress.org, instalado y activado. (Sirve para: %2$s.)', $spec['name'], $spec['why'] ) );
		$result['data'][ $key ] = 'installed';
		return true;
	}
	$result['data'][ $key ] = 'activation-failed';
	return false;
}

/** Step runner. $opts['only'] = 'smtp' installs just WP Mail SMTP. */
function mats_installer_step_plugins( array $opts ): array {
	$result  = mats_installer_result( 'plugins' );
	$install = ! empty( $opts['install'] );

	if ( 'smtp' === ( $opts['only'] ?? '' ) ) {
		$ok = mats_installer_ensure_plugin( $result, 'smtp', $install );
		if ( $ok ) {
			mats_installer_add( $result, 'info', 'Ahora configura WP Mail SMTP con la cuenta de correo desde la que saldrán los avisos (su asistente te guía paso a paso).', admin_url( 'admin.php?page=wp-mail-smtp' ), 'Configurar WP Mail SMTP' );
		}
		return $result;
	}

	foreach ( array( 'polylang', 'wpforms' ) as $key ) {
		mats_installer_ensure_plugin( $result, $key, $install );
	}
	if ( ! empty( $opts['smtp'] ) ) {
		mats_installer_ensure_plugin( $result, 'smtp', $install );
	} elseif ( 'active' !== mats_installer_plugin_state( 'smtp' )['state'] ) {
		mats_installer_add( $result, 'info', 'Recomendado (opcional): WP Mail SMTP hace que los correos del formulario lleguen bien y no acaben en spam. Puedes instalarlo con el botón «Instalar WP Mail SMTP» de este paso.' );
	}
	if ( ! empty( $result['reload'] ) ) {
		mats_installer_add( $result, 'info', 'Los plugins recién activados empiezan a funcionar en el siguiente paso.' );
	}
	return $result;
}

function mats_installer_status_plugins(): array {
	$checks = array();
	$labels = array(
		'active'    => 'instalado y activo',
		'installed' => 'instalado pero desactivado',
		'missing'   => 'no está instalado',
	);
	foreach ( mats_installer_plugin_specs() as $key => $spec ) {
		$state = mats_installer_plugin_state( $key )['state'];
		$ok    = 'active' === $state;
		$text  = $spec['name'] . ': ' . $labels[ $state ] . ( $spec['required'] ? '' : ' (recomendado, opcional)' );
		$url   = '';
		$label = '';
		if ( ! $spec['required'] && $ok ) {
			$url   = admin_url( 'admin.php?page=wp-mail-smtp' );
			$label = 'Configurar';
		}
		$checks[] = mats_installer_check( $spec['required'] ? $ok : ( $ok ? true : null ), $text, $url, $label );
	}
	return mats_installer_status_from( $checks );
}
