<?php
/**
 * Stand-alone section introduction: SectionHeader.astro (mono eyebrow +
 * h2 + optional lead) inside its own hairline section, as used by the
 * "What it is" block of the product page:
 *   <section class="sec hairtop" id="what-it-is" aria-labelledby="what-title">
 * The extra root class .sec-intro only scopes this block's styles.
 *
 * @package matsurfer
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$kicker      = (string) mats_get( $attributes, 'kicker' );
$kicker_note = (string) mats_get( $attributes, 'kickerNote' );
$title       = (string) mats_get( $attributes, 'title' );
$lead        = (string) mats_get( $attributes, 'lead' );
$section_id  = sanitize_html_class( (string) mats_get( $attributes, 'sectionId' ) );
$heading_id  = sanitize_html_class( (string) mats_get( $attributes, 'headingId' ) );

if ( ! mats_has( $title ) ) {
	echo mats_preview_empty( 'Introducción de sección' ); // phpcs:ignore WordPress.Security.EscapeOutput
	return;
}

if ( '' === $heading_id ) {
	$heading_id = '' !== $section_id ? $section_id . '-title' : wp_unique_id( 'sec-intro-title-' );
}
?>
<section class="sec hairtop sec-intro"<?php echo '' !== $section_id ? ' id="' . esc_attr( $section_id ) . '"' : ''; ?> aria-labelledby="<?php echo esc_attr( $heading_id ); ?>">
	<div class="container">
		<div class="reveal">
			<?php if ( mats_has( $kicker_note ) ) : ?>
				<div class="sec-head__row">
					<p class="eyebrow"<?php echo mats_edit( 'kicker' ); ?>><?php echo mats_text( $kicker ); ?></p>
					<p class="eyebrow sec-head__note"<?php echo mats_edit( 'kickerNote' ); ?>><?php echo mats_text( $kicker_note ); ?></p>
				</div>
			<?php elseif ( mats_has( $kicker ) ) : ?>
				<p class="eyebrow"<?php echo mats_edit( 'kicker' ); ?>><?php echo mats_text( $kicker ); ?></p>
			<?php endif; ?>
			<h2 class="h2" id="<?php echo esc_attr( $heading_id ); ?>"<?php echo mats_edit( 'title' ); ?>><?php echo mats_text( $title ); ?></h2>
			<?php if ( mats_has( $lead ) ) : ?>
				<p class="lead"<?php echo mats_edit( 'lead' ); ?>><?php echo mats_text( $lead ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</section>
