<?php
/**
 * News archives: category, tag and date (month / year / day). Same header,
 * cards and pagination as the News page.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

get_header();

$mats_kicker = mats_t( 'news' );
$mats_title  = '';
$mats_lead   = '';
if ( is_category() || is_tag() ) {
	$mats_term   = get_queried_object();
	$mats_kicker = is_category() ? mats_t( 'category' ) : mats_t( 'tag' );
	$mats_title  = $mats_term instanceof WP_Term ? $mats_term->name : '';
	$mats_lead   = $mats_term instanceof WP_Term ? wp_strip_all_tags( term_description( $mats_term ) ) : '';
} elseif ( is_date() ) {
	$mats_kicker = mats_t( 'archive' );
	$mats_title  = mats_blog_date_archive_title(); // also the <title> (inc/modules/seo.php)
} else {
	$mats_title = wp_strip_all_tags( get_the_archive_title() );
}
if ( '' === trim( $mats_title ) ) {
	$mats_title = mats_t( 'news' );
}
$mats_paged = max( 1, (int) get_query_var( 'paged' ) );
?>
<main id="main" class="blog-main">
	<?php
	echo mats_blog_hero( // phpcs:ignore WordPress.Security.EscapeOutput -- rendered section
		array(
			'kicker'     => $mats_kicker,
			'kickerNote' => $mats_paged > 1 ? sprintf( mats_t( 'page_of' ), $mats_paged, max( $mats_paged, (int) $GLOBALS['wp_query']->max_num_pages ) ) : '',
			'title'      => $mats_title,
			'lead'       => $mats_lead,
		)
	);
	?>

	<?php /* no aria-label: the header above is already the region named after the title (two regions with one name confuse screen readers) */ ?>
	<section class="sec hairtop blog-list">
		<div class="container">
			<?php if ( is_category() ) : ?>
				<?php get_template_part( 'template-parts/blog-categories' ); ?>
			<?php else : ?>
				<p class="blog-back"><a href="<?php echo esc_url( mats_blog_news_url() ); ?>"><span aria-hidden="true">←</span> <?php echo esc_html( mats_t( 'all_news' ) ); ?></a></p>
			<?php endif; ?>

			<?php if ( have_posts() ) : ?>
				<div class="blog-grid">
					<?php
					$mats_i = 0;
					while ( have_posts() ) :
						the_post();
						mats_blog_card(
							get_post(),
							array(
								'heading' => 'h2',
								'lazy'    => $mats_i > 2,
							)
						);
						++$mats_i;
					endwhile;
					?>
				</div>
				<?php echo mats_blog_pagination(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
			<?php else : ?>
				<?php get_template_part( 'template-parts/blog-empty' ); ?>
			<?php endif; ?>
		</div>
	</section>

	<?php echo mats_blog_cta( 'news-final' ); // phpcs:ignore WordPress.Security.EscapeOutput -- rendered section ?>
</main>
<?php
get_footer();
