<?php
/**
 * QA (module "cptadmin", fix pass): checks of the review fixes.
 *   MATS_WP_PORT=8918 php /home/user/wpsite-cli.php wordpress/tools/qa/cptadmin-fixpass.php
 *
 *   - the expert's name per language: old language-neutral value shown in
 *     both boxes, in the list and in the language checks; saving per
 *     language removes it (both boxes empty = unsigned again); the
 *     automatic internal name includes the person
 *   - the page cache is emptied (do_action 'litespeed_purge_all') when a
 *     visible record changes, its photo changes or ▲▼ is used — and not for
 *     drafts. Each purge case runs in its own process (the purge runs once
 *     per request), started by this same script with "purge:<case>".
 *   - ▲▼ only in the full list; year/month browser checks match the server
 *
 * Prints "PASS …" / "FAIL …" lines and a summary; exit code 1 on failure.
 */

$fails = 0;
$check = static function ( string $name, $got, $want ) use ( &$fails ) {
	$ok = $got === $want;
	if ( ! $ok ) {
		++$fails;
	}
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $name . ( $ok ? '' : ' → got ' . var_export( $got, true ) . ', want ' . var_export( $want, true ) ) . "\n";
};

$purges = 0;
add_action(
	'litespeed_purge_all',
	static function () use ( &$purges ) {
		++$purges;
	}
);

$scenario = (string) ( $argv[2] ?? '' );

/* ═══ helpers for cptadmin-fixpass.mjs ═════════════════════════════════ */
if ( 'make-legacy' === $scenario ) { // a published evaluation saved before the name had two boxes
	$tag = wp_generate_password( 4, false, false );
	$id  = (int) wp_insert_post(
		array(
			'post_type'   => 'mats_expert',
			'post_title'  => 'Legacy QA ' . $tag,
			'post_status' => 'publish',
		)
	);
	foreach ( array(
		'_mats_name'     => 'Dr Legacy ' . $tag,
		'_mats_field_en' => 'Legacy field ' . $tag,
		'_mats_field_es' => 'Campo antiguo ' . $tag,
		'_mats_quote_en' => 'Legacy English quote ' . $tag . '.',
		'_mats_quote_es' => 'Cita antigua en español ' . $tag . '.',
		'_mats_date'     => '2019-05',
	) as $key => $value ) {
		update_post_meta( $id, $key, $value );
	}
	echo wp_json_encode( array( 'id' => $id, 'name' => 'Dr Legacy ' . $tag ) );
	exit( 0 );
}
if ( str_starts_with( $scenario, 'meta:' ) ) {
	$id   = (int) substr( $scenario, 5 );
	$meta = array();
	foreach ( get_post_meta( $id ) as $key => $values ) {
		if ( str_starts_with( $key, '_mats_' ) ) {
			$meta[ $key ] = $values[0];
		}
	}
	echo wp_json_encode(
		array(
			'title' => get_post_field( 'post_title', $id ),
			'meta'  => $meta,
			'en'    => mats_field( $id, 'name', 'en' ),
			'es'    => mats_field( $id, 'name', 'es' ),
		),
		JSON_UNESCAPED_UNICODE
	);
	exit( 0 );
}

