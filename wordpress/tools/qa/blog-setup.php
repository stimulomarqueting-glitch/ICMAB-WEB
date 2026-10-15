<?php
/**
 * QA (module "blog"): realistic news content for the local instance.
 *   MATS_WP_PORT=8914 php /home/user/wpsite-cli.php wordpress/tools/qa/blog-setup.php
 * Creates 7 news articles per language (EN + ES, linked as Polylang
 * translations) built from the original site copy (content.json) — photos
 * from mats_import_media(), every common block in the first article —, three
 * categories + a tag per language, and publishes the two legal pages with a
 * long structured test text (local QA only, never shipped). Safe to re-run:
 * earlier QA posts (meta _mats_qa_blog) are deleted first.
 */

defined( 'ABSPATH' ) || exit;

/* ── clean previous run ─────────────────────────────────────────────── */
foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'lang' => '', 'meta_key' => '_mats_qa_blog' ) ) as $old ) {
	wp_delete_post( $old, true );
}
foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'lang' => '', 'name' => 'hello-world' ) ) as $old ) {
	wp_delete_post( $old, true );
}

$data = mats_content_data();

/* ── categories / tags per language, linked ─────────────────────────── */
function mats_qa_term( string $tax, array $names ): array {
	$ids = array();
	foreach ( $names as $lang => $pair ) {
		$term = get_term_by( 'slug', $pair[1], $tax );
		if ( $term ) {
			$ids[ $lang ] = (int) $term->term_id;
		} else {
			$made         = wp_insert_term( $pair[0], $tax, array( 'slug' => $pair[1], 'description' => $pair[2] ?? '' ) );
			$ids[ $lang ] = is_wp_error( $made ) ? 0 : (int) $made['term_id'];
		}
		if ( $ids[ $lang ] && ! empty( $pair[2] ) ) {
			wp_update_term( $ids[ $lang ], $tax, array( 'description' => $pair[2] ) );
		}
		pll_set_term_language( $ids[ $lang ], $lang );
	}
	pll_save_term_translations( $ids );
	return $ids;
}
/* the default category, "News" / "Noticias" in both languages (the wizard does this too) */
$def_en = (int) pll_get_term( (int) get_option( 'default_category' ), 'en' );
$def_es = (int) pll_get_term( (int) get_option( 'default_category' ), 'es' );
if ( $def_es ) {
	wp_update_term( $def_es, 'category', array( 'name' => 'Noticias', 'slug' => 'noticias' ) );
}
$cat_news     = array(
	'en' => (int) pll_get_term( (int) get_option( 'default_category' ), 'en' ),
	'es' => (int) pll_get_term( (int) get_option( 'default_category' ), 'es' ),
);
$cat_research = mats_qa_term( 'category', array( 'en' => array( 'Research', 'research-news', 'Results, evaluations and publications obtained with MatSurfer.' ), 'es' => array( 'Investigación', 'investigacion', 'Resultados, valoraciones y publicaciones obtenidos con MatSurfer.' ) ) );
$cat_product  = mats_qa_term( 'category', array( 'en' => array( 'Product', 'product-news' ), 'es' => array( 'Producto', 'producto-noticias' ) ) );
$tag_surf     = mats_qa_term( 'post_tag', array( 'en' => array( 'Surfaces', 'surfaces' ), 'es' => array( 'Superficies', 'superficies' ) ) );

/* ── media ─────────────────────────────────────────────────────────── */
$img = array();
foreach ( array( 'lab-bench.jpg', 'lab-corridor.jpg', 'product-table.jpg', 'researcher-device.jpg', 'tech-detail.jpg', 'og-image.png' ) as $file ) {
	$img[ $file ] = mats_import_media( $file );
}
$video = mats_import_media( 'matsurfer-device.mp4' );

