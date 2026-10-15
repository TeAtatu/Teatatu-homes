<?php
/**
 * Search and filtering. One definition of the public filters
 * (teatatu_homes_filter_defs()), one sanitiser and one WP_Query builder,
 * used by:
 *  - the main query on the Homes archive and the Homes taxonomy archives
 *    (URL parameters such as /homes/?listing=rent&beds=3), so a theme only
 *    needs to render a GET form with these names;
 *  - GET /wp-json/teatatu-homes/v1/search (includes/rest-api.php).
 *
 * Public search shows only active listings (Available, Under offer) unless
 * `status` asks for another.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public filter parameters: name => [type, default, label].
 *
 * @return array
 */
function teatatu_homes_filter_defs() {
	return array(
		'listing'   => array( 'enum', '', __( 'Buy or rent', 'teatatu-homes' ) ),
		'q'         => array( 'text', '', __( 'Street or keyword', 'teatatu-homes' ) ),
		'price_min' => array( 'int', 0, __( 'Min price', 'teatatu-homes' ) ),
		'price_max' => array( 'int', 0, __( 'Max price', 'teatatu-homes' ) ),
		'beds'      => array( 'int', 0, __( 'Bedrooms', 'teatatu-homes' ) ),
		'baths'     => array( 'int', 0, __( 'Bathrooms', 'teatatu-homes' ) ),
		'cars'      => array( 'int', 0, __( 'Car parks', 'teatatu-homes' ) ),
		'type'      => array( 'slugs', array(), __( 'Property type', 'teatatu-homes' ) ),
		'area'      => array( 'slugs', array(), __( 'Area', 'teatatu-homes' ) ),
		'feature'   => array( 'slugs', array(), __( 'Features', 'teatatu-homes' ) ),
		'agency'    => array( 'slugs', array(), __( 'Agency', 'teatatu-homes' ) ),
		'open'      => array( 'enum', '', __( 'Open homes', 'teatatu-homes' ) ),
		'pets'      => array( 'bool', false, __( 'Pets considered', 'teatatu-homes' ) ),
		'status'    => array( 'enum', '', __( 'Status', 'teatatu-homes' ) ),
		'sort'      => array( 'enum', 'newest', __( 'Sort', 'teatatu-homes' ) ),
	);
}

/**
 * Allowed values for the enum filters.
 *
 * @return array
 */
function teatatu_homes_filter_enums() {
	return array(
		'listing' => array_merge( array( '' ), array_keys( teatatu_homes_listing_types() ) ),
		'open'    => array( '', 'weekend', 'any' ),
		'status'  => array_merge( array( '', 'all' ), array_keys( teatatu_homes_status_defs() ) ),
		'sort'    => array_keys( teatatu_homes_sort_options() ),
	);
}

/**
 * Sort options.
 *
 * @return string[]
 */
function teatatu_homes_sort_options() {
	return array(
		'newest'     => __( 'Newest', 'teatatu-homes' ),
		'price_asc'  => __( 'Price: low to high', 'teatatu-homes' ),
		'price_desc' => __( 'Price: high to low', 'teatatu-homes' ),
		'open'       => __( 'Open homes soonest', 'teatatu-homes' ),
	);
}

/**
 * Suggested price steps for filter dropdowns, per listing type (sale in
 * dollars, rent in dollars per week).
 *
 * @param string $listing 'sale' or 'rent'.
 * @return int[]
 */
function teatatu_homes_price_steps( $listing ) {
	$steps = 'rent' === $listing
		? array( 400, 500, 600, 700, 800, 900, 1000, 1200, 1500 )
		: array( 600000, 700000, 800000, 900000, 1000000, 1200000, 1400000, 1600000, 2000000, 2500000 );
	return apply_filters( 'teatatu_homes_price_steps', $steps, $listing );
}

/**
 * Sanitises raw filter input (e.g. $_GET or REST params) to known values.
 * Unknown keys are dropped; bad values fall back to defaults.
 *
 * @param array $raw Raw input (unslashed).
 * @return array
 */
