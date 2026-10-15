<?php
/**
 * QA (module "blog"): router for the PHP built-in server that lets one
 * request pretend the theme has no theme.json, to prove theme.json does not
 * change the landing pages. Only affects the instance started with it:
 *   cd /home/user/wpsite && php -S localhost:8914 /home/user/ICMAB-WEB/wordpress/tools/qa/blog-notj-router.php
 *   QA_COOKIE=mats_notj=1 WP_BASE=http://localhost:8914 node wordpress/tools/qa/blog-landing.mjs <dir> notj
 *   WP_BASE=http://localhost:8914 node wordpress/tools/qa/blog-landing.mjs <dir> tj
 *   python3 -I wordpress/tools/qa/blog-landing-diff.py <dir> notj tj
 * Restart the normal server afterwards: /home/user/wp-serve.sh 8914
 */
if ( ! empty( $_COOKIE['mats_notj'] ) ) {
	/* pre-registered hook (WP_Hook::build_preinitialized_hooks) */
	$GLOBALS['wp_filter']['theme_file_path'][10][] = array(
		'function'      => static function ( $path, $file = '' ) {
			return 'theme.json' === $file ? '/nonexistent/theme.json' : $path;
		},
		'accepted_args' => 2,
	);
}
return require '/home/user/wpsite-router.php';
