<?php
/**
 * Section blocks: auto-registration + the field schema the editor uses.
 *
 * Every folder in blocks/ is one page section:
 *   blocks/<name>/block.json   standard metadata + "matsurfer": { "fields": [...] }
 *   blocks/<name>/render.php   server rendering — the exact public markup
 *   blocks/<name>/style.css    the section's styles (front end + editor)
 *   blocks/<name>/view.js      optional front-end behaviour
 *
 * Attributes are DERIVED from the field schema (one attribute per top-level
 * field), so block.json never has to repeat them. The shared editor script
 * (assets/js/editor/blocks.js) builds the side panel from the same schema
 * and shows the server-rendered preview with in-place editing.
 *
 * FIELD SCHEMA (each entry of matsurfer.fields)
 *   { "key": "title",  "type": "text",     "label": "Título", "help": "…" }
 *   { "key": "lead",   "type": "textarea", "label": "Texto", "rows": 4 }
 *   { "key": "photo",  "type": "image",    "label": "Foto" }          → {id,url,alt}
 *   { "key": "video",  "type": "video",    "label": "Vídeo" }         → {id,url}
 *   { "key": "cta",    "type": "link",     "label": "Botón" }         → {label,page,url,query,hash}
 *        "noLabel": true  → destination only (no button text)
 *   { "key": "icon",   "type": "select",   "label": "Icono", "options": [ {"value":"…","label":"…"} ] }
 *   { "key": "dark",   "type": "toggle",   "label": "Fondo oscuro" }
 *   { "key": "count",  "type": "number",   "label": "…" }
 *   { "key": "items",  "type": "repeater", "label": "Pasos", "itemLabel": "Paso",
 *        "titleField": "title", "min": 1, "max": 6, "fields": [ …same types… ] }
 *   { "key": "tags",   "type": "list",     "label": "Etiquetas", "itemLabel": "Etiqueta", "min": 0, "max": 8 }
 *   { "type": "panel",  "label": "Botón principal" }   → starts a new side-panel group
 *   { "type": "notice", "text": "…", "admin": "edit.php?post_type=mats_team", "linkLabel": "Ir a Equipo" }
 * Optional on any field: "default", "help", "placeholder", "inline": false
 * (opt out of in-place editing), "rows" (textarea).
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

/** Attribute definition for a field type. */
function mats_field_attribute( array $field ): ?array {
	$type = $field['type'] ?? 'text';
	switch ( $type ) {
		case 'text':
		case 'textarea':
		case 'select':
			$def = array(
				'type'    => 'string',
				'default' => (string) ( $field['default'] ?? '' ),
			);
			break;
		case 'toggle':
			$def = array(
				'type'    => 'boolean',
				'default' => (bool) ( $field['default'] ?? false ),
			);
			break;
		case 'number':
			$def = array(
				'type'    => 'number',
				'default' => (float) ( $field['default'] ?? 0 ),
			);
			break;
		case 'image':
		case 'video':
		case 'link':
			$def = array(
				'type'    => 'object',
				'default' => (object) ( $field['default'] ?? array() ),
			);
			break;
		case 'repeater':
		case 'list':
			$def = array(
				'type'    => 'array',
				'default' => (array) ( $field['default'] ?? array() ),
			);
			break;
		default:
			return null; // panel, notice: no attribute
	}
	return $def;
}

function mats_fields_to_attributes( array $fields ): array {
	$attributes = array();
	foreach ( $fields as $field ) {
		if ( empty( $field['key'] ) ) {
			continue;
		}
		$def = mats_field_attribute( $field );
		if ( $def ) {
			$attributes[ $field['key'] ] = $def;
		}
	}
	return $attributes;
}

/** Registers every block in blocks/ and remembers its field schema. */
function mats_register_blocks(): void {
	$registry = array();
	$files    = glob( MATS_DIR . '/blocks/*/block.json' ) ?: array();
	sort( $files );
	foreach ( $files as $file ) {
		$meta = json_decode( (string) file_get_contents( $file ), true );
		if ( ! is_array( $meta ) || empty( $meta['name'] ) ) {
			continue;
		}
		$fields     = $meta['matsurfer']['fields'] ?? array();
		$attributes = array_merge( mats_fields_to_attributes( $fields ), $meta['attributes'] ?? array() );
		$type = register_block_type( dirname( $file ), array( 'attributes' => $attributes ) );
		if ( $type ) {
			$registry[ $type->name ] = array(
				'title'       => $meta['title'] ?? $type->name,
				'description' => $meta['description'] ?? '',
				'fields'      => $fields,
				'scope'       => $meta['matsurfer']['scope'] ?? 'page',
			);
		}
	}
	$GLOBALS['mats_blocks'] = $registry;
}
add_action( 'init', 'mats_register_blocks', 20 );

