<?php
/**
 * Self-hosted GitHub-release-based updater. Teatatu Homes isn't on
 * WordPress.org, so this plugs into the normal wp-admin "update available"
 * UI using only wp_remote_get() and core update filters — no bundled
 * update library.
 *
 * One GitHub release (latest, tag vX.Y.Z) ships both the plugin and the
 * theme (teatatu-homes-theme has its own updater in inc/updater.php). The
 * tag only points at the release; this updater compares the *plugin's own*
 * Version header in teatatu-homes/teatatu-homes.php at that tag, so a
 * release that only changes the theme never offers a plugin update.
 *
 * Installing: automatically only when "Enable auto-updates" is on for the
 * plugin; otherwise the notice shows and an admin clicks Update now.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads a header from this plugin's own file, with a tiny static cache.
 *
 * @param string $key 'Name', 'Description', 'RequiresWP', or 'RequiresPHP'.
 * @return string
 */
function teatatu_homes_get_plugin_header( $key ) {
	static $headers = null;
	if ( null === $headers ) {
		$headers = get_file_data(
			TEATATU_HOMES_FILE,
			array(
				'Name'        => 'Plugin Name',
				'Description' => 'Description',
				'RequiresWP'  => 'Requires at least',
				'RequiresPHP' => 'Requires PHP',
			)
		);
	}
	return isset( $headers[ $key ] ) ? $headers[ $key ] : '';
}

/**
 * Gets the configured `owner/repo` GitHub slug.
 *
 * @return string
 */
function teatatu_homes_get_github_repo() {
	$repo = teatatu_homes_get_option( 'teatatu_homes_github_repo', 'TeAtatu/Teatatu-homes' );
	return $repo ? $repo : 'TeAtatu/Teatatu-homes';
}

/**
 * Fetches (and caches) the latest GitHub release for the configured repo.
 *
 * @param bool $force Bypass the cache.
 * @return array|false Decoded release data, or false on failure.
 */
function teatatu_homes_fetch_latest_release( $force = false ) {
	$cache_key = 'teatatu_homes_github_release';

	if ( ! $force ) {
		$cached = get_site_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}
	}

	$repo = teatatu_homes_get_github_repo();

	$headers = array(
		'User-Agent' => 'Teatatu-Homes-Plugin/' . TEATATU_HOMES_VERSION . ' (' . home_url() . ')',
		'Accept'     => 'application/vnd.github+json',
	);
	$token = teatatu_homes_get_option( 'teatatu_homes_github_token', '' );
	if ( $token ) {
		$headers['Authorization'] = 'token ' . $token;
	}

	$response = wp_remote_get(
		"https://api.github.com/repos/{$repo}/releases/latest",
		array(
			'headers' => $headers,
			'timeout' => 15,
		)
	);

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return false;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $body ) ) {
		return false;
	}

	// The plugin's own version at this release (not the tag — see above).
	$body['teatatu_homes_version'] = ! empty( $body['tag_name'] ) ? teatatu_homes_fetch_remote_version( $repo, $body['tag_name'], $headers ) : '';

	set_site_transient( $cache_key, $body, 12 * HOUR_IN_SECONDS );
	return $body;
}

/**
 * Reads the Version header of teatatu-homes/teatatu-homes.php at a tag.
 *
 * @param string $repo    owner/repo.
 * @param string $tag     Release tag.
 * @param array  $headers Request headers (User-Agent, token).
 * @return string Version, or '' if it couldn't be read.
 */
function teatatu_homes_fetch_remote_version( $repo, $tag, $headers ) {
	$headers['Accept'] = 'application/vnd.github.raw+json';
	$response          = wp_remote_get(
		"https://api.github.com/repos/{$repo}/contents/teatatu-homes/teatatu-homes.php?ref=" . rawurlencode( $tag ),
		array(
			'headers' => $headers,
			'timeout' => 15,
		)
	);
	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return '';
	}
	return preg_match( '/^[ \t\/*#@]*Version:\s*([0-9A-Za-z.\-+]+)/mi', substr( wp_remote_retrieve_body( $response ), 0, 8192 ), $m ) ? $m[1] : '';
}

/**
 * Release info for this plugin: the plugin's version at the latest release
 * and the download, or null when there's nothing usable.
 *
 * @return array|null {version, package, changelog, url}
 */
function teatatu_homes_release_info() {
	$release = teatatu_homes_fetch_latest_release();
	if ( ! $release || empty( $release['teatatu_homes_version'] ) || empty( $release['zipball_url'] ) ) {
		return null;
	}
	return array(
		'version'   => $release['teatatu_homes_version'],
		'package'   => $release['zipball_url'],
		'changelog' => isset( $release['body'] ) ? (string) $release['body'] : '',
		'url'       => isset( $release['html_url'] ) ? $release['html_url'] : 'https://github.com/' . teatatu_homes_get_github_repo(),
	);
}