/* ── block markup helpers (exactly what the editor saves) ───────────── */
function q_p( string $html ): string {
	return "<!-- wp:paragraph -->\n<p>{$html}</p>\n<!-- /wp:paragraph -->\n\n";
}
function q_h( string $text, int $level = 2 ): string {
	$attr = 2 === $level ? '' : ' {"level":' . $level . '}';
	return "<!-- wp:heading{$attr} -->\n<h{$level} class=\"wp-block-heading\">" . esc_html( $text ) . "</h{$level}>\n<!-- /wp:heading -->\n\n";
}
function q_list( array $items, bool $ordered = false ): string {
	$tag   = $ordered ? 'ol' : 'ul';
	$attr  = $ordered ? ' {"ordered":true}' : '';
	$inner = '';
	foreach ( $items as $item ) {
		$inner .= "<!-- wp:list-item -->\n<li>{$item}</li>\n<!-- /wp:list-item -->";
	}
	return "<!-- wp:list{$attr} -->\n<{$tag} class=\"wp-block-list\">{$inner}</{$tag}>\n<!-- /wp:list -->\n\n";
}
function q_quote( string $text, string $cite ): string {
	return "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><!-- wp:paragraph -->\n<p>" . esc_html( $text ) . "</p>\n<!-- /wp:paragraph --><cite>" . esc_html( $cite ) . "</cite></blockquote>\n<!-- /wp:quote -->\n\n";
}
function q_pullquote( string $text, string $cite ): string {
	return "<!-- wp:pullquote -->\n<figure class=\"wp-block-pullquote\"><blockquote><p>" . esc_html( $text ) . '</p><cite>' . esc_html( $cite ) . "</cite></blockquote></figure>\n<!-- /wp:pullquote -->\n\n";
}
function q_image( int $id, string $alt, string $caption = '' ): string {
	$url = (string) wp_get_attachment_image_url( $id, 'large' );
	$cap = $caption ? '<figcaption class="wp-element-caption">' . esc_html( $caption ) . '</figcaption>' : '';
	return "<!-- wp:image {\"id\":{$id},\"sizeSlug\":\"large\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-large\"><img src=\"" . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . "\" class=\"wp-image-{$id}\"/>{$cap}</figure>\n<!-- /wp:image -->\n\n";
}
function q_gallery( array $ids, string $caption ): string {
	$inner = '';
	foreach ( $ids as $id => $alt ) {
		$url    = (string) wp_get_attachment_image_url( $id, 'large' );
		$inner .= "<!-- wp:image {\"id\":{$id},\"sizeSlug\":\"large\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-large\"><img src=\"" . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . "\" class=\"wp-image-{$id}\"/></figure>\n<!-- /wp:image -->\n\n";
	}
	return "<!-- wp:gallery {\"linkTo\":\"none\"} -->\n<figure class=\"wp-block-gallery has-nested-images columns-default is-cropped\">{$inner}<figcaption class=\"blocks-gallery-caption wp-element-caption\">" . esc_html( $caption ) . "</figcaption></figure>\n<!-- /wp:gallery -->\n\n";
}
function q_video( int $id, string $caption ): string {
	$url = (string) wp_get_attachment_url( $id );
	return "<!-- wp:video {\"id\":{$id}} -->\n<figure class=\"wp-block-video\"><video controls src=\"" . esc_url( $url ) . '"></video><figcaption class="wp-element-caption">' . esc_html( $caption ) . "</figcaption></figure>\n<!-- /wp:video -->\n\n";
}
function q_embed( string $url, string $caption ): string {
	return "<!-- wp:embed {\"url\":\"{$url}\",\"type\":\"video\",\"providerNameSlug\":\"youtube\",\"responsive\":true,\"className\":\"wp-embed-aspect-16-9 wp-has-aspect-ratio\"} -->\n<figure class=\"wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio\"><div class=\"wp-block-embed__wrapper\">\n{$url}\n</div><figcaption class=\"wp-element-caption\">" . esc_html( $caption ) . "</figcaption></figure>\n<!-- /wp:embed -->\n\n";
}
function q_table( array $head, array $rows, string $caption ): string {
	$h = '<thead><tr>' . implode( '', array_map( static fn( $c ) => '<th>' . esc_html( $c ) . '</th>', $head ) ) . '</tr></thead>';
	$b = '<tbody>';
	foreach ( $rows as $row ) {
		$b .= '<tr>' . implode( '', array_map( static fn( $c ) => '<td>' . esc_html( $c ) . '</td>', $row ) ) . '</tr>';
	}
	$b .= '</tbody>';
	return "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table class=\"has-fixed-layout\">{$h}{$b}</table><figcaption class=\"wp-element-caption\">" . esc_html( $caption ) . "</figcaption></figure>\n<!-- /wp:table -->\n\n";
}
function q_buttons( array $buttons ): string {
	$inner = '';
	foreach ( $buttons as $b ) {
		list( $label, $href, $outline ) = $b;
		$open   = $outline ? "<!-- wp:button {\"className\":\"is-style-outline\"} -->\n<div class=\"wp-block-button is-style-outline\">" : "<!-- wp:button -->\n<div class=\"wp-block-button\">";
		$inner .= $open . '<a class="wp-block-button__link wp-element-button" href="' . esc_url( $href ) . '">' . esc_html( $label ) . "</a></div>\n<!-- /wp:button -->\n\n";
	}
	return "<!-- wp:buttons -->\n<div class=\"wp-block-buttons\">{$inner}</div>\n<!-- /wp:buttons -->\n\n";
}
function q_sep( string $style = '' ): string {
	if ( $style ) {
		return "<!-- wp:separator {\"className\":\"is-style-{$style}\"} -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity is-style-{$style}\"/>\n<!-- /wp:separator -->\n\n";
	}
	return "<!-- wp:separator -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity\"/>\n<!-- /wp:separator -->\n\n";
}
function q_code( string $code ): string {
	return "<!-- wp:code -->\n<pre class=\"wp-block-code\"><code>" . esc_html( $code ) . "</code></pre>\n<!-- /wp:code -->\n\n";
}

