<?php
/**
 * Public ordering configuration: locations, delivery fee, free delivery threshold, payment methods.
 */

namespace MLB\AppApi\Rest;

use MLB\AppApi\Formatter;
use MLB\AppApi\Maps;
use MLB\AppApi\Settings;
use MLB\AppApi\Statuses;

defined( 'ABSPATH' ) || exit;

class Config_Controller {

	public function register_routes( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/config',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_config' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function get_config() {
		$delivery_available = null !== Settings::get_delivery_method();
		$pickup_available   = null !== Settings::get_pickup_method();

		$bands       = Settings::get_distance_bands();
		$maps_ready  = Maps::is_configured();
		$price_bands = array_map(
			static fn ( array $band ): array => array(
				'up_to_km' => $band['up_to_km'],
				'fee'      => Formatter::money( $band['fee'] ),
			),
			$bands
		);

		$locations = array();
		foreach ( Settings::get_locations() as $location ) {
			$locations[] = array(
				'id'                      => (string) $location['id'],
				'name'                    => (string) $location['name'],
				'address'                 => (string) ( $location['address'] ?? '' ),
				'phone'                   => (string) ( $location['phone'] ?? '' ),
				'delivery'                => $delivery_available && ! empty( $location['delivery'] ),
				'pickup'                  => $pickup_available && ! empty( $location['pickup'] ),
				'delivery_fee'            => Formatter::money( Settings::get_delivery_fee( $location ) ),
				'free_delivery_threshold' => Formatter::money( Settings::get_free_delivery_threshold( $location ) ),
				// When true the fee depends on the address: ask /delivery/quote.
				'distance_pricing'        => $bands && $maps_ready && null !== Settings::location_point( $location ),
				'delivery_bands'          => $price_bands,
			);
		}

		return rest_ensure_response(
			array(
				'currency'        => get_woocommerce_currency(),
				'locations'       => $locations,
				'payment_methods' => $this->payment_methods(),
				'statuses'        => Statuses::labels(),
				'test_mode'       => Settings::is_test_mode(),
				'guest_checkout'  => Settings::guest_checkout_enabled(),
				'social_login'    => array(
					'google' => ! empty( Settings::google_client_ids() ),
					'apple'  => ! empty( Settings::apple_client_ids() ),
				),
			)
		);
	}

	/**
	 * Offline methods only: cash or card on delivery/pickup. Online card payment is a separate offer.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function payment_methods(): array {
		$gateways = WC()->payment_gateways()->payment_gateways();
		$methods  = array();

		foreach ( Orders_Controller::PAYMENT_METHODS as $id ) {
			if ( isset( $gateways[ $id ] ) && 'yes' === $gateways[ $id ]->enabled ) {
				$methods[] = array(
					'id'    => $id,
					'title' => $gateways[ $id ]->get_title(),
				);
			}
		}

		return $methods;
	}
}
