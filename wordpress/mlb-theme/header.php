<?php
/**
 * Site header: the Elementor header template, or a minimal fallback.
 */

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#232323">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="mlb-skip-link" href="#continut"><?php esc_html_e( 'Sari la conținut', 'mlb-theme' ); ?></a>
<div class="mlb-site-header" data-mlb-header>
	<?php
	if ( ! mlb_theme_do_location( 'header' ) ) {
		mlb_theme_fallback_header();
	}
	?>
</div>
<main id="continut" class="mlb-site-main">
