<?php
/**
 * Page not found: the PageHero header ("404", friendly title and text, a
 * button home), a search field and links to the main pages.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="blog-main blog-404">
	<?php
	echo mats_blog_hero( // phpcs:ignore WordPress.Security.EscapeOutput -- rendered section
		array(
			'kicker'    => '404',
			'title'     => mats_t( 'not_found' ),
			'lead'      => mats_t( 'not_found_txt' ),
			'cta'       => array(
				'label' => mats_t( 'go_home' ),
				'page'  => 'home',
				'url'   => '',
				'query' => '',
				'hash'  => '',
			),
			'ctaSource' => '404-home',
		)
	);
	?>
	<section class="sec hairtop blog-list" aria-label="<?php echo esc_attr( mats_t( 'search' ) ); ?>">
		<div class="container blog-404__grid">
			<div class="panel blog-404__search reveal">
				<h2 class="blog-404__title"><?php echo esc_html( mats_t( 'search' ) ); ?></h2>
				<p class="blog-404__text"><?php echo esc_html( mats_t( 'search_lead' ) ); ?></p>
				<?php get_search_form(); ?>
			</div>
			<?php get_template_part( 'template-parts/blog-links' ); ?>
		</div>
	</section>
</main>
<?php
get_footer();
