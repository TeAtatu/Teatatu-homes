<?php
/**
 * Updates for the Teatatu Homes theme, run by this plugin.
 *
 * A theme's own code only loads on sites where it is the active theme, but
 * WordPress checks for theme updates (and shows Network Admin > Themes,
 * and runs WP-Cron) from other sites — on a network, usually the main
 * site. So the plugin, which is loaded everywhere, does the theme's update
 * checks too. The theme keeps a copy of this logic
 * (teatatu-homes-theme/inc/updater.php) that only runs when this plugin
 * isn't active.
 *
 * - Releases: the theme is released separately from the plugin, from the
 *   same repository, with tags `teatatu-homes-theme-v<version>` (plugin
 *   releases use `teatatu-homes-v<version>` and are ignored here).
 * - The theme is always listed in WordPress's update data (as an update or
 *   as up to date), which is what makes the "Enable auto-updates" switch
 *   appear (Network Admin > Themes on multisite, Appearance > Themes on a
 *   single site).
 * - Installs automatically only when that switch is on; otherwise the
 *   notice shows and an admin clicks Update now.
 * - Only the teatatu-homes-theme/ folder from the release zip is installed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tells the theme its updates are handled here (it then skips its own copy).
 *
 * @return bool
 */
function teatatu_homes_theme_updater_loaded() {
	return true;
}

/**
 * The installed theme's folder (stylesheet), or '' when it isn't installed.
 * Found by folder name, else by its text domain (in case it was renamed).
 *
 * @return string
 */
function teatatu_homes_theme_slug() {
	static $slug = null;
	if ( null !== $slug ) {
		return $slug;
	}
	$slug = '';
	if ( wp_get_theme( 'teatatu-homes-theme' )->exists() ) {
		$slug = 'teatatu-homes-theme';
	} else {
		foreach ( wp_get_themes() as $stylesheet => $theme ) {
			if ( 'teatatu-homes-theme' === $theme->get( 'TextDomain' ) ) {
				$slug = (string) $stylesheet;
				break;
			}
		}
	}
	return $slug;
}

/**
 * Installed theme version.
 *
 * @return string
 */
function teatatu_homes_theme_installed_version() {
	return (string) wp_get_theme( teatatu_homes_theme_slug() )->get( 'Version' );
}

/**
 * `owner/repo` for updates: the plugin's setting when available.
 *
 * @return string
 */
function teatatu_homes_theme_repo() {
	$repo = teatatu_homes_get_option( 'teatatu_homes_github_repo', '' );
	return (string) apply_filters( 'teatatu_homes_theme_repo', $repo ? $repo : 'TeAtatu/Teatatu-homes' );
}

/**
 * GitHub token (only needed for a private repository).
 *
 * @return string
 */
function teatatu_homes_theme_token() {
	return (string) teatatu_homes_get_option( 'teatatu_homes_github_token', '' );
}

/**
 * Request headers for GitHub.
 *
 * @param string $accept Accept header.
 * @return array
 */
