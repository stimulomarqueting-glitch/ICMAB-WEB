<?php
/**
 * Interior page hero (PageHero.astro): mono kicker (+ optional note), h1
 * that rises in word by word, short lead, optional primary CTA and an
 * optional photo on a plate that tilts with the pointer (view.js).
 *
 * @package matsurfer
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$kicker      = (string) mats_get( $attributes, 'kicker' );
$kicker_note = (string) mats_get( $attributes, 'kickerNote' );
$title       = trim( (string) mats_get( $attributes, 'title' ) );
$lead        = (string) mats_get( $attributes, 'lead' );
$cta         = (array) mats_get( $attributes, 'cta', array() );
$photo       = (array) mats_get( $attributes, 'photo', array() );
$source      = (string) mats_get( $attributes, 'ctaSource' );
$heading_id  = sanitize_html_class( (string) mats_get( $attributes, 'headingId' ) );
$heading_id  = '' !== $heading_id ? $heading_id : 'page-title';

if ( ! mats_has( $title ) ) {
	echo mats_preview_empty( 'Cabecera de página' ); // phpcs:ignore WordPress.Security.EscapeOutput
	return;
}

$cta_label  = mats_link_label( $cta );
$n_words    = count( preg_split( '/\s+/u', $title ) ?: array() );
$photo_id   = absint( $photo['id'] ?? 0 );
if ( $photo_id && ! wp_attachment_is_image( $photo_id ) ) {
	/* the photo was deleted from the Media Library (or is not an image): the
	   url stored next to the id points to a missing file, so drop it. Only an
	   external image (id 0) is shown from its url. */
	$photo['url'] = '';
}
$photo_html = '';
if ( $photo_id > 0 || mats_has( $photo['url'] ?? '' ) ) {
	/* '' on the public site when the attachment no longer exists → no photo
	   column; in the editor, the "choose a photo" placeholder */
	$photo_html = mats_image(
		$photo,
		array(
			'class'         => 'phero__photo',
			'size'          => 'full',
			'sizes'         => '(max-width: 56rem) calc(100vw - 4rem), (max-width: 79rem) 40vw, 480px',
			'fetchpriority' => 'high',
			'width'         => 1000,
			'height'        => 750,
			'edit'          => 'photo',
			/* the editor canvas disables pointer events on img:not([draggable]) (core
			   content.css); draggable=false keeps the photo clickable to replace it */
			'attrs'         => mats_is_preview() ? array( 'draggable' => 'false' ) : array(),
		)
	);
}
$has_photo = '' !== $photo_html;
?>
<section class="sec phero" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>" data-phero>
	<div class="container phero__grid<?php echo $has_photo ? ' phero__grid--photo' : ''; ?>">
		<div>
			<?php if ( mats_has( $kicker_note ) ) : ?>
				<div class="phero__row rise-in" style="--i: 0">
					<p class="eyebrow"<?php echo mats_edit( 'kicker' ); ?>><?php echo mats_text( $kicker ); ?></p>
					<p class="eyebrow phero__note"<?php echo mats_edit( 'kickerNote' ); ?>><?php echo mats_text( $kicker_note ); ?></p>
				</div>
			<?php elseif ( mats_has( $kicker ) ) : ?>
				<p class="eyebrow rise-in" style="--i: 0"<?php echo mats_edit( 'kicker' ); ?>><?php echo mats_text( $kicker ); ?></p>
			<?php endif; ?>
			<h1 class="phero__title" id="<?php echo esc_attr( $heading_id ); ?>"<?php echo mats_edit( 'title' ); ?>><?php echo mats_words( $title, '<span class="phero__word"><span class="rise-in" style="--i: %2$d">%1$s</span></span> ', 1 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></h1>
			<?php if ( mats_has( $lead ) ) : ?>
				<p class="lead rise-in" style="--i: <?php echo (int) ( $n_words + 1 ); ?>"<?php echo mats_edit( 'lead' ); ?>><?php echo mats_text( $lead ); ?></p>
			<?php endif; ?>
			<?php if ( mats_has( $cta_label ) ) : ?>
				<div class="phero__cta rise-in" style="--i: <?php echo (int) ( $n_words + 2 ); ?>">
					<a class="btn" <?php echo mats_link_attrs( $cta ); ?><?php echo $source ? ' data-cta-source="' . esc_attr( $source ) . '"' : ''; ?><?php echo mats_edit( 'cta.label' ); ?>><?php echo mats_text( $cta_label ); ?></a>
				</div>
			<?php endif; ?>
		</div>
		<?php if ( $has_photo ) : ?>
			<div class="phero__stage" aria-hidden="false">
				<div class="phero__plate">
					<span class="phero__halo" aria-hidden="true"></span>
					<?php echo $photo_html; // phpcs:ignore WordPress.Security.EscapeOutput -- built by mats_image() ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</section>
