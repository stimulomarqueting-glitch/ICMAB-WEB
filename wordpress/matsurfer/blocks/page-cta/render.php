<?php
/**
 * Closing conversion band (PageCTA.astro): dark plum panel, sublimation
 * strip on the bottom edge, gradient pill button, optional email link.
 *
 * @package matsurfer
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$title          = (string) mats_get( $attributes, 'title' );
$text           = (string) mats_get( $attributes, 'text' );
$button         = (array) mats_get( $attributes, 'button', array() );
$centered       = ! empty( $attributes['centered'] );
$secondary_lead = (string) mats_get( $attributes, 'secondaryLead' );
$secondary      = (array) mats_get( $attributes, 'secondary', array() );
$source         = (string) mats_get( $attributes, 'ctaSource' );

if ( ! mats_has( $title ) && ! mats_has( mats_link_label( $button ) ) ) {
	echo mats_preview_empty( 'Llamada a la acción final' ); // phpcs:ignore WordPress.Security.EscapeOutput
	return;
}

$secondary_label = mats_link_label( $secondary );
$secondary_auto  = '' === $secondary_label && 'email' === ( $secondary['page'] ?? '' );
if ( $secondary_auto ) {
	$secondary_label = mats_contact_email();
}
$show_secondary = '' !== $secondary_label && ( mats_has( $secondary['page'] ?? '' ) || mats_has( $secondary['url'] ?? '' ) );
?>
<section class="sec pcta-sec" aria-label="<?php echo esc_attr( $title ); ?>">
	<div class="container">
		<div class="pcta reveal<?php echo $centered ? ' pcta--centered' : ''; ?>">
			<div class="pcta__copy">
				<h2 class="pcta__title"<?php echo mats_edit( 'title' ); ?>><?php echo mats_text( $title ); ?></h2>
				<?php if ( mats_has( $text ) ) : ?>
					<p class="pcta__text"<?php echo mats_edit( 'text' ); ?>><?php echo mats_text( $text ); ?></p>
				<?php endif; ?>
			</div>
			<div class="pcta__actions">
				<?php if ( mats_has( mats_link_label( $button ) ) ) : ?>
					<a class="btn btn--grad" <?php echo mats_link_attrs( $button ); ?><?php echo $source ? ' data-cta-source="' . esc_attr( $source ) . '"' : ''; ?><?php echo mats_edit( 'button.label' ); ?>><?php echo mats_text( mats_link_label( $button ) ); ?></a>
				<?php endif; ?>
				<?php if ( $show_secondary ) : ?>
					<p class="pcta__alt">
						<?php if ( mats_has( $secondary_lead ) ) : ?>
							<span<?php echo mats_edit( 'secondaryLead' ); ?>><?php echo mats_text( $secondary_lead ); ?></span>
						<?php endif; ?>
						<a <?php echo mats_link_attrs( $secondary ); ?><?php echo $secondary_auto ? '' : mats_edit( 'secondary.label' ); ?>><?php echo mats_text( $secondary_label ); ?></a>
					</p>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
