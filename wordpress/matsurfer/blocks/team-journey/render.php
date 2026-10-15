<?php
/**
 * "From the lab to the bench" (TeamJourney.astro), the section under the
 * team showcase: SectionHeader copy, three stages joined by a vapour line
 * (each with a big gradient numeral and a coloured node), then a panel of
 * figures with a note and a link to the case studies.
 *   <section class="sec hairtop mist--violet" aria-labelledby="journey-title">
 * The extra root class .tjourney only scopes this block's styles.
 *
 * @package matsurfer
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$kicker     = (string) mats_get( $attributes, 'kicker' );
$title      = (string) mats_get( $attributes, 'title' );
$lead       = (string) mats_get( $attributes, 'lead' );
$facts_note = (string) mats_get( $attributes, 'factsNote' );
$facts_link = (array) mats_get( $attributes, 'factsLink', array() );
$link_label = mats_link_label( $facts_link );

/* keys kept: they are the attribute indexes used by in-place editing */
$clean = static function ( $list, array $keys ): array {
	$out = array();
	foreach ( (array) $list as $index => $item ) {
		$item = is_object( $item ) ? (array) $item : $item;
		if ( ! is_array( $item ) ) {
			continue;
		}
		$row = array();
		foreach ( $keys as $key ) {
			$row[ $key ] = is_scalar( $item[ $key ] ?? '' ) ? (string) ( $item[ $key ] ?? '' ) : '';
		}
		if ( ! mats_has( $row ) ) {
			continue;
		}
		$out[ $index ] = $row;
	}
	return $out;
};
$steps = $clean( mats_get( $attributes, 'steps', array() ), array( 'title', 'text' ) );
$facts = $clean( mats_get( $attributes, 'facts', array() ), array( 'value', 'label' ) );

if ( ! mats_has( $title ) && ! $steps && ! $facts ) {
	echo mats_preview_empty( 'Del laboratorio al banco de trabajo' ); // phpcs:ignore WordPress.Security.EscapeOutput
	return;
}

/* ids stay unique when the section is placed more than once on a page */
$heading_id = 'journey-title';
$used_ids   = isset( $GLOBALS['mats_used_ids'] ) && is_array( $GLOBALS['mats_used_ids'] ) ? $GLOBALS['mats_used_ids'] : array();
if ( isset( $used_ids[ $heading_id ] ) ) {
	$heading_id = wp_unique_id( 'journey-title-' );
}
$used_ids[ $heading_id ]  = true;
$GLOBALS['mats_used_ids'] = $used_ids;

$show_link = mats_has( $link_label ) && ( mats_has( $facts_link['page'] ?? '' ) || mats_has( $facts_link['url'] ?? '' ) );
$position  = 0;
?>
<section class="sec hairtop mist--violet tjourney"<?php echo mats_has( $title ) ? ' aria-labelledby="' . esc_attr( $heading_id ) . '"' : ''; ?>>
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

		<?php if ( $steps ) : ?>
			<ol class="journey" role="list">
				<?php
				foreach ( $steps as $index => $step ) :
					$base = 'steps.' . $index . '.';
					?>
					<li class="journey__step reveal" style="--i: <?php echo (int) $position; ?>">
						<span class="journey__num" aria-hidden="true"><?php echo esc_html( mats_pad( $position + 1 ) ); ?></span>
						<span class="journey__node" aria-hidden="true"></span>
						<?php if ( mats_has( $step['title'] ) ) : ?>
							<h3 class="journey__title"<?php echo mats_edit( $base . 'title' ); ?>><?php echo mats_text( $step['title'] ); ?></h3>
						<?php endif; ?>
						<?php if ( mats_has( $step['text'] ) ) : ?>
							<p class="journey__text"<?php echo mats_edit( $base . 'text' ); ?>><?php echo mats_text( $step['text'] ); ?></p>
						<?php endif; ?>
					</li>
					<?php
					++$position;
				endforeach;
				?>
			</ol>
		<?php endif; ?>

		<?php if ( $facts || mats_has( $facts_note ) || $show_link ) : ?>
			<div class="panel journey__facts reveal">
				<?php if ( $facts ) : ?>
					<dl class="journey__grid">
						<?php
						foreach ( $facts as $index => $fact ) :
							$base = 'facts.' . $index . '.';
							?>
							<div class="journey__fact">
								<dd class="journey__value"<?php echo mats_edit( $base . 'value' ); ?>><?php echo mats_text( $fact['value'] ); ?></dd>
								<dt class="journey__label"<?php echo mats_edit( $base . 'label' ); ?>><?php echo mats_text( $fact['label'] ); ?></dt>
							</div>
						<?php endforeach; ?>
					</dl>
				<?php endif; ?>
				<?php if ( mats_has( $facts_note ) || $show_link ) : ?>
					<p class="journey__note">
						<?php if ( mats_has( $facts_note ) && mats_is_preview() ) : ?>
							<span<?php echo mats_edit( 'factsNote' ); ?>><?php echo mats_text( $facts_note ); ?></span>
						<?php elseif ( mats_has( $facts_note ) ) : /* plain text on the site, as in Astro; the editor wraps it to edit it in place */ ?>
							<?php echo mats_text( $facts_note ); ?>
						<?php endif; ?>
						<?php if ( $show_link ) : ?>
							<a <?php echo mats_link_attrs( $facts_link ); ?><?php echo mats_edit( 'factsLink.label' ); ?>><?php echo mats_text( $link_label ); ?></a>
						<?php endif; ?>
					</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
