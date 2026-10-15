<?php
/**
 * Search results (news articles and pages of the language being viewed):
 * header with the query and the number of results, the search form to
 * refine it, result cards and pagination.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

get_header();

global $wp_query;
$mats_query = trim( get_search_query( false ) );
$mats_count = (int) $wp_query->found_posts;
$mats_title = '' !== $mats_query ? mats_t( 'search_for' ) . ' “' . $mats_query . '”' : mats_t( 'search' );
if ( 1 === $mats_count ) {
	$mats_lead = mats_blog_t( 'result_one' );
} else {
	$mats_lead = sprintf( mats_blog_t( 'results_count' ), $mats_count );
}
?>
<main id="main" class="blog-main">
	<?php
	echo mats_blog_hero( // phpcs:ignore WordPress.Security.EscapeOutput -- rendered section
		array(
			'kicker' => mats_t( 'search' ),
			'title'  => $mats_title,
			'lead'   => $mats_lead,
		)
	);
	?>

	<section class="sec hairtop blog-list" aria-label="<?php echo esc_attr( $mats_title ); ?>">
		<div class="container">
			<div class="blog-search">
				<?php get_search_form(); ?>
			</div>

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
				<?php
				get_template_part(
					'template-parts/blog-empty',
					null,
					array(
						'title' => mats_t( 'no_results' ),
						'text'  => mats_blog_t( 'search_lead' ),
					)
				);
				?>
				<?php get_template_part( 'template-parts/blog-links' ); ?>
			<?php endif; ?>
		</div>
	</section>

	<?php echo mats_blog_cta( 'search-final' ); // phpcs:ignore WordPress.Security.EscapeOutput -- rendered section ?>
</main>
<?php
get_footer();
