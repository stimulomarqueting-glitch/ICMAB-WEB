<?php
/**
 * Reading layout for plain pages (legal texts…): title header + prose.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;
?>
<main id="main" class="prose-page">
	<header class="sec prose-page__head">
		<div class="container">
			<?php the_title( '<h1 class="prose-page__title">', '</h1>' ); ?>
		</div>
	</header>
	<div class="sec hairtop">
		<div class="container prose">
			<?php the_content(); ?>
		</div>
	</div>
</main>
