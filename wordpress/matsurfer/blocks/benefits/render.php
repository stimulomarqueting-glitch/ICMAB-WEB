<?php
/**
 * Outcome band (Benefits.astro) — the dark beat of a page (plum-violet):
 * SectionHeader copy on the plum backdrop and four glass cards, each with a
 * single-weight line icon and, along its base, the deposited layer — a
 * vapour line that draws itself when the card enters the viewport (CSS,
 * triggered by the site-wide reveal adding .is-visible).
 *   <section class="sec bene--dark" id="benefits" aria-labelledby="benefits-title">
 * Used on home (heading id "benefits-title") and on the product page
 * ("product-benefits-title"): the heading id is the headingId attribute.
 *
 * @package matsurfer
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$kicker     = (string) mats_get( $attributes, 'kicker' );
$title      = (string) mats_get( $attributes, 'title' );
$section_id = sanitize_html_class( (string) mats_get( $attributes, 'sectionId', 'benefits' ) );
$heading_id = sanitize_html_class( (string) mats_get( $attributes, 'headingId', 'benefits-title' ) );

/* line icons, one per benefit: vial (little material) · dot matrix (many
   conditions) · people (the whole group) · shield-check (defensible) */
$icons = array(
	'material'   => 'M10.5 4h7M12 4v7.5M16 4v7.5M12 11.5 8.8 20.2A2 2 0 0 0 10.7 23h6.6a2 2 0 0 0 1.9-2.8L16 11.5M10.2 17h7.6',
	'conditions' => 'M8 8h.01M14 8h.01M20 8h.01M8 14h.01M14 14h.01M20 14h.01M8 20h.01M14 20h.01M20 20h.01',
	'group'      => 'M10.5 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM18.5 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM4.5 22a6 6 0 0 1 12 0M16.5 22a4.5 4.5 0 0 1 7-3.7',
	'defensible' => 'M14 4.5l7.5 2.8v5.4c0 4.8-3.2 8.3-7.5 10-4.3-1.7-7.5-5.2-7.5-10V7.3L14 4.5ZM10.6 14.2l2.4 2.4 4.6-4.8',
);

/* keys kept: they are the attribute indexes used by in-place editing */
$outcomes = array();
foreach ( (array) mats_get( $attributes, 'outcomes', array() ) as $index => $item ) {
	$item = is_object( $item ) ? (array) $item : $item;
	if ( ! is_array( $item ) ) {
		continue;
	}
	$o_title = is_scalar( $item['title'] ?? '' ) ? (string) ( $item['title'] ?? '' ) : '';
	$o_text  = is_scalar( $item['text'] ?? '' ) ? (string) ( $item['text'] ?? '' ) : '';
	if ( ! mats_has( $o_title ) && ! mats_has( $o_text ) ) {
		continue;
	}
	$icon               = is_scalar( $item['icon'] ?? '' ) ? (string) ( $item['icon'] ?? '' ) : '';
	$outcomes[ $index ] = array(
		'icon'  => isset( $icons[ $icon ] ) ? $icon : 'conditions',
		'title' => $o_title,
		'text'  => $o_text,
	);
}

if ( ! mats_has( $title ) && ! $outcomes ) {
	echo mats_preview_empty( 'Beneficios' ); // phpcs:ignore WordPress.Security.EscapeOutput
	return;
}

/* ids stay unique when the section is placed more than once on a page */
$used_ids = isset( $GLOBALS['mats_used_ids'] ) && is_array( $GLOBALS['mats_used_ids'] ) ? $GLOBALS['mats_used_ids'] : array();
if ( '' === $heading_id || isset( $used_ids[ $heading_id ] ) ) {
	$heading_id = wp_unique_id( 'benefits-title-' );
}
if ( isset( $used_ids[ $section_id ] ) ) {
	$section_id = '';
}
$used_ids[ $heading_id ] = true;
if ( '' !== $section_id ) {
	$used_ids[ $section_id ] = true;
}
$GLOBALS['mats_used_ids'] = $used_ids;

/* the editor shows the drawn state of the cards (nothing scrolls into view there) */
$card_class = 'bene__outcome reveal' . ( mats_is_preview() ? ' is-visible' : '' );
$position   = 0;
?>
<section class="sec bene--dark"<?php echo '' !== $section_id ? ' id="' . esc_attr( $section_id ) . '"' : ''; ?><?php echo mats_has( $title ) ? ' aria-labelledby="' . esc_attr( $heading_id ) . '"' : ''; ?>>
	<div class="bene__glow" aria-hidden="true"></div>
	<div class="container">
		<div class="reveal">
			<?php if ( mats_has( $kicker ) ) : ?>
				<p class="eyebrow"<?php echo mats_edit( 'kicker' ); ?>><?php echo mats_text( $kicker ); ?></p>
			<?php endif; ?>
			<?php if ( mats_has( $title ) ) : ?>
				<h2 class="h2" id="<?php echo esc_attr( $heading_id ); ?>"<?php echo mats_edit( 'title' ); ?>><?php echo mats_text( $title ); ?></h2>
			<?php endif; ?>
		</div>

		<?php if ( $outcomes ) : ?>
			<ol class="bene__grid" role="list">
				<?php
				foreach ( $outcomes as $index => $o ) :
					$base = 'outcomes.' . $index . '.';
					?>
					<li class="<?php echo esc_attr( $card_class ); ?>" style="--i: <?php echo (int) $position; ?>">
						<div class="bene__head">
							<span class="bene__icon" aria-hidden="true">
								<svg viewBox="0 0 28 28" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
									<path d="<?php echo esc_attr( $icons[ $o['icon'] ] ); ?>" stroke-width="<?php echo 'conditions' === $o['icon'] ? '2.6' : '1.5'; ?>"></path>
								</svg>
							</span>
						</div>
						<?php if ( mats_has( $o['title'] ) ) : ?>
							<h3<?php echo mats_edit( $base . 'title' ); ?>><?php echo mats_text( $o['title'] ); ?></h3>
						<?php endif; ?>
						<?php if ( mats_has( $o['text'] ) ) : ?>
							<p<?php echo mats_edit( $base . 'text' ); ?>><?php echo mats_text( $o['text'] ); ?></p>
						<?php endif; ?>
						<span class="bene__layer" aria-hidden="true"></span>
					</li>
					<?php
					++$position;
				endforeach;
				?>
			</ol>
		<?php endif; ?>
	</div>
</section>
