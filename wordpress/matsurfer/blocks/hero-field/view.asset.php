<?php
/**
 * Asset file of view.js, read by WordPress when it registers the block. The
 * script is loaded on the site with its file date as ?ver=, so a theme update
 * that changes view.js is downloaded again instead of coming from the browser
 * cache. (style.css and editor.css are versioned by file time in
 * inc/blocks.php.)
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

return array(
	'dependencies' => array(),
	'version'      => (string) filemtime( __DIR__ . '/view.js' ),
);
