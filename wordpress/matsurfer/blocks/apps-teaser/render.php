<?php
/**
 * Home applications teaser (index.astro, "applications teaser" + the
 * AppsIsotype.astro figure): SectionHeader copy, the capsule isotype with
 * callout lines to the five application fields, one chip per published
 * application (link to its block on the Applications page) and a button.
 *   <section class="sec hairtop mist" id="applications" aria-labelledby="apps-teaser-title">
 *
 * The figure draws itself when it scrolls into view (CSS, triggered by the
 * site-wide reveal adding .is-visible), the dots drift and the five fields
 * pulse in turn (motion-safe only). Hovering/focusing a chip lights its
 * field in the figure (view.js) through data-field = application slug.
 * The extra root class .apps-teaser only scopes this block's styles.
 *
 * @package matsurfer
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$kicker     = (string) mats_get( $attributes, 'kicker' );
$title      = (string) mats_get( $attributes, 'title' );
$lead       = (string) mats_get( $attributes, 'lead' );
$cta        = (array) mats_get( $attributes, 'cta', array() );
$caption    = (string) mats_get( $attributes, 'isotypeCaption' );
$alt        = (string) mats_get( $attributes, 'isotypeAlt' );
$labels     = array_values(
	array_map(
		static fn( $v ) => is_scalar( $v ) ? (string) $v : '',
		(array) mats_get( $attributes, 'isotypeLabels', array() )
	)
);
$section_id = sanitize_html_class( (string) mats_get( $attributes, 'sectionId', 'applications' ) );
$heading_id = sanitize_html_class( (string) mats_get( $attributes, 'headingId', 'apps-teaser-title' ) );
$source     = (string) mats_get( $attributes, 'ctaSource', 'home-applications' );

if ( ! mats_has( $title ) ) {
	echo mats_preview_empty( 'Aplicaciones (inicio)' ); // phpcs:ignore WordPress.Security.EscapeOutput
	return;
}

/* ids stay unique when the section is placed more than once on a page */
$used_ids = isset( $GLOBALS['mats_used_ids'] ) && is_array( $GLOBALS['mats_used_ids'] ) ? $GLOBALS['mats_used_ids'] : array();
if ( '' === $heading_id || isset( $used_ids[ $heading_id ] ) ) {
	$heading_id = wp_unique_id( 'apps-teaser-title-' );
}
if ( isset( $used_ids[ $section_id ] ) ) {
	$section_id = '';
}
$used_ids[ $heading_id ] = true;
if ( '' !== $section_id ) {
	$used_ids[ $section_id ] = true;
}
$GLOBALS['mats_used_ids'] = $used_ids;

$apps      = mats_get_applications();
$cta_label = mats_link_label( $cta );

/* numbers printed like the original (JavaScript) template: 4.5, 6, 5.833333333333333 */
$num = static function ( $n ): string {
	$n = (float) $n;
	if ( floor( $n ) === $n ) {
		return (string) (int) $n;
	}
	return (string) wp_json_encode( $n );
};

/* dot field inside the capsule — smaller/coral on top, larger/violet below */
$dots = array(
	array( 280, 42, 4 ),
	array( 258, 58, 3.5 ), array( 298, 58, 4.5 ),
	array( 242, 74, 3 ), array( 275, 74, 5 ), array( 315, 76, 3.5 ),
	array( 255, 92, 4.5 ), array( 292, 92, 5.5 ),
	array( 240, 110, 4 ), array( 275, 110, 6 ), array( 310, 112, 5 ),
	array( 258, 130, 5.5 ), array( 292, 130, 6.5 ), array( 320, 132, 4 ),
	array( 242, 150, 5 ), array( 275, 150, 7 ), array( 308, 152, 6 ),
	array( 260, 172, 6.5 ), array( 292, 172, 7.5 ), array( 320, 174, 5.5 ),
	array( 244, 194, 6 ), array( 276, 196, 8.5 ), array( 310, 196, 7 ),
	array( 262, 218, 8 ), array( 294, 220, 9 ), array( 322, 220, 6.5 ),
	array( 246, 244, 7.5 ), array( 278, 246, 10 ), array( 310, 246, 8.5 ),
	array( 264, 272, 9.5 ), array( 294, 274, 11 ),
);

