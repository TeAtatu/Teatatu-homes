<?php
/**
 * Areas: every Te Atatū Peninsula address falls into one of three areas —
 * Matipo, Beach or Harbourview — the same split, slugs and descriptions the
 * Teatatu Events plugin uses, so a home and an event on the same street
 * land in the same area.
 *
 * Resolution order when a home is saved (see teatatu_homes_assign_area()):
 *  1. An Area an editor ticked by hand always wins.
 *  2. Otherwise, if Teatatu Events is active on this site, its street rules
 *     and boundary lines resolve the address (no external service).
 *  3. Otherwise the home is flagged "Needs area" for an editor.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The three Areas: slug => [name, description]. The slugs are fixed.
 *
 * @return array
 */
function teatatu_homes_area_defs() {
	return array(
		'matipo'      => array( __( 'Matipo', 'teatatu-homes' ), __( 'North of Taikata Road and west of Te Atatū Road.', 'teatatu-homes' ) ),
		'beach'       => array( __( 'Beach', 'teatatu-homes' ), __( 'North of Harbour View Road and east of Te Atatū Road.', 'teatatu-homes' ) ),
		'harbourview' => array( __( 'Harbourview', 'teatatu-homes' ), __( 'South of Taikata Road and Harbour View Road, down to the North-Western Motorway (SH16).', 'teatatu-homes' ) ),
	);
}

/**
 * Resolves an address to an Area slug using Teatatu Events, when present.
 *
 * @param array $parts Address segments (see teatatu_homes_address_fields()).
 * @return array {slug: string ('' if none), via: ''|'events', on_peninsula: bool|null}
 */
function teatatu_homes_resolve_area( $parts ) {
	$none = array( 'slug' => '', 'via' => '', 'on_peninsula' => null );
	if ( ! function_exists( 'teatatu_events_resolve_neighbourhood' ) ) {
		return $none;
	}
	$result = teatatu_events_resolve_neighbourhood( $parts );
	if ( empty( $result['slug'] ) ) {
		return array( 'slug' => '', 'via' => '', 'on_peninsula' => ! empty( $result['needs'] ) ? true : null );
	}
	// Events lets a network admin rename its slugs; map back to the fixed key.
	$slug = $result['slug'];
	if ( function_exists( 'teatatu_events_neighbourhood_key_for_slug' ) ) {
		$key  = teatatu_events_neighbourhood_key_for_slug( $slug );
		$slug = $key ? $key : $slug;
	}
	if ( ! array_key_exists( $slug, teatatu_homes_area_defs() ) ) {
		return $none;
	}
	return array( 'slug' => $slug, 'via' => 'events', 'on_peninsula' => true );
}

/**
 * Assigns a home's Area from its address unless an editor already chose
 * one, and keeps the "Needs area" flag in step.
 *
 * @param int $post_id Home ID.
 */
function teatatu_homes_assign_area( $post_id ) {
	$current = teatatu_homes_first_term( $post_id, 'teatatu_home_area' );
	if ( $current ) {
		delete_post_meta( $post_id, teatatu_homes_mk( 'needs_area' ) );
		return;
	}
	$resolved = teatatu_homes_resolve_area( teatatu_homes_get_address( $post_id ) );
	if ( $resolved['slug'] ) {
		$term = get_term_by( 'slug', $resolved['slug'], 'teatatu_home_area' );
		if ( $term ) {
			wp_set_object_terms( $post_id, array( (int) $term->term_id ), 'teatatu_home_area', false );
			update_post_meta( $post_id, teatatu_homes_mk( 'area_via' ), $resolved['via'] );
			delete_post_meta( $post_id, teatatu_homes_mk( 'needs_area' ) );
			return;
		}
	}
	update_post_meta( $post_id, teatatu_homes_mk( 'needs_area' ), 1 );
}
