<?php
/**
 * "Comenzi aplicație" panel under WooCommerce: live list of app orders with status buttons.
 * The page is a shell; assets/admin-orders.js renders it from the admin REST endpoints.
 */

namespace MLB\AppApi\Admin;

use MLB\AppApi\Plugin;
use MLB\AppApi\Settings;
use MLB\AppApi\Statuses;

defined( 'ABSPATH' ) || exit;

class Orders_Page {

	public const SLUG = 'mlb-app-orders';

	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	public static function register(): void {
		add_submenu_page( 'woocommerce', 'Comenzi aplicație', 'Comenzi aplicație', 'edit_shop_orders', self::SLUG, array( self::class, 'render' ) );
	}

	public static function enqueue( string $hook ): void {
		if ( 'woocommerce_page_' . self::SLUG !== $hook ) {
			return;
		}

		$url = plugins_url( 'assets/', MLB_APP_API_FILE );
		wp_enqueue_style( 'mlb-admin-orders', $url . 'admin-orders.css', array(), MLB_APP_API_VERSION );
		wp_enqueue_script( 'mlb-admin-orders', $url . 'admin-orders.js', array(), MLB_APP_API_VERSION, true );

		$locations = array();
		foreach ( Settings::get_locations() as $location ) {
			$locations[] = array(
				'id'   => (string) $location['id'],
				'name' => (string) $location['name'],
			);
		}

		wp_localize_script(
			'mlb-admin-orders',
			'mlbAdmin',
			array(
				'root'      => esc_url_raw( rest_url( Plugin::REST_NAMESPACE . '/admin/' ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'statuses'  => Statuses::labels(),
				'locations' => $locations,
				'testMode'  => Settings::is_test_mode(),
				'currency'  => get_woocommerce_currency_symbol(),
			)
		);
	}

	public static function render(): void {
		?>
		<div class="wrap mlb-orders">
			<h1>Comenzi aplicație</h1>
			<div id="mlb-orders-app" aria-live="polite"><p>Se încarcă…</p></div>
		</div>
		<?php
	}
}
