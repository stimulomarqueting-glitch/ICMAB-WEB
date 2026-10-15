<?php
/**
 * Invitation panel (research.astro, "Have you worked with the device?"):
 * a compact panel with a title and a sentence that ends in the contact
 * email (MatSurfer settings) as a mailto link.
 *   <section class="sec hairtop" aria-labelledby="invite-title">
 *
 * The extra root class .invite only scopes this block's styles.
 *
 * @package matsurfer
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$title      = (string) mats_get( $attributes, 'title' );
$text       = (string) mats_get( $attributes, 'text' );
$heading_id = sanitize_html_class( (string) mats_get( $attributes, 'headingId', 'invite-title' ) );
$email      = (string) mats_contact_email();

if ( ! mats_has( $title ) && ! mats_has( $text ) ) {
	echo mats_preview_empty( 'Invitación a colaborar' ); // phpcs:ignore WordPress.Security.EscapeOutput
	return;
}

/* ids stay unique when the section is placed more than once on a page */
$used_ids = isset( $GLOBALS['mats_used_ids'] ) && is_array( $GLOBALS['mats_used_ids'] ) ? $GLOBALS['mats_used_ids'] : array();
if ( '' === $heading_id || isset( $used_ids[ $heading_id ] ) ) {
	$heading_id = wp_unique_id( 'invite-title-' );
}
$used_ids[ $heading_id ]  = true;
$GLOBALS['mats_used_ids'] = $used_ids;

$has_title = mats_has( $title );
$has_email = is_email( $email );
?>
<section class="sec hairtop invite"<?php echo $has_title ? ' aria-labelledby="' . esc_attr( $heading_id ) . '"' : ''; ?>>
	<div class="container">
		<div class="panel page__contact reveal">
			<?php if ( $has_title ) : ?>
				<h2 id="<?php echo esc_attr( $heading_id ); ?>"<?php echo mats_edit( 'title' ); ?>><?php echo mats_text( $title ); ?></h2>
			<?php endif; ?>
			<?php if ( mats_has( $text ) || $has_email ) : ?>
				<p>
					<?php if ( mats_has( $text ) ) : ?>
						<?php
						/* the sentence is wrapped only in the editor, so it can be edited in place */
						echo mats_is_preview() ? '<span' . mats_edit( 'text' ) . '>' . mats_text( $text ) . '</span>' : mats_text( $text ); // phpcs:ignore WordPress.Security.EscapeOutput
						?>
					<?php endif; ?>
					<?php if ( $has_email ) : ?>
						<a href="<?php echo esc_url( 'mailto:' . $email ); ?>"><?php echo mats_text( $email ); ?></a>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</div>
	</div>
</section>
