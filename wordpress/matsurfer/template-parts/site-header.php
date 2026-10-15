<?php
/**
 * Site header (port of src/components/Header.astro, same markup and
 * classes): logo → home, main navigation with the current page marked,
 * EN/ES switcher, global CTA, burger menu under 64rem (behaviour in
 * assets/js/site.js, styles in assets/css/chrome.css).
 *
 * Labels come from Ajustes de la web (per language). Links are page roles
 * resolved to the page of the language being viewed; an item whose page
 * does not exist (or is not published) in that language is left out, so
 * the menu never sends anyone to the wrong place. "News" appears between
 * Team and Contact only when there are published news in that language.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

$mats_nav_roles = array( 'product', 'applications', 'research', 'team' );
if ( function_exists( 'mats_has_news' ) && mats_has_news() ) {
	$mats_nav_roles[] = 'news';
}
$mats_nav_roles[] = 'contact';

$mats_nav = array();
foreach ( $mats_nav_roles as $mats_role ) {
	$mats_label = trim( (string) mats_setting( 'nav_' . $mats_role ) );
	$mats_id    = mats_page_id( $mats_role );
	if ( ! $mats_id && 'news' === $mats_role && function_exists( 'mats_blog_news_page' ) ) {
		$mats_news = mats_blog_news_page(); // Ajustes → Lectura → Página de entradas
		$mats_id   = $mats_news ? (int) $mats_news->ID : 0;
	}
	if ( '' === $mats_label || ! $mats_id ) {
		continue;
	}
	$mats_nav[] = array(
		'label'   => $mats_label,
		'url'     => (string) get_permalink( $mats_id ),
		'current' => mats_is_current_role( $mats_role ),
	);
}

/* the global call to action: the contact page, or the contact email while that page does not exist */
$mats_cta_label = (string) mats_setting( 'header_cta' );
$mats_cta_url   = mats_page_id( 'contact' ) ? mats_page_url( 'contact' ) : 'mailto:' . mats_contact_email();
$mats_langs     = mats_language_links();
?>
<header class="header">
	<div class="container header__inner">
		<a class="header__logo" href="<?php echo esc_url( mats_home_url() ); ?>" aria-label="<?php echo esc_attr( 'MatSurfer — ' . mats_t( 'home' ) ); ?>">
			<img class="header__logotype" src="<?php echo esc_url( mats_asset( 'brand/logotipo.svg' ) ); ?>" width="168" height="30" alt="MatSurfer" />
		</a>

		<nav class="header__nav" aria-label="<?php echo esc_attr( mats_t( 'main_nav' ) ); ?>">
			<ul id="main-menu" class="header__menu">
				<?php foreach ( $mats_nav as $mats_item ) : ?>
					<li>
						<a href="<?php echo esc_url( $mats_item['url'] ); ?>"<?php echo $mats_item['current'] ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $mats_item['label'] ); ?></a>
					</li>
				<?php endforeach; ?>
				<?php if ( count( $mats_langs ) > 1 ) : ?>
					<li class="header__menu-lang">
						<ul class="lang" aria-label="<?php echo esc_attr( mats_t( 'language' ) ); ?>">
							<?php foreach ( $mats_langs as $mats_l ) : ?>
								<li>
									<a href="<?php echo esc_url( $mats_l['url'] ); ?>" hreflang="<?php echo esc_attr( $mats_l['html'] ); ?>" lang="<?php echo esc_attr( $mats_l['html'] ); ?>" aria-label="<?php echo esc_attr( $mats_l['name'] ); ?>"<?php echo $mats_l['current'] ? ' aria-current="true"' : ''; ?>><?php echo esc_html( $mats_l['label'] ); ?></a>
								</li>
							<?php endforeach; ?>
						</ul>
					</li>
				<?php endif; ?>
				<li class="header__menu-cta">
					<a class="btn" href="<?php echo esc_url( $mats_cta_url ); ?>" data-cta-source="header"><?php echo esc_html( $mats_cta_label ); ?></a>
				</li>
			</ul>
		</nav>

		<a class="btn btn--sm header__cta" href="<?php echo esc_url( $mats_cta_url ); ?>" data-cta-source="header"><?php echo esc_html( $mats_cta_label ); ?></a>

		<button class="header__burger" type="button" aria-expanded="false" aria-controls="main-menu" data-label-open="<?php echo esc_attr( mats_t( 'menu_open' ) ); ?>" data-label-close="<?php echo esc_attr( mats_t( 'menu_close' ) ); ?>" aria-label="<?php echo esc_attr( mats_t( 'menu_open' ) ); ?>">
			<span></span><span></span><span></span>
		</button>
	</div>
</header>
