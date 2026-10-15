<?php
/**
 * The News page ("News" / "Noticias", Ajustes → Lectura → Página de
 * entradas). Header = the PageHero section (kicker "News", the page's
 * title, its excerpt as the lead, its featured image when it has one),
 * then the newest article as a wide card and the rest in a 3-column grid,
 * previous / next pagination and the closing CTA band. Polylang limits the
 * list to the language being viewed.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

get_header();

$mats_page  = get_queried_object() instanceof WP_Post ? get_queried_object() : mats_blog_news_page();
$mats_title = $mats_page ? wp_strip_all_tags( get_the_title( $mats_page ) ) : mats_t( 'news' );
$mats_lead  = ( $mats_page && has_excerpt( $mats_page ) ) ? wp_strip_all_tags( get_the_excerpt( $mats_page ) ) : '';
$mats_paged = max( 1, (int) get_query_var( 'paged' ) );
?>
<main id="main" class="blog-main">
	<?php
	echo mats_blog_hero( // phpcs:ignore WordPress.Security.EscapeOutput -- rendered section
		array(
			'kicker'     => mats_t( 'news' ),
			'kickerNote' => $mats_paged > 1 ? sprintf( mats_t( 'page_of' ), $mats_paged, max( $mats_paged, (int) $GLOBALS['wp_query']->max_num_pages ) ) : '',
			'title'      => $mats_title,
			'lead'       => $mats_lead,
			'photo'      => $mats_page ? mats_blog_thumb( $mats_page ) : array(),
		)
	);
	?>

	<section class="sec hairtop blog-list" aria-label="<?php echo esc_attr( $mats_title ); ?>">
		<div class="container">
			<?php if ( have_posts() ) : ?>
				<?php get_template_part( 'template-parts/blog-categories' ); ?>
				<div class="blog-grid">
					<?php
					$mats_i = 0;
					while ( have_posts() ) :
						the_post();
						mats_blog_card(
							get_post(),
							array(
								'heading'  => 'h2',
								'featured' => 1 === $mats_paged && 0 === $mats_i,
								'lazy'     => $mats_i > 1,
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
