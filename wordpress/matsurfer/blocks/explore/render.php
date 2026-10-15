<?php
/**
 * Home research + team strip (index.astro, "research + team strip"): two
 * compact panels side by side, each with a title, one line of text and a
 * small ghost button; a violet wash slides in on hover. Pure CSS.
 *   <section class="sec hairtop explore" aria-label="Case studies · The team">
 *
 * @package matsurfer
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$cards = array(
	'research' => array(
		'title'  => (string) mats_get( $attributes, 'researchTitle' ),
		'text'   => (string) mats_get( $attributes, 'researchText' ),
		'cta'    => (array) mats_get( $attributes, 'researchCta', array() ),
		'source' => (string) mats_get( $attributes, 'researchCtaSource', 'home-research' ),
	),
	'team'     => array(
		'title'  => (string) mats_get( $attributes, 'teamTitle' ),
		'text'   => (string) mats_get( $attributes, 'teamText' ),
		'cta'    => (array) mats_get( $attributes, 'teamCta', array() ),
		'source' => (string) mats_get( $attributes, 'teamCtaSource', 'home-team' ),
	),
);
$cards = array_filter(
	$cards,
	static fn( $card ) => mats_has( $card['title'] ) || mats_has( mats_link_label( $card['cta'] ) )
);

if ( ! $cards ) {
	echo mats_preview_empty( 'Casos de estudio y equipo (accesos)' ); // phpcs:ignore WordPress.Security.EscapeOutput
	return;
}

$aria_label = implode( ' · ', array_filter( array_column( $cards, 'title' ), 'mats_has' ) );
?>
<section class="sec hairtop explore"<?php echo '' !== $aria_label ? ' aria-label="' . esc_attr( $aria_label ) . '"' : ''; ?>>
	<div class="container explore__grid">
		<?php
		foreach ( $cards as $key => $card ) :
			$label = mats_link_label( $card['cta'] );
			?>
			<div class="panel explore__card reveal">
				<div>
					<?php if ( mats_has( $card['title'] ) ) : ?>
						<h2<?php echo mats_edit( $key . 'Title' ); ?>><?php echo mats_text( $card['title'] ); ?></h2>
					<?php endif; ?>
					<?php if ( mats_has( $card['text'] ) ) : ?>
						<p<?php echo mats_edit( $key . 'Text' ); ?>><?php echo mats_text( $card['text'] ); ?></p>
					<?php endif; ?>
				</div>
				<?php if ( mats_has( $label ) ) : ?>
					<a class="btn btn--ghost btn--sm" <?php echo mats_link_attrs( $card['cta'] ); ?><?php echo $card['source'] ? ' data-cta-source="' . esc_attr( $card['source'] ) . '"' : ''; ?><?php echo mats_edit( $key . 'Cta.label' ); ?>><?php echo mats_text( $label ); ?></a>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
</section>
