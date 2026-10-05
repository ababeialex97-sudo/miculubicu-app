<?php
/**
 * Plugin settings with sensible defaults.
 *
 * Values live in WordPress options; the admin panel (step 4) will edit them.
 * Secrets never live in this repository.
 */

namespace MLB\AppApi;

defined( 'ABSPATH' ) || exit;

class Settings {

	public const OPTION_LOCATIONS          = 'mlb_app_api_locations';
	public const OPTION_FREE_DELIVERY_FROM = 'mlb_app_api_free_delivery_threshold';
	public const OPTION_TEST_MODE          = 'mlb_app_api_test_mode';
	public const OPTION_DELIVERY_INSTANCE  = 'mlb_app_api_delivery_instance_id';
	public const OPTION_PICKUP_INSTANCE    = 'mlb_app_api_pickup_instance_id';
	public const OPTION_JWT_SECRET         = 'mlb_app_api_jwt_secret';

	public const TEST_ORDER_NOTE = 'COMANDA DE TEST';

	/**
	 * Pickup/delivery points. Placeholder names until the client confirms them.
	 *
	 * Each location: id, name, address, phone, delivery (bool), pickup (bool),
	 * and optional delivery_fee / free_delivery_threshold overriding the global values.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_locations(): array {
		$defaults = array(
			array(
				'id'       => 'loc1',
				'name'     => 'Punct de lucru 1',
				'address'  => '',
				'phone'    => '',
				'delivery' => true,
				'pickup'   => true,
			),
			array(
				'id'       => 'loc2',
				'name'     => 'Punct de lucru 2',
				'address'  => '',
				'phone'    => '',
				'delivery' => true,
				'pickup'   => true,
			),
		);

		$locations = get_option( self::OPTION_LOCATIONS );
		if ( ! is_array( $locations ) || empty( $locations ) ) {
			$locations = $defaults;
		}

		return apply_filters( 'mlb_app_api_locations', array_values( $locations ) );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function get_location( string $id ): ?array {
		foreach ( self::get_locations() as $location ) {
			if ( ( $location['id'] ?? null ) === $id ) {
				return $location;
			}
		}
		return null;
	}

	/**
	 * Order subtotal from which delivery is free. 0 disables free delivery.
	 *
	 * @param array<string, mixed>|null $location
	 */
	public static function get_free_delivery_threshold( ?array $location = null ): float {
		if ( isset( $location['free_delivery_threshold'] ) ) {
			return max( 0.0, (float) $location['free_delivery_threshold'] );
		}
		return max( 0.0, (float) get_option( self::OPTION_FREE_DELIVERY_FROM, 0 ) );
	}

	/**
	 * While the site talks to the GrandChef TEST server, every app order is marked as a test order.
	 */
	public static function is_test_mode(): bool {
		return 'no' !== get_option( self::OPTION_TEST_MODE, 'yes' );
	}

	/**
	 * Shipping method used for home delivery (a flat_rate instance).
	 *
	 * GrandChef maps delivery methods by their WooCommerce title, so app orders must reuse
	 * the exact method configured in the shipping zone, never a made-up title.
	 */
	public static function get_delivery_method(): ?\WC_Shipping_Method {
		return self::find_shipping_method( self::OPTION_DELIVERY_INSTANCE, 'flat_rate' );
	}

	/**
	 * Shipping method used for pickup (a free_shipping instance).
	 */
	public static function get_pickup_method(): ?\WC_Shipping_Method {
		return self::find_shipping_method( self::OPTION_PICKUP_INSTANCE, 'free_shipping' );
	}

	/**
	 * Base delivery fee, read from the flat_rate instance cost unless the location overrides it.
	 *
	 * @param array<string, mixed>|null $location
	 */
	public static function get_delivery_fee( ?array $location = null ): float {
		if ( isset( $location['delivery_fee'] ) ) {
			return max( 0.0, (float) $location['delivery_fee'] );
		}

		$method = self::get_delivery_method();
		if ( ! $method ) {
			return 0.0;
		}

		$cost = (string) $method->get_option( 'cost', '0' );
		$cost = str_replace( ',', '.', $cost );
		return is_numeric( $cost ) ? max( 0.0, (float) $cost ) : 0.0;
	}

	public static function get_jwt_secret(): string {
		if ( defined( 'MLB_APP_API_JWT_SECRET' ) && '' !== MLB_APP_API_JWT_SECRET ) {
			return (string) MLB_APP_API_JWT_SECRET;
		}

		$secret = (string) get_option( self::OPTION_JWT_SECRET, '' );
		if ( '' === $secret ) {
			$secret = wp_generate_password( 64, true, true );
			update_option( self::OPTION_JWT_SECRET, $secret, false );
		}
		return $secret;
	}

	/**
	 * Uses the configured instance ID when set, otherwise the first enabled method of the
	 * given type across all shipping zones.
	 */
	private static function find_shipping_method( string $option, string $method_id ): ?\WC_Shipping_Method {
		$instance_id = (int) get_option( $option, 0 );
		if ( $instance_id > 0 ) {
			$method = \WC_Shipping_Zones::get_shipping_method( $instance_id );
			return $method instanceof \WC_Shipping_Method ? $method : null;
		}

		foreach ( \WC_Shipping_Zones::get_zones() as $zone ) {
			foreach ( $zone['shipping_methods'] as $method ) {
				if ( $method->id === $method_id && 'yes' === $method->enabled ) {
					return $method;
				}
			}
		}
		return null;
	}
}
