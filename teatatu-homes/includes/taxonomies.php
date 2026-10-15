<?php
/**
 * Registers the five Homes taxonomies, their term meta, the Agency
 * contact fields on the term screens, and the fixed/starter terms.
 *
 *  - teatatu_home_listing  Listing Type — fixed: "For sale" (sale), "For rent" (rent).
 *  - teatatu_home_type     Property Type — House, Townhouse, Unit / apartment, Section (editable).
 *  - teatatu_home_area     Area — fixed: Matipo, Beach, Harbourview (see includes/areas.php).
 *  - teatatu_home_feature  Features — free tags (Heat pump, Double glazing, …).
 *  - teatatu_home_agency   Agency — the listing agency or property manager, with contact term meta.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'teatatu_homes_register_taxonomies' );

/**
 * Taxonomy definitions: slug => [singular, plural, rest_base, hierarchical, rewrite slug, cap suffix].
 *
 * @return array
 */
function teatatu_homes_taxonomy_defs() {
	return array(
		'teatatu_home_listing' => array( __( 'Listing Type', 'teatatu-homes' ), __( 'Listing Types', 'teatatu-homes' ), 'home-listing-types', true, 'listing', 'listings' ),
		'teatatu_home_type'    => array( __( 'Property Type', 'teatatu-homes' ), __( 'Property Types', 'teatatu-homes' ), 'home-types', true, 'property-type', 'types' ),
		'teatatu_home_area'    => array( __( 'Area', 'teatatu-homes' ), __( 'Areas', 'teatatu-homes' ), 'home-areas', true, 'area', 'areas' ),
		'teatatu_home_feature' => array( __( 'Feature', 'teatatu-homes' ), __( 'Features', 'teatatu-homes' ), 'home-features', false, 'feature', 'features' ),
		'teatatu_home_agency'  => array( __( 'Agency', 'teatatu-homes' ), __( 'Agencies', 'teatatu-homes' ), 'home-agencies', true, 'agency', 'agencies' ),
	);
}

/**
 * Registers every Homes taxonomy with explicit, narrowly-scoped capabilities.
 */
function teatatu_homes_register_taxonomies() {
	foreach ( teatatu_homes_taxonomy_defs() as $taxonomy => $def ) {
		list( $singular, $plural, $rest_base, $hierarchical, $rewrite, $cap ) = $def;
		register_taxonomy(
			$taxonomy,
			array( 'teatatu_home' ),
			array(
				'labels'            => array(
					'name'          => $plural,
					'singular_name' => $singular,
					'menu_name'     => $plural,
					/* translators: %s: taxonomy singular name. */
					'add_new_item'  => sprintf( __( 'Add New %s', 'teatatu-homes' ), $singular ),
					/* translators: %s: taxonomy singular name. */
					'edit_item'     => sprintf( __( 'Edit %s', 'teatatu-homes' ), $singular ),
					/* translators: %s: taxonomy plural name. */
					'search_items'  => sprintf( __( 'Search %s', 'teatatu-homes' ), $plural ),
				),
				'hierarchical'      => $hierarchical,
				'public'            => true,
				'show_in_rest'      => true,
				'rest_base'         => $rest_base,
				'show_admin_column' => in_array( $taxonomy, array( 'teatatu_home_listing', 'teatatu_home_area' ), true ),
				'rewrite'           => array( 'slug' => $rewrite, 'with_front' => false ),
				'capabilities'      => array(
					'manage_terms' => 'manage_teatatu_home_' . $cap,
					'edit_terms'   => 'manage_teatatu_home_' . $cap,
					'delete_terms' => 'manage_teatatu_home_' . $cap,
					'assign_terms' => 'assign_teatatu_home_' . $cap,
				),
			)
		);
	}
}

/**
 * The fixed Listing Types: slug => label. The slugs `sale` and `rent` are
 * the keys every other part of the data model (validation, price labels,
 * queries, REST) uses — never rename them.
 *
 * @return string[]
 */
function teatatu_homes_listing_types() {
	return array(
		'sale' => __( 'For sale', 'teatatu-homes' ),
		'rent' => __( 'For rent', 'teatatu-homes' ),
	);
}

/**
 * Starter Property Types (editors may add more).
 *
 * @return string[] slug => name
 */
function teatatu_homes_default_property_types() {
	return array(
		'house'     => __( 'House', 'teatatu-homes' ),
		'townhouse' => __( 'Townhouse', 'teatatu-homes' ),
		'unit'      => __( 'Unit / apartment', 'teatatu-homes' ),
		'section'   => __( 'Section', 'teatatu-homes' ),
	);
}