/* colour by height: coral → magenta → violet */
$dot_color = static function ( $cy ): string {
	$t     = min( 1, max( 0, ( $cy - 40 ) / 240 ) );
	$stops = array( array( 240, 83, 47 ), array( 206, 66, 98 ), array( 108, 18, 248 ) );
	$pair  = $t < 0.5 ? array( $stops[0], $stops[1] ) : array( $stops[1], $stops[2] );
	$k     = $t < 0.5 ? $t * 2 : ( $t - 0.5 ) * 2;
	$rgb   = array();
	foreach ( array( 0, 1, 2 ) as $c ) {
		$rgb[] = (int) floor( $pair[0][ $c ] + ( $pair[1][ $c ] - $pair[0][ $c ] ) * $k + 0.5 ); // Math.round
	}
	return 'rgb(' . implode( ',', $rgb ) . ')';
};

/* a deterministic per-dot drift so the field breathes without looking random */
$drift = static function ( int $i ) use ( $num ): string {
	return sprintf(
		'--delay: %ss; --dur: %ss; --amp: %spx',
		$num( ( ( $i * 37 ) % 23 ) / 10 ),
		$num( 4.5 + ( ( $i * 13 ) % 9 ) / 3 ),
		$num( 1.5 + ( ( $i * 7 ) % 5 ) * 0.5 )
	);
};

/* callouts: target dot, ring radius, side, label (index in $labels), application slug */
$callouts = array(
	array( 298, 58, 10, 'r', 0, 'organic-electronics' ),
	array( 308, 152, 12, 'r', 1, 'sensors' ),
	array( 310, 246, 14, 'r', 3, 'coatings' ),
	array( 242, 150, 11, 'l', 2, 'solar-cells' ),
	array( 264, 272, 15, 'l', 4, 'spm-sample-preparation' ),
);

/* one gradient id per figure, so the section can appear more than once */
$grad_id = wp_unique_id( 'iso-cap-' );

