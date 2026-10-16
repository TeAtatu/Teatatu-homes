<?php
/**
 * Site header: the Te Atatū Local NetworkNav — the shared black strip
 * linking all five sites, then the Homes header (wordmark, search, one
 * primary action), the section links, and the 4px Harakeke accent rule.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$list_url = get_theme_mod( 'tth_list_url', '' );
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#content"><?php esc_html_e( 'Skip to content', 'teatatu-homes-theme' ); ?></a>
<header class="tal-nav" data-site="homes">
	<nav class="tal-network" aria-label="<?php esc_attr_e( 'Te Atatū Local sites', 'teatatu-homes-theme' ); ?>">
		<?php foreach ( tth_network_sites() as $key => $site ) : ?>
			<a class="tal-site" data-site="<?php echo esc_attr( $key ); ?>" href="<?php echo esc_url( $site[1] ); ?>" <?php echo 'homes' === $key ? 'aria-current="page"' : ''; ?>><span class="tal-site-dot" aria-hidden="true"></span><?php echo esc_html( $site[0] ); ?></a>
		<?php endforeach; ?>
	</nav>
	<div class="tal-nav-bar">
		<a class="tal-wordmark" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">Te Atatū <span><?php esc_html_e( 'Homes', 'teatatu-homes-theme' ); ?></span></a>
		<?php if ( tth_plugin_ready() ) : ?>
			<form class="tal-nav-search" role="search" method="get" action="<?php echo esc_url( tth_search_url() ); ?>">
				<?php echo tth_icon( 'search', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
				<label class="screen-reader-text" for="tal-nav-q"><?php esc_html_e( 'Search homes', 'teatatu-homes-theme' ); ?></label>
				<input id="tal-nav-q" type="search" name="q" placeholder="<?php esc_attr_e( 'Search homes, rentals, streets…', 'teatatu-homes-theme' ); ?>" />
			</form>
		<?php endif; ?>
		<?php if ( $list_url ) : ?>
			<a class="tal-btn tal-btn-primary tal-btn-sm" href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'List a property', 'teatatu-homes-theme' ); ?></a>
		<?php endif; ?>
	</div>
	<nav class="tal-nav-sub" aria-label="<?php esc_attr_e( 'Homes', 'teatatu-homes-theme' ); ?>">
		<?php
		wp_nav_menu(
			array(
				'theme_location' => 'primary',
				'container'      => false,
				'depth'          => 1,
				'fallback_cb'    => 'tth_primary_menu_fallback',
			)
		);
		?>
	</nav>
	<div class="tal-nav-accent" aria-hidden="true"></div>
</header>
<main id="content">
