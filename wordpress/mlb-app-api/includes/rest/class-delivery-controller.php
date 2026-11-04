<?php
/**
 * Delivery fee for an address, shown in the cart before ordering.
 */

namespace MLB\AppApi\Rest;

use MLB\AppApi\Delivery_Quote;
use MLB\AppApi\Formatter;
use MLB\AppApi\Places;
use MLB\AppApi\Site_Orders;
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

		$session = array(
			'type'      => 'string',
			'default'   => '',
			'maxLength' => 64,
			'pattern'   => '^[A-Za-z0-9_-]*$',
		);

		register_rest_route(
			$namespace,
			'/delivery/places',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'places' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'q'           => array(
						'type'      => 'string',
						'required'  => true,
						'maxLength' => 120,
					),
					'location_id' => array(
						'type'    => 'string',
						'default' => '',
					),
					'session'     => $session,
				),
			)
		);

		register_rest_route(
			$namespace,
			'/delivery/places/(?P<place_id>[A-Za-z0-9_-]+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'place' ),
				'permission_callback' => '__return_true',
				'args'                => array( 'session' => $session ),
			)
		);
	}

	/**
	 * Address suggestions for what the customer typed, nearest to the location first.
	 */
	public function places( \WP_REST_Request $request ) {
		$location = Settings::get_location( (string) $request['location_id'] ) ?? Site_Orders::default_location( 'delivery' );
		$near     = $location ? Settings::location_point( $location ) : null;
		$result   = Places::suggest( (string) $request['q'], (string) $request['session'], $near );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * The chosen suggestion as street, town and point.
	 */
	public function place( \WP_REST_Request $request ) {
		$result = Places::details( (string) $request['place_id'], (string) $request['session'] );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
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
	 * The delivery address; lat/lng come from the address suggestion the customer picked.
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
