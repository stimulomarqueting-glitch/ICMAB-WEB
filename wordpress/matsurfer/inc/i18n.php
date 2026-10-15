<?php
/**
 * Language helpers. English is the default language (site root), Spanish
 * lives under /es/ — Polylang does the routing. Everything here degrades to
 * a single-language site when Polylang is not active.
 *
 * Only two languages exist: 'en' and 'es' (Polylang language slugs).
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

/** Supported language slugs, default first. */
function mats_langs(): array {
	return array( 'en', 'es' );
}

/** Human labels for the language switcher and admin columns. */
function mats_lang_meta( string $lang = '' ): array {
	$meta = array(
		'en' => array(
			'label'  => 'EN',
			'name'   => 'English',
			'locale' => 'en_GB',
			'og'     => 'en_GB',
			'html'   => 'en',
		),
		'es' => array(
			'label'  => 'ES',
			'name'   => 'Español',
			'locale' => 'es_ES',
			'og'     => 'es_ES',
			'html'   => 'es',
		),
	);
	return $lang ? ( $meta[ $lang ] ?? $meta['en'] ) : $meta;
}

function mats_polylang_active(): bool {
	return function_exists( 'pll_current_language' ) && function_exists( 'pll_get_post' );
}

/** Normalises anything ('es_ES', 'ES', '') to a supported slug. */
function mats_normalize_lang( $lang ): string {
	$lang = strtolower( substr( (string) $lang, 0, 2 ) );
	return in_array( $lang, mats_langs(), true ) ? $lang : 'en';
}

/** Language of a post (page, news article…), defaulting to the site default. */
function mats_post_lang( $post_id ): string {
	$post_id = (int) $post_id;
	if ( $post_id && function_exists( 'pll_get_post_language' ) ) {
		$lang = pll_get_post_language( $post_id, 'slug' );
		if ( $lang ) {
			return mats_normalize_lang( $lang );
		}
	}
	return mats_default_lang();
}

function mats_default_lang(): string {
	if ( function_exists( 'pll_default_language' ) ) {
		$lang = pll_default_language( 'slug' );
		if ( $lang ) {
			return mats_normalize_lang( $lang );
		}
	}
	return str_starts_with( (string) get_locale(), 'es' ) ? 'es' : 'en';
}

/**
 * Language of what is being rendered right now.
 *  - front end: Polylang's current language
 *  - block editor previews (REST) and admin screens: the language of the
 *    post being edited, so a Spanish page previews in Spanish
 */
function mats_lang(): string {
	if ( isset( $GLOBALS['mats_force_lang'] ) ) {
		return mats_normalize_lang( $GLOBALS['mats_force_lang'] );
	}
	if ( is_admin() || mats_is_preview() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		$post_id = get_the_ID();
		if ( ! $post_id && isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$post_id = absint( $_GET['post'] ); // phpcs:ignore WordPress.Security.NonceVerification
		}
		if ( $post_id ) {
			return mats_post_lang( $post_id );
		}
	}
	if ( function_exists( 'pll_current_language' ) ) {
		$lang = pll_current_language( 'slug' );
		if ( $lang ) {
			return mats_normalize_lang( $lang );
		}
	}
	return mats_default_lang();
}

/** Run a callback with the language forced (importer, previews of other languages). */
function mats_with_lang( string $lang, callable $fn ) {
	$prev                     = $GLOBALS['mats_force_lang'] ?? null;
	$GLOBALS['mats_force_lang'] = $lang;
	try {
		return $fn();
	} finally {
		if ( null === $prev ) {
			unset( $GLOBALS['mats_force_lang'] );
		} else {
			$GLOBALS['mats_force_lang'] = $prev;
		}
	}
}

/**
 * Pick the current language's value from a bilingual pair
 * (`['en' => …, 'es' => …]`), falling back to English, then to any value.
 */
function mats_pick( $pair, string $lang = '' ) {
	if ( ! is_array( $pair ) ) {
		return $pair;
	}
	$lang = $lang ?: mats_lang();
	if ( isset( $pair[ $lang ] ) && '' !== $pair[ $lang ] ) {
		return $pair[ $lang ];
	}
	if ( isset( $pair['en'] ) && '' !== $pair['en'] ) {
		return $pair['en'];
	}
	foreach ( $pair as $value ) {
		if ( '' !== $value && null !== $value ) {
			return $value;
		}
	}
	return '';
}

/**
 * Fixed interface strings (assistive labels, small UI words) that are not
 * page content. Editable copy lives in pages and in Ajustes de la web.
 */
