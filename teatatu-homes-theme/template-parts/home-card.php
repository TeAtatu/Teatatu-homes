<?php
/**
 * Listing card for grids. Expects $args['item'] — a normalised item from
 * teatatu_homes_normalize_item().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$item = $args['item'];
?>
<a class="tth-card" href="<?php echo esc_url( $item['permalink'] ); ?>">
	<div class="tth-card__media">
		<?php tth_photo( $item['photos'] ? $item['photos'][0] : null ); ?>
		<?php tth_listing_tags( $item ); ?>
	</div>
	<div class="tth-card__body">
		<div class="tth-card__price"><?php echo esc_html( $item['price_label'] ); ?></div>
		<div class="tth-card__address"><?php echo esc_html( $item['address']['line1'] ); ?></div>
		<?php tth_specs( $item ); ?>
		<?php if ( $item['note'] ) : ?>
			<div class="tth-card__note"><?php echo esc_html( $item['note'] ); ?></div>
		<?php endif; ?>
	</div>
</a>
