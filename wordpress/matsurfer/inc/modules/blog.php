<?php
/**
 * News (blog) module: everything the news templates need that is not
 * markup — helpers, assets, body classes, the news query and the editor
 * typography of news articles.
 *
 * Templates: home.php (News page), single.php (article), archive.php
 * (category / tag / date), search.php + searchform.php, 404.php, page.php
 * + template-parts/page-prose.php (reading pages: legal texts), the card
 * template-parts/post-card.php (also used by the "Últimas noticias"
 * section, blocks/latest-posts).
 *
 * Styles: assets/css/blog.css (headers, prose, pagination, article
 * layout) on these views and inside the block editor of articles and
 * reading pages; the card styles ship with blocks/latest-posts/style.css
 * and are pulled in on the news views through `mats_template_blocks`.
 *
 * Comments are switched off site-wide elsewhere; these templates never
 * render them. Open Graph images are handled by the SEO module.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

/* Cards per page on the news list, archives and search (3 × 3 grid). The
   first page of the News page shows one more: the newest article, wide. */
const MATS_BLOG_PER_PAGE = 9;

/**
 * Fixed UI words of the news templates. Uses mats_t() (inc/i18n.php) and
 * falls back to the local copy for keys that core does not know yet.
 */
function mats_blog_t( string $key, string $lang = '' ): string {
	$value = mats_t( $key, $lang );
	if ( $value !== $key ) {
		return $value;
	}
	static $local = array(
		'no_news'          => array(
			'en' => 'There are no news articles yet. Check back soon.',
			'es' => 'Todavía no hay noticias. Vuelve pronto.',
		),
		'no_news_title'    => array(
			'en' => 'Nothing published yet',
			'es' => 'Aún no hay nada publicado',
		),
		'previous_article' => array(
			'en' => 'Previous article',
			'es' => 'Artículo anterior',
		),
		'next_article'     => array(
			'en' => 'Next article',
			'es' => 'Artículo siguiente',
		),
		'page_of'          => array(
			'en' => 'Page %1$d of %2$d',
			'es' => 'Página %1$d de %2$d',
		),
		'results_count'    => array(
			'en' => '%d results',
			'es' => '%d resultados',
		),
		'result_one'       => array(
			'en' => '1 result',
			'es' => '1 resultado',
		),
		'search_label'     => array(
			'en' => 'Search the site',
			'es' => 'Buscar en la web',
		),
		'search_ph'        => array(
			'en' => 'Search news and pages…',
			'es' => 'Buscar noticias y páginas…',
		),
		'search_lead'      => array(
			'en' => 'Type a few words to find news articles and pages.',
			'es' => 'Escribe unas palabras para encontrar noticias y páginas.',
		),
		'tag'              => array(
			'en' => 'Topic',
			'es' => 'Tema',
		),
		'archive'          => array(
			'en' => 'Archive',
			'es' => 'Archivo',
		),
		'updated_on'       => array(
			'en' => 'Updated on',
			'es' => 'Actualizado el',
		),
		'on_this_page'     => array(
			'en' => 'On this page',
			'es' => 'En esta página',
		),
		'share_linkedin'   => array(
			'en' => 'Share on LinkedIn',
			'es' => 'Compartir en LinkedIn',
		),
		'share_email'      => array(
			'en' => 'Send by email',
			'es' => 'Enviar por email',
		),
		'useful_links'     => array(
			'en' => 'You may be looking for',
			'es' => 'Quizá buscabas',
		),
		'blog_pagination'  => array(
			'en' => 'More news',
			'es' => 'Más noticias',
		),
		'article_nav'      => array(
			'en' => 'More articles',
			'es' => 'Más artículos',
		),
	);
	if ( isset( $local[ $key ] ) ) {
		return (string) mats_pick( $local[ $key ], $lang ? $lang : mats_lang() );
	}
	return $key;
}

/** Is this request one of the news / reading views styled by blog.css? */
function mats_blog_is_view(): bool {
	if ( is_home() || is_singular( 'post' ) || is_archive() || is_search() || is_404() ) {
		return true;
	}
	return is_page() && ! mats_is_landing( get_queried_object_id() );
}

