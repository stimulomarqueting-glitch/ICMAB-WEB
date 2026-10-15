<?php
/**
 * QA for the "leads" module — fix pass (review findings).
 * Run: MATS_WP_PORT=8912 php /home/user/wpsite-cli.php wordpress/tools/qa/leads-fixpass.php <check|seed N|clean>
 *
 *   check  plain-text storage (no HTML entities) through WPForms-shaped
 *          submissions, CSV row / mailto / privacy export / search on such
 *          a lead, lazy capability re-grant for editors, Spanish labels of
 *          every data-cta-source printed by the theme. Cleans up after itself.
 *   seed N creates N leads whose organisation is "QAFIX Lab <n>" (paging tests)
 *   entity creates (and keeps) the plain-text test lead (organisation "QAENT R&D <Labs")
 *   clean  deletes every QAFIX / QAENT lead
 * Prints a JSON report.
 */

$checks = array();
$ok     = static function ( string $name, bool $pass, $info = '' ) use ( &$checks ) {
	$checks[] = array( $pass ? 'PASS' : 'FAIL', $name, is_scalar( $info ) ? (string) $info : wp_json_encode( $info, JSON_UNESCAPED_UNICODE ) );
};
$mode = $argv[2] ?? 'check';

$qa_ids = static function ( string $tag ): array {
	return array_map(
		'intval',
		get_posts(
			array(
				'post_type'        => 'mats_lead',
				'post_status'      => 'any',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_query'       => array(
					array(
						'key'     => '_mats_lead_organisation',
						'value'   => $tag,
						'compare' => 'LIKE',
					),
				),
			)
		)
	);
};

if ( 'clean' === $mode ) {
	$n = 0;
	foreach ( array_merge( $qa_ids( 'QAFIX' ), $qa_ids( 'QAENT' ) ) as $id ) {
		$n += wp_delete_post( $id, true ) ? 1 : 0;
	}
	echo wp_json_encode( array( 'deleted' => $n ) ), "\n";
	return;
}

if ( 'seed' === $mode ) {
	$count = max( 1, (int) ( $argv[3] ?? 41 ) );
	$ids   = array();
	for ( $i = 1; $i <= $count; $i++ ) {
		$ids[] = mats_lead_save(
			array(
				'name'         => sprintf( 'Paging Person %02d', $i ),
				'email'        => sprintf( 'paging%02d@example.test', $i ),
				'organisation' => sprintf( 'QAFIX Lab %02d', $i ),
				'message'      => 'Paging test lead.',
				'language'     => 0 === $i % 2 ? 'es' : 'en',
				'consent'      => true,
			)
		);
	}
	echo wp_json_encode( array( 'seeded' => count( array_filter( $ids ) ) ) ), "\n";
	return;
}

/* ── 1. plain text: what WPForms passes (already sanitised by it) ──────── */
$raw = array(
	1 => 'Ana "Nan" O\'Neil <y Cía',
	2 => 'Ana.ONeil@Example.test',
	3 => 'QAENT R&D <Labs',
	4 => 'Jefa de I+D',
	5 => 'other',
	6 => '',
	7 => "We need Tc < 300 K for \"our\" R&D samples.\nSecond line: T < 300 K and P > 2 bar.",
	8 => 'I agree',
);
$fields = array();
foreach ( $raw as $id => $value ) {
	$fields[ $id ] = array(
		'id'    => $id,
		'type'  => 7 === $id ? 'textarea' : 'text',
		'name'  => 'Field ' . $id,
		'value' => 7 === $id ? sanitize_textarea_field( $value ) : sanitize_text_field( $value ), // what WPForms hands over
	);
}
$fields[9] = array(
	'id'    => 9,
	'type'  => 'text',
	'name'  => 'Phone & fax',
	'value' => sanitize_text_field( '+34 93 "580" & ext. <12' ),
);
$ok( 'precondition: WPForms value has entities', str_contains( $fields[7]['value'], '&lt;' ) && str_contains( $fields[7]['value'], '&quot;' ), $fields[7]['value'] );

