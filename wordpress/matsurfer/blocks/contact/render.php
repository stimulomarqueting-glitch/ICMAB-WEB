<?php
/**
 * Contact section (src/pages/[...lang]/contact.astro + LeadForm.astro):
 * kicker, rise-in word title, lead, "what happens next" timeline, direct
 * email, and the form card (.lf__card with the vapour line) holding the
 * WPForms form of the page's language (inc/modules/wpforms.php).
 *
 * When WPForms is not active or the form is missing, visitors see a short
 * message with the contact email (never an empty box); the editor preview
 * explains what is missing and where to fix it.
 *
 * @package matsurfer
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$kicker       = (string) mats_get( $attributes, 'kicker' );
$title        = (string) mats_get( $attributes, 'title' );
$lead         = (string) mats_get( $attributes, 'lead' );
$aside_title  = (string) mats_get( $attributes, 'asideTitle' );
$steps        = array_filter( (array) mats_get( $attributes, 'steps', array() ), 'mats_has' ); // keeps the attribute index for in-place editing
$direct_title = (string) mats_get( $attributes, 'directTitle' );
$direct_text  = (string) mats_get( $attributes, 'directText' );
$preview      = mats_is_preview();

if ( ! mats_has( $title ) && ! mats_has( $lead ) && ! mats_has( $aside_title ) ) {
	echo mats_preview_empty( 'Contacto con formulario' ); // phpcs:ignore WordPress.Security.EscapeOutput
	return;
}

/* one heading id per section, in case the section appears twice */
$GLOBALS['mats_contact_count'] = (int) ( $GLOBALS['mats_contact_count'] ?? 0 ) + 1;
$heading_id                    = 1 === $GLOBALS['mats_contact_count'] ? 'contact-title' : 'contact-title-' . $GLOBALS['mats_contact_count'];

$lang  = mats_lang();
$email = mats_contact_email();
$form  = function_exists( 'mats_wpforms_render' )
	? mats_wpforms_render( $lang )
	: array(
		'html'    => '',
		'form_id' => 0,
		'reason'  => 'inactive',
	);
if ( '' !== $form['html'] && function_exists( 'mats_wpforms_enqueue_assets' ) ) {
	mats_wpforms_enqueue_assets(); // no-op when already enqueued upfront
}

/* editor notes: only administrators can open WPForms, the Plugins screen and the Dashboard notice */
$can_admin  = $preview && current_user_can( 'manage_options' );
$lang_name  = 'es' === $lang ? 'español' : 'inglés';
$leads_path = $preview && defined( 'MATS_LEADS_PAGE' ) && defined( 'MATS_LEAD_CAP' ) && current_user_can( MATS_LEAD_CAP ) ? 'admin.php?page=' . MATS_LEADS_PAGE : '';

