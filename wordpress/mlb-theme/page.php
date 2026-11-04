<?php
/**
 * Pages. Elementor pages render their own full-width layout; classic pages get a readable column.
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$mlb_is_elementor = mlb_theme_elementor_ready() && \Elementor\Plugin::instance()->documents->get( get_the_ID() )?->is_built_with_elementor();
	if ( $mlb_is_elementor ) :
		the_content();
	else :
		?>
		<div class="mlb-container mlb-content">
			<article <?php post_class( 'mlb-entry' ); ?>>
				<?php if ( ! is_front_page() ) : ?>
					<h1 class="mlb-page-title"><?php the_title(); ?></h1>
				<?php endif; ?>
				<div class="mlb-entry__content"><?php the_content(); ?></div>
			</article>
		</div>
		<?php
	endif;
endwhile;

get_footer();
