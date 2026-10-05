<?php
/**
 * Plugin Name:       MLB App API
 * Description:       REST API for the Micu' lu' Bicu mobile app (menu, customer auth, orders, push tokens).
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            Micu' lu' Bicu
 * Text Domain:       mlb-app-api
 */

defined( 'ABSPATH' ) || exit;

define( 'MLB_APP_API_VERSION', '0.1.0' );
define( 'MLB_APP_API_FILE', __FILE__ );
define( 'MLB_APP_API_DIR', __DIR__ );

require_once __DIR__ . '/includes/class-jwt.php';
require_once __DIR__ . '/includes/class-settings.php';
require_once __DIR__ . '/includes/class-statuses.php';
require_once __DIR__ . '/includes/class-auth.php';
require_once __DIR__ . '/includes/class-formatter.php';
require_once __DIR__ . '/includes/rest/class-auth-controller.php';
require_once __DIR__ . '/includes/rest/class-menu-controller.php';
require_once __DIR__ . '/includes/rest/class-config-controller.php';
require_once __DIR__ . '/includes/rest/class-orders-controller.php';
require_once __DIR__ . '/includes/rest/class-push-controller.php';
require_once __DIR__ . '/includes/class-plugin.php';

register_activation_hook( __FILE__, array( \MLB\AppApi\Plugin::class, 'activate' ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

add_action( 'plugins_loaded', array( \MLB\AppApi\Plugin::class, 'init' ) );