/**
 * Asset versions of our blocks follow their files (style.css, view.js…), so
 * a theme update is never served from a stale browser cache.
 */
add_filter(
	'block_type_metadata',
	static function ( array $metadata ): array {
		if ( empty( $metadata['name'] ) || ! str_starts_with( $metadata['name'], 'matsurfer/' ) || empty( $metadata['file'] ) ) {
			return $metadata;
		}
		$stamp = 0;
		foreach ( glob( dirname( $metadata['file'] ) . '/*.{css,js,json}', GLOB_BRACE ) ?: array() as $asset ) {
			$stamp = max( $stamp, (int) filemtime( $asset ) );
		}
		$metadata['version'] = MATS_VERSION . '.' . $stamp;
		return $metadata;
	}
);

/** Registry of our blocks: name => [ title, fields, scope ]. */
function mats_blocks(): array {
	return isset( $GLOBALS['mats_blocks'] ) && is_array( $GLOBALS['mats_blocks'] ) ? $GLOBALS['mats_blocks'] : array();
}

/** Block category shown in the inserter. */
add_filter(
	'block_categories_all',
	static function ( array $categories ): array {
		array_unshift(
			$categories,
			array(
				'slug'  => 'matsurfer',
				'title' => 'Secciones MatSurfer',
				'icon'  => null,
			)
		);
		return $categories;
	}
);

/** Shared editor script + the data it needs (field schemas, link targets). */
add_action(
	'enqueue_block_editor_assets',
	static function () {
		wp_enqueue_script(
			'matsurfer-blocks-editor',
			MATS_URI . '/assets/js/editor/blocks.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-data', 'wp-api-fetch', 'wp-url', 'wp-dom-ready', 'wp-hooks', 'media-views' ),
			(string) filemtime( MATS_DIR . '/assets/js/editor/blocks.js' ),
			true
		);
		wp_enqueue_media();
		wp_add_inline_script(
			'matsurfer-blocks-editor',
			'window.matsBlocks = ' . wp_json_encode(
				array(
					'blocks'   => mats_blocks(),
					'targets'  => mats_link_targets(),
					'adminUrl' => admin_url(),
					'lang'     => mats_lang(),
				)
			) . ';',
			'before'
		);
		wp_enqueue_style( 'matsurfer-editor-ui', MATS_URI . '/assets/css/editor-ui.css', array(), (string) filemtime( MATS_DIR . '/assets/css/editor-ui.css' ) );
	}
);

/**
 * Names of our blocks used in a post's content (recursively), so their
 * styles load in <head> instead of late in the footer.
 */
function mats_blocks_in_content( string $content ): array {
	$names = array();
	$walk  = static function ( array $blocks ) use ( &$walk, &$names ) {
		foreach ( $blocks as $block ) {
			if ( ! empty( $block['blockName'] ) && str_starts_with( $block['blockName'], 'matsurfer/' ) ) {
				$names[ $block['blockName'] ] = true;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$walk( $block['innerBlocks'] );
			}
		}
	};
	$walk( parse_blocks( $content ) );
	return array_keys( $names );
}

/** Enqueue the styles/scripts of every section on the current page upfront. */
add_action(
	'wp_enqueue_scripts',
	static function () {
		$content = '';
		if ( is_singular() ) {
			$content = (string) get_post_field( 'post_content', get_queried_object_id() );
		}
		/* sections that templates render outside the content (blog CTA…) */
		$names = apply_filters( 'mats_template_blocks', array() );
		if ( $content ) {
			$names = array_merge( $names, mats_blocks_in_content( $content ) );
		}
		$registry = WP_Block_Type_Registry::get_instance();
		foreach ( array_unique( $names ) as $name ) {
			$type = $registry->get_registered( $name );
			if ( ! $type ) {
				continue;
			}
			foreach ( (array) $type->style_handles as $handle ) {
				wp_enqueue_style( $handle );
			}
			foreach ( (array) $type->view_script_handles as $handle ) {
				wp_enqueue_script( $handle );
			}
		}
	},
	20
);

/** Render one of our sections from PHP (templates: blog CTA, 404…). */
function mats_render_section( string $name, array $attributes = array() ): string {
	return render_block(
		array(
			'blockName'    => $name,
			'attrs'        => $attributes,
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		)
	);
}
