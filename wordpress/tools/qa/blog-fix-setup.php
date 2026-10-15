<?php
/**
 * QA (module "blog", fix pass): test articles for the review findings.
 *   MATS_WP_PORT=8914 php /home/user/wpsite-cli.php wordpress/tools/qa/blog-fix-setup.php
 *   QA_CLEAN=1 MATS_WP_PORT=8914 php /home/user/wpsite-cli.php wordpress/tools/qa/blog-fix-setup.php   (remove them)
 * Creates one EN + one ES article (linked) with a very long chemical title,
 * two tags, a file download (generated PDF, one with the inline preview), an
 * audio player (generated WAV) and the featured image; prints their IDs/URLs.
 * Everything carries the meta _mats_qa_blogfix and is removed with QA_CLEAN=1.
 */

defined( 'ABSPATH' ) || exit;

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';

foreach ( get_posts( array( 'post_type' => array( 'post', 'attachment' ), 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'lang' => '', 'meta_key' => '_mats_qa_blogfix' ) ) as $old ) {
	'attachment' === get_post_type( $old ) ? wp_delete_attachment( $old, true ) : wp_delete_post( $old, true );
}
foreach ( array( 'thin-films', 'peliculas-finas' ) as $slug ) {
	$t = get_term_by( 'slug', $slug, 'post_tag' );
	if ( $t ) {
		wp_delete_term( $t->term_id, 'post_tag' );
	}
}
if ( getenv( 'QA_CLEAN' ) ) {
	echo "cleaned\n";
	return;
}

/* ── generated media ───────────────────────────────────────────────── */
function mats_qa_fix_pdf(): string {
	$objs   = array(
		'<< /Type /Catalog /Pages 2 0 R >>',
		'<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
		'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
		null,
		'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
	);
	$stream  = "BT /F1 24 Tf 72 760 Td (MatSurfer press release - QA) Tj ET";
	$objs[3] = '<< /Length ' . strlen( $stream ) . " >>\nstream\n{$stream}\nendstream";
	$out     = "%PDF-1.4\n";
	$offsets = array();
	foreach ( $objs as $i => $body ) {
		$offsets[] = strlen( $out );
		$out      .= ( $i + 1 ) . " 0 obj\n{$body}\nendobj\n";
	}
	$xref = strlen( $out );
	$out .= 'xref' . "\n0 " . ( count( $objs ) + 1 ) . "\n0000000000 65535 f \n";
	foreach ( $offsets as $o ) {
		$out .= sprintf( "%010d 00000 n \n", $o );
	}
	$out .= 'trailer << /Size ' . ( count( $objs ) + 1 ) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
	return $out;
}
function mats_qa_fix_wav(): string {
	$rate = 8000;
	$data = '';
	for ( $i = 0; $i < $rate * 2; $i++ ) {
		$data .= pack( 'v', (int) ( 8000 * sin( 2 * M_PI * 440 * $i / $rate ) ) & 0xFFFF );
	}
	return 'RIFF' . pack( 'V', 36 + strlen( $data ) ) . 'WAVEfmt ' . pack( 'VvvVVvv', 16, 1, 1, $rate, $rate * 2, 2, 16 ) . 'data' . pack( 'V', strlen( $data ) ) . $data;
}
function mats_qa_fix_upload( string $name, string $bytes, string $mime, string $title ): int {
	$up = wp_upload_bits( $name, null, $bytes );
	if ( ! empty( $up['error'] ) ) {
		fwrite( STDERR, $up['error'] . "\n" );
		return 0;
	}
	$id = wp_insert_attachment(
		array(
			'post_mime_type' => $mime,
			'post_title'     => $title,
			'post_status'    => 'inherit',
		),
		$up['file']
	);
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $up['file'] ) );
	update_post_meta( $id, '_mats_qa_blogfix', 1 );
	return (int) $id;
}
$pdf   = mats_qa_fix_upload( 'matsurfer-press-release-qa.pdf', mats_qa_fix_pdf(), 'application/pdf', 'MatSurfer press release (QA)' );
$audio = mats_qa_fix_upload( 'matsurfer-interview-qa.wav', mats_qa_fix_wav(), 'audio/wav', 'Interview (QA)' );
$photo = mats_import_media( 'lab-bench.jpg' );

