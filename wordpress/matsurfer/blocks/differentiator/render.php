<?php
/**
 * Key differentiator (product page, #differentiator): violet mist band with
 * the eyebrow, a highlighted panel where the source–substrate distance "d"
 * is drawn as an instrument annotation (the line breathes), and the grid of
 * features under vapour hairlines. Port of the section in
 * src/pages/[...lang]/product.astro:
 *   <section class="sec hairtop mist--violet" id="differentiator" aria-labelledby="diff-title">
 * The extra root class .diff only scopes this block's styles.
 *
 * @package matsurfer
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$kicker     = (string) mats_get( $attributes, 'kicker' );
$title      = (string) mats_get( $attributes, 'title' );
$text       = (string) mats_get( $attributes, 'text' );
$features   = (array) mats_get( $attributes, 'features', array() );
$section_id = sanitize_html_class( (string) mats_get( $attributes, 'sectionId', 'differentiator' ) );
$heading_id = sanitize_html_class( (string) mats_get( $attributes, 'headingId', 'diff-title' ) );

/* features with something to show, keeping their index for the edit paths */
$items = array();
foreach ( $features as $i => $feature ) {
	$feature = is_array( $feature ) ? $feature : array();
	$f_title = (string) ( $feature['title'] ?? '' );
	$f_text  = (string) ( $feature['text'] ?? '' );
	if ( mats_has( $f_title ) || mats_has( $f_text ) ) {
		$items[ $i ] = array(
			'title' => $f_title,
			'text'  => $f_text,
		);
	}
}

if ( ! mats_has( $title ) && ! $items ) {
	echo mats_preview_empty( 'Diferencial clave' ); // phpcs:ignore WordPress.Security.EscapeOutput
	return;
}

/* ids stay unique when the section is placed more than once on a page */
$used_ids = isset( $GLOBALS['mats_used_ids'] ) && is_array( $GLOBALS['mats_used_ids'] ) ? $GLOBALS['mats_used_ids'] : array();
if ( '' === $heading_id || isset( $used_ids[ $heading_id ] ) ) {
	$heading_id = wp_unique_id( 'diff-title-' );
}
if ( isset( $used_ids[ $section_id ] ) ) {
	$section_id = '';
}
$used_ids[ $heading_id ] = true;
if ( '' !== $section_id ) {
	$used_ids[ $section_id ] = true;
}
$GLOBALS['mats_used_ids'] = $used_ids;
?>
<section class="sec hairtop mist--violet diff"<?php echo '' !== $section_id ? ' id="' . esc_attr( $section_id ) . '"' : ''; ?><?php echo mats_has( $title ) ? ' aria-labelledby="' . esc_attr( $heading_id ) . '"' : ''; ?>>
	<div class="container">
		<?php if ( mats_has( $kicker ) ) : ?>
			<p class="eyebrow reveal"<?php echo mats_edit( 'kicker' ); ?>><?php echo mats_text( $kicker ); ?></p>
		<?php endif; ?>
		<?php if ( mats_has( $title ) || mats_has( $text ) ) : ?>
			<div class="diff__hero panel reveal">
				<div class="diff__d" aria-hidden="true">
					<span class="mono">d</span>
					<span class="diff__d-line"></span>
				</div>
				<div>
					<?php if ( mats_has( $title ) ) : ?>
						<h2 class="diff__title" id="<?php echo esc_attr( $heading_id ); ?>"<?php echo mats_edit( 'title' ); ?>><?php echo mats_text( $title ); ?></h2>
					<?php endif; ?>
					<?php if ( mats_has( $text ) ) : ?>
						<p class="diff__text"<?php echo mats_edit( 'text' ); ?>><?php echo mats_text( $text ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>
		<?php if ( $items ) : ?>
			<div class="diff__grid">
				<?php foreach ( $items as $i => $feature ) : ?>
					<div class="diff__feature reveal">
						<?php if ( mats_has( $feature['title'] ) ) : ?>
							<h3<?php echo mats_edit( 'features.' . $i . '.title' ); ?>><?php echo mats_text( $feature['title'] ); ?></h3>
						<?php endif; ?>
						<?php if ( mats_has( $feature['text'] ) ) : ?>
							<p<?php echo mats_edit( 'features.' . $i . '.text' ); ?>><?php echo mats_text( $feature['text'] ); ?></p>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
