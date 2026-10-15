<?php
/**
 * Home addresses. The plugin owns every part of a listing's address: the
 * structured segments stored in post meta, normalising and validating them
 * (including the rule that every home must be on Te Atatū Peninsula), the
 * Area resolution input, the "how much of the address to show" privacy
 * setting, and every formatted string a display layer may print. The theme
 * never joins address fields itself — it prints what
 * teatatu_homes_public_address() returns.
 *
 * Segments mirror the Teatatu Events venue address (unit, number, street,
 * suburb, city, postcode, lat, lng) so the Events area resolver can read
 * them directly.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Address segments and their defaults. Suburb, city and postcode are fixed
 * for this site but still stored, so every address is complete on its own.
 *
 * @return array
 */
function teatatu_homes_address_fields() {
	return array(
		'unit'     => '',
		'number'   => '',
		'street'   => '',
		'suburb'   => 'Te Atatū Peninsula',
		'city'     => 'Auckland',
		'postcode' => '0610',
		'lat'      => '',
		'lng'      => '',
		'display'  => 'full',
	);
}

/**
 * How much of the address the public sees.
 *
 * @return string[]
 */
function teatatu_homes_address_display_modes() {
	return array(
		'full'   => __( 'Show the full address and map pin', 'teatatu-homes' ),
		'street' => __( 'Show the street only (no number, no map pin)', 'teatatu-homes' ),
		'hidden' => __( 'Hide the address ("Address on request")', 'teatatu-homes' ),
	);
}

/**
 * Meta key for an address segment.
 *
 * @param string $field Segment.
 * @return string
 */
function teatatu_homes_address_mk( $field ) {
	return '_teatatu_homes_addr_' . $field;
}

/**
 * Generous bounding box around Te Atatū Peninsula, used only to reject
 * coordinates that are clearly elsewhere: [min_lat, max_lat, min_lng, max_lng].
 *
 * @return float[]
 */
function teatatu_homes_peninsula_bounds() {
	return apply_filters( 'teatatu_homes_peninsula_bounds', array( -36.875, -36.805, 174.615, 174.690 ) );
}

add_action( 'init', 'teatatu_homes_register_address_meta' );

/**
 * Registers the address segments as post meta. Not exposed in REST — the
 * `home` field (includes/rest-api.php) shows the public address to
 * everyone and the full segments only to editors.
 */
function teatatu_homes_register_address_meta() {
	foreach ( array_keys( teatatu_homes_address_fields() ) as $field ) {
		register_post_meta(
			'teatatu_home',
			teatatu_homes_address_mk( $field ),
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => false,
				'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
			)
		);
	}
}

/**
 * A home's stored address segments.
 *
 * @param int $post_id Home ID.
 * @return array
 */
function teatatu_homes_get_address( $post_id ) {
	$out = array();
	foreach ( teatatu_homes_address_fields() as $field => $default ) {
		$value         = get_post_meta( $post_id, teatatu_homes_address_mk( $field ), true );
		$out[ $field ] = '' === $value ? $default : (string) $value;
	}
	return $out;
}

/**
 * Lower-cases and strips macrons/punctuation for comparisons.
 *
 * @param string $text Text.
 * @return string
 */
function teatatu_homes_fold_text( $text ) {
	$text = strtr( (string) $text, array( 'ā' => 'a', 'ē' => 'e', 'ī' => 'i', 'ō' => 'o', 'ū' => 'u', 'Ā' => 'a', 'Ē' => 'e', 'Ī' => 'i', 'Ō' => 'o', 'Ū' => 'u' ) );
	$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	$text = preg_replace( '/[^a-z0-9\s]+/', ' ', $text );
	return trim( preg_replace( '/\s+/', ' ', $text ) );
}

/**
 * Whether a suburb name means Te Atatū Peninsula.
 *
 * @param string $suburb Suburb.
 * @return bool
 */
function teatatu_homes_suburb_is_peninsula( $suburb ) {
	$folded = teatatu_homes_fold_text( $suburb );
	return '' === $folded || false !== strpos( $folded, 'te atatu peninsula' ) || in_array( $folded, array( 'te atatu', 'te atatu north' ), true );
}

/**
 * Tidies a street name: trims, expands a trailing abbreviation ("Rd" →
 * "Road") and restores the macron in "Te Atatū".
 *
 * @param string $street Street.
 * @return string
 */
