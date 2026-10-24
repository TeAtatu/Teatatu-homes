<?php
/**
 * Multisite: the Homes Master Site and the network cache version.
 *
 * The Master Site (Network Admin > Settings > Teatatu Homes) is the one site
 * on a network — usually the main site — where homes from every site can be
 * shown together: the [teatatu_homes_network_*] shortcodes and
 * GET /wp-json/teatatu-homes/v1/network-feed only produce output there (see
 * includes/network.php). Each site keeps its own listings in its own
 * tables; nothing is copied.
 *
 * Cached network results include a version number in their key; any change
 * to a home anywhere bumps it, which orphans every stale entry at once (they
 * then expire on their own) — no list of cache keys to maintain.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The blog ID set as the Homes Master Site.
 *
 * @return int 0 when none is set, or not a multisite.
 */
function teatatu_homes_get_master_site_id() {
	if ( ! is_multisite() ) {
		return 0;
	}
	return (int) get_site_option( 'teatatu_homes_master_site_id', 0 );
}

/**
 * Whether the current site is the Homes Master Site.
 *
 * @return bool
 */
function teatatu_homes_is_master_site() {
	$master = teatatu_homes_get_master_site_id();
	return $master > 0 && get_current_blog_id() === $master;
}

/**
 * Current network cache version (part of every network cache key).
 *
 * @return int
 */
function teatatu_homes_network_cache_version() {
	return (int) get_site_option( 'teatatu_homes_network_cache_version', 1 );
}

/**
 * Invalidates every cached network result.
 */
function teatatu_homes_bump_network_cache() {
	if ( is_multisite() ) {
		update_site_option( 'teatatu_homes_network_cache_version', teatatu_homes_network_cache_version() + 1 );
	}
}

add_action( 'save_post_teatatu_home', 'teatatu_homes_bump_network_cache' );
add_action( 'rest_after_insert_teatatu_home', 'teatatu_homes_bump_network_cache' );
add_action( 'before_delete_post', 'teatatu_homes_bump_network_cache_on_delete' );
add_action( 'transition_post_status', 'teatatu_homes_bump_network_cache_on_status', 10, 3 );

/**
 * Bumps the cache when a home is deleted.
 *
 * @param int $post_id Post being deleted.
 */
function teatatu_homes_bump_network_cache_on_delete( $post_id ) {
	if ( 'teatatu_home' === get_post_type( $post_id ) ) {
		teatatu_homes_bump_network_cache();
	}
}

/**
 * Bumps the cache when a home is published, unpublished or trashed.
 *
 * @param string  $new  New status.
 * @param string  $old  Old status.
 * @param WP_Post $post Post.
 */
function teatatu_homes_bump_network_cache_on_status( $new, $old, $post ) {
	if ( $new !== $old && 'teatatu_home' === $post->post_type ) {
		teatatu_homes_bump_network_cache();
	}
}
