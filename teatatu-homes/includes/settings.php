<?php
/**
 * Settings page: Network Admin > Settings > Teatatu Homes on a multisite
 * install, or Settings > Teatatu Homes on a single site. Holds the GitHub
 * update source, the enquiry fallback address and the data-retention
 * choice. One shared render function targets get/update_site_option() or
 * get/update_option() depending on is_multisite().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads a plugin option, using the network options table on multisite.
 *
 * @param string $key     Option name.
 * @param mixed  $default Default value.
 * @return mixed
 */
function teatatu_homes_get_option( $key, $default = false ) {
	return is_multisite() ? get_site_option( $key, $default ) : get_option( $key, $default );
}

/**
 * Writes a plugin option, using the network options table on multisite.
 *
 * @param string $key   Option name.
 * @param mixed  $value New value.
 * @return bool
 */
function teatatu_homes_update_option( $key, $value ) {
	return is_multisite() ? update_site_option( $key, $value ) : update_option( $key, $value );
}

/**
 * Deletes a plugin option, using the network options table on multisite.
 *
 * @param string $key Option name.
 * @return bool
 */
function teatatu_homes_delete_option( $key ) {
	return is_multisite() ? delete_site_option( $key ) : delete_option( $key );
}

add_action( 'network_admin_menu', 'teatatu_homes_register_network_settings_menu' );

/**
 * Adds the settings page under Network Admin > Settings on multisite.
 */
function teatatu_homes_register_network_settings_menu() {
	add_submenu_page(
		'settings.php',
		__( 'Teatatu Homes', 'teatatu-homes' ),
		__( 'Teatatu Homes', 'teatatu-homes' ),
		'manage_network_options',
		'teatatu-homes-settings',
		'teatatu_homes_render_settings_page'
	);
}

add_action( 'admin_menu', 'teatatu_homes_register_site_settings_menu' );

/**
 * Adds the settings page under Settings on a single-site install.
 */
function teatatu_homes_register_site_settings_menu() {
	if ( is_multisite() ) {
		return;
	}
	add_options_page(
		__( 'Teatatu Homes', 'teatatu-homes' ),
		__( 'Teatatu Homes', 'teatatu-homes' ),
		'manage_options',
		'teatatu-homes-settings',
		'teatatu_homes_render_settings_page'
	);
}

/**
 * Renders the settings form.
 */
