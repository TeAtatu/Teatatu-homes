<?php
/**
 * Home page: search, latest homes for sale and for rent, areas, and the
 * owner call to action.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
get_header();

$hero_image   = get_theme_mod( 'tth_hero_image', '' );
$hero_alt     = get_theme_mod( 'tth_hero_image_alt', '' );
$appraisal    = get_theme_mod( 'tth_appraisal_url', '' );
$list_url     = get_theme_mod( 'tth_list_url', '' );
$ready        = tth_plugin_ready();
$search_url   = tth_search_url();
?>

<section class="tth-wrap tth-hero">
	<div class="tth-hero__copy">
		<p class="tth-eyebrow"><?php esc_html_e( 'Te Atatū Peninsula · West Auckland', 'teatatu-homes-theme' ); ?></p>
		<h1 class="tth-display"><?php esc_html_e( 'Homes on the Peninsula. Nothing else.', 'teatatu-homes-theme' ); ?></h1>
		<p class="tth-lead"><?php esc_html_e( 'Every house, townhouse and unit for sale or rent between the motorway and the harbour — in one place.', 'teatatu-homes-theme' ); ?></p>

		<?php if ( $ready ) : ?>
			<form class="tth-search-card" role="search" method="get" action="<?php echo esc_url( $search_url ); ?>" data-tth-search>
				<fieldset class="tth-switch">
					<legend><?php esc_html_e( 'Buy or rent', 'teatatu-homes-theme' ); ?></legend>
					<label><input type="radio" name="listing" value="sale" checked /><span><?php esc_html_e( 'Buy', 'teatatu-homes-theme' ); ?></span></label>
					<label><input type="radio" name="listing" value="rent" /><span><?php esc_html_e( 'Rent', 'teatatu-homes-theme' ); ?></span></label>
				</fieldset>
				<div class="tth-search-card__grid">
					<label class="tth-field tth-field--full"><?php esc_html_e( 'Street or keyword', 'teatatu-homes-theme' ); ?>
						<input class="tth-control" type="search" name="q" placeholder="<?php esc_attr_e( 'e.g. Harbour View Road, garage, deck', 'teatatu-homes-theme' ); ?>" />
					</label>
					<label class="tth-field"><?php esc_html_e( 'Bedrooms', 'teatatu-homes-theme' ); ?>
						<?php tth_select( 'beds', 'tth-hero-beds', array( '' => __( 'Any', 'teatatu-homes-theme' ), 1 => '1+', 2 => '2+', 3 => '3+', 4 => '4+', 5 => '5+' ), '' ); ?>
					</label>
					<label class="tth-field"><span data-price-label data-sale="<?php esc_attr_e( 'Max price', 'teatatu-homes-theme' ); ?>" data-rent="<?php esc_attr_e( 'Max rent / week', 'teatatu-homes-theme' ); ?>"><?php esc_html_e( 'Max price', 'teatatu-homes-theme' ); ?></span>
						<?php tth_select( 'price_max', 'tth-hero-price', array( '' => __( 'Any', 'teatatu-homes-theme' ) ) + tth_price_options( 'sale' ), '', 'data-price-select' ); ?>
					</label>
					<label class="tth-field"><?php esc_html_e( 'Type', 'teatatu-homes-theme' ); ?>
						<?php
						$types = array( '' => __( 'All homes', 'teatatu-homes-theme' ) );
						foreach ( get_terms( array( 'taxonomy' => 'teatatu_home_type', 'hide_empty' => false ) ) as $term ) {
							$types[ $term->slug ] = $term->name;
						}
						tth_select( 'type', 'tth-hero-type', $types, '' );
						?>
					</label>
				</div>
				<button class="tth-btn tth-btn--block" type="submit"><?php echo tth_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Search homes', 'teatatu-homes-theme' ); ?></button>
			</form>

			<div class="tth-chips">
				<a class="tth-chip" href="<?php echo esc_url( tth_filters_url( array( 'open' => 'weekend', 'sort' => 'open' ), $search_url ) ); ?>"><?php esc_html_e( 'Open homes this weekend', 'teatatu-homes-theme' ); ?></a>
				<a class="tth-chip" href="<?php echo esc_url( tth_filters_url( array( 'listing' => 'rent', 'pets' => true ), $search_url ) ); ?>"><?php esc_html_e( 'Pet-friendly rentals', 'teatatu-homes-theme' ); ?></a>
				<a class="tth-chip" href="<?php echo esc_url( tth_filters_url( array( 'sort' => 'newest' ), $search_url ) ); ?>"><?php esc_html_e( 'Newest listings', 'teatatu-homes-theme' ); ?></a>
			</div>
		<?php endif; ?>
	</div>

	<div class="tth-hero__media">
		<?php if ( $hero_image ) : ?>
			<img src="<?php echo esc_url( $hero_image ); ?>" alt="<?php echo esc_attr( $hero_alt ); ?>" fetchpriority="high" />
		<?php endif; ?>
	</div>
</section>

<?php if ( $ready ) : ?>
	<section class="tth-wrap tth-section" aria-labelledby="tth-latest-heading">
		<div class="tth-section-head">
			<div>
				<h2 id="tth-latest-heading" class="tth-display"><?php esc_html_e( 'Latest on the Peninsula', 'teatatu-homes-theme' ); ?></h2>
				<p><?php esc_html_e( 'New homes for sale and rent.', 'teatatu-homes-theme' ); ?></p>
			</div>
			<div class="tth-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Listing type', 'teatatu-homes-theme' ); ?>" data-tth-tabs hidden>
				<button type="button" role="tab" id="tth-tab-sale" aria-controls="tth-panel-sale" aria-selected="true"><?php esc_html_e( 'For sale', 'teatatu-homes-theme' ); ?></button>
				<button type="button" role="tab" id="tth-tab-rent" aria-controls="tth-panel-rent" aria-selected="false" tabindex="-1"><?php esc_html_e( 'For rent', 'teatatu-homes-theme' ); ?></button>
			</div>
		</div>
		<?php
		foreach ( teatatu_homes_listing_types() as $slug => $label ) :
			$items = teatatu_homes_latest( $slug, 3 );
			$term  = get_term_by( 'slug', $slug, 'teatatu_home_listing' );
			$all   = $term ? get_term_link( $term ) : $search_url;
			?>
			<div class="tth-tabpanel" role="tabpanel" id="tth-panel-<?php echo esc_attr( $slug ); ?>" aria-labelledby="tth-tab-<?php echo esc_attr( $slug ); ?>">
				<h3 class="screen-reader-text"><?php echo esc_html( $label ); ?></h3>
				<?php if ( $items ) : ?>
					<div class="tth-card-grid">
						<?php foreach ( $items as $item ) : ?>
							<?php tth_card( $item ); ?>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<div class="tth-empty"><p><?php echo esc_html( 'rent' === $slug ? __( 'No rentals listed right now.', 'teatatu-homes-theme' ) : __( 'No homes for sale listed right now.', 'teatatu-homes-theme' ) ); ?></p></div>
				<?php endif; ?>
				<p style="margin-top:16px;"><a class="tth-arrow-link" href="<?php echo esc_url( $all ); ?>"><?php echo esc_html( 'rent' === $slug ? __( 'View all rentals', 'teatatu-homes-theme' ) : __( 'View all homes for sale', 'teatatu-homes-theme' ) ); ?><?php echo tth_icon( 'arrow', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></a></p>
			</div>
		<?php endforeach; ?>
	</section>

	<section class="tth-wrap tth-section" aria-labelledby="tth-areas-heading">
		<div class="tth-section-head">
			<div>
				<h2 id="tth-areas-heading" class="tth-display"><?php esc_html_e( 'Find your corner of the Peninsula', 'teatatu-homes-theme' ); ?></h2>
				<p><?php esc_html_e( 'Three areas, split by Te Atatū Road, Taikata Road and Harbour View Road.', 'teatatu-homes-theme' ); ?></p>
			</div>
		</div>
		<div class="tth-tiles">
			<?php foreach ( get_terms( array( 'taxonomy' => 'teatatu_home_area', 'hide_empty' => false ) ) as $area ) : ?>
				<a class="tth-tile" href="<?php echo esc_url( get_term_link( $area ) ); ?>">
					<span style="color: var(--tth-accent);"><?php echo tth_icon( 'pin', 28 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
					<span class="tth-tile__title"><?php echo esc_html( $area->name ); ?></span>
					<?php if ( $area->description ) : ?>
						<span class="tth-tile__text"><?php echo esc_html( $area->description ); ?></span>
					<?php endif; ?>
					<span class="tth-tile__meta">
						<?php
						/* translators: %s: area name. */
						echo esc_html( sprintf( __( 'Homes in %s', 'teatatu-homes-theme' ), $area->name ) );
						?>
					</span>
				</a>
			<?php endforeach; ?>
		</div>
	</section>
