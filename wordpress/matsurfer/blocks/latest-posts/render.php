<?php
/**
 * "Últimas noticias": the newest N news articles of the page's language as
 * cards (template-parts/post-card.php), with a header and a button to the
 * News page. Prints nothing on the public site while there are no
 * articles in that language; the editor shows a note instead.
 *
 * @package matsurfer
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$kicker = (string) mats_get( $attributes, 'kicker' );
$title  = (string) mats_get( $attributes, 'title' );
$count  = (int) mats_get( $attributes, 'count', '3' );
$count  = in_array( $count, array( 3, 6 ), true ) ? $count : 3;
$cta    = (array) mats_get( $attributes, 'cta', array() );
$lang   = mats_lang();

$args = array(
	'post_type'           => 'post',
	'post_status'         => 'publish',
	'posts_per_page'      => $count,
	'ignore_sticky_posts' => true,
	'no_found_rows'       => true,
);
if ( function_exists( 'pll_current_language' ) ) {
	$args['lang'] = $lang;
}
/* never list the article this section is shown on */
if ( is_singular( 'post' ) ) {
	$args['post__not_in'] = array( get_queried_object_id() );
}
$posts = get_posts( $args );

if ( ! $posts ) {
	echo mats_preview_note( 'Esta sección se mostrará sola en la web cuando haya noticias publicadas en este idioma. Ahora mismo no hay ninguna, así que los visitantes no la ven.', 'post-new.php', 'Escribir una noticia' ); // phpcs:ignore WordPress.Security.EscapeOutput
	return;
}

/*
 * Each empty field shows its standard words, in the page language: kicker
 * "News", title "Latest news", button "All news" to the News page. Filling
 * one field never removes the others, and the standard words are editable
 * in place like typed ones (what the editor types replaces them).
 */
if ( ! mats_has( $kicker ) ) {
	$kicker = mats_t( 'news' );
}
if ( ! mats_has( $title ) ) {
	$title = mats_t( 'latest_news' );
}
if ( ! mats_has( $cta['page'] ?? '' ) && ! mats_has( $cta['url'] ?? '' ) ) {
	$cta['page'] = 'news';
	$cta['url']  = '';
}
if ( ! mats_has( $cta['label'] ?? '' ) ) {
	$cta['label'] = 'news' === ( $cta['page'] ?? '' ) ? mats_t( 'all_news' ) : mats_t( 'read_more' );
}
$cta_label  = mats_link_label( $cta );
$heading_id = 'lposts-title-' . wp_unique_id();
?>
<section class="sec hairtop lposts" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>">
	<?php echo mats_preview_note( 'Las noticias se muestran solas, de la más reciente a la más antigua. Para cambiarlas, edita las entradas.', 'edit.php', 'Ir a Entradas' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<div class="container">
		<div class="lposts__head">
			<div class="reveal">
				<p class="eyebrow"<?php echo mats_edit( 'kicker' ); ?>><?php echo mats_text( $kicker ); ?></p>
				<h2 class="h2" id="<?php echo esc_attr( $heading_id ); ?>"<?php echo mats_edit( 'title' ); ?>><?php echo mats_text( $title ); ?></h2>
			</div>
			<?php if ( mats_has( $cta_label ) ) : ?>
				<a class="btn btn--ghost btn--sm lposts__cta reveal" <?php echo mats_link_attrs( $cta ); ?> data-cta-source="latest-news"<?php echo mats_edit( 'cta.label' ); ?>><?php echo mats_text( $cta_label ); ?></a>
			<?php endif; ?>
		</div>
		<div class="lposts__grid lposts__grid--<?php echo (int) $count; ?>">
			<?php
			foreach ( $posts as $item ) {
				mats_blog_card(
					$item,
					array(
						'heading' => 'h3',
						'lazy'    => true,
					)
				);
			}
			?>
		</div>
	</div>
</section>
