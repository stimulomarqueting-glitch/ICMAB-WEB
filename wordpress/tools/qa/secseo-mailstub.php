<?php
/**
 * QA (secseo): makes wp_mail() succeed without sending anything, so the
 * lost-password form behaves as on a host where mail works. Loaded with
 *   php -d auto_prepend_file=wordpress/tools/qa/secseo-mailstub.php -S …
 * (secseo-fixpass.mjs restarts its own port with it and puts the normal
 * server back at the end). Uses WordPress' pre-initialised hooks format.
 *
 * @package matsurfer-qa
 */

$GLOBALS['wp_filter']['pre_wp_mail'][10]['mats_secseo_mailstub'] = array(
	'function'      => static function () {
		return true;
	},
	'accepted_args' => 0,
);