/** Reading time of an article in whole minutes (≥ 1), at 200 words a minute. */
function mats_reading_time( $post = null ): int {
	$post = get_post( $post );
	if ( ! $post ) {
		return 1;
	}
	/* the saved block markup minus tags and block comments = the words a visitor reads */
	$text  = wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) );
	$text  = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
	$words = preg_split( '/\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
	$count = is_array( $words ) ? count( $words ) : 0;
	return max( 1, (int) ceil( $count / 200 ) );
}

/** "4 min read" / "4 min de lectura". */
function mats_reading_time_label( $post = null ): string {
	return mats_reading_time( $post ) . ' ' . mats_t( 'min_read' );
}

/**
 * Publication date in the page language, independent of installed
 * WordPress translations: long "15 October 2026" / "15 de octubre de 2026",
 * short "15 Oct 2026" / "15 oct 2026".
 */
function mats_blog_date( $post = null, string $format = 'long', string $which = 'published' ): string {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	$time = 'modified' === $which ? get_post_modified_time( 'U', false, $post ) : get_post_time( 'U', false, $post );
	if ( ! $time ) {
		return '';
	}
	return mats_blog_format_date( (int) $time, $format );
}

/** Formats a timestamp (site time zone) like mats_blog_date(). */
function mats_blog_format_date( int $time, string $format = 'long', string $lang = '' ): string {
	$lang   = $lang ? $lang : mats_lang();
	$months = array(
		'en' => array( 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' ),
		'es' => array( 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre' ),
	);
	$tz    = wp_timezone();
	$day   = (int) wp_date( 'j', $time, $tz );
	$month = (int) wp_date( 'n', $time, $tz );
	$year  = wp_date( 'Y', $time, $tz );
	$name  = $months[ 'es' === $lang ? 'es' : 'en' ][ $month - 1 ];
	if ( 'short' === $format ) {
		$name = mb_substr( $name, 0, 3 );
		return $day . ' ' . $name . ' ' . $year;
	}
	if ( 'month' === $format ) {
		return 'es' === $lang ? ucfirst( $name ) . ' de ' . $year : $name . ' ' . $year;
	}
	return 'es' === $lang ? $day . ' de ' . $name . ' de ' . $year : $day . ' ' . $name . ' ' . $year;
}

/** The category shown on a card / article: the first one that is not the default, else the default. */
function mats_blog_category( $post = null ): ?WP_Term {
	$post = get_post( $post );
	if ( ! $post ) {
		return null;
	}
	$terms = get_the_category( $post->ID );
	if ( ! $terms ) {
		return null;
	}
	$default = (int) get_option( 'default_category' );
	if ( function_exists( 'pll_get_term' ) && $default ) {
		$default = (int) ( pll_get_term( $default, mats_post_lang( $post->ID ) ) ?: $default );
	}
	foreach ( $terms as $term ) {
		if ( (int) $term->term_id !== $default ) {
			return $term;
		}
	}
	return $terms[0];
}

/** The News page (posts page) in the current language, or null. */
function mats_blog_news_page(): ?WP_Post {
	$id = mats_page_id( 'news' );
	if ( ! $id ) {
		$id = (int) get_option( 'page_for_posts' );
		if ( $id && function_exists( 'pll_get_post' ) ) {
			$id = (int) ( pll_get_post( $id, mats_lang() ) ?: 0 );
		}
	}
	$post = $id ? get_post( $id ) : null;
	return ( $post && 'publish' === $post->post_status ) ? $post : null;
}

/** URL of the News list in the current language (home when there is none). */
function mats_blog_news_url(): string {
	$page = mats_blog_news_page();
	return $page ? (string) get_permalink( $page ) : mats_home_url();
}

/**
 * Header of the news views, drawn by the "Cabecera de página" section
 * (blocks/page-hero) so it is the exact PageHero of the other pages.
 * $args: kicker, kickerNote, title, lead, photo (image value), cta (link value), ctaSource.
 */
function mats_blog_hero( array $args ): string {
	$attrs = array(
		'kicker'     => (string) ( $args['kicker'] ?? '' ),
		'kickerNote' => (string) ( $args['kickerNote'] ?? '' ),
		'title'      => (string) ( $args['title'] ?? '' ),
		'lead'       => (string) ( $args['lead'] ?? '' ),
		'cta'        => (array) ( $args['cta'] ?? array() ),
		'photo'      => (array) ( $args['photo'] ?? array() ),
		'ctaSource'  => (string) ( $args['ctaSource'] ?? '' ),
		'headingId'  => 'page-title',
	);
	$html = WP_Block_Type_Registry::get_instance()->is_registered( 'matsurfer/page-hero' ) ? mats_render_section( 'matsurfer/page-hero', $attrs ) : '';
	if ( '' !== trim( $html ) ) {
		return $html;
	}
	/* fallback while the section is unavailable: same structure, no motion */
	$out  = '<section class="sec phero blog-hero-fallback" aria-labelledby="page-title"><div class="container">';
	$out .= $attrs['kicker'] ? '<p class="eyebrow">' . esc_html( $attrs['kicker'] ) . '</p>' : '';
	$out .= '<h1 class="phero__title" id="page-title">' . esc_html( $attrs['title'] ) . '</h1>';
	$out .= $attrs['lead'] ? '<p class="lead">' . esc_html( $attrs['lead'] ) . '</p>' : '';
	return $out . '</div></section>';
}

/** Closing CTA band of the news views (Ajustes de la web → Noticias, else the home's closing copy). */
function mats_blog_cta( string $source = 'news-final' ): string {
	$lang  = mats_lang();
	$final = mats_content_data()[ $lang ]['home']['finalCta'] ?? array();
	$title = trim( (string) mats_setting( 'blog_cta_title' ) );
	$text  = trim( (string) mats_setting( 'blog_cta_text' ) );
	$label = trim( (string) mats_setting( 'blog_cta_button' ) );
	$attrs = array(
		'title'     => '' !== $title ? $title : (string) ( $final['title'] ?? '' ),
		'text'      => '' !== $text ? $text : (string) ( $final['text'] ?? '' ),
		'button'    => array(
			'label' => '' !== $label ? $label : (string) ( $final['button'] ?? '' ),
			'page'  => 'contact',
			'url'   => '',
			'query' => '',
			'hash'  => '',
		),
		'ctaSource' => $source,
	);
	return WP_Block_Type_Registry::get_instance()->is_registered( 'matsurfer/page-cta' ) ? mats_render_section( 'matsurfer/page-cta', $attrs ) : '';
}

/** Featured image of a post as an image value for mats_image(), [] when none. */
function mats_blog_thumb( $post = null ): array {
	$post = get_post( $post );
	if ( ! $post || ! has_post_thumbnail( $post ) ) {
		return array();
	}
	return mats_attachment_value( (int) get_post_thumbnail_id( $post ) );
}

/** One post card (template-parts/post-card.php). $args: heading (h2|h3), featured (bool), lazy (bool). */
function mats_blog_card( $post, array $args = array() ): void {
	$post = get_post( $post );
	if ( ! $post ) {
		return;
	}
	get_template_part( 'template-parts/post-card', null, array_merge( $args, array( 'post' => $post ) ) );
}

/** Previous / next pagination of a list ('' when there is a single page). */
function mats_blog_pagination( ?WP_Query $query = null ): string {
	global $wp_query;
	$query = $query ? $query : $wp_query;
	$total = (int) $query->max_num_pages;
	if ( $total < 2 ) {
		return '';
	}
	$paged = max( 1, (int) get_query_var( 'paged' ) );
	$prev  = $paged > 1 ? get_previous_posts_page_link() : '';
	$next  = $paged < $total ? get_next_posts_page_link( $total ) : '';

	ob_start();
	?>
	<nav class="blog-pager" aria-label="<?php echo esc_attr( mats_blog_t( 'blog_pagination' ) ); ?>">
		<?php if ( $prev ) : ?>
			<a class="blog-pager__link blog-pager__link--prev" href="<?php echo esc_url( $prev ); ?>" rel="prev"><span aria-hidden="true">←</span> <?php echo esc_html( mats_t( 'newer' ) ); ?></a>
		<?php else : ?>
			<span class="blog-pager__link is-disabled" aria-hidden="true"><span>←</span> <?php echo esc_html( mats_t( 'newer' ) ); ?></span>
		<?php endif; ?>
		<p class="blog-pager__status mono"><?php echo esc_html( sprintf( mats_blog_t( 'page_of' ), $paged, $total ) ); ?></p>
		<?php if ( $next ) : ?>
			<a class="blog-pager__link blog-pager__link--next" href="<?php echo esc_url( $next ); ?>" rel="next"><?php echo esc_html( mats_t( 'older' ) ); ?> <span aria-hidden="true">→</span></a>
		<?php else : ?>
			<span class="blog-pager__link is-disabled" aria-hidden="true"><?php echo esc_html( mats_t( 'older' ) ); ?> <span>→</span></span>
		<?php endif; ?>
	</nav>
	<?php
	return (string) ob_get_clean();
}

/**
 * Adds ids to the h2 headings of rendered content and returns
 * [ html, [ [ id, text ], … ] ] for an "On this page" index.
 */
function mats_blog_heading_index( string $html ): array {
	if ( ! class_exists( 'WP_HTML_Tag_Processor' ) || false === stripos( $html, '<h2' ) ) {
		return array( $html, array() );
	}
	preg_match_all( '#<h2\b[^>]*>(.*?)</h2>#is', $html, $found );
	$texts = array_map( static fn( $inner ) => trim( wp_strip_all_tags( $inner ) ), $found[1] ?? array() );

	$tags  = new WP_HTML_Tag_Processor( $html );
	$index = array();
	$used  = array();
	$i     = 0;
	while ( $tags->next_tag( 'h2' ) ) {
		$text = $texts[ $i ] ?? '';
		++$i;
		if ( '' === $text ) {
			continue;
		}
		$id = (string) $tags->get_attribute( 'id' );
		if ( '' === $id ) {
			$base = sanitize_title( $text );
			$base = '' !== $base ? $base : 'section';
			$id   = $base;
			$n    = 2;
			while ( isset( $used[ $id ] ) ) {
				$id = $base . '-' . $n++;
			}
			$tags->set_attribute( 'id', $id );
		}
		$used[ $id ] = true;
		$index[]     = array( $id, $text );
	}
	return array( $tags->get_updated_html(), $index );
}

/* ── query: 9 cards a page, the News page's first page shows 10 ────────── */

add_action(
	'pre_get_posts',
	static function ( WP_Query $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( $query->is_search() ) {
			$query->set( 'post_type', array( 'post', 'page' ) );
			$query->set( 'posts_per_page', MATS_BLOG_PER_PAGE );
			return;
		}
		if ( $query->is_archive() && ! $query->is_post_type_archive() ) {
			$query->set( 'posts_per_page', MATS_BLOG_PER_PAGE );
			return;
		}
		if ( $query->is_home() ) {
			$paged = max( 1, (int) $query->get( 'paged' ) );
			if ( 1 === $paged ) {
				$query->set( 'posts_per_page', MATS_BLOG_PER_PAGE + 1 );
			} else {
				$query->set( 'posts_per_page', MATS_BLOG_PER_PAGE );
				$query->set( 'offset', MATS_BLOG_PER_PAGE + 1 + ( $paged - 2 ) * MATS_BLOG_PER_PAGE );
			}
			$query->set( 'mats_blog_home', 1 );
		}
	}
);

/* page count of the News page: the first page holds one card more */
add_filter(
	'found_posts',
	static function ( $found, WP_Query $query ) {
		if ( $query->get( 'mats_blog_home' ) && $found > MATS_BLOG_PER_PAGE + 1 ) {
			return (int) $found - 1;
		}
		return $found;
	},
	10,
	2
);

add_action(
	'wp',
	static function () {
		global $wp_query;
		if ( is_admin() || ! $wp_query instanceof WP_Query || ! $wp_query->get( 'mats_blog_home' ) ) {
			return;
		}
		$found = (int) $wp_query->found_posts;
		if ( $found <= MATS_BLOG_PER_PAGE + 1 ) {
			$wp_query->max_num_pages = $found ? 1 : 0;
		} else {
			/* found_posts is already reduced by one: pages of 9 after a first page of 10 */
			$wp_query->max_num_pages = (int) ceil( $found / MATS_BLOG_PER_PAGE );
		}
	}
);

/* ── assets ─────────────────────────────────────────────────────────── */

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( ! mats_blog_is_view() ) {
			return;
		}
		wp_enqueue_style( 'matsurfer-blog', MATS_URI . '/assets/css/blog.css', array( 'matsurfer-chrome' ), mats_ver( 'assets/css/blog.css' ) );
	},
	15
);

