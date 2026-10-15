<?php
/**
 * The Home data model: post meta registration, the single validated writer
 * every code path uses (the admin meta box and REST alike), derived values
 * (open-home index, next open home), display-ready labels (price, dates,
 * status), and the normalised item array that the theme and the REST API
 * both read.
 *
 * Prices are whole New Zealand dollars: the asking price for a sale, the
 * weekly rent for a rental. Dates are stored as UTC Unix timestamps and
 * shown with wp_date() in the site's time zone.
 *
 * Photos are either uploads (Media Library attachments kept in the home's
 * own folder) or links to remote images — see includes/photos.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Full meta key for a home field.
 *
 * @param string $field Field name.
 * @return string
 */
function teatatu_homes_mk( $field ) {
	return '_teatatu_homes_' . $field;
}

// ---------------------------------------------------------------------------
// Vocabularies
// ---------------------------------------------------------------------------

/**
 * Listing status values and labels, and which Listing Type each applies to.
 *
 * @return array status => [label, listing types]
 */
function teatatu_homes_status_defs() {
	return array(
		'available'   => array( __( 'Available', 'teatatu-homes' ), array( 'sale', 'rent' ) ),
		'under_offer' => array( __( 'Under offer', 'teatatu-homes' ), array( 'sale' ) ),
		'sold'        => array( __( 'Sold', 'teatatu-homes' ), array( 'sale' ) ),
		'leased'      => array( __( 'Leased', 'teatatu-homes' ), array( 'rent' ) ),
		'withdrawn'   => array( __( 'Withdrawn', 'teatatu-homes' ), array( 'sale', 'rent' ) ),
	);
}

/**
 * Statuses shown in public search by default.
 *
 * @return string[]
 */
function teatatu_homes_active_statuses() {
	return array( 'available', 'under_offer' );
}

/**
 * Sale methods (For sale listings only).
 *
 * @return string[]
 */
function teatatu_homes_sale_methods() {
	return array(
		'asking'         => __( 'Asking price', 'teatatu-homes' ),
		'enquiries_over' => __( 'Enquiries over', 'teatatu-homes' ),
		'negotiation'    => __( 'By negotiation', 'teatatu-homes' ),
		'auction'        => __( 'Auction', 'teatatu-homes' ),
		'tender'         => __( 'Tender', 'teatatu-homes' ),
		'deadline'       => __( 'Deadline sale', 'teatatu-homes' ),
	);
}

/**
 * Sale methods that need a date (auction time, tender/deadline close).
 *
 * @return string[]
 */
function teatatu_homes_dated_sale_methods() {
	return array( 'auction', 'tender', 'deadline' );
}

/**
 * Pet policies (rentals).
 *
 * @return string[]
 */
function teatatu_homes_pet_policies() {
	return array(
		'no'         => __( 'No pets', 'teatatu-homes' ),
		'negotiable' => __( 'Pets considered', 'teatatu-homes' ),
		'yes'        => __( 'Pets welcome', 'teatatu-homes' ),
	);
}

/**
 * Furnishing options (rentals).
 *
 * @return string[]
 */
function teatatu_homes_furnishing_options() {
	return array(
		'unfurnished' => __( 'Unfurnished', 'teatatu-homes' ),
		'partly'      => __( 'Partly furnished', 'teatatu-homes' ),
		'furnished'   => __( 'Furnished', 'teatatu-homes' ),
	);
}

/**
 * Title types (sales).
 *
 * @return string[]
 */
function teatatu_homes_title_types() {
	return array(
		''           => __( 'Not stated', 'teatatu-homes' ),
		'freehold'   => __( 'Freehold', 'teatatu-homes' ),
		'cross_lease' => __( 'Cross lease', 'teatatu-homes' ),
		'unit_title' => __( 'Unit title', 'teatatu-homes' ),
		'leasehold'  => __( 'Leasehold', 'teatatu-homes' ),
	);
}

// ---------------------------------------------------------------------------
// Field model
// ---------------------------------------------------------------------------

/**
 * Editable home fields and their defaults. Address segments live in
 * includes/address.php; Listing Type, Property Type, Area, Features and
 * Agency are taxonomies.
 *
 * @return array
 */
