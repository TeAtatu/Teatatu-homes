<?php
/**
 * Search results: the Homes archive and every Homes taxonomy archive
 * (see tth_homes_taxonomy_template()). The plugin has already applied the
 * URL filters to the main query; this template renders the filter form,
 * the results and a map.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
get_header();

if ( ! tth_plugin_ready() ) {
	echo '<div class="tth-wrap tth-section">';
	tth_plugin_missing_message();
	echo '</div>';
	get_footer();
	return;
}

global $wp_query;
$filters   = teatatu_homes_current_filters();
$active    = teatatu_homes_active_filters( $filters );
$base_url  = tth_current_base_url();
$on_type   = is_tax( 'teatatu_home_listing' );
$listing   = $filters['listing'] ? $filters['listing'] : 'sale';
$items     = array_map( 'teatatu_homes_normalize_item', $wp_query->posts );
$total     = (int) $wp_query->found_posts;
$any       = array( '' => __( 'Any', 'teatatu-homes-theme' ) );
$terms_for = function ( $taxonomy ) {
	return get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
};
?>

<section class="tth-filterbar" aria-label="<?php esc_attr_e( 'Search filters', 'teatatu-homes-theme' ); ?>">
	<form class="tth-wrap" role="search" method="get" action="<?php echo esc_url( $base_url ); ?>" data-tth-search>
		<?php if ( ! $on_type ) : ?>
			<fieldset class="tth-switch">
				<legend><?php esc_html_e( 'Buy or rent', 'teatatu-homes-theme' ); ?></legend>
				<label><input type="radio" name="listing" value="" <?php checked( $filters['listing'], '' ); ?> /><span><?php esc_html_e( 'All', 'teatatu-homes-theme' ); ?></span></label>
				<label><input type="radio" name="listing" value="sale" <?php checked( $filters['listing'], 'sale' ); ?> /><span><?php esc_html_e( 'Buy', 'teatatu-homes-theme' ); ?></span></label>
				<label><input type="radio" name="listing" value="rent" <?php checked( $filters['listing'], 'rent' ); ?> /><span><?php esc_html_e( 'Rent', 'teatatu-homes-theme' ); ?></span></label>
			</fieldset>
		<?php endif; ?>
		<label class="tth-field tth-field--grow"><?php esc_html_e( 'Street or keyword', 'teatatu-homes-theme' ); ?>
			<input class="tth-control" type="search" name="q" value="<?php echo esc_attr( $filters['q'] ); ?>" placeholder="<?php esc_attr_e( 'Any street on the Peninsula', 'teatatu-homes-theme' ); ?>" />
		</label>
		<label class="tth-field tth-field--sm"><span data-price-label data-sale="<?php esc_attr_e( 'Max price', 'teatatu-homes-theme' ); ?>" data-rent="<?php esc_attr_e( 'Max rent / wk', 'teatatu-homes-theme' ); ?>"><?php echo esc_html( 'rent' === $listing ? __( 'Max rent / wk', 'teatatu-homes-theme' ) : __( 'Max price', 'teatatu-homes-theme' ) ); ?></span>
			<?php
			$price_options = $any + tth_price_options( $listing );
			if ( $filters['price_max'] && ! isset( $price_options[ $filters['price_max'] ] ) ) {
				$price_options[ $filters['price_max'] ] = teatatu_homes_format_money( $filters['price_max'] );
			}
			tth_select( 'price_max', 'tth-price-max', $price_options, $filters['price_max'] ? $filters['price_max'] : '', $on_type ? '' : 'data-price-select' );
			?>
		</label>
		<label class="tth-field tth-field--sm"><?php esc_html_e( 'Bedrooms', 'teatatu-homes-theme' ); ?>
			<?php tth_select( 'beds', 'tth-beds', $any + array( 1 => '1+', 2 => '2+', 3 => '3+', 4 => '4+', 5 => '5+' ), $filters['beds'] ? $filters['beds'] : '' ); ?>
		</label>
		<label class="tth-field tth-field--sm"><?php esc_html_e( 'Bathrooms', 'teatatu-homes-theme' ); ?>
			<?php tth_select( 'baths', 'tth-baths', $any + array( 1 => '1+', 2 => '2+', 3 => '3+' ), $filters['baths'] ? $filters['baths'] : '' ); ?>
		</label>
		<?php if ( ! is_tax( 'teatatu_home_type' ) ) : ?>
			<label class="tth-field tth-field--sm"><?php esc_html_e( 'Type', 'teatatu-homes-theme' ); ?>
				<?php
				$types = array( '' => __( 'All homes', 'teatatu-homes-theme' ) );
				foreach ( $terms_for( 'teatatu_home_type' ) as $term ) {
					$types[ $term->slug ] = $term->name;
				}
				tth_select( 'type', 'tth-type', $types, $filters['type'] ? $filters['type'][0] : '' );
				?>
			</label>
		<?php endif; ?>
		<button class="tth-btn" type="submit"><?php echo tth_icon( 'search', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Search', 'teatatu-homes-theme' ); ?></button>

		<details <?php echo ( $filters['area'] || $filters['feature'] || $filters['open'] || $filters['pets'] || $filters['cars'] || $filters['price_min'] ) ? 'open' : ''; ?>>
			<summary><?php echo tth_icon( 'filter', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'More filters', 'teatatu-homes-theme' ); ?></summary>
			<div class="tth-more">
				<?php if ( ! is_tax( 'teatatu_home_area' ) ) : ?>
					<fieldset>
						<legend><?php esc_html_e( 'Area', 'teatatu-homes-theme' ); ?></legend>
						<?php foreach ( $terms_for( 'teatatu_home_area' ) as $term ) : ?>
							<label class="tth-check"><input type="checkbox" name="area[]" value="<?php echo esc_attr( $term->slug ); ?>" <?php checked( in_array( $term->slug, $filters['area'], true ) ); ?> /> <?php echo esc_html( $term->name ); ?></label>
						<?php endforeach; ?>
					</fieldset>
				<?php endif; ?>
				<fieldset>
					<legend><?php esc_html_e( 'Open homes', 'teatatu-homes-theme' ); ?></legend>
					<label class="tth-check"><input type="radio" name="open" value="" <?php checked( $filters['open'], '' ); ?> /> <?php esc_html_e( 'Any time', 'teatatu-homes-theme' ); ?></label>
					<label class="tth-check"><input type="radio" name="open" value="weekend" <?php checked( $filters['open'], 'weekend' ); ?> /> <?php esc_html_e( 'This weekend', 'teatatu-homes-theme' ); ?></label>
					<label class="tth-check"><input type="radio" name="open" value="any" <?php checked( $filters['open'], 'any' ); ?> /> <?php esc_html_e( 'Has an open home', 'teatatu-homes-theme' ); ?></label>
				</fieldset>
				<fieldset>
					<legend><?php esc_html_e( 'More', 'teatatu-homes-theme' ); ?></legend>
					<label class="tth-field"><?php esc_html_e( 'Min price', 'teatatu-homes-theme' ); ?>
						<?php tth_select( 'price_min', 'tth-price-min', $any + tth_price_options( $listing ), $filters['price_min'] ? $filters['price_min'] : '', $on_type ? '' : 'data-price-select' ); ?>
					</label>
					<label class="tth-field"><?php esc_html_e( 'Car parks', 'teatatu-homes-theme' ); ?>
						<?php tth_select( 'cars', 'tth-cars', $any + array( 1 => '1+', 2 => '2+', 3 => '3+' ), $filters['cars'] ? $filters['cars'] : '' ); ?>
					</label>
					<?php if ( 'sale' !== $filters['listing'] ) : ?>
						<label class="tth-check"><input type="checkbox" name="pets" value="1" <?php checked( $filters['pets'] ); ?> /> <?php esc_html_e( 'Pets considered (rentals)', 'teatatu-homes-theme' ); ?></label>
					<?php endif; ?>
				</fieldset>
				<?php
				$features = $terms_for( 'teatatu_home_feature' );
				if ( $features && ! is_wp_error( $features ) ) :
					?>
					<fieldset>
						<legend><?php esc_html_e( 'Features', 'teatatu-homes-theme' ); ?></legend>
						<?php foreach ( $features as $term ) : ?>
							<label class="tth-check"><input type="checkbox" name="feature[]" value="<?php echo esc_attr( $term->slug ); ?>" <?php checked( in_array( $term->slug, $filters['feature'], true ) ); ?> /> <?php echo esc_html( $term->name ); ?></label>
						<?php endforeach; ?>
					</fieldset>
				<?php endif; ?>
			</div>
		</details>
		<input type="hidden" name="sort" value="<?php echo esc_attr( $filters['sort'] ); ?>" />
	</form>
</section>

<section class="tth-wrap tth-section" style="padding-top:32px;">
	<div class="tth-results__head">
		<div>
			<h1 class="tth-display"><?php echo esc_html( tth_results_heading( $filters ) ); ?></h1>
			<p class="tth-muted" role="status">
				<?php
				/* translators: %s: number of homes. */
				echo esc_html( sprintf( _n( '%s home', '%s homes', $total, 'teatatu-homes-theme' ), number_format_i18n( $total ) ) );
				?>
			</p>
		</div>
		<div class="tth-results__tools">
			<?php if ( $active ) : ?>
				<div class="tth-chips">
					<?php
					foreach ( $active as $key => $value ) :
						if ( 'listing' === $key && $on_type ) {
							continue;
						}
						$without = $active;
						unset( $without[ $key ] );
						$without['sort'] = $filters['sort'];
						/* translators: %s: filter description. */
						$remove_label = sprintf( __( 'Remove filter: %s', 'teatatu-homes-theme' ), tth_filter_chip_label( $key, $value ) );
						?>
						<a class="tth-chip tth-chip--active" href="<?php echo esc_url( tth_filters_url( $without, $base_url ) ); ?>" aria-label="<?php echo esc_attr( $remove_label ); ?>"><?php echo esc_html( tth_filter_chip_label( $key, $value ) ); ?><?php echo tth_icon( 'close', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<form method="get" action="<?php echo esc_url( $base_url ); ?>" class="tth-sort" data-tth-autosubmit>
				<?php
				foreach ( $active as $key => $value ) {
					if ( 'listing' === $key && $on_type ) {
						continue;
					}
					foreach ( (array) $value as $v ) {
						printf( '<input type="hidden" name="%s" value="%s" />', esc_attr( is_array( $value ) ? $key . '[]' : $key ), esc_attr( is_bool( $v ) ? '1' : $v ) );
					}
				}
				?>
				<label class="tth-field" style="flex-direction:row;align-items:center;gap:8px;"><?php esc_html_e( 'Sort', 'teatatu-homes-theme' ); ?>
					<?php tth_select( 'sort', 'tth-sort', teatatu_homes_sort_options(), $filters['sort'], 'style="height:40px;width:auto;"' ); ?>
				</label>
				<noscript><button class="tth-btn tth-btn--ghost" type="submit"><?php esc_html_e( 'Sort', 'teatatu-homes-theme' ); ?></button></noscript>
			</form>
		</div>
	</div>

	<div class="tth-results">
		<div class="tth-results__list">
			<?php if ( $items ) : ?>
				<?php foreach ( $items as $item ) : ?>
					<?php tth_card( $item, 'row' ); ?>
				<?php endforeach; ?>
				<nav class="tth-pagination" aria-label="<?php esc_attr_e( 'Results pages', 'teatatu-homes-theme' ); ?>">
					<div class="nav-links">
						<?php
						echo paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped.
							array(
								'total'     => (int) $wp_query->max_num_pages,
								'current'   => max( 1, (int) get_query_var( 'paged' ) ),
								'prev_text' => __( 'Previous', 'teatatu-homes-theme' ),
								'next_text' => __( 'Next', 'teatatu-homes-theme' ),
							)
						);
						?>
					</div>
				</nav>
			<?php else : ?>
				<div class="tth-empty">
					<h2 style="font-size:20px;"><?php esc_html_e( 'No homes match this search', 'teatatu-homes-theme' ); ?></h2>
					<p class="tth-muted"><?php esc_html_e( 'Try removing a filter or widening the price range.', 'teatatu-homes-theme' ); ?></p>
					<a class="tth-btn tth-btn--ghost" href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Clear all filters', 'teatatu-homes-theme' ); ?></a>
				</div>
			<?php endif; ?>
		</div>

		<?php $points = tth_map_points( $items ); ?>
		<?php if ( $points ) : ?>
			<aside class="tth-results__aside" aria-label="<?php esc_attr_e( 'Map', 'teatatu-homes-theme' ); ?>">
				<?php tth_map( $points, __( 'Map of the homes on this page', 'teatatu-homes-theme' ) ); ?>
				<p class="tth-muted" style="font-size:14px;"><?php esc_html_e( 'Homes whose address is shown on request are not pinned.', 'teatatu-homes-theme' ); ?></p>
			</aside>
		<?php endif; ?>
	</div>
</section>

<?php
get_footer();
