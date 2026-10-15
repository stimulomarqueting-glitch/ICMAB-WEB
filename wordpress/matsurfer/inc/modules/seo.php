<?php
/**
 * Built-in SEO — the head of the original site (src/layouts/BaseLayout.astro)
 * for every page, news article and archive, in both languages.
 *
 *  <title>          SEO title of the page, else a sensible default
 *  description      SEO description, else the excerpt / site description
 *  canonical        core prints it for pages and articles; added here for
 *                   the News page, the category archives and a latest-posts
 *                   front page
 *  Open Graph       type (website / article), site_name, title, description,
 *                   url, image 1200×630 (featured image, else
 *                   assets/media/og-image.png) + alt, locale + alternates
 *  Twitter          summary_large_image
 *  JSON-LD          one @graph in the footer: WebSite, Organization
 *                   (part of ICMAB-CSIC), BreadcrumbList on interior pages,
 *                   Product on the product page, BlogPosting on news
 *                   articles + every node the sections collected with
 *                   mats_jsonld_add() (publications, team…)
 *  hreflang         printed by Polylang; x-default (English) added to it
 *  sitemaps         core wp-sitemap.xml with pages, news and news
 *                   categories only; listed in robots.txt. "Discourage
 *                   search engines" (Ajustes → Lectura) still switches
 *                   everything off (core behaviour).
 *
 * Editors fill the texts in the editor sidebar panel "Google y redes
 * sociales" (assets/js/seo-admin.js) of every page and news article:
 * post meta '_mats_seo_title' and '_mats_seo_description'. Empty fields fall
 * back to:
 *   home page   Ajustes de la web → SEO title / description of the language
 *   pages       "<page title> — MatSurfer"; excerpt, else the site description
 *   news        "<article title> — MatSurfer"; excerpt, else the start of the text
 *
 * When Yoast SEO, Rank Math, SEOPress, All in One SEO, The SEO Framework or
 * Slim SEO is active, this module stands down (no title, meta, Open Graph,
 * canonical or panel) and only prints the JSON-LD the sections collected
 * that those plugins do not produce (publications, people…).
 *
 * Filters: mats_seo_enabled, mats_seo_plugin, mats_seo_site_name,
 * mats_seo_default_image, mats_seo_graph, mats_organization_id.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

const MATS_SEO_TITLE_MAX = 60;
const MATS_SEO_DESC_MAX  = 155;
const MATS_SEO_SEP       = ' — ';

/* ═══════════════════════════════════════════════════════════════════════
 * Mode: on, or standing down for an SEO plugin
 * ═════════════════════════════════════════════════════════════════════ */

/** Name of the active SEO plugin, '' when there is none. */
function mats_seo_plugin(): string {
	static $plugin = null;
	if ( null === $plugin ) {
		$plugin = '';
		if ( defined( 'WPSEO_VERSION' ) ) {
			$plugin = 'Yoast SEO';
		} elseif ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath', false ) ) {
			$plugin = 'Rank Math';
		} elseif ( defined( 'SEOPRESS_VERSION' ) ) {
			$plugin = 'SEOPress';
		} elseif ( defined( 'AIOSEO_VERSION' ) || function_exists( 'aioseo' ) ) {
			$plugin = 'All in One SEO';
		} elseif ( defined( 'THE_SEO_FRAMEWORK_VERSION' ) ) {
			$plugin = 'The SEO Framework';
		} elseif ( defined( 'SLIM_SEO_VER' ) ) {
			$plugin = 'Slim SEO';
		}
		$plugin = (string) apply_filters( 'mats_seo_plugin', $plugin );
	}
	return $plugin;
}

/** True when the theme prints the SEO head itself. */
function mats_seo_active(): bool {
	return '' === mats_seo_plugin() && (bool) apply_filters( 'mats_seo_enabled', true );
}

/* ═══════════════════════════════════════════════════════════════════════
 * Small helpers
 * ═════════════════════════════════════════════════════════════════════ */

function mats_seo_site_name(): string {
	return (string) apply_filters( 'mats_seo_site_name', 'MatSurfer' );
}

/** Site root without trailing slash, the same in every language (JSON-LD @ids). */
function mats_seo_site_url(): string {
	return untrailingslashit( (string) get_option( 'home' ) );
}

function mats_seo_org_id(): string {
	return (string) apply_filters( 'mats_organization_id', mats_seo_site_url() . '/#organization' );
}

function mats_seo_website_id(): string {
	return mats_seo_site_url() . '/#website';
}

/** Plain text: no tags, no entities, single spaces. */
function mats_seo_clean( $text ): string {
	$text = html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = wp_strip_all_tags( $text, true );
	return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
}

/** Shortens plain text to $max characters on a word boundary ("…"). */
function mats_seo_trim( string $text, int $max ): string {
	if ( mb_strlen( $text ) <= $max ) {
		return $text;
	}
	$cut   = mb_substr( $text, 0, $max - 1 );
	$space = mb_strrpos( $cut, ' ' );
	if ( false !== $space && $space > $max * 0.6 ) {
		$cut = mb_substr( $cut, 0, $space );
	}
	return rtrim( $cut, " \t\n\r\0\x0B,;:.-—–" ) . '…';
}

