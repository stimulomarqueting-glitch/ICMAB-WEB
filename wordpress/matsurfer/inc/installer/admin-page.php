<?php
/**
 * Setup wizard screen (MatSurfer → Puesta en marcha).
 *
 * One form for the whole screen: every button submits it with its own
 * name="step" value (plugins, smtp, languages, content, forms, review, all),
 * so the "Sobrescribir" checkbox applies to "Hacerlo todo" and to step 3.
 * Without JavaScript the form posts to admin-post.php; with JavaScript
 * (assets/js/installer.js) each step runs through admin-ajax.php, one
 * request per step, and the cards update in place.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

/** Pill text per state. */
function mats_installer_pill_html( array $status ): string {
	$icons = array(
		'done' => '✓',
		'todo' => '✗',
		'warn' => '!',
		'info' => '•',
	);
	$state = isset( $icons[ $status['state'] ] ) ? $status['state'] : 'todo';
	return sprintf(
		'<span class="mats-step__pill is-%1$s"><span aria-hidden="true">%2$s</span> %3$s</span>',
		esc_attr( $state ),
		esc_html( $icons[ $state ] ),
		esc_html( (string) $status['label'] )
	);
}

/** The ✓ / ✗ list of a step's current state. */
function mats_installer_checks_html( array $status ): string {
	if ( empty( $status['checks'] ) ) {
		return '';
	}
	$out = '<ul class="mats-step__checks">';
	foreach ( $status['checks'] as $check ) {
		$ok    = $check['ok'] ?? null;
		$class = true === $ok ? 'is-ok' : ( false === $ok ? 'is-bad' : 'is-info' );
		$icon  = true === $ok ? '✓' : ( false === $ok ? '✗' : '•' );
		$sr    = true === $ok ? 'Hecho: ' : ( false === $ok ? 'Pendiente: ' : '' );
		$out  .= '<li class="' . esc_attr( $class ) . '"><span class="mats-step__icon" aria-hidden="true">' . esc_html( $icon ) . '</span><span class="mats-step__text">';
		$out  .= ( $sr ? '<span class="screen-reader-text">' . esc_html( $sr ) . '</span>' : '' ) . esc_html( (string) $check['text'] );
		if ( ! empty( $check['url'] ) ) {
			$out .= ' <a href="' . esc_url( (string) $check['url'] ) . '">' . esc_html( (string) ( $check['label'] ?? 'Abrir' ) ) . '</a>';
		}
		$out .= '</span></li>';
	}
	return $out . '</ul>';
}

/** Messages of a step result. */
function mats_installer_messages_html( array $messages, int $time = 0, bool $reload = false ): string {
	if ( ! $messages ) {
		return '';
	}
	$icons = array(
		'ok'    => '✓',
		'info'  => 'i',
		'warn'  => '!',
		'error' => '✗',
	);
	$words = array(
		'ok'    => 'Hecho',
		'info'  => 'Información',
		'warn'  => 'Atención',
		'error' => 'Error',
	);
	$out = '<div class="mats-step__report">';
	if ( $time ) {
		/* digits only: reads the same whatever the language of the WordPress admin */
		$out .= '<p class="mats-step__when">Resultado de la última vez (' . esc_html( wp_date( 'j/m/Y \a \l\a\s H:i', $time ) ) . '):</p>';
	}
	$out .= '<ul class="mats-step__messages">';
	foreach ( $messages as $msg ) {
		$type = isset( $icons[ $msg['type'] ?? '' ] ) ? $msg['type'] : 'info';
		$out .= '<li class="is-' . esc_attr( $type ) . '"><span class="mats-step__icon" aria-hidden="true">' . esc_html( $icons[ $type ] ) . '</span><span class="mats-step__text"><span class="screen-reader-text">' . esc_html( $words[ $type ] ) . ': </span>' . esc_html( (string) ( $msg['text'] ?? '' ) );
		if ( ! empty( $msg['url'] ) ) {
			$out .= ' <a href="' . esc_url( (string) $msg['url'] ) . '">' . esc_html( (string) ( $msg['label'] ?? 'Abrir' ) ) . '</a>';
		}
		$out .= '</span></li>';
	}
	$out .= '</ul>';
	if ( $reload ) {
		$out .= '<p class="mats-step__when">Recarga la página para seguir con el siguiente paso.</p>';
	}
	return $out . '</div>';
}