function teatatu_homes_sanitize_filters( $raw ) {
	$raw   = (array) $raw;
	$enums = teatatu_homes_filter_enums();
	$out   = array();
	foreach ( teatatu_homes_filter_defs() as $key => $def ) {
		list( $type, $default ) = $def;
		$value = array_key_exists( $key, $raw ) ? $raw[ $key ] : $default;
		switch ( $type ) {
			case 'int':
				$value = is_scalar( $value ) ? absint( preg_replace( '/[^\d]/', '', (string) $value ) ) : 0;
				break;
			case 'bool':
				$value = is_scalar( $value ) && (bool) rest_sanitize_boolean( $value );
				break;
			case 'slugs':
				$list  = is_array( $value ) ? $value : explode( ',', (string) $value );
				$value = array_values( array_filter( array_map( 'sanitize_title', array_map( 'strval', array_filter( $list, 'is_scalar' ) ) ) ) );
				break;
			case 'enum':
				$value = is_scalar( $value ) ? sanitize_key( (string) $value ) : $default;
				$value = in_array( $value, $enums[ $key ], true ) ? $value : $default;
				break;
			default:
				$value = is_scalar( $value ) ? mb_substr( sanitize_text_field( (string) $value ), 0, 80 ) : '';
		}
		$out[ $key ] = $value;
	}
	if ( $out['price_min'] && $out['price_max'] && $out['price_min'] > $out['price_max'] ) {
		list( $out['price_min'], $out['price_max'] ) = array( $out['price_max'], $out['price_min'] );
	}
	return $out;
}

/**
 * Filters that differ from their defaults — what a theme shows as
 * removable "chips", and what to carry into pagination links.
 *
 * @param array $filters Sanitised filters.
 * @return array
 */
function teatatu_homes_active_filters( $filters ) {
	$out = array();
	foreach ( teatatu_homes_filter_defs() as $key => $def ) {
		if ( isset( $filters[ $key ] ) && $filters[ $key ] !== $def[1] && 'sort' !== $key ) {
			$out[ $key ] = $filters[ $key ];
		}
	}
	return $out;
}

/**
 * This weekend (or next, from Monday–Friday) as a UTC range in the site's
 * time zone: Saturday 00:00 to Sunday 23:59:59.
 *
 * @return int[] [from, to]
 */
function teatatu_homes_weekend_range() {
	$now = new DateTimeImmutable( 'now', wp_timezone() );
	$dow = (int) $now->format( 'N' ); // 1 = Mon … 7 = Sun.
	if ( 7 === $dow ) {
		$sat = $now->modify( '-1 day' );
	} elseif ( 6 === $dow ) {
		$sat = $now;
	} else {
		$sat = $now->modify( 'next saturday' );
	}
	$from = $sat->setTime( 0, 0, 0 );
	$to   = $from->modify( '+1 day' )->setTime( 23, 59, 59 );
	return array( max( time(), $from->getTimestamp() ), $to->getTimestamp() );
}

/**
 * WP_Query arguments for a set of sanitised filters.
 *
 * @param array $filters  Sanitised filters (teatatu_homes_sanitize_filters()).
 * @param int   $page     Page number.
 * @param int   $per_page Results per page.
 * @return array
 */
function teatatu_homes_query_args( $filters, $page = 1, $per_page = 12 ) {
	$args = array(
		'post_type'      => 'teatatu_home',
		'post_status'    => 'publish',
		'posts_per_page' => max( 1, min( 48, (int) $per_page ) ),
		'paged'          => max( 1, (int) $page ),
	);
	foreach ( teatatu_homes_filter_query_parts( $filters ) as $key => $value ) {
		$args[ $key ] = $value;
	}
	return $args;
}

/**
 * The filter-dependent query parts (tax_query, meta_query, ordering and the
 * keyword marker), shared by teatatu_homes_query_args() and the main-query
 * hook.
 *
 * @param array $filters Sanitised filters.
 * @return array
 */
