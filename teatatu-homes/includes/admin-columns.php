<?php
/**
 * Custom columns for the Homes (edit-teatatu_home) admin list table:
 * Photo, Address, Price, Rooms, Status and Next open home, plus a
 * Listing Type / Status / Needs-attention filter row.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'manage_teatatu_home_posts_columns', 'teatatu_homes_admin_columns' );

/**
 * Inserts custom columns around the title column.
 *
 * @param array $columns Existing columns.
 * @return array
 */
function teatatu_homes_admin_columns( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		if ( 'title' === $key ) {
			$new['teatatu_homes_image'] = __( 'Photo', 'teatatu-homes' );
		}
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['teatatu_homes_address'] = __( 'Address', 'teatatu-homes' );
			$new['teatatu_homes_price']   = __( 'Price', 'teatatu-homes' );
			$new['teatatu_homes_rooms']   = __( 'Bed / bath / car', 'teatatu-homes' );
			$new['teatatu_homes_status']  = __( 'Status', 'teatatu-homes' );
			$new['teatatu_homes_open']    = __( 'Next open home', 'teatatu-homes' );
		}
	}
	return $new;
}

add_action( 'manage_teatatu_home_posts_custom_column', 'teatatu_homes_render_admin_column', 10, 2 );

/**
 * Renders each custom column's cell content.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 */
function teatatu_homes_render_admin_column( $column, $post_id ) {
	switch ( $column ) {
		case 'teatatu_homes_image':
			$photo = teatatu_homes_main_photo_url( $post_id );
			if ( $photo ) {
				printf( '<img src="%s" alt="" loading="lazy" referrerpolicy="no-referrer" />', esc_url( $photo ) );
			} else {
				echo '&#8212;';
			}
			break;

		case 'teatatu_homes_address':
			$address = teatatu_homes_get_address( $post_id );
			echo esc_html( '' !== $address['street'] ? teatatu_homes_format_address( $address, 'street' ) : '—' );
			if ( 'full' !== $address['display'] ) {
				echo '<br /><span class="description">' . esc_html( 'hidden' === $address['display'] ? __( 'Hidden on site', 'teatatu-homes' ) : __( 'Street only on site', 'teatatu-homes' ) ) . '</span>';
			}
			$missing = teatatu_homes_missing_fields( $post_id );
			if ( $missing ) {
				/* translators: %s: missing fields. */
				echo '<br /><span class="tth-flag">' . esc_html( sprintf( __( 'Needs: %s', 'teatatu-homes' ), implode( ', ', $missing ) ) ) . '</span>';
			}
			break;

		case 'teatatu_homes_price':
			echo esc_html( teatatu_homes_price_label( teatatu_homes_get_fields( $post_id ), teatatu_homes_get_listing_type( $post_id ) ) );
			break;

		case 'teatatu_homes_rooms':
			$f = teatatu_homes_get_fields( $post_id );
			echo esc_html( sprintf( '%d / %d / %d', $f['bedrooms'], $f['bathrooms'], $f['car_parks'] ) );
			break;

		case 'teatatu_homes_status':
			$defs   = teatatu_homes_status_defs();
			$status = teatatu_homes_get_fields( $post_id )['status'];
			echo esc_html( $defs[ $status ][0] );
			break;

		case 'teatatu_homes_open':
			$next = (int) get_post_meta( $post_id, teatatu_homes_mk( 'next_open' ), true );
			echo esc_html( $next && $next > time() ? teatatu_homes_format_date( $next ) : '—' );
			break;
	}
}

add_filter( 'manage_edit-teatatu_home_sortable_columns', 'teatatu_homes_sortable_columns' );

/**
 * Sortable custom columns.
 *
 * @param array $columns Existing sortable columns.
 * @return array
 */
function teatatu_homes_sortable_columns( $columns ) {
	$columns['teatatu_homes_price'] = 'teatatu_homes_price';
	$columns['teatatu_homes_open']  = 'teatatu_homes_open';
	return $columns;
}