/** "3 de 4 pasos hechos" + bar. */
function mats_installer_summary_html(): string {
	$done  = 0;
	$total = count( mats_installer_auto_steps() );
	foreach ( mats_installer_auto_steps() as $step ) {
		if ( 'done' === mats_installer_status( $step )['state'] ) {
			++$done;
		}
	}
	$state = mats_installer_state();
	if ( $done === $total ) {
		$text = $state['done'] ? 'La web está lista y la puesta en marcha está terminada.' : 'La web está lista. Revisa el paso 5 y da la puesta en marcha por terminada.';
	} elseif ( 0 === $done ) {
		$text = 'Nada hecho todavía. Pulsa «Hacerlo todo» y espera unos minutos.';
	} else {
		$text = sprintf( 'Van %1$d de %2$d pasos hechos. Pulsa «Hacerlo todo» para completar el resto: lo que ya está hecho no se repite ni se duplica.', $done, $total );
	}
	return sprintf(
		'<div class="mats-setup__progress" data-done="%1$d" data-total="%2$d"><div class="mats-setup__bar" role="progressbar" aria-valuemin="0" aria-valuemax="%2$d" aria-valuenow="%1$d" aria-label="Pasos hechos"><span style="width:%3$d%%"></span></div><p><strong>%1$d / %2$d</strong> — %4$s</p></div>',
		$done,
		$total,
		(int) round( 100 * $done / max( 1, $total ) ),
		esc_html( $text )
	);
}

/*
 * "Sobrescribir los textos y las fotos": what it changes and what it keeps,
 * said the same way in the checkbox help, the JS dialog and the no-JS box.
 * It rebuilds the sections of every structural page (texts, buttons and
 * photos) from the original copy; the Google title/description somebody
 * wrote are kept (they are not saved in «Revisiones», so they could not be
 * brought back).
 */

/** Label of the checkbox. */
function mats_installer_overwrite_label(): string {
	return 'Sobrescribir los textos y las fotos con los originales';
}

/** Can a page's earlier version be brought back from «Revisiones» on this server? */
function mats_installer_revisions_on(): bool {
	return function_exists( 'wp_revisions_enabled' ) && wp_revisions_enabled(
		new WP_Post(
			(object) array(
				'ID'        => 0,
				'post_type' => 'page',
			)
		)
	);
}

/** What is replaced (and how to get it back). */
function mats_installer_overwrite_changes(): string {
	return 'Los textos y las fotos de las secciones de todas las páginas, en inglés y en español, vuelven a ser los de la web original: se sustituye lo que hayáis cambiado en ellos.'
		. ( mats_installer_revisions_on() ? ' Si hace falta, la versión anterior de cada página se puede recuperar desde «Revisiones», en la propia página.' : '' );
}

/** What is never touched. */
function mats_installer_overwrite_keeps(): string {
	return 'No se tocan: el título y la descripción para Google que hayáis escrito en cada página, el equipo, las aplicaciones, las publicaciones, las valoraciones, las noticias, los textos legales ni los formularios.';
}

/** Texts of the confirmation (JS dialog and no-JS box). */
function mats_installer_overwrite_question(): string {
	return '¿Seguro que quieres sobrescribir los textos y las fotos?';
}

function mats_installer_overwrite_warning(): string {
	return mats_installer_overwrite_changes() . ' ' . mats_installer_overwrite_keeps();
}

/**
 * Without JavaScript there is no confirmation dialog: pressing step 3 or
 * "Hacerlo todo" with "Sobrescribir los textos y las fotos" ticked comes back here
 * (?mats_confirm=content|all) and nothing runs until this box is accepted.
 */
