<?php
/**
 * The "Listing details" meta box: one form for every field of the data
 * model (listing, price, rooms, address, rental terms, media, documents,
 * open homes, agent). It saves through teatatu_homes_save_fields(), the
 * same validated writer REST uses. Homes use the classic edit screen so
 * this form is the single place Listing Type and Property Type are set.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'use_block_editor_for_post_type', 'teatatu_homes_disable_block_editor', 10, 2 );

/**
 * Homes use the classic edit screen (a data form, not a page layout).
 *
 * @param bool   $use       Whether to use the block editor.
 * @param string $post_type Post type.
 * @return bool
 */
function teatatu_homes_disable_block_editor( $use, $post_type ) {
	return 'teatatu_home' === $post_type ? false : $use;
}

add_action( 'add_meta_boxes_teatatu_home', 'teatatu_homes_add_meta_boxes' );

/**
 * Registers the meta box and removes the native boxes it replaces.
 */
function teatatu_homes_add_meta_boxes() {
	remove_meta_box( 'teatatu_home_listingdiv', 'teatatu_home', 'side' );
	remove_meta_box( 'teatatu_home_typediv', 'teatatu_home', 'side' );
	add_meta_box( 'teatatu_homes_details', __( 'Listing details', 'teatatu-homes' ), 'teatatu_homes_render_meta_box', 'teatatu_home', 'normal', 'high' );
}

add_action( 'admin_enqueue_scripts', 'teatatu_homes_admin_assets' );

/**
 * Admin styles and the small show/hide script for sale vs rent fields.
 *
 * @param string $hook Admin page hook.
 */
function teatatu_homes_admin_assets( $hook ) {
	$screen = get_current_screen();
	if ( ! $screen || 'teatatu_home' !== $screen->post_type ) {
		return;
	}
	wp_enqueue_style( 'teatatu-homes-admin', TEATATU_HOMES_URL . 'assets/css/teatatu-homes-admin.css', array(), TEATATU_HOMES_VERSION );
	if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		$post = get_post();
		$deps = array();
		if ( $post && current_user_can( 'upload_files' ) ) {
			// Uploads from this screen carry the home's ID, so they land in its folder.
			wp_enqueue_media( array( 'post' => $post->ID ) );
			$deps[] = 'media-editor';
		}
		wp_enqueue_script( 'teatatu-homes-admin', TEATATU_HOMES_URL . 'assets/js/teatatu-homes-admin.js', $deps, TEATATU_HOMES_VERSION, true );
		wp_localize_script(
			'teatatu-homes-admin',
			'tthAdmin',
			array(
				'frameTitle'  => __( 'Photos for this home', 'teatatu-homes' ),
				'frameButton' => __( 'Add to listing', 'teatatu-homes' ),
				'badUrl'      => __( 'Enter a full image URL starting with https://', 'teatatu-homes' ),
			)
		);
	}
}

/**
 * Prints a <select>.
 *
 * @param string $name    Field name.
 * @param array  $options value => label.
 * @param string $current Current value.
 */
function teatatu_homes_select( $name, $options, $current ) {
	printf( '<select id="%1$s" name="%1$s">', esc_attr( $name ) );
	foreach ( $options as $value => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( (string) $current, (string) $value, false ), esc_html( $label ) );
	}
	echo '</select>';
}

/**
 * Local "Y-m-d" / "H:i" parts of a timestamp for date/time inputs.
 *
 * @param int    $ts     UTC timestamp.
 * @param string $format Format.
 * @return string
 */
function teatatu_homes_local( $ts, $format ) {
	return $ts ? wp_date( $format, $ts ) : '';
}

/**
 * Renders the "Listing details" meta box.
 *
 * @param WP_Post $post Current post.
 */
