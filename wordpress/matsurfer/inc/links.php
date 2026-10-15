<?php
/**
 * Page roles and link resolution.
 *
 * Every structural page has a ROLE (home, product, applications, research,
 * team, contact, news, privacy, legal). The importer stores the role on the
 * page (`_mats_role` post meta) and in the `mats_page_roles` option
 * (role => page ID in the default language). Links inside sections never
 * store URLs to our own pages: they store the role, and are resolved here
 * to the page in the language being rendered. Renaming a page or changing
 * its slug therefore never breaks a button.
 *
 * Link value stored in block attributes:
 *   [ 'label' => 'Talk to the team', 'page' => 'contact', 'url' => '',
 *     'query' => '?interest=product', 'hash' => '' ]
 *   - 'page' = a role, or 'email' (mailto: contact email)
 *   - 'url'  = any other address (external, PDF…). Wins when not empty.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

/** Roles, in menu order, with their admin (Spanish) names. */
function mats_page_roles(): array {
	return array(
		'home'         => 'Inicio',
		'product'      => 'Producto',
		'applications' => 'Aplicaciones',
		'research'     => 'Casos de estudio',
		'team'         => 'Equipo',
		'contact'      => 'Contacto',
		'news'         => 'Noticias',
		'privacy'      => 'Política de privacidad',
		'legal'        => 'Aviso legal',
	);
}

/** Page ID for a role in a language (translation via Polylang). 0 if none. */
function mats_page_id( string $role, string $lang = '' ): int {
	$lang  = $lang ?: mats_lang();
	$roles = get_option( 'mats_page_roles', array() );
	$id    = is_array( $roles ) ? (int) ( $roles[ $role ] ?? 0 ) : 0;

	if ( ! $id ) {
		/* fallbacks WordPress already knows about */
		if ( 'home' === $role ) {
			$id = (int) get_option( 'page_on_front' );
		} elseif ( 'news' === $role ) {
			$id = (int) get_option( 'page_for_posts' );
		} elseif ( 'privacy' === $role ) {
			$id = (int) get_option( 'wp_page_for_privacy_policy' );
		}
	}
	if ( ! $id ) {
		return 0;
	}
	if ( function_exists( 'pll_get_post' ) ) {
		$translated = pll_get_post( $id, $lang );
		if ( $translated ) {
			$id = (int) $translated;
		} elseif ( mats_post_lang( $id ) !== $lang ) {
			return 0; // no translation in that language
		}
	}
	return 'publish' === get_post_status( $id ) ? $id : 0;
}

/** URL of a role's page in a language; the language home when missing. */
function mats_page_url( string $role, string $lang = '' ): string {
	$lang = $lang ?: mats_lang();
	if ( 'home' === $role ) {
		return mats_home_url( $lang );
	}
	$id = mats_page_id( $role, $lang );
	return $id ? (string) get_permalink( $id ) : mats_home_url( $lang );
}

function mats_home_url( string $lang = '' ): string {
	$lang = $lang ?: mats_lang();
	if ( function_exists( 'pll_home_url' ) ) {
		$url = pll_home_url( $lang );
		if ( $url ) {
			return $url;
		}
	}
	return home_url( '/' );
}

/** Role of a page ID (any language), '' if it is not a structural page. */
function mats_role_of( $post_id ): string {
	$post_id = (int) $post_id;
	if ( ! $post_id ) {
		return '';
	}
	$role = (string) get_post_meta( $post_id, '_mats_role', true );
	if ( $role ) {
		return $role;
	}
	if ( function_exists( 'pll_get_post_translations' ) ) {
		foreach ( pll_get_post_translations( $post_id ) as $tr_id ) {
			$role = (string) get_post_meta( $tr_id, '_mats_role', true );
			if ( $role ) {
				return $role;
			}
		}
	}
	return '';
}

/** Resolve a stored link value (array, or plain string URL) to an href. */
function mats_link_url( $link, string $lang = '' ): string {
	if ( is_string( $link ) ) {
		return $link;
	}
	if ( ! is_array( $link ) ) {
		return '#';
	}
	$url = trim( (string) ( $link['url'] ?? '' ) );
	if ( '' !== $url ) {
		return $url;
	}
	$page = (string) ( $link['page'] ?? '' );
	if ( 'email' === $page ) {
		return 'mailto:' . mats_contact_email();
	}
	if ( '' === $page ) {
		$hash = (string) ( $link['hash'] ?? '' );
		return $hash ? $hash : '#';
	}
	$base  = mats_page_url( $page, $lang );
	$query = (string) ( $link['query'] ?? '' );
	$hash  = (string) ( $link['hash'] ?? '' );
	if ( $query && '?' !== $query[0] ) {
		$query = '?' . $query;
	}
	if ( $hash && '#' !== $hash[0] ) {
		$hash = '#' . $hash;
	}
	return $base . $query . $hash;
}

/** Link label, '' when the link has none. */
function mats_link_label( $link ): string {
	return is_array( $link ) ? trim( (string) ( $link['label'] ?? '' ) ) : '';
}

/** True when the URL leaves the site (gets target=_blank rel=noopener). */
function mats_is_external( string $url ): bool {
	if ( '' === $url || '#' === $url[0] || '/' === $url[0] || str_starts_with( $url, 'mailto:' ) || str_starts_with( $url, 'tel:' ) ) {
		return false;
	}
	$host = wp_parse_url( $url, PHP_URL_HOST );
	return $host && wp_parse_url( home_url(), PHP_URL_HOST ) !== $host;
}

/**
 * Attribute string for an <a>: href (escaped) + target/rel for external
 * links. Usage: <a class="btn" <?php echo mats_link_attrs( $link ); ?>>
 */
function mats_link_attrs( $link, string $lang = '' ): string {
	$url = mats_link_url( $link, $lang );
	$out = 'href="' . esc_url( $url ) . '"';
	if ( mats_is_external( $url ) ) {
		$out .= ' target="_blank" rel="noopener"';
	}
	return $out;
}

/** Is the current request showing the page of this role (for aria-current)? */
function mats_is_current_role( string $role ): bool {
	if ( 'news' === $role ) {
		return is_home() || is_singular( 'post' ) || is_category() || is_tag() || is_date();
	}
	if ( 'home' === $role ) {
		return is_front_page();
	}
	return is_page() && mats_role_of( get_queried_object_id() ) === $role;
}

/** Options for the "Destino" selector in the block editor. */
function mats_link_targets(): array {
	$out = array();
	foreach ( mats_page_roles() as $role => $name ) {
		$out[] = array(
			'value' => $role,
			'label' => 'Página: ' . $name,
		);
	}
	$out[] = array(
		'value' => 'email',
		'label' => 'Email de contacto',
	);
	return $out;
}