function mats_installer_confirm_html( string $step ): string {
	if ( ! mats_installer_step_can_overwrite( $step ) ) {
		return '';
	}
	ob_start();
	?>
	<div class="mats-setup__confirm" id="mats-confirm" role="region" aria-labelledby="mats-confirm-title" tabindex="-1">
		<h2 id="mats-confirm-title" class="mats-setup__confirm-title"><?php echo esc_html( mats_installer_overwrite_question() ); ?></h2>
		<p><?php echo esc_html( mats_installer_overwrite_warning() ); ?></p>
		<p><?php echo esc_html( 'all' === $step ? 'Todavía no se ha hecho nada. Si confirmas, se harán los pasos 1 a 4 y se sobrescribirán los textos y las fotos.' : 'Todavía no se ha hecho nada. Si confirmas, se repetirá el paso 3 y se sobrescribirán los textos y las fotos.' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mats-setup__confirm-actions">
			<input type="hidden" name="action" value="mats_installer_run">
			<input type="hidden" name="step" value="<?php echo esc_attr( $step ); ?>">
			<input type="hidden" name="overwrite" value="1">
			<input type="hidden" name="confirm" value="1">
			<?php wp_nonce_field( MATS_SETUP_NONCE ); ?>
			<button type="submit" class="button button-primary"><?php echo esc_html( 'all' === $step ? 'Sí, sobrescribir y hacerlo todo' : 'Sí, sobrescribir los textos y las fotos' ); ?></button>
			<a class="button" href="<?php echo esc_url( mats_installer_url() . '#mats-step-content' ); ?>">No, volver sin cambiar nada</a>
		</form>
	</div>
	<?php
	return (string) ob_get_clean();
}

/** Card of one step. */
function mats_installer_step_html( string $key, int $number ): string {
	$step   = mats_installer_steps()[ $key ];
	$status = mats_installer_status( $key );
	$last   = mats_installer_state()['steps'][ $key ] ?? array();

	ob_start();
	?>
	<li class="mats-step" id="mats-step-<?php echo esc_attr( $key ); ?>" data-step="<?php echo esc_attr( $key ); ?>" data-state="<?php echo esc_attr( $status['state'] ); ?>">
		<div class="mats-step__head">
			<span class="mats-step__num" aria-hidden="true"><?php echo (int) $number; ?></span>
			<h2 class="mats-step__title"><span class="screen-reader-text">Paso <?php echo (int) $number; ?>: </span><?php echo esc_html( $step['title'] ); ?></h2>
			<span class="mats-step__status"><?php echo mats_installer_pill_html( $status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?></span>
		</div>
		<p class="mats-step__intro"><?php echo esc_html( $step['intro'] ); ?></p>
		<div class="mats-step__state"><?php echo mats_installer_checks_html( $status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?></div>
		<div class="mats-step__actions">
			<?php if ( 'content' === $key ) : ?>
				<p class="mats-step__option">
					<label>
						<input type="checkbox" name="overwrite" value="1" class="mats-setup__overwrite">
						<?php echo esc_html( mats_installer_overwrite_label() ); ?>
					</label>
					<span class="mats-step__help"><?php echo esc_html( 'Solo si quieres deshacer cambios. ' . mats_installer_overwrite_warning() ); ?></span>
				</p>
			<?php endif; ?>
			<button type="submit" name="step" value="<?php echo esc_attr( $key ); ?>" class="button <?php echo 'review' === $key ? 'button-primary' : ''; ?> mats-step__run">
				<?php echo esc_html( 'done' === $status['state'] && 'review' !== $key ? 'Repetir este paso' : $step['button'] ); ?>
			</button>
			<?php if ( 'plugins' === $key && 'active' !== mats_installer_plugin_state( 'smtp' )['state'] ) : ?>
				<button type="submit" name="step" value="smtp" class="button button-link mats-step__run mats-step__secondary">Instalar WP Mail SMTP (recomendado, opcional)</button>
			<?php endif; ?>
			<span class="mats-step__spinner spinner" aria-hidden="true"></span>
		</div>
		<div class="mats-step__result" aria-live="polite"><?php echo ! empty( $last['messages'] ) ? mats_installer_messages_html( (array) $last['messages'], (int) ( $last['time'] ?? 0 ) ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?></div>
	</li>
	<?php
	return (string) ob_get_clean();
}

/** The screen. */
function mats_installer_render_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html( 'No tienes permiso para ver esta pantalla.' ) );
	}
	$number = 0;
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only chooses which confirmation to show; its form has its own nonce.
	$confirm = isset( $_GET['mats_confirm'] ) && is_scalar( $_GET['mats_confirm'] ) ? sanitize_key( wp_unslash( $_GET['mats_confirm'] ) ) : '';
	?>
	<div class="wrap mats-setup">
		<h1 class="mats-setup__title">
			<?php echo mats_brand_mark( 30, 'mats-setup__mark' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
			Puesta en marcha de la web
		</h1>
		<p class="mats-setup__lead">Este asistente deja la web de MatSurfer lista en unos minutos: instala los plugins, configura el inglés y el español, crea las páginas con los textos y fotos originales y prepara el formulario de contacto. Puedes repetirlo cuando quieras: nunca duplica páginas ni cambia lo que ya hayáis editado.</p>
		<?php echo mats_installer_confirm_html( $confirm ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mats-setup__form" id="mats-setup-form">
			<input type="hidden" name="action" value="mats_installer_run">
			<?php wp_nonce_field( MATS_SETUP_NONCE ); ?>

			<div class="mats-setup__hero">
				<div class="mats-setup__summary" aria-live="polite"><?php echo mats_installer_summary_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?></div>
				<div class="mats-setup__all">
					<button type="submit" name="step" value="all" class="button button-primary button-hero mats-step__run mats-setup__all-button">Hacerlo todo</button>
					<span class="mats-setup__hint">Hace los pasos 1 a 4 seguidos. Tarda uno o dos minutos: no cierres la pestaña.</span>
				</div>
			</div>
			<div class="mats-setup__live" aria-live="assertive"></div>

			<ol class="mats-setup__steps">
				<?php
				foreach ( array_keys( mats_installer_steps() ) as $key ) {
					echo mats_installer_step_html( $key, ++$number ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
				}
				?>
			</ol>
		</form>
		<noscript><p class="mats-setup__noscript">Sin JavaScript los pasos también funcionan: cada botón recarga la página y muestra el resultado en su paso.</p></noscript>
	</div>
	<?php
}