/** Stored SEO texts of a post (plain). */
function mats_seo_meta( int $post_id, string $field ): string {
	return $post_id ? mats_seo_clean( get_post_meta( $post_id, '_mats_seo_' . $field, true ) ) : '';
}

/** Is this post the home page (in any language)? */
function mats_seo_is_front_post( int $post_id ): bool {
	if ( ! $post_id ) {
		return false;
	}
	$front = (int) get_option( 'page_on_front' );
	if ( $front && $front === $post_id ) {
		return true;
	}
	if ( $front && function_exists( 'pll_get_post_translations' ) && in_array( $post_id, array_map( 'intval', (array) pll_get_post_translations( $front ) ), true ) ) {
		return true;
	}
	return function_exists( 'mats_role_of' ) && 'home' === mats_role_of( $post_id );
}

/** "<title> — MatSurfer". */
function mats_seo_with_brand( string $title ): string {
	$title = trim( $title );
	$brand = mats_seo_site_name();
	if ( '' === $title || $title === $brand ) {
		return $brand;
	}
	return $title . MATS_SEO_SEP . $brand;
}

/** Breadcrumb / menu label of a structural page ('' if none). */
function mats_seo_role_label( string $role ): string {
	if ( '' === $role || 'home' === $role ) {
		return '';
	}
	$key = array(
		'privacy' => 'footer_l_privacy',
		'legal'   => 'footer_l_legal',
	)[ $role ] ?? 'nav_' . $role;
	return mats_seo_clean( mats_setting( $key ) );
}

/** The News page in the current language (0 when there is none). */
function mats_seo_news_page_id(): int {
	$id = function_exists( 'mats_page_id' ) ? mats_page_id( 'news' ) : 0;
	return $id ?: (int) get_option( 'page_for_posts' );
}

/** HTML language codes of the site languages ('en', 'es'). */
function mats_seo_languages(): array {
	$langs = function_exists( 'pll_languages_list' ) ? (array) pll_languages_list( array( 'fields' => 'slug' ) ) : array();
	$langs = $langs ? array_map( 'mats_normalize_lang', $langs ) : array( mats_lang() );
	return array_values( array_unique( array_map( static fn( $l ) => mats_lang_meta( $l )['html'], $langs ) ) );
}

/**
 * Social image of a post: its featured image (1200×630 crop when it
 * exists), else the default MatSurfer image. [ url, width, height, alt, type ].
 */
function mats_seo_image( int $post_id = 0 ): array {
	$thumb = $post_id ? (int) get_post_thumbnail_id( $post_id ) : 0;
	if ( $thumb && wp_attachment_is_image( $thumb ) ) {
		foreach ( array( 'mats-og', 'mats-wide', 'large', 'full' ) as $size ) {
			$src = wp_get_attachment_image_src( $thumb, $size );
			if ( $src && ! empty( $src[0] ) ) {
				$alt = mats_seo_clean( get_post_meta( $thumb, '_wp_attachment_image_alt', true ) );
				return array(
					'url'    => (string) $src[0],
					'width'  => (int) $src[1],
					'height' => (int) $src[2],
					'alt'    => '' !== $alt ? $alt : mats_seo_clean( get_the_title( $post_id ) ),
					'type'   => (string) get_post_mime_type( $thumb ),
				);
			}
		}
	}
	return (array) apply_filters(
		'mats_seo_default_image',
		array(
			'url'    => mats_asset( 'media/og-image.png' ),
			'width'  => 1200,
			'height' => 630,
			'alt'    => mats_seo_clean( mats_setting( 'og_alt' ) ),
			'type'   => 'image/png',
		)
	);
}

/** Description generated from the text of a news article. */
function mats_seo_text_excerpt( WP_Post $post ): string {
	$content = excerpt_remove_blocks( (string) $post->post_content ); // text blocks only, no sections
	$content = strip_shortcodes( $content );
	return mats_seo_trim( mats_seo_clean( $content ), MATS_SEO_DESC_MAX );
}

/** URL of the current page of a paginated list. */
function mats_seo_paged_url( string $first ): string {
	$paged = max( 1, (int) get_query_var( 'paged' ) );
	return $paged > 1 ? (string) get_pagenum_link( $paged, false ) : $first;
}

/** " — Page 2" for paginated lists. */
function mats_seo_paged_suffix(): string {
	$paged = max( 1, (int) get_query_var( 'paged' ) );
	return $paged > 1 ? MATS_SEO_SEP . mats_t( 'page' ) . ' ' . $paged : '';
}

/* ═══════════════════════════════════════════════════════════════════════
 * What the current request is about (computed once)
 * ═════════════════════════════════════════════════════════════════════ */

/**
 * [ kind, title, description, url, canonical (to print here), og_type,
 *   image, post (WP_Post|null), role, lang, locales (alternates),
 *   breadcrumb [ [name, url] … ] ].
 */
