<?php
/**
 * Custom role, capability provisioning, and taxonomy-capability mapping for
 * the `teatatu_homes_agent` role (AI agents: draft only) plus human editor
 * roles.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Capabilities granted to the restricted AI-agent role. It can draft and
 * tidy its own unpublished Homes and assign terms, but never publish.
 * Listing Types and Areas are fixed vocabularies, so the agent may assign
 * them but not manage them.
 *
 * @return string[]
 */
function teatatu_homes_agent_capabilities() {
	return array(
		'read'                            => true,
		'edit_teatatu_homes'              => true,
		'delete_teatatu_homes'            => true,
		'assign_teatatu_home_listings'    => true,
		'assign_teatatu_home_types'       => true,
		'assign_teatatu_home_areas'       => true,
		'assign_teatatu_home_features'    => true,
		'assign_teatatu_home_agencies'    => true,
		'manage_teatatu_home_features'    => true,
		'manage_teatatu_home_agencies'    => true,
	);
}

/**
 * Full set of Homes capabilities granted to Administrator and Editor.
 *
 * @return string[]
 */
function teatatu_homes_editor_capabilities() {
	return array(
		'edit_teatatu_home'                => true,
		'read_teatatu_home'                => true,
		'delete_teatatu_home'              => true,
		'edit_teatatu_homes'               => true,
		'edit_others_teatatu_homes'        => true,
		'publish_teatatu_homes'            => true,
		'read_private_teatatu_homes'       => true,
		'delete_teatatu_homes'             => true,
		'delete_private_teatatu_homes'     => true,
		'delete_published_teatatu_homes'   => true,
		'delete_others_teatatu_homes'      => true,
		'edit_private_teatatu_homes'       => true,
		'edit_published_teatatu_homes'     => true,
		'manage_teatatu_home_listings'     => true,
		'assign_teatatu_home_listings'     => true,
		'manage_teatatu_home_types'        => true,
		'assign_teatatu_home_types'        => true,
		'manage_teatatu_home_areas'        => true,
		'assign_teatatu_home_areas'        => true,
		'manage_teatatu_home_features'     => true,
		'assign_teatatu_home_features'     => true,
		'manage_teatatu_home_agencies'     => true,
		'assign_teatatu_home_agencies'     => true,
	);
}

/**
 * Adds the `teatatu_homes_agent` role and grants Homes capabilities to
 * Administrator/Editor on the current site. Safe to call repeatedly.
 */
function teatatu_homes_add_role_and_caps() {
	add_role(
		'teatatu_homes_agent',
		__( 'Homes Agent', 'teatatu-homes' ),
		teatatu_homes_agent_capabilities()
	);

	// Re-adding an existing role with add_role() is a no-op, so refresh caps explicitly.
	$agent_role = get_role( 'teatatu_homes_agent' );
	if ( $agent_role ) {
		foreach ( teatatu_homes_agent_capabilities() as $cap => $grant ) {
			$agent_role->add_cap( $cap, $grant );
		}
	}

	foreach ( array( 'administrator', 'editor' ) as $role_name ) {
		$role = get_role( $role_name );
		if ( ! $role ) {
			continue;
		}
		foreach ( teatatu_homes_editor_capabilities() as $cap => $grant ) {
			$role->add_cap( $cap, $grant );
		}
	}
}

/**
 * Re-syncs roles/capabilities (and the fixed terms) whenever the plugin's
 * version has changed since they were last provisioned on this site, so an
 * in-place file update never needs a manual reactivation.
 */
function teatatu_homes_maybe_upgrade_caps() {
	if ( get_option( 'teatatu_homes_caps_version' ) !== TEATATU_HOMES_VERSION ) {
		teatatu_homes_add_role_and_caps();
		teatatu_homes_seed_terms();
		update_option( 'teatatu_homes_caps_version', TEATATU_HOMES_VERSION );
	}
}
add_action( 'admin_init', 'teatatu_homes_maybe_upgrade_caps' );

/**
 * Removes the `teatatu_homes_agent` role and strips Homes capabilities from
 * Administrator/Editor on the current site.
 */
function teatatu_homes_remove_role_and_caps() {
	remove_role( 'teatatu_homes_agent' );

	foreach ( array( 'administrator', 'editor' ) as $role_name ) {
		$role = get_role( $role_name );
		if ( ! $role ) {
			continue;
		}
		foreach ( array_keys( teatatu_homes_editor_capabilities() ) as $cap ) {
			$role->remove_cap( $cap );
		}
	}
}
