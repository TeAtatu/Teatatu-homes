<?php
/**
 * Listing enquiries. A theme renders the form (any markup it likes) using
 * teatatu_homes_enquiry_action_url() and teatatu_homes_enquiry_hidden_fields(),
 * with inputs named `name`, `email`, `phone` and `message`. This file
 * validates the submission and emails it to the listing's agent, else the
 * agency, else the configured fallback, else the site admin. Enquiries are
 * not stored. Recipient addresses never reach the browser.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Form action URL (always the plain admin-post.php).
 *
 * @return string
 */
function teatatu_homes_enquiry_action_url() {
	return admin_url( 'admin-post.php' );
}

/**
 * Prints the hidden fields an enquiry form needs: action, home ID, nonce
 * and a honeypot (left empty by people, visually hidden by the theme
 * through the `teatatu-homes-hp` class).
 *
 * @param int $post_id Home ID.
 */
function teatatu_homes_enquiry_hidden_fields( $post_id ) {
	echo '<input type="hidden" name="action" value="teatatu_homes_enquiry" />';
	printf( '<input type="hidden" name="home_id" value="%d" />', (int) $post_id );
	wp_nonce_field( 'teatatu_homes_enquiry_' . (int) $post_id, 'teatatu_homes_enquiry_nonce', false );
	echo '<p class="teatatu-homes-hp" aria-hidden="true"><label>Leave this empty <input type="text" name="website" value="" tabindex="-1" autocomplete="off" /></label></p>';
}

/**
 * Result of the last submission for the current request, from the
 * redirect's query string: 'sent', 'invalid', 'limited', 'failed' or ''.
 *
 * @return string
 */
function teatatu_homes_enquiry_status() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
	$status = isset( $_GET['enquiry'] ) ? sanitize_key( wp_unslash( $_GET['enquiry'] ) ) : '';
	return in_array( $status, array( 'sent', 'invalid', 'limited', 'failed' ), true ) ? $status : '';
}

/**
 * Human message for an enquiry status.
 *
 * @param string $status Status.
 * @return string
 */
function teatatu_homes_enquiry_message( $status ) {
	$messages = array(
		'sent'    => __( 'Thanks — your enquiry has been sent. The agent will be in touch.', 'teatatu-homes' ),
		'invalid' => __( 'Please give your name, a valid email address and a message.', 'teatatu-homes' ),
		'limited' => __( 'You have sent several enquiries recently. Please try again later.', 'teatatu-homes' ),
		'failed'  => __( 'Sorry, your enquiry could not be sent. Please try again or phone the agent.', 'teatatu-homes' ),
	);
	return $messages[ $status ] ?? '';
}

/**
 * Who receives enquiries for a home.
 *
 * @param int $post_id Home ID.
 * @return string Email address.
 */
function teatatu_homes_enquiry_recipient( $post_id ) {
	$fields = teatatu_homes_get_fields( $post_id );
	if ( is_email( $fields['agent_email'] ) ) {
		return $fields['agent_email'];
	}
	$agency = teatatu_homes_agency_data( teatatu_homes_first_term( $post_id, 'teatatu_home_agency' ) );
	if ( $agency && is_email( $agency['email'] ) ) {
		return $agency['email'];
	}
	$fallback = teatatu_homes_get_option( 'teatatu_homes_enquiry_fallback_email', '' );
	return is_email( $fallback ) ? $fallback : get_option( 'admin_email' );
}

add_action( 'admin_post_teatatu_homes_enquiry', 'teatatu_homes_handle_enquiry' );
add_action( 'admin_post_nopriv_teatatu_homes_enquiry', 'teatatu_homes_handle_enquiry' );

/**
 * Handles an enquiry submission.
 */
function teatatu_homes_handle_enquiry() {
	$post_id = isset( $_POST['home_id'] ) ? absint( $_POST['home_id'] ) : 0;
	if ( ! $post_id || 'teatatu_home' !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
		wp_die( esc_html__( 'That listing is not available.', 'teatatu-homes' ), 404 );
	}
	$back = function ( $status ) use ( $post_id ) {
		wp_safe_redirect( add_query_arg( 'enquiry', $status, get_permalink( $post_id ) ) . '#enquire' );
		exit;
	};

	if ( ! isset( $_POST['teatatu_homes_enquiry_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['teatatu_homes_enquiry_nonce'] ), 'teatatu_homes_enquiry_' . $post_id ) ) {
		$back( 'failed' );
	}
	// Honeypot: pretend success so bots learn nothing.
	if ( ! empty( $_POST['website'] ) ) {
		$back( 'sent' );
	}

	$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
	$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
	if ( '' === $name || ! is_email( $email ) || '' === trim( $message ) || mb_strlen( $message ) > 5000 ) {
		$back( 'invalid' );
	}

	$ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$rl_key  = 'teatatu_homes_enq_' . md5( $ip . '|' . wp_salt( 'nonce' ) );
	$count   = (int) get_transient( $rl_key );
	if ( $count >= 5 ) {
		$back( 'limited' );
	}
	set_transient( $rl_key, $count + 1, HOUR_IN_SECONDS );

	$item = teatatu_homes_normalize_item( $post_id );
	/* translators: 1: listing address, 2: site name. */
	$subject = sprintf( __( 'Enquiry: %1$s (%2$s)', 'teatatu-homes' ), teatatu_homes_format_address( teatatu_homes_get_address( $post_id ), 'street' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
	$lines   = array(
		/* translators: %s: listing title. */
		sprintf( __( 'Listing: %s', 'teatatu-homes' ), $item['title'] ),
		/* translators: %s: address. */
		sprintf( __( 'Address: %s', 'teatatu-homes' ), teatatu_homes_private_address( $post_id ) ),
		/* translators: %s: price line. */
		sprintf( __( 'Price: %s', 'teatatu-homes' ), $item['price_label'] ),
		$item['permalink'],
		'',
		/* translators: %s: name. */
		sprintf( __( 'From: %s', 'teatatu-homes' ), $name ),
		/* translators: %s: email. */
		sprintf( __( 'Email: %s', 'teatatu-homes' ), $email ),
		/* translators: %s: phone. */
		$phone ? sprintf( __( 'Phone: %s', 'teatatu-homes' ), $phone ) : '',
		'',
		$message,
	);
	$headers = array( 'Reply-To: ' . str_replace( array( "\r", "\n" ), '', $name ) . ' <' . $email . '>' );
	$sent    = wp_mail( teatatu_homes_enquiry_recipient( $post_id ), $subject, implode( "\n", $lines ), $headers );

	/**
	 * Fires after an enquiry is handled (e.g. to log it to a CRM).
	 *
	 * @param int   $post_id Home ID.
	 * @param array $enquiry {name, email, phone, message}.
	 * @param bool  $sent    Whether wp_mail() reported success.
	 */
	do_action( 'teatatu_homes_enquiry_sent', $post_id, compact( 'name', 'email', 'phone', 'message' ), $sent );

	$back( $sent ? 'sent' : 'failed' );
}