function teatatu_homes_field_defaults() {
	return array(
		'status'          => 'available',
		'sale_method'     => 'asking',
		'price'           => 0,
		'price_text'      => '',
		'method_date'     => 0,
		'available_from'  => 0,
		'bond'            => 0,
		'pets'            => 'no',
		'furnished'       => 'unfurnished',
		'healthy_homes'   => false,
		'bedrooms'        => 0,
		'bathrooms'       => 0,
		'car_parks'       => 0,
		'floor_area'      => 0,
		'land_area'       => 0,
		'title_type'      => '',
		'photos'          => array(),
		'floor_plan_url'  => '',
		'video_url'       => '',
		'tour_url'        => '',
		'documents'       => array(),
		'open_homes'      => array(),
		'agent_name'      => '',
		'agent_phone'     => '',
		'agent_email'     => '',
		'agent_photo_url' => '',
		'external_url'    => '',
		'external_ref'    => '',
	);
}

/**
 * Field groups by type, used by registration, validation and casting.
 *
 * @return array
 */
function teatatu_homes_field_types() {
	return array(
		'int'    => array( 'price', 'method_date', 'available_from', 'bond', 'bedrooms', 'bathrooms', 'car_parks', 'floor_area', 'land_area' ),
		'bool'   => array( 'healthy_homes' ),
		'url'    => array( 'floor_plan_url', 'video_url', 'tour_url', 'agent_photo_url', 'external_url' ),
		'text'   => array( 'price_text', 'agent_name', 'agent_phone', 'external_ref' ),
		'email'  => array( 'agent_email' ),
		'enum'   => array( 'status', 'sale_method', 'pets', 'furnished', 'title_type' ),
		'array'  => array( 'photos', 'documents', 'open_homes' ),
	);
}

/**
 * The type group a field belongs to.
 *
 * @param string $field Field.
 * @return string
 */
function teatatu_homes_field_type( $field ) {
	foreach ( teatatu_homes_field_types() as $type => $fields ) {
		if ( in_array( $field, $fields, true ) ) {
			return $type;
		}
	}
	return 'text';
}

add_action( 'init', 'teatatu_homes_register_post_meta' );

/**
 * Registers home meta with its type. Raw meta is deliberately NOT exposed
 * in REST: `meta` on a public post is readable by anyone, which would leak
 * the agent's email and the exact address of homes whose address is
 * hidden. Reads and writes go through the context-aware `home` REST field
 * (includes/rest-api.php) instead.
 */
function teatatu_homes_register_post_meta() {
	$auth = function ( $allowed, $meta_key, $post_id ) {
		return current_user_can( 'edit_post', $post_id );
	};
	foreach ( teatatu_homes_field_defaults() as $field => $default ) {
		$type = teatatu_homes_field_type( $field );
		$args = array(
			'single'        => true,
			'auth_callback' => $auth,
		);
		if ( 'int' === $type ) {
			$args['type'] = 'integer';
		} elseif ( 'bool' === $type ) {
			$args['type'] = 'boolean';
		} elseif ( 'array' === $type ) {
			$args['type'] = 'array';
		} else {
			$args['type'] = 'string';
		}
		$args['show_in_rest'] = false;
		register_post_meta( 'teatatu_home', teatatu_homes_mk( $field ), $args );
	}
}

// ---------------------------------------------------------------------------
// Reading
// ---------------------------------------------------------------------------

/**
 * Every editable field as stored, cast to its type.
 *
 * @param int $post_id Home ID.
 * @return array
 */
function teatatu_homes_get_fields( $post_id ) {
	$out = array();
	foreach ( teatatu_homes_field_defaults() as $field => $default ) {
		$raw  = get_post_meta( $post_id, teatatu_homes_mk( $field ), true );
		$type = teatatu_homes_field_type( $field );
		if ( '' === $raw || null === $raw ) {
			$out[ $field ] = $default;
		} elseif ( 'int' === $type ) {
			$out[ $field ] = (int) $raw;
		} elseif ( 'bool' === $type ) {
			$out[ $field ] = (bool) $raw;
		} elseif ( 'array' === $type ) {
			$out[ $field ] = is_array( $raw ) ? $raw : array();
		} else {
			$out[ $field ] = (string) $raw;
		}
	}
	return $out;
}

/**
 * A home's Listing Type slug: 'sale', 'rent' or '' if not set yet.
 *
 * @param int $post_id Home ID.
 * @return string
 */
function teatatu_homes_get_listing_type( $post_id ) {
	$term = teatatu_homes_first_term( $post_id, 'teatatu_home_listing' );
	return $term && array_key_exists( $term->slug, teatatu_homes_listing_types() ) ? $term->slug : '';
}

