<?php
/**
 * Not found. Listings that have been removed land here too, so point
 * people back to the search.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
get_header();
?>
<div class="tth-wrap tth-page">
	<h1><?php esc_html_e( 'This page has moved on', 'teatatu-homes-theme' ); ?></h1>
	<p class="tth-lead" style="margin-bottom:24px;"><?php esc_html_e( 'The home may have sold, been leased or been taken off the market.', 'teatatu-homes-theme' ); ?></p>
	<a class="tth-btn" href="<?php echo esc_url( tth_plugin_ready() ? tth_search_url() : home_url( '/' ) ); ?>"><?php esc_html_e( 'Search homes on the Peninsula', 'teatatu-homes-theme' ); ?></a>
</div>
<?php
get_footer();
