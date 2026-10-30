<?php
/**
 * Delivery fee for an address, from the road distance to the chosen location, and the
 * courier's arrival time with live traffic.
 *
 * Distance pricing is on when the settings have price bands, a Maps API key and the location's
 * coordinates; otherwise every address pays the location's flat delivery fee, as before.
 * An address Google can't place exactly also pays the flat fee: an order is never blocked
 * because of the map.
 */

namespace MLB\AppApi;

defined( 'ABSPATH' ) || exit;

class Delivery_Quote {

	public const DEST_META     = '_mlb_delivery_point';
	public const DISTANCE_META = '_mlb_delivery_km';
	public const ETA_META      = '_mlb_eta_at';

	private const CACHE_PREFIX = 'mlb_dq_';
	private const CACHE_TTL    = MONTH_IN_SECONDS;

	/** Google lookups one network address may trigger per hour; cached addresses don't count. */
	private const LOOKUP_LIMIT = 40;

	/**
	 * @param array<string, mixed> $location
	 * @param array<string, mixed> $address address_1, city, and optionally lat/lng from the phone's GPS.
	 * @return array{mode: string, available: bool, fee: float|null, distance_km: float|null, verified: bool, formatted_address: string, message: string, point: array{lat: float, lng: float}|null}
	 */
	public static function quote( array $location, array $address ): array {
		$flat  = Settings::get_delivery_fee( $location );
		$bands = Settings::get_distance_bands();
		$from  = Settings::location_point( $location );

		if ( ! $bands || ! $from || ! Maps::is_configured() ) {
			return self::result( 'flat', true, $flat );
		}

		$point = self::point_from( $address );
		$text  = self::address_text( $address );
		if ( ! $point && '' === $text ) {
			return self::result( 'distance', true, null, null, false, '', 'Scrie adresa ca să calculăm costul livrării.' );
		}

		$place = self::locate( $from, $point, $text );
		if ( is_wp_error( $place ) ) {
			$message = 'mlb_address_not_found' === $place->get_error_code()
				? 'Nu găsim adresa pe hartă, așa că se aplică tariful standard. Verifică strada și numărul.'
				: '';
			return self::result( 'distance', true, $flat, null, false, '', $message );
		}

		$km  = round( $place['meters'] / 1000, 1 );
		$fee = Distance_Pricing::fee_for( $km, $bands );
		if ( null === $fee ) {
			return self::result(
				'distance',
				false,
				null,
				$km,
				true,
				$place['formatted_address'],
				sprintf( 'Livrăm până la %s, iar adresa ta e la %s.', Distance_Pricing::km_label( Distance_Pricing::max_km( $bands ) ), Distance_Pricing::km_label( $km ) ),
				$place['point']
			);
		}

		if ( ! $place['precise'] ) {
			return self::result( 'distance', true, $fee, $km, false, $place['formatted_address'], 'Am găsit doar strada, nu și numărul. Verifică adresa.', $place['point'] );
		}

		return self::result( 'distance', true, $fee, $km, true, $place['formatted_address'], '', $place['point'] );
	}

	/**
	 * Minutes from the location to the order's address with traffic right now, or null when
	 * the order has no point on the map or Google can't be reached.
	 */
	public static function eta_minutes( \WC_Order $order ): ?int {
		$location = Settings::get_location( (string) $order->get_meta( '_mlb_location_id' ) );
		$from     = $location ? Settings::location_point( $location ) : null;
		$to       = $order->get_meta( self::DEST_META );
		if ( ! $from || ! is_array( $to ) || ! isset( $to['lat'], $to['lng'] ) || ! Maps::is_configured() ) {
			return null;
		}

		$route = Maps::route( $from, array( 'lat' => (float) $to['lat'], 'lng' => (float) $to['lng'] ), true );
		if ( is_wp_error( $route ) ) {
			self::log( $route );
			return null;
		}
		return max( 1, (int) ceil( $route['seconds'] / 60 ) );
	}

	/**
	 * Stores when the courier should arrive, as the order leaves the restaurant. Not saved here:
	 * the status change saves the order.
	 */
	public static function record_eta( \WC_Order $order ): void {
		$minutes = self::eta_minutes( $order );
		if ( null === $minutes ) {
			return;
		}
		$order->update_meta_data( self::ETA_META, gmdate( DATE_ATOM, time() + $minutes * MINUTE_IN_SECONDS ) );
		$order->add_order_note( sprintf( 'Timp estimat până la client, cu traficul de acum: %d min.', $minutes ) );
	}

