<?php
/**
 * Site header.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
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
<header class="tth-header">
	<div class="tth-wrap tth-header__inner">
		<a class="tth-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<span style="color: var(--tth-accent);"><?php echo tth_icon( 'logo', 32 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
			<span class="tth-brand__name"><?php bloginfo( 'name' ); ?></span>
		</a>
		<nav class="tth-nav" aria-label="<?php esc_attr_e( 'Main', 'teatatu-homes-theme' ); ?>">
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
	</div>
</header>
<main id="content">