function teatatu_homes_theme_headers( $accept = 'application/vnd.github+json' ) {
	$headers = array(
		'User-Agent' => 'Teatatu-Homes-Theme/' . teatatu_homes_theme_installed_version() . ' (' . home_url() . ')',
		'Accept'     => $accept,
	);
	$token = teatatu_homes_theme_token();
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
function teatatu_homes_theme_release_info( $force = false ) {
	$cache_key = 'teatatu_homes_theme_github_release';
	if ( ! $force ) {
		$cached = get_site_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached ? $cached : null;
		}
	}

	// The theme's own releases only: tags `teatatu-homes-theme-v<version>`.
	$repo     = teatatu_homes_theme_repo();
	$response = wp_remote_get( "https://api.github.com/repos/{$repo}/releases?per_page=50", array( 'headers' => teatatu_homes_theme_headers(), 'timeout' => 15 ) );
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
		array( 'headers' => teatatu_homes_theme_headers( 'application/vnd.github.raw+json' ), 'timeout' => 15 )
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

add_filter( 'pre_set_site_transient_update_themes', 'teatatu_homes_theme_check' );

/**
 * Offers the update in wp-admin when the release has a newer theme version.
 *
 * @param object $transient update_themes transient.
 * @return object
 */
function teatatu_homes_theme_check( $transient ) {
	if ( empty( $transient->checked ) ) {
		return $transient;
	}
	$slug = teatatu_homes_theme_slug();
	if ( '' === $slug ) {
		return $transient; // Theme not installed.
	}
	$installed = teatatu_homes_theme_installed_version();
	$info      = teatatu_homes_theme_release_info();
	$newer     = $info && version_compare( $info['version'], $installed, '>' );
	// Always listed (update or no_update): the Themes screen only offers
	// "Enable auto-updates" for a non-WordPress.org theme that appears in one.
	$item = array(
		'theme'        => $slug,
		'new_version'  => $newer ? $info['version'] : $installed,
		// "View version details" opens this in a modal; GitHub can't be framed, so it's our own page.
		'url'          => add_query_arg( array( 'action' => 'teatatu_homes_theme_changelog' ), admin_url( 'admin-ajax.php' ) ),
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

add_action( 'wp_ajax_teatatu_homes_theme_changelog', 'teatatu_homes_theme_changelog' );

/**
 * The "View version details" modal: the release notes.
 */
function teatatu_homes_theme_changelog() {
	if ( ! current_user_can( 'update_themes' ) ) {
		wp_die( esc_html__( 'You do not have permission to view this.', 'teatatu-homes' ), 403 );
	}
	$info = teatatu_homes_theme_release_info();
	echo '<!doctype html><html><head><meta charset="utf-8"><title>' . esc_html__( 'Teatatu Homes Theme', 'teatatu-homes' ) . '</title>';
	echo '<style>body{font:15px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;margin:24px;color:#1d2327}h1{font-size:20px}</style></head><body>';
	if ( ! $info ) {
		echo '<p>' . esc_html__( 'Release details are not available right now.', 'teatatu-homes' ) . '</p>';
	} else {
		/* translators: %s: version. */
		echo '<h1>' . esc_html( sprintf( __( 'Teatatu Homes Theme %s', 'teatatu-homes' ), $info['version'] ) ) . '</h1>';
		echo $info['changelog'] ? wp_kses_post( wpautop( $info['changelog'] ) ) : '<p>' . esc_html__( 'No changelog available.', 'teatatu-homes' ) . '</p>';
		echo '<p><a href="' . esc_url( $info['url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'View the release on GitHub', 'teatatu-homes' ) . '</a></p>';
	}
	echo '</body></html>';
	exit;
}

add_filter( 'upgrader_source_selection', 'teatatu_homes_theme_source_dir', 10, 4 );

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
function teatatu_homes_theme_source_dir( $source, $remote_source, $upgrader, $hook_extra = array() ) {
	global $wp_filesystem;
	$slug = teatatu_homes_theme_slug();
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
		return new WP_Error( 'teatatu_homes_theme_bad_package', __( 'The downloaded update does not contain the Teatatu Homes theme. Nothing was changed.', 'teatatu-homes' ) );
	}

	if ( $from === $desired ) {
		return $desired;
	}
	if ( $wp_filesystem->move( untrailingslashit( $from ), untrailingslashit( $desired ), true ) ) {
		return $desired;
	}
	return new WP_Error( 'teatatu_homes_theme_move_failed', __( 'Could not prepare the Teatatu Homes theme update. Nothing was changed.', 'teatatu-homes' ) );
}

add_filter( 'auto_update_theme', 'teatatu_homes_theme_auto_update_gate', PHP_INT_MAX, 2 );

/**
 * Automatic updates only when "Enable auto-updates" is on for this theme.
 * Left alone when WordPress only asks whether auto-updates are forced
 * (null), so the Enable/Disable link stays clickable.
 *
 * @param bool|null $update Whether to update automatically.
 * @param object    $item   The update offer.
 * @return bool|null
 */
function teatatu_homes_theme_auto_update_gate( $update, $item ) {
	if ( null === $update || empty( $item->theme ) || teatatu_homes_theme_slug() !== $item->theme ) {
		return $update;
	}
	return in_array( teatatu_homes_theme_slug(), (array) get_site_option( 'auto_update_themes', array() ), true );
}

add_filter( 'http_request_args', 'teatatu_homes_theme_authorize_download', 10, 2 );

/**
 * Sends the GitHub token (if set) when downloading the release zip, so
 * updates work from a private repository.
 *
 * @param array  $args Request arguments.
 * @param string $url  URL.
 * @return array
 */
function teatatu_homes_theme_authorize_download( $args, $url ) {
	$token = teatatu_homes_theme_token();
	if ( $token && 0 === strpos( $url, 'https://api.github.com/repos/' . teatatu_homes_theme_repo() . '/zipball/' ) ) {
		$args['headers']                  = isset( $args['headers'] ) ? (array) $args['headers'] : array();
		$args['headers']['Authorization'] = 'token ' . $token;
	}
	return $args;
}

add_action( 'load-update-core.php', 'teatatu_homes_theme_force_check' );
add_action( 'admin_post_teatatu_homes_check_updates', 'teatatu_homes_theme_clear_cache', 1 );

/**
 * Dashboard > Updates > "Check again" skips the release cache.
 */
function teatatu_homes_theme_force_check() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only cache clear, same as core's force-check.
	if ( ! empty( $_GET['force-check'] ) && current_user_can( 'update_themes' ) ) {
		delete_site_transient( 'teatatu_homes_theme_github_release' );
	}
}

/**
 * The plugin's "Check for Updates Now" button checks the theme too. The
 * plugin's own handler verifies the nonce and capability and redirects;
 * this only clears caches, after the same checks.
 */
function teatatu_homes_theme_clear_cache() {
	if ( isset( $_POST['teatatu_homes_check_updates_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['teatatu_homes_check_updates_nonce'] ) ), 'teatatu_homes_check_updates' ) && current_user_can( 'update_themes' ) ) {
		delete_site_transient( 'teatatu_homes_theme_github_release' );
		delete_site_transient( 'update_themes' );
	}
}
