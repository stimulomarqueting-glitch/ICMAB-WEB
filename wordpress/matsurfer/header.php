<?php
/**
 * Document head + skip link + site header (template-parts/site-header.php,
 * port of Header.astro). Every template opens its content with
 * <main id="main"> — the skip link's target.
 *
 * The tiny inline script marks <html class="mats-js"> before the styles
 * load: only then does base.css hide the .reveal sections until site.js
 * shows them (chrome.css shows them otherwise). It does nothing when it
 * runs late (an optimisation plugin delaying scripts), and it removes the
 * mark if site.js has not started after 3 seconds (blocked, failed), so
 * content never depends on JavaScript.
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_print_inline_script_tag( "(function(d){if(d.readyState!=='loading')return;var h=d.documentElement;h.classList.add('mats-js');setTimeout(function(){if(!h.classList.contains('mats-ready'))h.classList.remove('mats-js');},3000);})(document);" ); ?>
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main"><?php echo esc_html( mats_t( 'skip' ) ); ?></a>
<?php get_template_part( 'template-parts/site-header' ); ?>
