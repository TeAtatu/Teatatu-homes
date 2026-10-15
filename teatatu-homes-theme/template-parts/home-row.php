<?php
/**
 * Listing row for search results. Expects $args['item'] — a normalised
 * item from teatatu_homes_normalize_item().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$item   = $args['item'];
$badges = array();
if ( $item['note'] ) {
	$badges[] = $item['note'];
}
if ( $item['rental'] ) {
	$badges[] = $item['rental']['pets_label'];
	if ( $item['rental']['healthy_homes'] ) {
		$badges[] = __( 'Healthy Homes', 'teatatu-homes-theme' );
	}
}
if ( $item['area'] ) {
	$badges[] = $item['area']['name'];
}
if ( $item['agency'] ) {
	$badges[] = $item['agency']['name'];
}
?>
<a class="tth-card tth-row" href="<?php echo esc_url( $item['permalink'] ); ?>">
	<div class="tth-card__media">
		<?php tth_photo( $item['photos'] ? $item['photos'][0] : null ); ?>
		<?php tth_listing_tags( $item ); ?>
	</div>
	<div class="tth-card__body">
		<div class="tth-card__price"><?php echo esc_html( $item['price_label'] ); ?></div>
		<div class="tth-card__address"><?php echo esc_html( $item['address']['line1'] ); ?></div>
		<?php tth_specs( $item ); ?>
		<?php if ( $item['excerpt'] ) : ?>
			<p class="tth-row__excerpt"><?php echo esc_html( wp_trim_words( $item['excerpt'], 30 ) ); ?></p>
		<?php endif; ?>
		<?php if ( $badges ) : ?>
			<div class="tth-badges">
				<?php foreach ( $badges as $badge ) : ?>
					<span class="tth-badge"><?php echo esc_html( $badge ); ?></span>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</a>