function teatatu_homes_filter_query_parts( $filters ) {
	$tax  = array( 'relation' => 'AND' );
	$meta = array( 'relation' => 'AND' );

	if ( $filters['listing'] ) {
		$tax[] = array( 'taxonomy' => 'teatatu_home_listing', 'field' => 'slug', 'terms' => array( $filters['listing'] ) );
	}
	$tax_filters = array(
		'type'   => 'teatatu_home_type',
		'area'   => 'teatatu_home_area',
		'agency' => 'teatatu_home_agency',
	);
	foreach ( $tax_filters as $key => $taxonomy ) {
		if ( $filters[ $key ] ) {
			$tax[] = array( 'taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => $filters[ $key ] );
		}
	}
	if ( $filters['feature'] ) {
		$tax[] = array( 'taxonomy' => 'teatatu_home_feature', 'field' => 'slug', 'terms' => $filters['feature'], 'operator' => 'AND' );
	}

	if ( 'all' !== $filters['status'] ) {
		$statuses = $filters['status'] ? array( $filters['status'] ) : teatatu_homes_active_statuses();
		$status   = array( 'key' => teatatu_homes_mk( 'status' ), 'value' => $statuses, 'compare' => 'IN' );
		if ( in_array( 'available', $statuses, true ) ) {
			// A home saved before its status was set counts as available.
			$status = array( 'relation' => 'OR', $status, array( 'key' => teatatu_homes_mk( 'status' ), 'compare' => 'NOT EXISTS' ) );
		}
		$meta['status'] = $status;
	}
	if ( $filters['price_min'] ) {
		$meta[] = array( 'key' => teatatu_homes_mk( 'price' ), 'value' => $filters['price_min'], 'compare' => '>=', 'type' => 'NUMERIC' );
	}
	if ( $filters['price_max'] ) {
		$meta[] = array( 'key' => teatatu_homes_mk( 'price' ), 'value' => $filters['price_max'], 'compare' => '<=', 'type' => 'NUMERIC' );
	}
	$mins = array( 'beds' => 'bedrooms', 'baths' => 'bathrooms', 'cars' => 'car_parks' );
	foreach ( $mins as $key => $field ) {
		if ( $filters[ $key ] ) {
			$meta[] = array( 'key' => teatatu_homes_mk( $field ), 'value' => $filters[ $key ], 'compare' => '>=', 'type' => 'NUMERIC' );
		}
	}
	if ( 'weekend' === $filters['open'] ) {
		$meta[] = array( 'key' => teatatu_homes_mk( 'open_start' ), 'value' => teatatu_homes_weekend_range(), 'compare' => 'BETWEEN', 'type' => 'NUMERIC' );
	} elseif ( 'any' === $filters['open'] ) {
		$meta[] = array( 'key' => teatatu_homes_mk( 'open_start' ), 'value' => time(), 'compare' => '>=', 'type' => 'NUMERIC' );
	}
	if ( $filters['pets'] ) {
		$meta[] = array( 'key' => teatatu_homes_mk( 'pets' ), 'value' => array( 'yes', 'negotiable' ), 'compare' => 'IN' );
	}

	$parts = array(
		'tax_query'  => $tax,
		'meta_query' => $meta,
	);

	switch ( $filters['sort'] ) {
		case 'price_asc':
		case 'price_desc':
			$parts['meta_query']['price_sort'] = array(
				'relation' => 'OR',
				array( 'key' => teatatu_homes_mk( 'price' ), 'compare' => 'EXISTS' ),
				array( 'key' => teatatu_homes_mk( 'price' ), 'compare' => 'NOT EXISTS' ),
			);
			$parts['orderby'] = array( 'meta_value_num' => 'price_asc' === $filters['sort'] ? 'ASC' : 'DESC', 'date' => 'DESC' );
			$parts['meta_key'] = teatatu_homes_mk( 'price' );
			break;
		case 'open':
			$parts['meta_query']['open_sort'] = array( 'key' => teatatu_homes_mk( 'next_open' ), 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' );
			$parts['orderby'] = array( 'open_sort' => 'ASC' );
			break;
		default:
			$parts['orderby'] = array( 'date' => 'DESC' );
	}

	if ( '' !== $filters['q'] ) {
		$parts['teatatu_homes_q'] = $filters['q'];
	}
	return $parts;
}

add_filter( 'posts_clauses', 'teatatu_homes_keyword_clauses', 10, 2 );

/**
 * Keyword search across title, excerpt, description and the street name —
 * but never the street of a home whose address is hidden.
 *
 * @param array    $clauses Query clauses.
 * @param WP_Query $query   Query.
 * @return array
 */
function teatatu_homes_keyword_clauses( $clauses, $query ) {
	$q = $query->get( 'teatatu_homes_q' );
	if ( ! is_string( $q ) || '' === $q ) {
		return $clauses;
	}
	global $wpdb;
	$like  = '%' . $wpdb->esc_like( $q ) . '%';
	$folded = '%' . $wpdb->esc_like( teatatu_homes_fold_text( $q ) ) . '%';
	$clauses['join']  .= $wpdb->prepare( " LEFT JOIN {$wpdb->postmeta} AS tth_st ON ({$wpdb->posts}.ID = tth_st.post_id AND tth_st.meta_key = %s)", teatatu_homes_address_mk( 'street' ) );
	$clauses['join']  .= $wpdb->prepare( " LEFT JOIN {$wpdb->postmeta} AS tth_dp ON ({$wpdb->posts}.ID = tth_dp.post_id AND tth_dp.meta_key = %s)", teatatu_homes_address_mk( 'display' ) );
	$clauses['where'] .= $wpdb->prepare(
		" AND ( {$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_excerpt LIKE %s OR {$wpdb->posts}.post_content LIKE %s OR ( ( tth_dp.meta_value IS NULL OR tth_dp.meta_value <> 'hidden' ) AND ( tth_st.meta_value LIKE %s OR tth_st.meta_value LIKE %s ) ) )",
		$like,
		$like,
		$like,
		$like,
		$folded
	);
	$clauses['groupby'] = "{$wpdb->posts}.ID";
	return $clauses;
}

/**
 * Whether a query is a public Homes listing query the URL filters apply to.
 *
 * @param WP_Query $query Query.
 * @return bool
 */
function teatatu_homes_is_listing_query( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return false;
	}
	if ( $query->is_post_type_archive( 'teatatu_home' ) ) {
		return true;
	}
	return $query->is_tax( array_keys( teatatu_homes_taxonomy_defs() ) );
}

add_action( 'pre_get_posts', 'teatatu_homes_filter_main_query' );

/**
 * Applies the URL filters to the Homes archive and taxonomy archives. On
 * /listing/rent/ the listing filter is implied by the page itself.
 *
 * @param WP_Query $query Main query.
 */
function teatatu_homes_filter_main_query( $query ) {
	if ( ! teatatu_homes_is_listing_query( $query ) ) {
		return;
	}
	$filters = teatatu_homes_current_filters();
	$parts   = teatatu_homes_filter_query_parts( $filters );

	// Keep the archive's own taxonomy condition alongside ours.
	$existing = $query->get( 'tax_query' );
	if ( is_array( $existing ) && $existing ) {
		$parts['tax_query'][] = $existing;
	}
	foreach ( $parts as $key => $value ) {
		$query->set( $key, $value );
	}
	$query->set( 'post_type', 'teatatu_home' );
	$query->set( 'posts_per_page', (int) apply_filters( 'teatatu_homes_per_page', 12 ) );
}

/**
 * The sanitised filters for the current request. On a Listing Type archive
 * the listing filter is taken from the archive.
 *
 * @return array
 */
function teatatu_homes_current_filters() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public, read-only search.
	$filters = teatatu_homes_sanitize_filters( wp_unslash( $_GET ) );
	if ( is_tax( 'teatatu_home_listing' ) ) {
		$term = get_queried_object();
		if ( $term && array_key_exists( $term->slug, teatatu_homes_listing_types() ) ) {
			$filters['listing'] = $term->slug;
		}
	}
	$cache = $filters;
	return $filters;
}

/**
 * Runs a search and returns normalised items.
 *
 * @param array $filters  Sanitised filters.
 * @param int   $page     Page.
 * @param int   $per_page Per page.
 * @return array {items, total, pages, page}
 */
function teatatu_homes_search( $filters, $page = 1, $per_page = 12 ) {
	$query = new WP_Query( teatatu_homes_query_args( $filters, $page, $per_page ) );
	return array(
		'items' => array_map( 'teatatu_homes_normalize_item', $query->posts ),
		'total' => (int) $query->found_posts,
		'pages' => (int) $query->max_num_pages,
		'page'  => max( 1, (int) $page ),
	);
}

/**
 * Latest active listings of one type — what a home page shows.
 *
 * @param string $listing 'sale' or 'rent'.
 * @param int    $count   How many.
 * @return array[] Normalised items.
 */
function teatatu_homes_latest( $listing, $count = 3 ) {
	$filters            = teatatu_homes_sanitize_filters( array() );
	$filters['listing'] = $listing;
	$result             = teatatu_homes_search( $filters, 1, $count );
	return $result['items'];
}