function mats_seo_context(): array {
	static $ctx = null;
	if ( null !== $ctx ) {
		return $ctx;
	}
	$lang = mats_lang();
	$ctx  = array(
		'kind'        => 'other',
		'title'       => '',
		'description' => '',
		'url'         => '',
		'canonical'   => '',
		'og_type'     => 'website',
		'image'       => array(),
		'post'        => null,
		'role'        => '',
		'lang'        => $lang,
		'locales'     => array(),
		'breadcrumb'  => array(),
	);
	$home_name   = mats_t( 'home' );
	$home_url    = mats_home_url( $lang );
	$site_desc   = mats_seo_clean( mats_setting( 'seo_description' ) );
	$news_id     = mats_seo_news_page_id();
	$news_crumb  = array();
	if ( $news_id ) {
		$news_label = mats_seo_role_label( 'news' );
		$news_crumb = array( '' !== $news_label ? $news_label : mats_seo_clean( get_the_title( $news_id ) ), (string) get_permalink( $news_id ) );
	}
	$translations = static function ( array $ids ) use ( $lang ): array {
		$out = array();
		foreach ( $ids as $l => $id ) {
			$l = mats_normalize_lang( $l );
			if ( $l !== $lang && $id ) {
				$out[] = $l;
			}
		}
		return $out;
	};

	if ( is_singular() ) {
		$post = get_queried_object();
		if ( $post instanceof WP_Post ) {
			$id            = (int) $post->ID;
			$ctx['post']   = $post;
			$ctx['role']   = function_exists( 'mats_role_of' ) ? mats_role_of( $id ) : '';
			$ctx['url']    = (string) wp_get_canonical_url( $post );
			$ctx['image']  = mats_seo_image( $id );
			$plain_title   = mats_seo_clean( $post->post_title );
			$meta_title    = mats_seo_meta( $id, 'title' );
			$meta_desc     = mats_seo_meta( $id, 'description' );
			$excerpt       = post_password_required( $post ) ? '' : mats_seo_clean( $post->post_excerpt );
			$is_front      = is_front_page() || mats_seo_is_front_post( $id );
			$ctx['locales'] = function_exists( 'pll_get_post_translations' ) ? $translations( (array) pll_get_post_translations( $id ) ) : array();

			if ( $is_front ) {
				$ctx['kind']        = 'front';
				$ctx['title']       = '' !== $meta_title ? $meta_title : mats_seo_clean( mats_setting( 'seo_title' ) );
				$ctx['description'] = '' !== $meta_desc ? $meta_desc : $site_desc;
			} elseif ( 'post' === $post->post_type ) {
				$ctx['kind']        = 'post';
				$ctx['og_type']     = 'article';
				$ctx['title']       = '' !== $meta_title ? $meta_title : mats_seo_with_brand( $plain_title );
				$ctx['description'] = '' !== $meta_desc ? $meta_desc : ( '' !== $excerpt ? $excerpt : ( post_password_required( $post ) ? '' : mats_seo_text_excerpt( $post ) ) );
				$ctx['breadcrumb']  = array_values( array_filter( array( array( $home_name, $home_url ), $news_crumb, array( $plain_title, $ctx['url'] ) ) ) );
			} else {
				$ctx['kind']        = 'page';
				$ctx['title']       = '' !== $meta_title ? $meta_title : mats_seo_with_brand( $plain_title );
				$ctx['description'] = '' !== $meta_desc ? $meta_desc : $excerpt;
				$label              = mats_seo_role_label( $ctx['role'] );
				$ctx['breadcrumb']  = array( array( $home_name, $home_url ), array( '' !== $label ? $label : $plain_title, $ctx['url'] ) );
			}
			if ( '' === $ctx['description'] ) {
				$ctx['description'] = $site_desc;
			}
		}
	} elseif ( is_home() ) {
		/* the News page (or the latest posts on the front page) */
		$page = get_queried_object();
		$page = $page instanceof WP_Post ? $page : ( $news_id && ! is_front_page() ? get_post( $news_id ) : null );
		if ( $page instanceof WP_Post ) {
			$id                 = (int) $page->ID;
			$ctx['kind']        = 'blog';
			$ctx['post']        = $page;
			$ctx['role']        = 'news';
			$first              = (string) get_permalink( $page );
			$meta_title         = mats_seo_meta( $id, 'title' );
			$meta_desc          = mats_seo_meta( $id, 'description' );
			$ctx['title']       = ( '' !== $meta_title ? $meta_title : mats_seo_with_brand( mats_seo_clean( $page->post_title ) ) ) . mats_seo_paged_suffix();
			$ctx['description'] = '' !== $meta_desc ? $meta_desc : mats_seo_clean( $page->post_excerpt );
			$ctx['image']       = mats_seo_image( $id );
			$ctx['locales']     = function_exists( 'pll_get_post_translations' ) ? $translations( (array) pll_get_post_translations( $id ) ) : array();
			$ctx['breadcrumb']  = array( array( $home_name, $home_url ), array( $news_crumb[0] ?? mats_seo_clean( $page->post_title ), $first ) );
		} else {
			$ctx['kind']  = 'front';
			$first        = $home_url;
			$ctx['title'] = mats_seo_clean( mats_setting( 'seo_title' ) ) . mats_seo_paged_suffix();
		}
		$ctx['url']       = mats_seo_paged_url( $first );
		$ctx['canonical'] = $ctx['url'];
		if ( '' === $ctx['description'] ) {
			$ctx['description'] = $site_desc;
		}
	} elseif ( is_category() || is_tag() ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			$link               = get_term_link( $term );
			$first              = is_wp_error( $link ) ? $home_url : (string) $link;
			$name               = mats_seo_clean( $term->name );
			$ctx['kind']        = 'term';
			$ctx['title']       = mats_seo_with_brand( $name ) . mats_seo_paged_suffix();
			$ctx['description'] = mats_seo_clean( $term->description );
			$ctx['url']         = mats_seo_paged_url( $first );
			$ctx['canonical']   = $ctx['url'];
			$ctx['locales']     = function_exists( 'pll_get_term_translations' ) ? $translations( (array) pll_get_term_translations( $term->term_id ) ) : array();
			$middle             = ( $news_crumb && 0 !== strcasecmp( $news_crumb[0], $name ) ) ? $news_crumb : array(); // no "News → News"
			$ctx['breadcrumb']  = array_values( array_filter( array( array( $home_name, $home_url ), $middle, array( $name, $first ) ) ) );
			if ( '' === $ctx['description'] ) {
				$ctx['description'] = $site_desc;
			}
		}
	} elseif ( is_search() ) {
		$ctx['kind']  = 'search';
		$quotes       = 'es' === $lang ? array( '«', '»' ) : array( '“', '”' );
		$ctx['title'] = mats_seo_with_brand( mats_t( 'search_for' ) . ' ' . $quotes[0] . mats_seo_clean( get_search_query( false ) ) . $quotes[1] );
	} elseif ( is_404() ) {
		$ctx['kind']  = '404';
		$ctx['title'] = mats_seo_with_brand( mats_t( 'not_found' ) );
	}

	if ( ! $ctx['image'] && in_array( $ctx['kind'], array( 'front', 'term' ), true ) ) {
		$ctx['image'] = mats_seo_image();
	}
	if ( ! function_exists( 'pll_get_post_translations' ) || ! in_array( $ctx['kind'], array( 'front', 'page', 'post', 'blog', 'term' ), true ) ) {
		$ctx['locales'] = array();
	} elseif ( 'front' === $ctx['kind'] && ! $ctx['post'] ) {
		$ctx['locales'] = array_values( array_diff( array_map( 'mats_normalize_lang', (array) pll_languages_list( array( 'fields' => 'slug' ) ) ), array( $lang ) ) );
	}
	return $ctx;
}

