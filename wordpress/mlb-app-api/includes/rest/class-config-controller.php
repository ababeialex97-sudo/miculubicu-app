<?php
/**
 * Public ordering configuration: locations, delivery fee, free delivery threshold, payment methods.
 */

namespace MLB\AppApi\Rest;

use MLB\AppApi\Formatter;
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
			);
		}

		return rest_ensure_response(
			array(
				'currency'        => get_woocommerce_currency(),
				'locations'       => $locations,
				'payment_methods' => $this->payment_methods(),
				'statuses'        => Statuses::labels(),
				'test_mode'       => Settings::is_test_mode(),
			)
		);
	}

	/**
	 * Only cash on delivery/pickup for now; online card payment is a separate offer.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function payment_methods(): array {
		$gateways = WC()->payment_gateways()->payment_gateways();
		$methods  = array();

		if ( isset( $gateways['cod'] ) && 'yes' === $gateways['cod']->enabled ) {
			$methods[] = array(
				'id'    => 'cod',
				'title' => $gateways['cod']->get_title(),
			);
		}

		return $methods;
	}
}