add_action( 'restrict_manage_posts', 'teatatu_homes_admin_filters' );

/**
 * Listing Type, Status and "Needs attention" filters above the list table.
 *
 * @param string $post_type Post type.
 */
function teatatu_homes_admin_filters( $post_type ) {
	if ( 'teatatu_home' !== $post_type ) {
		return;
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters.
	$listing = isset( $_GET['tth_listing'] ) ? sanitize_key( wp_unslash( $_GET['tth_listing'] ) ) : '';
	$status  = isset( $_GET['tth_status'] ) ? sanitize_key( wp_unslash( $_GET['tth_status'] ) ) : '';
	$needs   = ! empty( $_GET['tth_needs'] );
	// phpcs:enable
	echo '<label class="screen-reader-text" for="tth_listing_filter">' . esc_html__( 'Listing type', 'teatatu-homes' ) . '</label>';
	echo '<select id="tth_listing_filter" name="tth_listing"><option value="">' . esc_html__( 'Sale and rent', 'teatatu-homes' ) . '</option>';
	foreach ( teatatu_homes_listing_types() as $slug => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $slug ), selected( $listing, $slug, false ), esc_html( $label ) );
	}
	echo '</select>';
	echo '<label class="screen-reader-text" for="tth_status_filter">' . esc_html__( 'Status', 'teatatu-homes' ) . '</label>';
	echo '<select id="tth_status_filter" name="tth_status"><option value="">' . esc_html__( 'Any status', 'teatatu-homes' ) . '</option>';
	foreach ( teatatu_homes_status_defs() as $slug => $def ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $slug ), selected( $status, $slug, false ), esc_html( $def[0] ) );
	}
	echo '</select>';
	printf( '<label style="margin:0 8px;"><input type="checkbox" name="tth_needs" value="1" %s /> %s</label>', checked( $needs, true, false ), esc_html__( 'Needs Area', 'teatatu-homes' ) );
}

add_action( 'pre_get_posts', 'teatatu_homes_admin_list_query' );

/**
 * Applies the list-table filters and column sorting.
 *
 * @param WP_Query $query Current query.
 */
function teatatu_homes_admin_list_query( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() || 'teatatu_home' !== $query->get( 'post_type' ) ) {
		return;
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters.
	$listing = isset( $_GET['tth_listing'] ) ? sanitize_key( wp_unslash( $_GET['tth_listing'] ) ) : '';
	$status  = isset( $_GET['tth_status'] ) ? sanitize_key( wp_unslash( $_GET['tth_status'] ) ) : '';
	$needs   = ! empty( $_GET['tth_needs'] );
	// phpcs:enable
	if ( array_key_exists( $listing, teatatu_homes_listing_types() ) ) {
		$query->set( 'tax_query', array( array( 'taxonomy' => 'teatatu_home_listing', 'field' => 'slug', 'terms' => $listing ) ) );
	}
	$meta = array();
	if ( array_key_exists( $status, teatatu_homes_status_defs() ) ) {
		$meta[] = array( 'key' => teatatu_homes_mk( 'status' ), 'value' => $status );
	}
	if ( $needs ) {
		$meta[] = array( 'key' => teatatu_homes_mk( 'needs_area' ), 'compare' => 'EXISTS' );
	}
	if ( $meta ) {
		$query->set( 'meta_query', $meta );
	}

	$orderby = $query->get( 'orderby' );
	if ( 'teatatu_homes_price' === $orderby ) {
		$query->set( 'meta_key', teatatu_homes_mk( 'price' ) );
		$query->set( 'orderby', 'meta_value_num' );
	} elseif ( 'teatatu_homes_open' === $orderby ) {
		$query->set( 'meta_key', teatatu_homes_mk( 'next_open' ) );
		$query->set( 'orderby', 'meta_value_num' );
	}
}
