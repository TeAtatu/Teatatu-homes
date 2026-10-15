<?php
/**
 * REST API.
 *
 *  - The `teatatu_home` post type gets its standard routes at
 *    /wp-json/wp/v2/homes (and each taxonomy at its own rest_base) from
 *    register_post_type()/register_taxonomy().
 *  - A `home` field on those routes is the ONLY way listing data is read or
 *    written over REST. In the default `view` context it is the public
 *    normalised item (teatatu_homes_normalize_item() — hidden addresses
 *    stay hidden, the agent's email is never included). In the `edit`
 *    context, for a user who can edit the home, it also carries the raw
 *    fields and full address. Writes go through teatatu_homes_save_fields().
 *  - GET /wp-json/teatatu-homes/v1/search — the same filtered search the
 *    Homes archive uses (includes/query.php).
 *  - GET /wp-json/teatatu-homes/v1/vocabulary — every allowed value
 *    (listing types, statuses, sale methods, …) so agents and front-end
 *    scripts never hard-code them.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', 'teatatu_homes_register_rest' );

/**
 * Registers the `home` field and the plugin's own routes.
 */
function teatatu_homes_register_rest() {
	register_rest_field(
		'teatatu_home',
		'home',
		array(
			'get_callback'    => 'teatatu_homes_rest_get_home',
			'update_callback' => 'teatatu_homes_rest_update_home',
			'schema'          => array(
				'description' => __( 'Listing data. Read: public listing (view) or full fields (edit). Write: any subset of the writable keys.', 'teatatu-homes' ),
				'type'        => 'object',
				'context'     => array( 'view', 'edit' ),
				'arg_options' => array(
					'validate_callback' => 'teatatu_homes_rest_validate_home',
				),
			),
		)
	);

	$filter_args = array();
	foreach ( teatatu_homes_filter_defs() as $key => $def ) {
		$filter_args[ $key ] = array(
			'description' => $def[2],
			'type'        => 'slugs' === $def[0] ? array( 'array', 'string' ) : ( 'int' === $def[0] ? 'integer' : ( 'bool' === $def[0] ? 'boolean' : 'string' ) ),
		);
	}
	$filter_args['page']     = array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 );
	$filter_args['per_page'] = array( 'type' => 'integer', 'default' => 12, 'minimum' => 1, 'maximum' => 48 );

	register_rest_route(
		'teatatu-homes/v1',
		'/search',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'teatatu_homes_rest_search',
			'permission_callback' => '__return_true',
			'args'                => $filter_args,
		)
	);

	register_rest_route(
		'teatatu-homes/v1',
		'/vocabulary',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'teatatu_homes_rest_vocabulary',
			'permission_callback' => '__return_true',
		)
	);
}

/**
 * Writable keys of the `home` field.
 *
 * @return string[]
 */
function teatatu_homes_rest_writable_keys() {
	return array_merge( array_keys( teatatu_homes_field_defaults() ), array( 'listing', 'property_type', 'address', 'features', 'agency' ) );
}

/**
 * `home` field reader.
 *
 * @param array           $object  Prepared post data.
 * @param string          $field   Field name.
 * @param WP_REST_Request $request Request.
 * @return array
 */
function teatatu_homes_rest_get_home( $object, $field, $request ) {
	$id   = (int) $object['id'];
	$item = teatatu_homes_normalize_item( $id );
	if ( 'edit' === $request['context'] && current_user_can( 'edit_post', $id ) ) {
		$type          = teatatu_homes_first_term( $id, 'teatatu_home_type' );
		$agency        = teatatu_homes_first_term( $id, 'teatatu_home_agency' );
		$item['edit']  = array_merge(
			teatatu_homes_get_fields( $id ),
			array(
				'listing'       => teatatu_homes_get_listing_type( $id ),
				'property_type' => $type ? $type->slug : '',
				'agency'        => $agency ? $agency->slug : '',
				'features'      => wp_list_pluck( teatatu_homes_term_list( $id, 'teatatu_home_feature' ), 'name' ),
				'address'       => teatatu_homes_get_address( $id ),
				'missing'       => teatatu_homes_missing_fields( $id ),
				'needs_area'    => (bool) get_post_meta( $id, teatatu_homes_mk( 'needs_area' ), true ),
			)
		);
	}
	return $item;
}

/**
 * Validates a `home` write up front, so a bad payload fails the whole
 * request (400) before the post is created or changed.
 *
 * @param mixed           $value   Submitted value.
 * @param WP_REST_Request $request Request.
 * @return true|WP_Error
 */
