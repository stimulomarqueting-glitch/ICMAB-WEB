<?php
/**
 * QA for the "leads" module (Contactos inbox).
 * Run: MATS_WP_PORT=8912 php /home/user/wpsite-cli.php wordpress/tools/qa/leads-setup.php [reset]
 *
 * - points mats_settings form_en / form_es to fake WPForms IDs (9001 / 9002)
 * - creates the users editor1 / author1 (password: qa-pass-123)
 * - simulates anonymous WPForms submissions through do_action( 'wpforms_process_complete' )
 *   (EN form, ES form, a foreign form that must be ignored, a hostile payload)
 * - saves one lead directly with mats_lead_save() and one 2 years old (retention test)
 * - prints a JSON report of assertions
 */

$checks = array();
$ok     = static function ( string $name, bool $pass, $info = '' ) use ( &$checks ) {
	$checks[] = array( $pass ? 'PASS' : 'FAIL', $name, is_scalar( $info ) ? (string) $info : wp_json_encode( $info ) );
};

if ( in_array( 'reset', $argv, true ) ) {
	foreach ( get_posts( array( 'post_type' => 'mats_lead', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'suppress_filters' => true ) ) as $id ) {
		wp_delete_post( $id, true );
	}
	delete_option( 'mats_leads_retention_months' );
	delete_option( 'mats_leads_cleanup_last' );
	wp_clear_scheduled_hook( 'mats_leads_cleanup' );
}

/* capabilities (lazy grant already ran on init) */
$ok( 'admin has mats_manage_leads', get_role( 'administrator' )->has_cap( 'mats_manage_leads' ) );
$ok( 'editor has mats_manage_leads', get_role( 'editor' )->has_cap( 'mats_manage_leads' ) );
$ok( 'author lacks mats_manage_leads', ! get_role( 'author' )->has_cap( 'mats_manage_leads' ) );
$ok( 'contributor lacks', ! get_role( 'contributor' )->has_cap( 'mats_manage_leads' ) );
$ok( 'subscriber lacks', ! get_role( 'subscriber' )->has_cap( 'mats_manage_leads' ) );

/* post type flags */
$pto = get_post_type_object( 'mats_lead' );
$ok( 'cpt registered', (bool) $pto );
$ok( 'cpt not public / no rest / no ui / no export', $pto && ! $pto->public && ! $pto->show_in_rest && ! $pto->show_ui && ! $pto->can_export && $pto->exclude_from_search && ! $pto->publicly_queryable );
$ok( 'cpt create_posts do_not_allow', $pto && 'do_not_allow' === $pto->cap->create_posts );
$ok( 'not translated by Polylang', ! function_exists( 'pll_is_translated_post_type' ) || ! pll_is_translated_post_type( 'mats_lead' ) );
$sitemap_types = wp_sitemaps_get_server()->registry->get_provider( 'posts' ) ? array_keys( wp_sitemaps_get_server()->registry->get_provider( 'posts' )->get_object_subtypes() ) : array();
$ok( 'not in core sitemap', ! in_array( 'mats_lead', $sitemap_types, true ), $sitemap_types );

/* settings: fake WPForms IDs */
mats_update_setting( 'form_en', 9001 );
mats_update_setting( 'form_es', 9002 );
$ok( 'form ids map', array( 9001 => 'en', 9002 => 'es' ) === mats_lead_form_ids(), mats_lead_form_ids() );

/* users */
foreach ( array( 'editor1' => 'editor', 'author1' => 'author' ) as $login => $role ) {
	$user = get_user_by( 'login', $login );
	if ( ! $user ) {
		$uid = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_pass'    => 'qa-pass-123',
				'user_email'   => $login . '@example.test',
				'role'         => $role,
				'display_name' => 'editor' === $role ? 'Laura (editora)' : 'Pablo (autor)',
				'locale'       => 'es_ES',
			)
		);
	} else {
		wp_set_password( 'qa-pass-123', $user->ID );
	}
}
$ok( 'editor user can', user_can( get_user_by( 'login', 'editor1' ), 'mats_manage_leads' ) );
$ok( 'author user cannot', ! user_can( get_user_by( 'login', 'author1' ), 'mats_manage_leads' ) );

