<?php
/**
 * Home product teaser (index.astro, "product teaser"): SectionHeader copy
 * + button on the left, the device close-up on the right; the photo leans
 * in a little (3D tilt) when the pointer is over the grid. Pure CSS.
 *   <section class="sec hairtop mist--violet" id="technology" aria-labelledby="product-teaser-title">
 * The extra root class .product-teaser only scopes this block's styles.
 *
 * @package matsurfer
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$kicker     = (string) mats_get( $attributes, 'kicker' );
$title      = (string) mats_get( $attributes, 'title' );
$lead       = (string) mats_get( $attributes, 'lead' );
$cta        = (array) mats_get( $attributes, 'cta', array() );
$photo      = mats_get( $attributes, 'photo', array() );
$photo      = is_object( $photo ) ? (array) $photo : $photo; // block default is an empty object
$section_id = sanitize_html_class( (string) mats_get( $attributes, 'sectionId', 'technology' ) );
$heading_id = sanitize_html_class( (string) mats_get( $attributes, 'headingId', 'product-teaser-title' ) );
$source     = (string) mats_get( $attributes, 'ctaSource', 'home-product' );

if ( ! mats_has( $title ) ) {
	echo mats_preview_empty( 'Presentación del producto (inicio)' ); // phpcs:ignore WordPress.Security.EscapeOutput
	return;
}

/* ids stay unique when the section is placed more than once on a page */
$used_ids = isset( $GLOBALS['mats_used_ids'] ) && is_array( $GLOBALS['mats_used_ids'] ) ? $GLOBALS['mats_used_ids'] : array();
if ( '' === $heading_id || isset( $used_ids[ $heading_id ] ) ) {
	$heading_id = wp_unique_id( 'product-teaser-title-' );
}
if ( isset( $used_ids[ $section_id ] ) ) {
	$section_id = '';
}
$used_ids[ $heading_id ] = true;
if ( '' !== $section_id ) {
	$used_ids[ $section_id ] = true;
}
$GLOBALS['mats_used_ids'] = $used_ids;

$cta_label  = mats_link_label( $cta );
$photo_html = mats_image(
	$photo,
	array(
		'class'  => 'pt__photo reveal',
		'size'   => 'full',
		'sizes'  => '(max-width: 56rem) calc(100vw - 4rem), (max-width: 79rem) 40vw, 480px',
		'width'  => 1000,
		'height' => 750,
		'edit'   => 'photo',
	)
);
?>
<section class="sec hairtop mist--violet product-teaser"<?php echo '' !== $section_id ? ' id="' . esc_attr( $section_id ) . '"' : ''; ?> aria-labelledby="<?php echo esc_attr( $heading_id ); ?>">
	<div class="container pt__grid">
		<div>
			<div class="reveal">
				<?php if ( mats_has( $kicker ) ) : ?>
					<p class="eyebrow"<?php echo mats_edit( 'kicker' ); ?>><?php echo mats_text( $kicker ); ?></p>
				<?php endif; ?>
				<h2 class="h2" id="<?php echo esc_attr( $heading_id ); ?>"<?php echo mats_edit( 'title' ); ?>><?php echo mats_text( $title ); ?></h2>
				<?php if ( mats_has( $lead ) ) : ?>
					<p class="lead"<?php echo mats_edit( 'lead' ); ?>><?php echo mats_text( $lead ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( mats_has( $cta_label ) ) : ?>
				<div class="pt__cta reveal">
					<a class="btn" <?php echo mats_link_attrs( $cta ); ?><?php echo $source ? ' data-cta-source="' . esc_attr( $source ) . '"' : ''; ?><?php echo mats_edit( 'cta.label' ); ?>><?php echo mats_text( $cta_label ); ?></a>
				</div>
			<?php endif; ?>
		</div>
		<?php echo $photo_html; // phpcs:ignore WordPress.Security.EscapeOutput -- built by mats_image() ?>
	</div>
</section>