// ---------------------------------------------------------------------------
// Dates
// ---------------------------------------------------------------------------

/**
 * Parses a local date/time string ("2026-11-08 14:00", "2026-11-08") or a
 * timestamp into a UTC timestamp.
 *
 * @param mixed $value Value.
 * @return int|null Null if it could not be understood; 0 for blank.
 */
function teatatu_homes_parse_datetime( $value ) {
	if ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) && strlen( $value ) >= 9 ) ) {
		return (int) $value;
	}
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return 0;
	}
	try {
		$dt = new DateTimeImmutable( $value, wp_timezone() );
	} catch ( Exception $e ) {
		return null;
	}
	return $dt->getTimestamp();
}

/**
 * "Sat 8 Nov, 2:00pm" — the display format for open homes and auctions.
 *
 * @param int  $ts        UTC timestamp.
 * @param bool $with_time Include the time.
 * @return string
 */
function teatatu_homes_format_date( $ts, $with_time = true ) {
	if ( ! $ts ) {
		return '';
	}
	$date = wp_date( 'D j M', $ts );
	if ( wp_date( 'Y', $ts ) !== wp_date( 'Y' ) ) {
		$date .= ' ' . wp_date( 'Y', $ts );
	}
	return $with_time ? $date . ', ' . teatatu_homes_format_time( $ts ) : $date;
}

/**
 * "2:00pm" / "11:30am".
 *
 * @param int $ts UTC timestamp.
 * @return string
 */
function teatatu_homes_format_time( $ts ) {
	return wp_date( 'g:ia', $ts );
}

/**
 * Formats a whole-dollar amount: 1195000 → "$1,195,000".
 *
 * @param int $amount Dollars.
 * @return string
 */
function teatatu_homes_format_money( $amount ) {
	return '$' . number_format_i18n( (int) $amount );
}

// ---------------------------------------------------------------------------
// Validation and the single writer
// ---------------------------------------------------------------------------

/**
 * Validates fields against current values. `listing`, `property_type`,
 * `address` and `features`/`agency` keys are accepted here too, so one call
 * describes a whole listing.
 *
 * @param array  $fields       Fields to apply (any subset).
 * @param array  $current      Current values.
 * @param string $listing_type The listing type in force after this write.
 * @param int    $post_id      Home ID (0 when creating) — uploads must belong to it.
 * @return array|WP_Error Normalised field values.
 */
