<?php
/**
 * QA (module "blog", fix pass): router for the PHP built-in server that lets
 * single requests simulate situations on this instance only (cookies):
 *   mats_lim=N    the search checks at most N candidates one by one, so the
 *                 "too many candidates" SQL branch is exercised
 *   mats_edfix=1  the editor module's theme.json filter is switched off
 *                 (filter mats_editor_lock_design), i.e. theme.json alone
 *                 decides palette, font sizes and block gap — what the site
 *                 looks like once editor.php stops overriding those keys
 *   mats_notj=1   no theme.json at all (as blog-notj-router.php)
 *   mats_tags=1   articles keep their Tags box (filter mats_admin_disable_tags
 *                 of the admin module), so single.php shows the tag chips
 * Start:   cd /home/user/wpsite && PHP_CLI_SERVER_WORKERS=4 php -S localhost:8914 /home/user/ICMAB-WEB/wordpress/tools/qa/blog-fix-router.php
 * Restore: /home/user/wp-serve.sh 8914
 */
if ( ! empty( $_COOKIE['mats_lim'] ) ) {
	$mats_qa_lim = (int) $_COOKIE['mats_lim'];
	$GLOBALS['wp_filter']['mats_blog_search_check_limit'][10][] = array(
		'function'      => static fn() => $mats_qa_lim,
		'accepted_args' => 1,
	);
}
if ( ! empty( $_COOKIE['mats_edfix'] ) ) {
	$GLOBALS['wp_filter']['mats_editor_lock_design'][10][] = array(
		'function'      => '__return_false',
		'accepted_args' => 1,
	);
}
if ( ! empty( $_COOKIE['mats_tags'] ) ) {
	$GLOBALS['wp_filter']['mats_admin_disable_tags'][10][] = array(
		'function'      => '__return_false',
		'accepted_args' => 1,
	);
}
if ( ! empty( $_COOKIE['mats_notj'] ) ) {
	$GLOBALS['wp_filter']['theme_file_path'][10][] = array(
		'function'      => static function ( $path, $file = '' ) {
			return 'theme.json' === $file ? '/nonexistent/theme.json' : $path;
		},
		'accepted_args' => 2,
	);
}
return require '/home/user/wpsite-router.php';