function teatatu_homes_render_meta_box( $post ) {
	wp_nonce_field( 'teatatu_homes_save_meta_box', 'teatatu_homes_meta_box_nonce' );

	$f        = teatatu_homes_get_fields( $post->ID );
	$addr     = teatatu_homes_get_address( $post->ID );
	$listing  = teatatu_homes_get_listing_type( $post->ID );
	$type     = teatatu_homes_first_term( $post->ID, 'teatatu_home_type' );
	$statuses = array();
	foreach ( teatatu_homes_status_defs() as $key => $def ) {
		$statuses[ $key ] = $def[0];
	}
	$types = array( '' => __( '— Choose —', 'teatatu-homes' ) );
	foreach ( get_terms( array( 'taxonomy' => 'teatatu_home_type', 'hide_empty' => false ) ) as $term ) {
		$types[ $term->slug ] = $term->name;
	}
	$opens = $f['open_homes'];
	while ( count( $opens ) < 4 ) {
		$opens[] = array( 'start' => 0, 'end' => 0 );
	}
	$docs = array();
	foreach ( $f['documents'] as $doc ) {
		$docs[] = $doc['label'] . ' | ' . $doc['url'];
	}
	$area_via = get_post_meta( $post->ID, teatatu_homes_mk( 'area_via' ), true );
	?>
	<div class="tth-box" data-listing="<?php echo esc_attr( $listing ); ?>">

	<fieldset class="tth-section">
		<legend><?php esc_html_e( 'Listing', 'teatatu-homes' ); ?></legend>
		<div class="tth-grid">
			<div class="tth-field">
				<span class="tth-label"><?php esc_html_e( 'Listing type', 'teatatu-homes' ); ?> <span class="tth-req">*</span></span>
				<?php foreach ( teatatu_homes_listing_types() as $slug => $label ) : ?>
					<label class="tth-inline"><input type="radio" name="tth_listing" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $listing, $slug ); ?> /> <?php echo esc_html( $label ); ?></label>
				<?php endforeach; ?>
			</div>
			<div class="tth-field">
				<label for="tth_status"><?php esc_html_e( 'Status', 'teatatu-homes' ); ?></label>
				<?php teatatu_homes_select( 'tth_status', $statuses, $f['status'] ); ?>
			</div>
			<div class="tth-field">
				<label for="tth_property_type"><?php esc_html_e( 'Property type', 'teatatu-homes' ); ?></label>
				<?php teatatu_homes_select( 'tth_property_type', $types, $type ? $type->slug : '' ); ?>
			</div>
		</div>
		<div class="tth-grid">
			<div class="tth-field tth-sale">
				<label for="tth_sale_method"><?php esc_html_e( 'Sale method', 'teatatu-homes' ); ?></label>
				<?php teatatu_homes_select( 'tth_sale_method', teatatu_homes_sale_methods(), $f['sale_method'] ); ?>
			</div>
			<div class="tth-field">
				<label for="tth_price"><span class="tth-sale"><?php esc_html_e( 'Price ($)', 'teatatu-homes' ); ?></span><span class="tth-rent"><?php esc_html_e( 'Rent per week ($)', 'teatatu-homes' ); ?></span></label>
				<input type="text" inputmode="numeric" id="tth_price" name="tth_price" value="<?php echo $f['price'] ? esc_attr( $f['price'] ) : ''; ?>" />
			</div>
			<div class="tth-field tth-sale">
				<label for="tth_method_date"><?php esc_html_e( 'Auction / closing date and time', 'teatatu-homes' ); ?></label>
				<span class="tth-inline">
					<input type="date" id="tth_method_date" name="tth_method_date" value="<?php echo esc_attr( teatatu_homes_local( $f['method_date'], 'Y-m-d' ) ); ?>" />
					<input type="time" name="tth_method_time" aria-label="<?php esc_attr_e( 'Time', 'teatatu-homes' ); ?>" value="<?php echo esc_attr( teatatu_homes_local( $f['method_date'], 'H:i' ) ); ?>" />
				</span>
			</div>
			<div class="tth-field">
				<label for="tth_price_text"><?php esc_html_e( 'Price wording override', 'teatatu-homes' ); ?></label>
				<input type="text" id="tth_price_text" name="tth_price_text" value="<?php echo esc_attr( $f['price_text'] ); ?>" placeholder="<?php esc_attr_e( 'Optional — replaces the generated price line', 'teatatu-homes' ); ?>" />
			</div>
			<div class="tth-field tth-sale">
				<label for="tth_title_type"><?php esc_html_e( 'Title', 'teatatu-homes' ); ?></label>
				<?php teatatu_homes_select( 'tth_title_type', teatatu_homes_title_types(), $f['title_type'] ); ?>
			</div>
		</div>
		<p class="description tth-sale"><?php esc_html_e( 'The price is used for searching even when the sale method hides it (auction, negotiation). Leave it blank if the vendor does not want it searchable.', 'teatatu-homes' ); ?></p>
	</fieldset>

	<fieldset class="tth-section tth-rent">
		<legend><?php esc_html_e( 'Rental terms', 'teatatu-homes' ); ?></legend>
		<div class="tth-grid">
			<div class="tth-field">
				<label for="tth_available_from"><?php esc_html_e( 'Available from', 'teatatu-homes' ); ?></label>
				<input type="date" id="tth_available_from" name="tth_available_from" value="<?php echo esc_attr( teatatu_homes_local( $f['available_from'], 'Y-m-d' ) ); ?>" />
			</div>
			<div class="tth-field">
				<label for="tth_bond"><?php esc_html_e( 'Bond ($)', 'teatatu-homes' ); ?></label>
				<input type="text" inputmode="numeric" id="tth_bond" name="tth_bond" value="<?php echo $f['bond'] ? esc_attr( $f['bond'] ) : ''; ?>" />
			</div>
			<div class="tth-field">
				<label for="tth_pets"><?php esc_html_e( 'Pets', 'teatatu-homes' ); ?></label>
				<?php teatatu_homes_select( 'tth_pets', teatatu_homes_pet_policies(), $f['pets'] ); ?>
			</div>
			<div class="tth-field">
				<label for="tth_furnished"><?php esc_html_e( 'Furnishing', 'teatatu-homes' ); ?></label>
				<?php teatatu_homes_select( 'tth_furnished', teatatu_homes_furnishing_options(), $f['furnished'] ); ?>
			</div>
			<div class="tth-field">
				<span class="tth-label"><?php esc_html_e( 'Healthy Homes', 'teatatu-homes' ); ?></span>
				<label class="tth-inline"><input type="checkbox" name="tth_healthy_homes" value="1" <?php checked( $f['healthy_homes'] ); ?> /> <?php esc_html_e( 'Meets the Healthy Homes Standards', 'teatatu-homes' ); ?></label>
			</div>
		</div>
	</fieldset>

	<fieldset class="tth-section">
		<legend><?php esc_html_e( 'Property', 'teatatu-homes' ); ?></legend>
		<div class="tth-grid">
			<?php
			$numbers = array(
				'bedrooms'   => __( 'Bedrooms', 'teatatu-homes' ),
				'bathrooms'  => __( 'Bathrooms', 'teatatu-homes' ),
				'car_parks'  => __( 'Car parks', 'teatatu-homes' ),
				'floor_area' => __( 'Floor area (m²)', 'teatatu-homes' ),
				'land_area'  => __( 'Land area (m²)', 'teatatu-homes' ),
			);
			foreach ( $numbers as $field => $label ) :
				?>
				<div class="tth-field tth-narrow">
					<label for="tth_<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $label ); ?></label>
					<input type="number" min="0" id="tth_<?php echo esc_attr( $field ); ?>" name="tth_<?php echo esc_attr( $field ); ?>" value="<?php echo $f[ $field ] ? esc_attr( $f[ $field ] ) : ''; ?>" />
				</div>
			<?php endforeach; ?>
		</div>
	</fieldset>

	<fieldset class="tth-section">
		<legend><?php esc_html_e( 'Address', 'teatatu-homes' ); ?></legend>
		<div class="tth-grid">
			<div class="tth-field tth-narrow">
				<label for="tth_addr_unit"><?php esc_html_e( 'Unit', 'teatatu-homes' ); ?></label>
				<input type="text" id="tth_addr_unit" name="tth_addr_unit" value="<?php echo esc_attr( $addr['unit'] ); ?>" />
			</div>
			<div class="tth-field tth-narrow">
				<label for="tth_addr_number"><?php esc_html_e( 'Number', 'teatatu-homes' ); ?></label>
				<input type="text" id="tth_addr_number" name="tth_addr_number" value="<?php echo esc_attr( $addr['number'] ); ?>" />
			</div>
			<div class="tth-field tth-wide">
				<label for="tth_addr_street"><?php esc_html_e( 'Street', 'teatatu-homes' ); ?> <span class="tth-req">*</span></label>
				<input type="text" id="tth_addr_street" name="tth_addr_street" value="<?php echo esc_attr( $addr['street'] ); ?>" placeholder="Harbour View Road" />
			</div>
			<div class="tth-field">
				<span class="tth-label"><?php esc_html_e( 'Suburb', 'teatatu-homes' ); ?></span>
				<span class="tth-static"><?php echo esc_html( $addr['suburb'] . ', ' . $addr['city'] . ' ' . $addr['postcode'] ); ?></span>
			</div>
		</div>
		<div class="tth-grid">
			<div class="tth-field">
				<label for="tth_addr_lat"><?php esc_html_e( 'Latitude', 'teatatu-homes' ); ?></label>
				<input type="text" id="tth_addr_lat" name="tth_addr_lat" value="<?php echo esc_attr( $addr['lat'] ); ?>" placeholder="-36.84" />
			</div>
			<div class="tth-field">
				<label for="tth_addr_lng"><?php esc_html_e( 'Longitude', 'teatatu-homes' ); ?></label>
				<input type="text" id="tth_addr_lng" name="tth_addr_lng" value="<?php echo esc_attr( $addr['lng'] ); ?>" placeholder="174.65" />
			</div>
			<div class="tth-field tth-wide">
				<label for="tth_addr_display"><?php esc_html_e( 'Show on the website', 'teatatu-homes' ); ?></label>
				<?php teatatu_homes_select( 'tth_addr_display', teatatu_homes_address_display_modes(), $addr['display'] ); ?>
			</div>
		</div>
		<p class="description">
			<?php
			if ( 'events' === $area_via ) {
				esc_html_e( 'The Area was worked out from this address. Tick a different Area in the Areas box to override it.', 'teatatu-homes' );
			} elseif ( function_exists( 'teatatu_events_resolve_neighbourhood' ) ) {
				esc_html_e( 'If no Area is ticked, it is worked out from the street (using Teatatu Events street rules) when you save.', 'teatatu-homes' );
			} else {
				esc_html_e( 'Tick the Area in the Areas box. (With the Teatatu Events plugin active it is worked out from the street automatically.)', 'teatatu-homes' );
			}
			?>
		</p>
	</fieldset>

	<fieldset class="tth-section">
		<legend><?php esc_html_e( 'Photos and media', 'teatatu-homes' ); ?></legend>
		<p class="tth-label" style="margin:8px 0 4px;"><?php esc_html_e( 'Photos', 'teatatu-homes' ); ?></p>
		<?php teatatu_homes_render_photo_manager( $post, $f['photos'] ); ?>
		<div class="tth-grid">
			<div class="tth-field tth-wide">
				<label for="tth_floor_plan_url"><?php esc_html_e( 'Floor plan URL', 'teatatu-homes' ); ?></label>
				<input type="url" id="tth_floor_plan_url" name="tth_floor_plan_url" value="<?php echo esc_attr( $f['floor_plan_url'] ); ?>" />
			</div>
			<div class="tth-field tth-wide">
				<label for="tth_video_url"><?php esc_html_e( 'Video URL', 'teatatu-homes' ); ?></label>
				<input type="url" id="tth_video_url" name="tth_video_url" value="<?php echo esc_attr( $f['video_url'] ); ?>" />
			</div>
			<div class="tth-field tth-wide">
				<label for="tth_tour_url"><?php esc_html_e( 'Virtual tour URL', 'teatatu-homes' ); ?></label>
				<input type="url" id="tth_tour_url" name="tth_tour_url" value="<?php echo esc_attr( $f['tour_url'] ); ?>" />
			</div>
		</div>
		<div class="tth-field tth-full">
			<label for="tth_documents"><?php esc_html_e( 'Documents — one per line as "Label | URL" (e.g. LIM report | https://…)', 'teatatu-homes' ); ?></label>
			<textarea id="tth_documents" name="tth_documents" rows="3" class="large-text code"><?php echo esc_textarea( implode( "\n", $docs ) ); ?></textarea>
		</div>
	</fieldset>

	<fieldset class="tth-section">
		<legend><?php esc_html_e( 'Open homes / viewings', 'teatatu-homes' ); ?></legend>
		<table class="tth-opens">
			<thead><tr><th><?php esc_html_e( 'Date', 'teatatu-homes' ); ?></th><th><?php esc_html_e( 'Starts', 'teatatu-homes' ); ?></th><th><?php esc_html_e( 'Ends', 'teatatu-homes' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $opens as $i => $slot ) : ?>
				<tr>
					<td><input type="date" name="tth_open[<?php echo (int) $i; ?>][date]" aria-label="<?php esc_attr_e( 'Date', 'teatatu-homes' ); ?>" value="<?php echo esc_attr( teatatu_homes_local( $slot['start'], 'Y-m-d' ) ); ?>" /></td>
					<td><input type="time" name="tth_open[<?php echo (int) $i; ?>][start]" aria-label="<?php esc_attr_e( 'Starts', 'teatatu-homes' ); ?>" value="<?php echo esc_attr( teatatu_homes_local( $slot['start'], 'H:i' ) ); ?>" /></td>
					<td><input type="time" name="tth_open[<?php echo (int) $i; ?>][end]" aria-label="<?php esc_attr_e( 'Ends', 'teatatu-homes' ); ?>" value="<?php echo esc_attr( teatatu_homes_local( $slot['end'], 'H:i' ) ); ?>" /></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description"><?php esc_html_e( 'Clear a row to remove it. Past open homes drop off automatically. Save to get more empty rows.', 'teatatu-homes' ); ?></p>
	</fieldset>

	<fieldset class="tth-section">
		<legend><?php esc_html_e( 'Agent / property manager', 'teatatu-homes' ); ?></legend>
		<div class="tth-grid">
			<div class="tth-field"><label for="tth_agent_name"><?php esc_html_e( 'Name', 'teatatu-homes' ); ?></label><input type="text" id="tth_agent_name" name="tth_agent_name" value="<?php echo esc_attr( $f['agent_name'] ); ?>" /></div>
			<div class="tth-field"><label for="tth_agent_phone"><?php esc_html_e( 'Phone', 'teatatu-homes' ); ?></label><input type="tel" id="tth_agent_phone" name="tth_agent_phone" value="<?php echo esc_attr( $f['agent_phone'] ); ?>" /></div>
			<div class="tth-field"><label for="tth_agent_email"><?php esc_html_e( 'Email (enquiries are sent here, never shown)', 'teatatu-homes' ); ?></label><input type="email" id="tth_agent_email" name="tth_agent_email" value="<?php echo esc_attr( $f['agent_email'] ); ?>" /></div>
			<div class="tth-field tth-wide"><label for="tth_agent_photo_url"><?php esc_html_e( 'Photo URL', 'teatatu-homes' ); ?></label><input type="url" id="tth_agent_photo_url" name="tth_agent_photo_url" value="<?php echo esc_attr( $f['agent_photo_url'] ); ?>" /></div>
		</div>
		<div class="tth-grid">
			<div class="tth-field tth-wide"><label for="tth_external_url"><?php esc_html_e( 'Original listing URL', 'teatatu-homes' ); ?></label><input type="url" id="tth_external_url" name="tth_external_url" value="<?php echo esc_attr( $f['external_url'] ); ?>" /></div>
			<div class="tth-field"><label for="tth_external_ref"><?php esc_html_e( 'Agency reference', 'teatatu-homes' ); ?></label><input type="text" id="tth_external_ref" name="tth_external_ref" value="<?php echo esc_attr( $f['external_ref'] ); ?>" /></div>
		</div>
		<p class="description"><?php esc_html_e( 'Choose the agency in the Agencies box. Its phone and email are used when these are blank.', 'teatatu-homes' ); ?></p>
	</fieldset>
	</div>
	<?php
}

