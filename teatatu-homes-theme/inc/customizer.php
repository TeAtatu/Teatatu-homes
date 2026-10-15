<?php
/**
 * Customizer: presentation settings only (hero photo, call-to-action
 * links, footer line). Listing data lives in the Teatatu Homes plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'customize_register', 'tth_customize_register' );

/**
 * Registers the "Te Atatū Homes" Customizer section.
 *
 * @param WP_Customize_Manager $wp_customize Manager.
 */
function tth_customize_register( $wp_customize ) {
	$wp_customize->add_section(
		'tth_theme',
		array(
			'title'    => __( 'Te Atatū Homes', 'teatatu-homes-theme' ),
			'priority' => 30,
		)
	);

	$settings = array(
		'tth_hero_image'     => array( __( 'Home page photo URL', 'teatatu-homes-theme' ), 'url', 'esc_url_raw' ),
		'tth_hero_image_alt' => array( __( 'Home page photo description (alt text)', 'teatatu-homes-theme' ), 'text', 'sanitize_text_field' ),
		'tth_appraisal_url'  => array( __( '"Get an appraisal" link', 'teatatu-homes-theme' ), 'url', 'esc_url_raw' ),
		'tth_list_url'       => array( __( '"List a home" / "List a rental" link', 'teatatu-homes-theme' ), 'url', 'esc_url_raw' ),
		'tth_footer_text'    => array( __( 'Footer line', 'teatatu-homes-theme' ), 'text', 'sanitize_text_field' ),
	);
	foreach ( $settings as $id => $def ) {
		$wp_customize->add_setting( $id, array( 'default' => '', 'sanitize_callback' => $def[2] ) );
		$wp_customize->add_control( $id, array( 'label' => $def[0], 'section' => 'tth_theme', 'type' => $def[1] ) );
	}
}