function teatatu_homes_validate_fields( $fields, $current, $listing_type, $post_id = 0 ) {
	$new = array_merge( teatatu_homes_field_defaults(), $current );
	$err = function ( $message, $field ) {
		return new WP_Error( 'rest_invalid_param', $message, array( 'status' => 400, 'field' => $field ) );
	};

	foreach ( teatatu_homes_field_types()['int'] as $field ) {
		if ( ! array_key_exists( $field, $fields ) ) {
			continue;
		}
		if ( in_array( $field, array( 'method_date', 'available_from' ), true ) ) {
			$ts = teatatu_homes_parse_datetime( $fields[ $field ] );
			if ( null === $ts ) {
				return $err( __( 'A date could not be understood.', 'teatatu-homes' ), $field );
			}
			$new[ $field ] = $ts;
			continue;
		}
		$raw = is_string( $fields[ $field ] ) ? preg_replace( '/[\s,$]/', '', $fields[ $field ] ) : $fields[ $field ];
		if ( '' === $raw || null === $raw ) {
			$new[ $field ] = 0;
			continue;
		}
		if ( ! is_numeric( $raw ) || $raw < 0 ) {
			return $err( __( 'Numbers must be zero or more.', 'teatatu-homes' ), $field );
		}
		$new[ $field ] = (int) round( (float) $raw );
	}
	if ( $new['bedrooms'] > 30 || $new['bathrooms'] > 30 || $new['car_parks'] > 50 ) {
		return $err( __( 'Room counts look too large.', 'teatatu-homes' ), 'bedrooms' );
	}

	foreach ( teatatu_homes_field_types()['bool'] as $field ) {
		if ( array_key_exists( $field, $fields ) ) {
			$new[ $field ] = (bool) rest_sanitize_boolean( $fields[ $field ] );
		}
	}
	foreach ( teatatu_homes_field_types()['url'] as $field ) {
		if ( array_key_exists( $field, $fields ) ) {
			$raw = trim( (string) $fields[ $field ] );
			$url = esc_url_raw( $raw, array( 'http', 'https' ) );
			if ( '' !== $raw && '' === $url ) {
				return $err( __( 'A URL is not valid.', 'teatatu-homes' ), $field );
			}
			$new[ $field ] = $url;
		}
	}
	foreach ( teatatu_homes_field_types()['text'] as $field ) {
		if ( array_key_exists( $field, $fields ) ) {
			$new[ $field ] = sanitize_text_field( (string) $fields[ $field ] );
		}
	}
	if ( array_key_exists( 'agent_email', $fields ) ) {
		$raw = trim( (string) $fields['agent_email'] );
		$new['agent_email'] = sanitize_email( $raw );
		if ( '' !== $raw && ! is_email( $new['agent_email'] ) ) {
			return $err( __( 'The agent email is not valid.', 'teatatu-homes' ), 'agent_email' );
		}
	}

	$enums = array(
		'status'      => array_keys( teatatu_homes_status_defs() ),
		'sale_method' => array_keys( teatatu_homes_sale_methods() ),
		'pets'        => array_keys( teatatu_homes_pet_policies() ),
		'furnished'   => array_keys( teatatu_homes_furnishing_options() ),
		'title_type'  => array_keys( teatatu_homes_title_types() ),
	);
	foreach ( $enums as $field => $allowed ) {
		if ( array_key_exists( $field, $fields ) ) {
			$value = sanitize_key( (string) $fields[ $field ] );
			if ( ! in_array( $value, $allowed, true ) ) {
				return $err( __( 'Unknown value.', 'teatatu-homes' ), $field );
			}
			$new[ $field ] = $value;
		}
	}

	// Status must suit the listing type (no "Sold" rentals).
	if ( $listing_type ) {
		$defs = teatatu_homes_status_defs();
		if ( ! in_array( $listing_type, $defs[ $new['status'] ][1], true ) ) {
			return $err( __( 'That status does not apply to this listing type.', 'teatatu-homes' ), 'status' );
		}
	}

	if ( array_key_exists( 'photos', $fields ) ) {
		$photos = teatatu_homes_validate_photos( $fields['photos'], $post_id );
		if ( is_wp_error( $photos ) ) {
			return $photos;
		}
		$new['photos'] = $photos;
	}

	if ( array_key_exists( 'documents', $fields ) ) {
		$docs = array();
		foreach ( (array) $fields['documents'] as $doc ) {
			$doc   = (array) $doc;
			$label = sanitize_text_field( (string) ( $doc['label'] ?? '' ) );
			$raw   = trim( (string) ( $doc['url'] ?? '' ) );
			if ( '' === $raw && '' === $label ) {
				continue;
			}
			$url = esc_url_raw( $raw, array( 'http', 'https' ) );
			if ( '' === $url || '' === $label ) {
				return $err( __( 'Each document needs a label and a valid URL.', 'teatatu-homes' ), 'documents' );
			}
			$docs[] = array( 'label' => $label, 'url' => $url );
		}
		$new['documents'] = $docs;
	}

	if ( array_key_exists( 'open_homes', $fields ) ) {
		$slots = array();
		foreach ( (array) $fields['open_homes'] as $slot ) {
			$slot  = (array) $slot;
			$start = teatatu_homes_parse_datetime( $slot['start'] ?? '' );
			$end   = teatatu_homes_parse_datetime( $slot['end'] ?? '' );
			if ( 0 === $start && 0 === $end ) {
				continue;
			}
			if ( null === $start || null === $end || ! $start ) {
				return $err( __( 'An open home time could not be understood.', 'teatatu-homes' ), 'open_homes' );
			}
			if ( ! $end ) {
				$end = $start + 30 * MINUTE_IN_SECONDS;
			}
			if ( $end <= $start ) {
				return $err( __( 'An open home ends before it starts.', 'teatatu-homes' ), 'open_homes' );
			}
			// Drop slots that finished more than a day ago.
			if ( $end < time() - DAY_IN_SECONDS ) {
				continue;
			}
			$slots[ $start ] = array( 'start' => $start, 'end' => $end );
		}
		ksort( $slots );
		$new['open_homes'] = array_values( $slots );
	}

	return $new;
}

