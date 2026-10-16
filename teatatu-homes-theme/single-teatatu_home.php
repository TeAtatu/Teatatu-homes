<?php
/**
 * A single listing. Everything shown comes from the plugin's normalised
 * item; the description is the post content.
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

while ( have_posts() ) :
	the_post();
	$item    = teatatu_homes_normalize_item( get_post() );
	$images  = $item['photos'];
	$shown   = array_slice( $images, 0, 5 );
	$type    = $item['listing'] ? get_term_by( 'slug', $item['listing'], 'teatatu_home_listing' ) : null;
	$enquiry = teatatu_homes_enquiry_status();
	$phone   = $item['agent']['phone'] ? $item['agent']['phone'] : ( $item['agency'] ? $item['agency']['phone'] : '' );

	// Detail rows that apply to this listing type.
	$details = array();
	if ( $item['sale'] ) {
		$details[ __( 'Sale method', 'teatatu-homes-theme' ) ] = $item['sale']['method_label'];
		if ( $item['sale']['title_label'] ) {
			$details[ __( 'Title', 'teatatu-homes-theme' ) ] = $item['sale']['title_label'];
		}
	}
	if ( $item['rental'] ) {
		$details[ __( 'Available', 'teatatu-homes-theme' ) ] = $item['rental']['available_label'];
		if ( $item['rental']['bond_label'] ) {
			$details[ __( 'Bond', 'teatatu-homes-theme' ) ] = $item['rental']['bond_label'];
		}
		$details[ __( 'Pets', 'teatatu-homes-theme' ) ]      = $item['rental']['pets_label'];
		$details[ __( 'Furnishing', 'teatatu-homes-theme' ) ] = $item['rental']['furnished_label'];
		$details[ __( 'Healthy Homes', 'teatatu-homes-theme' ) ] = $item['rental']['healthy_homes'] ? __( 'Meets the standards', 'teatatu-homes-theme' ) : __( 'Not stated', 'teatatu-homes-theme' );
	}
	if ( $item['property_type'] ) {
		$details[ __( 'Property type', 'teatatu-homes-theme' ) ] = $item['property_type']['name'];
	}
	if ( $item['external_ref'] ) {
		$details[ __( 'Reference', 'teatatu-homes-theme' ) ] = $item['external_ref'];
	}

	$facts = array();
	if ( $item['bedrooms'] ) {
		$facts[ _n( 'Bedroom', 'Bedrooms', $item['bedrooms'], 'teatatu-homes-theme' ) ] = $item['bedrooms'];
	}
	if ( $item['bathrooms'] ) {
		$facts[ _n( 'Bathroom', 'Bathrooms', $item['bathrooms'], 'teatatu-homes-theme' ) ] = $item['bathrooms'];
	}
	if ( $item['car_parks'] ) {
		$facts[ _n( 'Car park', 'Car parks', $item['car_parks'], 'teatatu-homes-theme' ) ] = $item['car_parks'];
	}
	if ( $item['floor_area'] ) {
		$facts[ __( 'Floor area', 'teatatu-homes-theme' ) ] = number_format_i18n( $item['floor_area'] ) . ' m²';
	}
	if ( $item['land_area'] ) {
		$facts[ __( 'Land area', 'teatatu-homes-theme' ) ] = number_format_i18n( $item['land_area'] ) . ' m²';
	}
	?>

	<article class="tth-wrap tth-listing">
		<nav class="tth-crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'teatatu-homes-theme' ); ?>">
			<ol>
				<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'teatatu-homes-theme' ); ?></a></li>
				<?php if ( $type ) : ?>
					<li><a href="<?php echo esc_url( get_term_link( $type ) ); ?>"><?php echo esc_html( $item['listing_label'] ); ?></a></li>
				<?php endif; ?>
				<li aria-current="page"><?php echo esc_html( $item['address']['line1'] ); ?></li>
			</ol>
		</nav>

		<?php if ( ! $item['is_active'] ) : ?>
			<p class="tth-status-banner">
				<?php echo tth_icon( 'home' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
				<?php
				/* translators: %s: status such as Sold or Leased. */
				echo esc_html( sprintf( __( 'This listing is %s.', 'teatatu-homes-theme' ), strtolower( $item['status_label'] ) ) );
				?>
			</p>
		<?php endif; ?>

		<?php if ( $shown ) : ?>
			<div class="tth-gallery tth-gallery--<?php echo (int) count( $shown ); ?>">
				<?php foreach ( $shown as $i => $photo ) : ?>
					<button type="button" class="tth-gallery__item" data-tth-open-gallery="<?php echo (int) $i; ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: photo number. */ __( 'Open photo %d', 'teatatu-homes-theme' ), $i + 1 ) ); ?>">
						<?php tth_photo( $photo, 0 === $i ? 'url' : 'thumb', 0 === $i ? 'eager' : 'lazy', 0 === $i ? '(max-width: 720px) 100vw, 620px' : '(max-width: 720px) 50vw, 310px' ); ?>
						<?php if ( 0 === $i ) : ?>
							<?php tth_listing_tags( $item ); ?>
						<?php endif; ?>
					</button>
				<?php endforeach; ?>
			</div>
			<?php if ( count( $images ) > 1 ) : ?>
				<p class="tth-gallery-all">
					<button type="button" class="tal-btn tal-btn-secondary" data-tth-open-gallery="0">
						<?php
						/* translators: %d: number of photos. */
						echo esc_html( sprintf( __( 'View all %d photos', 'teatatu-homes-theme' ), count( $images ) ) );
						?>
					</button>
				</p>
				<dialog class="tth-lightbox" aria-label="<?php esc_attr_e( 'Photos', 'teatatu-homes-theme' ); ?>" data-tth-gallery>
					<div class="tth-lightbox__bar"><button type="button" class="tth-icon-btn" data-tth-close aria-label="<?php esc_attr_e( 'Close photos', 'teatatu-homes-theme' ); ?>"><?php echo tth_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button></div>
					<div class="tth-lightbox__list">
						<?php foreach ( $images as $i => $photo ) : ?>
							<div data-tth-photo="<?php echo (int) $i; ?>"><?php tth_photo( $photo, 'url', 'lazy', '(max-width: 1100px) 100vw, 1100px' ); ?></div>
						<?php endforeach; ?>
					</div>
				</dialog>
			<?php endif; ?>
		<?php endif; ?>

		<div class="tth-listing__cols">
			<div class="tth-listing__main">
				<header>
					<div class="tth-listing__title">
						<div>
							<h1 class="tth-display"><?php echo esc_html( $item['address']['line1'] ); ?></h1>
							<p class="tth-listing__locality"><?php echo esc_html( $item['area'] ? $item['area']['name'] . ' · ' . $item['address']['line2'] : $item['address']['line2'] ); ?></p>
						</div>
						<button type="button" class="tth-icon-btn" data-tth-share="<?php echo esc_url( $item['permalink'] ); ?>" aria-label="<?php esc_attr_e( 'Share this listing', 'teatatu-homes-theme' ); ?>"><?php echo tth_icon( 'share' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
					</div>
					<p class="tth-listing__price"><?php echo esc_html( $item['price_label'] ); ?></p>
					<?php if ( $item['title'] && $item['title'] !== $item['address']['line1'] ) : ?>
						<p class="tth-listing__headline"><?php echo esc_html( $item['title'] ); ?></p>
					<?php endif; ?>
				</header>

				<?php if ( $facts ) : ?>
					<dl class="tth-facts">
						<?php foreach ( $facts as $label => $value ) : ?>
							<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $value ); ?></dd></div>
						<?php endforeach; ?>
					</dl>
				<?php endif; ?>

				<?php if ( get_the_content() ) : ?>
					<section class="tth-block" aria-labelledby="tth-about">
						<h2 id="tth-about"><?php esc_html_e( 'About this home', 'teatatu-homes-theme' ); ?></h2>
						<div class="tth-prose"><?php the_content(); ?></div>
					</section>
				<?php endif; ?>

				<?php if ( $details ) : ?>
					<section class="tth-block" aria-labelledby="tth-details">
						<h2 id="tth-details"><?php echo esc_html( $item['rental'] ? __( 'Rental details', 'teatatu-homes-theme' ) : __( 'Sale details', 'teatatu-homes-theme' ) ); ?></h2>
						<dl class="tth-details">
							<?php foreach ( $details as $label => $value ) : ?>
								<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $value ); ?></dd></div>
							<?php endforeach; ?>
						</dl>
					</section>
				<?php endif; ?>

				<?php if ( $item['features'] ) : ?>
					<section class="tth-block" aria-labelledby="tth-features">
						<h2 id="tth-features"><?php esc_html_e( 'Features', 'teatatu-homes-theme' ); ?></h2>
						<ul class="tth-features">
							<?php foreach ( $item['features'] as $feature ) : ?>
								<li><span><?php echo tth_icon( 'check', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span><?php echo esc_html( $feature['name'] ); ?></li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>

				<section class="tth-block" aria-labelledby="tth-location">
					<h2 id="tth-location"><?php esc_html_e( 'On the Peninsula', 'teatatu-homes-theme' ); ?></h2>
					<?php if ( $item['area'] ) : ?>
						<p class="tth-muted">
							<?php
							$area_term = get_term( $item['area']['id'], 'teatatu_home_area' );
							echo esc_html( $item['area']['name'] . ( $area_term && $area_term->description ? ' — ' . $area_term->description : '' ) );
							?>
							<a href="<?php echo esc_url( $item['area']['link'] ); ?>">
								<?php
								/* translators: %s: area name. */
								echo esc_html( sprintf( __( 'More homes in %s', 'teatatu-homes-theme' ), $item['area']['name'] ) );
								?>
							</a>
						</p>
					<?php endif; ?>
					<?php if ( $item['address']['point'] ) : ?>
						<?php tth_map( tth_map_points( array( $item ) ), __( 'Map showing this home', 'teatatu-homes-theme' ), 'tth-map--short' ); ?>
						<p><a href="<?php echo esc_url( $item['address']['map_url'] ); ?>" rel="noopener" target="_blank"><?php esc_html_e( 'Open in OpenStreetMap', 'teatatu-homes-theme' ); ?></a></p>
					<?php elseif ( 'full' !== $item['address']['display'] ) : ?>
						<p class="tth-muted"><?php esc_html_e( 'The exact location is available from the agent.', 'teatatu-homes-theme' ); ?></p>
					<?php endif; ?>
				</section>

				<?php if ( $item['documents'] || $item['video_url'] || $item['tour_url'] || $item['external_url'] ) : ?>
					<section class="tth-block" aria-labelledby="tth-docs">
						<h2 id="tth-docs"><?php esc_html_e( 'Documents and links', 'teatatu-homes-theme' ); ?></h2>
						<div class="tth-docs">
							<?php foreach ( $item['documents'] as $doc ) : ?>
								<a class="tal-btn tal-btn-secondary" href="<?php echo esc_url( $doc['url'] ); ?>" rel="noopener" target="_blank"><?php echo tth_icon( 'doc', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $doc['label'] ); ?></a>
							<?php endforeach; ?>
							<?php if ( $item['video_url'] ) : ?>
								<a class="tal-btn tal-btn-secondary" href="<?php echo esc_url( $item['video_url'] ); ?>" rel="noopener" target="_blank"><?php esc_html_e( 'Video', 'teatatu-homes-theme' ); ?></a>
							<?php endif; ?>
							<?php if ( $item['tour_url'] ) : ?>
								<a class="tal-btn tal-btn-secondary" href="<?php echo esc_url( $item['tour_url'] ); ?>" rel="noopener" target="_blank"><?php esc_html_e( 'Virtual tour', 'teatatu-homes-theme' ); ?></a>
							<?php endif; ?>
							<?php if ( $item['external_url'] ) : ?>
								<a class="tal-btn tal-btn-secondary" href="<?php echo esc_url( $item['external_url'] ); ?>" rel="noopener" target="_blank"><?php esc_html_e( 'Original listing', 'teatatu-homes-theme' ); ?></a>
							<?php endif; ?>
						</div>
					</section>
				<?php endif; ?>
			</div>

			<aside class="tth-listing__aside" aria-label="<?php esc_attr_e( 'Open homes and contact', 'teatatu-homes-theme' ); ?>">
				<?php if ( $item['is_active'] ) : ?>
					<div class="tth-panel">
						<h2><?php echo esc_html( $item['rental'] ? __( 'Viewings', 'teatatu-homes-theme' ) : __( 'Open homes', 'teatatu-homes-theme' ) ); ?></h2>
						<?php if ( $item['open_homes'] ) : ?>
							<?php foreach ( $item['open_homes'] as $slot ) : ?>
								<div class="tth-slot"><div><strong><?php echo esc_html( $slot['date'] ); ?></strong><span><?php echo esc_html( $slot['time'] ); ?></span></div></div>
							<?php endforeach; ?>
						<?php else : ?>
							<p class="tth-muted"><?php esc_html_e( 'None scheduled. Ask the agent for a private viewing.', 'teatatu-homes-theme' ); ?></p>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<div class="tth-panel" id="enquire">
					<?php if ( $item['agent']['name'] || $item['agency'] ) : ?>
						<div class="tth-agent">
							<?php
							$photo = $item['agent']['photo_url'] ? $item['agent']['photo_url'] : ( $item['agency'] ? $item['agency']['logo_url'] : '' );
							if ( $photo ) :
								?>
								<img class="tth-agent__photo" src="<?php echo esc_url( $photo ); ?>" alt="" loading="lazy" referrerpolicy="no-referrer" />
							<?php endif; ?>
							<div>
								<?php if ( $item['agent']['name'] ) : ?>
									<div class="tth-agent__name"><?php echo esc_html( $item['agent']['name'] ); ?></div>
								<?php endif; ?>
								<?php if ( $item['agency'] ) : ?>
									<div class="tth-agent__agency"><?php echo esc_html( $item['agency']['name'] ); ?></div>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>

					<?php if ( $phone ) : ?>
						<a class="tal-btn tal-btn-secondary" href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', $phone ) ); ?>" data-tth-reveal="<?php echo esc_attr( $phone ); ?>">
							<?php echo tth_icon( 'phone', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
							<span data-tth-reveal-text><?php echo esc_html( $phone ); ?></span>
						</a>
					<?php endif; ?>

					<?php if ( $item['is_active'] ) : ?>
						<h2><?php esc_html_e( 'Send an enquiry', 'teatatu-homes-theme' ); ?></h2>
						<?php if ( $enquiry ) : ?>
							<p class="tal-notice <?php echo 'sent' === $enquiry ? 'tal-notice-success' : 'tal-notice-error'; ?>" role="<?php echo 'sent' === $enquiry ? 'status' : 'alert'; ?>"><?php echo tth_icon( 'sent' === $enquiry ? 'check' : 'alert' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span><?php echo esc_html( teatatu_homes_enquiry_message( $enquiry ) ); ?></span></p>
						<?php endif; ?>
						<?php if ( 'sent' !== $enquiry ) : ?>
							<form method="post" action="<?php echo esc_url( teatatu_homes_enquiry_action_url() ); ?>" class="tth-stack">
								<?php teatatu_homes_enquiry_hidden_fields( $item['id'] ); ?>
								<label class="tth-field"><?php esc_html_e( 'Your name', 'teatatu-homes-theme' ); ?>
									<input class="tth-control" type="text" name="name" autocomplete="name" required />
								</label>
								<label class="tth-field"><?php esc_html_e( 'Email', 'teatatu-homes-theme' ); ?>
									<input class="tth-control" type="email" name="email" autocomplete="email" required />
								</label>
								<label class="tth-field"><?php esc_html_e( 'Phone (optional)', 'teatatu-homes-theme' ); ?>
									<input class="tth-control" type="tel" name="phone" autocomplete="tel" />
								</label>
								<label class="tth-field"><?php esc_html_e( 'Message', 'teatatu-homes-theme' ); ?>
									<textarea class="tth-control" name="message" rows="4" required><?php echo esc_textarea( $item['rental'] ? __( "I'd like to view this rental.", 'teatatu-homes-theme' ) : __( "I'd like more information about this home.", 'teatatu-homes-theme' ) ); ?></textarea>
								</label>
								<button class="tal-btn tal-btn-primary tal-btn-block" type="submit"><?php echo esc_html( $item['rental'] ? __( 'Email the property manager', 'teatatu-homes-theme' ) : __( 'Email the agent', 'teatatu-homes-theme' ) ); ?></button>
							</form>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			</aside>
		</div>

		<?php
		$similar_filters = teatatu_homes_sanitize_filters(
			array(
				'listing' => $item['listing'],
				'area'    => $item['area'] ? $item['area']['slug'] : '',
			)
		);
		$similar         = array_values(
			array_filter(
				teatatu_homes_search( $similar_filters, 1, 4 )['items'],
				function ( $other ) use ( $item ) {
					return $other['id'] !== $item['id'];
				}
			)
		);
		$similar         = array_slice( $similar, 0, 3 );
		if ( $similar ) :
			?>
			<section class="tth-block" aria-labelledby="tth-similar">
				<h2 id="tth-similar" class="tth-h2">
					<?php
					echo esc_html(
						$item['area']
							/* translators: %s: area name. */
							? sprintf( __( 'More homes in %s', 'teatatu-homes-theme' ), $item['area']['name'] )
							: __( 'More homes on the Peninsula', 'teatatu-homes-theme' )
					);
					?>
				</h2>
				<div class="tth-card-grid">
					<?php foreach ( $similar as $other ) : ?>
						<?php tth_card( $other ); ?>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>
	</article>
	<?php
endwhile;

get_footer();
