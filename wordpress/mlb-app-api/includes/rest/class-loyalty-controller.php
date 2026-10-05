<?php
/**
 * Loyalty card and coupons for the logged-in customer, and the cart price check.
 */

namespace MLB\AppApi\Rest;

use MLB\AppApi\Auth;
use MLB\AppApi\Coupons;
use MLB\AppApi\Formatter;
use MLB\AppApi\Loyalty;
use MLB\AppApi\Loyalty_Rules;
use MLB\AppApi\Settings;

defined( 'ABSPATH' ) || exit;

class Loyalty_Controller {

	public function register_routes( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/loyalty',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_loyalty' ),
				'permission_callback' => array( Auth::class, 'require_customer' ),
			)
		);

		register_rest_route(
			$namespace,
			'/cart/preview',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'preview' ),
				'permission_callback' => array( Auth::class, 'require_customer' ),
				'args'                => array(
					'items'        => Orders_Controller::items_schema(),
					'coupon_codes' => Orders_Controller::coupon_codes_schema(),
					'fulfillment'  => array(
						'type'    => 'string',
						'enum'    => array( 'delivery', 'pickup' ),
						'default' => 'delivery',
					),
					'location_id'  => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
	}

	public function get_loyalty() {
		$settings    = Loyalty::settings();
		$customer_id = get_current_user_id();

		return rest_ensure_response(
			array(
				'enabled'      => $settings['enabled'],
				'stamps'       => Loyalty::stamps( $customer_id ),
				'required'     => $settings['required'],
				'reward_label' => Loyalty_Rules::amount_label( $settings['reward_type'], $settings['reward_amount'] ),
				'coupons'      => Coupons::for_customer( $customer_id ),
			)
		);
	}

	/**
	 * Server-side totals for the cart, including coupon discounts, before the order is placed.
	 */
	public function preview( \WP_REST_Request $request ) {
		$lines = Orders_Controller::resolve_lines( (array) $request['items'] );
		if ( is_wp_error( $lines ) ) {
			return $lines;
		}

		$customer = new \WC_Customer( get_current_user_id() );
		$result   = Coupons::preview( $lines, (array) $request['coupon_codes'], $customer );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$subtotal = 0.0;
		foreach ( $lines as $line ) {
			$subtotal += (float) wc_get_price_to_display( $line['product'] ) * $line['quantity'];
		}

		$shipping = 0.0;
		$location = Settings::get_location( (string) $request['location_id'] );
		if ( 'delivery' === $request['fulfillment'] ) {
			$shipping = Orders_Controller::delivery_cost( $location ?? array(), $subtotal );
		}

		return rest_ensure_response(
			array(
				'subtotal' => Formatter::money( $subtotal ),
				'discount' => Formatter::money( $result['discount'] ),
				'shipping' => Formatter::money( $shipping ),
				'total'    => Formatter::money( max( 0.0, $subtotal - $result['discount'] ) + $shipping ),
				'coupons'  => $result['coupons'],
			)
		);
	}
}
