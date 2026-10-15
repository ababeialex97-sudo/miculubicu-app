<?php
defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="mlb-container mlb-content mlb-404">
	<h1 class="mlb-page-title"><?php esc_html_e( 'Pagina nu există', 'mlb-theme' ); ?></h1>
	<p><?php esc_html_e( 'Poate te interesează meniul nostru.', 'mlb-theme' ); ?></p>
	<p><a class="mlb-btn mlb-btn--primary" href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) ); ?>"><?php esc_html_e( 'Vezi meniul', 'mlb-theme' ); ?></a></p>
</div>
<?php
get_footer();
