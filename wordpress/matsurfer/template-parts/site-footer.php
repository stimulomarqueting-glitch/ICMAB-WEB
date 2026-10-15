<?php
/**
 * Site footer markup (port of Footer.astro): brand block + three link
 * columns + legal line. Labels come from Ajustes de la web.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

$s       = static fn( string $key ): string => (string) mats_setting( $key );
$columns = array(
	array(
		'title' => $s( 'footer_col1_title' ),
		'links' => array(
			array( $s( 'footer_l_device' ), mats_page_url( 'product' ), 'product' ),
			array( $s( 'footer_l_how' ), mats_page_url( 'product' ) . '#how-it-works', '' ),
			array( $s( 'footer_l_parameters' ), mats_page_url( 'product' ) . '#parameters', '' ),
			array( $s( 'footer_l_faq' ), mats_page_url( 'product' ) . '#faq', '' ),
		),
	),
	array(
		'title' => $s( 'footer_col2_title' ),
		'links' => array_values(
			array_filter(
				array(
					array( $s( 'footer_l_applications' ), mats_page_url( 'applications' ), 'applications' ),
					array( $s( 'footer_l_research' ), mats_page_url( 'research' ), 'research' ),
					array( $s( 'footer_l_team' ), mats_page_url( 'team' ), 'team' ),
					( function_exists( 'mats_has_news' ) && mats_has_news() ) ? array( $s( 'footer_l_news' ), mats_page_url( 'news' ), 'news' ) : null,
					array( $s( 'footer_l_contact' ), mats_page_url( 'contact' ), 'contact' ),
				)
			)
		),
	),
	array(
		'title' => $s( 'footer_col3_title' ),
		'links' => array_values(
			array_filter(
				array(
					mats_page_id( 'privacy' ) ? array( $s( 'footer_l_privacy' ), mats_page_url( 'privacy' ), 'privacy' ) : null,
					mats_page_id( 'legal' ) ? array( $s( 'footer_l_legal' ), mats_page_url( 'legal' ), 'legal' ) : null,
					array( $s( 'footer_l_email' ), 'mailto:' . mats_contact_email(), '' ),
				)
			)
		),
	),
);
?>
<footer class="footer">
	<div class="container">
		<div class="footer__top">
			<div class="footer__brand">
				<a class="footer__logo" href="<?php echo esc_url( mats_home_url() ); ?>">
					<img class="footer__logotype" src="<?php echo esc_url( mats_asset( 'brand/logotipo.svg' ) ); ?>" width="168" height="30" alt="MatSurfer" />
				</a>
				<p class="footer__desc"><?php echo esc_html( $s( 'footer_description' ) ); ?></p>
				<a class="btn btn--sm footer__cta" href="<?php echo esc_url( mats_page_url( 'contact' ) ); ?>" data-cta-source="footer"><?php echo esc_html( $s( 'footer_cta' ) ); ?></a>
			</div>

			<nav class="footer__nav" aria-label="<?php echo esc_attr( mats_t( 'footer_nav' ) ); ?>">
				<?php foreach ( $columns as $col ) : ?>
					<div>
						<span class="eyebrow"><?php echo esc_html( $col['title'] ); ?></span>
						<?php foreach ( $col['links'] as $link ) : ?>
							<?php if ( '' === $link[0] ) { continue; } ?>
							<a href="<?php echo esc_url( $link[1] ); ?>"<?php echo ( $link[2] && mats_is_current_role( $link[2] ) ) ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $link[0] ); ?></a>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
			</nav>
		</div>

		<div class="footer__bottom">
			<span><?php echo esc_html( $s( 'footer_copyright' ) ); ?></span>
			<span class="mono"><?php echo esc_html( $s( 'footer_bottom' ) ); ?></span>
		</div>
	</div>
</footer>
