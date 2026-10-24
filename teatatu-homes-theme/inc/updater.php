<?php
/**
 * Self-hosted GitHub-release-based updater for the theme, mirroring the
 * Teatatu Homes plugin's updater (teatatu-homes/includes/updater.php).
 *
 * Releases: the theme is released separately from the plugin, from the same
 * repository, with tags `teatatu-homes-theme-v<version>` (plugin releases use
 * `teatatu-homes-v<version>` and are ignored here). So the theme can be
 * edited, versioned and released without touching the plugin.
 *
 * Installing: automatically only when "Enable auto-updates" is on for this
 * theme (Appearance > Themes; Network Admin > Themes on multisite).
 * Otherwise the "update available" notice shows and an admin clicks
 * Update now. The GitHub repository and token come from the plugin's
 * settings (Settings > Teatatu Homes) when the plugin is active.
 *
 * Loading: WordPress only runs a theme's functions.php on sites where the
 * theme is active, but Network Admin > Themes, update checks and WP-Cron
 * run on the main site. The Teatatu Homes plugin therefore carries its own
 * copy of this logic (teatatu-homes/includes/theme-updater.php) that runs
 * everywhere; this file is only the fallback when the plugin is inactive.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// The Teatatu Homes plugin runs these checks itself (includes/theme-updater.php),
// because it is loaded on every site and theme code isn't. This copy is the
// fallback for when the plugin isn't active. (The check must be on a function
// in another file: this file's own functions already exist before it runs.)
if ( function_exists( 'teatatu_homes_theme_updater_loaded' ) ) {
	return;
}

/**
 * This theme's folder name (its stylesheet slug) — taken from where this
 * file lives, not the current site's active theme.
 *
 * @return string
 */
function tth_updater_slug() {
	return basename( dirname( __DIR__ ) );
}

/**
 * Installed theme version.
 *
 * @return string
 */
function tth_updater_installed_version() {
	return (string) wp_get_theme( tth_updater_slug() )->get( 'Version' );
}

/**
 * `owner/repo` for updates: the plugin's setting when available.
 *
 * @return string
 */
function tth_updater_repo() {
	$repo = function_exists( 'teatatu_homes_get_option' ) ? teatatu_homes_get_option( 'teatatu_homes_github_repo', '' ) : '';
	return (string) apply_filters( 'tth_updater_repo', $repo ? $repo : 'TeAtatu/Teatatu-homes' );
}

/**
 * GitHub token (only needed for a private repository).
 *
 * @return string
 */
function tth_updater_token() {
	return function_exists( 'teatatu_homes_get_option' ) ? (string) teatatu_homes_get_option( 'teatatu_homes_github_token', '' ) : '';
}

/**
 * Request headers for GitHub.
 *
 * @param string $accept Accept header.
 * @return array
 */
function tth_updater_headers( $accept = 'application/vnd.github+json' ) {
	$headers = array(
		'User-Agent' => 'Teatatu-Homes-Theme/' . tth_updater_installed_version() . ' (' . home_url() . ')',
		'Accept'     => $accept,
	);
	$token = tth_updater_token();
	if ( $token ) {
		$headers['Authorization'] = 'token ' . $token;
	}
	return $headers;
}

/**
 * The theme's version and download at the latest release (cached 12h).
 *
 * @param bool $force Bypass the cache.
 * @return array|null {version, package, changelog, url, requires, requires_php}
 */
function tth_updater_release_info( $force = false ) {
	$cache_key = 'tth_theme_github_release';
	if ( ! $force ) {
		$cached = get_site_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached ? $cached : null;
		}
	}

	// The theme's own releases only: tags `teatatu-homes-theme-v<version>`.
	$repo     = tth_updater_repo();
	$response = wp_remote_get( "https://api.github.com/repos/{$repo}/releases?per_page=50", array( 'headers' => tth_updater_headers(), 'timeout' => 15 ) );
	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}
	$releases = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $releases ) ) {
		return null;
	}
	$release = null;
	$version = '';
	foreach ( $releases as $candidate ) {
		if ( ! is_array( $candidate ) || ! empty( $candidate['draft'] ) || ! empty( $candidate['prerelease'] ) || empty( $candidate['tag_name'] ) || empty( $candidate['zipball_url'] ) ) {
			continue;
		}
		if ( preg_match( '/^teatatu-homes-theme-v(\d[0-9A-Za-z.\-+]*)$/', $candidate['tag_name'], $m ) && ( '' === $version || version_compare( $m[1], $version, '>' ) ) ) {
			$release = $candidate;
			$version = $m[1];
		}
	}
	if ( ! $release ) {
		return null;
	}

	// Requirements from style.css at that tag (optional; the update still works without them).
	$requires = '';
	$requires_php = '';
	$style    = wp_remote_get(
		"https://api.github.com/repos/{$repo}/contents/teatatu-homes-theme/style.css?ref=" . rawurlencode( $release['tag_name'] ),
		array( 'headers' => tth_updater_headers( 'application/vnd.github.raw+json' ), 'timeout' => 15 )
	);
	if ( ! is_wp_error( $style ) && 200 === (int) wp_remote_retrieve_response_code( $style ) ) {
		$head         = substr( wp_remote_retrieve_body( $style ), 0, 8192 );
		$requires     = preg_match( '/^[ \t\/*#@]*Requires at least:\s*(.+)$/mi', $head, $m ) ? trim( $m[1] ) : '';
		$requires_php = preg_match( '/^[ \t\/*#@]*Requires PHP:\s*(.+)$/mi', $head, $m ) ? trim( $m[1] ) : '';
	}

	$info = array(
		'version'      => $version,
		'package'      => $release['zipball_url'],
		'changelog'    => isset( $release['body'] ) ? (string) $release['body'] : '',
		'url'          => isset( $release['html_url'] ) ? $release['html_url'] : 'https://github.com/' . $repo,
		'requires'     => $requires,
		'requires_php' => $requires_php,
	);
	set_site_transient( $cache_key, $info, 12 * HOUR_IN_SECONDS );
	return $info;
}

