<?php
/**
 * QA (module "blog", fix pass): an article that already contains its own
 * call-to-action section gets no second closing band.
 *   MATS_WP_PORT=8914 php /home/user/wpsite-cli.php wordpress/tools/qa/blog-fix-cta.php            (creates, prints the URL)
 *   QA_CLEAN=1 MATS_WP_PORT=8914 php /home/user/wpsite-cli.php wordpress/tools/qa/blog-fix-cta.php (removes)
 */
defined( 'ABSPATH' ) || exit;
foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'lang' => '', 'meta_key' => '_mats_qa_blogfix_cta' ) ) as $old ) {
	wp_delete_post( $old, true );
}
if ( getenv( 'QA_CLEAN' ) ) {
	echo "cleaned\n";
	return;
}
$cta = serialize_block(
	array(
		'blockName'    => 'matsurfer/page-cta',
		'attrs'        => array(
			'title'  => 'Own closing band of this article',
			'text'   => 'Typed by the editor inside the article.',
			'button' => array( 'label' => 'Write to us', 'page' => 'contact' ),
		),
		'innerBlocks'  => array(),
		'innerHTML'    => '',
		'innerContent' => array(),
	)
);
$id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => 'QA article with its own call to action',
		'post_content' => "<!-- wp:paragraph -->\n<p>Short QA text.</p>\n<!-- /wp:paragraph -->\n\n" . $cta,
	)
);
pll_set_post_language( $id, 'en' );
update_post_meta( $id, '_mats_qa_blogfix_cta', 1 );
echo $id, ' ', get_permalink( $id ), "\n";
