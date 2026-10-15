<?php
/**
 * QA (secseo, fix pass): unit checks of the fixes + helpers for
 * secseo-fixpass.mjs.
 *   MATS_WP_PORT=8916 php /home/user/wpsite-cli.php wordpress/tools/qa/secseo-fixpass.php
 * MATS_QA_STEP selects what runs:
 *   unit (default)  trim, social image sizes, home texts, robots.txt,
 *                   upload names, login lock lifted by a new password
 *   drafts-setup    EN article (published) + ES translation (draft), a
 *                   published pair, an article whose automatic description
 *                   is cut after «Ó» → JSON
 *   drafts-cleanup  deletes them
 *   settings-set    seo_title / seo_description of both languages → QA text
 *   settings-reset  puts the saved values back (backup option)
 *   reset-key       a password reset key for "editora" → JSON
 *   ids             page / post IDs used by the editor checks → JSON
 *   restore-pass    editora's password back to editora-local-pass + unlock
 *
 * @package matsurfer-qa
 */

$mats_step = getenv( 'MATS_QA_STEP' ) ?: 'unit';
$mats_fail = array();
$mats_ok   = static function ( $cond, $label ) use ( &$mats_fail ) {
	echo ( $cond ? '[ok]   ' : '[FAIL] ' ), $label, "\n";
	if ( ! $cond ) {
		$mats_fail[] = $label;
	}
};

/* ── helper steps for the HTTP checks ─────────────────────────────────── */

if ( 'drafts-setup' === $mats_step ) {
	$mats_en = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_name' => 'secseo-fixpass-en-only', 'post_title' => 'SecSEO fixpass: EN only', 'post_content' => '<!-- wp:paragraph --><p>Published in English only.</p><!-- /wp:paragraph -->' ) );
	$mats_es = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_name' => 'secseo-fixpass-borrador', 'post_title' => 'SecSEO fixpass: borrador', 'post_content' => '<!-- wp:paragraph --><p>Borrador.</p><!-- /wp:paragraph -->' ) );
	pll_set_post_language( $mats_en, 'en' );
	pll_set_post_language( $mats_es, 'es' );
	pll_save_post_translations( array( 'en' => $mats_en, 'es' => $mats_es ) );
	/* a published one with a published translation, for comparison */
	$mats_en2 = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_name' => 'secseo-fixpass-both', 'post_title' => 'SecSEO fixpass: both', 'post_content' => '<!-- wp:paragraph --><p>Both languages.</p><!-- /wp:paragraph -->' ) );
	$mats_es2 = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_name' => 'secseo-fixpass-ambos', 'post_title' => 'SecSEO fixpass: ambos', 'post_content' => '<!-- wp:paragraph --><p>Ambos idiomas.</p><!-- /wp:paragraph -->' ) );
	pll_set_post_language( $mats_en2, 'en' );
	pll_set_post_language( $mats_es2, 'es' );
	pll_save_post_translations( array( 'en' => $mats_en2, 'es' => $mats_es2 ) );
	/* automatic description cut right after a multibyte letter ("…PRESENTÓ") */
	$mats_lead = 'Texto de prueba sobre recubrimientos funcionales en el laboratorio, donde EL EQUIPO PRESENTÓ';
	$mats_lead = str_repeat( 'a', 140 - mb_strlen( $mats_lead ) - 1 ) . ' ' . $mats_lead; // ends at character 140
	$mats_mb   = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_name' => 'secseo-fixpass-multibyte', 'post_title' => 'SecSEO fixpass: multibyte', 'post_content' => '<!-- wp:paragraph --><p>' . $mats_lead . ' resultadosextraordinarios del método π-π y más texto para alargar.</p><!-- /wp:paragraph -->' ) );
	pll_set_post_language( $mats_mb, 'es' );
	echo wp_json_encode( array( 'enOnly' => get_permalink( $mats_en ), 'both' => get_permalink( $mats_en2 ), 'multibyte' => get_permalink( $mats_mb ), 'ids' => array( $mats_en, $mats_es, $mats_en2, $mats_es2, $mats_mb ) ) ), "\n";
	return;
}
if ( 'drafts-cleanup' === $mats_step ) {
	foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => -1, 'lang' => '', 'fields' => 'ids', 'post_name__in' => array( 'secseo-fixpass-en-only', 'secseo-fixpass-borrador', 'secseo-fixpass-both', 'secseo-fixpass-ambos', 'secseo-fixpass-multibyte' ) ) ) as $mats_id ) {
		wp_delete_post( $mats_id, true );
	}
	echo "drafts removed\n";
	return;
}
if ( 'settings-set' === $mats_step ) {
	$mats_saved = get_option( 'mats_settings', array() );
	update_option( 'mats_secseo_settings_backup', array( 'exists' => false !== get_option( 'mats_settings', false ), 'value' => $mats_saved ), false );
	$mats_new = is_array( $mats_saved ) ? $mats_saved : array();
	foreach ( array( 'en', 'es' ) as $mats_lang ) {
		$mats_new[ $mats_lang ]                    = is_array( $mats_new[ $mats_lang ] ?? null ) ? $mats_new[ $mats_lang ] : array();
		$mats_new[ $mats_lang ]['seo_title']       = 'QA GENERAL TITLE ' . strtoupper( $mats_lang );
		$mats_new[ $mats_lang ]['seo_description'] = 'QA GENERAL DESCRIPTION ' . strtoupper( $mats_lang ) . ' — written in Ajustes de la web.';
	}
	update_option( 'mats_settings', $mats_new );
	echo "settings set\n";
	return;
}
if ( 'settings-reset' === $mats_step ) {
	$mats_backup = get_option( 'mats_secseo_settings_backup' );
	if ( is_array( $mats_backup ) ) {
		if ( $mats_backup['exists'] ) {
			update_option( 'mats_settings', $mats_backup['value'] );
		} else {
			delete_option( 'mats_settings' );
		}
		delete_option( 'mats_secseo_settings_backup' );
	}
	echo "settings restored\n";
	return;
}
if ( 'reset-key' === $mats_step ) {
	$mats_user = get_user_by( 'login', 'editora' );
	echo wp_json_encode( array( 'key' => get_password_reset_key( $mats_user ) ) ), "\n";
	return;
}
if ( 'ids' === $mats_step ) {
	$mats_post = get_posts( array( 'post_type' => 'post', 'numberposts' => 1, 'lang' => 'en', 'fields' => 'ids' ) );
	echo wp_json_encode(
		array(
			'home'      => mats_page_id( 'home', 'en' ),
			'product'   => mats_page_id( 'product', 'es' ),
			'news'      => mats_page_id( 'news', 'en' ),
			'post'      => (int) ( $mats_post[0] ?? 0 ),
			'siteTitle' => mats_seo_clean( mats_setting( 'seo_title', 'en' ) ),
		)
	), "\n";
	return;
}
if ( 'restore-pass' === $mats_step ) {
	$mats_user = get_user_by( 'login', 'editora' );
	wp_set_password( 'editora-local-pass', $mats_user->ID );
	mats_sec_login_unlock_user( (int) $mats_user->ID );
	echo "editora password restored\n";
	return;
}

