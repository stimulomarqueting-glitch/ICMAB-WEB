<?php
/**
 * QA (fix "empty-required-text-removes-whole-section"): checks that the
 * stored content of every page satisfies the "required" / "requiredGroup"
 * flags of the section fields (block.json → matsurfer.fields), with the same
 * rules as assets/js/editor/blocks.js. A page that fails would open in the
 * editor with saving locked, so this must print "RESULT ok" on an installed
 * site.
 *
 *   MATS_WP_PORT=8981 php /home/user/wpsite-cli.php wordpress/tools/qa/fix-editorux-required-seed.php
 */

$php_trim = static fn( $v ): string => is_scalar( $v ) ? trim( (string) $v ) : '';

$filled = static function ( array $field, $value ) use ( &$filled, $php_trim ): bool {
	$value = is_object( $value ) ? (array) $value : $value;
	switch ( $field['type'] ?? 'text' ) {
		case 'text':
		case 'textarea':
		case 'select':
			return '' !== $php_trim( $value );
		case 'link':
			return is_array( $value ) && '' !== $php_trim( $value['label'] ?? '' );
		case 'image':
		case 'video':
			return is_array( $value ) && ( ! empty( $value['url'] ) || ! empty( $value['id'] ) );
		case 'list':
			foreach ( (array) $value as $v ) {
				if ( '' !== $php_trim( $v ) ) {
					return true;
				}
			}
			return false;
		case 'repeater':
			foreach ( (array) $value as $item ) {
				$item = is_object( $item ) ? (array) $item : $item;
				if ( ! is_array( $item ) ) {
					continue;
				}
				foreach ( (array) ( $field['fields'] ?? array() ) as $sub ) {
					if ( ! empty( $sub['key'] ) && in_array( $sub['type'] ?? 'text', array( 'text', 'textarea', 'list', 'link', 'repeater' ), true ) && $filled( $sub, $item[ $sub['key'] ] ?? null ) ) {
						return true;
					}
				}
			}
			return false;
	}
	return true;
};

$registry = mats_blocks();
$checked  = 0;
$problems = 0;
$flags    = 0;
foreach ( $registry as $def ) {
	foreach ( $def['fields'] as $f ) {
		$flags += ( ! empty( $f['required'] ) || ! empty( $f['requiredGroup'] ) ) ? 1 : 0;
		foreach ( (array) ( $f['fields'] ?? array() ) as $sub ) {
			$flags += ! empty( $sub['required'] ) ? 1 : 0;
		}
	}
}
echo "fields with a required flag: $flags\n";

$posts = get_posts(
	array(
		'post_type'   => array( 'page', 'post' ),
		'post_status' => 'any',
		'numberposts' => -1,
		'lang'        => '',
	)
);
foreach ( $posts as $p ) {
	foreach ( parse_blocks( $p->post_content ) as $block ) {
		$name = (string) ( $block['blockName'] ?? '' );
		if ( ! isset( $registry[ $name ] ) ) {
			continue;
		}
		++$checked;
		$type  = WP_Block_Type_Registry::get_instance()->get_registered( $name );
		$attrs = $type ? $type->prepare_attributes_for_render( (array) $block['attrs'] ) : (array) $block['attrs'];
		$groups = array();
		foreach ( $registry[ $name ]['fields'] as $f ) {
			if ( empty( $f['key'] ) ) {
				continue;
			}
			$value = $attrs[ $f['key'] ] ?? null;
			if ( true === ( $f['required'] ?? false ) && ! $filled( $f, $value ) ) {
				++$problems;
				echo "PROBLEM page {$p->ID} {$name}: «{$f['label']}» is empty\n";
			}
			if ( ! empty( $f['requiredGroup'] ) ) {
				$groups[ $f['requiredGroup'] ][] = $filled( $f, $value ) ? 1 : 0;
			}
			if ( 'repeater' === ( $f['type'] ?? '' ) ) {
				foreach ( array_values( (array) $value ) as $i => $item ) {
					$item = is_object( $item ) ? (array) $item : (array) $item;
					foreach ( (array) ( $f['fields'] ?? array() ) as $sub ) {
						if ( ! empty( $sub['key'] ) && true === ( $sub['required'] ?? false ) && ! $filled( $sub, $item[ $sub['key'] ] ?? null ) ) {
							++$problems;
							echo "PROBLEM page {$p->ID} {$name}: item " . ( $i + 1 ) . " «{$sub['label']}» is empty\n";
						}
					}
				}
			}
		}
		foreach ( $groups as $g => $vals ) {
			if ( ! array_sum( $vals ) ) {
				++$problems;
				echo "PROBLEM page {$p->ID} {$name}: group {$g} is all empty\n";
			}
		}
	}
}
echo "sections checked: $checked\n";
echo $problems ? "RESULT FAIL $problems\n" : "RESULT ok\n";