$old_user = get_current_user_id();
wp_set_current_user( 0 ); // anonymous visitor, like a real submission
$_POST['matsurfer_meta'] = wp_slash( // WordPress slashes $_POST
	array(
		'cta_source'  => 'latest-news',
		'utm'         => wp_json_encode( array( 'source' => 'news & letter', 'campaign' => 'q4 "launch"' ) ),
		'application' => '',
		'language'    => 'es',
		'source_page' => '/es/noticias/',
	)
);
$es_form = absint( mats_setting( 'form_es', 'es' ) );
if ( ! $es_form ) {
	mats_update_setting( 'form_en', 9001 );
	mats_update_setting( 'form_es', 9002 );
	$es_form = 9002;
}
$before = $qa_ids( 'QAENT' );
do_action( 'wpforms_process_complete', $fields, array( 'fields' => $raw ), array( 'id' => $es_form ), 0 );
unset( $_POST['matsurfer_meta'] );
wp_set_current_user( $old_user );
$new  = array_values( array_diff( $qa_ids( 'QAENT' ), $before ) );
$lead = $new ? mats_lead_get( $new[0] ) : null;
$ok( 'entity lead stored', 1 === count( $new ) && $lead, $new );
if ( 'entity' === $mode ) { // keep it for the browser test (leads-fixpass.mjs); "clean" removes it
	echo wp_json_encode( array( 'entity' => $new[0] ?? 0 ) ), "\n";
	return;
}

if ( $lead ) {
	$ok( 'message is plain text', "We need Tc < 300 K for \"our\" R&D samples.\nSecond line: T < 300 K and P > 2 bar." === $lead['message'], $lead['message'] );
	$ok( 'organisation plain', 'QAENT R&D <Labs' === $lead['organisation'], $lead['organisation'] );
	$ok( 'name plain (quotes, apostrophe, <)', 'Ana "Nan" O\'Neil <y Cía' === $lead['name'], $lead['name'] );
	$ok( 'role plain', 'Jefa de I+D' === $lead['role'], $lead['role'] );
	$ok( 'extra field label + value plain', array( array( 'label' => 'Phone & fax', 'value' => '+34 93 "580" & ext. <12' ) ) === $lead['extra'], $lead['extra'] );
	$ok( 'utm plain', array( 'source' => 'news & letter', 'campaign' => 'q4 "launch"' ) === $lead['utm'], $lead['utm'] );
	$ok( 'email lowercased', 'ana.oneil@example.test' === $lead['email'], $lead['email'] );
	$ok( 'consent yes', 'yes' === $lead['consent'] );
	$ok( 'language es', 'es' === $lead['language'] );
	$stored = get_post_meta( $lead['id'], '_mats_lead_message', true );
	$ok( 'no entity in stored meta', ! preg_match( '/&(lt|gt|quot|amp|#0?39);/', (string) $stored ), $stored );

	/* outputs */
	$row = mats_leads_csv_row( $lead );
	$hdr = mats_leads_csv_header();
	$ok( 'csv header/row same width', count( $hdr ) === count( $row ), count( $hdr ) . ' / ' . count( $row ) );
	$cell = array_combine( $hdr, $row );
	$ok( 'csv message plain', $cell['Mensaje'] === $lead['message'], $cell['Mensaje'] );
	$ok( 'csv organisation plain', 'QAENT R&D <Labs' === $cell['Organización'], $cell['Organización'] );
	$ok( 'csv cta code + description', 'latest-news' === $cell['Botón de origen'] && str_starts_with( $cell['Botón de origen (qué es)'], 'Sección «Últimas noticias»' ), $cell['Botón de origen'] . ' | ' . $cell['Botón de origen (qué es)'] );
	$ok( 'csv extra plain', 'Phone & fax: +34 93 "580" & ext. <12' === $cell['Otros campos'], $cell['Otros campos'] );

	$mailto = mats_lead_mailto( $lead );
	$body   = rawurldecode( (string) substr( $mailto, strpos( $mailto, '&body=' ) + 6 ) );
	$ok( 'mailto body quotes plain text', str_contains( $body, '> We need Tc < 300 K for "our" R&D samples.' ) && ! str_contains( $body, '&lt;' ), $body );
	$ok( 'mailto greeting in Spanish with plain name', str_starts_with( $body, 'Hola, Ana "Nan" O\'Neil <y Cía:' ), strtok( $body, "\n" ) );
	$ok( 'mailto has no raw line breaks / spaces', ! preg_match( '/[\s<>"]/', $mailto ), $mailto );

	$export = mats_leads_privacy_export( 'ANA.ONEIL@example.test' );
	$pairs  = array();
	foreach ( $export['data'] as $item ) {
		if ( 'mats-lead-' . $lead['id'] === $item['item_id'] ) {
			foreach ( $item['data'] as $d ) {
				$pairs[ $d['name'] ] = $d['value'];
			}
		}
	}
	$ok( 'privacy export: values escaped once (no double entities)', esc_html( $lead['message'] ) === ( $pairs['Mensaje (Message)'] ?? '' ) && ! str_contains( $pairs['Mensaje (Message)'] ?? '', '&amp;lt;' ), $pairs['Mensaje (Message)'] ?? '' );
	/* what core's HTML report does with each value: wp_kses( $value, 'personal_data_export' ) */
	$cell_html = wp_kses( $pairs['Mensaje (Message)'] ?? '', 'personal_data_export' );
	$ok( 'privacy HTML report shows the whole message', html_entity_decode( $cell_html, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) === $lead['message'], $cell_html );
	$org_html = wp_kses( $pairs['Organización (Organisation)'] ?? '', 'personal_data_export' );
	$ok( 'privacy HTML report shows the organisation', 'QAENT R&D <Labs' === html_entity_decode( $org_html, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), $org_html );

	/* search (list filters decode the term the same way) */
	$_REQUEST['s'] = wp_slash( '"our" R&D' );
	$f             = mats_leads_request_filters();
	$found         = get_posts( array_merge( mats_leads_query_args( $f ), array( 'fields' => 'ids', 'posts_per_page' => 5 ) ) );
	$ok( 'search with quotes and & finds it', in_array( $lead['id'], array_map( 'intval', $found ), true ), $f['s'] );
	$_REQUEST['s'] = wp_slash( 'Tc < 300 K' );
	$f             = mats_leads_request_filters();
	$found         = get_posts( array_merge( mats_leads_query_args( $f ), array( 'fields' => 'ids', 'posts_per_page' => 5 ) ) );
	$ok( 'search with < finds it', 'Tc < 300 K' === $f['s'] && in_array( $lead['id'], array_map( 'intval', $found ), true ), $f['s'] );
	unset( $_REQUEST['s'] );

	/* detail screen output: escaped once, nothing injected */
	ob_start();
	mats_leads_render_detail( $lead );
	$detail = ob_get_clean();
	$ok( 'detail escapes the plain text once', str_contains( $detail, 'We need Tc &lt; 300 K for &quot;our&quot; R&amp;D samples.' ) && ! str_contains( $detail, '&amp;lt;' ), '' );
	$ok( 'detail shows the Spanish cta label', str_contains( $detail, 'Sección «Últimas noticias»' ) && str_contains( $detail, '<code>latest-news</code>' ) );
	$ok( 'detail: no raw < from the lead', ! str_contains( $detail, '<Labs' ) && ! str_contains( $detail, '<y Cía' ) && ! str_contains( $detail, '<12' ) );
}