/* ═══ one purge case per process ═══════════════════════════════════════ */
if ( str_starts_with( $scenario, 'purge:' ) ) {
	$case = substr( $scenario, 6 );
	/* a record to act on; a published one is published straight in the
	   database, so that creating it does not use up this request's purge */
	$team = static function ( string $status ): int {
		global $wpdb;
		$id = (int) wp_insert_post(
			array(
				'post_type'   => 'mats_team',
				'post_title'  => 'Purge QA ' . wp_generate_password( 4, false ),
				'post_status' => 'draft',
			)
		);
		if ( 'publish' === $status ) {
			$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			clean_post_cache( $id );
		}
		return $id;
	};
	$img = (int) get_posts( array( 'post_type' => 'attachment', 'post_mime_type' => 'image', 'post_status' => 'inherit', 'posts_per_page' => 1, 'fields' => 'ids' ) )[0];
	$want = 1;
	switch ( $case ) {
		case 'draft-save': // a draft created and saved: nothing visible changed
			$id = $team( 'draft' );
			wp_update_post( array( 'ID' => $id, 'menu_order' => 3 ) );
			$want = 0;
			break;
		case 'draft-photo':
			$id = $team( 'draft' );
			set_post_thumbnail( $id, $img );
			$want = 0;
			break;
		case 'publish': // draft → publish
			$id = $team( 'draft' );
			wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
			break;
		case 'update': // published record updated (also Quick Edit / bulk edit)
			$id      = $team( 'publish' );
			$purges  = 0;
			wp_update_post( array( 'ID' => $id, 'menu_order' => 7 ) );
			break;
		case 'unpublish':
			$id     = $team( 'publish' );
			$purges = 0;
			wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
			break;
		case 'trash':
			$id     = $team( 'publish' );
			$purges = 0;
			wp_trash_post( $id );
			break;
		case 'photo': // the photo box saves on its own (AJAX), no "Actualizar"
			$id     = $team( 'publish' );
			$purges = 0;
			set_post_thumbnail( $id, $img );
			break;
		case 'move-down':
		case 'move-up': // the ▲▼ handler (direct menu_order writes)
			$ids      = mats_cpta_ordered_ids( 'mats_team' );
			$id       = 'move-down' === $case ? $ids[0] : $ids[1];
			$neighbour = 'move-down' === $case ? $ids[1] : $ids[0];
			$_GET     = array(
				'post' => (string) $id,
				'dir'  => 'move-down' === $case ? 'down' : 'up',
			);
			$_REQUEST = $_GET + array( '_wpnonce' => wp_create_nonce( 'mats_cpta_move_' . $id ) );
			add_filter(
				'wp_redirect',
				static function () {
					throw new RuntimeException( 'redirect' );
				}
			);
			try {
				do_action( 'admin_post_mats_cpta_move' );
			} catch ( RuntimeException $e ) {
				// the handler ends with a redirect
			}
			$after = array_map( 'intval', get_posts( array( 'post_type' => 'mats_team', 'post_status' => array( 'publish', 'future', 'draft', 'pending', 'private' ), 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => array( 'menu_order' => 'ASC', 'date' => 'ASC', 'ID' => 'ASC' ), 'suppress_filters' => true ) ) );
			$check( "▲▼ $case: swapped", array_search( $id, $after, true ) === array_search( $neighbour, $ids, true ), true );
			$id = 0;
			break;
		default:
			echo "FAIL unknown case $case\n";
			exit( 1 );
	}
	$check( "purge on $case", $purges, $want );
	if ( $id ) {
		wp_delete_post( $id, true );
	}
	exit( $fails ? 1 : 0 );
}

/* ═══ main checks ══════════════════════════════════════════════════════ */

$render_box = static function ( int $id ): string {
	ob_start();
	mats_cpta_render_content_box( get_post( $id ) );
	return (string) ob_get_clean();
};
$render_cell = static function ( string $column, int $id ): string {
	ob_start();
	mats_cpta_render_column( $column, $id );
	return (string) ob_get_clean();
};
$input_value = static function ( string $html, string $name ): ?string {
	if ( preg_match( '/<input[^>]*name="mats_cpta\[' . preg_quote( $name, '/' ) . '\]"[^>]*value="([^"]*)"/', $html, $m ) ) {
		return html_entity_decode( $m[1], ENT_QUOTES );
	}
	return null;
};
$save = static function ( int $id, array $fields ) {
	$_POST = array(
		'mats_cpta_nonce' => wp_create_nonce( 'mats_cpta_save_' . $id ),
		'mats_cpta'       => wp_slash( $fields ),
	);
	mats_cpta_save( $id, get_post( $id ) );
	$_POST = array();
};
$expert = static function ( array $meta, string $status = 'publish' ): int {
	$id = (int) wp_insert_post(
		array(
			'post_type'   => 'mats_expert',
			'post_title'  => 'Fixpass QA ' . wp_generate_password( 4, false ),
			'post_status' => $status,
		)
	);
	foreach ( $meta as $key => $value ) {
		update_post_meta( $id, $key, $value );
	}
	return $id;
};
$all_expert_fields = static function ( int $id, array $override ): array {
	$out = array();
	foreach ( mats_cpta_fields( 'mats_expert' ) as $field => $i18n ) {
		foreach ( $i18n ? array( '_en', '_es' ) : array( '' ) as $suffix ) {
			$out[ $field . $suffix ] = (string) get_post_meta( $id, mats_meta_key( $field, ltrim( $suffix, '_' ) ), true );
		}
	}
	return array_merge( $out, $override );
};

