<?php
/**
 * Dark highlighted band (applications.astro, "beyond surface science"):
 * plum band with two soft glows and a centred glass panel — mono kicker,
 * title, text — whose bottom edge draws a vapour line on reveal.
 *   <section class="sec broader--dark" aria-labelledby="broader-title">
 *
 * @package matsurfer
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$kicker     = (string) mats_get( $attributes, 'kicker' );
$title      = (string) mats_get( $attributes, 'title' );
$text       = (string) mats_get( $attributes, 'text' );
$heading_id = sanitize_html_class( (string) mats_get( $attributes, 'headingId', 'broader-title' ) );

if ( ! mats_has( $title ) && ! mats_has( $text ) ) {
	echo mats_preview_empty( 'Banda oscura destacada' ); // phpcs:ignore WordPress.Security.EscapeOutput
	return;
}

/* ids stay unique when the section is placed more than once on a page */
$used_ids = isset( $GLOBALS['mats_used_ids'] ) && is_array( $GLOBALS['mats_used_ids'] ) ? $GLOBALS['mats_used_ids'] : array();
if ( '' === $heading_id || isset( $used_ids[ $heading_id ] ) ) {
	$heading_id = wp_unique_id( 'broader-title-' );
}
$used_ids[ $heading_id ]  = true;
$GLOBALS['mats_used_ids'] = $used_ids;

$has_title = mats_has( $title );

/* the editor shows the drawn state of the vapour line (nothing scrolls into view there) */
$panel_class = 'broader__panel reveal' . ( mats_is_preview() ? ' is-visible' : '' );
?>
<section class="sec broader--dark"<?php echo $has_title ? ' aria-labelledby="' . esc_attr( $heading_id ) . '"' : ''; ?>>
	<div class="broader__glow" aria-hidden="true"></div>
	<div class="container broader">
		<div class="<?php echo esc_attr( $panel_class ); ?>">
			<?php if ( mats_has( $kicker ) ) : ?>
				<p class="eyebrow"<?php echo mats_edit( 'kicker' ); ?>><?php echo mats_text( $kicker ); ?></p>
			<?php endif; ?>
			<?php if ( $has_title ) : ?>
				<h2 class="h2" id="<?php echo esc_attr( $heading_id ); ?>"<?php echo mats_edit( 'title' ); ?>><?php echo mats_text( $title ); ?></h2>
			<?php endif; ?>
			<?php if ( mats_has( $text ) ) : ?>
				<p class="broader__text"<?php echo mats_edit( 'text' ); ?>><?php echo mats_text( $text ); ?></p>
			<?php endif; ?>
			<span class="broader__layer" aria-hidden="true"></span>
		</div>
	</div>
</section>