function teatatu_homes_rest_validate_home( $value, $request ) {
	if ( ! is_array( $value ) ) {
		return new WP_Error( 'rest_invalid_param', __( '`home` must be an object.', 'teatatu-homes' ), array( 'status' => 400 ) );
	}
	$unknown = array_diff( array_keys( $value ), teatatu_homes_rest_writable_keys() );
	if ( $unknown ) {
		/* translators: %s: key names. */
		return new WP_Error( 'rest_invalid_param', sprintf( __( 'Unknown `home` keys: %s.', 'teatatu-homes' ), implode( ', ', $unknown ) ), array( 'status' => 400 ) );
	}
	$id      = (int) $request['id'];
	$current = $id && 'teatatu_home' === get_post_type( $id ) ? teatatu_homes_get_fields( $id ) : teatatu_homes_field_defaults();
	$listing = isset( $value['listing'] ) ? sanitize_key( (string) $value['listing'] ) : ( $id ? teatatu_homes_get_listing_type( $id ) : '' );
	if ( '' !== $listing && ! array_key_exists( $listing, teatatu_homes_listing_types() ) ) {
		return new WP_Error( 'rest_invalid_param', __( 'Listing type must be "sale" or "rent".', 'teatatu-homes' ), array( 'status' => 400 ) );
	}
	$checked = teatatu_homes_validate_fields( $value, $current, $listing, 'teatatu_home' === get_post_type( $id ) ? $id : 0 );
	if ( is_wp_error( $checked ) ) {
		return $checked;
	}
	if ( isset( $value['address'] ) ) {
		$address = teatatu_homes_validate_address( $value['address'], $id ? teatatu_homes_get_address( $id ) : null );
		if ( is_wp_error( $address ) ) {
			return $address;
		}
	}
	return true;
}

/**
 * `home` field writer.
 *
 * @param array   $value Submitted value.
 * @param WP_Post $post  Post.
 * @return true|WP_Error
 */
function teatatu_homes_rest_update_home( $value, $post ) {
	if ( ! current_user_can( 'edit_post', $post->ID ) ) {
		return new WP_Error( 'rest_forbidden', __( 'You cannot edit this home.', 'teatatu-homes' ), array( 'status' => 403 ) );
	}
	return teatatu_homes_save_fields( $post->ID, (array) $value );
}

add_action( 'rest_after_insert_teatatu_home', 'teatatu_homes_rest_after_insert' );

/**
 * After a REST create/update (terms and fields applied), refresh the Area
 * and derived values.
 *
 * @param WP_Post $post Post.
 */
function teatatu_homes_rest_after_insert( $post ) {
	teatatu_homes_assign_area( $post->ID );
	teatatu_homes_refresh_derived( $post->ID );
}

/**
 * GET /teatatu-homes/v1/search.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function teatatu_homes_rest_search( WP_REST_Request $request ) {
	$filters = teatatu_homes_sanitize_filters( $request->get_params() );
	$result  = teatatu_homes_search( $filters, (int) $request['page'], (int) $request['per_page'] );
	$result['filters'] = $filters;

	$response = rest_ensure_response( $result );
	$response->header( 'X-WP-Total', (string) $result['total'] );
	$response->header( 'X-WP-TotalPages', (string) $result['pages'] );
	return $response;
}

/**
 * GET /teatatu-homes/v1/vocabulary.
 *
 * @return WP_REST_Response
 */
function teatatu_homes_rest_vocabulary() {
	$terms = function ( $taxonomy ) {
		$out = array();
		foreach ( get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) ) as $term ) {
			$out[] = array( 'id' => (int) $term->term_id, 'slug' => $term->slug, 'name' => $term->name, 'description' => $term->description, 'count' => (int) $term->count );
		}
		return $out;
	};
	$statuses = array();
	foreach ( teatatu_homes_status_defs() as $slug => $def ) {
		$statuses[ $slug ] = array( 'label' => $def[0], 'listing' => $def[1] );
	}
	return rest_ensure_response(
		array(
			'listing_types'    => teatatu_homes_listing_types(),
			'statuses'         => $statuses,
			'active_statuses'  => teatatu_homes_active_statuses(),
			'sale_methods'     => teatatu_homes_sale_methods(),
			'dated_methods'    => teatatu_homes_dated_sale_methods(),
			'pet_policies'     => teatatu_homes_pet_policies(),
			'furnishing'       => teatatu_homes_furnishing_options(),
			'title_types'      => teatatu_homes_title_types(),
			'address_display'  => teatatu_homes_address_display_modes(),
			'address_defaults' => array_intersect_key( teatatu_homes_address_fields(), array_flip( array( 'suburb', 'city', 'postcode' ) ) ),
			'sort_options'     => teatatu_homes_sort_options(),
			'price_steps'      => array( 'sale' => teatatu_homes_price_steps( 'sale' ), 'rent' => teatatu_homes_price_steps( 'rent' ) ),
			'areas'            => $terms( 'teatatu_home_area' ),
			'property_types'   => $terms( 'teatatu_home_type' ),
			'features'         => $terms( 'teatatu_home_feature' ),
			'agencies'         => $terms( 'teatatu_home_agency' ),
		)
	);
}
