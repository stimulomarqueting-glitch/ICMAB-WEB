<?php
/**
 * Full-viewport team showcase (TeamShowcase.astro, top of the team page):
 * one cut-out portrait at a time over the plum studio backdrop, the page
 * headline (h1) on the left, the active person's bio on the right, and an
 * avatar picker with a meta line (name · role · affiliation · LinkedIn)
 * along the bottom.
 *
 * The people come from the Equipo records (mats_get_team(): published
 * only, in display order). Every profile's bio and meta line is in the
 * HTML; view.js only toggles which one is active (auto-rotation, picker,
 * arrow keys). With no published profile the section falls back to the
 * plain page hero of team.astro (kicker + h1 + lead).
 *
 * Also adds the Person JSON-LD nodes team.astro builds (published
 * profiles only — never invented).
 *
 * @package matsurfer
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$kicker         = (string) mats_get( $attributes, 'kicker' );
$title          = trim( (string) mats_get( $attributes, 'title' ) );
$lead           = (string) mats_get( $attributes, 'lead' );
$picker_label   = (string) mats_get( $attributes, 'pickerLabel' );
$show_label     = trim( (string) mats_get( $attributes, 'showLabel' ) );
$no_bio         = (string) mats_get( $attributes, 'noBio' );
$linkedin_label = trim( (string) mats_get( $attributes, 'linkedinLabel' ) );
$website_label  = trim( (string) mats_get( $attributes, 'websiteLabel' ) );
$linkedin_of    = trim( (string) mats_get( $attributes, 'linkedinOf' ) );
$website_of     = trim( (string) mats_get( $attributes, 'websiteOf' ) );
$linkedin_label = '' !== $linkedin_label ? $linkedin_label : 'LinkedIn';

$members = function_exists( 'mats_get_team' ) ? array_values( mats_get_team() ) : array();

/* the h1 id stays unique if the section is placed twice; claimed only once
   an h1 is actually printed, so an empty copy never takes 'page-title' */
$claim_heading_id = static function (): string {
	$id       = 'page-title';
	$used_ids = isset( $GLOBALS['mats_used_ids'] ) && is_array( $GLOBALS['mats_used_ids'] ) ? $GLOBALS['mats_used_ids'] : array();
	if ( isset( $used_ids[ $id ] ) ) {
		$id = wp_unique_id( 'page-title-' );
	}
	$used_ids[ $id ]          = true;
	$GLOBALS['mats_used_ids'] = $used_ids;
	return $id;
};

