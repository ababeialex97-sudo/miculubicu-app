<?php
/**
 * Address suggestions while the customer types (Google Places API, New), so the delivery
 * address is a real place on the map and the distance price is exact.
 *
 * The key stays on the server: the app and the site ask /delivery/places and get back only
 * the suggestions. A session token from the client groups the typing and the final choice,
 * which Google bills as one session.
 */

namespace MLB\AppApi;

defined( 'ABSPATH' ) || exit;

class Places {

	private const AUTOCOMPLETE_URL = 'https://places.googleapis.com/v1/places:autocomplete';
	private const DETAILS_URL      = 'https://places.googleapis.com/v1/places/';

	/** Suggestions favour places this close to the location (meters). */
	private const BIAS_RADIUS = 20000.0;

	/** Typing makes many calls: a looser hourly limit than the price lookups. */
	private const HOURLY_LIMIT = 300;

	private const TIMEOUT = 5;

	/**
	 * @param array{lat: float, lng: float}|null $near
	 * @return array<int, array{place_id: string, main: string, secondary: string}>|\WP_Error
	 */
	public static function suggest( string $input, string $session, ?array $near ) {
		$input = trim( $input );
		if ( mb_strlen( $input ) < 3 || ! Maps::is_configured() ) {
			return array();
		}

		$key    = 'mlb_pl_' . md5( wp_json_encode( array( mb_strtolower( $input ), $near ) ) );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		if ( Delivery_Quote::is_rate_limited( 'places', self::HOURLY_LIMIT ) ) {
			return new \WP_Error( 'mlb_maps_rate_limited', 'Prea multe căutări. Scrie adresa întreagă.', array( 'status' => 429 ) );
		}

		$body = array(
			'input'               => $input,
			'languageCode'        => 'ro',
			'regionCode'          => 'ro',
			'includedRegionCodes' => array( 'ro' ),
		);
		if ( '' !== $session ) {
			$body['sessionToken'] = $session;
		}
		if ( $near ) {
			$body['locationBias'] = array(
				'circle' => array(
					'center' => array(
						'latitude'  => $near['lat'],
						'longitude' => $near['lng'],
					),
					'radius' => self::BIAS_RADIUS,
				),
			);
		}

		$data = Maps::request(
			wp_remote_post(
				self::AUTOCOMPLETE_URL,
				array(
					'timeout' => self::TIMEOUT,
					'headers' => array(
						'Content-Type'   => 'application/json',
						'X-Goog-Api-Key' => Maps::api_key(),
					),
					'body'    => wp_json_encode( $body ),
				)
			)
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$suggestions = self::parse_suggestions( $data );
		set_transient( $key, $suggestions, DAY_IN_SECONDS );
		return $suggestions;
	}

	/**
	 * The chosen place as an address the order can use.
	 *
	 * @return array{address_1: string, city: string, lat: float, lng: float, formatted_address: string, precise: bool}|\WP_Error
	 */
	public static function details( string $place_id, string $session ) {
		if ( ! preg_match( '/^[A-Za-z0-9_-]{10,300}$/', $place_id ) ) {
			return new \WP_Error( 'mlb_invalid_place', 'Adresa aleasă nu mai e valabilă. Caut-o din nou.', array( 'status' => 400 ) );
		}

		$key    = 'mlb_pd_' . md5( $place_id );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		if ( Delivery_Quote::is_rate_limited( 'places', self::HOURLY_LIMIT ) ) {
			return new \WP_Error( 'mlb_maps_rate_limited', 'Prea multe căutări. Scrie adresa întreagă.', array( 'status' => 429 ) );
		}

		$url  = add_query_arg( array_filter( array( 'languageCode' => 'ro', 'sessionToken' => $session ) ), self::DETAILS_URL . rawurlencode( $place_id ) );
		$data = Maps::request(
			wp_remote_get(
				$url,
				array(
					'timeout' => self::TIMEOUT,
					'headers' => array(
						'X-Goog-Api-Key'   => Maps::api_key(),
						'X-Goog-FieldMask' => 'location,formattedAddress,addressComponents,types,displayName',
					),
				)
			)
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$place = self::parse_place( $data );
		if ( null === $place ) {
			return new \WP_Error( 'mlb_address_not_found', 'Nu am găsit locul pe hartă.', array( 'status' => 404 ) );
		}
		set_transient( $key, $place, 30 * DAY_IN_SECONDS );
		self::remember( $place );
		return $place;
	}

	/**
	 * The point of an address someone picked from the suggestions, when the same street,
	 * number and town come back as text (the website checkout only sends text).
	 *
	 * @return array{lat: float, lng: float}|null
	 */
	public static function remembered_point( string $street, string $city ): ?array {
		$point = get_transient( self::memory_key( $street, $city ) );
		return is_array( $point ) && isset( $point['lat'], $point['lng'] ) ? $point : null;
	}

	/**
	 * @param array{address_1: string, city: string, lat: float, lng: float, precise: bool} $place
	 */
	private static function remember( array $place ): void {
		if ( $place['precise'] && '' !== $place['address_1'] ) {
			set_transient(
				self::memory_key( $place['address_1'], $place['city'] ),
				array(
					'lat' => $place['lat'],
					'lng' => $place['lng'],
				),
				30 * DAY_IN_SECONDS
			);
		}
	}

	public static function memory_key( string $street, string $city ): string {
		$text = mb_strtolower( remove_accents( trim( $street ) . '|' . trim( $city ) ) );
		return 'mlb_pm_' . md5( (string) preg_replace( '/[\s,.]+/', ' ', $text ) );
	}

	/**
	 * @param array<string, mixed> $data Autocomplete response.
	 * @return array<int, array{place_id: string, main: string, secondary: string}>
	 */
	public static function parse_suggestions( array $data ): array {
		$out = array();
		foreach ( (array) ( $data['suggestions'] ?? array() ) as $suggestion ) {
			$prediction = $suggestion['placePrediction'] ?? null;
			if ( ! is_array( $prediction ) || empty( $prediction['placeId'] ) ) {
				continue;
			}
			$main      = (string) ( $prediction['structuredFormat']['mainText']['text'] ?? $prediction['text']['text'] ?? '' );
			$secondary = (string) ( $prediction['structuredFormat']['secondaryText']['text'] ?? '' );
			$out[]     = array(
				'place_id'  => (string) $prediction['placeId'],
				'main'      => $main,
				'secondary' => preg_replace( '/,?\s*(România|Romania)$/u', '', $secondary ),
			);
		}
		return $out;
	}

	/**
	 * Street and number for address_1 (or the place's name when it has no street, such as a
	 * hospital), the town, and the point.
	 *
	 * @param array<string, mixed> $data Place details response.
	 * @return array{address_1: string, city: string, lat: float, lng: float, formatted_address: string, precise: bool}|null
	 */
	public static function parse_place( array $data ): ?array {
		if ( ! isset( $data['location']['latitude'], $data['location']['longitude'] ) ) {
			return null;
		}

		$parts = array();
		foreach ( (array) ( $data['addressComponents'] ?? array() ) as $component ) {
			foreach ( (array) ( $component['types'] ?? array() ) as $type ) {
				$parts[ $type ] = $parts[ $type ] ?? (string) ( $component['longText'] ?? '' );
			}
		}

		$street = trim( ( $parts['route'] ?? '' ) . ' ' . ( $parts['street_number'] ?? '' ) );
		$name   = (string) ( $data['displayName']['text'] ?? '' );
		$types  = (array) ( $data['types'] ?? array() );
		$is_poi = ! array_intersect( array( 'street_address', 'premise', 'subpremise', 'route' ), $types );
		if ( '' === $street || ( $is_poi && '' !== $name && empty( $parts['street_number'] ) ) ) {
			$street = trim( $name . ( '' !== $street ? ', ' . $street : '' ) );
		}

		return array(
			'address_1'         => $street,
			'city'              => $parts['locality'] ?? $parts['administrative_area_level_2'] ?? '',
			'lat'               => (float) $data['location']['latitude'],
			'lng'               => (float) $data['location']['longitude'],
			'formatted_address' => (string) ( $data['formattedAddress'] ?? '' ),
			// A building, or a named place such as a hospital, is exact; a bare street is not.
			'precise'           => ! empty( $parts['street_number'] ) || $is_poi,
		);
	}
}