add_filter( 'pre_set_site_transient_update_plugins', 'teatatu_homes_check_for_update' );

/**
 * Populates the update_plugins transient when a newer GitHub release exists.
 *
 * @param object $transient Existing update_plugins transient.
 * @return object
 */
function teatatu_homes_check_for_update( $transient ) {
	if ( empty( $transient->checked ) ) {
		return $transient;
	}

	$info = teatatu_homes_release_info();
	if ( ! $info || ! version_compare( $info['version'], TEATATU_HOMES_VERSION, '>' ) ) {
		return $transient;
	}
	$remote_version = $info['version'];

	$item = array(
		'id'            => 'teatatu-homes/teatatu-homes.php',
		'slug'          => 'teatatu-homes',
		'plugin'        => TEATATU_HOMES_BASENAME,
		'new_version'   => $remote_version,
		'url'           => 'https://github.com/' . teatatu_homes_get_github_repo(),
		'package'       => $info['package'],
		'requires'      => teatatu_homes_get_plugin_header( 'RequiresWP' ),
		'requires_php'  => teatatu_homes_get_plugin_header( 'RequiresPHP' ),
		'icons'         => array(),
		'banners'       => array(),
	);

	$transient->response[ TEATATU_HOMES_BASENAME ] = (object) $item;
	if ( isset( $transient->no_update[ TEATATU_HOMES_BASENAME ] ) ) {
		unset( $transient->no_update[ TEATATU_HOMES_BASENAME ] );
	}

	return $transient;
}

add_filter( 'plugins_api', 'teatatu_homes_plugins_api', 10, 3 );

/**
 * Powers the "View version details" popup from the latest GitHub release.
 *
 * @param false|object|array $result The result object/array (default false).
 * @param string              $action  The type of information being requested.
 * @param object              $args    Plugin API arguments.
 * @return false|object
 */
function teatatu_homes_plugins_api( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || empty( $args->slug ) || 'teatatu-homes' !== $args->slug ) {
		return $result;
	}

	$info           = teatatu_homes_release_info();
	$remote_version = $info ? $info['version'] : TEATATU_HOMES_VERSION;
	$changelog      = $info ? $info['changelog'] : '';

	$info = array(
		'name'          => teatatu_homes_get_plugin_header( 'Name' ),
		'slug'          => 'teatatu-homes',
		'version'       => $remote_version,
		'author'        => '<a href="https://github.com/TeAtatu">TeAtatu</a>',
		'homepage'      => 'https://github.com/' . teatatu_homes_get_github_repo(),
		'requires'      => teatatu_homes_get_plugin_header( 'RequiresWP' ),
		'requires_php'  => teatatu_homes_get_plugin_header( 'RequiresPHP' ),
		'download_link' => $info ? $info['package'] : '',
		'sections'      => array(
			'description' => wp_kses_post( teatatu_homes_get_plugin_header( 'Description' ) ),
			'changelog'   => $changelog ? wpautop( wp_kses_post( $changelog ) ) : '<p>' . esc_html__( 'No changelog available.', 'teatatu-homes' ) . '</p>',
		),
	);

	return (object) $info;
}

add_filter( 'upgrader_source_selection', 'teatatu_homes_fix_source_dir', 10, 4 );

/**
 * Makes the downloaded release install as plugins/teatatu-homes/.
 *
 * GitHub's release zipball holds the whole repository
 * (`TeAtatu-Teatatu-homes-<sha>/` with README.md, teatatu-homes/ and
 * teatatu-homes-theme/ inside), so renaming that folder would bury the
 * plugin one level too deep and WordPress would deactivate it. When the
 * plugin sits in a `teatatu-homes/` subfolder, that subfolder is moved up
 * and used as the source; a zip that already has the plugin at its top
 * level (e.g. a release asset) is just renamed.
 *
 * @param string      $source        Path to the extracted, unrenamed source.
 * @param string      $remote_source Path to the parent temp directory.
 * @param WP_Upgrader $upgrader      Upgrader instance.
 * @param array       $hook_extra    Extra arguments, including 'plugin'.
 * @return string|WP_Error
 */
