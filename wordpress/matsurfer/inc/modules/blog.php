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

/**
 * Closing CTA band of the News page, the archives, search and every article.
 * Copy: Ajustes de la web → «Cierre de las noticias» (blog_cta_title,
 * blog_cta_text, blog_cta_button, per language); an emptied field falls back
 * to the home's closing copy. The button always goes to Contact.
 */
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
	<nav class="blog-pager" aria-label="<?php echo esc_attr( mats_t( 'blog_pagination' ) ); ?>">
		<?php if ( $prev ) : ?>
			<a class="blog-pager__link blog-pager__link--prev" href="<?php echo esc_url( $prev ); ?>" rel="prev"><span aria-hidden="true">←</span> <?php echo esc_html( mats_t( 'newer' ) ); ?></a>
		<?php else : ?>
			<span class="blog-pager__link is-disabled" aria-hidden="true"><span>←</span> <?php echo esc_html( mats_t( 'newer' ) ); ?></span>
		<?php endif; ?>
		<p class="blog-pager__status mono"><?php echo esc_html( sprintf( mats_t( 'page_of' ), $paged, $total ) ); ?></p>
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

/* ── search: match what visitors can read, not the page's markup ──────── */

/*
 * WordPress compares the search words with the raw post_content. For pages
 * built from sections that is mostly block-attribute JSON ("kicker",
 * "label", "page", image file names…), and for articles it includes block
 * comments and HTML ("wp:paragraph", "class", "size-large"…), so common
 * words returned every page. Here:
 *   - articles and reading pages: WordPress' own SQL finds the candidates,
 *     then each one is checked again against its visible text;
 *   - pages built from sections: matched on their title, excerpt,
 *     search-engine description and the texts typed in their sections.
 * Only the site's main search is affected (not the admin, not the REST API).
 */

/** Visible words of a post: title, excerpt, the text of its blocks and the texts typed in MatSurfer sections. */
function mats_blog_search_text( WP_Post $post ): string {
	$content = (string) $post->post_content;
	$parts   = array( (string) $post->post_title, (string) $post->post_excerpt );
	if ( false !== strpos( $content, '<!-- wp:matsurfer/' ) ) {
		mats_blog_section_texts( parse_blocks( $content ), $parts );
	}
	/* block comments (and the attributes inside them), tags and their attribute values are not read by visitors */
	$parts[] = wp_strip_all_tags( (string) preg_replace( '/<!--.*?-->/s', ' ', strip_shortcodes( $content ) ) );
	if ( 'page' === $post->post_type ) {
		$parts[] = (string) get_post_meta( $post->ID, '_mats_seo_description', true );
	}
	return implode( ' ', $parts );
}

/** Collects the texts typed in MatSurfer sections (their field schema says which attributes are copy). */
function mats_blog_section_texts( array $blocks, array &$parts ): void {
	$registry = mats_blocks();
	foreach ( $blocks as $block ) {
		$name = (string) ( $block['blockName'] ?? '' );
		if ( isset( $registry[ $name ] ) ) {
			mats_blog_field_texts( (array) ( $registry[ $name ]['fields'] ?? array() ), (array) ( $block['attrs'] ?? array() ), $parts );
		}
		if ( ! empty( $block['innerBlocks'] ) ) {
			mats_blog_section_texts( (array) $block['innerBlocks'], $parts );
		}
	}
}

/** Texts of one set of field values (recurses into repeaters). */
function mats_blog_field_texts( array $fields, array $values, array &$parts ): void {
	foreach ( $fields as $field ) {
		$key = (string) ( $field['key'] ?? '' );
		if ( '' === $key || ! array_key_exists( $key, $values ) ) {
			continue;
		}
		$value = $values[ $key ];
		switch ( $field['type'] ?? 'text' ) {
			case 'text':
			case 'textarea':
				if ( is_scalar( $value ) ) {
					$parts[] = (string) $value;
				}
				break;
			case 'list':
				foreach ( (array) $value as $item ) {
					if ( is_scalar( $item ) ) {
						$parts[] = (string) $item;
					}
				}
				break;
			case 'link':
				if ( empty( $field['noLabel'] ) && is_array( $value ) && isset( $value['label'] ) && is_scalar( $value['label'] ) ) {
					$parts[] = (string) $value['label'];
				}
				break;
			case 'repeater':
				foreach ( (array) $value as $item ) {
					if ( is_array( $item ) ) {
						mats_blog_field_texts( (array) ( $field['fields'] ?? array() ), $item, $parts );
					}
				}
				break;
		}
	}
}

/** Lower case, no accents, entities decoded, single spaces: how words are compared. */
function mats_blog_search_normalize( string $text ): string {
	$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = remove_accents( $text );
	$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
}

