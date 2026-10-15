<?php
/**
 * Uninstall routine. Runs standalone — the main plugin file is not loaded.
 * Always removes the `teatatu_homes_agent` role/capabilities, the daily
 * cron tick and the plugin's own settings on every site in the network (or
 * the single site). Deletes Home listings, their uploaded photos (the
 * attachments in each home's uploads/homes/<ID>/ folder) and the terms only
 * if the admin opted in on the settings page. Linked photos were never
 * stored, so there is nothing to clean up for them.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/capabilities.php';
require_once __DIR__ . '/includes/taxonomies.php';

// Populate $wp_taxonomies so get_terms() recognises our taxonomies below.
teatatu_homes_register_taxonomies();

/**
 * Per-site cleanup.
 *
 * @param bool $delete_data Whether to also delete Homes and their terms.
 */
function teatatu_homes_uninstall_cleanup_site( $delete_data ) {
	teatatu_homes_remove_role_and_caps();
	wp_clear_scheduled_hook( 'teatatu_homes_daily' );
	delete_option( 'teatatu_homes_caps_version' );

	if ( ! $delete_data ) {
		return;
	}

	$post_ids = get_posts(
		array(
			'post_type'        => 'teatatu_home',
			'post_status'      => 'any',
			'numberposts'      => -1,
			'fields'           => 'ids',
			'suppress_filters' => true,
		)
	);
	$uploads = wp_get_upload_dir();
	foreach ( $post_ids as $post_id ) {
		$attachment_ids = get_posts(
			array(
				'post_type'        => 'attachment',
				'post_parent'      => $post_id,
				'post_status'      => 'any',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);
		foreach ( $attachment_ids as $attachment_id ) {
			wp_delete_attachment( $attachment_id, true );
		}
		$dir = $uploads['basedir'] . '/homes/' . (int) $post_id;
		if ( is_dir( $dir ) && 2 === count( (array) scandir( $dir ) ) ) {
			@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- best effort.
		}
		wp_delete_post( $post_id, true );
	}

	foreach ( array_keys( teatatu_homes_taxonomy_defs() ) as $taxonomy ) {
		$term_ids = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		if ( is_wp_error( $term_ids ) ) {
			continue;
		}
		foreach ( $term_ids as $term_id ) {
			wp_delete_term( $term_id, $taxonomy );
		}
	}
}

$teatatu_homes_options = array( 'teatatu_homes_github_repo', 'teatatu_homes_github_token', 'teatatu_homes_delete_data_on_uninstall', 'teatatu_homes_enquiry_fallback_email' );

if ( is_multisite() ) {
	$delete_data = (bool) get_site_option( 'teatatu_homes_delete_data_on_uninstall', false );
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $site_id ) {
		switch_to_blog( $site_id );
		teatatu_homes_uninstall_cleanup_site( $delete_data );
		restore_current_blog();
	}
	foreach ( $teatatu_homes_options as $option ) {
		delete_site_option( $option );
	}
	delete_site_transient( 'teatatu_homes_github_release' );
} else {
	$delete_data = (bool) get_option( 'teatatu_homes_delete_data_on_uninstall', false );
	teatatu_homes_uninstall_cleanup_site( $delete_data );
	foreach ( $teatatu_homes_options as $option ) {
		delete_option( $option );
	}
	delete_transient( 'teatatu_homes_github_release' );
}
