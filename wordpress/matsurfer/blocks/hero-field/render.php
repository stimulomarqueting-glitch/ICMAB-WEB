<?php
/**
 * Home hero, "floating device" variant (HeroField.astro): plum backdrop with
 * a slow gradient mesh, staggered headline, the product photo on a plate
 * that tilts in 3D with the pointer, a vapour plume drawn inside the flask
 * (canvas, view.js), a marquee of application fields and the sublimation
 * strip on the bottom edge.
 *
 * @package matsurfer
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$claim         = (string) mats_get( $attributes, 'claim' );
$headline      = (string) mats_get( $attributes, 'headline' );
$descriptor    = (string) mats_get( $attributes, 'descriptor' );
$lead          = (string) mats_get( $attributes, 'lead' );
$cta_primary   = (array) mats_get( $attributes, 'ctaPrimary', array() );
$cta_secondary = (array) mats_get( $attributes, 'ctaSecondary', array() );
$eyebrow       = (string) mats_get( $attributes, 'eyebrow' );
$photo         = mats_get( $attributes, 'photo', array() );
$photo         = is_object( $photo ) ? (array) $photo : $photo; // block default is an empty object
$vapour        = ! array_key_exists( 'vapour', $attributes ) || ! empty( $attributes['vapour'] );
/* keys kept: they are the attribute indexes used by in-place editing */
$marquee       = array_filter(
	array_map(
		static fn( $v ) => is_scalar( $v ) ? (string) $v : '',
		(array) mats_get( $attributes, 'marquee', array() )
	),
	'mats_has'
);
$source        = (string) mats_get( $attributes, 'ctaSource', 'hero' );
$source_2      = (string) mats_get( $attributes, 'ctaSourceSecondary', 'hero-product' );

if ( ! mats_has( $headline ) ) {
	echo mats_preview_empty( 'Portada (inicio)' ); // phpcs:ignore WordPress.Security.EscapeOutput
	return;
}

/* claim: the last word is emphasised ("Surfaces for <b>all</b>.") */
$claim_lead = '';
$claim_emph = $claim;
$claim_end  = '';
if ( preg_match( '/^(.*\s)(\S+?)(\.?)$/us', $claim, $m ) ) {
	$claim_lead = $m[1];
	$claim_emph = $m[2];
	$claim_end  = $m[3];
}

/* stagger index after the headline words (as in Astro: words.length + n) */
$word_count = count( preg_split( '/\s+/u', trim( $headline ) ) ?: array() );

$primary_label   = mats_link_label( $cta_primary );
$secondary_label = mats_link_label( $cta_secondary );

/* the photo is decorative here (the stage is aria-hidden, as in Astro) */
$photo_html = mats_image(
	$photo,
	array(
		'class'         => 'plate__img',
		'size'          => 'full',
		'sizes'         => '(max-width: 56rem) min(112vw, 38rem), (max-width: 64rem) 64vw, min(54vw, 52rem)',
		'fetchpriority' => 'high',
		'decorative'    => true,
		'width'         => 1100,
		'height'        => 619,
		'edit'          => 'photo',
	)
);
?>
<section class="field" aria-labelledby="hero-title" data-field>
	<div class="field__mesh" aria-hidden="true"></div>

	<div class="container field__grid">
		<div class="field__content">
			<?php if ( mats_has( $claim ) ) : ?>
				<p class="field__claim rise" style="--i: 0"<?php echo mats_edit( 'claim' ); ?>><?php echo mats_text( $claim_lead ); ?><b><?php echo mats_text( $claim_emph ); ?></b><?php echo mats_text( $claim_end ); ?></p>
			<?php endif; ?>
			<h1 class="field__title" id="hero-title"<?php echo mats_edit( 'headline' ); ?>><?php echo mats_words( $headline, '<span class="field__word"><span class="rise" style="--i: %2$d">%1$s</span></span> ', 1 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></h1>
			<?php if ( mats_has( $descriptor ) ) : ?>
				<p class="field__descriptor rise" style="--i: <?php echo (int) ( $word_count + 1 ); ?>"<?php echo mats_edit( 'descriptor' ); ?>><?php echo mats_text( $descriptor ); ?></p>
			<?php endif; ?>
			<?php if ( mats_has( $lead ) ) : ?>
				<p class="field__lead rise" style="--i: <?php echo (int) ( $word_count + 2 ); ?>"<?php echo mats_edit( 'lead' ); ?>><?php echo mats_text( $lead ); ?></p>
			<?php endif; ?>
			<?php if ( mats_has( $primary_label ) || mats_has( $secondary_label ) ) : ?>
				<div class="field__ctas rise" style="--i: <?php echo (int) ( $word_count + 3 ); ?>">
					<?php if ( mats_has( $primary_label ) ) : ?>
						<a class="btn" <?php echo mats_link_attrs( $cta_primary ); ?><?php echo $source ? ' data-cta-source="' . esc_attr( $source ) . '"' : ''; ?><?php echo mats_edit( 'ctaPrimary.label' ); ?>><?php echo mats_text( $primary_label ); ?></a>
					<?php endif; ?>
					<?php if ( mats_has( $secondary_label ) ) : ?>
						<a class="btn btn--glass" <?php echo mats_link_attrs( $cta_secondary ); ?><?php echo $source_2 ? ' data-cta-source="' . esc_attr( $source_2 ) . '"' : ''; ?><?php echo mats_edit( 'ctaSecondary.label' ); ?>><?php echo mats_text( $secondary_label ); ?></a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<?php if ( mats_has( $eyebrow ) ) : ?>
				<p class="eyebrow field__eyebrow rise" style="--i: <?php echo (int) ( $word_count + 4 ); ?>"<?php echo mats_edit( 'eyebrow' ); ?>><?php echo mats_text( $eyebrow ); ?></p>
			<?php endif; ?>
		</div>

		<?php
		/* no photo: no plate on the site (an empty tinted card otherwise); the
		   editor preview gets the click-to-choose placeholder from mats_image() */
		if ( '' !== $photo_html ) :
			?>
			<div class="field__stage" aria-hidden="true">
				<div class="plate" data-plate>
					<div class="plate__float">
						<div class="plate__halo"></div>
						<div class="plate__shadow"></div>
						<div class="plate__card">
							<?php echo $photo_html; // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<div class="plate__tint"></div>
							<?php if ( $vapour ) : /* the plume is mapped onto the photo */ ?>
								<canvas class="plate__vapour" data-vapour></canvas>
							<?php endif; ?>
							<div class="plate__sheen"></div>
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( $marquee ) : ?>
		<div class="field__marquee" aria-hidden="true">
			<ul class="field__track">
				<?php
				/* the list runs twice for a seamless loop; only the first copy is editable */
				foreach ( array( true, false ) as $first ) {
					foreach ( $marquee as $i => $item ) {
						echo '<li' . ( $first ? mats_edit( 'marquee.' . $i ) : '' ) . '>' . mats_text( $item ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput
					}
				}
				?>
			</ul>
		</div>
	<?php endif; ?>
	<div class="field__strip" aria-hidden="true"></div>
</section>
