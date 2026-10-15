<?php
/**
 * Expert experiences (research.astro): section title and a two-column grid
 * of cards — mono field tag, the quote, the attribution line (only when the
 * evaluation has a name), the device and an optional "more" link — with a
 * vapour hairline drawn on reveal and a large quote mark in the corner.
 *   <section aria-labelledby="experiences-title" class="sec hairtop">
 *
 * The evaluations come from the Valoraciones records (mats_get_experts(),
 * newest first). Like the original, the section is not printed at all
 * while there is none. The extra root class .research-experiences only
 * scopes this block's styles.
 *
 * @package matsurfer
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$title      = (string) mats_get( $attributes, 'title' );
$more       = (string) mats_get( $attributes, 'moreLink' );
$heading_id = sanitize_html_class( (string) mats_get( $attributes, 'headingId', 'experiences-title' ) );

$experts = array_values( mats_get_experts() );

if ( ! $experts ) {
	echo mats_preview_note( 'Esta sección no se muestra en la web hasta que haya alguna valoración publicada.', 'edit.php?post_type=mats_expert', 'Editar valoraciones' ); // phpcs:ignore WordPress.Security.EscapeOutput
	return;
}

/* ids stay unique when the section is placed more than once on a page */
$used_ids = isset( $GLOBALS['mats_used_ids'] ) && is_array( $GLOBALS['mats_used_ids'] ) ? $GLOBALS['mats_used_ids'] : array();
if ( '' === $heading_id || isset( $used_ids[ $heading_id ] ) ) {
	$heading_id = wp_unique_id( 'experiences-title-' );
}
$used_ids[ $heading_id ]  = true;
$GLOBALS['mats_used_ids'] = $used_ids;

$has_title = mats_has( $title );

/* the editor shows the drawn state of the hairline (nothing scrolls into view there) */
$card_class = 'panel research__card reveal' . ( mats_is_preview() ? ' is-visible' : '' );
?>
<section<?php echo $has_title ? ' aria-labelledby="' . esc_attr( $heading_id ) . '"' : ''; ?> class="sec hairtop research-experiences">
	<?php echo mats_preview_note( 'Las valoraciones salen automáticamente de las publicadas en el menú Valoraciones (la más reciente primero).', 'edit.php?post_type=mats_expert', 'Editar valoraciones' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<div class="container">
		<?php if ( $has_title ) : ?>
			<h2 class="research__head" id="<?php echo esc_attr( $heading_id ); ?>"<?php echo mats_edit( 'title' ); ?>><?php echo mats_text( $title ); ?></h2>
		<?php endif; ?>
		<div class="research__grid">
			<?php foreach ( $experts as $e ) : ?>
				<article class="<?php echo esc_attr( $card_class ); ?>">
					<?php if ( mats_has( $e['field'] ) ) : ?>
						<span class="research__field"><?php echo mats_text( $e['field'] ); ?></span>
					<?php endif; ?>
					<blockquote class="research__quote"><p>“<?php echo mats_text( $e['quote'] ); ?>”</p></blockquote>
					<?php if ( mats_has( $e['name'] ) ) : ?>
						<p class="research__who">
							<strong><?php echo mats_text( $e['name'] ); ?></strong><?php echo mats_has( $e['role'] ) ? ' · ' . mats_text( $e['role'] ) : ''; ?><?php echo mats_has( $e['institution'] ) ? ', ' . mats_text( $e['institution'] ) : ''; ?>
						</p>
					<?php endif; ?>
					<?php if ( mats_has( $e['device'] ) ) : ?>
						<p class="research__device"><?php echo mats_text( $e['device'] ); ?></p>
					<?php endif; ?>
					<?php if ( mats_has( $e['link'] ) && mats_has( $more ) ) : ?>
						<a class="research__link" href="<?php echo esc_url( $e['link'] ); ?>" target="_blank" rel="noopener"<?php echo mats_edit( 'moreLink' ); ?>><?php echo mats_text( $more ); ?></a>
					<?php endif; ?>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>