/**
 * Turns the meta box's POST data into teatatu_homes_save_fields() input.
 *
 * @param array $src Unslashed POST data.
 * @return array
 */
function teatatu_homes_fields_from_form( $src ) {
	$fields = array();
	$simple = array( 'status', 'sale_method', 'price', 'price_text', 'title_type', 'bond', 'pets', 'furnished', 'bedrooms', 'bathrooms', 'car_parks', 'floor_area', 'land_area', 'floor_plan_url', 'video_url', 'tour_url', 'agent_name', 'agent_phone', 'agent_email', 'agent_photo_url', 'external_url', 'external_ref' );
	foreach ( $simple as $field ) {
		if ( isset( $src[ 'tth_' . $field ] ) ) {
			$fields[ $field ] = $src[ 'tth_' . $field ];
		}
	}
	if ( isset( $src['tth_listing'] ) ) {
		$fields['listing'] = $src['tth_listing'];
	}
	if ( isset( $src['tth_property_type'] ) ) {
		$fields['property_type'] = $src['tth_property_type'];
	}
	$fields['healthy_homes'] = ! empty( $src['tth_healthy_homes'] );
	if ( isset( $src['tth_method_date'] ) ) {
		$date                  = trim( (string) $src['tth_method_date'] );
		$time                  = trim( (string) ( $src['tth_method_time'] ?? '' ) );
		$fields['method_date'] = '' === $date ? '' : trim( $date . ' ' . $time );
	}
	if ( isset( $src['tth_available_from'] ) ) {
		$fields['available_from'] = $src['tth_available_from'];
	}
	$photos = teatatu_homes_photos_from_form( $src );
	if ( null !== $photos ) {
		$fields['photos'] = $photos;
	}
	if ( isset( $src['tth_documents'] ) ) {
		$fields['documents'] = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $src['tth_documents'] ) as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}
			$bits                  = array_map( 'trim', explode( '|', $line, 2 ) );
			$fields['documents'][] = array( 'label' => $bits[0], 'url' => $bits[1] ?? '' );
		}
	}
	if ( isset( $src['tth_open'] ) && is_array( $src['tth_open'] ) ) {
		$fields['open_homes'] = array();
		foreach ( $src['tth_open'] as $row ) {
			$date = trim( (string) ( $row['date'] ?? '' ) );
			if ( '' === $date ) {
				continue;
			}
			$start                  = trim( (string) ( $row['start'] ?? '' ) );
			$end                    = trim( (string) ( $row['end'] ?? '' ) );
			$fields['open_homes'][] = array(
				'start' => $date . ' ' . ( '' === $start ? '00:00' : $start ),
				'end'   => '' === $end ? '' : $date . ' ' . $end,
			);
		}
	}
	$address = teatatu_homes_address_from_form( $src );
	if ( $address ) {
		$fields['address'] = $address;
	}
	return $fields;
}