/**
 * Validates and saves a whole listing. Every write path uses this.
 *
 * Accepted keys: every field in teatatu_homes_field_defaults(), plus
 * `listing` ('sale'|'rent'), `property_type` (term slug), `address`
 * (segments, see includes/address.php), `features` (names or slugs) and
 * `agency` (term ID or slug).
 *
 * @param int   $post_id Home ID.
 * @param array $fields  Fields to save.
 * @return true|WP_Error
 */
function teatatu_homes_save_fields( $post_id, $fields ) {
	if ( 'teatatu_home' !== get_post_type( $post_id ) ) {
		return new WP_Error( 'teatatu_homes_wrong_type', __( 'Not a Home.', 'teatatu-homes' ), array( 'status' => 400 ) );
	}

	$listing = teatatu_homes_get_listing_type( $post_id );
	if ( array_key_exists( 'listing', $fields ) ) {
		$listing = sanitize_key( (string) $fields['listing'] );
		if ( ! array_key_exists( $listing, teatatu_homes_listing_types() ) ) {
			return new WP_Error( 'rest_invalid_param', __( 'Listing type must be "sale" or "rent".', 'teatatu-homes' ), array( 'status' => 400, 'field' => 'listing' ) );
		}
	}

	$new = teatatu_homes_validate_fields( $fields, teatatu_homes_get_fields( $post_id ), $listing, $post_id );
	if ( is_wp_error( $new ) ) {
		return $new;
	}

	$address = null;
	if ( array_key_exists( 'address', $fields ) ) {
		$address = teatatu_homes_validate_address( $fields['address'], teatatu_homes_get_address( $post_id ) );
		if ( is_wp_error( $address ) ) {
			return $address;
		}
	}

	$term_sets = array();
	if ( array_key_exists( 'listing', $fields ) ) {
		$term = get_term_by( 'slug', $listing, 'teatatu_home_listing' );
		if ( ! $term ) {
			return new WP_Error( 'teatatu_homes_missing_terms', __( 'Listing Type terms are missing — reactivate the plugin.', 'teatatu-homes' ), array( 'status' => 500 ) );
		}
		$term_sets['teatatu_home_listing'] = array( (int) $term->term_id );
	}
	if ( array_key_exists( 'property_type', $fields ) ) {
		$slug = sanitize_title( (string) $fields['property_type'] );
		$term = '' === $slug ? null : get_term_by( 'slug', $slug, 'teatatu_home_type' );
		if ( '' !== $slug && ! $term ) {
			return new WP_Error( 'rest_invalid_param', __( 'Unknown property type.', 'teatatu-homes' ), array( 'status' => 400, 'field' => 'property_type' ) );
		}
		$term_sets['teatatu_home_type'] = $term ? array( (int) $term->term_id ) : array();
	}
	if ( array_key_exists( 'agency', $fields ) ) {
		$ref  = $fields['agency'];
		$term = is_numeric( $ref ) ? get_term( (int) $ref, 'teatatu_home_agency' ) : get_term_by( 'slug', sanitize_title( (string) $ref ), 'teatatu_home_agency' );
		if ( $ref && ( ! $term || is_wp_error( $term ) ) ) {
			return new WP_Error( 'rest_invalid_param', __( 'Unknown agency.', 'teatatu-homes' ), array( 'status' => 400, 'field' => 'agency' ) );
		}
		$term_sets['teatatu_home_agency'] = $ref ? array( (int) $term->term_id ) : array();
	}
	if ( array_key_exists( 'features', $fields ) ) {
		$term_sets['teatatu_home_feature'] = array_values( array_filter( array_map( 'sanitize_text_field', (array) $fields['features'] ) ) );
	}

	foreach ( teatatu_homes_field_defaults() as $field => $default ) {
		$value = $new[ $field ];
		if ( is_bool( $value ) ) {
			$value = $value ? 1 : 0;
		}
		update_post_meta( $post_id, teatatu_homes_mk( $field ), $value );
	}
	foreach ( $term_sets as $taxonomy => $terms ) {
		wp_set_object_terms( $post_id, $terms, $taxonomy, false );
	}
	if ( null !== $address ) {
		foreach ( $address as $field => $value ) {
			update_post_meta( $post_id, teatatu_homes_address_mk( $field ), $value );
		}
		teatatu_homes_assign_area( $post_id );
	}

	teatatu_homes_refresh_derived( $post_id );
	return true;
}

// ---------------------------------------------------------------------------
// Derived values
// ---------------------------------------------------------------------------

