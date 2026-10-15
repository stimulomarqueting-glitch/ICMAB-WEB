<?php
/**
 * News card: 16:10 featured image (or a soft vapour plate with the
 * isotype when there is none), category eyebrow · date, title, excerpt,
 * "Read the article". The whole card is clickable through the title link
 * (one link per card for screen readers and keyboards).
 *
 * Used by home.php, archive.php, search.php and the "Últimas noticias"
 * section (blocks/latest-posts). Styles: blocks/latest-posts/style.css.
 *
 * $args:
 *   post     WP_Post (required)
 *   heading  'h2' | 'h3' (default 'h2')
 *   featured bool — wide horizontal card (newest article on the News page)
 *   lazy     bool — lazy-load the image (default true)
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

$mats_card = $args['post'] ?? null;
if ( ! $mats_card instanceof WP_Post ) {
	return;
}
$mats_heading  = in_array( $args['heading'] ?? 'h2', array( 'h2', 'h3' ), true ) ? $args['heading'] : 'h2';
$mats_featured = ! empty( $args['featured'] );
$mats_lazy     = $args['lazy'] ?? true;
$mats_is_post  = 'post' === $mats_card->post_type;
$mats_link     = (string) get_permalink( $mats_card );
$mats_title    = wp_strip_all_tags( get_the_title( $mats_card ) );
$mats_cat      = $mats_is_post ? mats_blog_category( $mats_card ) : null;
$mats_excerpt  = wp_strip_all_tags( (string) get_the_excerpt( $mats_card ) );
if ( ! $mats_is_post && '' === trim( $mats_excerpt ) ) {
	/* pages built from sections have no text excerpt: use their search-engine description */
	$mats_excerpt = wp_strip_all_tags( (string) get_post_meta( $mats_card->ID, '_mats_seo_description', true ) );
}
$mats_thumb    = mats_blog_thumb( $mats_card );
$mats_img      = $mats_thumb ? mats_image(
	$mats_thumb,
	array(
		'class'   => 'post-card__img',
		'size'    => $mats_featured ? 'mats-wide' : 'mats-card',
		'sizes'   => $mats_featured ? '(max-width: 56rem) calc(100vw - 2rem), 700px' : '(max-width: 40rem) calc(100vw - 2rem), (max-width: 64rem) 45vw, 380px',
		'loading' => $mats_lazy ? 'lazy' : 'eager',
	)
) : '';
$mats_title_id = 'post-card-' . $mats_card->ID . '-' . wp_unique_id();
?>
<article class="panel post-card reveal<?php echo $mats_featured ? ' post-card--featured' : ''; ?><?php echo $mats_img ? '' : ' post-card--noimg'; ?>" aria-labelledby="<?php echo esc_attr( $mats_title_id ); ?>">
	<div class="post-card__media">
		<?php if ( $mats_img ) : ?>
			<?php echo $mats_img; // phpcs:ignore WordPress.Security.EscapeOutput -- built by mats_image() ?>
		<?php else : ?>
			<span class="post-card__placeholder" aria-hidden="true"><?php echo mats_brand_mark( 54, 'post-card__mark' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		<?php endif; ?>
	</div>
	<div class="post-card__body">
		<p class="post-card__meta">
			<?php if ( $mats_cat ) : ?>
				<span class="post-card__cat"><?php echo esc_html( $mats_cat->name ); ?></span>
				<span class="post-card__sep" aria-hidden="true">·</span>
			<?php endif; ?>
			<?php if ( $mats_is_post ) : ?>
				<time datetime="<?php echo esc_attr( get_the_date( 'c', $mats_card ) ); ?>"><?php echo esc_html( mats_blog_date( $mats_card, 'short' ) ); ?></time>
			<?php else : ?>
				<span><?php echo esc_html( mats_t( 'page' ) ); ?></span>
			<?php endif; ?>
		</p>
		<<?php echo esc_html( $mats_heading ); ?> class="post-card__title" id="<?php echo esc_attr( $mats_title_id ); ?>">
			<a class="post-card__link" href="<?php echo esc_url( $mats_link ); ?>"><?php echo esc_html( $mats_title ); ?></a>
		</<?php echo esc_html( $mats_heading ); ?>>
		<?php if ( '' !== trim( $mats_excerpt ) ) : ?>
			<p class="post-card__excerpt"><?php echo esc_html( $mats_excerpt ); ?></p>
		<?php endif; ?>
		<span class="post-card__more" aria-hidden="true"><?php echo esc_html( $mats_is_post ? mats_t( 'read_article' ) : mats_t( 'read_more' ) ); ?> <span class="post-card__arrow">→</span></span>
	</div>
</article>