add_filter( 'pre_set_site_transient_update_themes', 'tth_updater_check' );

/**
 * Offers the update in wp-admin when the release has a newer theme version.
 *
 * @param object $transient update_themes transient.
 * @return object
 */
function tth_updater_check( $transient ) {
	if ( empty( $transient->checked ) ) {
		return $transient;
	}
	$slug      = tth_updater_slug();
	$installed = tth_updater_installed_version();
	$info      = tth_updater_release_info();
	$newer     = $info && version_compare( $info['version'], $installed, '>' );
	// Always listed (update or no_update): the Themes screen only offers
	// "Enable auto-updates" for a non-WordPress.org theme that appears in one.
	$item = array(
		'theme'        => $slug,
		'new_version'  => $newer ? $info['version'] : $installed,
		// "View version details" opens this in a modal; GitHub can't be framed, so it's our own page.
		'url'          => add_query_arg( array( 'action' => 'tth_theme_changelog' ), admin_url( 'admin-ajax.php' ) ),
		'package'      => $newer ? $info['package'] : '',
		'requires'     => $newer ? $info['requires'] : (string) wp_get_theme( $slug )->get( 'RequiresWP' ),
		'requires_php' => $newer ? $info['requires_php'] : (string) wp_get_theme( $slug )->get( 'RequiresPHP' ),
	);
	if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
		$transient->response = array();
	}
	if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
		$transient->no_update = array();
	}
	if ( $newer ) {
		$transient->response[ $slug ] = $item;
		unset( $transient->no_update[ $slug ] );
	} else {
		$transient->no_update[ $slug ] = $item;
		unset( $transient->response[ $slug ] );
	}
	return $transient;
}

add_action( 'wp_ajax_tth_theme_changelog', 'tth_updater_changelog' );

/**
 * The "View version details" modal: the release notes.
 */
