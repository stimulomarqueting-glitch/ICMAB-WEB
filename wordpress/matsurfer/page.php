<?php
/**
 * Pages. Landing pages built from MatSurfer sections render edge to edge,
 * exactly as their sections draw them; any other page (legal texts…) uses
 * the reading layout of template-parts/page-prose.php.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	if ( mats_is_landing() ) :
		?>
		<main id="main">
			<?php the_content(); ?>
		</main>
		<?php
	else :
		get_template_part( 'template-parts/page-prose' );
	endif;
endwhile;

get_footer();