function mats_t( string $key, string $lang = '' ): string {
	static $strings = array(
		'skip'          => array( 'en' => 'Skip to content', 'es' => 'Saltar al contenido' ),
		'main_nav'      => array( 'en' => 'Main', 'es' => 'Principal' ),
		'footer_nav'    => array( 'en' => 'Footer', 'es' => 'Pie de página' ),
		'home'          => array( 'en' => 'Home', 'es' => 'Inicio' ),
		'menu_open'     => array( 'en' => 'Open menu', 'es' => 'Abrir menú' ),
		'menu_close'    => array( 'en' => 'Close menu', 'es' => 'Cerrar menú' ),
		'language'      => array( 'en' => 'Language', 'es' => 'Idioma' ),
		'read_more'     => array( 'en' => 'Read more', 'es' => 'Leer más' ),
		'read_article'  => array( 'en' => 'Read the article', 'es' => 'Leer el artículo' ),
		'news'          => array( 'en' => 'News', 'es' => 'Noticias' ),
		'all_news'      => array( 'en' => 'All news', 'es' => 'Todas las noticias' ),
		'back_to_news'  => array( 'en' => 'Back to news', 'es' => 'Volver a noticias' ),
		'latest_news'   => array( 'en' => 'Latest news', 'es' => 'Últimas noticias' ),
		'newer'         => array( 'en' => 'Newer', 'es' => 'Más recientes' ),
		'older'         => array( 'en' => 'Older', 'es' => 'Anteriores' ),
		'page'          => array( 'en' => 'Page', 'es' => 'Página' ),
		'search'        => array( 'en' => 'Search', 'es' => 'Buscar' ),
		'search_for'    => array( 'en' => 'Search results for', 'es' => 'Resultados de búsqueda para' ),
		'no_results'    => array( 'en' => 'Nothing found. Try other words.', 'es' => 'No hemos encontrado nada. Prueba con otras palabras.' ),
		'not_found'     => array( 'en' => 'Page not found', 'es' => 'Página no encontrada' ),
		'not_found_txt' => array( 'en' => 'The page you are looking for does not exist or has moved.', 'es' => 'La página que buscas no existe o ha cambiado de dirección.' ),
		'go_home'       => array( 'en' => 'Go to the home page', 'es' => 'Ir a la página de inicio' ),
		'min_read'      => array( 'en' => 'min read', 'es' => 'min de lectura' ),
		'published_on'  => array( 'en' => 'Published on', 'es' => 'Publicado el' ),
		'category'      => array( 'en' => 'Category', 'es' => 'Categoría' ),
		'share'         => array( 'en' => 'Share', 'es' => 'Compartir' ),
		'linkedin'      => array( 'en' => 'LinkedIn profile', 'es' => 'Perfil de LinkedIn' ),
		'website'       => array( 'en' => 'Website', 'es' => 'Web' ),
		'optional'      => array( 'en' => '(optional)', 'es' => '(opcional)' ),
		'no_news'          => array( 'en' => 'There are no news articles yet. Check back soon.', 'es' => 'Todavía no hay noticias. Vuelve pronto.' ),
		'no_news_title'    => array( 'en' => 'Nothing published yet', 'es' => 'Aún no hay nada publicado' ),
		'previous_article' => array( 'en' => 'Previous article', 'es' => 'Artículo anterior' ),
		'next_article'     => array( 'en' => 'Next article', 'es' => 'Artículo siguiente' ),
		'page_of'          => array( 'en' => 'Page %1$d of %2$d', 'es' => 'Página %1$d de %2$d' ),
		'results_count'    => array( 'en' => '%d results', 'es' => '%d resultados' ),
		'result_one'       => array( 'en' => '1 result', 'es' => '1 resultado' ),
		'search_label'     => array( 'en' => 'Search the site', 'es' => 'Buscar en la web' ),
		'search_ph'        => array( 'en' => 'Search news and pages…', 'es' => 'Buscar noticias y páginas…' ),
		'search_lead'      => array( 'en' => 'Type a few words to find news articles and pages.', 'es' => 'Escribe unas palabras para encontrar noticias y páginas.' ),
		'tag'              => array( 'en' => 'Topic', 'es' => 'Tema' ),
		'archive'          => array( 'en' => 'Archive', 'es' => 'Archivo' ),
		'updated_on'       => array( 'en' => 'Updated on', 'es' => 'Actualizado el' ),
		'on_this_page'     => array( 'en' => 'On this page', 'es' => 'En esta página' ),
		'share_linkedin'   => array( 'en' => 'Share on LinkedIn', 'es' => 'Compartir en LinkedIn' ),
		'share_email'      => array( 'en' => 'Send by email', 'es' => 'Enviar por email' ),
		'useful_links'     => array( 'en' => 'You may be looking for', 'es' => 'Quizá buscabas' ),
		'blog_pagination'  => array( 'en' => 'More news', 'es' => 'Más noticias' ),
		'article_nav'      => array( 'en' => 'More articles', 'es' => 'Más artículos' ),
		'play_video'       => array( 'en' => 'Play video', 'es' => 'Reproducir vídeo' ),
		'form_missing'  => array( 'en' => 'The contact form is being set up. Meanwhile, write to us at', 'es' => 'Estamos configurando el formulario de contacto. Mientras tanto, escríbenos a' ),
	);
	$lang = $lang ?: mats_lang();
	return isset( $strings[ $key ] ) ? (string) mats_pick( $strings[ $key ], $lang ) : $key;
}

/**
 * Language switcher links for the current request: one entry per language
 * with the translated URL of the current page (or that language's home).
 */
function mats_language_links(): array {
	$current = mats_lang();
	$links   = array();
	$raw     = function_exists( 'pll_the_languages' ) ? pll_the_languages(
		array(
			'raw'                    => 1,
			'hide_if_empty'          => 0,
			'hide_if_no_translation' => 0,
			'force_home'             => 0,
			'echo'                   => 0,
		)
	) : array();

	foreach ( mats_langs() as $lang ) {
		$meta = mats_lang_meta( $lang );
		$url  = '';
		if ( is_array( $raw ) && isset( $raw[ $lang ]['url'] ) ) {
			$url = $raw[ $lang ]['url'];
		}
		if ( ! $url ) {
			$url = function_exists( 'pll_home_url' ) ? pll_home_url( $lang ) : home_url( '/' );
		}
		if ( ! function_exists( 'pll_the_languages' ) && $lang !== $current ) {
			continue; // single-language site: no switcher entry for the other language
		}
		$links[] = array(
			'lang'    => $lang,
			'label'   => $meta['label'],
			'name'    => $meta['name'],
			'html'    => $meta['html'],
			'url'     => $url,
			'current' => $lang === $current,
		);
	}
	return $links;
}
