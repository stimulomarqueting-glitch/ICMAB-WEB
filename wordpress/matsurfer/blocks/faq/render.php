<?php
/**
 * FAQ (product page, #faq): section header (SectionHeader.astro: eyebrow +
 * h2) and the accessible accordion of src/components/Accordion.astro
 * (<button aria-expanded aria-controls> toggling a labelled region; the
 * panel height animates by growing its grid row — view.js). Port of the
 * section in src/pages/[...lang]/product.astro:
 *   <section class="sec hairtop" id="faq" aria-labelledby="faq-title">
 *     … <Accordion items={faqItems} idPrefix="faq" />
 * The extra root class .faq only scopes this block's styles.
 *
 * Editor preview: every answer is shown open (nothing runs the accordion
 * script there) so questions and answers can be edited in place.
 *
 * @package matsurfer
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$kicker     = (string) mats_get( $attributes, 'kicker' );
$title      = (string) mats_get( $attributes, 'title' );
$raw_items  = (array) mats_get( $attributes, 'items', array() );
$section_id = sanitize_html_class( (string) mats_get( $attributes, 'sectionId', 'faq' ) );
$heading_id = sanitize_html_class( (string) mats_get( $attributes, 'headingId', 'faq-title' ) );
$prefix     = sanitize_html_class( (string) mats_get( $attributes, 'idPrefix', 'faq' ) );
$preview    = mats_is_preview();

/* questions with a text, keeping their index for the edit paths */
$items = array();
foreach ( $raw_items as $i => $item ) {
	$item = is_array( $item ) ? $item : array();
	$q    = (string) ( $item['q'] ?? '' );
	if ( mats_has( $q ) ) {
		$items[ $i ] = array(
			'q' => $q,
			'a' => (string) ( $item['a'] ?? '' ),
		);
	}
}

if ( ! $items ) {
	echo mats_preview_empty( 'Preguntas frecuentes' ); // phpcs:ignore WordPress.Security.EscapeOutput
	return;
}

/* ids stay unique when the section is placed more than once on a page */
$used_ids = isset( $GLOBALS['mats_used_ids'] ) && is_array( $GLOBALS['mats_used_ids'] ) ? $GLOBALS['mats_used_ids'] : array();
if ( '' === $heading_id || isset( $used_ids[ $heading_id ] ) ) {
	$heading_id = wp_unique_id( 'faq-title-' );
}
if ( isset( $used_ids[ $section_id ] ) ) {
	$section_id = '';
}
if ( '' === $prefix || isset( $used_ids[ 'acc:' . $prefix ] ) ) {
	$prefix = wp_unique_id( 'faq-' );
}
$used_ids[ $heading_id ]       = true;
$used_ids[ 'acc:' . $prefix ] = true;
if ( '' !== $section_id ) {
	$used_ids[ $section_id ] = true;
}
$GLOBALS['mats_used_ids'] = $used_ids;
?>
<section class="sec hairtop faq"<?php echo '' !== $section_id ? ' id="' . esc_attr( $section_id ) . '"' : ''; ?><?php echo mats_has( $title ) ? ' aria-labelledby="' . esc_attr( $heading_id ) . '"' : ''; ?>>
	<div class="container">
		<?php if ( mats_has( $kicker ) || mats_has( $title ) ) : ?>
			<div class="reveal">
				<?php if ( mats_has( $kicker ) ) : ?>
					<p class="eyebrow"<?php echo mats_edit( 'kicker' ); ?>><?php echo mats_text( $kicker ); ?></p>
				<?php endif; ?>
				<?php if ( mats_has( $title ) ) : ?>
					<h2 class="h2" id="<?php echo esc_attr( $heading_id ); ?>"<?php echo mats_edit( 'title' ); ?>><?php echo mats_text( $title ); ?></h2>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<div class="acc reveal" data-acc>
			<ul class="acc__list" role="list">
				<?php
				$n = 0;
				foreach ( $items as $i => $item ) :
					$t_id = $prefix . '-t-' . $n;
					$p_id = $prefix . '-p-' . $n;
					++$n;
					?>
					<li class="acc__item"<?php echo $preview ? ' data-open' : ''; ?>>
						<h3 class="acc__heading">
							<button type="button" class="acc__trigger" id="<?php echo esc_attr( $t_id ); ?>" aria-expanded="<?php echo $preview ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr( $p_id ); ?>">
								<?php if ( $preview ) : ?>
									<span<?php echo mats_edit( 'items.' . $i . '.q' ); ?>><?php echo mats_text( $item['q'] ); ?></span>
								<?php else : ?>
									<?php echo mats_text( $item['q'] ); ?>
								<?php endif; ?>
								<span class="acc__icon" aria-hidden="true"></span>
							</button>
						</h3>
						<div class="acc__panel" id="<?php echo esc_attr( $p_id ); ?>" role="region" aria-labelledby="<?php echo esc_attr( $t_id ); ?>"<?php echo $preview ? '' : ' hidden'; ?>>
							<div class="acc__inner">
								<p<?php echo mats_edit( 'items.' . $i . '.a' ); ?>><?php echo mats_text( $item['a'] ); ?></p>
							</div>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
</section>