/**
 * Rebuilds the derived meta used by queries:
 *  - `_teatatu_homes_open_start` — one row per upcoming open home start, so
 *    "open this weekend" is a plain meta BETWEEN.
 *  - `_teatatu_homes_next_open` — the next open home start (0 if none), for
 *    sorting.
 *
 * @param int $post_id Home ID.
 */
function teatatu_homes_refresh_derived( $post_id ) {
	$fields = teatatu_homes_get_fields( $post_id );
	$now    = time();
	$next   = 0;
	delete_post_meta( $post_id, teatatu_homes_mk( 'open_start' ) );
	foreach ( $fields['open_homes'] as $slot ) {
		if ( (int) $slot['end'] < $now ) {
			continue;
		}
		add_post_meta( $post_id, teatatu_homes_mk( 'open_start' ), (int) $slot['start'] );
		if ( ! $next || $slot['start'] < $next ) {
			$next = (int) $slot['start'];
		}
	}
	update_post_meta( $post_id, teatatu_homes_mk( 'next_open' ), $next );
}

add_action( 'teatatu_homes_daily', 'teatatu_homes_refresh_stale_open_homes' );

/**
 * Daily: refreshes homes whose next open home has passed, so sorting and
 * "has an open home" stay right without an edit.
 */
function teatatu_homes_refresh_stale_open_homes() {
	$ids = get_posts(
		array(
			'post_type'        => 'teatatu_home',
			'post_status'      => 'any',
			'numberposts'      => 500,
			'fields'           => 'ids',
			'suppress_filters' => true,
			'meta_query'       => array(
				array( 'key' => teatatu_homes_mk( 'next_open' ), 'value' => array( 1, time() ), 'compare' => 'BETWEEN', 'type' => 'NUMERIC' ),
			),
		)
	);
	foreach ( $ids as $id ) {
		teatatu_homes_refresh_derived( $id );
	}
}

add_action( 'init', 'teatatu_homes_ensure_cron' );

/**
 * Keeps the daily tick scheduled (WP-Cron entries can go missing).
 */
function teatatu_homes_ensure_cron() {
	if ( ! wp_next_scheduled( 'teatatu_homes_daily' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'teatatu_homes_daily' );
	}
}

/**
 * Fields still needed before a listing is ready to publish.
 *
 * @param int $post_id Home ID.
 * @return string[] Labels of missing fields.
 */
function teatatu_homes_missing_fields( $post_id ) {
	$missing = array();
	$fields  = teatatu_homes_get_fields( $post_id );
	$listing = teatatu_homes_get_listing_type( $post_id );
	$address = teatatu_homes_get_address( $post_id );
	if ( '' === trim( get_the_title( $post_id ) ) ) {
		$missing[] = __( 'Title', 'teatatu-homes' );
	}
	if ( ! $listing ) {
		$missing[] = __( 'Listing Type', 'teatatu-homes' );
	}
	if ( '' === $address['street'] ) {
		$missing[] = __( 'Street', 'teatatu-homes' );
	}
	if ( 'rent' === $listing && ! $fields['price'] ) {
		$missing[] = __( 'Weekly rent', 'teatatu-homes' );
	}
	if ( 'sale' === $listing && in_array( $fields['sale_method'], teatatu_homes_dated_sale_methods(), true ) && ! $fields['method_date'] ) {
		$missing[] = __( 'Auction / closing date', 'teatatu-homes' );
	}
	if ( 'sale' === $listing && in_array( $fields['sale_method'], array( 'asking', 'enquiries_over' ), true ) && ! $fields['price'] && '' === $fields['price_text'] ) {
		$missing[] = __( 'Price', 'teatatu-homes' );
	}
	if ( get_post_meta( $post_id, teatatu_homes_mk( 'needs_area' ), true ) ) {
		$missing[] = __( 'Area', 'teatatu-homes' );
	}
	return $missing;
}

// ---------------------------------------------------------------------------
// Labels
// ---------------------------------------------------------------------------

/**
 * The headline price string for a listing.
 *
 * Sale: "$1,195,000", "Enquiries over $1,100,000", "Price by negotiation",
 * "Auction · Sat 8 Nov, 2:00pm", "Tender closes Thu 20 Nov, 4:00pm",
 * "Deadline sale · …", "Sold". Rent: "$720 per week", "Leased".
 * A `price_text` override wins while the listing is active.
 *
 * @param array  $fields  Fields (teatatu_homes_get_fields()).
 * @param string $listing Listing type.
 * @return string
 */