	/**
	 * Minutes until the stored arrival time, or null when there is none or it has passed.
	 */
	public static function eta_minutes_left( \WC_Order $order ): ?int {
		$eta = strtotime( (string) $order->get_meta( self::ETA_META ) );
		if ( ! $eta || $eta <= time() ) {
			return null;
		}
		return (int) ceil( ( $eta - time() ) / MINUTE_IN_SECONDS );
	}

	/**
	 * Point and road distance for an address, from the cache when someone already asked for it.
	 *
	 * @param array{lat: float, lng: float}      $from
	 * @param array{lat: float, lng: float}|null $point
	 * @return array{point: array{lat: float, lng: float}, meters: int, formatted_address: string, precise: bool}|\WP_Error
	 */
	private static function locate( array $from, ?array $point, string $text ) {
		$key    = self::CACHE_PREFIX . md5( wp_json_encode( array( $from, $point ?? self::normalize( $text ) ) ) );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return isset( $cached['error'] ) ? new \WP_Error( (string) $cached['error'], '' ) : $cached;
		}

		if ( self::is_rate_limited() ) {
			return new \WP_Error( 'mlb_maps_rate_limited', '' );
		}

		$place = $point
			? array(
				'lat'               => $point['lat'],
				'lng'               => $point['lng'],
				'formatted_address' => '',
				'precise'           => true,
			)
			: Maps::geocode( $text );
		if ( is_wp_error( $place ) ) {
			if ( 'mlb_address_not_found' === $place->get_error_code() ) {
				set_transient( $key, array( 'error' => 'mlb_address_not_found' ), DAY_IN_SECONDS );
			} else {
				self::log( $place );
			}
			return $place;
		}

		$to    = array(
			'lat' => $place['lat'],
			'lng' => $place['lng'],
		);
		$route = Maps::route( $from, $to, false );
		if ( is_wp_error( $route ) ) {
			self::log( $route );
			return $route;
		}

		$result = array(
			'point'             => $to,
			'meters'            => $route['meters'],
			'formatted_address' => $place['formatted_address'],
			'precise'           => $place['precise'],
		);
		set_transient( $key, $result, self::CACHE_TTL );
		return $result;
	}

	/**
	 * @param array<string, mixed> $address
	 * @return array{lat: float, lng: float}|null
	 */
	private static function point_from( array $address ): ?array {
		if ( ! isset( $address['lat'], $address['lng'] ) || ! is_numeric( $address['lat'] ) || ! is_numeric( $address['lng'] ) ) {
			return null;
		}
		return Distance_Pricing::parse_coordinates( $address['lat'] . ',' . $address['lng'] );
	}

	/**
	 * Street and town only: block and apartment don't change where the courier drives.
	 *
	 * @param array<string, mixed> $address
	 */
	private static function address_text( array $address ): string {
		$street = trim( (string) ( $address['address_1'] ?? '' ) );
		$city   = trim( (string) ( $address['city'] ?? '' ) );
		return '' === $street ? '' : implode( ', ', array_filter( array( $street, $city, 'România' ) ) );
	}

	private static function normalize( string $text ): string {
		return preg_replace( '/\s+/', ' ', mb_strtolower( remove_accents( $text ) ) );
	}

	private static function is_rate_limited(): bool {
		$ip = '';
		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'REMOTE_ADDR' ) as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				break;
			}
		}
		$key   = 'mlb_dq_rate_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= self::LOOKUP_LIMIT ) {
			return true;
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return false;
	}

	private static function log( \WP_Error $error ): void {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->warning( $error->get_error_message(), array( 'source' => 'mlb-app-api' ) );
		}
	}

	/**
	 * @param array{lat: float, lng: float}|null $point
	 * @return array{mode: string, available: bool, fee: float|null, distance_km: float|null, verified: bool, formatted_address: string, message: string, point: array{lat: float, lng: float}|null}
	 */
	private static function result( string $mode, bool $available, ?float $fee, ?float $km = null, bool $verified = false, string $formatted = '', string $message = '', ?array $point = null ): array {
		return array(
			'mode'              => $mode,
			'available'         => $available,
			'fee'               => $fee,
			'distance_km'       => $km,
			'verified'          => $verified,
			'formatted_address' => $formatted,
			'message'           => $message,
			'point'             => $point,
		);
	}
}