/* ── 1. record from before the name had two boxes ───────────────────── */
$base = array(
	'_mats_field_en' => 'Fixpass field',
	'_mats_field_es' => 'Campo fixpass',
	'_mats_quote_en' => 'English quote.',
	'_mats_quote_es' => 'Cita en español.',
	'_mats_date'     => '2020-01',
);
$old = $expert( $base + array( '_mats_name' => 'Dr Legacy Person' ) );
$html = $render_box( $old );
$check( 'legacy name: EN box prefilled', $input_value( $html, 'name_en' ), 'Dr Legacy Person' );
$check( 'legacy name: ES box prefilled', $input_value( $html, 'name_es' ), 'Dr Legacy Person' );
$name_row = (string) preg_replace( '/^.*?(data-field="name".*?)data-field=.*$/s', '$1', $html );
$check( 'legacy name: no «sin traducir» on the ES box', str_contains( $name_row, 'is-missing' ), false );
$check( 'legacy name: no language warning', in_array( 'Nombre de la persona', mats_cpta_missing( get_post( $old ) )['es'], true ), false );
$cell = $render_cell( 'mats_who', $old );
$check( 'legacy name: list «Persona» shows it', str_contains( $cell, 'Dr Legacy Person' ) && ! str_contains( $cell, 'Sin firma' ), true );

/* saved untouched (boxes as prefilled) → per language, neutral gone, site unchanged */
$save( $old, $all_expert_fields( $old, array( 'name_en' => 'Dr Legacy Person', 'name_es' => 'Dr Legacy Person' ) ) );
$check( 'legacy saved: EN key written', get_post_meta( $old, '_mats_name_en', true ), 'Dr Legacy Person' );
$check( 'legacy saved: ES key written', get_post_meta( $old, '_mats_name_es', true ), 'Dr Legacy Person' );
$check( 'legacy saved: neutral key removed', metadata_exists( 'post', $old, '_mats_name' ), false );
$check( 'legacy saved: getter EN unchanged', mats_field( $old, 'name', 'en' ), 'Dr Legacy Person' );
$check( 'legacy saved: getter ES unchanged', mats_field( $old, 'name', 'es' ), 'Dr Legacy Person' );
wp_delete_post( $old, true );

/* both boxes emptied → unsigned on both sites */
$old = $expert( $base + array( '_mats_name' => 'Dr Legacy Person' ) );
$save( $old, $all_expert_fields( $old, array( 'name_en' => '', 'name_es' => '' ) ) );
$check( 'legacy emptied: neutral key removed', metadata_exists( 'post', $old, '_mats_name' ), false );
$check( 'legacy emptied: getter EN unsigned', mats_field( $old, 'name', 'en' ), '' );
$check( 'legacy emptied: getter ES unsigned', mats_field( $old, 'name', 'es' ), '' );
$check( 'legacy emptied: list «Sin firma»', str_contains( $render_cell( 'mats_who', $old ), 'Sin firma' ), true );
$found = array_filter( mats_get_experts( 'es' ), static fn( $e ) => $e['id'] === $old );
$check( 'legacy emptied: mats_get_experts(es) name empty', '' === ( reset( $found )['name'] ?? 'x' ), true );
wp_delete_post( $old, true );