function teatatu_homes_normalize_street( $street ) {
	$street = trim( preg_replace( '/\s+/', ' ', sanitize_text_field( (string) $street ) ), " ,." );
	if ( '' === $street ) {
		return '';
	}
	$abbr  = array(
		'st' => 'Street', 'rd' => 'Road', 'ave' => 'Avenue', 'av' => 'Avenue', 'dr' => 'Drive', 'pl' => 'Place',
		'cres' => 'Crescent', 'cr' => 'Crescent', 'tce' => 'Terrace', 'ln' => 'Lane', 'ct' => 'Court', 'cl' => 'Close', 'gr' => 'Grove', 'pde' => 'Parade',
	);
	$words = explode( ' ', $street );
	$last  = strtolower( rtrim( end( $words ), '.' ) );
	if ( isset( $abbr[ $last ] ) && count( $words ) > 1 ) {
		$words[ count( $words ) - 1 ] = $abbr[ $last ];
	}
	$street = implode( ' ', $words );
	$street = preg_replace( '/\bte atatu\b/i', 'Te Atatū', $street );
	return $street;
}

/**
 * Validates address segments against the current address.
 *
 * @param array $input   Segments to apply (any subset).
 * @param array $current Current segments.
 * @return array|WP_Error Normalised segments.
 */
function teatatu_homes_validate_address( $input, $current = null ) {
	$defaults = teatatu_homes_address_fields();
	$new      = null === $current ? $defaults : array_merge( $defaults, $current );
	if ( ! is_array( $input ) ) {
		return new WP_Error( 'rest_invalid_param', __( 'The address must be an object.', 'teatatu-homes' ), array( 'status' => 400 ) );
	}

	if ( array_key_exists( 'suburb', $input ) && ! teatatu_homes_suburb_is_peninsula( $input['suburb'] ) ) {
		return new WP_Error( 'teatatu_homes_not_peninsula', __( 'This site only lists homes on Te Atatū Peninsula.', 'teatatu-homes' ), array( 'status' => 400, 'field' => 'address.suburb' ) );
	}
	if ( array_key_exists( 'unit', $input ) ) {
		$new['unit'] = sanitize_text_field( (string) $input['unit'] );
	}
	if ( array_key_exists( 'number', $input ) ) {
		$number = strtoupper( preg_replace( '/\s+/', '', sanitize_text_field( (string) $input['number'] ) ) );
		if ( '' !== $number && ! preg_match( '/^\d{1,5}[A-Z]?(-\d{1,5}[A-Z]?)?$/', $number ) ) {
			return new WP_Error( 'rest_invalid_param', __( 'The street number should look like 12, 12A or 12-14.', 'teatatu-homes' ), array( 'status' => 400, 'field' => 'address.number' ) );
		}
		$new['number'] = $number;
	}
	if ( array_key_exists( 'street', $input ) ) {
		$new['street'] = teatatu_homes_normalize_street( $input['street'] );
	}
	foreach ( array( 'lat', 'lng' ) as $axis ) {
		if ( array_key_exists( $axis, $input ) ) {
			$raw = trim( (string) $input[ $axis ] );
			if ( '' !== $raw && ! is_numeric( $raw ) ) {
				return new WP_Error( 'rest_invalid_param', __( 'Coordinates must be numbers.', 'teatatu-homes' ), array( 'status' => 400, 'field' => 'address.' . $axis ) );
			}
			$new[ $axis ] = '' === $raw ? '' : (string) round( (float) $raw, 6 );
		}
	}
	if ( ( '' === $new['lat'] ) !== ( '' === $new['lng'] ) ) {
		return new WP_Error( 'rest_invalid_param', __( 'Give both latitude and longitude, or neither.', 'teatatu-homes' ), array( 'status' => 400, 'field' => 'address.lat' ) );
	}
	if ( '' !== $new['lat'] ) {
		list( $min_lat, $max_lat, $min_lng, $max_lng ) = teatatu_homes_peninsula_bounds();
		$lat = (float) $new['lat'];
		$lng = (float) $new['lng'];
		if ( $lat < $min_lat || $lat > $max_lat || $lng < $min_lng || $lng > $max_lng ) {
			return new WP_Error( 'teatatu_homes_not_peninsula', __( 'Those coordinates are not on Te Atatū Peninsula.', 'teatatu-homes' ), array( 'status' => 400, 'field' => 'address.lat' ) );
		}
	}
	if ( array_key_exists( 'display', $input ) ) {
		$mode           = sanitize_key( $input['display'] );
		$new['display'] = array_key_exists( $mode, teatatu_homes_address_display_modes() ) ? $mode : 'full';
	}

	// Fixed for this site.
	$new['suburb']   = $defaults['suburb'];
	$new['city']     = $defaults['city'];
	$new['postcode'] = $defaults['postcode'];

	return $new;
}

