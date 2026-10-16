<?php
/**
 * Teatatu Homes Theme — display only.
 *
 * Every listing value shown by this theme comes from the Teatatu Homes
 * plugin: listings are read through teatatu_homes_normalize_item(), search
 * filters through teatatu_homes_current_filters() and friends, and the
 * enquiry form posts to the plugin's handler. The theme stores no listing
 * data and joins no address fields itself. Without the plugin it shows a
 * notice instead of listings.
 *
 * Styling follows the Te Atatū Local design system (assets/css/te-atatu-local*.css):
 * <html data-site="homes"> gives every component the Harakeke green accent.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TTH_THEME_VERSION', '1.2.0' );

require_once get_template_directory() . '/inc/template-tags.php';
require_once get_template_directory() . '/inc/customizer.php';

/**
 * Whether the Teatatu Homes plugin is active.
 *
 * @return bool
 */
function tth_plugin_ready() {
	return function_exists( 'teatatu_homes_normalize_item' );
}

add_action( 'after_setup_theme', 'tth_setup' );

/**
 * Theme supports and menus.
 */
function tth_setup() {
	load_theme_textdomain( 'teatatu-homes-theme', get_template_directory() . '/languages' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support( 'responsive-embeds' );
	register_nav_menus(
		array(
			'primary' => __( 'Header menu', 'teatatu-homes-theme' ),
			'footer'  => __( 'Footer menu', 'teatatu-homes-theme' ),
		)
	);
}

add_action( 'wp_enqueue_scripts', 'tth_assets' );

/**
 * Fonts, styles and the small progressive-enhancement script.
 */
function tth_assets() {
	$css = get_template_directory_uri() . '/assets/css/';
	wp_enqueue_style( 'tal-font', 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap', array(), null );
	wp_enqueue_style( 'tal-tokens', $css . 'te-atatu-local-tokens.css', array(), TTH_THEME_VERSION );
	wp_enqueue_style( 'tal-components', $css . 'te-atatu-local.css', array( 'tal-tokens', 'tal-font' ), TTH_THEME_VERSION );
	wp_enqueue_style( 'tth-style', get_stylesheet_uri(), array( 'tal-components' ), TTH_THEME_VERSION );
	wp_enqueue_script( 'tth-theme', get_template_directory_uri() . '/assets/js/theme.js', array(), TTH_THEME_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	wp_localize_script(
		'tth-theme',
		'tthTheme',
		array(
			'priceSteps' => tth_plugin_ready() ? array( 'sale' => tth_price_options( 'sale' ), 'rent' => tth_price_options( 'rent' ) ) : array(),
			'copied'     => __( 'Link copied', 'teatatu-homes-theme' ),
			'showPhone'  => __( 'Show phone number', 'teatatu-homes-theme' ),
		)
	);
}

/**
 * Loads Leaflet (OpenStreetMap tiles) for a page that shows a map.
 * Called from templates only when there is at least one point to show.
 */
function tth_enqueue_map() {
	wp_enqueue_style( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
	wp_enqueue_script( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', array( 'in_footer' => true ) );
	wp_enqueue_script( 'tth-map', get_template_directory_uri() . '/assets/js/map.js', array( 'leaflet' ), TTH_THEME_VERSION, array( 'in_footer' => true ) );
}

add_action( 'wp_head', 'tth_js_class', 1 );

/**
 * Marks the document as script-capable before paint, so tab panels that
 * JS will manage don't flash.
 */
function tth_js_class() {
	echo "<script>document.documentElement.classList.add('js');</script>\n";
}

add_filter( 'template_include', 'tth_homes_taxonomy_template' );

/**
 * Every Homes taxonomy archive (Listing Type, Area, Property Type, Feature,
 * Agency) is a filtered search, so it uses the Homes archive template.
 *
 * @param string $template Template path.
 * @return string
 */
function tth_homes_taxonomy_template( $template ) {
	if ( tth_plugin_ready() && is_tax( array_keys( teatatu_homes_taxonomy_defs() ) ) ) {
		$archive = locate_template( 'archive-teatatu_home.php' );
		if ( $archive ) {
			return $archive;
		}
	}
	return $template;
}

add_action( 'admin_notices', 'tth_plugin_missing_notice' );

/**
 * Tells admins the theme needs the plugin.
 */
function tth_plugin_missing_notice() {
	if ( tth_plugin_ready() || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p>' . esc_html__( 'The Teatatu Homes theme displays listings from the Teatatu Homes plugin. Activate the plugin to show homes.', 'teatatu-homes-theme' ) . '</p></div>';
}

add_filter( 'document_title_parts', 'tth_title_parts' );

/**
 * A clearer page title on search results ("Homes for rent in Matipo").
 *
 * @param array $parts Title parts.
 * @return array
 */
function tth_title_parts( $parts ) {
	if ( tth_plugin_ready() && ( is_post_type_archive( 'teatatu_home' ) || is_tax( array_keys( teatatu_homes_taxonomy_defs() ) ) ) ) {
		$parts['title'] = tth_results_heading( teatatu_homes_current_filters() );
	}
	return $parts;
}

add_filter( 'language_attributes', 'tth_site_attribute' );

/**
 * Marks the document as the Homes site of the Te Atatū Local network, so the
 * design system's components take the Harakeke green accent.
 *
 * @param string $output Attributes for <html>.
 * @return string
 */
function tth_site_attribute( $output ) {
	return $output . ' data-site="homes"';
}

add_filter( 'body_class', 'tth_body_class' );

/**
 * Applies the design system's root class (font, focus rings, reduced motion).
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function tth_body_class( $classes ) {
	$classes[] = 'tal-root';
	return $classes;
}

/**
 * The five Te Atatū Local sites for the network strip, in the design
 * system's fixed order. Filter `tth_network_sites` to change addresses
 * (e.g. on a staging network).
 *
 * @return array[] key => [label, url]
 */
function tth_network_sites() {
	return apply_filters(
		'tth_network_sites',
		array(
			'main'   => array( __( 'Te Atatū', 'teatatu-homes-theme' ), 'https://teatatu.nz/' ),
			'events' => array( __( 'Events', 'teatatu-homes-theme' ), 'https://events.teatatu.nz/' ),
			'news'   => array( __( 'News', 'teatatu-homes-theme' ), 'https://news.teatatu.nz/' ),
			'homes'  => array( __( 'Homes', 'teatatu-homes-theme' ), home_url( '/' ) ),
			'market' => array( __( 'Market', 'teatatu-homes-theme' ), 'https://market.teatatu.nz/' ),
		)
	);
}