/* ═══════════════════════════════════════════════════════════════════════
 * <head>: title, description, canonical, Open Graph, Twitter
 * ═════════════════════════════════════════════════════════════════════ */

add_filter(
	'pre_get_document_title',
	static function ( $title ) {
		if ( ! mats_seo_active() || is_admin() || is_feed() ) {
			return $title;
		}
		$ctx = mats_seo_context();
		return '' !== $ctx['title'] ? esc_html( $ctx['title'] ) : $title;
	},
	20
);

/* 404s are never indexed (search results already are not: core) */
add_filter(
	'wp_robots',
	static function ( array $robots ): array {
		if ( mats_seo_active() && is_404() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	},
	20
);

/** Prints the meta tags of the current request. */
function mats_seo_head(): void {
	if ( ! mats_seo_active() ) {
		return;
	}
	$ctx = mats_seo_context();
	if ( in_array( $ctx['kind'], array( 'search', '404', 'other' ), true ) ) {
		return;
	}
	$lang  = $ctx['lang'];
	$title = $ctx['title'];
	$desc  = $ctx['description'];
	$image = $ctx['image'];
	$out   = array();

	if ( '' !== $desc ) {
		$out[] = sprintf( '<meta name="description" content="%s" />', esc_attr( $desc ) );
	}
	/* core prints rel=canonical for pages and articles; lists get theirs here */
	if ( '' !== $ctx['canonical'] && ! is_singular() ) {
		$out[] = sprintf( '<link rel="canonical" href="%s" />', esc_url( $ctx['canonical'] ) );
	}

	$og = array(
		'og:type'      => $ctx['og_type'],
		'og:site_name' => mats_seo_site_name(),
		'og:title'     => $title,
		'og:description' => $desc,
		'og:url'       => $ctx['url'],
	);
	foreach ( $og as $property => $content ) {
		if ( '' !== (string) $content ) {
			$out[] = sprintf( '<meta property="%s" content="%s" />', esc_attr( $property ), 'og:url' === $property ? esc_url( $content ) : esc_attr( $content ) );
		}
	}
	if ( ! empty( $image['url'] ) ) {
		$out[] = sprintf( '<meta property="og:image" content="%s" />', esc_url( $image['url'] ) );
		if ( ! empty( $image['width'] ) && ! empty( $image['height'] ) ) {
			$out[] = sprintf( '<meta property="og:image:width" content="%d" />', (int) $image['width'] );
			$out[] = sprintf( '<meta property="og:image:height" content="%d" />', (int) $image['height'] );
		}
		if ( ! empty( $image['type'] ) ) {
			$out[] = sprintf( '<meta property="og:image:type" content="%s" />', esc_attr( $image['type'] ) );
		}
		if ( ! empty( $image['alt'] ) ) {
			$out[] = sprintf( '<meta property="og:image:alt" content="%s" />', esc_attr( $image['alt'] ) );
		}
	}
	$out[] = sprintf( '<meta property="og:locale" content="%s" />', esc_attr( mats_lang_meta( $lang )['og'] ) );
	foreach ( $ctx['locales'] as $alt_lang ) {
		$out[] = sprintf( '<meta property="og:locale:alternate" content="%s" />', esc_attr( mats_lang_meta( $alt_lang )['og'] ) );
	}
	if ( 'post' === $ctx['kind'] && $ctx['post'] instanceof WP_Post ) {
		$out[] = sprintf( '<meta property="article:published_time" content="%s" />', esc_attr( (string) get_post_time( 'c', true, $ctx['post'] ) ) );
		$out[] = sprintf( '<meta property="article:modified_time" content="%s" />', esc_attr( (string) get_post_modified_time( 'c', true, $ctx['post'] ) ) );
		$cats  = get_the_category( $ctx['post']->ID );
		if ( $cats ) {
			$out[] = sprintf( '<meta property="article:section" content="%s" />', esc_attr( mats_seo_clean( $cats[0]->name ) ) );
		}
	}

	$out[] = '<meta name="twitter:card" content="summary_large_image" />';
	$out[] = sprintf( '<meta name="twitter:title" content="%s" />', esc_attr( $title ) );
	if ( '' !== $desc ) {
		$out[] = sprintf( '<meta name="twitter:description" content="%s" />', esc_attr( $desc ) );
	}
	if ( ! empty( $image['url'] ) ) {
		$out[] = sprintf( '<meta name="twitter:image" content="%s" />', esc_url( $image['url'] ) );
		if ( ! empty( $image['alt'] ) ) {
			$out[] = sprintf( '<meta name="twitter:image:alt" content="%s" />', esc_attr( $image['alt'] ) );
		}
	}
	$sitemap = function_exists( 'get_sitemap_url' ) ? get_sitemap_url( 'index' ) : false;
	if ( $sitemap && get_option( 'blog_public' ) ) {
		$out[] = sprintf( '<link rel="sitemap" type="application/xml" href="%s" />', esc_url( $sitemap ) );
	}

	echo "\n" . implode( "\n", $out ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value escaped above.
}
add_action( 'wp_head', 'mats_seo_head', 1 );

/* Polylang prints the hreflang links; add x-default → the English version */
add_filter(
	'pll_rel_hreflang_attributes',
	static function ( $hreflangs ) {
		if ( ! is_array( $hreflangs ) || isset( $hreflangs['x-default'] ) || count( $hreflangs ) < 2 ) {
			return $hreflangs;
		}
		$default = mats_default_lang();
		foreach ( $hreflangs as $code => $url ) {
			if ( strtolower( substr( (string) $code, 0, 2 ) ) === $default ) {
				$hreflangs['x-default'] = $url;
				break;
			}
		}
		return $hreflangs;
	}
);

/* ═══════════════════════════════════════════════════════════════════════
 * JSON-LD (footer: the sections have collected their nodes by then)
 * ═════════════════════════════════════════════════════════════════════ */

/* our BlogPosting replaces the simpler one of the news module */
add_filter( 'mats_blog_jsonld', '__return_false', 20 );

/** The nodes of the current page. */
function mats_seo_graph(): array {
	$ctx   = mats_seo_context();
	$site  = mats_seo_site_url();
	$name  = mats_seo_site_name();
	$graph = array(
		array(
			'@type'      => 'WebSite',
			'@id'        => mats_seo_website_id(),
			'name'       => $name,
			'url'        => $site,
			'inLanguage' => mats_seo_languages(),
			'publisher'  => array( '@id' => mats_seo_org_id() ),
		),
		array(
			'@type'              => 'Organization',
			'@id'                => mats_seo_org_id(),
			'name'               => $name,
			'url'                => $site,
			'description'        => mats_seo_clean( mats_setting( 'seo_description' ) ),
			'parentOrganization' => array(
				'@type' => 'ResearchOrganization',
				'name'  => 'ICMAB-CSIC (Institut de Ciència de Materials de Barcelona)',
				'url'   => 'https://icmab.es',
			),
		),
	);

	if ( count( $ctx['breadcrumb'] ) > 1 ) {
		$items = array();
		foreach ( array_values( $ctx['breadcrumb'] ) as $i => $crumb ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => (string) $crumb[0],
				'item'     => (string) $crumb[1],
			);
		}
		$graph[] = array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $items,
		);
	}

	/* product.astro: the device itself */
	if ( 'page' === $ctx['kind'] && 'product' === $ctx['role'] ) {
		$product = array(
			'@type'       => 'Product',
			'name'        => $name,
			'description' => $ctx['description'],
			'brand'       => array( '@id' => mats_seo_org_id() ),
			'category'    => 'Laboratory equipment (research prototype, technology transfer phase)',
			'url'         => $ctx['url'],
		);
		if ( ! empty( $ctx['image']['url'] ) ) {
			$product['image'] = $ctx['image']['url'];
		}
		$graph[] = $product;
	}

	/* news article */
	if ( 'post' === $ctx['kind'] && $ctx['post'] instanceof WP_Post ) {
		$post    = $ctx['post'];
		$article = array(
			'@type'            => 'BlogPosting',
			'@id'              => $ctx['url'] . '#article',
			'headline'         => mats_seo_trim( mats_seo_clean( $post->post_title ), 110 ),
			'description'      => $ctx['description'],
			'url'              => $ctx['url'],
			'mainEntityOfPage' => array(
				'@type' => 'WebPage',
				'@id'   => $ctx['url'],
			),
			'datePublished'    => (string) get_post_time( 'c', true, $post ),
			'dateModified'     => (string) get_post_modified_time( 'c', true, $post ),
			'inLanguage'       => mats_lang_meta( $ctx['lang'] )['html'],
			'isPartOf'         => array( '@id' => mats_seo_website_id() ),
			'author'           => array(
				'@type' => 'Organization',
				'name'  => $name,
				'url'   => $site,
			),
			'publisher'        => array( '@id' => mats_seo_org_id() ),
		);
		if ( ! empty( $ctx['image']['url'] ) ) {
			$article['image'] = array_filter(
				array(
					'@type'  => 'ImageObject',
					'url'    => $ctx['image']['url'],
					'width'  => (int) ( $ctx['image']['width'] ?? 0 ),
					'height' => (int) ( $ctx['image']['height'] ?? 0 ),
				)
			);
		}
		$cats = get_the_category( $post->ID );
		if ( $cats ) {
			$article['articleSection'] = mats_seo_clean( $cats[0]->name );
		}
		$graph[] = $article;
	}

	/* nodes collected by the sections (ScholarlyArticle, Person…) */
	foreach ( mats_jsonld_nodes() as $node ) {
		if ( is_array( $node ) && $node ) {
			$graph[] = $node;
		}
	}
	return (array) apply_filters( 'mats_seo_graph', mats_seo_unique_nodes( $graph ), $ctx );
}