/* hostile: entity-encoded tags typed by a visitor stay inert text */
$h = mats_lead_normalize(
	array(
		'name'    => '&lt;script&gt;alert(1)&lt;/script&gt;',
		'message' => '<script>alert(2)</script>ok &lt;img src=x onerror=alert(3)&gt;',
	)
);
$ok( 'real tags still stripped', ! str_contains( $h['message'], 'alert(2)' ), $h['message'] );
ob_start();
echo esc_html( $h['name'] ); // what every screen does
$esc = ob_get_clean();
$ok( 'decoded entity tags are escaped on output', ! str_contains( $esc, '<script' ), $esc );

/* ── 2. capability: lazy re-grant for editors ─────────────────────────── */
$editor = get_role( 'editor' );
$editor->remove_cap( 'mats_manage_leads' );
mats_leads_ensure_caps();
$ok( 'editor gets the capability back when it went missing', get_role( 'editor' )->has_cap( 'mats_manage_leads' ) );
get_role( 'editor' )->add_cap( 'mats_manage_leads', false ); // a role editor plugin "denies" it on purpose
mats_leads_ensure_caps();
$ok( 'an explicit denial for editors is respected', ! get_role( 'editor' )->has_cap( 'mats_manage_leads' ) );
mats_leads_ensure_caps( true ); // version/activation path: back to the defaults
$ok( 'forced pass restores editors', get_role( 'editor' )->has_cap( 'mats_manage_leads' ) );
get_role( 'administrator' )->remove_cap( 'mats_manage_leads' );
mats_leads_ensure_caps();
$ok( 'administrator gets it back', get_role( 'administrator' )->has_cap( 'mats_manage_leads' ) );
$ok( 'author still lacks it', ! get_role( 'author' )->has_cap( 'mats_manage_leads' ) );
$ok( 'caps version option intact', MATS_LEADS_CAPS_VERSION === get_option( 'mats_leads_caps_version' ) );

