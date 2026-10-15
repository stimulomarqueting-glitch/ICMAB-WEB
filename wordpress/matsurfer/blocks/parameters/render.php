<?php
/**
 * Parameters (product page, #parameters): coral mist band with the section
 * header (SectionHeader.astro: eyebrow + h2 + lead) and the grid of
 * parameter cards (mono symbol + label; the first card is tinted). Port of
 * the section in src/pages/[...lang]/product.astro:
 *   <section class="sec hairtop mist" id="parameters" aria-labelledby="params-title">
 * The extra root class .params only scopes this block's styles.
 *
 * @package matsurfer
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$kicker     = (string) mats_get( $attributes, 'kicker' );
$title      = (string) mats_get( $attributes, 'title' );
$lead       = (string) mats_get( $attributes, 'lead' );
$raw_items  = (array) mats_get( $attributes, 'items', array() );
$section_id = sanitize_html_class( (string) mats_get( $attributes, 'sectionId', 'parameters' ) );
$heading_id = sanitize_html_class( (string) mats_get( $attributes, 'headingId', 'params-title' ) );

/* cards with something to show, keeping their index for the edit paths */
$items = array();
foreach ( $raw_items as $i => $item ) {
	$item   = is_array( $item ) ? $item : array();
	$symbol = (string) ( $item['symbol'] ?? '' );
	$label  = (string) ( $item['label'] ?? '' );
	if ( mats_has( $symbol ) || mats_has( $label ) ) {
		$items[ $i ] = array(
			'symbol' => $symbol,
			'label'  => $label,
		);
	}
}

if ( ! mats_has( $title ) && ! $items ) {
	echo mats_preview_empty( 'Parámetros' ); // phpcs:ignore WordPress.Security.EscapeOutput
	return;
}

/* ids stay unique when the section is placed more than once on a page */
$used_ids = isset( $GLOBALS['mats_used_ids'] ) && is_array( $GLOBALS['mats_used_ids'] ) ? $GLOBALS['mats_used_ids'] : array();
if ( '' === $heading_id || isset( $used_ids[ $heading_id ] ) ) {
	$heading_id = wp_unique_id( 'params-title-' );
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
<section class="sec hairtop mist params"<?php echo '' !== $section_id ? ' id="' . esc_attr( $section_id ) . '"' : ''; ?><?php echo mats_has( $title ) ? ' aria-labelledby="' . esc_attr( $heading_id ) . '"' : ''; ?>>
	<div class="container">
		<div class="reveal">
			<?php if ( mats_has( $kicker ) ) : ?>
				<p class="eyebrow"<?php echo mats_edit( 'kicker' ); ?>><?php echo mats_text( $kicker ); ?></p>
			<?php endif; ?>
			<?php if ( mats_has( $title ) ) : ?>
				<h2 class="h2" id="<?php echo esc_attr( $heading_id ); ?>"<?php echo mats_edit( 'title' ); ?>><?php echo mats_text( $title ); ?></h2>
			<?php endif; ?>
			<?php if ( mats_has( $lead ) ) : ?>
				<p class="lead"<?php echo mats_edit( 'lead' ); ?>><?php echo mats_text( $lead ); ?></p>
			<?php endif; ?>
		</div>
		<?php if ( $items ) : ?>
			<ul class="params__grid reveal" role="list">
				<?php foreach ( $items as $i => $item ) : ?>
					<li class="params__card">
						<span class="params__symbol mono"<?php echo mats_edit( 'items.' . $i . '.symbol' ); ?>><?php echo mats_text( $item['symbol'] ); ?></span>
						<span class="params__label"<?php echo mats_edit( 'items.' . $i . '.label' ); ?>><?php echo mats_text( $item['label'] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
</section>
