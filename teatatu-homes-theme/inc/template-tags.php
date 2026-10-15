<?php
/**
 * Template tags: small rendering helpers shared by the templates. They take
 * the plugin's normalised item array (teatatu_homes_normalize_item()) and
 * turn it into markup — no data is read from post meta directly.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Inline stroke icon (decorative: aria-hidden).
 *
 * @param string $name Icon name.
 * @param int    $size Pixel size.
 * @return string SVG markup.
 */
function tth_icon( $name, $size = 20 ) {
	$paths = array(
		'logo'   => '<path d="M5 15 16 6l11 9"/><path d="M8 13v12h16V13"/><path d="M4 28c3-2 5-2 8 0s5 2 8 0 5-2 8 0"/>',
		'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
		'arrow'  => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'bed'    => '<path d="M3 18V7M21 18v-5a3 3 0 0 0-3-3H10v8"/><path d="M3 14h18"/><circle cx="6.5" cy="11" r="1.5"/>',
		'bath'   => '<path d="M4 12h16v3a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4z"/><path d="M6 12V6a2 2 0 0 1 4 0"/><path d="M7 19l-1 2M17 19l1 2"/>',
		'car'    => '<path d="M5 16V11l2-5h10l2 5v5"/><path d="M3 16h18v3H3z"/><circle cx="7.5" cy="13" r="1"/><circle cx="16.5" cy="13" r="1"/>',
		'area'   => '<rect x="4" y="4" width="16" height="16" rx="1"/><path d="M4 9h5M15 4v5M9 20v-5h11"/>',
		'share'  => '<path d="M12 15V3M7 8l5-5 5 5"/><path d="M5 13v7h14v-7"/>',
		'phone'  => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a1 1 0 0 1-1 1A16 16 0 0 1 4 5a1 1 0 0 1 1-1z"/>',
		'doc'    => '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4"/>',
		'check'  => '<path d="m5 12 5 5 9-10"/>',
		'coast'  => '<path d="M2 16c2.5-2 4.5-2 7 0s4.5 2 7 0 4.5-2 6 0"/><path d="M2 20c2.5-2 4.5-2 7 0s4.5 2 7 0 4.5-2 6 0"/><circle cx="16" cy="7" r="3"/>',
		'pin'    => '<path d="M12 21s-7-6.5-7-12a7 7 0 0 1 14 0c0 5.5-7 12-7 12z"/><circle cx="12" cy="9" r="2.5"/>',
		'filter' => '<path d="M4 6h16M7 12h10M10 18h4"/>',
		'close'  => '<path d="M6 6l12 12M18 6 6 18"/>',
		'photo'  => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-8 8"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	$box = 'logo' === $name ? '0 0 32 32' : '0 0 24 24';
	return sprintf(
		'<svg width="%1$d" height="%1$d" viewBox="%2$s" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%3$s</svg>',
		(int) $size,
		esc_attr( $box ),
		$paths[ $name ]
	);
}

/**
 * Base URL of the Homes search (the post type archive).
 *
 * @return string
 */
function tth_search_url() {
	$url = get_post_type_archive_link( 'teatatu_home' );
	return $url ? $url : home_url( '/homes/' );
}

/**
 * Price dropdown options for a listing type: value => label.
 * Sale "$800k" / "$1.2m"; rent "$600 / wk".
 *
 * @param string $listing 'sale' or 'rent'.
 * @return array
 */
function tth_price_options( $listing ) {
	$out = array();
	foreach ( teatatu_homes_price_steps( $listing ) as $step ) {
		if ( 'rent' === $listing ) {
			/* translators: %s: amount. */
			$out[ $step ] = sprintf( __( '%s / wk', 'teatatu-homes-theme' ), teatatu_homes_format_money( $step ) );
		} elseif ( $step >= 1000000 ) {
			$out[ $step ] = '$' . rtrim( rtrim( number_format( $step / 1000000, 2 ), '0' ), '.' ) . 'm';
		} else {
			$out[ $step ] = '$' . ( $step / 1000 ) . 'k';
		}
	}
	return $out;
}

/**
 * Heading for a results page, e.g. "Homes for rent in Matipo".
 *
 * @param array $filters Sanitised filters.
 * @return string
 */
function tth_results_heading( $filters ) {
	if ( 'rent' === $filters['listing'] ) {
		$what = __( 'Homes for rent', 'teatatu-homes-theme' );
	} elseif ( 'sale' === $filters['listing'] ) {
		$what = __( 'Homes for sale', 'teatatu-homes-theme' );
	} else {
		$what = __( 'Homes for sale and rent', 'teatatu-homes-theme' );
	}
	$where = __( 'on Te Atatū Peninsula', 'teatatu-homes-theme' );
	if ( 1 === count( $filters['area'] ) ) {
		$area = get_term_by( 'slug', $filters['area'][0], 'teatatu_home_area' );
		if ( $area ) {
			/* translators: %s: area name. */
			$where = sprintf( __( 'in %s', 'teatatu-homes-theme' ), $area->name );
		}
	} elseif ( is_tax( 'teatatu_home_area' ) ) {
		/* translators: %s: area name. */
		$where = sprintf( __( 'in %s', 'teatatu-homes-theme' ), single_term_title( '', false ) );
	}
	return $what . ' ' . $where;
}

/**
 * Label for an active filter chip.
 *
 * @param string $key   Filter key.
 * @param mixed  $value Value.
 * @return string
 */
function tth_filter_chip_label( $key, $value ) {
	$term_label = function ( $slugs, $taxonomy ) {
		$names = array();
		foreach ( (array) $slugs as $slug ) {
			$term    = get_term_by( 'slug', $slug, $taxonomy );
			$names[] = $term ? $term->name : $slug;
		}
		return implode( ', ', $names );
	};
	switch ( $key ) {
		case 'listing':
			$types = teatatu_homes_listing_types();
			return $types[ $value ] ?? $value;
		case 'q':
			/* translators: %s: search text. */
			return sprintf( __( '“%s”', 'teatatu-homes-theme' ), $value );
		case 'price_min':
			/* translators: %s: price. */
			return sprintf( __( 'From %s', 'teatatu-homes-theme' ), teatatu_homes_format_money( $value ) );
		case 'price_max':
			/* translators: %s: price. */
			return sprintf( __( 'Up to %s', 'teatatu-homes-theme' ), teatatu_homes_format_money( $value ) );
		case 'beds':
			/* translators: %d: count. */
			return sprintf( __( '%d+ bedrooms', 'teatatu-homes-theme' ), $value );
		case 'baths':
			/* translators: %d: count. */
			return sprintf( __( '%d+ bathrooms', 'teatatu-homes-theme' ), $value );
		case 'cars':
			/* translators: %d: count. */
			return sprintf( __( '%d+ car parks', 'teatatu-homes-theme' ), $value );
		case 'type':
			return $term_label( $value, 'teatatu_home_type' );
		case 'area':
			return $term_label( $value, 'teatatu_home_area' );
		case 'feature':
			return $term_label( $value, 'teatatu_home_feature' );
		case 'agency':
			return $term_label( $value, 'teatatu_home_agency' );
		case 'open':
			return 'weekend' === $value ? __( 'Open this weekend', 'teatatu-homes-theme' ) : __( 'Has an open home', 'teatatu-homes-theme' );
		case 'pets':
			return __( 'Pets considered', 'teatatu-homes-theme' );
		case 'status':
			$defs = teatatu_homes_status_defs();
			return 'all' === $value ? __( 'Any status', 'teatatu-homes-theme' ) : ( $defs[ $value ][0] ?? $value );
	}
	return (string) $value;
}

/**
 * Search URL with the given filters (array values become comma lists).
 *
 * @param array  $filters Filters to put in the URL.
 * @param string $base    Base URL (defaults to the current archive).
 * @return string
 */
function tth_filters_url( $filters, $base = '' ) {
	$args = array();
	foreach ( $filters as $key => $value ) {
		if ( is_array( $value ) ) {
			$value = implode( ',', $value );
		}
		if ( is_bool( $value ) ) {
			$value = $value ? '1' : '';
		}
		if ( '' !== (string) $value && '0' !== (string) $value ) {
			$args[ $key ] = $value;
		}
	}
	return add_query_arg( array_map( 'rawurlencode', $args ), $base ? $base : tth_current_base_url() );
}

/**
 * The current archive's URL without query string or page number.
 *
 * @return string
 */
function tth_current_base_url() {
	if ( is_tax() ) {
		$link = get_term_link( get_queried_object() );
		if ( ! is_wp_error( $link ) ) {
			return $link;
		}
	}
	return tth_search_url();
}

/**
 * Prints an <img> for a listing photo, or a neutral placeholder. Takes a
 * resolved photo from the plugin (uploaded or linked — the theme doesn't
 * need to care which) and uses its srcset when it has one.
 *
 * @param array|null $photo   Resolved photo (item['photos'][n]) or null.
 * @param string     $size    'thumb' (cards) or 'url' (gallery).
 * @param string     $loading 'lazy' or 'eager'.
 * @param string     $sizes   The sizes attribute for srcset.
 */
function tth_photo( $photo, $size = 'thumb', $loading = 'lazy', $sizes = '(max-width: 720px) 100vw, 400px' ) {
	if ( empty( $photo ) ) {
		echo '<span class="tth-photo-empty">' . tth_icon( 'photo', 28 ) . '<span class="screen-reader-text">' . esc_html__( 'No photo yet', 'teatatu-homes-theme' ) . '</span></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		return;
	}
	$attrs = sprintf(
		'src="%s" alt="%s" loading="%s" decoding="async"',
		esc_url( $photo[ $size ] ),
		esc_attr( $photo['alt'] ),
		esc_attr( $loading )
	);
	if ( $photo['srcset'] ) {
		$attrs .= sprintf( ' srcset="%s" sizes="%s"', esc_attr( $photo['srcset'] ), esc_attr( $sizes ) );
	}
	if ( $photo['width'] && $photo['height'] ) {
		$attrs .= sprintf( ' width="%d" height="%d"', (int) $photo['width'], (int) $photo['height'] );
	}
	if ( 'link' === $photo['source'] ) {
		$attrs .= ' referrerpolicy="no-referrer"';
	}
	echo '<img ' . $attrs . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
}

/**
 * Listing tags over a photo: For sale / For rent, plus a status when not
 * simply available.
 *
 * @param array $item Normalised item.
 */
function tth_listing_tags( $item ) {
	if ( $item['listing'] ) {
		printf( '<span class="tth-tag%s">%s</span>', 'rent' === $item['listing'] ? ' tth-tag--rent' : '', esc_html( $item['listing_label'] ) );
	}
	if ( 'available' !== $item['status'] ) {
		printf( '<span class="tth-tag tth-tag--status">%s</span>', esc_html( $item['status_label'] ) );
	}
}

/**
 * Bed / bath / car / type line.
 *
 * @param array $item Normalised item.
 */
function tth_specs( $item ) {
	$specs = array();
	if ( $item['bedrooms'] ) {
		/* translators: %d: count. */
		$specs[] = array( 'bed', sprintf( _n( '%d bed', '%d bed', $item['bedrooms'], 'teatatu-homes-theme' ), $item['bedrooms'] ) );
	}
	if ( $item['bathrooms'] ) {
		/* translators: %d: count. */
		$specs[] = array( 'bath', sprintf( _n( '%d bath', '%d bath', $item['bathrooms'], 'teatatu-homes-theme' ), $item['bathrooms'] ) );
	}
	if ( $item['car_parks'] ) {
		/* translators: %d: count. */
		$specs[] = array( 'car', sprintf( _n( '%d car', '%d car', $item['car_parks'], 'teatatu-homes-theme' ), $item['car_parks'] ) );
	}
	if ( $item['property_type'] ) {
		$specs[] = array( '', $item['property_type']['name'] );
	}
	if ( ! $specs ) {
		return;
	}
	echo '<ul class="tth-specs">';
	foreach ( $specs as $spec ) {
		echo '<li>' . ( $spec[0] ? tth_icon( $spec[0], 16 ) : '' ) . esc_html( $spec[1] ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
	}
	echo '</ul>';
}

/**
 * A listing card (grid) or row (results list).
 *
 * @param array  $item    Normalised item.
 * @param string $variant 'card' or 'row'.
 */
function tth_card( $item, $variant = 'card' ) {
	get_template_part( 'template-parts/home', $variant, array( 'item' => $item ) );
}

/**
 * Map points for a set of items: only homes whose address display is
 * "full" have a point (the plugin decides).
 *
 * @param array[] $items Normalised items.
 * @return array[]
 */
function tth_map_points( $items ) {
	$points = array();
	foreach ( $items as $item ) {
		if ( empty( $item['address']['point'] ) ) {
			continue;
		}
		$points[] = array(
			'lat'     => $item['address']['point']['lat'],
			'lng'     => $item['address']['point']['lng'],
			'label'   => wp_strip_all_tags( $item['price_label'] ),
			'title'   => $item['address']['line1'],
			'url'     => $item['permalink'],
			'listing' => $item['listing'],
		);
	}
	return $points;
}

/**
 * Prints a map container (Leaflet is loaded only when there are points).
 *
 * @param array[] $points Points from tth_map_points().
 * @param string  $label  Accessible label.
 * @param string  $class  Extra class.
 */
function tth_map( $points, $label, $class = '' ) {
	if ( ! $points ) {
		return;
	}
	tth_enqueue_map();
	printf(
		'<div class="tth-map %1$s" role="region" aria-label="%2$s" data-points="%3$s"></div>',
		esc_attr( $class ),
		esc_attr( $label ),
		esc_attr( wp_json_encode( $points ) )
	);
}

/**
 * Header menu fallback when no menu is assigned: Buy, Rent, Open homes and
 * the Areas, built from the plugin's archives.
 */
function tth_primary_menu_fallback() {
	$links = array();
	if ( tth_plugin_ready() ) {
		foreach ( teatatu_homes_listing_types() as $slug => $label ) {
			$term = get_term_by( 'slug', $slug, 'teatatu_home_listing' );
			if ( $term ) {
				$links[] = array( 'sale' === $slug ? __( 'Buy', 'teatatu-homes-theme' ) : __( 'Rent', 'teatatu-homes-theme' ), get_term_link( $term ) );
			}
		}
		$links[] = array( __( 'Open homes', 'teatatu-homes-theme' ), tth_filters_url( array( 'open' => 'any', 'sort' => 'open' ), tth_search_url() ) );
	}
	$cta = get_theme_mod( 'tth_list_url', '' );
	echo '<ul>';
	foreach ( $links as $link ) {
		printf( '<li><a href="%s">%s</a></li>', esc_url( $link[1] ), esc_html( $link[0] ) );
	}
	if ( $cta ) {
		printf( '<li class="tth-nav__cta"><a href="%s">%s</a></li>', esc_url( $cta ), esc_html__( 'List a home', 'teatatu-homes-theme' ) );
	}
	echo '</ul>';
}

/**
 * Prints a <select> of options.
 *
 * @param string $name     Field name.
 * @param string $id       Element ID.
 * @param array  $options  value => label (first is the "any" option).
 * @param mixed  $current  Current value.
 * @param string $extra    Extra attributes (pre-escaped).
 */
function tth_select( $name, $id, $options, $current, $extra = '' ) {
	printf( '<select class="tth-control" name="%s" id="%s" %s>', esc_attr( $name ), esc_attr( $id ), $extra ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller passes static attributes.
	foreach ( $options as $value => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( (string) $current, (string) $value, false ), esc_html( $label ) );
	}
	echo '</select>';
}

/**
 * Notice shown in place of listings when the plugin is missing.
 */
function tth_plugin_missing_message() {
	echo '<div class="tth-empty"><p>' . esc_html__( 'Listings are not available right now.', 'teatatu-homes-theme' ) . '</p></div>';
}