/* ── the articles, from the original copy ───────────────────────────── */
$posts = array();
foreach ( array( 'en', 'es' ) as $lang ) {
	$c     = $data[ $lang ];
	$p     = $c['product'];
	$steps = $p['howItWorks']['steps'];
	$faq   = $p['faq']['items'];
	$exp   = $c['expertExperiences'];
	$apps  = $c['applications'];
	$ben   = $p['benefits']['outcomes'] ?? $c['home']['benefits']['outcomes'];
	$en    = 'en' === $lang;
	$contact = mats_page_url( 'contact', $lang );

	$step_items = array_map( static fn( $s ) => '<strong>' . esc_html( $s['title'] ) . '.</strong> ' . esc_html( $s['text'] ), $steps );
	$param_rows = array_map( static fn( $i ) => array( $i['symbol'], $i['label'] ), $p['parameters']['items'] );

	/* 1 — every common block */
	$kitchen  = q_p( esc_html( $p['hero']['lead'] ) . ' ' . ( $en ? 'This article walks through a full experiment, from the source material to the prepared surface — and shows <a href="' . esc_url( $contact ) . '">how to request a demonstration</a>.' : 'Este artículo recorre un experimento completo, del material de partida a la superficie preparada, y explica <a href="' . esc_url( $contact ) . '">cómo pedir una demostración</a>.' ) );
	$kitchen .= q_h( $p['howItWorks']['title'] );
	$kitchen .= q_p( esc_html( $faq[0]['a'] ) );
	$kitchen .= q_list( $step_items, true );
	$kitchen .= q_image( $img['tech-detail.jpg'], $p['hero']['photoAlt'], $en ? 'Detail of the source holder inside the glass chamber.' : 'Detalle del soporte de la fuente dentro de la cámara de vidrio.' );
	$kitchen .= q_h( $p['parameters']['title'], 3 );
	$kitchen .= q_p( esc_html( $p['parameters']['lead'] ) . ' <em>' . ( $en ? 'Values are always agreed with the team.' : 'Los valores se acuerdan siempre con el equipo.' ) . '</em>' );
	$kitchen .= q_table( $en ? array( 'Symbol', 'Parameter' ) : array( 'Símbolo', 'Parámetro' ), $param_rows, $en ? 'The controlled parameters of a MatSurfer deposition.' : 'Los parámetros controlados de una deposición con MatSurfer.' );
	$kitchen .= q_quote( $exp[0]['quote'], ( $exp[0]['name'] ?? '' ) . ', ' . ( $exp[0]['institution'] ?? '' ) );
	$kitchen .= q_h( $en ? 'What your team gets' : 'Lo que obtiene tu equipo' );
	$kitchen .= q_list( array_map( static fn( $b ) => '<strong>' . esc_html( $b['title'] ) . '</strong> — ' . esc_html( $b['text'] ), $ben ) );
	$kitchen .= q_gallery(
		array(
			$img['lab-bench.jpg']         => $en ? 'Laboratory bench' : 'Mesa de laboratorio',
			$img['researcher-device.jpg'] => $en ? 'A researcher with the device' : 'Una investigadora con el dispositivo',
			$img['lab-corridor.jpg']      => $en ? 'Laboratory corridor at ICMAB' : 'Pasillo de laboratorios del ICMAB',
		),
		$en ? 'MatSurfer in the laboratories of ICMAB-CSIC.' : 'MatSurfer en los laboratorios del ICMAB-CSIC.'
	);
	$kitchen .= q_h( $p['howItWorks']['video']['caption'], 3 );
	$kitchen .= q_video( $video, $p['howItWorks']['video']['caption'] );
	$kitchen .= q_p( $en ? 'Recorded talks are published on video platforms; this one is embedded from YouTube:' : 'Las charlas grabadas se publican en plataformas de vídeo; esta se inserta desde YouTube:' );
	$kitchen .= q_embed( 'https://www.youtube.com/watch?v=aqz-KE-bpKQ', $en ? 'Embedded video (YouTube).' : 'Vídeo insertado (YouTube).' );
	$kitchen .= q_sep();
	$kitchen .= q_h( $faq[1]['q'], 3 );
	$kitchen .= q_p( esc_html( $faq[1]['a'] ) );
	$kitchen .= q_pullquote( $p['benefits']['title'] ?? $c['home']['benefits']['title'], 'MatSurfer · ICMAB-CSIC' );
	$kitchen .= q_h( $en ? 'A note for data people' : 'Una nota para quien trabaja con datos', 4 );
	$kitchen .= q_p( $en ? 'Each run can be logged as a small record — for example in a lab notebook export like the one below. Use <code>d</code> for the source–substrate distance.' : 'Cada ensayo puede registrarse como un pequeño registro, por ejemplo en una exportación del cuaderno de laboratorio como la de abajo. Usa <code>d</code> para la distancia fuente–sustrato.' );
	$kitchen .= q_code( "run: 2026-10-08-a\nsubstrate: glass\nd: <agreed with the team>\nT: <agreed with the team>\nt: <agreed with the team>" );
	$kitchen .= q_sep( 'dots' );
	$kitchen .= q_p( esc_html( $c['home']['finalCta']['text'] ) );
	$kitchen .= q_buttons(
		array(
			array( $c['home']['finalCta']['button'], $contact, false ),
			array( $p['hero']['cta'], $contact . '?interest=product', true ),
		)
	);

	$posts[ $lang ] = array(
		array(
			'title'   => $p['howItWorks']['title'] . ( $en ? ': a step-by-step look at one experiment' : ': un experimento paso a paso' ),
			'excerpt' => $en ? 'From loading the source material to removing the prepared sample — the four steps of a MatSurfer deposition, the parameters that define it and what researchers say about it.' : 'De cargar el material de partida a retirar la muestra preparada: los cuatro pasos de una deposición con MatSurfer, los parámetros que la definen y lo que opinan los investigadores.',
			'content' => $kitchen,
			'date'    => '2026-10-08 10:30:00',
			'thumb'   => $img['product-table.jpg'],
			'cats'    => array( $cat_product[ $lang ] ),
			'tags'    => array( $tag_surf[ $lang ] ),
		),
		array(
			'title'   => $en ? 'Technology assessment: a fast, low-cost screening tool' : 'Evaluación tecnológica: una herramienta de cribado rápida y de bajo coste',
			'excerpt' => '',
			'content' => q_p( esc_html( $c['researchPage']['lead'] ?? '' ) ) . q_quote( $exp[0]['quote'], ( $exp[0]['name'] ?? '' ) . ' · ' . ( $exp[0]['role'] ?? '' ) . ', ' . ( $exp[0]['institution'] ?? '' ) ) . q_p( esc_html( $faq[0]['a'] ) ) . q_image( $img['researcher-device.jpg'], $en ? 'A researcher next to the device' : 'Una investigadora junto al dispositivo' ),
			'date'    => '2026-09-24 09:00:00',
			'thumb'   => $img['researcher-device.jpg'],
			'cats'    => array( $cat_research[ $lang ] ),
			'tags'    => array( $tag_surf[ $lang ] ),
		),
		array(
			'title'   => ( $exp[1]['field'] ?? '' ) . ( $en ? ': versatile crystallisation with MatSurfer' : ': cristalización versátil con MatSurfer' ),
			'excerpt' => $en ? 'An evaluation on metal–organic and covalent organic frameworks shows how the adjustable configuration helps crystallise films in direct contact or further away.' : 'Una evaluación con redes metalorgánicas y orgánicas covalentes muestra cómo la configuración ajustable ayuda a cristalizar películas en contacto directo o a más distancia.',
			'content' => q_p( esc_html( $exp[1]['quote'] ?? '' ) ) . q_h( $en ? 'Why it matters' : 'Por qué importa' ) . q_p( esc_html( $apps[0]['challenge'] ) ) . q_list( array_map( static fn( $a ) => esc_html( $a['title'] ), array_slice( $apps, 0, 4 ) ) ),
			'date'    => '2026-10-02 12:00:00',
			'thumb'   => $img['lab-bench.jpg'],
			'cats'    => array( $cat_research[ $lang ] ),
			'tags'    => array(),
		),
		array(
			'title'   => $p['faq']['title'] . ( $en ? ': five questions about direct surface functionalization' : ': cinco preguntas sobre la funcionalización directa de superficies' ),
			'excerpt' => '',
			'content' => implode( '', array_map( static fn( $f ) => q_h( $f['q'] ) . q_p( esc_html( $f['a'] ) ), array_slice( $faq, 0, 5 ) ) ),
			'date'    => '2026-09-10 08:15:00',
			'thumb'   => $img['tech-detail.jpg'],
			'cats'    => array( $cat_product[ $lang ] ),
			'tags'    => array(),
		),
		array(
			'title'   => $c['applicationsPage']['hero']['title'] ?? ( $en ? 'Applications' : 'Aplicaciones' ),
			'excerpt' => $c['applicationsPage']['hero']['lead'] ?? '',
			'content' => implode( '', array_map( static fn( $a ) => q_h( $a['title'], 3 ) . q_p( esc_html( $a['use'] ) . ' ' . esc_html( $a['benefit'] ) ), $apps ) ),
			'date'    => '2026-08-27 11:00:00',
			'thumb'   => $img['lab-corridor.jpg'],
			'cats'    => array( $cat_news[ $lang ] ),
			'tags'    => array( $tag_surf[ $lang ] ),
		),
		array(
			'title'   => $c['home']['finalCta']['title'],
			'excerpt' => $c['home']['finalCta']['text'],
			'content' => q_p( esc_html( $p['hero']['lead'] ) ) . q_p( esc_html( $c['home']['finalCta']['text'] ) ) . q_buttons( array( array( $c['home']['finalCta']['button'], $contact, false ) ) ),
			'date'    => '2026-07-15 10:00:00',
			'thumb'   => $img['lab-bench.jpg'],
			'cats'    => array( $cat_news[ $lang ] ),
			'tags'    => array(),
		),
		array(
			'title'   => $en ? 'Licensing: the technology behind MatSurfer is patent protected' : 'Licencias: la tecnología de MatSurfer está protegida por patente',
			'excerpt' => '',
			'content' => q_p( esc_html( $c['site']['footer']['description'] ?? $p['hero']['lead'] ) ) . q_p( ( $en ? 'Patent: ' : 'Patente: ' ) . '<strong>' . esc_html( mats_setting( 'patent_code' ) ) . '</strong>.' ),
			'date'    => '2025-12-11 09:30:00',
			'thumb'   => 0,
			'cats'    => array( $cat_news[ $lang ] ),
			'tags'    => array(),
		),
	);
}

