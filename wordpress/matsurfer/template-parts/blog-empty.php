<?php
/**
 * Friendly empty state for the news views (no articles yet, nothing found).
 * $args: title, text, search (bool — show the search form), cta (bool —
 * button to the contact page, default false).
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

$mats_empty_title = (string) ( $args['title'] ?? mats_blog_t( 'no_news_title' ) );
$mats_empty_text  = (string) ( $args['text'] ?? mats_blog_t( 'no_news' ) );
$mats_empty_cta   = $args['cta'] ?? false;
$mats_cta_label   = (string) mats_setting( 'header_cta' );
?>
<div class="panel blog-empty reveal">
	<span class="blog-empty__mark" aria-hidden="true"><?php echo mats_brand_mark( 40 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
	<h2 class="blog-empty__title"><?php echo esc_html( $mats_empty_title ); ?></h2>
	<p class="blog-empty__text"><?php echo esc_html( $mats_empty_text ); ?></p>
	<?php if ( ! empty( $args['search'] ) ) : ?>
		<div class="blog-empty__search"><?php get_search_form(); ?></div>
	<?php endif; ?>
	<?php if ( $mats_empty_cta && '' !== $mats_cta_label ) : ?>
		<a class="btn btn--ghost btn--sm blog-empty__cta" href="<?php echo esc_url( mats_page_url( 'contact' ) ); ?>" data-cta-source="news-empty"><?php echo esc_html( $mats_cta_label ); ?></a>
	<?php endif; ?>
</div>