/* ── unit checks ──────────────────────────────────────────────────────── */

/* 1. mats_seo_trim(): never cuts a character in two */
$mats_text = 'El nuevo recubrimiento se aplicó en el laboratorio del ICMAB, donde EL EQUIPO PRESENTÓ los resultados del método π-π con ÀÔ y más texto detrás para alargar la frase bastante';
$mats_bad  = array();
for ( $mats_max = 20; $mats_max <= 170; $mats_max++ ) {
	$mats_cut = mats_seo_trim( $mats_text, $mats_max );
	if ( ! mb_check_encoding( $mats_cut, 'UTF-8' ) || '' === esc_attr( $mats_cut ) || mb_strlen( $mats_cut ) > $mats_max ) {
		$mats_bad[] = $mats_max;
	}
}
$mats_ok( ! $mats_bad, 'mats_seo_trim: valid UTF-8 and within the limit for every length 20–170' . ( $mats_bad ? ' (bad: ' . implode( ',', $mats_bad ) . ')' : '' ) );
foreach ( array( 'Ó', 'π', 'À', 'Ô' ) as $mats_char ) {
	$mats_str = str_repeat( 'a', 30 ) . ' ' . str_repeat( 'b', 10 ) . $mats_char . ' ccccccccc';
	$mats_cut = mats_seo_trim( $mats_str, 44 );
	$mats_ok( str_ends_with( $mats_cut, $mats_char . '…' ), "mats_seo_trim keeps a final $mats_char (" . $mats_cut . ')' );
}
$mats_ok( 'abc def…' === mats_seo_trim( 'abc def, — ghi jkl mno', 12 ), 'mats_seo_trim still drops trailing punctuation and dashes (' . mats_seo_trim( 'abc def, — ghi jkl mno', 12 ) . ')' );

