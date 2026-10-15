<?php
/**
 * Fallback template (the blog list lives in home.php).
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="sec">
	<div class="container">
		<?php
		while ( have_posts() ) :
			the_post();
			the_title( '<h2 class="h2"><a href="' . esc_url( get_permalink() ) . '">', '</a></h2>' );
		endwhile;
		?>
	</div>
</main>
<?php
get_footer();
