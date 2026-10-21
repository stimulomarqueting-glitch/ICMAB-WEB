<?php
/**
 * Applications page list (src/pages/[...lang]/applications.astro): the
 * sticky in-page nav (one pill per application, the one in view marked
 * aria-current by view.js) and one horizontal block per published
 * application — need → with MatSurfer → benefit — each with its anchor
 * (#<slug>) and a contextual contact CTA
 * (/contact?interest=application&application=<slug>). Cards whose
 * application has «Enlace a Casos de estudio» ticked also get a text link
 * to the Case Studies page (#validation) next to that button (theme
 * addition, not in the Astro page: .app__foot / .app__case).
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
$label_case      = (string) mats_get( $attributes, 'labelCase' );

/*
 * Link to the validation band of the Case Studies page, on the cards whose
 * «Enlace a Casos de estudio» is ticked (caseStudy). Empty text → the
 * theme's default for the language (then not editable in place: the shown
 * text is not the attribute). No Case Studies page in this language → no link.
 */
$case_text = mats_has( $label_case ) ? $label_case : mats_t( 'case_link' );
$case_edit = mats_has( $label_case ) ? mats_edit( 'labelCase' ) : '';
$case_link = array(
	'label' => $case_text,
	'page'  => 'research',
	'url'   => '',
	'query' => '',
	'hash'  => '#validation',
);
$case_on   = mats_page_id( 'research' ) > 0;
/* editor preview: no href. Its text is edited in place inside the link, and the
   preview's click guard lets clicks in editable text through, so a live link
   would take the editor canvas to the Case Studies page. */
$case_href = mats_is_preview() ? '' : mats_link_attrs( $case_link );

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
	<?php echo mats_preview_note( 'Las fichas salen automáticamente de las aplicaciones publicadas (títulos, textos y orden). Aquí solo se editan los rótulos, el texto del botón y el del enlace a Casos de estudio. Qué fichas llevan ese enlace se decide en cada aplicación («Enlace a Casos de estudio»).', 'edit.php?post_type=mats_application', 'Editar aplicaciones' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
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
				<?php $has_case = $case_on && ! empty( $app['caseStudy'] ); ?>
				<?php if ( mats_has( $label_cta ) || $has_case ) : ?>
					<div class="app__foot">
						<?php if ( mats_has( $label_cta ) ) : ?>
							<a class="btn btn--ghost btn--sm app__cta" <?php echo mats_link_attrs( $cta ); ?> data-cta-source="<?php echo esc_attr( 'applications-' . $app['slug'] ); ?>" aria-label="<?php echo esc_attr( $label_cta . ' — ' . $app['title'] ); ?>"<?php echo mats_edit( 'labelCta' ); ?>><?php echo mats_text( $label_cta ); ?></a>
						<?php endif; ?>
						<?php if ( $has_case ) : ?>
							<a class="app__case" <?php echo $case_href; // phpcs:ignore WordPress.Security.EscapeOutput -- mats_link_attrs() escapes ?> data-case-link="<?php echo esc_attr( $app['slug'] ); ?>"><span class="app__case-icon" aria-hidden="true"><svg viewBox="0 0 20 20" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M10 1.9l2.1 1.5 2.6-.1.8 2.5 2.1 1.5-.8 2.5.8 2.5-2.1 1.5-.8 2.5-2.6-.1L10 18.1l-2.1-1.5-2.6.1-.8-2.5-2.1-1.5.8-2.5-.8-2.5 2.1-1.5.8-2.5 2.6.1L10 1.9Z" /><path d="M6.9 10.2l2.1 2.1 4.1-4.3" /></svg></span><span class="app__case-text"<?php echo $case_edit; // phpcs:ignore WordPress.Security.EscapeOutput -- mats_edit() escapes ?>><?php echo mats_text( $case_text ); ?></span></a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</article>
		<?php endforeach; ?>
	</div>
</section>