add_action( 'save_post_teatatu_home', 'teatatu_homes_save_meta_box', 10, 2 );

/**
 * Saves the meta box through the shared validated writer.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 */
function teatatu_homes_save_meta_box( $post_id, $post ) {
	if ( ! isset( $_POST['teatatu_homes_meta_box_nonce'] ) ||
		! wp_verify_nonce( wp_unslash( $_POST['teatatu_homes_meta_box_nonce'] ), 'teatatu_homes_save_meta_box' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$result = teatatu_homes_save_fields( $post_id, teatatu_homes_fields_from_form( wp_unslash( $_POST ) ) );
	if ( is_wp_error( $result ) ) {
		set_transient( 'teatatu_homes_notice_' . get_current_user_id(), array( 'error', $result->get_error_message() ), 45 );
		return;
	}

	if ( 'auto-draft' === $post->post_status ) {
		return;
	}
	$missing = teatatu_homes_missing_fields( $post_id );
	if ( $missing ) {
		/* translators: %s: comma-separated list of missing fields. */
		$message = sprintf( __( 'This listing still needs: %s.', 'teatatu-homes' ), implode( ', ', $missing ) );
		set_transient( 'teatatu_homes_notice_' . get_current_user_id(), array( 'warning', $message ), 45 );
	}
}

add_action( 'save_post_teatatu_home', 'teatatu_homes_refresh_on_save', 20 );

/**
 * Every save (quick edit, REST, meta box) keeps the Area and the derived
 * open-home index current.
 *
 * @param int $post_id Post ID.
 */
function teatatu_homes_refresh_on_save( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || 'auto-draft' === get_post_status( $post_id ) ) {
		return;
	}
	teatatu_homes_assign_area( $post_id );
	teatatu_homes_refresh_derived( $post_id );
}

add_action( 'admin_notices', 'teatatu_homes_show_meta_box_notice' );

/**
 * Shows the notice stashed by the meta box saver.
 */
function teatatu_homes_show_meta_box_notice() {
	$key    = 'teatatu_homes_notice_' . get_current_user_id();
	$notice = get_transient( $key );
	if ( ! $notice ) {
		return;
	}
	delete_transient( $key );
	printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $notice[0] ), esc_html( $notice[1] ) );
}
