<?php
/**
 * Network-wide homes on the Master Site (see includes/multisite.php).
 *
 *  - teatatu_homes_get_network_items() gathers homes from every site in the
 *    network (each site's own search, so filters mean the same thing
 *    everywhere), re-sorts the combined list and caches it. The shortcodes
 *    and the REST endpoint both use it, so they always agree.
 *  - [teatatu_homes_network_grid], [teatatu_homes_network_list] and
 *    [teatatu_homes_network_latest] show them on the Master Site — usually
 *    the network's main site, which runs its own theme rather than the
 *    Homes theme, so these carry a small stylesheet that picks up the
 *    Te Atatū Local tokens when that theme provides them.
 *  - GET /wp-json/teatatu-homes/v1/network-feed returns the same items.
 *
 * Both only produce output on the Master Site.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode / REST attributes and defaults.
 *
 * @return array
 */
function teatatu_homes_network_defaults() {
	return array(
		'listing'       => '',
		'count'         => 6,
		'columns'       => 3,
		'area'          => '',
		'type'          => '',
		'feature'       => '',
		'beds'          => '',
		'price_max'     => '',
		'open'          => '',
		'sort'          => 'newest',
		'exclude_sites' => '',
		'show_photo'    => 'true',
		'show_specs'    => 'true',
		'show_site'     => 'false',
		'link_target'   => '_self',
	);
}

/**
 * Parses attributes: listing filters go through the plugin's own filter
 * sanitiser (teatatu_homes_sanitize_filters()); display options are
 * normalised here.
 *
 * @param array $raw Raw attributes.
 * @return array {filters, count, columns, exclude_sites, show_photo, show_specs, show_site, link_target}
 */
function teatatu_homes_network_atts( $raw ) {
	$atts    = shortcode_atts( teatatu_homes_network_defaults(), (array) $raw );
	$filters = teatatu_homes_sanitize_filters(
		array(
			'listing'   => $atts['listing'],
			'area'      => $atts['area'],
			'type'      => $atts['type'],
			'feature'   => $atts['feature'],
			'beds'      => $atts['beds'],
			'price_max' => $atts['price_max'],
			'open'      => $atts['open'],
			'sort'      => $atts['sort'],
		)
	);
	$bool    = function ( $v ) {
		return (bool) rest_sanitize_boolean( $v );
	};
	return array(
		'filters'       => $filters,
		'count'         => max( 1, min( 48, (int) $atts['count'] ) ),
		'columns'       => max( 1, min( 4, (int) $atts['columns'] ) ),
		'exclude_sites' => array_values( array_filter( array_map( 'absint', explode( ',', (string) $atts['exclude_sites'] ) ) ) ),
		'show_photo'    => $bool( $atts['show_photo'] ),
		'show_specs'    => $bool( $atts['show_specs'] ),
		'show_site'     => $bool( $atts['show_site'] ),
		'link_target'   => '_blank' === $atts['link_target'] ? '_blank' : '_self',
	);
}

/**
 * Homes from every site in the network, newest (or per `sort`) first.
 * Cached for an hour; any change to a home anywhere invalidates the cache.
 *
 * @param array $filters       Sanitised filters (teatatu_homes_sanitize_filters()).
 * @param int   $count         How many.
 * @param int[] $exclude_sites Site IDs to leave out.
 * @return array[] Normalised items plus site_id, site_name, site_url.
 */