/* ── block markup (as the editor saves it) ──────────────────────────── */
function q_fix_p( string $html ): string {
	return "<!-- wp:paragraph -->\n<p>{$html}</p>\n<!-- /wp:paragraph -->\n\n";
}
function q_fix_file( int $id, string $name, string $button, bool $preview ): string {
	$url   = (string) wp_get_attachment_url( $id );
	$fid   = 'wp-block-file--media-' . wp_generate_uuid4();
	$attrs = array(
		'id'   => $id,
		'href' => $url,
	);
	if ( $preview ) {
		$attrs['displayPreview'] = true;
	}
	$embed = $preview ? '<object class="wp-block-file__embed" data="' . esc_url( $url ) . '" type="application/pdf" style="width:100%;height:600px" aria-label="' . esc_attr( $name ) . '"></object>' : '';
	return '<!-- wp:file ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES ) . " -->\n<div class=\"wp-block-file\">{$embed}<a id=\"{$fid}\" href=\"" . esc_url( $url ) . '">' . esc_html( $name ) . '</a><a href="' . esc_url( $url ) . "\" class=\"wp-block-file__button wp-element-button\" download aria-describedby=\"{$fid}\">" . esc_html( $button ) . "</a></div>\n<!-- /wp:file -->\n\n";
}
function q_fix_audio( int $id, string $caption ): string {
	$url = (string) wp_get_attachment_url( $id );
	return "<!-- wp:audio {\"id\":{$id}} -->\n<figure class=\"wp-block-audio\"><audio controls src=\"" . esc_url( $url ) . '"></audio><figcaption class="wp-element-caption">' . esc_html( $caption ) . "</figcaption></figure>\n<!-- /wp:audio -->\n\n";
}

$tags = array(
	'en' => array( 'Surfaces', 'Thin films' ),
	'es' => array( 'Superficies', 'Películas finas' ),
);
$copy = array(
	'en' => array(
		'title'   => 'Poly(3,4-ethylenedioxythiophene) films on hexamethyldisilazane-treated substrates',
		'excerpt' => 'A QA article with a very long chemical word in its title, a file download and an audio player.',
		'p1'      => 'Conductive polymer films such as PEDOT are grown on substrates first treated with HMDS vapour. This QA text checks the reading column.',
		'file'    => 'Press release (PDF, 1 page)',
		'button'  => 'Download',
		'p2'      => 'The same document, shown with its inline preview:',
		'audio'   => 'Interview with the team (2 s test tone).',
	),
	'es' => array(
		'title'   => 'Películas de poli(3,4-etilendioxitiofeno) sobre sustratos tratados con hexametildisilazano',
		'excerpt' => 'Un artículo de prueba con una palabra química muy larga en el título, una descarga y un reproductor de audio.',
		'p1'      => 'Las películas de polímeros conductores como el PEDOT crecen sobre sustratos tratados antes con vapor de HMDS. Este texto de prueba revisa la columna de lectura.',
		'file'    => 'Nota de prensa (PDF, 1 página)',
		'button'  => 'Descargar',
		'p2'      => 'El mismo documento, con su vista previa:',
		'audio'   => 'Entrevista con el equipo (tono de prueba de 2 s).',
	),
);
$ids = array();
foreach ( array( 'en', 'es' ) as $lang ) {
	$c       = $copy[ $lang ];
	$content = q_fix_p( esc_html( $c['p1'] ) ) . q_fix_file( $pdf, $c['file'], $c['button'], false ) . q_fix_p( esc_html( $c['p2'] ) ) . q_fix_file( $pdf, $c['file'], $c['button'], true ) . q_fix_audio( $audio, $c['audio'] ) . q_fix_p( esc_html( $c['p1'] ) );
	$id      = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => $c['title'],
			'post_excerpt' => $c['excerpt'],
			'post_content' => $content,
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		echo $id->get_error_message(), "\n";
		continue;
	}
	pll_set_post_language( $id, $lang );
	update_post_meta( $id, '_mats_qa_blogfix', 1 );
	set_post_thumbnail( $id, $photo );
	$tag_ids = array();
	foreach ( $tags[ $lang ] as $name ) {
		$term = get_term_by( 'name', $name, 'post_tag' );
		if ( ! $term ) {
			$made = wp_insert_term( $name, 'post_tag' );
			$term = is_wp_error( $made ) ? null : get_term( $made['term_id'], 'post_tag' );
		}
		if ( $term ) {
			pll_set_term_language( (int) $term->term_id, $lang );
			$tag_ids[] = (int) $term->term_id;
		}
	}
	wp_set_post_terms( $id, $tag_ids, 'post_tag' );
	$ids[ $lang ] = $id;
}
pll_save_post_translations( $ids );
$t_en = get_term_by( 'slug', 'thin-films', 'post_tag' );
$t_es = get_term_by( 'slug', 'peliculas-finas', 'post_tag' );
if ( $t_en && $t_es ) {
	pll_save_term_translations( array( 'en' => $t_en->term_id, 'es' => $t_es->term_id ) );
}
foreach ( $ids as $lang => $id ) {
	echo $lang, ' ', $id, ' ', get_permalink( $id ), "\n";
}
echo 'pdf ', $pdf, ' audio ', $audio, "\n";
