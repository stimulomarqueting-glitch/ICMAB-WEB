<?php
/**
 * Rendering helpers shared by every block (render.php) and template.
 *
 * INLINE EDITING CONTRACT (block editor previews)
 * The editor shows each section exactly as the public page renders it. In
 * that preview, elements marked with mats_edit( 'path' ) become editable in
 * place, and images marked through mats_image( …, [ 'edit' => 'path' ] )
 * open the media library on click. `path` is the attribute path, using dots
 * for nesting: 'title', 'cta.label', 'items.2.text'.
 * Only mark an element when its text content is EXACTLY the attribute value
 * (no extra words, no transformations) — otherwise leave it unmarked and
 * the value is still editable in the side panel.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

/** True while rendering a block for the editor preview (REST block renderer). */
function mats_is_preview(): bool {
	return ! empty( $GLOBALS['mats_preview'] );
}

add_filter(
	'rest_request_before_callbacks',
	static function ( $response, $handler, $request ) {
		if ( $request instanceof WP_REST_Request && str_starts_with( $request->get_route(), '/wp/v2/block-renderer/' ) ) {
			$GLOBALS['mats_preview'] = true;
		}
		return $response;
	},
	10,
	3
);

/** ` data-mats-edit="path"` in editor previews, '' on the public site. */
function mats_edit( string $path ): string {
	return mats_is_preview() ? ' data-mats-edit="' . esc_attr( $path ) . '"' : '';
}

/** Escaped text of a string attribute. */
function mats_text( $value ): string {
	return esc_html( is_scalar( $value ) ? (string) $value : '' );
}

/** Attribute accessor with a default: mats_get( $attributes, 'cta.label' ). */
function mats_get( array $attributes, string $path, $default = '' ) {
	$node = $attributes;
	foreach ( explode( '.', $path ) as $segment ) {
		if ( is_object( $node ) ) {
			$node = (array) $node;
		}
		if ( is_array( $node ) && array_key_exists( $segment, $node ) ) {
			$node = $node[ $segment ];
		} else {
			return $default;
		}
	}
	return null === $node ? $default : $node;
}

/** Non-empty string check for optional elements. */
function mats_has( $value ): bool {
	if ( is_array( $value ) ) {
		return ! empty( array_filter( $value, static fn( $v ) => '' !== $v && null !== $v && array() !== $v ) );
	}
	return '' !== trim( (string) $value );
}

/** '01', '02'… step numbers. */
function mats_pad( int $n ): string {
	return str_pad( (string) $n, 2, '0', STR_PAD_LEFT );
}

/**
 * Words wrapped for the staggered "rise-in" headline animation.
 * `$wrap` is a sprintf pattern receiving (word, index); in the editor the
 * plain text is returned so it can be edited in place.
 *   echo mats_words( $title, '<span class="phero__word"><span class="rise-in" style="--i: %2$d">%1$s</span></span> ', 1 );
 */
function mats_words( string $text, string $wrap, int $start = 0 ): string {
	if ( mats_is_preview() ) {
		return esc_html( $text );
	}
	$out   = '';
	$words = preg_split( '/\s+/u', trim( $text ) ) ?: array();
	foreach ( $words as $i => $word ) {
		$out .= sprintf( $wrap, esc_html( $word ), $start + $i );
	}
	return rtrim( $out );
}

/**
 * Image value stored in attributes: [ 'id' => attachment ID, 'url' => …, 'alt' => … ].
 * Returns the <img> tag (responsive srcset when it is a media-library image).
 *
 * $args:
 *   class, size ('large'), sizes, loading ('lazy'), fetchpriority, width, height,
 *   edit  => attribute path for click-to-replace in the editor,
 *   decorative => true → alt=""
 */
