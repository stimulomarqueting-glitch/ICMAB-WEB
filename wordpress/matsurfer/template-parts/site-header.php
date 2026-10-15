<?php
/**
 * Site header markup. Labels come from Ajustes de la web (per language).
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

$items = array();
foreach ( array( 'product', 'applications', 'research', 'team', 'contact' ) as $role ) {
	$items[] = array(
		'role'  => $role,
		'label' => (string) mats_setting( 'nav_' . $role ),
	);
}
if ( function_exists( 'mats_has_news' ) && mats_has_news() ) {
	array_splice(
		$items,
		4,
		0,
		array(
			array(
				'role'  => 'news',
				'label' => (string) mats_setting( 'nav_news' ),
			),
		)
	);
}
$cta_label = (string) mats_setting( 'header_cta' );
$cta_url   = mats_page_url( 'contact' );
?>
<header class="header">
	<div class="container header__inner">
		<a class="header__logo" href="<?php echo esc_url( mats_home_url() ); ?>" aria-label="<?php echo esc_attr( 'MatSurfer — ' . mats_t( 'home' ) ); ?>">
			<img class="header__logotype" src="<?php echo esc_url( mats_asset( 'brand/logotipo.svg' ) ); ?>" width="168" height="30" alt="MatSurfer" />
		</a>

		<nav class="header__nav" aria-label="<?php echo esc_attr( mats_t( 'main_nav' ) ); ?>">
			<ul id="main-menu" class="header__menu">
				<?php foreach ( $items as $item ) : ?>
					<?php if ( '' === $item['label'] ) { continue; } ?>
					<li>
						<a href="<?php echo esc_url( mats_page_url( $item['role'] ) ); ?>"<?php echo mats_is_current_role( $item['role'] ) ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $item['label'] ); ?></a>
					</li>
				<?php endforeach; ?>
				<?php $langs = mats_language_links(); ?>
				<?php if ( count( $langs ) > 1 ) : ?>
					<li class="header__menu-lang">
						<ul class="lang" aria-label="<?php echo esc_attr( mats_t( 'language' ) ); ?>">
							<?php foreach ( $langs as $l ) : ?>
								<li>
									<a href="<?php echo esc_url( $l['url'] ); ?>" hreflang="<?php echo esc_attr( $l['html'] ); ?>" lang="<?php echo esc_attr( $l['html'] ); ?>" aria-label="<?php echo esc_attr( $l['name'] ); ?>"<?php echo $l['current'] ? ' aria-current="true"' : ''; ?>><?php echo esc_html( $l['label'] ); ?></a>
								</li>
							<?php endforeach; ?>
						</ul>
					</li>
				<?php endif; ?>
				<li class="header__menu-cta">
					<a class="btn" href="<?php echo esc_url( $cta_url ); ?>" data-cta-source="header"><?php echo esc_html( $cta_label ); ?></a>
				</li>
			</ul>
		</nav>

		<a class="btn btn--sm header__cta" href="<?php echo esc_url( $cta_url ); ?>" data-cta-source="header"><?php echo esc_html( $cta_label ); ?></a>

		<button class="header__burger" type="button" aria-expanded="false" aria-controls="main-menu" data-label-open="<?php echo esc_attr( mats_t( 'menu_open' ) ); ?>" data-label-close="<?php echo esc_attr( mats_t( 'menu_close' ) ); ?>" aria-label="<?php echo esc_attr( mats_t( 'menu_open' ) ); ?>">
			<span></span><span></span><span></span>
		</button>
	</div>
</header>
