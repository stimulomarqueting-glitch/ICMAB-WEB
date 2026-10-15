<?php
/**
 * Category chips above the news grid: "All news" + every category of the
 * current language that has articles. Hidden when there is only one.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

$mats_cats = get_categories(
	array(
		'hide_empty' => true,
		'orderby'    => 'count',
		'order'      => 'DESC',
		'number'     => 8,
	)
);
if ( count( $mats_cats ) < 2 ) {
	return;
}
$mats_current = is_category() ? (int) get_queried_object_id() : 0;
?>
<nav class="blog-cats" aria-label="<?php echo esc_attr( mats_t( 'category' ) ); ?>">
	<ul class="blog-cats__list" role="list">
		<li><a class="blog-cats__chip" href="<?php echo esc_url( mats_blog_news_url() ); ?>"<?php echo is_home() ? ' aria-current="page"' : ''; ?>><?php echo esc_html( mats_t( 'all_news' ) ); ?></a></li>
		<?php foreach ( $mats_cats as $mats_cat ) : ?>
			<li><a class="blog-cats__chip" href="<?php echo esc_url( get_category_link( $mats_cat ) ); ?>"<?php echo $mats_current === (int) $mats_cat->term_id ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $mats_cat->name ); ?> <span class="blog-cats__count mono"><?php echo (int) $mats_cat->count; ?></span></a></li>
		<?php endforeach; ?>
	</ul>
</nav>
