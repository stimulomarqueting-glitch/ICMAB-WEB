<?php
/**
 * Setup step 4 — the contact forms (one WPForms form per language), built
 * by the forms module: mats_wpforms_create_forms() in inc/modules/wpforms.php.
 * Existing forms are kept (the module never overwrites them here).
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

function mats_installer_wpforms_active(): bool {
	return function_exists( 'mats_wpforms_active' ) ? (bool) mats_wpforms_active() : function_exists( 'wpforms' );
}

/** Explains how to get WPForms when it is not active. */
function mats_installer_wpforms_howto( array &$result ): void {
	mats_installer_add(
		$result,
		'info',
		sprintf(
			'Primero hay que tener WPForms Lite instalado y activo: pulsa el botón del paso 1. Si no se puede instalar de forma automática, ve a %1$s, busca «WPForms», pulsa «%2$s» y después «%3$s». Luego vuelve aquí y pulsa «Crear los formularios». Mientras tanto, la página Contacto muestra el email de contacto en lugar del formulario.',
			mats_installer_wp_label( 'Plugins → Añadir plugin', 'Plugins → Add Plugin', true ),
			mats_installer_wp_label( 'Instalar ahora', 'Install Now' ),
			mats_installer_wp_label( 'Activar', 'Activate' )
		),
		admin_url( 'plugin-install.php?tab=search&type=term&s=wpforms' ),
		'Buscar el plugin'
	);
}

function mats_installer_step_forms( array $opts ): array {
	$result = mats_installer_result( 'forms' );

	if ( mats_installer_needs_reload() ) {
		$result['reload']  = true;
		$result['skipped'] = true;
		mats_installer_add( $result, 'info', 'Este paso se hará en cuanto se recargue la página (se acaba de activar un plugin).' );
		return $result;
	}
	if ( ! function_exists( 'mats_wpforms_create_forms' ) ) {
		mats_installer_add( $result, 'error', 'Esta versión del tema no incluye la creación automática de formularios. Crea el formulario a mano en WPForms o actualiza el tema.' );
		return $result;
	}
	if ( ! mats_installer_wpforms_active() ) {
		mats_installer_add( $result, 'error', 'WPForms no está activo, así que todavía no se pueden crear los formularios de contacto.' );
		mats_installer_wpforms_howto( $result );
		return $result;
	}

	$report = (array) mats_wpforms_create_forms( false );
	$names  = array(
		'en' => 'inglés',
		'es' => 'español',
	);
	foreach ( (array) ( $report['forms'] ?? array() ) as $lang => $form ) {
		$status = (string) ( $form['status'] ?? '' );
		$text   = (string) ( $form['message'] ?? sprintf( 'Formulario en %s: %s', $names[ $lang ] ?? $lang, $status ) );
		$url    = ! empty( $form['id'] ) ? admin_url( 'admin.php?page=wpforms-builder&view=fields&form_id=' . (int) $form['id'] ) : '';
		mats_installer_add( $result, 'error' === $status ? 'error' : 'ok', $text, $url, $url ? 'Abrir en WPForms' : '' );
	}
	if ( ! empty( $report['notice'] ) ) {
		mats_installer_add( $result, 'info', (string) $report['notice'] );
	}
	if ( empty( $report['ok'] ) && $result['ok'] ) {
		mats_installer_add( $result, 'error', 'No se han podido crear los formularios. Comprueba que WPForms está activo y vuelve a intentarlo.' );
	}
	if ( $result['ok'] ) {
		mats_installer_add( $result, 'info', sprintf( 'Los mensajes llegarán a %s y se guardan también en el menú «Contactos». Si debe ser otra dirección, cámbiala en «Ajustes de la web», en el menú de la izquierda (apartado «Email de contacto»).', mats_contact_email() ) );
	}
	$result['data'] = $report;
	return $result;
}

function mats_installer_status_forms(): array {
	$checks = array();
	$active = mats_installer_wpforms_active();
	$checks[] = mats_installer_check( $active, $active ? 'WPForms está activo.' : 'WPForms no está activo (paso 1).' );
	if ( function_exists( 'mats_wpforms_status' ) ) {
		$status = mats_wpforms_status();
		foreach ( array( 'en' => 'inglés', 'es' => 'español' ) as $lang => $name ) {
			$id       = (int) ( $status['forms'][ $lang ] ?? 0 );
			$checks[] = mats_installer_check(
				$id > 0 && $active,
				$id ? sprintf( 'Formulario en %1$s creado (nº %2$d).', $name, $id ) : sprintf( 'Falta el formulario en %s.', $name ),
				$id && $active ? admin_url( 'admin.php?page=wpforms-builder&view=fields&form_id=' . $id ) : '',
				'Abrir en WPForms'
			);
		}
	} else {
		$checks[] = mats_installer_check( false, 'Esta versión del tema no incluye la creación automática de formularios.' );
	}
	return mats_installer_status_from( $checks );
}