/**
 * Formats address segments. The only place address segments are joined.
 *
 * @param array  $parts Segments.
 * @param string $style 'street' (12A Harbour View Road), 'single_line'
 *                      (… , Te Atatū Peninsula, Auckland 0610) or 'title'
 *                      (2/12A Harbour View Road).
 * @return string
 */
function teatatu_homes_format_address( $parts, $style = 'single_line' ) {
	$parts  = array_merge( teatatu_homes_address_fields(), (array) $parts );
	$number = $parts['number'];
	if ( '' !== $parts['unit'] && '' !== $number ) {
		$number = $parts['unit'] . '/' . $number;
	}
	$street = trim( $number . ' ' . $parts['street'] );
	if ( 'street' === $style || 'title' === $style ) {
		return $street;
	}
	$tail = trim( $parts['city'] . ' ' . $parts['postcode'] );
	return implode( ', ', array_filter( array( $street, $parts['suburb'], $tail ) ) );
}

/**
 * What the public may see of a home's address, honouring its display
 * mode. Every front-end and REST consumer uses this.
 *
 * @param int $post_id Home ID.
 * @return array {
 *     @type string     $display  full|street|hidden.
 *     @type string     $line1    Street line ("12A Harbour View Road", "Harbour View Road" or "Address on request").
 *     @type string     $line2    Locality ("Te Atatū Peninsula, Auckland 0610").
 *     @type string     $single   Both lines joined.
 *     @type array|null $point    {lat, lng} — only when the full address is shown.
 *     @type string     $map_url  OpenStreetMap link (full address only).
 * }
 */
function teatatu_homes_public_address( $post_id ) {
	$parts   = teatatu_homes_get_address( $post_id );
	$display = $parts['display'];
	$line2   = implode( ', ', array_filter( array( $parts['suburb'], trim( $parts['city'] . ' ' . $parts['postcode'] ) ) ) );

	if ( 'hidden' === $display || '' === $parts['street'] ) {
		$line1 = __( 'Address on request', 'teatatu-homes' );
	} elseif ( 'street' === $display ) {
		$line1 = $parts['street'];
	} else {
		$line1 = teatatu_homes_format_address( $parts, 'street' );
	}

	$point   = null;
	$map_url = '';
	if ( 'full' === $display && '' !== $parts['lat'] && '' !== $parts['lng'] ) {
		$point   = array( 'lat' => (float) $parts['lat'], 'lng' => (float) $parts['lng'] );
		$map_url = sprintf( 'https://www.openstreetmap.org/?mlat=%1$s&mlon=%2$s#map=17/%1$s/%2$s', rawurlencode( $parts['lat'] ), rawurlencode( $parts['lng'] ) );
	}

	return array(
		'display' => $display,
		'line1'   => $line1,
		'line2'   => $line2,
		'single'  => $line1 . ', ' . $line2,
		'point'   => $point,
		'map_url' => $map_url,
	);
}

/**
 * Full address for editors (admin list, notices, enquiry emails) —
 * ignores the display mode.
 *
 * @param int $post_id Home ID.
 * @return string
 */
function teatatu_homes_private_address( $post_id ) {
	return teatatu_homes_format_address( teatatu_homes_get_address( $post_id ), 'single_line' );
}

/**
 * Pulls address segments out of a flat form array (`tth_addr_<field>`).
 *
 * @param array $src Unslashed request data.
 * @return array
 */
function teatatu_homes_address_from_form( $src ) {
	$out = array();
	foreach ( array( 'unit', 'number', 'street', 'lat', 'lng', 'display' ) as $field ) {
		if ( isset( $src[ 'tth_addr_' . $field ] ) ) {
			$out[ $field ] = $src[ 'tth_addr_' . $field ];
		}
	}
	return $out;
}