/* sections the news templates render outside the content: their styles in <head> */
add_filter(
	'mats_template_blocks',
	static function ( array $names ): array {
		if ( is_admin() ) {
			return $names;
		}
		if ( is_home() || is_archive() || is_search() || is_404() ) {
			$names[] = 'matsurfer/page-hero';
			$names[] = 'matsurfer/latest-posts'; // the card styles
		}
		if ( is_singular( 'post' ) ) {
			$names[] = 'matsurfer/page-cta';
		}
		if ( is_page() && ! mats_is_landing( get_queried_object_id() ) ) {
			$names[] = 'matsurfer/page-hero';
		}
		if ( is_home() || is_archive() || is_search() ) {
			$names[] = 'matsurfer/page-cta';
		}
		return $names;
	}
);

/* body classes for the news views */
add_filter(
	'body_class',
	static function ( array $classes ): array {
		if ( is_singular( 'post' ) ) {
			$classes[] = 'mats-blog';
			$classes[] = 'mats-blog--single';
		} elseif ( is_home() || is_archive() || is_search() ) {
			$classes[] = 'mats-blog';
			$classes[] = 'mats-blog--list';
		} elseif ( is_404() ) {
			$classes[] = 'mats-blog--404';
		} elseif ( is_page() ) {
			$classes[] = mats_is_landing( get_queried_object_id() ) ? 'mats-landing' : 'mats-prose-page';
		}
		return $classes;
	}
);

