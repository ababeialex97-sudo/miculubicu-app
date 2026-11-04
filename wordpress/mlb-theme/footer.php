<?php
/**
 * Site footer: the Elementor footer template, or a minimal fallback.
 */

defined( 'ABSPATH' ) || exit;
?>
</main>
<div class="mlb-site-footer">
	<?php
	if ( ! mlb_theme_do_location( 'footer' ) ) {
		mlb_theme_fallback_footer();
	}
	?>
</div>
<?php wp_footer(); ?>
</body>
</html>