/* the editor shows the drawn state (nothing scrolls into view there) */
$figure_class = 'isotype reveal' . ( mats_is_preview() ? ' is-visible' : '' );
?>
<section class="sec hairtop mist apps-teaser"<?php echo '' !== $section_id ? ' id="' . esc_attr( $section_id ) . '"' : ''; ?> aria-labelledby="<?php echo esc_attr( $heading_id ); ?>">
	<div class="container">
		<div class="reveal">
			<?php if ( mats_has( $kicker ) ) : ?>
				<p class="eyebrow"<?php echo mats_edit( 'kicker' ); ?>><?php echo mats_text( $kicker ); ?></p>
			<?php endif; ?>
			<h2 class="h2" id="<?php echo esc_attr( $heading_id ); ?>"<?php echo mats_edit( 'title' ); ?>><?php echo mats_text( $title ); ?></h2>
			<?php if ( mats_has( $lead ) ) : ?>
				<p class="lead"<?php echo mats_edit( 'lead' ); ?>><?php echo mats_text( $lead ); ?></p>
			<?php endif; ?>
		</div>

		<figure class="<?php echo esc_attr( $figure_class ); ?>" data-isotype>
			<svg viewBox="0 0 600 330"<?php echo mats_has( $alt ) ? ' role="img" aria-label="' . esc_attr( $alt ) . '"' : ' aria-hidden="true"'; ?> class="isotype__svg">
				<defs>
					<linearGradient id="<?php echo esc_attr( $grad_id ); ?>" x1="0" y1="0" x2="0" y2="1">
						<stop offset="0" stop-color="#F0532F" />
						<stop offset="0.5" stop-color="#CE4262" />
						<stop offset="0.75" stop-color="#A72F9E" />
						<stop offset="1" stop-color="#6C12F8" />
					</linearGradient>
				</defs>

				<!-- capsule: draws itself on reveal -->
				<rect class="iso__capsule" x="215" y="12" width="130" height="306" rx="65" fill="none" stroke="url(#<?php echo esc_attr( $grad_id ); ?>)" stroke-width="6" pathLength="1" />

				<!-- dot field: each dot drifts on its own rhythm -->
				<?php foreach ( $dots as $i => $dot ) : ?>
					<circle class="iso__dot" cx="<?php echo esc_attr( $num( $dot[0] ) ); ?>" cy="<?php echo esc_attr( $num( $dot[1] ) ); ?>" r="<?php echo esc_attr( $num( $dot[2] ) ); ?>" fill="<?php echo esc_attr( $dot_color( $dot[1] ) ); ?>" style="<?php echo esc_attr( $drift( $i ) ); ?>" />
				<?php endforeach; ?>

				<!-- callouts: line draws, ring scales in, label settles; pulses in turn -->
				<?php
				foreach ( $callouts as $i => $c ) :
					list( $x, $y, $r, $side, $label_index, $field ) = $c;
					$edge_x = 'r' === $side ? $x + $r : $x - $r;
					$end_x  = 'r' === $side ? 420 : 140;
					$text_x = 'r' === $side ? 428 : 132;
					$label  = $labels[ $label_index ] ?? '';
					?>
					<g class="iso__callout" data-field="<?php echo esc_attr( $field ); ?>" style="--i: <?php echo (int) $i; ?>">
						<circle class="iso__pulse" cx="<?php echo (int) $x; ?>" cy="<?php echo (int) $y; ?>" r="<?php echo (int) $r; ?>" fill="none" stroke="#CE4262" stroke-width="1.5" />
						<circle class="iso__ring" cx="<?php echo (int) $x; ?>" cy="<?php echo (int) $y; ?>" r="<?php echo (int) $r; ?>" fill="none" stroke="#26241F" stroke-width="1.5" />
						<?php if ( mats_has( $label ) ) : ?>
							<line class="iso__line" x1="<?php echo (int) $edge_x; ?>" y1="<?php echo (int) $y; ?>" x2="<?php echo (int) $end_x; ?>" y2="<?php echo (int) $y; ?>" stroke="#57534A" stroke-width="1.2" pathLength="1" />
							<circle class="iso__end" cx="<?php echo (int) $end_x; ?>" cy="<?php echo (int) $y; ?>" r="2" fill="#26241F" />
							<text class="iso__label" x="<?php echo (int) $text_x; ?>" y="<?php echo esc_attr( $num( $y + 4.5 ) ); ?>" font-family="Manrope, sans-serif" font-size="15" font-weight="600" fill="#26241F" text-anchor="<?php echo 'r' === $side ? 'start' : 'end'; ?>"><?php echo mats_text( $label ); ?></text>
						<?php endif; ?>
					</g>
				<?php endforeach; ?>
			</svg>
			<?php if ( mats_has( $caption ) ) : ?>
				<figcaption class="isotype__caption"<?php echo mats_edit( 'isotypeCaption' ); ?>><?php echo mats_text( $caption ); ?></figcaption>
			<?php endif; ?>
		</figure>

		<?php if ( $apps ) : ?>
			<ul class="apps__links" role="list" data-apps-links>
				<?php foreach ( array_values( $apps ) as $i => $app ) : ?>
					<li class="reveal" style="--rd: <?php echo (int) ( $i * 70 ); ?>ms">
						<a class="apps__chip" <?php echo mats_link_attrs( array( 'page' => 'applications', 'hash' => '#' . $app['slug'] ) ); ?> data-field="<?php echo esc_attr( $app['slug'] ); ?>">
							<?php echo mats_text( $app['title'] ); ?>
							<span aria-hidden="true">→</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php echo mats_preview_note( 'Los accesos bajo el isotipo salen automáticamente de las aplicaciones publicadas (título y orden).', 'edit.php?post_type=mats_application', 'Editar aplicaciones' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>

		<?php if ( mats_has( $cta_label ) ) : ?>
			<div class="apps__cta reveal">
				<a class="btn" <?php echo mats_link_attrs( $cta ); ?><?php echo $source ? ' data-cta-source="' . esc_attr( $source ) . '"' : ''; ?><?php echo mats_edit( 'cta.label' ); ?>><?php echo mats_text( $cta_label ); ?></a>
			</div>
		<?php endif; ?>
	</div>
</section>
