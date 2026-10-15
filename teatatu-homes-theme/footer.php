<?php
/**
 * Site footer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$footer_text = get_theme_mod( 'tth_footer_text', '' );
?>
</main>
<footer class="tth-footer">
	<div class="tth-wrap tth-footer__grid">
		<div>
			<p class="tth-footer__title"><?php bloginfo( 'name' ); ?></p>
			<p style="margin-top:10px;"><?php echo esc_html( $footer_text ? $footer_text : __( 'Homes for sale and rent on Te Atatū Peninsula, Auckland.', 'teatatu-homes-theme' ) ); ?></p>
		</div>
		<?php if ( tth_plugin_ready() ) : ?>
			<nav aria-labelledby="tth-footer-browse">
				<h2 id="tth-footer-browse"><?php esc_html_e( 'Browse', 'teatatu-homes-theme' ); ?></h2>
				<ul>
					<?php
					foreach ( teatatu_homes_listing_types() as $slug => $label ) :
						$term = get_term_by( 'slug', $slug, 'teatatu_home_listing' );
						if ( ! $term ) {
							continue;
						}
						?>
						<li><a href="<?php echo esc_url( get_term_link( $term ) ); ?>"><?php echo esc_html( 'sale' === $slug ? __( 'Homes for sale', 'teatatu-homes-theme' ) : __( 'Homes for rent', 'teatatu-homes-theme' ) ); ?></a></li>
					<?php endforeach; ?>
					<li><a href="<?php echo esc_url( tth_filters_url( array( 'open' => 'weekend', 'sort' => 'open' ), tth_search_url() ) ); ?>"><?php esc_html_e( 'Open homes this weekend', 'teatatu-homes-theme' ); ?></a></li>
				</ul>
			</nav>
			<nav aria-labelledby="tth-footer-areas">
				<h2 id="tth-footer-areas"><?php esc_html_e( 'Areas', 'teatatu-homes-theme' ); ?></h2>
				<ul>
					<?php foreach ( get_terms( array( 'taxonomy' => 'teatatu_home_area', 'hide_empty' => false ) ) as $area ) : ?>
						<li><a href="<?php echo esc_url( get_term_link( $area ) ); ?>"><?php echo esc_html( $area->name ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</nav>
		<?php endif; ?>
		<?php if ( has_nav_menu( 'footer' ) ) : ?>
			<nav aria-labelledby="tth-footer-site">
				<h2 id="tth-footer-site"><?php bloginfo( 'name' ); ?></h2>
				<?php wp_nav_menu( array( 'theme_location' => 'footer', 'container' => false, 'depth' => 1 ) ); ?>
			</nav>
		<?php endif; ?>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