function teatatu_homes_price_label( $fields, $listing ) {
	if ( 'sold' === $fields['status'] ) {
		return __( 'Sold', 'teatatu-homes' );
	}
	if ( 'leased' === $fields['status'] ) {
		return __( 'Leased', 'teatatu-homes' );
	}
	if ( '' !== $fields['price_text'] ) {
		return $fields['price_text'];
	}
	if ( 'rent' === $listing ) {
		/* translators: %s: weekly rent. */
		return $fields['price'] ? sprintf( __( '%s per week', 'teatatu-homes' ), teatatu_homes_format_money( $fields['price'] ) ) : __( 'Rent on application', 'teatatu-homes' );
	}
	$date = teatatu_homes_format_date( $fields['method_date'] );
	switch ( $fields['sale_method'] ) {
		case 'auction':
			/* translators: %s: auction date. */
			return $date ? sprintf( __( 'Auction · %s', 'teatatu-homes' ), $date ) : __( 'Auction', 'teatatu-homes' );
		case 'tender':
			/* translators: %s: tender close date. */
			return $date ? sprintf( __( 'Tender closes %s', 'teatatu-homes' ), $date ) : __( 'Tender', 'teatatu-homes' );
		case 'deadline':
			/* translators: %s: deadline date. */
			return $date ? sprintf( __( 'Deadline sale · %s', 'teatatu-homes' ), $date ) : __( 'Deadline sale', 'teatatu-homes' );
		case 'negotiation':
			return __( 'Price by negotiation', 'teatatu-homes' );
		case 'enquiries_over':
			/* translators: %s: price. */
			return $fields['price'] ? sprintf( __( 'Enquiries over %s', 'teatatu-homes' ), teatatu_homes_format_money( $fields['price'] ) ) : __( 'Contact the agent', 'teatatu-homes' );
		default:
			return $fields['price'] ? teatatu_homes_format_money( $fields['price'] ) : __( 'Contact the agent', 'teatatu-homes' );
	}
}

/**
 * Upcoming open homes, display-ready.
 *
 * @param array $slots Stored slots.
 * @return array[] {start, end, date, time, label}
 */
function teatatu_homes_upcoming_open_homes( $slots ) {
	$out = array();
	foreach ( (array) $slots as $slot ) {
		if ( (int) $slot['end'] < time() ) {
			continue;
		}
		$time  = teatatu_homes_format_time( $slot['start'] ) . ' – ' . teatatu_homes_format_time( $slot['end'] );
		$out[] = array(
			'start' => (int) $slot['start'],
			'end'   => (int) $slot['end'],
			'date'  => teatatu_homes_format_date( $slot['start'], false ),
			'time'  => $time,
			'label' => teatatu_homes_format_date( $slot['start'], false ) . ', ' . $time,
		);
	}
	return $out;
}

/**
 * A short "what's notable" line for cards: the next open home, else
 * availability (rentals), else "New this week".
 *
 * @param array  $fields  Fields.
 * @param string $listing Listing type.
 * @param array  $opens   Upcoming open homes.
 * @param int    $post_id Home ID.
 * @return string
 */
function teatatu_homes_card_note( $fields, $listing, $opens, $post_id ) {
	if ( $opens ) {
		/* translators: %s: open home date and time. */
		return sprintf( __( 'Open home %s', 'teatatu-homes' ), teatatu_homes_format_date( $opens[0]['start'] ) );
	}
	if ( 'rent' === $listing ) {
		if ( ! $fields['available_from'] || $fields['available_from'] <= time() ) {
			return __( 'Available now', 'teatatu-homes' );
		}
		/* translators: %s: date. */
		return sprintf( __( 'Available %s', 'teatatu-homes' ), teatatu_homes_format_date( $fields['available_from'], false ) );
	}
	if ( strtotime( get_post_field( 'post_date_gmt', $post_id ) . ' UTC' ) > time() - WEEK_IN_SECONDS ) {
		return __( 'New this week', 'teatatu-homes' );
	}
	return '';
}

// ---------------------------------------------------------------------------
// The normalised item
// ---------------------------------------------------------------------------

/**
 * Builds the plain-array representation of a home used by the theme and
 * the REST API. Display layers read only this.
 *
 * @param WP_Post|int $post Home.
 * @return array
 */
