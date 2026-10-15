<?php
/**
 * QA (group "pagehero"): a hero photo whose attachment is deleted from the
 * Media Library must collapse to the text-only layout on the public site
 * (no broken <img>), while an external image (id 0, url only) still shows.
 * Creates a throw-away attachment + a published QA page, renders,
 * deletes the attachment, renders again. Leaves the page (meta _mats_qa =
 * pagehero, removed by pagehero-build.php) for the editor check.
 * Run: MATS_WP_PORT=8902 php /home/user/wpsite-cli.php wordpress/tools/qa/pagehero-deleted-photo.php
 */

require_once ABSPATH . 'wp-admin/includes/image.php';

$src  = MATS_DIR . '/assets/media/product-table.jpg';
$up   = wp_upload_dir();
$dest = trailingslashit( $up['path'] ) . 'pagehero-qa-deleted-' . time() . '.jpg';
copy( $src, $dest );
$att = wp_insert_attachment(
	array(
		'post_mime_type' => 'image/jpeg',
		'post_title'     => 'pagehero QA (to be deleted)',
		'post_status'    => 'inherit',
	),
	$dest
);
wp_update_attachment_metadata( $att, wp_generate_attachment_metadata( $att, $dest ) );
$photo = array(
	'id'  => $att,
	'url' => (string) wp_get_attachment_url( $att ),
	'alt' => 'QA photo',
);

$page_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Pagehero deleted photo (en)',
		'post_name'    => 'pagehero-deleted-photo',
		'post_content' => wp_slash(
			serialize_block(
				array(
					'blockName'    => 'matsurfer/page-hero',
					'attrs'        => array(
						'kicker' => 'QA',
						'title'  => 'Deleted photo check',
						'lead'   => 'The photo of this hero is removed from the Media Library.',
						'photo'  => $photo,
					),
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				)
			)
		),
	)
);
update_post_meta( $page_id, '_mats_qa', 'pagehero' );
pll_set_post_language( $page_id, 'en' );

$summary = static function ( string $html ): string {
	$grid = false !== strpos( $html, 'phero__grid--photo' ) ? 'photo column' : 'text only';
	preg_match( '/<img[^>]*class="phero__photo"[^>]*>|<span class="mats-image-empty[^>]*>/', $html, $m );
	return $grid . ' | ' . ( isset( $m[0] ) ? substr( $m[0], 0, 110 ) : 'no image element' );
};
$render = static function () use ( $page_id ): string {
	return do_blocks( get_post_field( 'post_content', $page_id ) );
};

$ok = true;
$before = $render();
echo 'with attachment     : ', $summary( $before ), "\n";
$ok = $ok && false !== strpos( $before, 'phero__grid--photo' );

wp_delete_attachment( $att, true );
$after = $render();
echo 'attachment deleted  : ', $summary( $after ), "\n";
$ok = $ok && false === strpos( $after, 'phero__grid--photo' ) && false === strpos( $after, '<img' );
echo 'stored url serves   : ', file_exists( $dest ) ? 'file still on disk' : 'file gone', "\n";

$GLOBALS['mats_preview'] = true;
$prev = $render();
echo 'editor preview      : ', $summary( $prev ), "\n";
$ok = $ok && false !== strpos( $prev, 'mats-image-empty phero__photo' );
$GLOBALS['mats_preview'] = false;

$ext = mats_render_section( 'matsurfer/page-hero', array( 'title' => 'T', 'photo' => array( 'id' => 0, 'url' => 'https://example.org/photo.jpg', 'alt' => 'ext' ) ) );
echo 'external image id 0 : ', $summary( $ext ), "\n";
$ok = $ok && false !== strpos( $ext, 'phero__grid--photo' );

echo 'page: ', get_permalink( $page_id ), ' (id ', $page_id, ")\n";
echo $ok ? "RESULT: PASS\n" : "RESULT: FAIL\n";
