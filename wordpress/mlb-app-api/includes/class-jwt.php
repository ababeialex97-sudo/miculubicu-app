<?php
/**
 * Minimal HS256 JSON Web Token encoder/decoder.
 *
 * Kept free of WordPress dependencies so it can be unit tested with plain PHP.
 */

namespace MLB\AppApi;

defined( 'ABSPATH' ) || exit;

class Jwt {

	/**
	 * @param array<string, mixed> $payload
	 */
	public static function encode( array $payload, string $secret ): string {
		$header = array(
			'alg' => 'HS256',
			'typ' => 'JWT',
		);

		$segments = array(
			self::base64url_encode( (string) json_encode( $header ) ),
			self::base64url_encode( (string) json_encode( $payload ) ),
		);

		$signature  = hash_hmac( 'sha256', implode( '.', $segments ), $secret, true );
		$segments[] = self::base64url_encode( $signature );

		return implode( '.', $segments );
	}

	/**
	 * Returns the payload, or null when the token is malformed, badly signed or expired.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function decode( string $token, string $secret, ?int $now = null ): ?array {
		$parts = explode( '.', $token );
		if ( 3 !== count( $parts ) ) {
			return null;
		}

		list( $header_b64, $payload_b64, $signature_b64 ) = $parts;

		$header = json_decode( self::base64url_decode( $header_b64 ), true );
		if ( ! is_array( $header ) || ( $header['alg'] ?? '' ) !== 'HS256' ) {
			return null;
		}

		$expected = hash_hmac( 'sha256', $header_b64 . '.' . $payload_b64, $secret, true );
		if ( ! hash_equals( $expected, self::base64url_decode( $signature_b64 ) ) ) {
			return null;
		}

		$payload = json_decode( self::base64url_decode( $payload_b64 ), true );
		if ( ! is_array( $payload ) ) {
			return null;
		}

		$now = $now ?? time();
		if ( ! isset( $payload['exp'] ) || ! is_int( $payload['exp'] ) || $payload['exp'] <= $now ) {
			return null;
		}

		return $payload;
	}

	private static function base64url_encode( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	private static function base64url_decode( string $data ): string {
		$decoded = base64_decode( strtr( $data, '-_', '+/' ), true );
		return false === $decoded ? '' : $decoded;
	}
}
