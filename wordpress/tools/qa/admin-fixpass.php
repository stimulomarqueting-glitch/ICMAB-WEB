<?php
/**
 * admin module QA (fix pass) — CLI checks of the editor / admin rules.
 *
 *   MATS_WP_PORT=8915 php /home/user/wpsite-cli.php \
 *     /home/user/ICMAB-WEB/wordpress/tools/qa/admin-fixpass.php
 *
 * Prints one line per check (OK / FAIL) and a JSON summary of the ids the
 * Playwright scripts use. Needs admin-setup.php first (editor "editora").
 *
 * @package matsurfer-qa
 */

$mats_qa_fail = 0;
$mats_qa_ok   = static function ( bool $cond, string $label ) use ( &$mats_qa_fail ) {
	echo ( $cond ? 'OK   ' : 'FAIL ' ) . $label . "\n";
	if ( ! $cond ) {
		++$mats_qa_fail;
	}
};

$mats_qa_editor = get_user_by( 'login', 'editora' );
$mats_qa_ok( (bool) $mats_qa_editor, 'editor user exists' );

/* ── theme.json: brand palette and S/M/L/XL sizes reach the editor; no 24px flow margin ── */
$mats_qa_settings = wp_get_global_settings();
$mats_qa_palette  = wp_list_pluck( (array) ( $mats_qa_settings['color']['palette']['theme'] ?? array() ), 'slug' );
$mats_qa_sizes    = wp_list_pluck( (array) ( $mats_qa_settings['typography']['fontSizes']['theme'] ?? array() ), 'size', 'slug' );
$mats_qa_ok( array( 'ink', 'coral', 'violet', 'plum', 'cream' ) === array_values( $mats_qa_palette ), 'brand palette (theme.json) kept: ' . implode( ',', $mats_qa_palette ) );
$mats_qa_ok( array( 'small', 'normal', 'large', 'x-large' ) === array_keys( $mats_qa_sizes ) && '1.0625rem' === ( $mats_qa_sizes['normal'] ?? '' ), 'S/M/L/XL sizes kept: ' . wp_json_encode( $mats_qa_sizes ) );
$mats_qa_ok( false === ( $mats_qa_settings['color']['custom'] ?? null ) && false === ( $mats_qa_settings['typography']['customFontSize'] ?? null ), 'no free colours / font sizes' );
$mats_qa_ok( false === ( $mats_qa_settings['dimensions']['aspectRatio'] ?? null ), 'dimensions.aspectRatio locked by editor.php' );
$mats_qa_ok( ! array_key_exists( 'blockGap', (array) ( $mats_qa_settings['spacing'] ?? array() ) ) || null === $mats_qa_settings['spacing']['blockGap'], 'spacing.blockGap not set' );
$mats_qa_css = wp_get_global_stylesheet();
$mats_qa_ok( false !== strpos( $mats_qa_css, '--wp--preset--color--coral' ), 'stylesheet has --wp--preset--color--coral' );
$mats_qa_ok( false === strpos( $mats_qa_css, 'margin-block-start: 24px' ), 'stylesheet has no 24px flow margin' );

/* ── allowed blocks ── */
$mats_qa_post = get_posts(
	array(
		'post_type'   => 'post',
		'numberposts' => 1,
		'lang'        => '',
	)
);
if ( $mats_qa_post ) {
	$mats_qa_allowed = mats_editor_allowed_blocks( $mats_qa_post[0], false );
	$mats_qa_ok( ! in_array( 'matsurfer/page-cta', $mats_qa_allowed, true ) && in_array( 'core/embed', $mats_qa_allowed, true ), 'news: no page-cta, writing blocks present' );
	$mats_qa_ok( 0 === count( array_filter( $mats_qa_allowed, static fn( $n ) => str_starts_with( $n, 'matsurfer/' ) ) ), 'news: no MatSurfer sections at all' );
}