/* helper: a WPForms-like $fields array */
$wpf = static function ( array $v ): array {
	$types = array( 1 => 'name', 2 => 'email', 3 => 'text', 4 => 'text', 5 => 'select', 6 => 'select', 7 => 'textarea', 8 => 'gdpr-checkbox' );
	$names = array( 1 => 'Full name', 2 => 'Email', 3 => 'Organisation', 4 => 'Role', 5 => 'I am interested in', 6 => 'Area of interest', 7 => 'Message', 8 => 'Consent' );
	$out   = array();
	foreach ( $types as $id => $type ) {
		$out[ $id ] = array(
			'name'      => $names[ $id ],
			'value'     => $v[ $id ] ?? '',
			'value_raw' => $v[ $id ] ?? '',
			'id'        => $id,
			'type'      => $type,
		);
	}
	foreach ( $v['extra'] ?? array() as $id => $f ) {
		$out[ $id ] = $f;
	}
	return $out;
};
$submit = static function ( int $form_id, array $fields, array $meta = array() ) {
	$_POST['matsurfer_meta'] = wp_slash( $meta );
	$before                  = (int) wp_count_posts( 'mats_lead' )->private;
	wp_set_current_user( 0 ); // visitors submit the form
	do_action( 'wpforms_process_complete', $fields, array( 'fields' => $fields ), array( 'id' => $form_id ), 0 );
	wp_set_current_user( 1 );
	unset( $_POST['matsurfer_meta'] );
	wp_cache_delete( _count_posts_cache_key( 'mats_lead', '' ), 'counts' );
	$ids = get_posts( array( 'post_type' => 'mats_lead', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'DESC', 'suppress_filters' => true ) );
	$after = (int) wp_count_posts( 'mats_lead' )->private;
	return array( $after - $before, $ids ? (int) $ids[0] : 0 );
};

/* 1. English form, from the home hero, with UTM */
list( $diff, $en_id ) = $submit(
	9001,
	$wpf(
		array(
			1 => 'Dr. Emily Carter',
			2 => 'Emily.Carter@Oxford-Materials.example',
			3 => 'Oxford Materials Lab',
			4 => 'Principal Investigator',
			5 => 'Product information',
			6 => 'Solar cells — perovskite and organic',
			7 => "Hello,\n\nWe are evaluating vapour-phase functionalisation for perovskite interlayers (R&D, 2026).\nCould you share the specs & pricing?\n\nThanks — Emily",
			8 => 'I agree to the privacy policy',
		)
	),
	array(
		'cta_source'  => 'hero',
		'utm'         => '{"source":"linkedin","medium":"social","campaign":"launch-2026","content":"","term":""}',
		'application' => '',
		'language'    => 'en',
		'source_page' => '/contact/?interest=product',
	)
);
$en = mats_lead_get( $en_id );
$ok( 'EN submission stored', 1 === $diff && $en, $en_id );
$ok( 'EN language', $en && 'en' === $en['language'] );
$ok( 'EN title', $en && 'Dr. Emily Carter — Oxford Materials Lab' === get_post( $en_id )->post_title, get_post( $en_id )->post_title );
$ok( 'EN email lowercased', $en && 'emily.carter@oxford-materials.example' === $en['email'], $en['email'] ?? '' );
$ok( 'EN message keeps & and lines', $en && str_contains( $en['message'], "R&D, 2026).\nCould you share the specs & pricing?" ), $en['message'] ?? '' );
$ok( 'EN consent yes + date', $en && 'yes' === $en['consent'] && preg_match( '/^\d{4}-\d{2}-\d{2} /', $en['consent_date'] ) );
$ok( 'EN utm parsed', $en && array( 'source' => 'linkedin', 'medium' => 'social', 'campaign' => 'launch-2026' ) === $en['utm'], $en['utm'] ?? '' );
$ok( 'EN cta + page', $en && 'hero' === $en['cta_source'] && '/contact/?interest=product' === $en['source_page'] );
$ok( 'EN status new', $en && 'new' === $en['status'] );
$ok( 'EN post private, author 0, slug w/o name', 'private' === get_post_status( $en_id ) && 0 === (int) get_post( $en_id )->post_author && str_starts_with( get_post( $en_id )->post_name, 'contacto-' ) );
$all_meta = get_post_meta( $en_id );
$ok( 'no IP / user agent stored', ! preg_grep( '/ip|agent|remote/i', array_keys( $all_meta ) ) && ! str_contains( wp_json_encode( $all_meta ), '127.0.0.1' ), array_keys( $all_meta ) );
$ok( 'author cannot edit/read lead', ! user_can( get_user_by( 'login', 'author1' ), 'edit_post', $en_id ) && ! user_can( get_user_by( 'login', 'author1' ), 'read_post', $en_id ) );
$ok( 'editor can edit lead', user_can( get_user_by( 'login', 'editor1' ), 'edit_post', $en_id ) );

