<?php
/**
 * News article. Header in the PageHero language (meta row, h1 rising word
 * by word, lead = the excerpt when it was written by hand, featured image
 * on a plate), the block content in a reading column with a share rail,
 * previous / next article and the closing CTA band.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$mats_post  = get_post();
	$mats_title = wp_strip_all_tags( get_the_title() );
	$mats_cat   = mats_blog_category( $mats_post );
	$mats_thumb = mats_blog_thumb( $mats_post );
	$mats_lead  = has_excerpt() ? wp_strip_all_tags( get_the_excerpt() ) : '';
	$mats_words = count( preg_split( '/\s+/u', trim( $mats_title ) ) ?: array() );
	$mats_url   = (string) get_permalink();
	$mats_photo = $mats_thumb ? mats_image(
		$mats_thumb,
		array(
			'class'         => 'post-hero__photo',
			'size'          => 'mats-wide',
			'sizes'         => '(max-width: 56rem) calc(100vw - 2rem), (max-width: 79rem) 42vw, 520px',
			'fetchpriority' => 'high',
		)
	) : '';
	?>
	<main id="main" class="post-main">
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'post' ); ?>>
			<header class="sec post-hero">
				<div class="container post-hero__grid<?php echo $mats_photo ? ' post-hero__grid--photo' : ''; ?>">
					<div class="post-hero__copy">
						<a class="post-hero__back rise-in" style="--i: 0" href="<?php echo esc_url( mats_blog_news_url() ); ?>"><span aria-hidden="true">←</span> <?php echo esc_html( mats_t( 'back_to_news' ) ); ?></a>
						<p class="post-meta rise-in" style="--i: 0">
							<?php if ( $mats_cat ) : ?>
								<a class="post-meta__cat" href="<?php echo esc_url( get_category_link( $mats_cat ) ); ?>"><?php echo esc_html( $mats_cat->name ); ?></a>
								<span class="post-meta__sep" aria-hidden="true">·</span>
							<?php endif; ?>
							<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( mats_blog_date( $mats_post ) ); ?></time>
							<span class="post-meta__sep" aria-hidden="true">·</span>
							<span><?php echo esc_html( mats_reading_time_label( $mats_post ) ); ?></span>
						</p>
						<h1 class="post-hero__title"><?php echo mats_words( $mats_title, '<span class="post-hero__word"><span class="rise-in" style="--i: %2$d">%1$s</span></span> ', 1 ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped word by word ?></h1>
						<?php if ( '' !== $mats_lead ) : ?>
							<p class="lead post-hero__lead rise-in" style="--i: <?php echo (int) ( $mats_words + 1 ); ?>"><?php echo esc_html( $mats_lead ); ?></p>
						<?php endif; ?>
					</div>
					<?php if ( $mats_photo ) : ?>
						<div class="post-hero__stage rise-in" style="--i: 2">
							<div class="post-hero__plate">
								<span class="post-hero__halo" aria-hidden="true"></span>
								<?php echo $mats_photo; // phpcs:ignore WordPress.Security.EscapeOutput -- built by mats_image() ?>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</header>

			<div class="sec hairtop post-body">
				<div class="container post-body__grid">
					<div class="mats-prose post-content">
						<?php the_content(); ?>
					</div>
					<aside class="post-aside" aria-label="<?php echo esc_attr( mats_t( 'share' ) ); ?>">
						<div class="post-aside__inner">
							<p class="eyebrow"><?php echo esc_html( mats_t( 'share' ) ); ?></p>
							<ul class="post-share" role="list">
								<li>
									<a class="post-share__link" href="<?php echo esc_url( 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode( $mats_url ) ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( mats_blog_t( 'share_linkedin' ) ); ?>">
										<span class="post-share__icon" aria-hidden="true"><?php echo mats_icon( 'linkedin' ); // phpcs:ignore WordPress.Security.EscapeOutput -- theme file ?></span>
										<span>LinkedIn</span>
									</a>
								</li>
								<li>
									<a class="post-share__link" href="<?php echo esc_url( 'mailto:?subject=' . rawurlencode( $mats_title ) . '&body=' . rawurlencode( $mats_url ) ); ?>" aria-label="<?php echo esc_attr( mats_blog_t( 'share_email' ) ); ?>">
										<span class="post-share__icon" aria-hidden="true"><?php echo mats_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput -- theme file ?></span>
										<span>Email</span>
									</a>
								</li>
							</ul>
							<a class="post-aside__back" href="<?php echo esc_url( mats_blog_news_url() ); ?>"><span aria-hidden="true">←</span> <?php echo esc_html( mats_t( 'all_news' ) ); ?></a>
						</div>
					</aside>
				</div>
			</div>
		</article>

		<?php
		$mats_prev = get_previous_post();
		$mats_next = get_next_post();
		if ( $mats_prev || $mats_next ) :
			?>
			<nav class="sec hairtop post-nav" aria-label="<?php echo esc_attr( mats_blog_t( 'article_nav' ) ); ?>">
				<div class="container post-nav__grid">
					<?php
					foreach ( array(
						'prev' => $mats_prev,
						'next' => $mats_next,
					) as $mats_dir => $mats_adj ) :
						if ( ! $mats_adj ) :
							echo '<span class="post-nav__spacer" aria-hidden="true"></span>';
							continue;
						endif;
						?>
						<a class="panel post-nav__link post-nav__link--<?php echo esc_attr( $mats_dir ); ?>" href="<?php echo esc_url( get_permalink( $mats_adj ) ); ?>" rel="<?php echo esc_attr( $mats_dir ); ?>">
							<span class="eyebrow post-nav__label">
								<?php if ( 'prev' === $mats_dir ) : ?>
									<span aria-hidden="true">←</span> <?php echo esc_html( mats_blog_t( 'previous_article' ) ); ?>
								<?php else : ?>
									<?php echo esc_html( mats_blog_t( 'next_article' ) ); ?> <span aria-hidden="true">→</span>
								<?php endif; ?>
							</span>
							<span class="post-nav__title"><?php echo esc_html( wp_strip_all_tags( get_the_title( $mats_adj ) ) ); ?></span>
							<time class="post-nav__date" datetime="<?php echo esc_attr( get_the_date( 'c', $mats_adj ) ); ?>"><?php echo esc_html( mats_blog_date( $mats_adj, 'short' ) ); ?></time>
						</a>
					<?php endforeach; ?>
				</div>
			</nav>
		<?php endif; ?>

		<?php echo mats_blog_cta( 'news-article-final' ); // phpcs:ignore WordPress.Security.EscapeOutput -- rendered section ?>
	</main>
	<?php
endwhile;

get_footer();
