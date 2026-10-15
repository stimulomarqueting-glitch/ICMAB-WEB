<?php
/**
 * Setup step 5 — final review: what cannot be automated, with links.
 * Running the step only records that the setup is finished (the reminder
 * notice disappears).
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

/** Edit link of a role's page in a language ('' when missing). */
function mats_installer_edit_link( string $role, string $lang ): string {
	list( $id ) = mats_installer_find_page( $role, $lang );
	return $id ? (string) get_edit_post_link( $id, 'raw' ) : '';
}

/** The checklist items: [ ok (true|false|null), text, url, label ]. */
function mats_installer_review_items(): array {
	$items    = array();
	$settings = mats_installer_known_page( 'settings' );
	$guide    = mats_installer_known_page( 'guide' );

	/* contact email */
	$saved    = get_option( 'mats_settings', array() );
	$explicit = is_array( $saved ) && ! empty( $saved['global']['contact_email'] );
	$items[]  = mats_installer_check(
		$explicit ? true : null,
		sprintf( 'Email de contacto: los mensajes del formulario llegan a %s. Si debe ser otra dirección, cámbiala en «Ajustes de la web», en el menú de la izquierda (apartado «Email de contacto»).', mats_contact_email() ),
		$settings,
		'Abrir Ajustes de la web'
	);

	/* reliable email */
	$smtp    = 'active' === mats_installer_plugin_state( 'smtp' )['state'];
	$items[] = mats_installer_check(
		$smtp ? true : null,
		$smtp
			? 'WP Mail SMTP está activo. Comprueba en su pantalla que está conectado a una cuenta de correo y envía un email de prueba.'
			: 'Recomendado: instala WP Mail SMTP (botón del paso 1) y conéctalo a una cuenta de correo, para que los avisos del formulario no acaben en spam. Después haz una prueba enviando el formulario de la página Contacto.',
		$smtp ? admin_url( 'admin.php?page=wp-mail-smtp' ) : '',
		'Configurar WP Mail SMTP'
	);

	/* search engines */
	$public  = '1' === (string) get_option( 'blog_public' );
	$box     = mats_installer_wp_label( 'Disuadir a los motores de búsqueda de indexar este sitio', 'Discourage search engines from indexing this site' );
	$items[] = mats_installer_check(
		null,
		$public
			? sprintf( 'Buscadores: ahora la web es visible para Google. Si todavía es una web de pruebas (staging), marca «%1$s» en %2$s, y desmárcalo el día que se publique la web definitiva.', $box, mats_installer_wp_label( 'Ajustes → Lectura', 'Settings → Reading', true ) )
			: sprintf( 'Buscadores: está marcado «%s». Bien mientras sea una web de pruebas; desmárcalo el día que se publique la web definitiva o Google no la mostrará.', $box ),
		admin_url( 'options-reading.php' ),
		'Ajustes → Lectura'
	);

	/* editors */
	$editors = count(
		get_users(
			array(
				'role'   => 'editor',
				'fields' => 'ID',
				'number' => 50,
			)
		)
	);
	$items[] = mats_installer_check(
		$editors ? true : null,
		( $editors ? sprintf( 'Usuarios: hay %d con el rol «Editor». ', $editors ) : '' ) . 'Crea un usuario con el rol «Editor» para cada persona del ICMAB que vaya a publicar noticias o cambiar textos: puede hacerlo todo con el contenido, pero no puede tocar plugins, ajustes ni el diseño. No compartáis el usuario administrador.',
		admin_url( 'user-new.php' ),
		'Añadir usuario'
	);

	/* legal texts */
	$legal = array();
	foreach ( array( 'privacy', 'legal' ) as $role ) {
		list( $id ) = mats_installer_find_page( $role, 'en' );
		if ( ! $id || 'publish' !== get_post_status( $id ) ) {
			$legal[] = mats_page_roles()[ $role ];
		}
	}
	$items[] = mats_installer_check(
		$legal ? null : true,
		$legal
			? 'Textos legales: «' . implode( '» y «', $legal ) . '» siguen en borrador. Cuando el ICMAB facilite los textos, pégalos (en inglés y en español) y publica las páginas.'
			: 'Textos legales: la Política de privacidad y el Aviso legal están publicados.',
		admin_url( 'edit.php?post_type=page&post_status=draft' ),
		'Ver los borradores'
	);

	/* look at the site */
	$items[] = mats_installer_check(
		null,
		'Mira la web en los dos idiomas (inglés en la raíz, español en /es/) y comprueba fotos y textos.',
		home_url( '/' ),
		'Ver la web'
	);

	/* guide */
	$items[] = mats_installer_check(
		null,
		'Guía de uso: explica cómo publicar noticias, cambiar textos y fotos y gestionar equipo, aplicaciones, publicaciones y valoraciones. Compártela con el equipo del ICMAB.' . ( $guide ? '' : ' (La encontrarás en el menú de la izquierda, «Guía de uso».)' ),
		$guide,
		'Abrir la guía'
	);
	return $items;
}

function mats_installer_step_review( array $opts ): array {
	$result         = mats_installer_result( 'review' );
	$state          = mats_installer_state();
	$state['done']  = time();
	$state['notice'] = 'hide';
	mats_installer_save_state( $state );
	mats_installer_add( $result, 'ok', 'Puesta en marcha terminada. Puedes volver a esta pantalla cuando quieras desde el menú «MatSurfer» de la izquierda: repetir los pasos nunca duplica nada.' );
	if ( ! mats_installer_all_done() ) {
		mats_installer_add( $result, 'warn', 'Ojo: todavía hay pasos marcados como pendientes. La web funciona, pero revísalos cuando puedas.' );
	}
	return $result;
}

/**
 * "Terminada" only while the automatic steps are still in place: when one
 * of them stops being done later (e.g. Polylang deactivated), the card says
 * so instead of a ✓ next to a summary of "1 / 4".
 */
function mats_installer_status_review(): array {
	$state = mats_installer_state();
	$items = mats_installer_review_items();
	if ( ! $state['done'] ) {
		return array(
			'state'  => 'info',
			'label'  => 'Por revisar',
			'checks' => $items,
		);
	}
	$all = mats_installer_all_done();
	return array(
		'state'  => $all ? 'done' : 'warn',
		'label'  => $all ? 'Terminada' : 'Hay pasos pendientes',
		'checks' => $items,
	);
}