/* 2. Spanish form, from an application CTA, with an extra WPForms field */
list( $diff, $es_id ) = $submit(
	9002,
	$wpf(
		array(
			1       => 'María José Núñez',
			2       => 'mj.nunez@uab.example',
			3       => 'Universitat Autònoma de Barcelona',
			4       => 'Investigadora postdoctoral',
			5       => 'Colaboración científica',
			6       => 'Sensores químicos y biosensores',
			7       => "Hola:\n\nNos interesa probar el equipo con superficies de óxido de indio y estaño. ¿Podríamos hacer una visita?\n\nGracias.",
			8       => 'Acepto la política de privacidad',
			'extra' => array(
				9 => array( 'name' => 'Teléfono', 'value' => '+34 600 000 000', 'id' => 9, 'type' => 'phone' ),
				10 => array( 'name' => 'Separador', 'value' => '', 'id' => 10, 'type' => 'divider' ),
			),
		)
	),
	array(
		'cta_source'  => 'applications-sensors',
		'utm'         => '',
		'application' => 'sensors',
		'language'    => 'es',
		'source_page' => '/es/applications/',
	)
);
$es = mats_lead_get( $es_id );
$ok( 'ES submission stored', 1 === $diff && $es, $es_id );
$ok( 'ES language by form id', $es && 'es' === $es['language'] );
$ok( 'ES accents intact', $es && 'María José Núñez' === $es['name'] && 'Universitat Autònoma de Barcelona' === $es['organisation'] );
$ok( 'ES extra field kept (divider skipped)', $es && 1 === count( $es['extra'] ) && 'Teléfono' === $es['extra'][0]['label'], $es['extra'] ?? '' );
$ok( 'ES application', $es && 'sensors' === $es['application'] );
$ok( 'ES empty utm', $es && array() === $es['utm'] );

/* 3. a form that is not ours: ignored */
list( $diff ) = $submit( 1234, $wpf( array( 1 => 'Newsletter person', 2 => 'news@example.test', 7 => 'Subscribe me' ) ) );
$ok( 'foreign form ignored', 0 === $diff, $diff );

/* 4. hostile payload: tags stripped, tracking sanitised, no consent */
list( $diff, $x_id ) = $submit(
	9001,
	$wpf(
		array(
			1 => '<script>alert(1)</script>Mallory "Bobby" O\'Hacker',
			2 => 'mallory@evil.example<script>',
			3 => '=HYPERLINK("http://evil.example","click")',
			4 => '<img src=x onerror=alert(2)>',
			5 => 'other',
			6 => 'new-combinations',
			7 => "<b>bold</b> text with a backslash C:\\temp and \"quotes\"\n<iframe src=//evil></iframe>",
			8 => '',
		)
	),
	array(
		'cta_source'  => 'javascript:alert(1)<x>',
		'utm'         => '{"source":"<script>x</script>","utm_medium":"cpc","campaign":["array"]}',
		'application' => '../../etc/passwd',
		'language'    => 'fr',
		'source_page' => 'javascript:alert(document.cookie)',
	)
);
$x = mats_lead_get( $x_id );
$ok( 'hostile stored', 1 === $diff && $x, $x_id );
$ok( 'hostile name stripped', $x && ! str_contains( $x['name'], '<' ) && str_contains( $x['name'], 'Mallory "Bobby" O\'Hacker' ), $x['name'] ?? '' );
$ok( 'hostile backslash kept', $x && str_contains( $x['message'], 'C:\\temp' ), $x['message'] ?? '' );
$ok( 'hostile message no tags', $x && ! preg_match( '/<\s*(b|iframe)/i', $x['message'] ), $x['message'] ?? '' );
$ok( 'hostile consent no', $x && 'no' === $x['consent'] && '' === $x['consent_date'] );
$ok( 'hostile page dropped', $x && '' === $x['source_page'], $x['source_page'] ?? '' );
$ok( 'hostile cta cleaned', $x && ! preg_match( '/[<>()]/', $x['cta_source'] ), $x['cta_source'] ?? '' );
$ok( 'hostile utm cleaned (script block removed, array ignored)', $x && array( 'medium' => 'cpc' ) === $x['utm'], $x['utm'] ?? '' );
$ok( 'hostile application slug', $x && ! str_contains( $x['application'], '/' ), $x['application'] ?? '' );
$ok( 'csv formula neutralised', "'=HYPERLINK" === substr( mats_leads_csv_cell( $x['organisation'] ), 0, 11 ) );