<?php else : ?>
	<section class="tth-wrap tth-section"><?php tth_plugin_missing_message(); ?></section>
<?php endif; ?>

<section class="tth-wrap tth-section" aria-label="<?php esc_attr_e( 'Why use this site', 'teatatu-homes-theme' ); ?>">
	<div class="tth-values">
		<div>
			<h3><?php esc_html_e( 'Only the Peninsula', 'teatatu-homes-theme' ); ?></h3>
			<p><?php esc_html_e( 'No scrolling past other suburbs. If it is listed here, it is on Te Atatū Peninsula.', 'teatatu-homes-theme' ); ?></p>
		</div>
		<div>
			<h3><?php esc_html_e( 'Local detail', 'teatatu-homes-theme' ); ?></h3>
			<p><?php esc_html_e( 'Every home is placed in its area, with open homes, documents and rental terms in one place.', 'teatatu-homes-theme' ); ?></p>
		</div>
		<div>
			<h3><?php esc_html_e( 'Talk to the agent', 'teatatu-homes-theme' ); ?></h3>
			<p><?php esc_html_e( 'Enquiries go straight to the listing agent or property manager.', 'teatatu-homes-theme' ); ?></p>
		</div>
	</div>
</section>

<?php if ( $appraisal || $list_url ) : ?>
	<section class="tth-wrap tth-section" style="padding-bottom:80px;">
		<div class="tth-cta">
			<div>
				<h2 class="tth-display"><?php esc_html_e( 'Selling or renting out a Peninsula home?', 'teatatu-homes-theme' ); ?></h2>
				<p><?php esc_html_e( 'List it where Peninsula buyers and tenants are already looking.', 'teatatu-homes-theme' ); ?></p>
			</div>
			<div class="tth-cta__actions">
				<?php if ( $appraisal ) : ?>
					<a class="tth-btn tth-btn--light" href="<?php echo esc_url( $appraisal ); ?>"><?php esc_html_e( 'Get an appraisal', 'teatatu-homes-theme' ); ?></a>
				<?php endif; ?>
				<?php if ( $list_url ) : ?>
					<a class="tth-btn tth-btn--outline-light" href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'List a rental', 'teatatu-homes-theme' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</section>
<?php endif; ?>

<?php
get_footer();