/**
 * Creates the fixed Listing Types and Areas, and the starter Property
 * Types, on the current site if missing. Never renames or deletes.
 */
function teatatu_homes_seed_terms() {
	if ( ! taxonomy_exists( 'teatatu_home_listing' ) ) {
		teatatu_homes_register_taxonomies();
	}
	$sets = array(
		'teatatu_home_listing' => teatatu_homes_listing_types(),
		'teatatu_home_type'    => teatatu_homes_default_property_types(),
	);
	foreach ( teatatu_homes_area_defs() as $slug => $def ) {
		$sets['teatatu_home_area'][ $slug ] = $def;
	}
	foreach ( $sets as $taxonomy => $terms ) {
		foreach ( $terms as $slug => $def ) {
			if ( get_term_by( 'slug', $slug, $taxonomy ) ) {
				continue;
			}
			$name = is_array( $def ) ? $def[0] : $def;
			$desc = is_array( $def ) ? $def[1] : '';
			wp_insert_term( $name, $taxonomy, array( 'slug' => $slug, 'description' => $desc ) );
		}
	}
}

add_filter( 'pre_delete_term', 'teatatu_homes_guard_fixed_terms', 10, 2 );

/**
 * Stops the fixed Listing Type and Area terms being deleted — the data
 * model and the theme rely on their slugs.
 *
 * @param int    $term_id  Term ID.
 * @param string $taxonomy Taxonomy.
 */
function teatatu_homes_guard_fixed_terms( $term_id, $taxonomy ) {
	if ( defined( 'WP_UNINSTALL_PLUGIN' ) ) {
		return;
	}
	$fixed = array(
		'teatatu_home_listing' => array_keys( teatatu_homes_listing_types() ),
		'teatatu_home_area'    => array_keys( teatatu_homes_area_defs() ),
	);
	if ( empty( $fixed[ $taxonomy ] ) ) {
		return;
	}
	$term = get_term( $term_id, $taxonomy );
	if ( $term && ! is_wp_error( $term ) && in_array( $term->slug, $fixed[ $taxonomy ], true ) ) {
		wp_die( esc_html__( 'This term is part of the Homes data model and cannot be deleted. You can rename it instead.', 'teatatu-homes' ), 403 );
	}
}

/**
 * Agency contact fields: field => term meta key.
 *
 * @return string[]
 */
function teatatu_homes_agency_meta_keys() {
	return array(
		'logo_url' => 'teatatu_home_agency_logo_url',
		'phone'    => 'teatatu_home_agency_phone',
		'email'    => 'teatatu_home_agency_email',
		'website'  => 'teatatu_home_agency_website',
	);
}

add_action( 'init', 'teatatu_homes_register_term_meta' );

/**
 * Registers Agency term meta (REST-exposed; writes need manage_teatatu_home_agencies).
 */
function teatatu_homes_register_term_meta() {
	$sanitizers = array(
		'logo_url' => 'esc_url_raw',
		'phone'    => 'sanitize_text_field',
		'email'    => 'sanitize_email',
		'website'  => 'esc_url_raw',
	);
	foreach ( teatatu_homes_agency_meta_keys() as $field => $key ) {
		register_term_meta(
			'teatatu_home_agency',
			$key,
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => $sanitizers[ $field ],
				'show_in_rest'      => true,
				'auth_callback'     => function () {
					return current_user_can( 'manage_teatatu_home_agencies' );
				},
			)
		);
	}
}

/**
 * Agency field labels.
 *
 * @return string[]
 */
function teatatu_homes_agency_field_labels() {
	return array(
		'logo_url' => __( 'Logo URL', 'teatatu-homes' ),
		'phone'    => __( 'Phone', 'teatatu-homes' ),
		'email'    => __( 'Email', 'teatatu-homes' ),
		'website'  => __( 'Website', 'teatatu-homes' ),
	);
}

add_action( 'teatatu_home_agency_add_form_fields', 'teatatu_homes_agency_add_form_fields' );
add_action( 'teatatu_home_agency_edit_form_fields', 'teatatu_homes_agency_edit_form_fields' );

/**
 * Agency contact fields on the "Add New Agency" screen.
 */