function teatatu_homes_fix_source_dir( $source, $remote_source, $upgrader, $hook_extra = array() ) {
	global $wp_filesystem;

	if ( empty( $hook_extra['plugin'] ) || TEATATU_HOMES_BASENAME !== $hook_extra['plugin'] ) {
		return $source;
	}
	if ( ! $wp_filesystem ) {
		return $source;
	}

	$source  = trailingslashit( $source );
	$desired = trailingslashit( $remote_source ) . 'teatatu-homes/';
	$main    = 'teatatu-homes.php';

	if ( $wp_filesystem->exists( $source . 'teatatu-homes/' . $main ) ) {
		// Repository zip: lift the plugin subfolder out.
		$from = $source . 'teatatu-homes/';
	} elseif ( $wp_filesystem->exists( $source . $main ) ) {
		// Plugin already at the top level: just rename the folder.
		$from = $source;
	} else {
		return new WP_Error( 'teatatu_homes_bad_package', __( 'The downloaded update does not contain the Teatatu Homes plugin. Nothing was changed.', 'teatatu-homes' ) );
	}

	if ( $from === $desired ) {
		return $desired;
	}
	if ( $wp_filesystem->move( untrailingslashit( $from ), untrailingslashit( $desired ), true ) ) {
		return $desired;
	}
	return new WP_Error( 'teatatu_homes_move_failed', __( 'Could not prepare the Teatatu Homes update. Nothing was changed.', 'teatatu-homes' ) );
}

add_filter( 'auto_update_plugin', 'teatatu_homes_auto_update_gate', PHP_INT_MAX, 2 );

/**
 * Automatic updates happen only when "Enable auto-updates" is on for this
 * plugin (Plugins screen; Network Admin > Plugins on multisite). When it is
 * off, the "update available" notice still appears and an admin can click
 * Update now to install the latest release by hand.
 *
 * Runs last so another plugin or a filter can't force automatic updates on.
 * When WordPress only asks whether auto-updates are *forced* (it passes
 * null, to decide whether to lock the toggle), the value is left alone so
 * the Enable/Disable link stays clickable.
 *
 * @param bool|null $update Whether to update automatically (null = forced-state check).
 * @param object    $item   The update offer.
 * @return bool|null
 */
function teatatu_homes_auto_update_gate( $update, $item ) {
	if ( null === $update || empty( $item->plugin ) || TEATATU_HOMES_BASENAME !== $item->plugin ) {
		return $update;
	}
	return in_array( TEATATU_HOMES_BASENAME, (array) get_site_option( 'auto_update_plugins', array() ), true );
}

add_action( 'admin_post_teatatu_homes_check_updates', 'teatatu_homes_handle_check_updates' );

/**
 * Handles the "Check for Updates Now" button: clears cached GitHub data and
 * the core update_plugins transient so the next admin page load re-checks.
 */
function teatatu_homes_handle_check_updates() {
	if ( ! isset( $_POST['teatatu_homes_check_updates_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['teatatu_homes_check_updates_nonce'] ), 'teatatu_homes_check_updates' ) ) {
		wp_die( esc_html__( 'Security check failed.', 'teatatu-homes' ) );
	}
	$cap = is_multisite() ? 'manage_network_options' : 'manage_options';
	if ( ! current_user_can( $cap ) ) {
		wp_die( esc_html__( 'You do not have permission to do that.', 'teatatu-homes' ), 403 );
	}

	delete_site_transient( 'teatatu_homes_github_release' );
	delete_site_transient( 'update_plugins' );

	$redirect_base = is_multisite() ? network_admin_url( 'settings.php' ) : admin_url( 'options-general.php' );
	wp_safe_redirect( add_query_arg( array( 'page' => 'teatatu-homes-settings', 'teatatu_homes_settings_saved' => '1' ), $redirect_base ) );
	exit;
}

add_filter( 'http_request_args', 'teatatu_homes_authorize_download', 10, 2 );

/**
 * Sends the GitHub token (if one is set) when downloading this repo's
 * release zip, so updates also work from a private repository.
 *
 * @param array  $args Request arguments.
 * @param string $url  Request URL.
 * @return array
 */
function teatatu_homes_authorize_download( $args, $url ) {
	$token = teatatu_homes_get_option( 'teatatu_homes_github_token', '' );
	if ( $token && 0 === strpos( $url, 'https://api.github.com/repos/' . teatatu_homes_get_github_repo() . '/zipball/' ) ) {
		$args['headers']                  = isset( $args['headers'] ) ? (array) $args['headers'] : array();
		$args['headers']['Authorization'] = 'token ' . $token;
	}
	return $args;
}

add_action( 'load-update-core.php', 'teatatu_homes_force_check' );

/**
 * Dashboard > Updates > "Check again" skips the 12-hour release cache.
 */
function teatatu_homes_force_check() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only cache clear, same as core's force-check.
	if ( ! empty( $_GET['force-check'] ) && current_user_can( 'update_plugins' ) ) {
		delete_site_transient( 'teatatu_homes_github_release' );
	}
}