/*
 * A theme.json switches on WordPress' block templates for classic themes
 * ("Template → Create new template" in the editor). Our layouts are the PHP
 * templates; editors must not be able to replace them, so it stays off.
 */
add_action(
	'after_setup_theme',
	static function () {
		remove_theme_support( 'block-templates' );
	},
	20
);

/* the lead of reading pages and the News page: the page's excerpt */
add_action(
	'init',
	static function () {
		add_post_type_support( 'page', 'excerpt' );
	}
);

/* … but landing pages (built from sections) do not use it: no excerpt box there */
add_action(
	'load-post.php',
	static function () {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $post_id && 'page' === get_post_type( $post_id ) && mats_is_landing( $post_id ) && (int) get_option( 'page_for_posts' ) !== $post_id && mats_role_of( $post_id ) !== 'news' ) {
			remove_post_type_support( 'page', 'excerpt' );
		}
	}
);

/*
 * Block editor of news articles and reading pages: the canvas uses the
 * article typography (blog.css scopes its editor rules to
 * .editor-styles-wrapper). Landing pages keep the plain section preview.
 */
add_action(
	'enqueue_block_assets',
	static function () {
		if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! $screen->is_block_editor() ) {
			return;
		}
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$is_post = 'post' === $screen->post_type;
		$is_page = 'page' === $screen->post_type && ( ! $post_id || ! mats_is_landing( $post_id ) );
		if ( ! $is_post && ! $is_page ) {
			return;
		}
		wp_enqueue_style( 'matsurfer-blog-editor', MATS_URI . '/assets/css/blog.css', array(), mats_ver( 'assets/css/blog.css' ) );
	}
);

/* ── structured data: the article as a BlogPosting (filter to switch off) ── */

add_action(
	'wp',
	static function () {
		if ( ! is_singular( 'post' ) || ! function_exists( 'mats_jsonld_add' ) || ! apply_filters( 'mats_blog_jsonld', true ) ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		$node = array(
			'@type'            => 'BlogPosting',
			'headline'         => wp_strip_all_tags( get_the_title( $post ) ),
			'datePublished'    => get_post_time( 'c', true, $post ),
			'dateModified'     => get_post_modified_time( 'c', true, $post ),
			'mainEntityOfPage' => get_permalink( $post ),
			'inLanguage'       => mats_lang_meta( mats_post_lang( $post->ID ) )['html'],
			'publisher'        => array(
				'@type' => 'Organization',
				'name'  => 'MatSurfer · ICMAB-CSIC',
			),
		);
		if ( has_excerpt( $post ) ) {
			$node['description'] = wp_strip_all_tags( get_the_excerpt( $post ) );
		}
		$image = get_the_post_thumbnail_url( $post, 'mats-wide' );
		if ( $image ) {
			$node['image'] = $image;
		}
		mats_jsonld_add( $node );
	}
);
