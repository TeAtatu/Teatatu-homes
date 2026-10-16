<?php
/**
 * Fallback template (blog posts, search results and anything without a
 * more specific template).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
get_header();
?>
<div class="tth-wrap tth-page">
	<?php if ( is_home() || is_archive() || is_search() ) : ?>
		<h1><?php echo esc_html( is_search() ? sprintf( /* translators: %s: search terms. */ __( 'Search: %s', 'teatatu-homes-theme' ), get_search_query() ) : wp_strip_all_tags( get_the_archive_title() ) ); ?></h1>
	<?php endif; ?>
	<?php if ( have_posts() ) : ?>
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article <?php post_class( 'tth-post' ); ?>>
				<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
				<div class="entry-content"><?php the_excerpt(); ?></div>
			</article>
		<?php endwhile; ?>
		<nav class="tth-pagination" aria-label="<?php esc_attr_e( 'Pages', 'teatatu-homes-theme' ); ?>"><div class="nav-links"><?php echo paginate_links(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped. ?></div></nav>
	<?php else : ?>
		<p><?php esc_html_e( 'Nothing found.', 'teatatu-homes-theme' ); ?></p>
	<?php endif; ?>
</div>
<?php
get_footer();