function teatatu_homes_agency_add_form_fields() {
	foreach ( teatatu_homes_agency_field_labels() as $field => $label ) {
		$type = 'email' === $field ? 'email' : ( in_array( $field, array( 'logo_url', 'website' ), true ) ? 'url' : 'text' );
		?>
		<div class="form-field">
			<label for="teatatu_home_agency_<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $label ); ?></label>
			<input type="<?php echo esc_attr( $type ); ?>" name="teatatu_home_agency[<?php echo esc_attr( $field ); ?>]" id="teatatu_home_agency_<?php echo esc_attr( $field ); ?>" value="" class="regular-text" />
		</div>
		<?php
	}
}

/**
 * Agency contact fields on the "Edit Agency" screen.
 *
 * @param WP_Term $term Term being edited.
 */
function teatatu_homes_agency_edit_form_fields( $term ) {
	$keys = teatatu_homes_agency_meta_keys();
	foreach ( teatatu_homes_agency_field_labels() as $field => $label ) {
		$type  = 'email' === $field ? 'email' : ( in_array( $field, array( 'logo_url', 'website' ), true ) ? 'url' : 'text' );
		$value = get_term_meta( $term->term_id, $keys[ $field ], true );
		?>
		<tr class="form-field">
			<th scope="row"><label for="teatatu_home_agency_<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td><input type="<?php echo esc_attr( $type ); ?>" name="teatatu_home_agency[<?php echo esc_attr( $field ); ?>]" id="teatatu_home_agency_<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" /></td>
		</tr>
		<?php
	}
}

add_action( 'saved_teatatu_home_agency', 'teatatu_homes_save_agency_fields' );

/**
 * Saves Agency contact fields from either term screen.
 *
 * @param int $term_id Term ID.
 */
function teatatu_homes_save_agency_fields( $term_id ) {
	if ( ! isset( $_POST['teatatu_home_agency'] ) || ! is_array( $_POST['teatatu_home_agency'] ) ) {
		return;
	}
	$nonce_ok = false;
	if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( wp_unslash( $_POST['_wpnonce'] ), 'update-tag_' . $term_id ) ) {
		$nonce_ok = true;
	} elseif ( isset( $_POST['_wpnonce_add-tag'] ) && wp_verify_nonce( wp_unslash( $_POST['_wpnonce_add-tag'] ), 'add-tag' ) ) {
		$nonce_ok = true;
	}
	if ( ! $nonce_ok || ! current_user_can( 'manage_teatatu_home_agencies' ) ) {
		return;
	}
	$input = wp_unslash( $_POST['teatatu_home_agency'] );
	foreach ( teatatu_homes_agency_meta_keys() as $field => $key ) {
		if ( ! isset( $input[ $field ] ) ) {
			continue;
		}
		$raw   = (string) $input[ $field ];
		$value = 'email' === $field ? sanitize_email( $raw ) : ( in_array( $field, array( 'logo_url', 'website' ), true ) ? esc_url_raw( $raw, array( 'http', 'https' ) ) : sanitize_text_field( $raw ) );
		update_term_meta( $term_id, $key, $value );
	}
}

/**
 * An Agency as a plain array.
 *
 * @param WP_Term|null $term Agency term.
 * @return array|null
 */
function teatatu_homes_agency_data( $term ) {
	if ( ! $term || is_wp_error( $term ) ) {
		return null;
	}
	$out = array(
		'id'   => (int) $term->term_id,
		'slug' => $term->slug,
		'name' => $term->name,
		'link' => get_term_link( $term ),
	);
	foreach ( teatatu_homes_agency_meta_keys() as $field => $key ) {
		$out[ $field ] = (string) get_term_meta( $term->term_id, $key, true );
	}
	return $out;
}

/**
 * First assigned term of a taxonomy.
 *
 * @param int    $post_id  Post ID.
 * @param string $taxonomy Taxonomy.
 * @return WP_Term|null
 */
function teatatu_homes_first_term( $post_id, $taxonomy ) {
	$terms = get_the_terms( $post_id, $taxonomy );
	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return null;
	}
	return $terms[0];
}

/**
 * Assigned terms as [{id, slug, name}].
 *
 * @param int    $post_id  Post ID.
 * @param string $taxonomy Taxonomy.
 * @return array
 */
function teatatu_homes_term_list( $post_id, $taxonomy ) {
	$terms = get_the_terms( $post_id, $taxonomy );
	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return array();
	}
	$out = array();
	foreach ( $terms as $term ) {
		$out[] = array( 'id' => (int) $term->term_id, 'slug' => $term->slug, 'name' => $term->name );
	}
	return $out;
}
