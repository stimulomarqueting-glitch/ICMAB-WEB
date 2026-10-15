<?php
/**
 * "Who it's for" (Audiences.astro, home): four audiences as expanding
 * panels with the client's brand line icons (assets/icons/brand).
 *   <section class="sec hairtop" id="who-is-it-for" aria-labelledby="audiences-title">
 * On desktop one panel is active at a time — it grows, turns plum, shows
 * its description and carries a vapour spotlight that follows the pointer
 * — while the others rest as white tiles with icon and title. The active
 * panel cycles slowly until the visitor hovers, focuses or taps one
 * (view.js). Below 64rem every panel is open in a plain grid; reduced
 * motion gets the open grid with no cycling.
 * The extra root class .audiences only scopes this block's styles.
 *
 * @package matsurfer
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$kicker     = (string) mats_get( $attributes, 'kicker' );
$title      = (string) mats_get( $attributes, 'title' );
$section_id = sanitize_html_class( (string) mats_get( $attributes, 'sectionId', 'who-is-it-for' ) );
$heading_id = sanitize_html_class( (string) mats_get( $attributes, 'headingId', 'audiences-title' ) );

/* the client's brand line icons, keyed by audience */
$icons = array(
	'surface'    => 'academic-groups',
	'lab'        => 'new-to-surfaces',
	'insitu'     => 'corporate-rnd',
	'modularity' => 'equipment-partners',
);

/* keys kept: they are the attribute indexes used by in-place editing */
$groups = array();
foreach ( (array) mats_get( $attributes, 'groups', array() ) as $index => $item ) {
	$item = is_object( $item ) ? (array) $item : $item;
	if ( ! is_array( $item ) ) {
		continue;
	}
	$g_title = is_scalar( $item['title'] ?? '' ) ? (string) ( $item['title'] ?? '' ) : '';
	$g_text  = is_scalar( $item['text'] ?? '' ) ? (string) ( $item['text'] ?? '' ) : '';
	if ( ! mats_has( $g_title ) && ! mats_has( $g_text ) ) {
		continue;
	}
	$icon             = is_scalar( $item['icon'] ?? '' ) ? (string) ( $item['icon'] ?? '' ) : '';
	$groups[ $index ] = array(
		'icon'  => $icons[ $icon ] ?? $icons['lab'],
		'title' => $g_title,
		'text'  => $g_text,
	);
}

if ( ! mats_has( $title ) && ! $groups ) {
	echo mats_preview_empty( 'Para quién es' ); // phpcs:ignore WordPress.Security.EscapeOutput
	return;
}

/* ids stay unique when the section is placed more than once on a page:
   the first one keeps the original ids (aud-0…), later ones get a suffix */
$used_ids = isset( $GLOBALS['mats_used_ids'] ) && is_array( $GLOBALS['mats_used_ids'] ) ? $GLOBALS['mats_used_ids'] : array();
if ( '' === $heading_id || isset( $used_ids[ $heading_id ] ) ) {
	$heading_id = wp_unique_id( 'audiences-title-' );
}
if ( isset( $used_ids[ $section_id ] ) ) {
	$section_id = '';
}
$panel_prefix = isset( $used_ids['aud-0'] ) ? wp_unique_id( 'aud' ) . '-' : 'aud-';
$used_ids[ $heading_id ]         = true;
$used_ids[ $panel_prefix . '0' ] = true;
if ( '' !== $section_id ) {
	$used_ids[ $section_id ] = true;
}
$GLOBALS['mats_used_ids'] = $used_ids;

$position = 0;
?>
<section class="sec hairtop audiences"<?php echo '' !== $section_id ? ' id="' . esc_attr( $section_id ) . '"' : ''; ?><?php echo mats_has( $title ) ? ' aria-labelledby="' . esc_attr( $heading_id ) . '"' : ''; ?>>
	<div class="container">
		<div class="reveal">
			<?php if ( mats_has( $kicker ) ) : ?>
				<p class="eyebrow"<?php echo mats_edit( 'kicker' ); ?>><?php echo mats_text( $kicker ); ?></p>
			<?php endif; ?>
			<?php if ( mats_has( $title ) ) : ?>
				<h2 class="h2" id="<?php echo esc_attr( $heading_id ); ?>"<?php echo mats_edit( 'title' ); ?>><?php echo mats_text( $title ); ?></h2>
			<?php endif; ?>
		</div>

		<?php if ( $groups ) : ?>
			<div class="aud reveal" data-audiences data-interval="3800">
				<?php
				foreach ( $groups as $index => $g ) :
					$base     = 'groups.' . $index . '.';
					$panel_id = $panel_prefix . $position;
					?>
					<article class="aud__panel" data-index="<?php echo (int) $position; ?>"<?php echo 0 === $position ? ' data-active' : ''; ?> tabindex="0"<?php echo mats_has( $g['title'] ) ? ' aria-labelledby="' . esc_attr( $panel_id ) . '"' : ''; ?>>
						<span class="aud__spot" aria-hidden="true"></span>
						<span class="aud__icon" aria-hidden="true"><?php echo mats_icon( $g['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- the theme's own SVG file ?></span>
						<?php if ( mats_has( $g['title'] ) ) : ?>
							<h3 class="aud__title" id="<?php echo esc_attr( $panel_id ); ?>"<?php echo mats_edit( $base . 'title' ); ?>><?php echo mats_text( $g['title'] ); ?></h3>
						<?php endif; ?>
						<?php if ( mats_has( $g['text'] ) ) : ?>
							<div class="aud__more">
								<p<?php echo mats_edit( $base . 'text' ); ?>><?php echo mats_text( $g['text'] ); ?></p>
							</div>
						<?php endif; ?>
						<span class="aud__layer" aria-hidden="true"></span>
					</article>
					<?php
					++$position;
				endforeach;
				?>
			</div>
		<?php endif; ?>
	</div>
</section>
