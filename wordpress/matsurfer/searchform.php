<?php
/**
 * Search form (get_search_form()): one field + button, searching in the
 * language being viewed (the form posts to that language's home).
 *
 * @package matsurfer
 */

defined( 'ABSPATH' ) || exit;

$mats_search_id = 'search-' . wp_unique_id();
?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url( mats_home_url() ); ?>">
	<label class="screen-reader-text" for="<?php echo esc_attr( $mats_search_id ); ?>"><?php echo esc_html( mats_blog_t( 'search_label' ) ); ?></label>
	<span class="search-form__field">
		<svg class="search-form__icon" viewBox="0 0 20 20" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" focusable="false"><circle cx="8.5" cy="8.5" r="5.75" /><path d="m13 13 4.25 4.25" /></svg>
		<input type="search" id="<?php echo esc_attr( $mats_search_id ); ?>" class="search-form__input" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php echo esc_attr( mats_blog_t( 'search_ph' ) ); ?>" autocomplete="off" required />
	</span>
	<button type="submit" class="btn search-form__submit"><?php echo esc_html( mats_t( 'search' ) ); ?></button>
</form>
