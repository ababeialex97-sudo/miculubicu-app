<?php
/**
 * Delivery fee by road distance: price bands such as "up to 2 km = 10 lei".
 *
 * Kept free of WordPress dependencies so it can be unit tested with plain PHP.
 */

namespace MLB\AppApi;

defined( 'ABSPATH' ) || exit;

class Distance_Pricing {

	/**
	 * Reads bands written one per line as "km = lei" (e.g. "2 = 10"), sorted by distance.
	 * Lines that don't parse are dropped. The last band is the farthest the restaurant delivers.
	 *
	 * @return array<int, array{up_to_km: float, fee: float}>
	 */
	public static function parse_bands( $text ): array {
		$bands = array();
		foreach ( preg_split( '/[\r\n;]+/', (string) $text ) as $line ) {
			if ( ! preg_match( '/^\s*(\d+(?:[.,]\d+)?)\s*(?:km)?\s*[=:]\s*(\d+(?:[.,]\d+)?)\s*(?:lei)?\s*$/i', $line, $match ) ) {
				continue;
			}
			$km  = (float) str_replace( ',', '.', $match[1] );
			$fee = (float) str_replace( ',', '.', $match[2] );
			if ( $km > 0 ) {
				// A repeated distance keeps the last price written for it.
				$bands[ (string) $km ] = array(
					'up_to_km' => $km,
					'fee'      => $fee,
				);
			}
		}

		$bands = array_values( $bands );
		usort( $bands, static fn ( array $a, array $b ): int => $a['up_to_km'] <=> $b['up_to_km'] );
		return $bands;
	}

	/**
	 * @param array<int, array{up_to_km: float, fee: float}> $bands
	 */
	public static function bands_to_text( array $bands ): string {
		$lines = array();
		foreach ( $bands as $band ) {
			$lines[] = self::number( $band['up_to_km'] ) . ' = ' . self::number( $band['fee'] );
		}
		return implode( "\n", $lines );
	}

	/**
	 * Fee for a distance, or null when it is beyond the last band (no delivery there).
	 *
	 * @param array<int, array{up_to_km: float, fee: float}> $bands
	 */
	public static function fee_for( float $km, array $bands ): ?float {
		foreach ( $bands as $band ) {
			if ( $km <= $band['up_to_km'] ) {
				return $band['fee'];
			}
		}
		return null;
	}

	/**
	 * @param array<int, array{up_to_km: float, fee: float}> $bands
	 */
	public static function max_km( array $bands ): float {
		return $bands ? (float) end( $bands )['up_to_km'] : 0.0;
	}

	/**
	 * Reads "46.6407, 27.7276" as copied from Google Maps; null when it isn't a valid point.
	 *
	 * @return array{lat: float, lng: float}|null
	 */
	public static function parse_coordinates( $text ): ?array {
		if ( ! preg_match( '/^\s*(-?\d{1,2}(?:\.\d+)?)\s*[,;\s]\s*(-?\d{1,3}(?:\.\d+)?)\s*$/', (string) $text, $match ) ) {
			return null;
		}
		$lat = (float) $match[1];
		$lng = (float) $match[2];
		if ( abs( $lat ) > 90 || abs( $lng ) > 180 ) {
			return null;
		}
		return array(
			'lat' => $lat,
			'lng' => $lng,
		);
	}

	/**
	 * "3,2 km" in the Romanian style, one decimal.
	 */
	public static function km_label( float $km ): string {
		return str_replace( '.', ',', (string) round( $km, 1 ) ) . ' km';
	}

	private static function number( float $value ): string {
		return rtrim( rtrim( number_format( $value, 2, '.', '' ), '0' ), '.' );
	}
}