/** Drops exact duplicates (a section placed twice…). */
function mats_seo_unique_nodes( array $nodes ): array {
	$seen = array();
	$out  = array();
	foreach ( $nodes as $node ) {
		$key = md5( (string) wp_json_encode( $node ) );
		if ( ! isset( $seen[ $key ] ) ) {
			$seen[ $key ] = true;
			$out[]        = $node;
		}
	}
	return $out;
}

/** <script type="application/ld+json"> with a @graph ('' when empty). */
function mats_seo_jsonld_tag( array $graph ): string {
	if ( ! $graph ) {
		return '';
	}
	$json = wp_json_encode(
		array(
			'@context' => 'https://schema.org',
			'@graph'   => array_values( $graph ),
		),
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
	);
	return $json ? '<script type="application/ld+json">' . $json . "</script>\n" : '';
}

add_action(
	'wp_footer',
	static function () {
		if ( is_feed() || is_embed() ) {
			return;
		}
		if ( mats_seo_active() ) {
			echo mats_seo_jsonld_tag( mats_seo_graph() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with < and > escaped.
			return;
		}
		if ( '' === mats_seo_plugin() ) {
			return; // switched off on purpose (mats_seo_enabled)
		}
		/* an SEO plugin prints the site, page, breadcrumb and article nodes:
		   only add what the sections know and the plugins do not */
		$skip  = array( 'WebSite', 'WebPage', 'Organization', 'BreadcrumbList', 'Article', 'BlogPosting', 'NewsArticle' );
		$nodes = array_filter(
			mats_jsonld_nodes(),
			static fn( $node ) => is_array( $node ) && ! empty( $node['@type'] ) && ! in_array( $node['@type'], $skip, true )
		);
		echo mats_seo_jsonld_tag( mats_seo_unique_nodes( array_values( $nodes ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with < and > escaped.
	},
	20
);

/* ═══════════════════════════════════════════════════════════════════════
 * Sitemaps & robots.txt
 * ═════════════════════════════════════════════════════════════════════ */

/* pages and news only (the private records and the contacts never are public) */
add_filter(
	'wp_sitemaps_post_types',
	static function ( $types ) {
		return array_intersect_key( (array) $types, array_flip( array( 'page', 'post' ) ) );
	},
	20
);

/* news categories only (no tags, no formats) */
add_filter(
	'wp_sitemaps_taxonomies',
	static function ( $taxonomies ) {
		return array_intersect_key( (array) $taxonomies, array( 'category' => true ) );
	},
	20
);

/* no users sitemap (also in the security module) */
add_filter(
	'wp_sitemaps_add_provider',
	static function ( $provider, $name ) {
		return 'users' === $name ? false : $provider;
	},
	20,
	2
);

/* the sitemap in robots.txt (core adds it when sitemaps are on; never twice) */
add_filter(
	'robots_txt',
	static function ( $output, $public ) {
		if ( ! $public || false !== stripos( (string) $output, 'sitemap:' ) || ! function_exists( 'get_sitemap_url' ) ) {
			return $output;
		}
		$url = get_sitemap_url( 'index' );
		if ( $url ) {
			$output = rtrim( (string) $output ) . "\n\nSitemap: " . esc_url_raw( $url ) . "\n";
		}
		return $output;
	},
	20,
	2
);

/* ═══════════════════════════════════════════════════════════════════════
 * Social image size (1200×630), made only for featured images of pages
 * and news — not for every photo of the media library
 * ═════════════════════════════════════════════════════════════════════ */

/** Adds the 1200×630 crop ('mats-og') to an image that does not have it. */
function mats_seo_ensure_og_size( int $attachment_id ): void {
	if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
		return;
	}
	$meta = wp_get_attachment_metadata( $attachment_id );
	if ( ! is_array( $meta ) || isset( $meta['sizes']['mats-og'] ) || empty( $meta['width'] ) || empty( $meta['height'] ) ) {
		return;
	}
	$orig_w = (int) $meta['width'];
	$orig_h = (int) $meta['height'];
	if ( $orig_w < 600 || $orig_h < 315 ) {
		return; // too small: the original is used as it is
	}
	/* the biggest 1200×630-shaped crop the photo allows (never enlarged) */
	$w = min( 1200, $orig_w );
	$h = (int) round( $w * 630 / 1200 );
	if ( $h > $orig_h ) {
		$h = $orig_h;
		$w = (int) round( $h * 1200 / 630 );
	}
	if ( $w === $orig_w && $h === $orig_h ) {
		return; // already the right shape
	}
	$file = get_attached_file( $attachment_id );
	if ( ! $file || ! file_exists( $file ) ) {
		return;
	}
	$resized = image_make_intermediate_size( $file, $w, $h, true );
	if ( ! $resized ) {
		return;
	}
	$meta['sizes']            = is_array( $meta['sizes'] ?? null ) ? $meta['sizes'] : array();
	$meta['sizes']['mats-og'] = $resized;
	wp_update_attachment_metadata( $attachment_id, $meta );
}

/* when a featured image is chosen for a page or a news article */
$mats_seo_thumb_hook = static function ( $meta_id, $object_id, $meta_key, $meta_value ) {
	if ( '_thumbnail_id' !== $meta_key || ! in_array( get_post_type( (int) $object_id ), array( 'post', 'page' ), true ) ) {
		return;
	}
	mats_seo_ensure_og_size( (int) $meta_value );
};
add_action( 'added_post_meta', $mats_seo_thumb_hook, 10, 4 );
add_action( 'updated_post_meta', $mats_seo_thumb_hook, 10, 4 );
unset( $mats_seo_thumb_hook );

/* ═══════════════════════════════════════════════════════════════════════
 * Editor: "Google y redes sociales"
 * ═════════════════════════════════════════════════════════════════════ */

function mats_seo_sanitize_title( $value ): string {
	return mb_substr( sanitize_text_field( (string) $value ), 0, 200 );
}

function mats_seo_sanitize_description( $value ): string {
	return mb_substr( trim( (string) preg_replace( '/\s+/u', ' ', sanitize_textarea_field( (string) $value ) ) ), 0, 500 );
}

add_action(
	'init',
	static function () {
		$fields = array(
			'_mats_seo_title'       => 'mats_seo_sanitize_title',
			'_mats_seo_description' => 'mats_seo_sanitize_description',
		);
		foreach ( array( 'page', 'post' ) as $type ) {
			foreach ( $fields as $key => $sanitize ) {
				register_post_meta(
					$type,
					$key,
					array(
						'type'              => 'string',
						'single'            => true,
						'default'           => '',
						'show_in_rest'      => true,
						'sanitize_callback' => $sanitize,
						'auth_callback'     => static fn( $allowed, $meta_key, $post_id ) => current_user_can( 'edit_post', (int) $post_id ),
					)
				);
			}
		}
	},
	20
);

/** Data for the editor panel of a post. */
function mats_seo_editor_data( WP_Post $post ): array {
	$lang = function_exists( 'mats_post_lang' ) ? mats_post_lang( $post->ID ) : 'en';
	return array(
		'keys'      => array(
			'title'       => '_mats_seo_title',
			'description' => '_mats_seo_description',
		),
		'max'       => array(
			'title'       => MATS_SEO_TITLE_MAX,
			'description' => MATS_SEO_DESC_MAX,
		),
		'brand'     => mats_seo_site_name(),
		'sep'       => MATS_SEO_SEP,
		'postType'  => $post->post_type,
		'isFront'   => mats_seo_is_front_post( (int) $post->ID ),
		'siteTitle' => mats_seo_clean( mats_setting( 'seo_title', $lang ) ),
		'siteDesc'  => mats_seo_clean( mats_setting( 'seo_description', $lang ) ),
		'home'      => mats_home_url( $lang ),
	);
}

add_action(
	'enqueue_block_editor_assets',
	static function () {
		if ( ! mats_seo_active() ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$post   = get_post();
		if ( ! $screen || 'post' !== $screen->base || ! $post instanceof WP_Post || ! in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
			return;
		}
		wp_enqueue_script(
			'matsurfer-seo-admin',
			MATS_URI . '/assets/js/seo-admin.js',
			array( 'wp-plugins', 'wp-editor', 'wp-element', 'wp-components', 'wp-data' ),
			mats_ver( 'assets/js/seo-admin.js' ),
			true
		);
		wp_add_inline_script( 'matsurfer-seo-admin', 'window.matsSeo = ' . wp_json_encode( mats_seo_editor_data( $post ) ) . ';', 'before' );
		wp_register_style( 'matsurfer-seo-admin', false, array(), MATS_VERSION );
		wp_enqueue_style( 'matsurfer-seo-admin' );
		wp_add_inline_style( 'matsurfer-seo-admin', mats_seo_admin_css() );
	}
);

/** Styles of the panel (and of the classic-editor box). */
function mats_seo_admin_css(): string {
	return '.mats-seo__intro{margin:0 0 12px;color:#50575e}'
		. '.mats-seo__preview{border:1px solid #dcdcde;border-radius:8px;background:#fff;padding:12px 14px;margin:0 0 16px;font-family:arial,sans-serif;overflow-wrap:anywhere}'
		. '.mats-seo__label{display:block;margin:0 0 6px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#757575;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}'
		. '.mats-seo__site{font-size:12px;line-height:1.4;color:#202124}'
		. '.mats-seo__url{font-size:12px;line-height:1.4;color:#4d5156}'
		. '.mats-seo__ptitle{margin:4px 0 3px;font-size:17px;line-height:1.3;color:#1a0dab}'
		. '.mats-seo__pdesc{margin:0;font-size:13px;line-height:1.5;color:#4d5156}'
		. '.mats-seo__pdesc.is-auto{font-style:italic}'
		. '.mats-seo__field{margin:0 0 16px}'
		. '.mats-seo__count{margin:4px 0 0;font-size:12px;line-height:1.4}'
		. '.mats-seo__count.is-ok{color:#007017}.mats-seo__count.is-warn{color:#996800}.mats-seo__count.is-empty{color:#757575}'
		. '.mats-seo__note{margin:0;font-size:12px;color:#50575e}'
		. '.mats-seo-box input[type=text],.mats-seo-box textarea{width:100%}';
}

/*
 * Classic editor fallback (only shown when a page is edited without the
 * block editor; the block editor uses the sidebar panel instead).
 */
add_action(
	'add_meta_boxes',
	static function ( $post_type ) {
		if ( ! mats_seo_active() || ! in_array( $post_type, array( 'page', 'post' ), true ) ) {
			return;
		}
		add_meta_box( 'mats-seo', 'Google y redes sociales', 'mats_seo_metabox', $post_type, 'side', 'default', array( '__back_compat_meta_box' => true ) );
	}
);

function mats_seo_metabox( WP_Post $post ): void {
	$data  = mats_seo_editor_data( $post );
	$title = (string) get_post_meta( $post->ID, '_mats_seo_title', true );
	$desc  = (string) get_post_meta( $post->ID, '_mats_seo_description', true );
	wp_nonce_field( 'mats_seo_save_' . $post->ID, 'mats_seo_nonce' );
	echo '<style>' . mats_seo_admin_css() . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static CSS.
	echo '<div class="mats-seo-box">';
	echo '<p class="mats-seo__intro">' . esc_html( 'Cómo se verá en Google y al compartirla en redes. Si dejas un campo vacío, la web pone un texto automático.' ) . '</p>';
	printf(
		'<p class="mats-seo__field"><label for="mats-seo-title"><strong>%1$s</strong></label><br><input type="text" id="mats-seo-title" name="mats_seo_title" value="%2$s" placeholder="%3$s"><span class="description">%4$s</span></p>',
		esc_html( 'Título en Google' ),
		esc_attr( $title ),
		esc_attr( $data['isFront'] ? $data['siteTitle'] : mats_seo_with_brand( mats_seo_clean( $post->post_title ) ) ),
		esc_html( sprintf( 'Recomendado: %d caracteres como máximo.', MATS_SEO_TITLE_MAX ) )
	);
	printf(
		'<p class="mats-seo__field"><label for="mats-seo-description"><strong>%1$s</strong></label><br><textarea id="mats-seo-description" name="mats_seo_description" rows="4" placeholder="%2$s">%3$s</textarea><span class="description">%4$s</span></p>',
		esc_html( 'Descripción en Google' ),
		esc_attr( 'post' === $post->post_type ? 'Vacío: se usa el extracto o el principio del texto.' : $data['siteDesc'] ),
		esc_textarea( $desc ),
		esc_html( sprintf( 'Recomendado: entre 50 y %d caracteres.', MATS_SEO_DESC_MAX ) )
	);
	echo '</div>';
}

add_action(
	'save_post',
	static function ( $post_id, $post ) {
		if ( ! isset( $_POST['mats_seo_nonce'] ) || ! $post instanceof WP_Post ) {
			return; // block editor (REST) saves go through the registered meta
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mats_seo_nonce'] ) ), 'mats_seo_save_' . $post_id ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$title = mats_seo_sanitize_title( wp_unslash( $_POST['mats_seo_title'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by the callback.
		$desc  = mats_seo_sanitize_description( wp_unslash( $_POST['mats_seo_description'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by the callback.
		foreach ( array( '_mats_seo_title' => $title, '_mats_seo_description' => $desc ) as $key => $value ) {
			if ( '' === $value ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}
	},
	10,
	2
);

/* the person installing the site is told why the theme's SEO is off */
add_action(
	'admin_notices',
	static function () {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( '' === mats_seo_plugin() || ! $screen || 'plugins' !== $screen->id || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-info"><p>%s</p></div>',
			esc_html(
				sprintf(
					'%s está activo: el SEO que trae el tema MatSurfer (títulos, descripciones, Open Graph y datos estructurados) se ha desactivado para no repetir etiquetas. Los títulos y descripciones ya escritos en «Google y redes sociales» no se pasan solos al plugin.',
					mats_seo_plugin()
				)
			)
		);
	}
);
