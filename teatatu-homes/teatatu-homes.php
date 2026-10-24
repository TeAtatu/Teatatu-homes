<?php
/**
 * Plugin Name: Teatatu Homes
 * Plugin URI: https://github.com/TeAtatu/Teatatu-homes
 * Description: The data layer for a Te Atatū Peninsula homes site: a "Homes" listing post type covering property sales and rentals, with Listing Type, Property Type, Area, Feature and Agency taxonomies, a single validated field model (price, rooms, address, open homes, photos uploaded to a per-home Media Library folder or linked), a filtered search query shared by the theme and the REST API, listing enquiries, and a draft-only AI-agent role. Display is left entirely to the theme. Uses only standard WordPress database tables.
 * Version: 1.2.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: TeAtatu
 * Author URI: https://github.com/TeAtatu
 * License: GPL-3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: teatatu-homes
 * Update URI: https://github.com/TeAtatu/Teatatu-homes
 * Network: true
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TEATATU_HOMES_VERSION', '1.2.0' );
define( 'TEATATU_HOMES_FILE', __FILE__ );
define( 'TEATATU_HOMES_DIR', plugin_dir_path( __FILE__ ) );
define( 'TEATATU_HOMES_URL', plugin_dir_url( __FILE__ ) );
define( 'TEATATU_HOMES_BASENAME', plugin_basename( __FILE__ ) );

require_once TEATATU_HOMES_DIR . 'includes/capabilities.php';
require_once TEATATU_HOMES_DIR . 'includes/settings.php';
require_once TEATATU_HOMES_DIR . 'includes/post-type.php';
require_once TEATATU_HOMES_DIR . 'includes/taxonomies.php';
require_once TEATATU_HOMES_DIR . 'includes/areas.php';
require_once TEATATU_HOMES_DIR . 'includes/address.php';
require_once TEATATU_HOMES_DIR . 'includes/photos.php';
require_once TEATATU_HOMES_DIR . 'includes/home-data.php';
require_once TEATATU_HOMES_DIR . 'includes/query.php';
require_once TEATATU_HOMES_DIR . 'includes/meta-boxes.php';
require_once TEATATU_HOMES_DIR . 'includes/admin-columns.php';
require_once TEATATU_HOMES_DIR . 'includes/rest-api.php';
require_once TEATATU_HOMES_DIR . 'includes/enquiries.php';
require_once TEATATU_HOMES_DIR . 'includes/updater.php';
require_once TEATATU_HOMES_DIR . 'includes/theme-updater.php';

/**
 * Fired on plugin activation. Handles both single-site and network activation.
 *
 * @param bool $network_wide Whether the plugin is being network-activated.
 */
function teatatu_homes_activate( $network_wide ) {
	if ( is_multisite() && $network_wide ) {
		$site_ids = get_sites( array( 'fields' => 'ids' ) );
		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );
			teatatu_homes_provision_site();
			restore_current_blog();
		}
	} else {
		teatatu_homes_provision_site();
	}
}
register_activation_hook( TEATATU_HOMES_FILE, 'teatatu_homes_activate' );

/**
 * Runs the per-site provisioning routine: roles/capabilities, the fixed
 * terms (Listing Types, Areas, starter Property Types), and a rewrite flush.
 */
function teatatu_homes_provision_site() {
	teatatu_homes_add_role_and_caps();
	teatatu_homes_register_post_type();
	teatatu_homes_register_taxonomies();
	teatatu_homes_seed_terms();
	flush_rewrite_rules();
}

/**
 * Fired on plugin deactivation.
 *
 * @param bool $network_wide Whether the plugin was network-activated.
 */
function teatatu_homes_deactivate( $network_wide ) {
	if ( is_multisite() && $network_wide ) {
		$site_ids = get_sites( array( 'fields' => 'ids' ) );
		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );
			flush_rewrite_rules();
			wp_clear_scheduled_hook( 'teatatu_homes_daily' );
			restore_current_blog();
		}
	} else {
		flush_rewrite_rules();
		wp_clear_scheduled_hook( 'teatatu_homes_daily' );
	}
}
register_deactivation_hook( TEATATU_HOMES_FILE, 'teatatu_homes_deactivate' );

/**
 * Provisions a brand-new site created after network activation.
 *
 * @param WP_Site $new_site Newly created site.
 */
function teatatu_homes_new_site( $new_site ) {
	if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	if ( ! is_plugin_active_for_network( TEATATU_HOMES_BASENAME ) ) {
		return;
	}
	switch_to_blog( (int) $new_site->blog_id );
	teatatu_homes_provision_site();
	restore_current_blog();
}
add_action( 'wp_initialize_site', 'teatatu_homes_new_site' );
