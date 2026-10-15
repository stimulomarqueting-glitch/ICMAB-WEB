<?php
/**
 * Publications (research.astro): section title and the list of papers —
 * title (linked when the paper has a URL), authors · journal, year, DOI and
 * an optional description; a short vapour tick slides in on hover.
 *   <section aria-labelledby="publications-title" class="sec hairtop" id="publications">
 *
 * The papers come from the Publicaciones records (mats_get_publications(),
 * newest year first). Like the original, the section is not printed at
 * all while there is none. Each paper also becomes a ScholarlyArticle node
 * of the page's JSON-LD (collected with mats_jsonld_add(); the SEO module
 * prints them). The extra root class .research-publications only scopes
 * this block's styles.
 *
 * @package matsurfer
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$title      = (string) mats_get( $attributes, 'title' );
$section_id = sanitize_html_class( (string) mats_get( $attributes, 'sectionId', 'publications' ) );
$heading_id = sanitize_html_class( (string) mats_get( $attributes, 'headingId', 'publications-title' ) );

$papers = array_values( mats_get_publications() );

if ( ! $papers ) {
	echo mats_preview_note( 'Esta sección no se muestra en la web hasta que haya alguna publicación publicada.', 'edit.php?post_type=mats_publication', 'Editar publicaciones' ); // phpcs:ignore WordPress.Security.EscapeOutput
	return;
}

/* ids stay unique when the section is placed more than once on a page */
$used_ids = isset( $GLOBALS['mats_used_ids'] ) && is_array( $GLOBALS['mats_used_ids'] ) ? $GLOBALS['mats_used_ids'] : array();
if ( '' === $heading_id || isset( $used_ids[ $heading_id ] ) ) {
	$heading_id = wp_unique_id( 'publications-title-' );
}
if ( isset( $used_ids[ $section_id ] ) ) {
	$section_id = '';
}
$used_ids[ $heading_id ] = true;
if ( '' !== $section_id ) {
	$used_ids[ $section_id ] = true;
}
$GLOBALS['mats_used_ids'] = $used_ids;

/* ScholarlyArticle JSON-LD (once per page, public site only) */
if ( ! mats_is_preview() && empty( $GLOBALS['mats_jsonld_papers'] ) ) {
	$GLOBALS['mats_jsonld_papers'] = true;
	foreach ( $papers as $p ) {
		$node = array(
			'@type'         => 'ScholarlyArticle',
			'headline'      => html_entity_decode( wp_strip_all_tags( (string) $p['title'] ), ENT_QUOTES, 'UTF-8' ),
			'author'        => (string) $p['authors'],
			'datePublished' => (string) $p['year'],
		);
		if ( mats_has( $p['doi'] ) ) {
			$node['sameAs'] = 'https://doi.org/' . $p['doi'];
		}
		mats_jsonld_add( $node );
	}
}

$has_title = mats_has( $title );
?>
<section<?php echo $has_title ? ' aria-labelledby="' . esc_attr( $heading_id ) . '"' : ''; ?> class="sec hairtop research-publications"<?php echo '' !== $section_id ? ' id="' . esc_attr( $section_id ) . '"' : ''; ?>>
	<?php echo mats_preview_note( 'Los artículos salen automáticamente de los publicados en el menú Publicaciones (el año más reciente primero).', 'edit.php?post_type=mats_publication', 'Editar publicaciones' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<div class="container">
		<?php if ( $has_title ) : ?>
			<h2 class="research__head" id="<?php echo esc_attr( $heading_id ); ?>"<?php echo mats_edit( 'title' ); ?>><?php echo mats_text( $title ); ?></h2>
		<?php endif; ?>
		<ul class="research__pubs" role="list">
			<?php foreach ( $papers as $p ) : ?>
				<?php
				$journal = (string) $p['journal'];
				$year    = (int) $p['year'];
				$meta    = mats_text( $p['authors'] );
				if ( mats_has( $journal ) ) {
					$meta .= ( '' !== $meta ? ' · ' : '' ) . '<em>' . mats_text( $journal ) . '</em>';
				}
				if ( $year ) {
					$meta .= ( mats_has( $journal ) ? ', ' : ( '' !== $meta ? ' · ' : '' ) ) . $year;
				}
				?>
				<li class="research__pub reveal">
					<h3><?php if ( mats_has( $p['url'] ) ) : ?><a href="<?php echo esc_url( $p['url'] ); ?>" target="_blank" rel="noopener"><?php echo mats_text( $p['title'] ); ?></a><?php else : ?><?php echo mats_text( $p['title'] ); ?><?php endif; ?></h3>
					<p class="research__meta">
						<?php echo $meta; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above ?>
						<?php if ( mats_has( $p['doi'] ) ) : ?><span class="research__doi"> · DOI: <?php echo mats_text( $p['doi'] ); ?></span><?php endif; ?>
					</p>
					<?php if ( mats_has( $p['description'] ) ) : ?>
						<p class="research__desc"><?php echo mats_text( $p['description'] ); ?></p>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
