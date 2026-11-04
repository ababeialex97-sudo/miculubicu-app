<?php
/**
 * Delivery fee for an address, shown in the cart before ordering.
 */

namespace MLB\AppApi\Rest;

use MLB\AppApi\Delivery_Quote;
use MLB\AppApi\Formatter;
use MLB\AppApi\Settings;

defined( 'ABSPATH' ) || exit;

class Delivery_Controller {

	public function register_routes( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/delivery/quote',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'quote' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'location_id' => array(
						'type'     => 'string',
						'required' => true,
					),
					'address'     => self::address_schema(),
				),
			)
		);
	}

	public function quote( \WP_REST_Request $request ) {
		$location = Settings::get_location( (string) $request['location_id'] );
		if ( ! $location ) {
			return new \WP_Error( 'mlb_invalid_location', 'Punctul de lucru ales nu există.', array( 'status' => 400 ) );
		}

		return rest_ensure_response( self::payload( Delivery_Quote::quote( $location, (array) $request['address'] ) ) );
	}

	/**
	 * @param array<string, mixed> $quote
	 * @return array<string, mixed>
	 */
	public static function payload( array $quote ): array {
		return array(
			'mode'              => $quote['mode'],
			'available'         => $quote['available'],
			'fee'               => null === $quote['fee'] ? null : Formatter::money( $quote['fee'] ),
			'distance_km'       => $quote['distance_km'],
			'verified'          => $quote['verified'],
			'formatted_address' => $quote['formatted_address'],
			'message'           => $quote['message'],
		);
	}

	/**
	 * The delivery address; lat/lng come from the phone's location when the customer shares it.
	 *
	 * @return array<string, mixed>
	 */
	public static function address_schema(): array {
		return array(
			'type'       => 'object',
			'default'    => array(),
			'properties' => array(
				'address_1' => array(
					'type'      => 'string',
					'maxLength' => 200,
				),
				'address_2' => array(
					'type'      => 'string',
					'maxLength' => 200,
				),
				'city'      => array(
					'type'      => 'string',
					'maxLength' => 100,
				),
				'lat'       => array(
					'type'    => 'number',
					'minimum' => -90,
					'maximum' => 90,
				),
				'lng'       => array(
					'type'    => 'number',
					'minimum' => -180,
					'maximum' => 180,
				),
			),
		);
	}
}
