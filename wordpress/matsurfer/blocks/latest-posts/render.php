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

/* a section just added (nothing filled in yet) starts with sensible words */
$fresh = ! mats_has( $kicker ) && ! mats_has( $title ) && ! mats_has( $cta['label'] ?? '' ) && ! mats_has( $cta['page'] ?? '' ) && ! mats_has( $cta['url'] ?? '' );
if ( $fresh ) {
	$kicker = mats_t( 'news' );
	$cta    = array(
		'label' => mats_t( 'all_news' ),
		'page'  => 'news',
	);
}
$own_kicker = mats_has( mats_get( $attributes, 'kicker' ) );
$own_title  = mats_has( mats_get( $attributes, 'title' ) );
$own_cta    = ! $fresh && mats_has( mats_link_label( (array) mats_get( $attributes, 'cta', array() ) ) );
$cta_label  = mats_link_label( $cta );
$heading_id = 'lposts-title-' . wp_unique_id();
if ( '' === trim( $title ) ) {
	$title = mats_t( 'latest_news' );
}
?>
<section class="sec hairtop lposts" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>">
	<?php echo mats_preview_note( 'Las noticias se muestran solas, de la más reciente a la más antigua. Para cambiarlas, edita las entradas.', 'edit.php', 'Ir a Entradas' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<div class="container">
		<div class="lposts__head">
			<div class="reveal">
				<?php if ( mats_has( $kicker ) ) : ?>
					<p class="eyebrow"<?php echo $own_kicker ? mats_edit( 'kicker' ) : ''; ?>><?php echo mats_text( $kicker ); ?></p>
				<?php endif; ?>
				<h2 class="h2" id="<?php echo esc_attr( $heading_id ); ?>"<?php echo $own_title ? mats_edit( 'title' ) : ''; ?>><?php echo mats_text( $title ); ?></h2>
			</div>
			<?php if ( mats_has( $cta_label ) ) : ?>
				<a class="btn btn--ghost btn--sm lposts__cta reveal" <?php echo mats_link_attrs( $cta ); ?> data-cta-source="latest-news"<?php echo $own_cta ? mats_edit( 'cta.label' ) : ''; ?>><?php echo mats_text( $cta_label ); ?></a>
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