$config = '' !== $form['html'] && function_exists( 'mats_wpforms_client_config' ) ? mats_wpforms_client_config( $lang, (int) $form['form_id'] ) : array();
$opt    = (string) ( function_exists( 'mats_wpforms_copy' ) ? ( mats_wpforms_copy( $lang )['optionalTag'] ?? '' ) : '' );
if ( '' === $opt ) {
	$opt = mats_t( 'optional', $lang );
}
?>
<section class="sec contact-sec" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>">
	<div class="container contact">
		<div>
			<?php if ( mats_has( $kicker ) ) : ?>
				<p class="eyebrow rise-in" style="--i: 0"<?php echo mats_edit( 'kicker' ); ?>><?php echo mats_text( $kicker ); ?></p>
			<?php endif; ?>
			<h1 class="contact__title" id="<?php echo esc_attr( $heading_id ); ?>"<?php echo mats_edit( 'title' ); ?>>
				<?php echo mats_words( $title, '<span class="contact__word"><span class="rise-in" style="--i: %2$d">%1$s</span></span> ', 1 ); // phpcs:ignore WordPress.Security.EscapeOutput -- words escaped inside ?>
			</h1>
			<?php if ( mats_has( $lead ) ) : ?>
				<p class="lead rise-in" style="--i: 5"<?php echo mats_edit( 'lead' ); ?>><?php echo mats_text( $lead ); ?></p>
			<?php endif; ?>

			<div class="contact__aside reveal">
				<?php if ( mats_has( $aside_title ) ) : ?>
					<h2 class="contact__aside-title"<?php echo mats_edit( 'asideTitle' ); ?>><?php echo mats_text( $aside_title ); ?></h2>
				<?php endif; ?>
				<?php if ( $steps ) : ?>
					<ol class="contact__steps" role="list">
						<?php $n = 0; ?>
						<?php foreach ( $steps as $i => $step ) : ?>
							<li>
								<span class="contact__sn" aria-hidden="true"><?php echo esc_html( mats_pad( ++$n ) ); ?></span>
								<span<?php echo mats_edit( 'steps.' . $i ); ?>><?php echo mats_text( $step ); ?></span>
							</li>
						<?php endforeach; ?>
					</ol>
				<?php endif; ?>
				<?php if ( mats_has( $direct_title ) ) : ?>
					<h2 class="contact__aside-title contact__direct">
						<span class="contact__mail" aria-hidden="true"><?php echo mats_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput -- theme SVG file ?></span>
						<?php if ( $preview ) : ?>
							<span<?php echo mats_edit( 'directTitle' ); ?>><?php echo mats_text( $direct_title ); ?></span>
						<?php else : ?>
							<?php echo mats_text( $direct_title ); ?>
						<?php endif; ?>
					</h2>
				<?php endif; ?>
				<p class="contact__direct-text">
					<?php if ( $preview ) : ?>
						<span<?php echo mats_edit( 'directText' ); ?>><?php echo mats_text( $direct_text ); ?></span>
					<?php else : ?>
						<?php echo mats_text( $direct_text ); ?>
					<?php endif; ?>
					<a href="<?php echo esc_url( 'mailto:' . $email ); ?>"><?php echo esc_html( $email ); ?></a>
				</p>
			</div>
		</div>

		<?php if ( '' !== $form['html'] ) : ?>
			<div class="panel lf__card reveal" data-lf-config="<?php echo esc_attr( (string) wp_json_encode( $config ) ); ?>" style="<?php echo esc_attr( '--lf-opt: "' . str_replace( array( '"', '\\' ), '', $opt ) . '"' ); ?>">
				<?php
				if ( $preview && $form['form_id'] ) {
					if ( $can_admin ) {
						echo mats_preview_note( // phpcs:ignore WordPress.Security.EscapeOutput
							sprintf( 'Este es el formulario de WPForms nº %d (%s). Sus campos, el botón, el mensaje de gracias y el email que recibe los contactos se cambian en WPForms.', (int) $form['form_id'], $lang_name ),
							'admin.php?page=wpforms-builder&view=fields&form_id=' . (int) $form['form_id'],
							'Editar el formulario'
						);
					} else {
						/* Editors cannot open WPForms: no admin links, just who to ask */
						echo mats_preview_note( // phpcs:ignore WordPress.Security.EscapeOutput
							sprintf( 'Este es el formulario de contacto en %s. Sus campos, el botón, el mensaje de gracias y el email que recibe los avisos solo los puede cambiar la persona que administra la web: si hay que cambiar algo, pídeselo. Los mensajes que llegan se guardan en «Contactos».', $lang_name ),
							$leads_path,
							$leads_path ? 'Ver los contactos' : ''
						);
					}
				}
				echo $form['html']; // phpcs:ignore WordPress.Security.EscapeOutput -- WPForms output
				?>
			</div>
		<?php else : ?>
			<div class="panel lf__card lf__card--missing reveal">
				<?php
				if ( $preview ) {
					$reasons = $can_admin
						? array(
							'inactive' => array( 'El plugin WPForms no está instalado o activado, así que los visitantes ven el aviso de abajo con el email de contacto.', 'plugins.php', 'Ir a Plugins' ),
							'missing'  => array( sprintf( 'Todavía no existe el formulario de contacto en %s. Ve al Escritorio y pulsa «Crear los formularios de contacto». Mientras tanto, los visitantes ven el aviso de abajo.', $lang_name ), 'index.php', 'Ir al Escritorio' ),
							'empty'    => array( 'WPForms no ha devuelto el formulario (puede estar vacío o desactivado). Revisa el formulario en WPForms. Mientras tanto, los visitantes ven el aviso de abajo.', 'admin.php?page=wpforms-overview', 'Abrir WPForms' ),
						)
						: array(
							'inactive' => array( 'El formulario de contacto no se puede mostrar porque el plugin WPForms no está activo. Los visitantes ven el aviso de abajo con el email de contacto. Avisa a la persona que administra la web para que lo active.', '', '' ),
							'missing'  => array( sprintf( 'Todavía no existe el formulario de contacto en %s. Los visitantes ven el aviso de abajo con el email de contacto. Avisa a la persona que administra la web para que cree el formulario.', $lang_name ), '', '' ),
							'empty'    => array( 'WPForms no ha devuelto el formulario (puede estar vacío o desactivado). Los visitantes ven el aviso de abajo con el email de contacto. Avisa a la persona que administra la web para que lo revise.', '', '' ),
						);
					$why     = $reasons[ $form['reason'] ] ?? $reasons['inactive'];
					echo mats_preview_note( $why[0], $why[1], $why[2] ); // phpcs:ignore WordPress.Security.EscapeOutput
				}
				?>
				<p class="lf__missing">
					<?php echo esc_html( mats_t( 'form_missing', $lang ) ); ?>
					<a href="<?php echo esc_url( 'mailto:' . $email ); ?>"><?php echo esc_html( $email ); ?></a>.
				</p>
			</div>
		<?php endif; ?>
	</div>
</section>