/* 5. direct save (other integrations) + choice label mapping */
$direct = mats_lead_save(
	array(
		'name'         => 'Kenji Watanabe',
		'email'        => 'k.watanabe@tohoku.example',
		'organisation' => 'Tohoku University',
		'role'         => 'Associate Professor',
		'interest'     => 'distribution',
		'area'         => 'spm-sample-preparation',
		'message'      => 'We would like to discuss distribution in Japan.',
		'consent'      => true,
		'language'     => 'en',
		'cta_source'   => 'footer',
		'utm'          => array( 'utm_source' => 'newsletter', 'utm_campaign' => 'q4' ),
		'source_page'  => 'http://localhost:8912/team/',
	)
);
$d = mats_lead_get( $direct );
$ok( 'direct save', (bool) $d, $direct );
$ok( 'choice label slug → ES', 'Distribución' === mats_lead_choice_label( 'interest', $d['interest'] ), mats_lead_choice_label( 'interest', $d['interest'] ) );
$ok( 'choice label EN label → ES', 'Información del producto' === mats_lead_choice_label( 'interest', 'Product information' ) );
$ok( 'choice area', 'Preparación de muestras para SPM' === mats_lead_choice_label( 'area', 'spm-sample-preparation' ) );
$ok( 'utm_ prefix accepted', array( 'source' => 'newsletter', 'campaign' => 'q4' ) === $d['utm'], $d['utm'] );
$ok( 'empty save refused', 0 === mats_lead_save( array( 'role' => 'x' ) ) );

/* 6. an old lead (2 years) for the retention test + a few more for paging/filters */
$old = mats_lead_save( array( 'name' => 'Old Contact', 'email' => 'old@example.test', 'organisation' => 'Archive Ltd', 'message' => 'Old message', 'language' => 'es', 'consent' => 'yes' ) );
$old_date = gmdate( 'Y-m-d H:i:s', strtotime( '-25 months' ) );
wp_update_post( array( 'ID' => $old, 'post_date' => get_date_from_gmt( $old_date ), 'post_date_gmt' => $old_date ) );
mats_lead_set_status( $old, 'answered' );
mats_lead_set_status( $direct, 'read' );

/* status functions */
$ok( 'set status answered', mats_lead_set_status( $es_id, 'answered' ) && 'answered' === mats_lead_get( $es_id )['status'] && mats_lead_get( $es_id )['answered_at'] > 0 );
$ok( 'set status back to new', mats_lead_set_status( $es_id, 'new' ) && 0 === mats_lead_get( $es_id )['answered_at'] );
$ok( 'invalid status refused', ! mats_lead_set_status( $es_id, 'spam' ) );
$ok( 'not a lead refused', ! mats_lead_set_status( 1, 'read' ) );

/* front end / REST exposure */
$ok( 'no REST route', ! isset( rest_get_server()->get_routes()['/wp/v2/mats_lead'] ) );
$q = new WP_Query( array( 'post_type' => 'any', 's' => 'Carter', 'post_status' => 'publish' ) );
$ok( 'not found by front-end style search', 0 === (int) $q->found_posts, $q->found_posts );

/* GDPR exporter / eraser registered */
$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
$erasers   = apply_filters( 'wp_privacy_personal_data_erasers', array() );
$ok( 'exporter registered', isset( $exporters['matsurfer-leads'] ) );
$ok( 'eraser registered', isset( $erasers['matsurfer-leads'] ) );
$export = call_user_func( $exporters['matsurfer-leads']['callback'], 'EMILY.carter@oxford-materials.example', 1 );
$ok( 'exporter finds by email (case-insensitive)', 1 === count( $export['data'] ) && $export['done'], count( $export['data'] ) );
$ok( 'exporter data contains message', str_contains( wp_json_encode( $export['data'] ), 'perovskite' ) );

echo wp_json_encode(
	array(
		'ids'    => compact( 'en_id', 'es_id', 'x_id', 'direct', 'old' ),
		'counts' => array(
			'all'      => mats_leads_count(),
			'new'      => mats_leads_count( 'new' ),
			'read'     => mats_leads_count( 'read' ),
			'answered' => mats_leads_count( 'answered' ),
		),
	),
	JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
) . "\n";
$fail = 0;
foreach ( $checks as $c ) {
	echo $c[0] . '  ' . $c[1] . ( 'FAIL' === $c[0] && '' !== $c[2] ? '  → ' . $c[2] : '' ) . "\n";
	$fail += 'FAIL' === $c[0] ? 1 : 0;
}
echo $fail ? "\n$fail FAILED\n" : "\nALL PASSED (" . count( $checks ) . ")\n";