function tth_updater_changelog() {
	if ( ! current_user_can( 'update_themes' ) ) {
		wp_die( esc_html__( 'You do not have permission to view this.', 'teatatu-homes-theme' ), 403 );
	}
	$info = tth_updater_release_info();
	echo '<!doctype html><html><head><meta charset="utf-8"><title>' . esc_html__( 'Teatatu Homes Theme', 'teatatu-homes-theme' ) . '</title>';
	echo '<style>body{font:15px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;margin:24px;color:#1d2327}h1{font-size:20px}</style></head><body>';
	if ( ! $info ) {
		echo '<p>' . esc_html__( 'Release details are not available right now.', 'teatatu-homes-theme' ) . '</p>';
	} else {
		/* translators: %s: version. */
		echo '<h1>' . esc_html( sprintf( __( 'Teatatu Homes Theme %s', 'teatatu-homes-theme' ), $info['version'] ) ) . '</h1>';
		echo $info['changelog'] ? wp_kses_post( wpautop( $info['changelog'] ) ) : '<p>' . esc_html__( 'No changelog available.', 'teatatu-homes-theme' ) . '</p>';
		echo '<p><a href="' . esc_url( $info['url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'View the release on GitHub', 'teatatu-homes-theme' ) . '</a></p>';
	}
	echo '</body></html>';
	exit;
}

add_filter( 'upgrader_source_selection', 'tth_updater_source_dir', 10, 4 );

/**
 * Makes the downloaded release install as themes/teatatu-homes-theme/.
 * GitHub's zipball holds the whole repository, so the
 * teatatu-homes-theme/ subfolder is lifted out; a zip that already has
 * the theme at its top level is just renamed.
 *
 * @param string      $source        Extracted source path.
 * @param string      $remote_source Parent temp directory.
 * @param WP_Upgrader $upgrader      Upgrader.
 * @param array       $hook_extra    Includes 'theme'.
 * @return string|WP_Error
 */
function tth_updater_source_dir( $source, $remote_source, $upgrader, $hook_extra = array() ) {
	global $wp_filesystem;
	$slug = tth_updater_slug();
	if ( empty( $hook_extra['theme'] ) || $slug !== $hook_extra['theme'] || ! $wp_filesystem ) {
		return $source;
	}

	$source  = trailingslashit( $source );
	$desired = trailingslashit( $remote_source ) . $slug . '/';

	if ( $wp_filesystem->exists( $source . 'teatatu-homes-theme/style.css' ) ) {
		$from = $source . 'teatatu-homes-theme/';
	} elseif ( $wp_filesystem->exists( $source . 'style.css' ) && $wp_filesystem->exists( $source . 'single-teatatu_home.php' ) ) {
		$from = $source;
	} else {
		return new WP_Error( 'tth_bad_package', __( 'The downloaded update does not contain the Teatatu Homes theme. Nothing was changed.', 'teatatu-homes-theme' ) );
	}

	if ( $from === $desired ) {
		return $desired;
	}
	if ( $wp_filesystem->move( untrailingslashit( $from ), untrailingslashit( $desired ), true ) ) {
		return $desired;
	}
	return new WP_Error( 'tth_move_failed', __( 'Could not prepare the Teatatu Homes theme update. Nothing was changed.', 'teatatu-homes-theme' ) );
}

add_action( 'init', 'tth_updater_ensure_registered', 30 );

/**
 * Keeps the theme in WordPress's theme update list, which is what makes the
 * "Enable / Disable auto-updates" link appear for it (Appearance > Themes;
 * Network Admin > Themes on multisite) — used only when the plugin isn't
 * active (with the plugin, it lists the theme on every check itself).
 *
 * WordPress rebuilds that list from scratch on every update check and only
 * runs the update code of the theme active on the site doing the check, so
 * a check run elsewhere drops this theme. When this theme is running and
 * finds itself missing, it re-saves the list, which runs tth_updater_check()
 * through core's own filter. Checked at most every ten minutes.
 */
function tth_updater_ensure_registered() {
	$last = (int) get_option( 'tth_updater_ensured', 0 );
	if ( time() - $last < 10 * MINUTE_IN_SECONDS ) {
		return;
	}
	update_option( 'tth_updater_ensured', time() );

	$slug      = tth_updater_slug();
	$transient = get_site_transient( 'update_themes' );
	if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
		return; // Not built yet: core's own check will run our filter.
	}
	if ( ! empty( $transient->response[ $slug ] ) || ! empty( $transient->no_update[ $slug ] ) ) {
		return;
	}
	set_site_transient( 'update_themes', $transient );
}

add_filter( 'auto_update_theme', 'tth_updater_auto_update_gate', PHP_INT_MAX, 2 );

/**
 * Automatic updates only when "Enable auto-updates" is on for this theme.
 * Left alone when WordPress only asks whether auto-updates are forced
 * (null), so the Enable/Disable link stays clickable.
 *
 * @param bool|null $update Whether to update automatically.
 * @param object    $item   The update offer.
 * @return bool|null
 */
function tth_updater_auto_update_gate( $update, $item ) {
	if ( null === $update || empty( $item->theme ) || tth_updater_slug() !== $item->theme ) {
		return $update;
	}
	return in_array( tth_updater_slug(), (array) get_site_option( 'auto_update_themes', array() ), true );
}

add_filter( 'http_request_args', 'tth_updater_authorize_download', 10, 2 );

/**
 * Sends the GitHub token (if set) when downloading the release zip, so
 * updates work from a private repository.
 *
 * @param array  $args Request arguments.
 * @param string $url  URL.
 * @return array
 */
function tth_updater_authorize_download( $args, $url ) {
	$token = tth_updater_token();
	if ( $token && 0 === strpos( $url, 'https://api.github.com/repos/' . tth_updater_repo() . '/zipball/' ) ) {
		$args['headers']                  = isset( $args['headers'] ) ? (array) $args['headers'] : array();
		$args['headers']['Authorization'] = 'token ' . $token;
	}
	return $args;
}

add_action( 'load-update-core.php', 'tth_updater_force_check' );
add_action( 'admin_post_teatatu_homes_check_updates', 'tth_updater_clear_cache', 1 );

/**
 * Dashboard > Updates > "Check again" skips the release cache.
 */
function tth_updater_force_check() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only cache clear, same as core's force-check.
	if ( ! empty( $_GET['force-check'] ) && current_user_can( 'update_themes' ) ) {
		delete_site_transient( 'tth_theme_github_release' );
	}
}

/**
 * The plugin's "Check for Updates Now" button checks the theme too. The
 * plugin's own handler verifies the nonce and capability and redirects;
 * this only clears caches, after the same checks.
 */
function tth_updater_clear_cache() {
	if ( isset( $_POST['teatatu_homes_check_updates_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['teatatu_homes_check_updates_nonce'] ) ), 'teatatu_homes_check_updates' ) && current_user_can( 'update_themes' ) ) {
		delete_site_transient( 'tth_theme_github_release' );
		delete_site_transient( 'update_themes' );
	}
}
