<?php
/**
 * WooCommerce shop, category and product pages.
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="mlb-container mlb-content mlb-shop">
	<?php woocommerce_content(); ?>
</div>
<?php
get_footer();
