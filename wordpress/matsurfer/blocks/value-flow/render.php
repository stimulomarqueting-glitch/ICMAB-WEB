<?php
/**
 * Home value flow (index.astro, "value flow"): Problem → MatSurfer → Result,
 * one sentence each. Three numbered panels in a row; the middle one carries
 * the MatSurfer isotype and the vapour tint, the last one ends on the
 * deposited layer (gradient line), and an arrow badge bridges each gap
 * (it turns to point down when the steps stack).
 *
 * The roles follow the step itself, as in Astro (item i === 1 is the core,
 * i === 2 the result): an empty step is skipped, but the isotype and the
 * core/result styling stay on their own step. Numbers, the --i stagger and
 * the arrows follow the visible order.
 *
 * The extra root class .value-flow only scopes this block's styles.
 *
 * @package matsurfer
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$label  = (string) mats_get( $attributes, 'label' );
$kicker = (string) mats_get( $attributes, 'kicker' );

/* keys kept: they are the attribute indexes used by in-place editing */
$items = array();
foreach ( (array) mats_get( $attributes, 'items', array() ) as $index => $item ) {
	$item = is_object( $item ) ? (array) $item : $item;
	if ( ! is_array( $item ) ) {
		continue;
	}
	$tag  = (string) ( $item['tag'] ?? '' );
	$text = (string) ( $item['text'] ?? '' );
	if ( mats_has( $tag ) || mats_has( $text ) ) {
		$items[ $index ] = array(
			'tag'  => $tag,
			'text' => $text,
		);
	}
}

if ( ! $items ) {
	echo mats_preview_empty( 'Problema → MatSurfer → Resultado' ); // phpcs:ignore WordPress.Security.EscapeOutput
	return;
}

$aria_label = mats_has( $label ) ? $label : $kicker;
$count      = count( $items );
$position   = 0;
?>
<section class="sec hairtop value-flow"<?php echo mats_has( $aria_label ) ? ' aria-label="' . esc_attr( $aria_label ) . '"' : ''; ?>>
	<div class="container">
		<?php if ( mats_has( $kicker ) ) : ?>
			<p class="eyebrow value__kicker reveal"<?php echo mats_edit( 'kicker' ); ?>><?php echo mats_text( $kicker ); ?></p>
		<?php endif; ?>
		<ol class="value" role="list">
			<?php
			foreach ( $items as $index => $item ) :
				$is_core = 1 === $index; // the MatSurfer step (Astro: i === 1)
				$classes = array( 'panel', 'value__item', 'reveal' );
				if ( $is_core ) {
					$classes[] = 'value__item--core';
				} elseif ( 2 === $index ) {
					$classes[] = 'value__item--result';
				}
				$base = 'items.' . $index . '.';
				?>
				<li class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" style="--i: <?php echo (int) $position; ?>">
					<div class="value__head">
						<span class="value__step"><?php echo esc_html( mats_pad( $position + 1 ) ); ?></span>
						<?php if ( $is_core ) : ?>
							<span class="value__tag value__tag--brand">
								<?php echo mats_brand_mark( 18 ); // phpcs:ignore WordPress.Security.EscapeOutput -- built and escaped by mats_brand_mark() ?>
								<?php if ( mats_is_preview() ) : ?>
									<span<?php echo mats_edit( $base . 'tag' ); ?>><?php echo mats_text( $item['tag'] ); ?></span>
								<?php else : ?>
									<?php echo mats_text( $item['tag'] ); ?>
								<?php endif; ?>
							</span>
						<?php elseif ( mats_has( $item['tag'] ) ) : ?>
							<span class="value__tag"<?php echo mats_edit( $base . 'tag' ); ?>><?php echo mats_text( $item['tag'] ); ?></span>
						<?php endif; ?>
					</div>
					<?php if ( mats_has( $item['text'] ) ) : ?>
						<p<?php echo mats_edit( $base . 'text' ); ?>><?php echo mats_text( $item['text'] ); ?></p>
					<?php endif; ?>
					<?php if ( $position < $count - 1 ) : ?>
						<span class="value__arrow" aria-hidden="true">
							<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8h10M9 4l4 4-4 4" /></svg>
						</span>
					<?php endif; ?>
				</li>
				<?php
				++$position;
			endforeach;
			?>
		</ol>
	</div>
</section>