function teatatu_homes_normalize_item( $post ) {
	$post    = get_post( $post );
	$id      = (int) $post->ID;
	$fields  = teatatu_homes_get_fields( $id );
	$listing = teatatu_homes_get_listing_type( $id );
	$types   = teatatu_homes_listing_types();
	$status  = teatatu_homes_status_defs();
	$type    = teatatu_homes_first_term( $id, 'teatatu_home_type' );
	$area    = teatatu_homes_first_term( $id, 'teatatu_home_area' );
	$opens   = teatatu_homes_upcoming_open_homes( $fields['open_homes'] );
	$address = teatatu_homes_public_address( $id );
	$photos  = teatatu_homes_resolve_photos( $fields['photos'], $address['line1'] );

	$documents = $fields['documents'];
	if ( $fields['floor_plan_url'] ) {
		$documents[] = array( 'label' => __( 'Floor plan', 'teatatu-homes' ), 'url' => $fields['floor_plan_url'] );
	}

	$rental = null;
	if ( 'rent' === $listing ) {
		$pets      = teatatu_homes_pet_policies();
		$furnished = teatatu_homes_furnishing_options();
		$rental    = array(
			'available_from'       => $fields['available_from'],
			'available_label'      => ( ! $fields['available_from'] || $fields['available_from'] <= time() ) ? __( 'Available now', 'teatatu-homes' ) : teatatu_homes_format_date( $fields['available_from'], false ),
			'bond'                 => $fields['bond'],
			'bond_label'           => $fields['bond'] ? teatatu_homes_format_money( $fields['bond'] ) : '',
			'pets'                 => $fields['pets'],
			'pets_label'           => $pets[ $fields['pets'] ],
			'furnished'            => $fields['furnished'],
			'furnished_label'      => $furnished[ $fields['furnished'] ],
			'healthy_homes'        => $fields['healthy_homes'],
		);
	}

	$sale = null;
	if ( 'sale' === $listing ) {
		$methods = teatatu_homes_sale_methods();
		$titles  = teatatu_homes_title_types();
		$sale    = array(
			'method'       => $fields['sale_method'],
			'method_label' => $methods[ $fields['sale_method'] ],
			'method_date'  => $fields['method_date'],
			'title_type'   => $fields['title_type'],
			'title_label'  => $fields['title_type'] ? $titles[ $fields['title_type'] ] : '',
		);
	}

	return array(
		'id'            => $id,
		'title'         => get_the_title( $post ),
		'excerpt'       => get_the_excerpt( $post ),
		'permalink'     => get_permalink( $post ),
		'listing'       => $listing,
		'listing_label' => $listing ? $types[ $listing ] : '',
		'status'        => $fields['status'],
		'status_label'  => $status[ $fields['status'] ][0],
		'is_active'     => in_array( $fields['status'], teatatu_homes_active_statuses(), true ),
		'price'         => $fields['price'],
		'price_label'   => teatatu_homes_price_label( $fields, $listing ),
		'address'       => $address,
		'area'          => $area ? array( 'id' => (int) $area->term_id, 'slug' => $area->slug, 'name' => $area->name, 'link' => get_term_link( $area ) ) : null,
		'property_type' => $type ? array( 'id' => (int) $type->term_id, 'slug' => $type->slug, 'name' => $type->name ) : null,
		'bedrooms'      => $fields['bedrooms'],
		'bathrooms'     => $fields['bathrooms'],
		'car_parks'     => $fields['car_parks'],
		'floor_area'    => $fields['floor_area'],
		'land_area'     => $fields['land_area'],
		'features'      => teatatu_homes_term_list( $id, 'teatatu_home_feature' ),
		'image_url'     => $photos ? $photos[0]['thumb'] : '',
		'photos'        => $photos,
		'video_url'     => $fields['video_url'],
		'tour_url'      => $fields['tour_url'],
		'documents'     => $documents,
		'open_homes'    => $opens,
		'note'          => teatatu_homes_card_note( $fields, $listing, $opens, $id ),
		'sale'          => $sale,
		'rental'        => $rental,
		'agent'         => array(
			'name'      => $fields['agent_name'],
			'phone'     => $fields['agent_phone'],
			'has_email' => '' !== $fields['agent_email'],
			'photo_url' => $fields['agent_photo_url'],
		),
		'agency'        => teatatu_homes_agency_data( teatatu_homes_first_term( $id, 'teatatu_home_agency' ) ),
		'external_url'  => $fields['external_url'],
		'external_ref'  => $fields['external_ref'],
		'modified_gmt'  => get_post_field( 'post_modified_gmt', $post ),
	);
}