/* 2. social image: the fallback really moves past missing sizes */
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
$mats_make = static function ( int $w, int $h, string $name ): int {
	$img = imagecreatetruecolor( $w, $h );
	imagefilledrectangle( $img, 0, 0, $w - 1, $h - 1, imagecolorallocate( $img, 30, 120, 90 ) );
	$tmp = wp_tempnam( $name );
	imagejpeg( $img, $tmp, 80 );
	return (int) media_handle_sideload( array( 'name' => $name, 'tmp_name' => $tmp ), 0 );
};
$mats_big = $mats_make( 2400, 1600, 'secseo-fixpass-big.jpg' );
$mats_meta = wp_get_attachment_metadata( $mats_big );
$mats_ok( ! isset( $mats_meta['sizes']['mats-og'] ) && isset( $mats_meta['sizes']['mats-wide'] ), 'new upload has mats-wide but no mats-og (made only for featured images)' );
$mats_ok( array( 'mats-wide', 'large', 'full' ) === mats_seo_image_sizes( $mats_big ), 'no mats-og → mats-wide, large, full (' . implode( ',', mats_seo_image_sizes( $mats_big ) ) . ')' );
$mats_post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'secseo fixpass image' ) );
update_post_meta( $mats_post, '_thumbnail_id', $mats_big ); // as before the module: no crop made
$mats_meta['sizes'] = array_diff_key( $mats_meta['sizes'], array( 'mats-og' => 1 ) );
wp_update_attachment_metadata( $mats_big, $mats_meta );
$mats_img = mats_seo_image( $mats_post );
$mats_ok( 1600 === $mats_img['width'] && 900 === $mats_img['height'], 'featured image without the crop → mats-wide 1600×900 (' . $mats_img['width'] . '×' . $mats_img['height'] . ')' );
mats_seo_ensure_og_size( $mats_big );
$mats_img = mats_seo_image( $mats_post );
$mats_ok( 1200 === $mats_img['width'] && 630 === $mats_img['height'] && str_contains( $mats_img['url'], '1200x630' ), 'with the crop → mats-og 1200×630' );
$mats_exact = $mats_make( 1200, 630, 'secseo-fixpass-exact.jpg' );
$mats_ok( 'full' === mats_seo_image_sizes( $mats_exact )[0], 'a photo already 1200×630 → the original first (' . implode( ',', mats_seo_image_sizes( $mats_exact ) ) . ')' );
$mats_small = $mats_make( 1000, 562, 'secseo-fixpass-small.jpg' );
set_post_thumbnail( $mats_post, $mats_small );
$mats_img = mats_seo_image( $mats_post );
$mats_ok( $mats_img['width'] <= 1000 && abs( $mats_img['width'] / $mats_img['height'] - 1200 / 630 ) < 0.02, 'small photo set as featured → its biggest 1.91:1 crop (' . $mats_img['width'] . '×' . $mats_img['height'] . ')' );
wp_delete_post( $mats_post, true );
foreach ( array( $mats_big, $mats_exact, $mats_small ) as $mats_att ) {
	wp_delete_attachment( $mats_att, true );
}

/* 3. home page: the installer's copy counts as empty, the team's text wins */
$mats_home    = (int) get_option( 'page_on_front' );
$mats_shipped = mats_seo_shipped_home_texts();
$mats_ok( 2 === count( $mats_shipped['title'] ) && 2 === count( $mats_shipped['description'] ), 'shipped home texts found for both languages' );
$mats_before  = get_post_meta( $mats_home, '_mats_seo_title', true );
update_post_meta( $mats_home, '_mats_seo_title', $mats_shipped['title']['en'] );
$mats_ok( '' === mats_seo_home_meta( $mats_home, 'title' ), 'home title equal to the original copy → general title of Ajustes de la web' );
update_post_meta( $mats_home, '_mats_seo_title', 'Our own home title' );
$mats_ok( 'Our own home title' === mats_seo_home_meta( $mats_home, 'title' ), 'a home title written by the team is used' );
$mats_data = mats_seo_editor_data( get_post( $mats_home ) );
$mats_ok( $mats_data['isFront'] && in_array( $mats_shipped['title']['es'], $mats_data['shipped']['title'], true ), 'editor data of the home page lists the original copy' );
update_post_meta( $mats_home, '_mats_seo_title', $mats_before );
$mats_ok( '' === mats_seo_editor_value( get_post( $mats_home ), 'title' ) || $mats_before !== $mats_shipped['title']['en'], 'classic box shows the original copy as empty' );
$mats_product = function_exists( 'mats_page_id' ) ? mats_page_id( 'product', 'en' ) : 0;
if ( $mats_product ) {
	$mats_pdata = mats_seo_editor_data( get_post( $mats_product ) );
	$mats_ok( ! $mats_pdata['isFront'] && array() === $mats_pdata['shipped']['title'], 'other pages: no "original copy" rule' );
	$mats_ok( false === $mats_pdata['excerpt'], 'landing page (product): no «Extracto» → excerpt flag false' );
}
$mats_news = function_exists( 'mats_page_id' ) ? mats_page_id( 'news', 'en' ) : 0;
if ( $mats_news ) {
	$mats_ok( true === mats_seo_editor_data( get_post( $mats_news ) )['excerpt'], 'News page keeps the «Extracto»' );
}
$mats_ok( 120 === $mats_data['min']['description'] && 155 === $mats_data['max']['description'], 'description length 120–155 passed to the panel' );

