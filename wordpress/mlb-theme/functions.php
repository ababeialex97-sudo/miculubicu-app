<?php
/**
 * Micu' lu' Bicu theme bootstrap.
 */

defined( 'ABSPATH' ) || exit;

define( 'MLB_THEME_VERSION', '1.0.0' );
define( 'MLB_THEME_DIR', __DIR__ );
define( 'MLB_THEME_URL', get_template_directory_uri() );

require_once __DIR__ . '/inc/setup.php';
require_once __DIR__ . '/inc/locations.php';
require_once __DIR__ . '/inc/woocommerce.php';
require_once __DIR__ . '/inc/elementor.php';
require_once __DIR__ . '/inc/kit.php';
require_once __DIR__ . '/inc/installer.php';