function mats_image( $image, array $args = array() ): string {
	if ( is_object( $image ) ) {
		$image = (array) $image; // empty object attributes arrive as stdClass
	}
	$image = is_array( $image ) ? $image : array( 'url' => (string) $image );
	$id    = absint( $image['id'] ?? 0 );
	$url   = (string) ( $image['url'] ?? '' );
	$alt   = ! empty( $args['decorative'] ) ? '' : (string) ( $image['alt'] ?? '' );
	$edit  = (string) ( $args['edit'] ?? '' );

	$attr = array(
		'class'    => (string) ( $args['class'] ?? '' ),
		'alt'      => $alt,
		'loading'  => (string) ( $args['loading'] ?? 'lazy' ),
		'decoding' => 'async',
	);
	if ( ! empty( $args['sizes'] ) ) {
		$attr['sizes'] = (string) $args['sizes'];
	}
	if ( ! empty( $args['fetchpriority'] ) ) {
		$attr['fetchpriority'] = (string) $args['fetchpriority'];
		$attr['loading']       = 'eager';
	}
	if ( isset( $args['attrs'] ) && is_array( $args['attrs'] ) ) {
		$attr = array_merge( $attr, $args['attrs'] );
	}
	if ( $edit && mats_is_preview() ) {
		$attr['data-mats-image'] = $edit;
		$attr['title']           = 'Haz clic para cambiar la foto';
	}
	if ( '' === $attr['class'] ) {
		unset( $attr['class'] );
	}

	if ( $id && wp_attachment_is_image( $id ) ) {
		if ( '' === $attr['alt'] && empty( $args['decorative'] ) ) {
			$attr['alt'] = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
		}
		$html = wp_get_attachment_image( $id, (string) ( $args['size'] ?? 'large' ), false, $attr );
		if ( $html ) {
			return $html;
		}
	}
	if ( '' === $url ) {
		if ( $edit && mats_is_preview() ) {
			return '<span class="mats-image-empty ' . esc_attr( (string) ( $args['class'] ?? '' ) ) . '" data-mats-image="' . esc_attr( $edit ) . '">Haz clic para elegir una foto</span>';
		}
		return '';
	}
	$html = '<img src="' . esc_url( $url ) . '"';
	foreach ( $attr as $name => $value ) {
		$html .= ' ' . $name . '="' . esc_attr( (string) $value ) . '"';
	}
	if ( ! empty( $args['width'] ) && ! empty( $args['height'] ) ) {
		$html .= ' width="' . (int) $args['width'] . '" height="' . (int) $args['height'] . '"';
	}
	return $html . ' />';
}

/** Plain URL of an image value (for CSS backgrounds, OG tags…). */
function mats_image_url( $image, string $size = 'large' ): string {
	if ( is_object( $image ) ) {
		$image = (array) $image; // empty object attributes arrive as stdClass
	}
	$image = is_array( $image ) ? $image : array( 'url' => (string) $image );
	$id    = absint( $image['id'] ?? 0 );
	if ( $id ) {
		$src = wp_get_attachment_image_url( $id, $size );
		if ( $src ) {
			return $src;
		}
	}
	return (string) ( $image['url'] ?? '' );
}

/** URL of a file shipped with the theme (assets/…). */
function mats_asset( string $path ): string {
	return MATS_URI . '/assets/' . ltrim( $path, '/' );
}

/**
 * Inline SVG icon from assets/icons/<set>/<name>.svg (the client's brand
 * icons use currentColor). Returns '' when missing.
 */
function mats_icon( string $name, string $set = 'brand' ): string {
	static $cache = array();
	$key = $set . '/' . $name;
	if ( ! isset( $cache[ $key ] ) ) {
		$file          = MATS_DIR . '/assets/icons/' . sanitize_file_name( $set ) . '/' . sanitize_file_name( $name ) . '.svg';
		$cache[ $key ] = is_readable( $file ) ? trim( (string) file_get_contents( $file ) ) : '';
		$cache[ $key ] = preg_replace( '/<\?xml.*?\?>/s', '', $cache[ $key ] );
	}
	return $cache[ $key ];
}

/**
 * MatSurfer isotype — the client's original file as an image, never redrawn.
 * $size = rendered height in px; width follows the file's proportions.
 */
function mats_brand_mark( int $size = 26, string $class = '' ): string {
	$width = round( $size * ( 298 / 481 ), 1 );
	return sprintf(
		'<img src="%s" width="%s" height="%d" alt="" aria-hidden="true" decoding="async"%s />',
		esc_url( mats_asset( 'brand/isotipo.svg' ) ),
		esc_attr( (string) $width ),
		$size,
		$class ? ' class="' . esc_attr( $class ) . '"' : ''
	);
}

/** Collects JSON-LD nodes while blocks render; printed in the footer. */
function mats_jsonld_add( array $node ): void {
	$GLOBALS['mats_jsonld'][] = $node;
}

function mats_jsonld_nodes(): array {
	return isset( $GLOBALS['mats_jsonld'] ) && is_array( $GLOBALS['mats_jsonld'] ) ? $GLOBALS['mats_jsonld'] : array();
}

/**
 * Editor-only hint shown inside a section whose content comes from
 * elsewhere (Equipo, Aplicaciones…). Never printed on the public site.
 */
function mats_preview_note( string $text, string $admin_path = '', string $link_label = '' ): string {
	if ( ! mats_is_preview() ) {
		return '';
	}
	$link = '';
	if ( $admin_path ) {
		$link = ' <a class="mats-note__link" href="' . esc_url( admin_url( $admin_path ) ) . '" target="_blank" rel="noopener">' . esc_html( $link_label ?: 'Abrir' ) . ' ↗</a>';
	}
	return '<div class="mats-note">' . esc_html( $text ) . $link . '</div>';
}

/** Editor-only empty-state for a section whose required fields are empty. */
function mats_preview_empty( string $section_name ): string {
	if ( ! mats_is_preview() ) {
		return '';
	}
	return '<div class="mats-empty"><strong>' . esc_html( $section_name ) . '</strong><span>Esta sección está vacía. Rellena sus textos en el panel de la derecha.</span></div>';
}