/* ── insert + translations ──────────────────────────────────────────── */
kses_remove_filters();
$made = array();
foreach ( array( 'en', 'es' ) as $lang ) {
	foreach ( $posts[ $lang ] as $i => $spec ) {
		$id = wp_insert_post(
			wp_slash(
				array(
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'post_title'     => $spec['title'],
					'post_excerpt'   => $spec['excerpt'],
					'post_content'   => $spec['content'],
					'post_date'      => $spec['date'],
					'post_category'  => array_filter( $spec['cats'] ),
					'tags_input'     => array_filter( $spec['tags'] ),
					'comment_status' => 'closed',
				)
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			echo 'error: ', $id->get_error_message(), "\n";
			continue;
		}
		pll_set_post_language( $id, $lang );
		wp_set_post_terms( $id, array_filter( $spec['tags'] ), 'post_tag' );
		update_post_meta( $id, '_mats_qa_blog', 1 );
		if ( $spec['thumb'] ) {
			set_post_thumbnail( $id, $spec['thumb'] );
		}
		$made[ $i ][ $lang ] = $id;
	}
}
kses_init_filters();
foreach ( $made as $pair ) {
	pll_save_post_translations( $pair );
}

/* the embed: cache an oEmbed answer locally (no network in QA) */
$yt = 'https://www.youtube.com/watch?v=aqz-KE-bpKQ';
foreach ( $made[0] as $id ) {
	$attr = wp_embed_defaults( $yt );
	$key  = md5( $yt . serialize( $attr ) );
	update_post_meta( $id, '_oembed_' . $key, '<iframe title="MatSurfer" width="500" height="281" src="' . esc_url( wp_get_attachment_url( $video ) ) . '" frameborder="0" allowfullscreen></iframe>' );
	update_post_meta( $id, '_oembed_time_' . $key, time() );
}

/* legal pages: publish with a long structured test text (local QA only) */
foreach ( array( 'privacy', 'legal' ) as $role ) {
	foreach ( array( 'en', 'es' ) as $lang ) {
		$pid = mats_page_id( $role, $lang ) ?: (int) pll_get_post( (int) get_option( 'mats_page_roles' )[ $role ], $lang );
		if ( ! $pid ) {
			continue;
		}
		$en   = 'en' === $lang;
		$body = q_p( $en ? '<strong>QA placeholder.</strong> This page only exists on the local test site to check the reading layout of legal texts; the real text comes from ICMAB.' : '<strong>Texto de prueba.</strong> Esta página solo existe en la web de pruebas local para revisar el diseño de los textos legales; el texto real lo facilita el ICMAB.' );
		foreach ( array( 1, 2, 3, 4 ) as $n ) {
			$body .= q_h( ( $en ? 'Section ' : 'Apartado ' ) . $n . ( $en ? ' — heading of a legal clause' : ' — título de una cláusula legal' ) );
			$body .= q_p( str_repeat( $en ? 'Lorem ipsum placeholder sentence for layout testing only. ' : 'Frase de relleno solo para probar el diseño de la página. ', 6 ) );
			if ( 2 === $n ) {
				$body .= q_list( array( $en ? 'First item of a list' : 'Primer elemento de una lista', $en ? 'Second item, a little longer to wrap onto a second line on phones' : 'Segundo elemento, un poco más largo para que ocupe dos líneas en el móvil', $en ? 'Third item' : 'Tercer elemento' ) );
				$body .= q_h( $en ? 'A sub-clause' : 'Una subcláusula', 3 );
				$body .= q_p( $en ? 'Contact: <a href="mailto:matsurfer@icmab.es">matsurfer@icmab.es</a>.' : 'Contacto: <a href="mailto:matsurfer@icmab.es">matsurfer@icmab.es</a>.' );
			}
		}
		wp_update_post(
			wp_slash(
				array(
					'ID'           => $pid,
					'post_status'  => 'publish',
					'post_content' => $body,
					'post_excerpt' => $en ? 'How MatSurfer (ICMAB-CSIC) handles the information you share with us.' : 'Cómo trata MatSurfer (ICMAB-CSIC) la información que compartes con nosotros.',
				)
			)
		);
	}
}

delete_option( 'rewrite_rules' );
echo 'posts: ', wp_json_encode( $made ), "\n";