/* 4. robots.txt: no Sitemap line when the theme is not in charge / sitemaps are off */
$mats_core_robots = array( wp_sitemaps_get_server(), 'add_robots' );
$mats_base        = "User-agent: *\nDisallow: /wp-admin/\n";
$mats_ok( 1 === substr_count( apply_filters( 'robots_txt', $mats_base, '1' ), 'Sitemap:' ), 'robots.txt: one Sitemap line normally' );
remove_filter( 'robots_txt', $mats_core_robots, 0 );
$mats_ok( 1 === substr_count( apply_filters( 'robots_txt', $mats_base, '1' ), 'Sitemap:' ), 'robots.txt: the theme adds it when core does not' );
add_filter( 'wp_sitemaps_enabled', '__return_false', 99 );
$mats_ok( 0 === substr_count( apply_filters( 'robots_txt', $mats_base, '1' ), 'Sitemap:' ), 'robots.txt: no Sitemap line when wp-sitemap.xml is off (SEO plugin)' );
remove_filter( 'wp_sitemaps_enabled', '__return_false', 99 );
add_filter( 'mats_seo_enabled', '__return_false' );
$mats_ok( 0 === substr_count( apply_filters( 'robots_txt', $mats_base, '1' ), 'Sitemap:' ), 'robots.txt: no Sitemap line from the theme when its SEO is off' );
remove_filter( 'mats_seo_enabled', '__return_false' );
add_filter( 'robots_txt', $mats_core_robots, 0, 2 );

/* 5. upload names */
wp_set_current_user( get_user_by( 'login', 'editora' )->ID );
foreach ( array( 'informe.pl.png', 'figura.py.png', 'brochure.PL.pdf', 'www.icmab.es.png' ) as $mats_name ) {
	$mats_r = mats_sec_upload_prefilter( array( 'name' => $mats_name, 'size' => 100, 'error' => 0 ) );
	$mats_ok( empty( $mats_r['error'] ), "upload accepted: $mats_name" );
}
$mats_r = mats_sec_upload_prefilter( array( 'name' => 'Foto.PHP.jpg', 'size' => 100, 'error' => 0 ) );
$mats_ok( ! empty( $mats_r['error'] ) && str_contains( $mats_r['error'], '«.php.»' ) && str_contains( $mats_r['error'], '«Foto-PHP.jpg»' ), 'Foto.PHP.jpg refused, the message names «.php.» and suggests «Foto-PHP.jpg» (' . ( $mats_r['error'] ?? '' ) . ')' );
$mats_r = mats_sec_upload_prefilter( array( 'name' => 'run.cgi.png', 'size' => 100, 'error' => 0 ) );
$mats_ok( ! empty( $mats_r['error'] ) && str_contains( $mats_r['error'], '«.cgi.»' ), 'run.cgi.png still refused with a clear message' );
$mats_r = mats_sec_upload_prefilter( array( 'name' => 'logo.svg', 'size' => 100, 'error' => 0 ) );
$mats_ok( ! empty( $mats_r['error'] ) && str_contains( $mats_r['error'], 'SVG' ), 'logo.svg refused with the SVG message' );
$mats_r = mats_sec_upload_prefilter( array( 'name' => 'tool.py', 'size' => 100, 'error' => 0 ) );
$mats_ok( ! empty( $mats_r['error'] ), 'tool.py (final extension) still refused' );
wp_set_current_user( 1 );

