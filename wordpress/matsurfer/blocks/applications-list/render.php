<?php
/**
 * Applications page list (src/pages/[...lang]/applications.astro): the
 * sticky in-page nav (one pill per application, the one in view marked
 * aria-current by view.js) and one horizontal block per published
 * application — need → with MatSurfer → benefit — each with its anchor
 * (#<slug>) and a contextual contact CTA
 * (/contact?interest=application&application=<slug>).
 *   <nav class="apps-nav" data-apps-nav> + <section class="sec hairtop">
 *
 * The applications come from the Aplicaciones records (mats_get_applications());
 * this block only holds the fixed labels. The extra root class .apps-list
 * on the section only scopes this block's styles.
 *
 * @package matsurfer
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$label_section   = (string) mats_get( $attributes, 'labelSection' );
$label_challenge = (string) mats_get( $attributes, 'labelChallenge' );
$label_use       = (string) mats_get( $attributes, 'labelUse' );
$label_benefit   = (string) mats_get( $attributes, 'labelBenefit' );
$label_cta       = (string) mats_get( $attributes, 'labelCta' );

$apps = array_values( mats_get_applications() );

if ( ! $apps ) {
	/* nothing to list: no empty sticky bar on the public site */
	echo mats_preview_note( 'Esta sección no se muestra en la web porque no hay ninguna aplicación publicada.', 'edit.php?post_type=mats_application', 'Editar aplicaciones' ); // phpcs:ignore WordPress.Security.EscapeOutput
	return;
}

/* anchors stay unique when the list is placed more than once on a page */
$used_ids = isset( $GLOBALS['mats_used_ids'] ) && is_array( $GLOBALS['mats_used_ids'] ) ? $GLOBALS['mats_used_ids'] : array();
foreach ( $apps as $i => $app ) {
	$anchor = '' !== $app['slug'] ? $app['slug'] : 'application-' . ( $i + 1 );
	$base   = $anchor;
	$n      = 2;
	while ( isset( $used_ids[ $anchor ] ) ) {
		$anchor = $base . '-' . $n;
		++$n;
	}
	$used_ids[ $anchor ] = true;
	$apps[ $i ]['anchor'] = $anchor;
}
$GLOBALS['mats_used_ids'] = $used_ids;

/* the editor shows the drawn state of the edge layer (nothing scrolls into view there) */
$card_class = 'panel app reveal' . ( mats_is_preview() ? ' is-visible' : '' );
?>
<nav class="apps-nav"<?php echo mats_has( $label_section ) ? ' aria-label="' . esc_attr( $label_section ) . '"' : ''; ?> data-apps-nav>
	<div class="container apps-nav__inner">
		<?php foreach ( $apps as $app ) : ?>
			<a class="apps-nav__pill" href="#<?php echo esc_attr( $app['anchor'] ); ?>" data-target="<?php echo esc_attr( $app['anchor'] ); ?>"><?php echo mats_text( $app['title'] ); ?></a>
		<?php endforeach; ?>
	</div>
</nav>

<section class="sec hairtop apps-list"<?php echo mats_has( $label_section ) ? ' aria-label="' . esc_attr( $label_section ) . '"' : ''; ?>>
	<?php echo mats_preview_note( 'Las fichas salen automáticamente de las aplicaciones publicadas (títulos, textos y orden). Aquí solo se editan los rótulos y el texto del botón.', 'edit.php?post_type=mats_application', 'Editar aplicaciones' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<div class="container apps">
		<?php foreach ( $apps as $app ) : ?>
			<?php
			$cta = array(
				'label' => $label_cta,
				'page'  => 'contact',
				'url'   => '',
				'query' => '?interest=application&application=' . rawurlencode( $app['slug'] ),
				'hash'  => '',
			);
			?>
			<article class="<?php echo esc_attr( $card_class ); ?>" id="<?php echo esc_attr( $app['anchor'] ); ?>" aria-labelledby="app-<?php echo esc_attr( $app['anchor'] ); ?>">
				<header class="app__head">
					<?php if ( mats_has( $app['tag'] ) ) : ?>
						<span class="app__tag"><?php echo mats_text( $app['tag'] ); ?></span>
					<?php endif; ?>
					<h2 id="app-<?php echo esc_attr( $app['anchor'] ); ?>"><?php echo mats_text( $app['title'] ); ?></h2>
				</header>
				<div class="app__cols">
					<div>
						<h3 class="app__label"<?php echo mats_edit( 'labelChallenge' ); ?>><?php echo mats_text( $label_challenge ); ?></h3>
						<p><?php echo mats_text( $app['challenge'] ); ?></p>
					</div>
					<div>
						<h3 class="app__label"<?php echo mats_edit( 'labelUse' ); ?>><?php echo mats_text( $label_use ); ?></h3>
						<p><?php echo mats_text( $app['use'] ); ?></p>
					</div>
					<div>
						<h3 class="app__label"<?php echo mats_edit( 'labelBenefit' ); ?>><?php echo mats_text( $label_benefit ); ?></h3>
						<p><?php echo mats_text( $app['benefit'] ); ?></p>
					</div>
				</div>
				<?php if ( mats_has( $label_cta ) ) : ?>
					<a class="btn btn--ghost btn--sm app__cta" <?php echo mats_link_attrs( $cta ); ?> data-cta-source="<?php echo esc_attr( 'applications-' . $app['slug'] ); ?>" aria-label="<?php echo esc_attr( $label_cta . ' — ' . $app['title'] ); ?>"<?php echo mats_edit( 'labelCta' ); ?>><?php echo mats_text( $label_cta ); ?></a>
				<?php endif; ?>
			</article>
		<?php endforeach; ?>
	</div>
</section>
