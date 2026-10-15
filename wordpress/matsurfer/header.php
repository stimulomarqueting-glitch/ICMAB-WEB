<?php
/**
 * Document head + skip link + site header (template-parts/site-header.php,
 * port of Header.astro). Every template opens its content with
 * <main id="main"> — the skip link's target.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;
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