/* ── lock support: admins keep it, editors do not ── */
$mats_qa_supports = static function ( int $user ) {
	wp_set_current_user( $user );
	$args = apply_filters( 'register_block_type_args', array( 'supports' => array() ), 'matsurfer/section-intro' );
	return $args['supports'];
};
$mats_qa_admin_sup = $mats_qa_supports( 1 );
$mats_qa_ok( true === ( $mats_qa_admin_sup['lock'] ?? null ) && false === $mats_qa_admin_sup['html'] && false === $mats_qa_admin_sup['className'], 'admins: sections keep lock support (no html / className)' );
if ( $mats_qa_editor ) {
	$mats_qa_ed_sup = $mats_qa_supports( $mats_qa_editor->ID );
	$mats_qa_ok( false === ( $mats_qa_ed_sup['lock'] ?? null ) && false === ( $mats_qa_ed_sup['renaming'] ?? null ) && false === ( $mats_qa_ed_sup['visibility'] ?? null ), 'editors: no lock / renaming / visibility on sections' );
}
wp_set_current_user( 1 );

/* ── tags ── */
$mats_qa_ok( ! in_array( 'post_tag', get_object_taxonomies( 'post' ), true ), 'news articles have no Tags' );
$mats_qa_ok( in_array( 'category', get_object_taxonomies( 'post' ), true ), 'news articles keep Categories' );

/* ── X-Pingback only handled by security.php ── */
$mats_qa_admin_src = (string) file_get_contents( MATS_DIR . '/inc/modules/admin.php' );
$mats_qa_ok( false === strpos( $mats_qa_admin_src, "unset( \$headers['X-Pingback'] )" ), 'admin.php no longer unsets X-Pingback (security.php does)' );
$mats_qa_ok( ! isset( apply_filters( 'wp_headers', array( 'X-Pingback' => 'x' ), null )['X-Pingback'] ), 'X-Pingback still removed (security.php)' );

/* ── upload tips: the team cut-out exception ── */
ob_start();
mats_admin_upload_tip();
$mats_qa_tip = ob_get_clean();
$mats_qa_ok( false !== strpos( $mats_qa_tip, 'Recorte sin fondo' ) && false !== strpos( $mats_qa_tip, 'WebP o PNG' ), 'upload tip names the cut-out exception' );

/* ── news widget: an author never sees other people's unpublished titles ── */
$mats_qa_author = get_user_by( 'login', 'qa-autor' );
if ( ! $mats_qa_author ) {
	$mats_qa_author_id = wp_insert_user(
		array(
			'user_login' => 'qa-autor',
			'user_pass'  => wp_generate_password( 20 ),
			'user_email' => 'qa-autor@example.org',
			'role'       => 'author',
		)
	);
	$mats_qa_author    = get_user_by( 'id', $mats_qa_author_id );
}
$mats_qa_drafts = get_posts(
	array(
		'post_type'   => 'post',
		'post_status' => 'draft',
		'numberposts' => 5,
		'lang'        => '',
	)
);
wp_set_current_user( $mats_qa_author->ID );
ob_start();
mats_admin_news_widget();
$mats_qa_widget = ob_get_clean();
wp_set_current_user( 1 );
$mats_qa_leak = array_filter( $mats_qa_drafts, static fn( $p ) => (int) $p->post_author !== (int) $mats_qa_author->ID && false !== strpos( $mats_qa_widget, esc_html( get_the_title( $p ) ) ) );
$mats_qa_ok( $mats_qa_drafts && ! $mats_qa_leak, 'news widget (author): no draft titles of other people (' . count( $mats_qa_drafts ) . ' drafts checked)' );
ob_start();
mats_admin_news_widget();
$mats_qa_widget_admin = ob_get_clean();
$mats_qa_ok( ! $mats_qa_drafts || false !== strpos( $mats_qa_widget_admin, esc_html( get_the_title( $mats_qa_drafts[0] ) ) ), 'news widget (admin): drafts listed' );