/* 6. login lock: a new password lifts the account lock on every IP */
$mats_editor = get_user_by( 'login', 'editora' );
remove_action( 'after_password_reset', 'wp_password_change_notification' ); // no e-mails from the checks
add_filter( 'send_password_change_email', '__return_false' );
$mats_ip_was = $_SERVER['REMOTE_ADDR'] ?? null;
$mats_set_ip = static function ( $ip ) {
	$_SERVER['REMOTE_ADDR'] = $ip;
};
foreach ( array( '203.0.113.7', '198.51.100.9' ) as $mats_ip ) {
	$mats_set_ip( $mats_ip );
	for ( $mats_i = 0; $mats_i < 5; $mats_i++ ) {
		mats_sec_login_register_failure( 0 === $mats_i % 2 ? 'editora' : 'editora@example.org' );
	}
	$mats_ok( 'user' === mats_sec_login_lock( 'editora' )['kind'], "editora locked from $mats_ip" );
}
$mats_hash = $mats_editor->user_pass;
reset_password( $mats_editor, 'secseo-temporary-pass-1' ); // fires after_password_reset
foreach ( array( '203.0.113.7', '198.51.100.9' ) as $mats_ip ) {
	$mats_set_ip( $mats_ip );
	$mats_ok( 0 === mats_sec_login_lock( 'editora' )['until'], "password reset lifted the lock from $mats_ip" );
}
$mats_ok( false !== get_transient( mats_sec_login_keys( 'editora' )['ip'] ), 'the per-IP counter is kept' );
/* the same through the profile (an administrator sets a new password) */
$mats_set_ip( '203.0.113.7' );
for ( $mats_i = 0; $mats_i < 5; $mats_i++ ) {
	mats_sec_login_register_failure( 'editora' );
}
$mats_ok( 0 < mats_sec_login_lock( 'editora' )['until'], 'locked again' );
wp_update_user( array( 'ID' => $mats_editor->ID, 'user_pass' => 'secseo-temporary-pass-2' ) );
$mats_ok( 0 === mats_sec_login_lock( 'editora' )['until'], 'a new password set in the profile lifts the lock' );
/* a profile save without a new password does not */
for ( $mats_i = 0; $mats_i < 5; $mats_i++ ) {
	mats_sec_login_register_failure( 'editora' );
}
wp_update_user( array( 'ID' => $mats_editor->ID, 'description' => 'secseo' ) );
$mats_ok( 0 < mats_sec_login_lock( 'editora' )['until'], 'a profile save without a new password keeps the lock' );
/* the re-encryption of a password at login (wp_set_password action) must not unlock */
do_action( 'wp_set_password', 'x', $mats_editor->ID, $mats_editor );
$mats_ok( 0 < mats_sec_login_lock( 'editora' )['until'], 'the wp_set_password action alone (password re-encryption at login) keeps the lock' );
$mats_lock = mats_sec_login_lock( 'editora' );
$mats_msg  = mats_sec_lock_message( $mats_lock['until'], $mats_lock['kind'] );
$mats_ok( str_contains( $mats_msg, '¿Has olvidado la contraseña?' ) && str_contains( $mats_msg, 'en cuanto la cambies podrás entrar' ), 'account lock message: changing the password unlocks' );
/* per-IP lock: its own message, no reset hint */
add_filter( 'mats_security_login_limits', static fn() => array( 'ip' => 3 ) );
$mats_set_ip( '192.0.2.44' );
foreach ( array( 'nadie1', 'nadie2', 'nadie3' ) as $mats_name ) {
	mats_sec_login_register_failure( $mats_name );
}
$mats_lock = mats_sec_login_lock( 'otra-persona' );
$mats_msg  = mats_sec_lock_message( $mats_lock['until'], $mats_lock['kind'] );
$mats_ok( 'ip' === $mats_lock['kind'] && str_contains( $mats_msg, 'Desde esta conexión' ) && ! str_contains( $mats_msg, 'olvidado' ), 'IP lock message does not promise that a reset unlocks' );
/* clean up: password, description, counters */
global $wpdb;
$wpdb->update( $wpdb->users, array( 'user_pass' => $mats_hash ), array( 'ID' => $mats_editor->ID ) );
clean_user_cache( $mats_editor->ID );
update_user_meta( $mats_editor->ID, 'description', '' );
$mats_ok( wp_check_password( 'editora-local-pass', get_userdata( $mats_editor->ID )->user_pass, $mats_editor->ID ), 'editora password restored' );
$_SERVER['REMOTE_ADDR'] = $mats_ip_was;
foreach ( $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_mats\_login\_%'" ) as $mats_row ) {
	delete_transient( substr( $mats_row, strlen( '_transient_' ) ) );
}

echo $mats_fail ? "\nFAILED (" . count( $mats_fail ) . "):\n - " . implode( "\n - ", $mats_fail ) . "\n" : "\nALL FIXPASS UNIT CHECKS PASSED\n";
