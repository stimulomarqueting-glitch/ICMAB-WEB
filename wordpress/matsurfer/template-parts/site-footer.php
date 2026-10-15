<?php
/**
 * Site footer (port of src/components/Footer.astro, same markup and
 * classes): brand block + three link columns + legal line. Every text
 * comes from Ajustes de la web (per language); links are page roles
 * resolved to the page of the language being viewed. A link whose page does
 * not exist (or is not published) is left out — e.g. the privacy policy and
 * the legal notice appear once their pages are published — and so is a
 * column left without links. Styles: assets/css/chrome.css.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

$mats_s = static fn( string $key ): string => trim( (string) mats_setting( $key ) );

/* [ label, role (page that must exist), hash, role marked as current ] */
$mats_link = static function ( string $key, string $role, string $hash = '', bool $mark = true ) use ( $mats_s ): ?array {
	$label = $mats_s( $key );
	$id    = mats_page_id( $role );
	if ( ! $id && 'news' === $role && function_exists( 'mats_blog_news_page' ) ) {
		$news = mats_blog_news_page();
		$id   = $news ? (int) $news->ID : 0;
	}
	if ( '' === $label || ! $id ) {
		return null;
	}
	return array(
		'label'   => $label,
		'url'     => (string) get_permalink( $id ) . $hash,
		'current' => $mark && '' === $hash && mats_is_current_role( $role ),
	);
};

$mats_has_news = function_exists( 'mats_has_news' ) && mats_has_news();
$mats_columns  = array(
	array(
		'title' => $mats_s( 'footer_col1_title' ),
		'links' => array(
			$mats_link( 'footer_l_device', 'product' ),
			$mats_link( 'footer_l_how', 'product', '#how-it-works' ),
			$mats_link( 'footer_l_parameters', 'product', '#parameters' ),
			$mats_link( 'footer_l_faq', 'product', '#faq' ),
		),
	),
	array(
		'title' => $mats_s( 'footer_col2_title' ),
		'links' => array(
			$mats_link( 'footer_l_applications', 'applications' ),
			$mats_link( 'footer_l_research', 'research' ),
			$mats_link( 'footer_l_team', 'team' ),
			$mats_has_news ? $mats_link( 'footer_l_news', 'news' ) : null,
			$mats_link( 'footer_l_contact', 'contact' ),
		),
	),
	array(
		'title' => $mats_s( 'footer_col3_title' ),
		'links' => array(
			$mats_link( 'footer_l_privacy', 'privacy' ),
			$mats_link( 'footer_l_legal', 'legal' ),
			'' !== $mats_s( 'footer_l_email' ) ? array(
				'label'   => $mats_s( 'footer_l_email' ),
				'url'     => 'mailto:' . mats_contact_email(),
				'current' => false,
			) : null,
		),
	),
);
foreach ( $mats_columns as $mats_i => $mats_col ) {
	$mats_columns[ $mats_i ]['links'] = array_values( array_filter( $mats_col['links'] ) );
}
$mats_columns = array_values( array_filter( $mats_columns, static fn( $col ) => (bool) $col['links'] ) );

$mats_cta_url = mats_page_id( 'contact' ) ? mats_page_url( 'contact' ) : 'mailto:' . mats_contact_email();
?>
<footer class="footer">
	<div class="container">
		<div class="footer__top">
			<div class="footer__brand">
				<a class="footer__logo" href="<?php echo esc_url( mats_home_url() ); ?>">
					<img class="footer__logotype" src="<?php echo esc_url( mats_asset( 'brand/logotipo.svg' ) ); ?>" width="168" height="30" alt="MatSurfer" />
				</a>
				<p class="footer__desc"><?php echo esc_html( $mats_s( 'footer_description' ) ); ?></p>
				<a class="btn btn--sm footer__cta" href="<?php echo esc_url( $mats_cta_url ); ?>" data-cta-source="footer"><?php echo esc_html( $mats_s( 'footer_cta' ) ); ?></a>
			</div>

			<nav class="footer__nav" aria-label="<?php echo esc_attr( mats_t( 'footer_nav' ) ); ?>">
				<?php foreach ( $mats_columns as $mats_col ) : ?>
					<div>
						<span class="eyebrow"><?php echo esc_html( $mats_col['title'] ); ?></span>
						<?php foreach ( $mats_col['links'] as $mats_item ) : ?>
							<a href="<?php echo esc_url( $mats_item['url'] ); ?>"<?php echo $mats_item['current'] ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $mats_item['label'] ); ?></a>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
			</nav>
		</div>

		<div class="footer__bottom">
			<span><?php echo esc_html( $mats_s( 'footer_copyright' ) ); ?></span>
			<span class="mono"><?php echo esc_html( $mats_s( 'footer_bottom' ) ); ?></span>
		</div>
	</div>
</footer>
