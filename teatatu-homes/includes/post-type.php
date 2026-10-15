<?php
/**
 * Registers the `teatatu_home` custom post type — one post per listing,
 * whether for sale or for rent (the Listing Type taxonomy says which).
 * The post title is the display headline, the editor holds the full
 * description and the excerpt the short card summary. No 'thumbnail'
 * support — photos (uploads and links, in order) are the `photos` field
 * (see includes/photos.php). This file renders nothing on the front end;
 * display belongs to the theme.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'teatatu_homes_register_post_type' );

/**
 * Registers the Homes post type.
 */
function teatatu_homes_register_post_type() {
	$labels = array(
		'name'               => __( 'Homes', 'teatatu-homes' ),
		'singular_name'      => __( 'Home', 'teatatu-homes' ),
		'menu_name'          => __( 'Homes', 'teatatu-homes' ),
		'add_new'            => __( 'Add New', 'teatatu-homes' ),
		'add_new_item'       => __( 'Add New Home', 'teatatu-homes' ),
		'edit_item'          => __( 'Edit Home', 'teatatu-homes' ),
		'new_item'           => __( 'New Home', 'teatatu-homes' ),
		'view_item'          => __( 'View Home', 'teatatu-homes' ),
		'view_items'         => __( 'View Homes', 'teatatu-homes' ),
		'search_items'       => __( 'Search Homes', 'teatatu-homes' ),
		'not_found'          => __( 'No Homes found', 'teatatu-homes' ),
		'not_found_in_trash' => __( 'No Homes found in Trash', 'teatatu-homes' ),
		'all_items'          => __( 'All Homes', 'teatatu-homes' ),
		'archives'           => __( 'Homes', 'teatatu-homes' ),
	);

	register_post_type(
		'teatatu_home',
		array(
			'labels'              => $labels,
			'public'              => true,
			'show_in_rest'        => true,
			'rest_base'           => 'homes',
			'supports'            => array( 'title', 'editor', 'excerpt', 'author', 'revisions', 'custom-fields' ),
			'menu_icon'           => 'dashicons-admin-home',
			'has_archive'         => true,
			'rewrite'             => array( 'slug' => 'homes' ),
			'capability_type'     => array( 'teatatu_home', 'teatatu_homes' ),
			'map_meta_cap'        => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 20,
			'hierarchical'        => false,
			'exclude_from_search' => false,
		)
	);
}