/* a form without the name boxes (another tool) leaves the old value alone */
$old = $expert( $base + array( '_mats_name' => 'Dr Legacy Person' ) );
$save( $old, array( 'quote_en' => 'Changed quote.' ) );
$check( 'no name in the form: neutral kept', get_post_meta( $old, '_mats_name', true ), 'Dr Legacy Person' );
wp_delete_post( $old, true );

/* half migrated: English site still shows the neutral value → in the EN box */
$old  = $expert( $base + array( '_mats_name' => 'Dr Old', '_mats_name_es' => 'Dr. Nuevo' ) );
$html = $render_box( $old );
$check( 'half migrated: EN box = what the English site shows', $input_value( $html, 'name_en' ), mats_field( $old, 'name', 'en' ) );
$check( 'half migrated: ES box = its own value', $input_value( $html, 'name_es' ), 'Dr. Nuevo' );
wp_delete_post( $old, true );

/* ── 2. fresh install: name per language ────────────────────────────── */
$new  = $expert( $base + array( '_mats_name_en' => 'Dr Fresh Person', '_mats_name_es' => 'Dr. Fresh Person' ) );
$cell = $render_cell( 'mats_who', $new );
$check( 'per-language name: list «Persona» shows the English one', str_contains( $cell, 'Dr Fresh Person' ) && ! str_contains( $cell, 'Sin firma' ), true );
$html = $render_box( $new );
$check( 'per-language name: EN box', $input_value( $html, 'name_en' ), 'Dr Fresh Person' );
$check( 'per-language name: ES box', $input_value( $html, 'name_es' ), 'Dr. Fresh Person' );
$check( 'per-language name: no warning', mats_cpta_missing( get_post( $new ) )['es'], array() );
wp_delete_post( $new, true );

$only_es = $expert( $base + array( '_mats_name_es' => 'Dra. Solo Español' ) );
$check( 'name only in ES: list shows it', str_contains( $render_cell( 'mats_who', $only_es ), 'Dra. Solo Español' ), true );
$check( 'name only in ES: «Falta EN» warning', in_array( 'Nombre de la persona', mats_cpta_missing( get_post( $only_es ) )['en'], true ), true );
wp_delete_post( $only_es, true );

/* ── 3. automatic internal name includes the person ─────────────────── */
$auto = (int) wp_insert_post(
	array(
		'post_type'   => 'mats_expert',
		'post_title'  => 'temporary',
		'post_status' => 'draft',
	)
);
$_POST = array(
	'mats_cpta_nonce' => wp_create_nonce( 'mats_cpta_save_' . $auto ),
	'mats_cpta'       => wp_slash(
		array(
			'field_en' => 'Review field',
			'name_en'  => 'Dr Review Tester',
			'name_es'  => 'Dr. Review Tester',
		)
	),
);
wp_update_post(
	array(
		'ID'         => $auto,
		'post_title' => '',
	)
);
$check( 'auto internal name: field — EN name', get_post_field( 'post_title', $auto ), 'Review field — Dr Review Tester' );
$_POST['mats_cpta'] = wp_slash(
	array(
		'field_en' => 'Review field',
		'name_en'  => '',
		'name_es'  => 'Dra. Solo ES',
	)
);
wp_update_post(
	array(
		'ID'         => $auto,
		'post_title' => '',
	)
);
$check( 'auto internal name: ES name when EN is empty', get_post_field( 'post_title', $auto ), 'Review field — Dra. Solo ES' );
$_POST = array();
wp_delete_post( $auto, true );

/* ── 4. texts ───────────────────────────────────────────────────────── */
$intro = (string) mats_cpta_config( 'mats_expert' )['intro'];
$check( 'expert intro: no longer «el nombre … una sola vez»', str_contains( $intro, 'El nombre, la institución' ), false );
$check( 'expert intro: name in both columns', str_contains( $intro, 'El nombre también va en las dos columnas' ), true );
$check( 'date help: explains the format', str_contains( (string) mats_cpta_field_def( 'mats_expert', 'date' )['help'], '2026-09' ), true );

