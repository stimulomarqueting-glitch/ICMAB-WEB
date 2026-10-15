<?php
/**
 * "You may be looking for": chips to the main pages (labels from Ajustes
 * de la web → menu), shown on the 404 page and on empty searches.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

$mats_roles = array( 'product', 'applications', 'research', 'team' );
if ( mats_has_news() ) {
	$mats_roles[] = 'news';
}
$mats_roles[] = 'contact';
$mats_links   = array();
foreach ( $mats_roles as $mats_role ) {
	$mats_label = trim( (string) mats_setting( 'nav_' . $mats_role ) );
	if ( '' !== $mats_label && mats_page_id( $mats_role ) ) {
		$mats_links[] = array( $mats_label, mats_page_url( $mats_role ) );
	}
}
if ( ! $mats_links ) {
	return;
}
?>
<nav class="blog-links reveal" aria-label="<?php echo esc_attr( mats_t( 'useful_links' ) ); ?>">
	<p class="eyebrow"><?php echo esc_html( mats_t( 'useful_links' ) ); ?></p>
	<ul class="blog-links__list" role="list">
		<?php foreach ( $mats_links as $mats_link ) : ?>
			<li><a class="blog-links__chip" href="<?php echo esc_url( $mats_link[1] ); ?>"><?php echo esc_html( $mats_link[0] ); ?> <span aria-hidden="true">→</span></a></li>
		<?php endforeach; ?>
	</ul>
</nav>
