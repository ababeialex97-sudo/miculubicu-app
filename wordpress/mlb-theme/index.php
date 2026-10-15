<?php
/**
 * Generic template: blog index, archives and anything without a more specific template.
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="mlb-container mlb-content">
	<?php if ( is_home() || is_archive() || is_search() ) : ?>
		<h1 class="mlb-page-title"><?php echo esc_html( is_search() ? sprintf( /* translators: %s: search term */ __( 'Rezultate pentru „%s”', 'mlb-theme' ), get_search_query() ) : wp_strip_all_tags( get_the_archive_title() ) ); ?></h1>
	<?php endif; ?>

	<?php if ( have_posts() ) : ?>
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article <?php post_class( 'mlb-entry' ); ?>>
				<?php if ( is_singular() ) : ?>
					<h1 class="mlb-page-title"><?php the_title(); ?></h1>
					<div class="mlb-entry__content"><?php the_content(); ?></div>
				<?php else : ?>
					<h2 class="mlb-entry__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<div class="mlb-entry__excerpt"><?php the_excerpt(); ?></div>
				<?php endif; ?>
			</article>
		<?php endwhile; ?>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<p><?php esc_html_e( 'Nu am găsit nimic aici.', 'mlb-theme' ); ?></p>
	<?php endif; ?>
</div>
<?php
get_footer();