/* ── 5. browser checks match the server ─────────────────────────────── */
ob_start();
mats_cpta_render_input( mats_cpta_field_def( 'mats_publication', 'year' ), 'year', 'y', '', '', '' );
$year_html = (string) ob_get_clean();
$check( 'year box: min 1900 like the server', str_contains( $year_html, 'min="1900"' ), true );
$check( 'server: 1900 accepted', mats_cpta_sanitize( 'year', '1900' ), '1900' );
ob_start();
mats_cpta_render_input( mats_cpta_field_def( 'mats_expert', 'date' ), 'date', 'd', '', '', '' );
$month_html = (string) ob_get_clean();
preg_match( '/pattern="([^"]*)"/', $month_html, $m );
$pattern = html_entity_decode( $m[1] ?? '', ENT_QUOTES );
$check( 'month box: has a title explaining the format', str_contains( $month_html, 'title="Año y mes, por ejemplo 2026-09"' ), true );
foreach ( array( '2026-09', '2026-9', '09/2026', '9/2026', '2026.09', ' 2026-09 ' ) as $sample ) {
	$browser = 1 === preg_match( '#^(?:' . $pattern . ')$#u', $sample );
	$check( "month «{$sample}»: browser and server agree (accepted)", $browser && null !== mats_cpta_sanitize( 'month', $sample ), true );
}
foreach ( array( 'septiembre', '2026', '2026-09-01' ) as $sample ) {
	$browser = 1 === preg_match( '#^(?:' . $pattern . ')$#u', $sample );
	$check( "month «{$sample}»: refused by the browser pattern too", $browser, false );
}

/* ── 6. ▲▼ only in the full list ────────────────────────────────────── */
$_GET = array( 'post_type' => 'mats_team', 'author' => '1' );
$check( '▲▼: not in «Mías» (author filter)', mats_cpta_list_is_default_view(), false );
$_GET = array( 'post_type' => 'mats_team', 'post_status' => 'draft' );
$check( '▲▼: not in a status view', mats_cpta_list_is_default_view(), false );
$_GET = array( 'post_type' => 'mats_team', 's' => 'Ana' );
$check( '▲▼: not in a search', mats_cpta_list_is_default_view(), false );
$_GET = array( 'post_type' => 'mats_team' );
$GLOBALS['wp_query'] = new WP_Query(
	array(
		'post_type'      => 'mats_team',
		'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
		'posts_per_page' => 5,
	)
);
$check( '▲▼: full list (found_posts = all records)', mats_cpta_list_is_default_view(), true );
$_GET = array();

/* ── 7. «Contenido» heading is not a postbox toggle ─────────────────── */
ob_start();
do_action( 'edit_form_after_title', get_post( (int) mats_cpta_ordered_ids( 'mats_team' )[0] ) );
$box = (string) ob_get_clean();
$check( '«Contenido»: heading without .hndle', str_contains( $box, 'class="hndle"' ), false );
$check( '«Contenido»: plain heading present', str_contains( $box, '<h2 class="mats-cpta-box__title">Contenido</h2>' ), true );

/* ── 8. page cache: one process per case ───────────────────────────── */
foreach ( array( 'draft-save', 'draft-photo', 'publish', 'update', 'unpublish', 'trash', 'photo', 'move-down', 'move-up' ) as $case ) {
	$out = array();
	$code = 0;
	exec( 'php /home/user/wpsite-cli.php ' . escapeshellarg( __FILE__ ) . ' ' . escapeshellarg( 'purge:' . $case ) . ' 2>&1', $out, $code );
	foreach ( $out as $line ) {
		echo $line . "\n";
		if ( str_starts_with( $line, 'FAIL' ) ) {
			++$fails;
		}
	}
	if ( 0 !== $code && ! array_filter( $out, static fn( $l ) => str_starts_with( $l, 'FAIL' ) ) ) {
		++$fails;
		echo "FAIL purge case $case exited with $code\n";
	}
}

echo ( $fails ? "\n$fails FAILED\n" : "\nALL PASSED\n" );
exit( $fails ? 1 : 0 );