function teatatu_homes_get_network_items( $filters, $count, $exclude_sites = array() ) {
	if ( ! is_multisite() ) {
		return array();
	}
	sort( $exclude_sites );
	$cache_key = 'teatatu_homes_net_' . md5( wp_json_encode( array( teatatu_homes_network_cache_version(), $filters, (int) $count, $exclude_sites ) ) );
	$cached    = get_site_transient( $cache_key );
	if ( false !== $cached ) {
		return $cached;
	}

	$all = array();
	foreach ( get_sites( array( 'fields' => 'ids', 'archived' => 0, 'deleted' => 0, 'spam' => 0 ) ) as $site_id ) {
		$site_id = (int) $site_id;
		if ( in_array( $site_id, $exclude_sites, true ) ) {
			continue;
		}
		switch_to_blog( $site_id );
		$found     = teatatu_homes_search( $filters, 1, $count );
		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url( '/' );
		restore_current_blog();
		foreach ( $found['items'] as $item ) {
			$item['site_id']   = $site_id;
			$item['site_name'] = $site_name;
			$item['site_url']  = $site_url;
			$all[]             = $item;
		}
	}

	// Re-sort the combined list the way each site's search sorted its own.
	$sort = $filters['sort'];
	usort(
		$all,
		function ( $a, $b ) use ( $sort ) {
			if ( 'price_asc' === $sort || 'price_desc' === $sort ) {
				$cmp = $a['price'] <=> $b['price'];
				return 'price_asc' === $sort ? $cmp : -$cmp;
			}
			if ( 'open' === $sort ) {
				$na = $a['open_homes'] ? $a['open_homes'][0]['start'] : PHP_INT_MAX;
				$nb = $b['open_homes'] ? $b['open_homes'][0]['start'] : PHP_INT_MAX;
				return $na <=> $nb;
			}
			return strcmp( $b['date_gmt'], $a['date_gmt'] );
		}
	);
	$all = array_slice( $all, 0, $count );

	set_site_transient( $cache_key, $all, HOUR_IN_SECONDS );
	return $all;
}

// ---------------------------------------------------------------------------
// Shortcodes (Master Site only)
// ---------------------------------------------------------------------------

add_action( 'init', 'teatatu_homes_register_network_shortcodes' );

/**
 * Registers the network shortcodes and their stylesheet.
 */
function teatatu_homes_register_network_shortcodes() {
	wp_register_style( 'teatatu-homes-network', TEATATU_HOMES_URL . 'assets/css/teatatu-homes-network.css', array(), TEATATU_HOMES_VERSION );
	foreach ( array( 'grid', 'list', 'latest' ) as $variant ) {
		add_shortcode(
			'teatatu_homes_network_' . $variant,
			function ( $atts ) use ( $variant ) {
				return teatatu_homes_render_network_shortcode( (array) $atts, $variant );
			}
		);
	}
}

/**
 * Shared shortcode implementation: Master Site gate, attributes, items, markup.
 *
 * @param array  $raw     Raw attributes.
 * @param string $variant 'grid', 'list' or 'latest'.
 * @return string
 */
function teatatu_homes_render_network_shortcode( $raw, $variant ) {
	if ( ! is_multisite() ) {
		return '';
	}
	if ( ! teatatu_homes_is_master_site() ) {
		if ( current_user_can( 'manage_options' ) ) {
			$message = teatatu_homes_get_master_site_id()
				? __( "This Homes shortcode only displays on the network's Homes Master Site.", 'teatatu-homes' )
				: __( 'No Homes Master Site has been set yet — choose one in Network Admin > Settings > Teatatu Homes.', 'teatatu-homes' );
			return '<p class="thn-notice">' . esc_html( $message ) . '</p>';
		}
		return '';
	}

	$atts = teatatu_homes_network_atts( $raw );
	if ( 'latest' === $variant ) {
		$atts['count'] = 1;
	}
	wp_enqueue_style( 'teatatu-homes-network' );

	$items = teatatu_homes_get_network_items( $atts['filters'], $atts['count'], $atts['exclude_sites'] );
	if ( ! $items ) {
		return '<p class="thn-empty">' . esc_html__( 'No homes to show right now.', 'teatatu-homes' ) . '</p>';
	}

	$class = 'thn thn--' . $variant . ( 'grid' === $variant ? ' thn--cols-' . $atts['columns'] : '' );
	$out   = '<div class="' . esc_attr( $class ) . '">';
	foreach ( $items as $item ) {
		$out .= teatatu_homes_render_network_card( $item, $atts );
	}
	return $out . '</div>';
}

/**
 * Markup for one home.
 *
 * @param array $item Network item.
 * @param array $atts Parsed attributes.
 * @return string
 */
