<?php
/**
 * Bootstraps the plugin: REST routes and cache/token housekeeping hooks.
 */

namespace MLB\AppApi;

defined( 'ABSPATH' ) || exit;

class Plugin {

	public const REST_NAMESPACE = 'mlb/v1';

	public static function init(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( self::class, 'missing_woocommerce_notice' ) );
			return;
		}

		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );

		// The cached menu must follow catalogue changes.
		foreach ( array( 'woocommerce_update_product', 'woocommerce_new_product', 'woocommerce_delete_product', 'woocommerce_trash_product', 'woocommerce_update_product_variation', 'woocommerce_product_set_stock_status', 'woocommerce_variation_set_stock_status', 'created_product_cat', 'edited_product_cat', 'delete_product_cat' ) as $hook ) {
			add_action( $hook, array( Rest\Menu_Controller::class, 'flush_cache' ) );
		}

		// A password change logs the customer out of the app on every device.
		add_action(
			'after_password_reset',
			static function ( \WP_User $user ): void {
				Auth::revoke_tokens( $user->ID );
			}
		);
		add_action(
			'profile_update',
			static function ( int $user_id, \WP_User $old_user ): void {
				$user = get_user_by( 'id', $user_id );
				if ( $user && $user->user_pass !== $old_user->user_pass ) {
					Auth::revoke_tokens( $user_id );
				}
			},
			10,
			2
		);
	}

	public static function register_routes(): void {
		$controllers = array(
			new Rest\Auth_Controller(),
			new Rest\Menu_Controller(),
			new Rest\Config_Controller(),
			new Rest\Orders_Controller(),
			new Rest\Push_Controller(),
		);

		foreach ( $controllers as $controller ) {
			$controller->register_routes( self::REST_NAMESPACE );
		}
	}

	public static function activate(): void {
		// Creates the signing secret up front unless it is defined in wp-config.php.
		Settings::get_jwt_secret();
	}

	public static function missing_woocommerce_notice(): void {
		echo '<div class="notice notice-error"><p>MLB App API are nevoie de WooCommerce activ.</p></div>';
	}
}
