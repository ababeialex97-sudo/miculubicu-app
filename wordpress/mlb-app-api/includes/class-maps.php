<?php
/**
 * Google Maps Platform calls: finding a delivery address (Geocoding API) and the road route
 * to it (Routes API), with or without live traffic.
 *
 * The API key lives in the MLB_APP_API_MAPS_KEY constant or in the plugin settings, never in
 * the app: every call goes through this server.
 */

namespace MLB\AppApi;

defined( 'ABSPATH' ) || exit;

class Maps {

	private const GEOCODE_URL = 'https://maps.googleapis.com/maps/api/geocode/json';
	private const ROUTES_URL  = 'https://routes.googleapis.com/directions/v2:computeRoutes';

	/** Short, so a slow Google answer never holds up the cart or an order. */
	private const TIMEOUT = 6;

	/** Result types that point at a building. */
	private const EXACT_TYPES = array( 'street_address', 'premise', 'subpremise', 'establishment', 'point_of_interest' );

	/** Result types that only point at the street. */
	private const STREET_TYPES = array( 'route', 'intersection' );

	public static function api_key(): string {
		if ( defined( 'MLB_APP_API_MAPS_KEY' ) && '' !== MLB_APP_API_MAPS_KEY ) {
			return (string) MLB_APP_API_MAPS_KEY;
		}
		return (string) get_option( Settings::OPTION_MAPS_KEY, '' );
	}

	public static function is_configured(): bool {
		return '' !== self::api_key();
	}

	/**
	 * The point for a written address. `precise` is false when Google found the street but not
	 * the number; finding only the town counts as not found, since the distance would be a guess.
	 *
	 * @return array{lat: float, lng: float, formatted_address: string, precise: bool}|\WP_Error
	 */
	public static function geocode( string $address ) {
		$url = add_query_arg(
			array(
				'address'    => rawurlencode( $address ),
				'components' => 'country:RO',
				'region'     => 'ro',
				'language'   => 'ro',
				'key'        => rawurlencode( self::api_key() ),
			),
			self::GEOCODE_URL
		);

		$data = self::request( wp_remote_get( $url, array( 'timeout' => self::TIMEOUT ) ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$status = (string) ( $data['status'] ?? '' );
		if ( 'ZERO_RESULTS' === $status ) {
			return new \WP_Error( 'mlb_address_not_found', 'Nu am găsit adresa pe hartă.' );
		}
		if ( 'OK' !== $status || empty( $data['results'][0]['geometry']['location'] ) ) {
			return new \WP_Error( 'mlb_maps_error', 'Geocoding: ' . $status . ' ' . (string) ( $data['error_message'] ?? '' ) );
		}

		$result = $data['results'][0];
		$types  = (array) ( $result['types'] ?? array() );
		if ( ! array_intersect( array_merge( self::EXACT_TYPES, self::STREET_TYPES ), $types ) ) {
			return new \WP_Error( 'mlb_address_not_found', 'Google a găsit doar localitatea.' );
		}

		return array(
			'lat'               => (float) $result['geometry']['location']['lat'],
			'lng'               => (float) $result['geometry']['location']['lng'],
			'formatted_address' => (string) ( $result['formatted_address'] ?? '' ),
			// The app shows formatted_address, so the customer can spot a street Google guessed wrong.
			'precise'           => (bool) array_intersect( self::EXACT_TYPES, $types ),
		);
	}

	/**
	 * Driving route between two points. With $traffic the duration counts the traffic right now.
	 *
	 * @param array{lat: float, lng: float} $from
	 * @param array{lat: float, lng: float} $to
	 * @return array{meters: int, seconds: int}|\WP_Error
	 */
	public static function route( array $from, array $to, bool $traffic ) {
		$body = array(
			'origin'            => self::waypoint( $from ),
			'destination'       => self::waypoint( $to ),
			'travelMode'        => 'DRIVE',
			'routingPreference' => $traffic ? 'TRAFFIC_AWARE' : 'TRAFFIC_UNAWARE',
			'languageCode'      => 'ro',
			'units'             => 'METRIC',
		);

		$response = wp_remote_post(
			self::ROUTES_URL,
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array(
					'Content-Type'     => 'application/json',
					'X-Goog-Api-Key'   => self::api_key(),
					'X-Goog-FieldMask' => 'routes.distanceMeters,routes.duration',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		$data = self::request( $response );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		if ( empty( $data['routes'][0] ) ) {
			return new \WP_Error( 'mlb_route_not_found', 'Nu există drum până la adresă.' );
		}

		$route = $data['routes'][0];
		return array(
			'meters'  => (int) ( $route['distanceMeters'] ?? 0 ),
			'seconds' => (int) rtrim( (string) ( $route['duration'] ?? '0s' ), 's' ),
		);
	}

	/**
	 * @param array{lat: float, lng: float} $point
	 * @return array<string, mixed>
	 */
	private static function waypoint( array $point ): array {
		return array(
			'location' => array(
				'latLng' => array(
					'latitude'  => $point['lat'],
					'longitude' => $point['lng'],
				),
			),
		);
	}

	/**
	 * @param array<string, mixed>|\WP_Error $response
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function request( $response ) {
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'mlb_maps_unreachable', 'Google Maps nu răspunde: ' . $response->get_error_message() );
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 400 || ! is_array( $data ) ) {
			$message = is_array( $data ) ? (string) ( $data['error']['message'] ?? '' ) : '';
			return new \WP_Error( 'mlb_maps_error', sprintf( 'Google Maps a răspuns cu %d. %s', $code, $message ) );
		}
		return $data;
	}
}