function teatatu_homes_render_network_card( $item, $atts ) {
	$target = '_blank' === $atts['link_target'] ? ' target="_blank" rel="noopener"' : '';
	$html   = '<a class="thn-card" href="' . esc_url( $item['permalink'] ) . '"' . $target . '>';

	if ( $atts['show_photo'] ) {
		$html .= '<span class="thn-media">';
		if ( $item['photos'] ) {
			$photo = $item['photos'][0];
			$html .= sprintf(
				'<img src="%s" alt="%s" loading="lazy" decoding="async"%s%s />',
				esc_url( $photo['thumb'] ),
				esc_attr( $photo['alt'] ),
				$photo['srcset'] ? ' srcset="' . esc_attr( $photo['srcset'] ) . '" sizes="(max-width: 720px) 100vw, 400px"' : '',
				'link' === $photo['source'] ? ' referrerpolicy="no-referrer"' : ''
			);
		}
		if ( $item['listing'] ) {
			$html .= '<span class="thn-tag' . ( 'rent' === $item['listing'] ? ' thn-tag--rent' : '' ) . '">' . esc_html( $item['listing_label'] ) . '</span>';
		}
		if ( 'available' !== $item['status'] ) {
			$html .= '<span class="thn-tag thn-tag--status">' . esc_html( $item['status_label'] ) . '</span>';
		}
		$html .= '</span>';
	}

	$html .= '<span class="thn-body">';
	$html .= '<span class="thn-price">' . esc_html( $item['price_label'] ) . '</span>';
	$html .= '<span class="thn-address">' . esc_html( $item['address']['line1'] ) . '</span>';
	if ( $atts['show_specs'] ) {
		$specs = array();
		if ( $item['bedrooms'] ) {
			/* translators: %d: bedrooms. */
			$specs[] = sprintf( _n( '%d bed', '%d bed', $item['bedrooms'], 'teatatu-homes' ), $item['bedrooms'] );
		}
		if ( $item['bathrooms'] ) {
			/* translators: %d: bathrooms. */
			$specs[] = sprintf( _n( '%d bath', '%d bath', $item['bathrooms'], 'teatatu-homes' ), $item['bathrooms'] );
		}
		if ( $item['car_parks'] ) {
			/* translators: %d: car parks. */
			$specs[] = sprintf( _n( '%d car', '%d car', $item['car_parks'], 'teatatu-homes' ), $item['car_parks'] );
		}
		if ( $item['property_type'] ) {
			$specs[] = $item['property_type']['name'];
		}
		if ( $specs ) {
			$html .= '<span class="thn-specs">' . esc_html( implode( ' · ', $specs ) ) . '</span>';
		}
	}
	if ( $item['note'] ) {
		$html .= '<span class="thn-note">' . esc_html( $item['note'] ) . '</span>';
	}
	if ( $atts['show_site'] ) {
		$html .= '<span class="thn-site">' . esc_html( $item['site_name'] ) . '</span>';
	}
	return $html . '</span></a>';
}

// ---------------------------------------------------------------------------
// REST (Master Site only)
// ---------------------------------------------------------------------------

add_action( 'rest_api_init', 'teatatu_homes_register_network_route' );

/**
 * Registers GET /wp-json/teatatu-homes/v1/network-feed.
 */
function teatatu_homes_register_network_route() {
	$args = array();
	foreach ( array_keys( teatatu_homes_network_defaults() ) as $key ) {
		if ( in_array( $key, array( 'columns', 'show_photo', 'show_specs', 'show_site', 'link_target' ), true ) ) {
			continue; // Display-only.
		}
		$args[ $key ] = array( 'type' => in_array( $key, array( 'count', 'beds', 'price_max' ), true ) ? 'integer' : 'string' );
	}
	register_rest_route(
		'teatatu-homes/v1',
		'/network-feed',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'teatatu_homes_rest_network_feed',
			'permission_callback' => '__return_true',
			'args'                => $args,
		)
	);
}

/**
 * The network items as JSON — the same data the shortcodes show.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function teatatu_homes_rest_network_feed( WP_REST_Request $request ) {
	if ( ! is_multisite() ) {
		return new WP_Error( 'teatatu_homes_not_multisite', __( 'This endpoint is only available on a multisite network.', 'teatatu-homes' ), array( 'status' => 404 ) );
	}
	if ( ! teatatu_homes_is_master_site() ) {
		return new WP_Error( 'teatatu_homes_not_master_site', __( "This endpoint only responds on the network's Homes Master Site.", 'teatatu-homes' ), array( 'status' => 404 ) );
	}
	$atts = teatatu_homes_network_atts( $request->get_params() );
	return rest_ensure_response( teatatu_homes_get_network_items( $atts['filters'], $atts['count'], $atts['exclude_sites'] ) );
}
