<?php
/**
 * How it works (product page, #how-it-works): section header
 * (SectionHeader.astro: eyebrow + h2), the device video behind a branded
 * facade (isotype + play button, removed on first play — view.js), and the
 * four process cards. Without a video it shows the "coming soon"
 * placeholder figure, like the Astro page does when the file is missing.
 * Port of the section in src/pages/[...lang]/product.astro:
 *   <section class="sec hairtop" id="how-it-works" aria-labelledby="how-title">
 * The extra root class .tech only scopes this block's styles.
 *
 * @package matsurfer
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$kicker      = (string) mats_get( $attributes, 'kicker' );
$title       = (string) mats_get( $attributes, 'title' );
$video       = (array) mats_get( $attributes, 'video', array() );
$caption     = (string) mats_get( $attributes, 'caption' );
$play_label  = (string) mats_get( $attributes, 'playLabel' );
$placeholder = (string) mats_get( $attributes, 'placeholder' );
$raw_steps   = (array) mats_get( $attributes, 'steps', array() );
$section_id  = sanitize_html_class( (string) mats_get( $attributes, 'sectionId', 'how-it-works' ) );
$heading_id  = sanitize_html_class( (string) mats_get( $attributes, 'headingId', 'how-title' ) );

/* the video file: the Media Library address wins over the stored one */
$video_id  = absint( $video['id'] ?? 0 );
$video_url = $video_id ? (string) wp_get_attachment_url( $video_id ) : '';
if ( '' === $video_url ) {
	$video_url = (string) ( $video['url'] ?? '' );
}
$has_video = '' !== trim( $video_url );

/* steps with something to show, keeping their index for the edit paths */
$steps = array();
foreach ( $raw_steps as $i => $step ) {
	$step    = is_array( $step ) ? $step : array();
	$s_title = (string) ( $step['title'] ?? '' );
	$s_text  = (string) ( $step['text'] ?? '' );
	if ( mats_has( $s_title ) || mats_has( $s_text ) ) {
		$steps[ $i ] = array(
			'title' => $s_title,
			'text'  => $s_text,
		);
	}
}

if ( ! mats_has( $title ) && ! $steps && ! $has_video ) {
	echo mats_preview_empty( 'Cómo funciona' ); // phpcs:ignore WordPress.Security.EscapeOutput
	return;
}

/* ids stay unique when the section is placed more than once on a page */
$used_ids  = isset( $GLOBALS['mats_used_ids'] ) && is_array( $GLOBALS['mats_used_ids'] ) ? $GLOBALS['mats_used_ids'] : array();
$poster_id = 'tech-video-poster';
if ( '' === $heading_id || isset( $used_ids[ $heading_id ] ) ) {
	$heading_id = wp_unique_id( 'how-title-' );
}
if ( isset( $used_ids[ $section_id ] ) ) {
	$section_id = '';
}
if ( $has_video && isset( $used_ids[ $poster_id ] ) ) {
	$poster_id = wp_unique_id( 'tech-video-poster-' );
}
$used_ids[ $heading_id ] = true;
if ( $has_video ) {
	$used_ids[ $poster_id ] = true;
}
if ( '' !== $section_id ) {
	$used_ids[ $section_id ] = true;
}
$GLOBALS['mats_used_ids'] = $used_ids;

/* accessible name of the play button: playLabel, else the caption, else
   the title, else a fixed UI string (its contents are all aria-hidden, so
   the name must never be empty) */
$poster_label = '';
foreach ( array( $play_label, $caption, $title ) as $candidate ) {
	if ( mats_has( $candidate ) ) {
		$poster_label = trim( wp_strip_all_tags( $candidate ) );
		if ( '' !== $poster_label ) {
			break;
		}
	}
}
if ( '' === $poster_label ) {
	$poster_label = (string) mats_pick(
		array(
			'en' => 'Play video',
			'es' => 'Reproducir vídeo',
		)
	);
}
?>
<section class="sec hairtop tech"<?php echo '' !== $section_id ? ' id="' . esc_attr( $section_id ) . '"' : ''; ?><?php echo mats_has( $title ) ? ' aria-labelledby="' . esc_attr( $heading_id ) . '"' : ''; ?>>
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

		<figure class="tech__media reveal">
			<?php if ( $has_video ) : ?>
				<div class="tech__videobox" data-tech-video>
					<video class="tech__video" controls preload="none" src="<?php echo esc_url( $video_url ); ?>" playsinline></video>
					<?php /* branded facade: capsule logo + play button; removed on first play */ ?>
					<button class="tech__poster" type="button" id="<?php echo esc_attr( $poster_id ); ?>" aria-label="<?php echo esc_attr( $poster_label ); ?>">
						<?php echo mats_brand_mark( 120 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<span class="tech__play" aria-hidden="true">
							<svg viewBox="0 0 24 24" fill="currentColor"><path d="M8.5 6.2v11.6c0 .8.9 1.3 1.6.9l9.2-5.8c.6-.4.6-1.4 0-1.8L10.1 5.3c-.7-.4-1.6.1-1.6.9Z"></path></svg>
						</span>
					</button>
				</div>
			<?php elseif ( mats_has( $placeholder ) ) : ?>
				<div class="tech__video-ph" role="img" aria-label="<?php echo esc_attr( $placeholder ); ?>">
					<span class="tech__video-badge"<?php echo mats_edit( 'placeholder' ); ?>><?php echo mats_text( $placeholder ); ?></span>
				</div>
			<?php else : ?>
				<div class="tech__video-ph" aria-hidden="true"></div>
			<?php endif; ?>
			<?php if ( mats_has( $caption ) ) : ?>
				<figcaption class="tech__media-cap"<?php echo mats_edit( 'caption' ); ?>><?php echo mats_text( $caption ); ?></figcaption>
			<?php endif; ?>
		</figure>

		<?php if ( $steps ) : ?>
			<ol class="tech__steps" role="list">
				<?php
				$n = 0;
				foreach ( $steps as $i => $step ) :
					++$n;
					?>
					<li class="tech__step reveal">
						<span class="tech__sn" aria-hidden="true"><?php echo esc_html( mats_pad( $n ) ); ?></span>
						<?php if ( mats_has( $step['title'] ) ) : ?>
							<h3<?php echo mats_edit( 'steps.' . $i . '.title' ); ?>><?php echo mats_text( $step['title'] ); ?></h3>
						<?php endif; ?>
						<?php if ( mats_has( $step['text'] ) ) : ?>
							<p<?php echo mats_edit( 'steps.' . $i . '.text' ); ?>><?php echo mats_text( $step['text'] ); ?></p>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>
	</div>
</section>
