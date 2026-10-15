<?php
/**
 * IP & validation band (research.astro): plum credibility band with two
 * soft glows, the title and one glass panel per paragraph, each drawing a
 * vapour line along its bottom edge on reveal.
 *   <section aria-labelledby="validation-title" class="sec validation--dark" id="validation">
 *
 * Like the original, the section is not printed at all while it has no
 * paragraph.
 *
 * @package matsurfer
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$title      = (string) mats_get( $attributes, 'title' );
$paragraphs = array_map(
	static fn( $v ) => is_scalar( $v ) ? (string) $v : '',
	array_values( (array) mats_get( $attributes, 'paragraphs', array() ) )
);
$section_id = sanitize_html_class( (string) mats_get( $attributes, 'sectionId', 'validation' ) );
$heading_id = sanitize_html_class( (string) mats_get( $attributes, 'headingId', 'validation-title' ) );

/* keep the list index of each paragraph: it is the in-place editing path */
$filled = array_filter( $paragraphs, 'mats_has' );

if ( ! $filled ) {
	echo mats_preview_empty( 'Validación y propiedad intelectual' ); // phpcs:ignore WordPress.Security.EscapeOutput
	return;
}

/* ids stay unique when the section is placed more than once on a page */
$used_ids = isset( $GLOBALS['mats_used_ids'] ) && is_array( $GLOBALS['mats_used_ids'] ) ? $GLOBALS['mats_used_ids'] : array();
if ( '' === $heading_id || isset( $used_ids[ $heading_id ] ) ) {
	$heading_id = wp_unique_id( 'validation-title-' );
}
if ( isset( $used_ids[ $section_id ] ) ) {
	$section_id = '';
}
$used_ids[ $heading_id ] = true;
if ( '' !== $section_id ) {
	$used_ids[ $section_id ] = true;
}
$GLOBALS['mats_used_ids'] = $used_ids;

$has_title = mats_has( $title );

/* the editor shows the drawn state of the vapour line (nothing scrolls into view there) */
$panel_class = 'research__validation reveal' . ( mats_is_preview() ? ' is-visible' : '' );
?>
<section<?php echo $has_title ? ' aria-labelledby="' . esc_attr( $heading_id ) . '"' : ''; ?> class="sec validation--dark"<?php echo '' !== $section_id ? ' id="' . esc_attr( $section_id ) . '"' : ''; ?>>
	<div class="validation__glow" aria-hidden="true"></div>
	<div class="container">
		<?php if ( $has_title ) : ?>
			<h2 class="research__head research__head--dark" id="<?php echo esc_attr( $heading_id ); ?>"<?php echo mats_edit( 'title' ); ?>><?php echo mats_text( $title ); ?></h2>
		<?php endif; ?>
		<?php foreach ( $filled as $i => $paragraph ) : ?>
			<div class="<?php echo esc_attr( $panel_class ); ?>">
				<p<?php echo mats_edit( 'paragraphs.' . $i ); ?>><?php echo mats_text( $paragraph ); ?></p>
				<span class="research__layer" aria-hidden="true"></span>
			</div>
		<?php endforeach; ?>
	</div>
</section>