/* ── guide: renders, conditions, real labels ── */
$mats_qa_render = static function ( int $user ): string {
	wp_set_current_user( $user );
	ob_start();
	mats_admin_render_guide();
	return (string) ob_get_clean();
};
$mats_qa_g_admin = $mats_qa_render( 1 );
$mats_qa_g_ed    = $mats_qa_editor ? $mats_qa_render( $mats_qa_editor->ID ) : '';
wp_set_current_user( 1 );
$mats_qa_ok( 15 === substr_count( $mats_qa_g_admin, 'class="mats-guide__section"' ) && 14 === substr_count( $mats_qa_g_ed, 'class="mats-guide__section"' ), 'guide: 15 sections for admins, 14 for editors' );
foreach ( array( 'Retrato', 'Recorte sin fondo', 'Elegir recorte', 'Figura', 'Orden en la web', '▲ ▼', 'Google y redes sociales', 'Título en Google', 'Cierre de las noticias', 'Textos por defecto para Google', 'Restaurar valores originales', 'Guardar cambios', 'Volver al original', 'Ya le he respondido: marcar como atendido', 'Exportar CSV', 'Desbloquear' ) as $mats_qa_label ) {
	$mats_qa_ok( false !== strpos( $mats_qa_g_admin, esc_html( $mats_qa_label ) ), 'guide mentions «' . $mats_qa_label . '»' );
}
$mats_qa_ok( false === strpos( $mats_qa_g_admin, 'también en la página de inicio' ), 'guide: no "latest news on the home page" claim' );
$mats_qa_ok( false === strpos( $mats_qa_g_admin, 'Noticias y Google' ), 'guide: no old "Noticias y Google" settings section' );
$mats_qa_ok( false === strpos( $mats_qa_g_ed, 'Desbloquear' ), 'editors do not read about unlocking' );
$mats_qa_es = mats_admin_spanish_available();
$mats_qa_ok( $mats_qa_es === ( false !== strpos( $mats_qa_g_ed, 'Perfil → Idioma' ) ), 'guide: "Perfil → Idioma" only when es_ES is installed (installed: ' . ( $mats_qa_es ? 'yes' : 'no' ) . ')' );
$mats_qa_ok( $mats_qa_es !== ( false !== strpos( $mats_qa_g_ed, 'todavía no está instalada la traducción' ) ), 'guide: "ask the administrator" only when es_ES is missing' );
foreach ( array( $mats_qa_g_admin, $mats_qa_g_ed ) as $mats_qa_html ) {
	$mats_qa_ok( ! preg_match( '/\{[a-z_]+\}/', $mats_qa_html ) && false === strpos( $mats_qa_html, '**' ), 'guide: placeholders and bold all formatted' );
}

/* ── fixed pages: no checkbox for editors in the Pages list ── */
$mats_qa_product = (int) mats_page_id( 'product', 'en' );
if ( $mats_qa_editor && $mats_qa_product ) {
	wp_set_current_user( $mats_qa_editor->ID );
	$mats_qa_ok( false === apply_filters( 'wp_list_table_show_post_checkbox', true, get_post( $mats_qa_product ) ), 'editor: fixed page has no list checkbox' );
	wp_set_current_user( 1 );
	$mats_qa_ok( true === apply_filters( 'wp_list_table_show_post_checkbox', true, get_post( $mats_qa_product ) ), 'admin: fixed page keeps its list checkbox' );
}

/* ── translation links: a fixed page is never taken from its translation ── */
$mats_qa_product_es = (int) mats_page_id( 'product', 'es' );
if ( $mats_qa_product && $mats_qa_product_es ) {
	$mats_qa_links = mats_editor_safe_translation_links( 999999, 'es', array( 'en' => $mats_qa_product ) );
	$mats_qa_ok( array() === $mats_qa_links, 'a new ES page cannot be linked to the EN Product page (already has an ES version)' );
	$mats_qa_links = mats_editor_safe_translation_links( $mats_qa_product_es, 'es', array( 'en' => $mats_qa_product ) );
	$mats_qa_ok( array( 'en' => $mats_qa_product ) === $mats_qa_links, 'the ES Product page keeps its link to the EN one' );
}

echo wp_json_encode(
	array(
		'product_en' => $mats_qa_product,
		'product_es' => $mats_qa_product_es,
		'team_en'    => (int) mats_page_id( 'team', 'en' ),
		'post'       => $mats_qa_post ? (int) $mats_qa_post[0]->ID : 0,
		'lang'       => $mats_qa_product ? pll_get_post_language( $mats_qa_product ) : '',
		'tr'         => $mats_qa_product ? pll_get_post_translations( $mats_qa_product ) : array(),
	)
) . "\n";
echo $mats_qa_fail ? "\n{$mats_qa_fail} CHECK(S) FAILED\n" : "\nALL CHECKS PASSED\n";
