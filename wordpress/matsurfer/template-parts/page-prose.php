<?php
/**
 * Reading layout for plain pages (legal texts…): the PageHero header
 * (kicker, title, the excerpt as lead, "updated on" note) and the page
 * content in a readable column; long texts (three or more main headings)
 * get an "On this page" index beside the column.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

$mats_role   = mats_role_of( get_the_ID() );
$mats_kicker = in_array( $mats_role, array( 'privacy', 'legal' ), true ) ? (string) mats_setting( 'footer_col3_title' ) : '';
$mats_kicker = '' !== trim( $mats_kicker ) ? $mats_kicker : 'MatSurfer';
$mats_lead   = has_excerpt() ? wp_strip_all_tags( get_the_excerpt() ) : '';
$mats_note   = mats_blog_t( 'updated_on' ) . ' ' . mats_blog_date( get_post(), 'long', 'modified' );

$mats_content = (string) apply_filters( 'the_content', get_the_content() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- core filter
list( $mats_content, $mats_index ) = mats_blog_heading_index( $mats_content );
$mats_has_index = count( $mats_index ) >= 3;
?>
<main id="main" class="prose-page">
	<?php
	echo mats_blog_hero( // phpcs:ignore WordPress.Security.EscapeOutput -- rendered section
		array(
			'kicker'     => $mats_kicker,
			'kickerNote' => $mats_note,
			'title'      => wp_strip_all_tags( get_the_title() ),
			'lead'       => $mats_lead,
			'photo'      => mats_blog_thumb(),
		)
	);
	?>
	<div class="sec hairtop prose-page__body">
		<div class="container prose-page__grid<?php echo $mats_has_index ? ' prose-page__grid--index' : ''; ?>">
			<div class="mats-prose prose-page__content">
				<?php echo $mats_content; // phpcs:ignore WordPress.Security.EscapeOutput -- the_content output ?>
			</div>
			<?php if ( $mats_has_index ) : ?>
				<nav class="prose-index" aria-label="<?php echo esc_attr( mats_blog_t( 'on_this_page' ) ); ?>">
					<div class="prose-index__inner">
						<p class="eyebrow"><?php echo esc_html( mats_blog_t( 'on_this_page' ) ); ?></p>
						<ol class="prose-index__list" role="list">
							<?php foreach ( $mats_index as $mats_item ) : ?>
								<li><a href="#<?php echo esc_attr( $mats_item[0] ); ?>"><?php echo esc_html( $mats_item[1] ); ?></a></li>
							<?php endforeach; ?>
						</ol>
					</div>
				</nav>
			<?php endif; ?>
		</div>
	</div>
</main>