function teatatu_homes_render_settings_page() {
	$cap = is_multisite() ? 'manage_network_options' : 'manage_options';
	if ( ! current_user_can( $cap ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'teatatu-homes' ) );
	}

	$github_repo         = teatatu_homes_get_option( 'teatatu_homes_github_repo', 'TeAtatu/Teatatu-homes' );
	$github_token_is_set = (bool) teatatu_homes_get_option( 'teatatu_homes_github_token', '' );
	$delete_on_uninstall = (bool) teatatu_homes_get_option( 'teatatu_homes_delete_data_on_uninstall', false );
	$enquiry_fallback    = teatatu_homes_get_option( 'teatatu_homes_enquiry_fallback_email', '' );
	// Always the plain admin-post.php — wp-admin/network/admin-post.php does not exist.
	$post_url = admin_url( 'admin-post.php' );

	if ( isset( $_GET['teatatu_homes_settings_saved'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'teatatu-homes' ) . '</p></div>';
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Teatatu Homes Settings', 'teatatu-homes' ); ?></h1>
		<form method="post" action="<?php echo esc_url( $post_url ); ?>">
			<?php wp_nonce_field( 'teatatu_homes_save_settings', 'teatatu_homes_settings_nonce' ); ?>
			<input type="hidden" name="action" value="teatatu_homes_save_settings" />
			<table class="form-table">
				<tr>
					<th><label for="th_enquiry_fallback"><?php esc_html_e( 'Enquiry Fallback Email', 'teatatu-homes' ); ?></label></th>
					<td>
						<input type="email" id="th_enquiry_fallback" name="enquiry_fallback_email" class="regular-text" value="<?php echo esc_attr( $enquiry_fallback ); ?>" />
						<p class="description"><?php esc_html_e( 'Enquiries go to the listing\'s agent, then the agency. If neither has an email address they go here, and finally to the site admin email.', 'teatatu-homes' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="th_github_repo"><?php esc_html_e( 'GitHub Repository', 'teatatu-homes' ); ?></label></th>
					<td>
						<input type="text" id="th_github_repo" name="github_repo" class="regular-text" value="<?php echo esc_attr( $github_repo ); ?>" placeholder="owner/repo" />
						<p class="description"><?php esc_html_e( 'Used to check for updates to the Teatatu Homes plugin and theme via GitHub Releases.', 'teatatu-homes' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="th_github_token"><?php esc_html_e( 'GitHub Access Token', 'teatatu-homes' ); ?></label></th>
					<td>
						<input type="password" id="th_github_token" name="github_token" class="regular-text" value="" autocomplete="new-password" placeholder="<?php echo $github_token_is_set ? esc_attr__( 'Token is set — leave blank to keep it', 'teatatu-homes' ) : esc_attr__( 'Optional, only needed for a private repo', 'teatatu-homes' ); ?>" />
						<?php if ( $github_token_is_set ) : ?>
							<label style="display:block;margin-top:6px;"><input type="checkbox" name="github_token_clear" value="1" /> <?php esc_html_e( 'Clear the saved token', 'teatatu-homes' ); ?></label>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Data on Uninstall', 'teatatu-homes' ); ?></th>
					<td>
						<label><input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( $delete_on_uninstall ); ?> /> <?php esc_html_e( 'Delete all Teatatu Homes data when this plugin is uninstalled', 'teatatu-homes' ); ?></label>
						<p class="description"><?php esc_html_e( 'Unchecked by default. When checked, uninstalling permanently deletes every Home listing, every photo uploaded to a home, and every Listing Type, Property Type, Area, Feature and Agency term.', 'teatatu-homes' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save Settings', 'teatatu-homes' ) ); ?>
		</form>

		<hr />
		<h2><?php esc_html_e( 'Updates', 'teatatu-homes' ); ?></h2>
		<p class="description"><?php esc_html_e( 'The plugin and the Teatatu Homes theme are released separately from the same GitHub repository (tags teatatu-homes-v… and teatatu-homes-theme-v…). Each installs automatically only when its own "Enable auto-updates" link is on; otherwise click Update now when an update is shown. This button checks for both.', 'teatatu-homes' ); ?></p>
		<?php
		$plugin_auto = in_array( TEATATU_HOMES_BASENAME, (array) get_site_option( 'auto_update_plugins', array() ), true );
		$plugins_url = is_multisite() ? network_admin_url( 'plugins.php' ) : admin_url( 'plugins.php' );
		?>
		<p>
			<?php esc_html_e( 'Teatatu Homes plugin:', 'teatatu-homes' ); ?> <strong><?php echo esc_html( TEATATU_HOMES_VERSION ); ?></strong>
			· <?php echo $plugin_auto ? esc_html__( 'auto-updates on', 'teatatu-homes' ) : esc_html__( 'auto-updates off', 'teatatu-homes' ); ?>
			· <a href="<?php echo esc_url( $plugins_url ); ?>"><?php echo $plugin_auto ? esc_html__( 'Turn off in Plugins', 'teatatu-homes' ) : esc_html__( 'Turn on in Plugins', 'teatatu-homes' ); ?></a>
		</p>
		<?php
		$theme_slug = teatatu_homes_theme_slug();
		if ( $theme_slug ) :
			$homes_theme = wp_get_theme( $theme_slug );
			$theme_auto  = in_array( $theme_slug, (array) get_site_option( 'auto_update_themes', array() ), true );
			$themes_url  = is_multisite() ? network_admin_url( 'themes.php' ) : admin_url( 'themes.php' );
			?>
			<p>
				<?php esc_html_e( 'Teatatu Homes theme:', 'teatatu-homes' ); ?> <strong><?php echo esc_html( $homes_theme->get( 'Version' ) ); ?></strong>
				· <?php echo $theme_auto ? esc_html__( 'auto-updates on', 'teatatu-homes' ) : esc_html__( 'auto-updates off', 'teatatu-homes' ); ?>
				· <a href="<?php echo esc_url( $themes_url ); ?>"><?php echo $theme_auto ? esc_html__( 'Turn off in Themes', 'teatatu-homes' ) : esc_html__( 'Turn on in Themes', 'teatatu-homes' ); ?></a>
			</p>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( $post_url ); ?>">
			<?php wp_nonce_field( 'teatatu_homes_check_updates', 'teatatu_homes_check_updates_nonce' ); ?>
			<input type="hidden" name="action" value="teatatu_homes_check_updates" />
			<?php submit_button( __( 'Check for Updates Now', 'teatatu-homes' ), 'secondary' ); ?>
		</form>
	</div>
	<?php
}

add_action( 'admin_post_teatatu_homes_save_settings', 'teatatu_homes_handle_save_settings' );

/**
 * Handles the settings form submission.
 */
function teatatu_homes_handle_save_settings() {
	if ( ! isset( $_POST['teatatu_homes_settings_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['teatatu_homes_settings_nonce'] ), 'teatatu_homes_save_settings' ) ) {
		wp_die( esc_html__( 'Security check failed.', 'teatatu-homes' ) );
	}
	$cap = is_multisite() ? 'manage_network_options' : 'manage_options';
	if ( ! current_user_can( $cap ) ) {
		wp_die( esc_html__( 'You do not have permission to do that.', 'teatatu-homes' ), 403 );
	}

	if ( isset( $_POST['enquiry_fallback_email'] ) ) {
		teatatu_homes_update_option( 'teatatu_homes_enquiry_fallback_email', sanitize_email( wp_unslash( $_POST['enquiry_fallback_email'] ) ) );
	}

	if ( isset( $_POST['github_repo'] ) ) {
		$repo = sanitize_text_field( wp_unslash( $_POST['github_repo'] ) );
		teatatu_homes_update_option( 'teatatu_homes_github_repo', $repo ? $repo : 'TeAtatu/Teatatu-homes' );
	}

	if ( ! empty( $_POST['github_token_clear'] ) ) {
		teatatu_homes_delete_option( 'teatatu_homes_github_token' );
	} elseif ( ! empty( $_POST['github_token'] ) ) {
		teatatu_homes_update_option( 'teatatu_homes_github_token', sanitize_text_field( wp_unslash( $_POST['github_token'] ) ) );
	}

	teatatu_homes_update_option( 'teatatu_homes_delete_data_on_uninstall', ! empty( $_POST['delete_data_on_uninstall'] ) );

	$redirect_base = is_multisite() ? network_admin_url( 'settings.php' ) : admin_url( 'options-general.php' );
	wp_safe_redirect( add_query_arg( array( 'page' => 'teatatu-homes-settings', 'teatatu_homes_settings_saved' => '1' ), $redirect_base ) );
	exit;
}
