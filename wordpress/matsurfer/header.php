<?php
/**
 * Document head + site header (port of Header.astro): logo → home, main
 * navigation with the current page marked, EN/ES switcher, global CTA,
 * burger menu under 64rem.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

$mats_lang = mats_lang();
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main"><?php echo esc_html( mats_t( 'skip' ) ); ?></a>
<?php get_template_part( 'template-parts/site-header' ); ?>