/* ── no published profile: the plain page hero (PageHero.astro markup) ── */
if ( ! $members ) {
	if ( ! mats_has( $title ) ) {
		echo mats_preview_empty( 'Escaparate del equipo' ); // phpcs:ignore WordPress.Security.EscapeOutput
		return;
	}
	$heading_id = $claim_heading_id();
	$n_words    = count( preg_split( '/\s+/u', $title ) ?: array() );
	echo mats_preview_note( 'Todavía no hay ninguna persona publicada en Equipo, así que esta sección se muestra como una cabecera sencilla. En cuanto publiques una, aparecerá el escaparate con sus fotos.', 'edit.php?post_type=mats_team', 'Editar equipo' ); // phpcs:ignore WordPress.Security.EscapeOutput
	?>
	<section class="sec phero tshow-hero" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>" data-phero>
		<div class="container phero__grid">
			<div>
				<?php if ( mats_has( $kicker ) ) : ?>
					<p class="eyebrow rise-in" style="--i: 0"<?php echo mats_edit( 'kicker' ); ?>><?php echo mats_text( $kicker ); ?></p>
				<?php endif; ?>
				<h1 class="phero__title" id="<?php echo esc_attr( $heading_id ); ?>"<?php echo mats_edit( 'title' ); ?>><?php echo mats_words( $title, '<span class="phero__word"><span class="rise-in" style="--i: %2$d">%1$s</span></span> ', 1 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></h1>
				<?php if ( mats_has( $lead ) ) : ?>
					<p class="lead rise-in" style="--i: <?php echo (int) ( $n_words + 1 ); ?>"<?php echo mats_edit( 'lead' ); ?>><?php echo mats_text( $lead ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	</section>
	<?php
	return;
}

/* initials of the first two words: the monogram stand-in */
$initials_of = static function ( string $name ): string {
	$words = array_slice( preg_split( '/\s+/u', trim( $name ), -1, PREG_SPLIT_NO_EMPTY ) ?: array(), 0, 2 );
	$out   = '';
	foreach ( $words as $word ) {
		$out .= mb_substr( $word, 0, 1 );
	}
	return $out;
};

/* open on the first profile that has a portrait; the monogram stand-in is
   never the first impression while a photo is still pending */
$default_index = 0;
foreach ( $members as $i => $m ) {
	if ( ! empty( $m['showcaseImage'] ) ) {
		$default_index = $i;
		break;
	}
}
$on = static function ( int $i ) use ( $default_index ): string {
	return $i === $default_index ? ' data-active' : '';
};

/* Person JSON-LD (team.astro): published profiles only, once per request.
   Plain text, as Astro writes it: the name is the title as typed in Equipo
   (get_the_title() would add typographic entities such as &#8217;), and
   every value is entity-decoded and tag-free. */
if ( ! mats_is_preview() && empty( $GLOBALS['mats_team_jsonld'] ) && function_exists( 'mats_jsonld_add' ) ) {
	$GLOBALS['mats_team_jsonld'] = true;
	$org_id                      = (string) apply_filters( 'mats_organization_id', untrailingslashit( (string) get_option( 'home' ) ) . '/#organization' );
	$plain                       = static function ( $value ): string {
		$value = html_entity_decode( (string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$value = wp_strip_all_tags( $value, true );
		return trim( (string) preg_replace( '/\s+/u', ' ', $value ) );
	};
	foreach ( $members as $m ) {
		$raw_name = ! empty( $m['id'] ) ? (string) get_post_field( 'post_title', (int) $m['id'], 'raw' ) : '';
		$node     = array(
			'@type'       => 'Person',
			'name'        => $plain( '' !== trim( $raw_name ) ? $raw_name : $m['name'] ),
			'jobTitle'    => $plain( $m['role'] ),
			'affiliation' => $plain( $m['affiliation'] ),
		);
		if ( mats_has( $m['linkedin'] ) ) {
			$node['sameAs'] = (string) $m['linkedin'];
		}
		$node['worksFor'] = array( '@id' => $org_id );
		mats_jsonld_add( $node );
	}
}

/* the section is named by its h1; with the title emptied there is no h1
   (never an empty heading) and the section takes the kicker, or the
   picker label, as its accessible name */
$has_title = mats_has( $title );
if ( $has_title ) {
	$heading_id   = $claim_heading_id();
	$section_name = ' aria-labelledby="' . esc_attr( $heading_id ) . '"';
} else {
	$heading_id   = '';
	$fallback     = mats_has( $kicker ) ? trim( $kicker ) : trim( $picker_label );
	$section_name = '' !== $fallback ? ' aria-label="' . esc_attr( $fallback ) . '"' : '';
}

$linkedin_icon = mats_icon( 'linkedin' );
/* editor only: where the people are edited (above the section, so it
   never covers the headline or the bio) */
echo mats_preview_note( 'Las fotos, cargos, biografías y enlaces salen de las personas publicadas en Equipo. En la web, el escaparate cambia de persona solo cada pocos segundos.', 'edit.php?post_type=mats_team', 'Editar equipo' ); // phpcs:ignore WordPress.Security.EscapeOutput
if ( ! $has_title ) {
	echo mats_preview_note( 'Falta el título de la página. Escríbelo en «Título de la página (H1)», en el panel de la derecha: mientras esté vacío, la página no tiene título principal, y eso perjudica a los buscadores y a los lectores de pantalla.' ); // phpcs:ignore WordPress.Security.EscapeOutput
}
?>
<section class="show"<?php echo $section_name; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above ?> data-showcase data-interval="5500">
	<div class="show__bg" aria-hidden="true"></div>

	<div class="show__stage" aria-hidden="true">
		<?php foreach ( $members as $i => $m ) : ?>
			<?php $has_photo = ! empty( $m['showcaseImage'] ); ?>
			<figure class="show__person<?php echo $has_photo ? '' : ' show__person--mono'; ?>" data-index="<?php echo (int) $i; ?>"<?php echo $on( $i ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
				<?php
				$photo_html = $has_photo ? mats_image(
					$m['showcaseImage'],
					array(
						'size'          => 'full',
						'sizes'         => '(max-width: 40rem) 84vw, (max-width: 63.98rem) 60vw, min(44vw, 40rem)',
						'decorative'    => true,
						'fetchpriority' => $i === $default_index ? 'high' : 'low',
					)
				) : '';
				if ( '' !== $photo_html ) :
					echo $photo_html; // phpcs:ignore WordPress.Security.EscapeOutput -- built by mats_image()
				else :
					?>
					<span><?php echo mats_text( $initials_of( (string) $m['name'] ) ); ?></span>
				<?php endif; ?>
			</figure>
		<?php endforeach; ?>
	</div>

	<div class="show__scrim" aria-hidden="true"></div>

	<div class="container show__content">
		<div class="show__top">
			<div>
				<?php if ( mats_has( $kicker ) ) : ?>
					<p class="eyebrow show__kicker"<?php echo mats_edit( 'kicker' ); ?>><?php echo mats_text( $kicker ); ?></p>
				<?php endif; ?>
				<?php if ( $has_title ) : ?>
					<h1 class="show__title" id="<?php echo esc_attr( $heading_id ); ?>"<?php echo mats_edit( 'title' ); ?>><?php echo mats_text( $title ); ?></h1>
				<?php endif; ?>
			</div>
			<div class="show__bios" aria-live="polite">
				<?php foreach ( $members as $i => $m ) : ?>
					<?php $bio = trim( (string) $m['bio'] ); ?>
					<?php if ( '' !== $bio ) : ?>
						<p class="show__bio is-in" data-index="<?php echo (int) $i; ?>"<?php echo $on( $i ); // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php echo mats_text( $bio ); ?></p>
					<?php else : ?>
						<p class="show__bio is-in" data-index="<?php echo (int) $i; ?>"<?php echo $on( $i ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo mats_edit( 'noBio' ); ?>><?php echo mats_text( $no_bio ); ?></p>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="show__bottom">
			<div class="show__picker" role="group"<?php echo mats_has( $picker_label ) ? ' aria-label="' . esc_attr( $picker_label ) . '"' : ''; ?>>
				<?php foreach ( $members as $i => $m ) : ?>
					<?php
					$thumb_html = ! empty( $m['image'] ) ? mats_image(
						$m['image'],
						array(
							'size'       => 'thumbnail',
							'decorative' => true,
							'attrs'      => array(
								'width'  => 56,
								'height' => 56,
							),
						)
					) : '';
					?>
					<button
						type="button"
						class="show__avatar"
						data-index="<?php echo (int) $i; ?>"
						aria-pressed="<?php echo $i === $default_index ? 'true' : 'false'; ?>"
						aria-label="<?php echo esc_attr( trim( $show_label . ' ' . $m['name'] ) ); ?>"
					>
						<span class="show__dot" aria-hidden="true"></span>
						<span class="show__thumb">
							<?php if ( '' !== $thumb_html ) : ?>
								<?php echo $thumb_html; // phpcs:ignore WordPress.Security.EscapeOutput -- built by mats_image() ?>
							<?php else : ?>
								<span class="show__thumb-mono"><?php echo mats_text( $initials_of( (string) $m['name'] ) ); ?></span>
							<?php endif; ?>
						</span>
					</button>
				<?php endforeach; ?>
			</div>

			<div class="show__metas">
				<?php foreach ( $members as $i => $m ) : ?>
					<div class="show__meta" data-index="<?php echo (int) $i; ?>"<?php echo $on( $i ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
						<span class="show__name is-in"><?php echo mats_text( $m['name'] ); ?></span>
						<span class="show__role"><?php echo mats_text( $m['role'] ); ?></span>
						<span class="show__aff"><?php echo mats_text( $m['affiliation'] ); ?></span>
						<?php if ( mats_has( $m['linkedin'] ) ) : ?>
							<a class="show__link" href="<?php echo esc_url( $m['linkedin'] ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( trim( $m['name'] . ' ' . $linkedin_of ) ); ?>">
								<span class="show__link-icon" aria-hidden="true"><?php echo $linkedin_icon; // phpcs:ignore WordPress.Security.EscapeOutput -- the theme's own SVG file ?></span>
								<?php echo mats_text( $linkedin_label ); ?>
							</a>
						<?php elseif ( mats_has( $m['website'] ) ) : ?>
							<?php
							$site_label = '' !== $website_label ? $website_label : (string) wp_parse_url( (string) $m['website'], PHP_URL_HOST );
							$site_label = '' !== $site_label ? $site_label : (string) $m['website'];
							?>
							<a class="show__link" href="<?php echo esc_url( $m['website'] ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( trim( $m['name'] . ' ' . $website_of ) ); ?>"><?php echo mats_text( $site_label ); ?></a>
						<?php else : ?>
							<span></span>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

	<div class="show__strip" aria-hidden="true"></div>
</section>