/* ── 3. every data-cta-source printed by the theme has a Spanish label ── */
$theme   = get_template_directory();
$sources = array( 'direct' ); // contact-form.js default
$it      = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $theme, FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $file ) {
	if ( ! preg_match( '/\.(php|json)$/', $file->getFilename() ) || str_contains( $file->getPathname(), '/inc/modules/leads.php' ) ) {
		continue;
	}
	$code = (string) file_get_contents( $file->getPathname() );
	preg_match_all( '/data-cta-source="([a-z0-9\-]+)"/', $code, $m1 );              // literal attributes
	preg_match_all( "/'ctaSource'\s*=>\s*'([a-z0-9\-]+)'/", $code, $m2 );          // template args / seeds
	preg_match_all( "/CtaSource'\s*=>\s*'([a-z0-9\-]+)'/", $code, $m3 );           // explore seed
	preg_match_all( "/mats_blog_cta\(\s*'([a-z0-9\-]+)'/", $code, $m4 );            // news templates
	preg_match_all( "/mats_get\(\s*\\\$attributes,\s*'[a-zA-Z]*[cC]taSource[a-zA-Z]*',\s*'([a-z0-9\-]+)'/", $code, $m5 ); // block defaults
	preg_match_all( "/\['[a-zA-Z]*[cC]taSource'\]\s*=\s*'([a-z0-9\-]+)'/", $code, $m6 );         // $attrs['ctaSource'] = '…'
	$sources = array_merge( $sources, $m1[1], $m2[1], $m3[1], $m4[1], $m5[1], $m6[1] );
	if ( str_ends_with( $file->getFilename(), 'block.json' ) ) {
		$json = json_decode( $code, true );
		foreach ( (array) ( $json['attributes'] ?? array() ) as $key => $attr ) {
			if ( stripos( $key, 'ctasource' ) !== false && ! empty( $attr['default'] ) ) {
				$sources[] = (string) $attr['default'];
			}
		}
	}
}
/* page-cta seed: "<page>-final" for every page role that has the section */
if ( ! function_exists( 'mats_installer_page_specs' ) && is_readable( $theme . '/inc/installer/content.php' ) ) {
	require_once $theme . '/inc/installer/content.php';
}
foreach ( ( function_exists( 'mats_installer_page_specs' ) ? mats_installer_page_specs() : array() ) as $role => $spec ) {
	if ( in_array( 'page-cta', (array) ( $spec['blocks'] ?? array() ), true ) ) {
		$sources[] = $role . '-final';
	}
}
/* applications-<slug> for every published application */
foreach ( mats_get_applications( 'es' ) as $app ) {
	$sources[] = 'applications-' . $app['slug'];
}
$sources = array_values( array_unique( $sources ) );
sort( $sources );
$missing = array_values( array_filter( $sources, static fn( $s ) => '' === mats_lead_cta_label( $s ) ) );
$ok( 'cta sources found in the theme', count( $sources ) >= 20, implode( ', ', $sources ) );
$ok( 'every cta source has a Spanish label', ! $missing, $missing );
$ok( 'latest-news label', str_starts_with( mats_lead_cta_label( 'latest-news' ), 'Sección «Últimas noticias»' ) );
$ok( 'news-empty label', str_starts_with( mats_lead_cta_label( 'news-empty' ), 'Noticias: botón de contacto' ) );
$ok( 'generic <page>-final label', 'Contacto: llamada final' === mats_lead_cta_label( 'contact-final' ) );
$ok( 'unknown source → empty', '' === mats_lead_cta_label( 'something-else' ) && '' === mats_lead_cta_label( 'nosuchpage-final' ) );

/* ── 4. last page helper ──────────────────────────────────────────────── */
$all = (int) ( new WP_Query( array( 'post_type' => 'mats_lead', 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'suppress_filters' => true ) ) )->found_posts;
$ok( 'last page (no filters)', max( 1, (int) ceil( $all / MATS_LEADS_PER_PAGE ) ) === mats_leads_last_page( array() ), $all );
$ok( 'last page of an empty result = 1', 1 === mats_leads_last_page( array( 's' => 'zz-no-such-lead-zz' ) ) );

/* clean up the entity lead */
foreach ( $qa_ids( 'QAENT' ) as $id ) {
	wp_delete_post( $id, true );
}

$fail = count( array_filter( $checks, static fn( $c ) => 'FAIL' === $c[0] ) );
echo wp_json_encode(
	array(
		'pass'   => count( $checks ) - $fail,
		'fail'   => $fail,
		'checks' => $checks,
	),
	JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
), "\n";