/** Does this visible text contain every search word (and none of the "-excluded" ones)? */
function mats_blog_search_matches( string $text, array $terms ): bool {
	$haystack = mats_blog_search_normalize( $text );
	$prefix   = (string) apply_filters( 'wp_query_search_exclusion_prefix', '-' );
	foreach ( $terms as $term ) {
		$term    = (string) $term;
		$exclude = '' !== $prefix && str_starts_with( $term, $prefix );
		$needle  = mats_blog_search_normalize( $exclude ? substr( $term, strlen( $prefix ) ) : $term );
		if ( '' === $needle ) {
			continue;
		}
		if ( str_contains( $haystack, $needle ) === $exclude ) {
			return false;
		}
	}
	return true;
}

/** IDs of the pages built from sections (every language), once per request. */
function mats_blog_landing_ids(): array {
	static $ids = null;
	if ( null === $ids ) {
		$ids   = array();
		$pages = get_posts(
			array(
				'post_type'        => 'page',
				'post_status'      => array( 'publish', 'private' ),
				'posts_per_page'   => -1,
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'lang'             => '',
			)
		);
		foreach ( $pages as $page ) {
			if ( mats_is_landing( $page ) ) {
				$ids[] = (int) $page->ID;
			}
		}
	}
	return $ids;
}

add_filter(
	'posts_search',
	static function ( $search, $query ) {
		global $wpdb;
		if ( '' === trim( (string) $search ) || is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() || ! $query->is_search() || $query->get( 'exact' ) ) {
			return $search;
		}
		$terms = array_filter( array_map( 'strval', (array) $query->get( 'search_terms' ) ), 'strlen' );
		if ( ! $terms ) {
			return $search;
		}
		$types   = array_values( array_intersect( (array) $query->get( 'post_type' ), array( 'post', 'page' ) ) );
		$types   = $types ? $types : array( 'post', 'page' );
		$landing = mats_blog_landing_ids();
		$lang    = function_exists( 'pll_get_post_language' ) ? mats_lang() : '';
		$ids     = array();

		/* pages built from sections: their visible copy, in the language being viewed */
		if ( in_array( 'page', $types, true ) ) {
			foreach ( $landing as $id ) {
				$page = get_post( $id );
				if ( ! $page || ( $lang && pll_get_post_language( $id ) !== $lang ) ) {
					continue;
				}
				if ( ! is_user_logged_in() && '' !== $page->post_password ) {
					continue;
				}
				if ( mats_blog_search_matches( mats_blog_search_text( $page ), $terms ) ) {
					$ids[] = $id;
				}
			}
		}

		/* articles and reading pages: WordPress' candidates, checked on their visible text */
		$type_sql = "'" . implode( "','", array_map( 'esc_sql', $types ) ) . "'";
		$not_in   = $landing ? ' AND ' . $wpdb->posts . '.ID NOT IN (' . implode( ',', array_map( 'intval', $landing ) ) . ')' : '';
		$limit    = max( 1, (int) apply_filters( 'mats_blog_search_check_limit', 1000 ) );
		// $search is the WHERE fragment WordPress just prepared for this query; the rest is fixed or integers.
		$rows = $wpdb->get_results( "SELECT {$wpdb->posts}.ID, {$wpdb->posts}.post_title, {$wpdb->posts}.post_excerpt, {$wpdb->posts}.post_content, {$wpdb->posts}.post_type FROM {$wpdb->posts} WHERE {$wpdb->posts}.post_type IN ({$type_sql}) AND {$wpdb->posts}.post_status IN ('publish','private'){$not_in} {$search} LIMIT " . ( $limit + 1 ) ); // phpcs:ignore WordPress.DB
		if ( ! is_array( $rows ) ) {
			return $search;
		}
		if ( count( $rows ) > $limit ) {
			/* too many to check one by one: keep WordPress' match for articles, ours for section pages */
			$core = (string) preg_replace( '/^\s*AND\s+/i', '', (string) $search );
			$own  = $ids ? " OR {$wpdb->posts}.ID IN (" . implode( ',', $ids ) . ')' : '';
			return " AND ( ( 1 = 1{$not_in} AND {$core} ){$own} )";
		}
		foreach ( $rows as $row ) {
			$post = new WP_Post( $row );
			if ( mats_blog_search_matches( mats_blog_search_text( $post ), $terms ) ) {
				$ids[] = (int) $row->ID;
			}
		}
		if ( ! $ids ) {
			return ' AND 1 = 0';
		}
		$out = " AND {$wpdb->posts}.ID IN (" . implode( ',', array_unique( $ids ) ) . ')';
		if ( ! is_user_logged_in() ) {
			$out .= " AND ({$wpdb->posts}.post_password = '')";
		}
		return $out;
	},
	10,
	2
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
