<?php
/**
 * Verifies OpenID Connect ID tokens (RS256) from Google and Apple against their published keys.
 *
 * Kept free of WordPress dependencies so it can be unit tested with plain PHP.
 */

namespace MLB\AppApi;

defined( 'ABSPATH' ) || exit;

class Id_Token {

	/** Clock difference tolerated between the identity provider and this server. */
	private const LEEWAY = 120;

	/**
	 * Returns the claims, or null when the token is malformed, badly signed, expired,
	 * or issued by someone else or for another app.
	 *
	 * @param array<int, array<string, mixed>> $keys    JWKS "keys".
	 * @param string[]                         $issuers Accepted "iss" values.
	 * @param string[]                         $audiences Accepted "aud" values (client IDs).
	 * @return array<string, mixed>|null
	 */
	public static function verify( string $token, array $keys, array $issuers, array $audiences, ?int $now = null ): ?array {
		$parts = explode( '.', $token );
		if ( 3 !== count( $parts ) || empty( $audiences ) ) {
			return null;
		}

		list( $header_b64, $payload_b64, $signature_b64 ) = $parts;

		$header = json_decode( self::base64url_decode( $header_b64 ), true );
		if ( ! is_array( $header ) || ( $header['alg'] ?? '' ) !== 'RS256' || ! isset( $header['kid'] ) ) {
			return null;
		}

		$pem = null;
		foreach ( $keys as $key ) {
			if ( ( $key['kid'] ?? null ) === $header['kid'] && ( $key['kty'] ?? '' ) === 'RSA' && isset( $key['n'], $key['e'] ) ) {
				$pem = self::rsa_pem( (string) $key['n'], (string) $key['e'] );
				break;
			}
		}
		if ( null === $pem ) {
			return null;
		}

		$public_key = openssl_pkey_get_public( $pem );
		if ( false === $public_key ) {
			return null;
		}
		if ( 1 !== openssl_verify( $header_b64 . '.' . $payload_b64, self::base64url_decode( $signature_b64 ), $public_key, OPENSSL_ALGO_SHA256 ) ) {
			return null;
		}

		$claims = json_decode( self::base64url_decode( $payload_b64 ), true );
		if ( ! is_array( $claims ) ) {
			return null;
		}

		$now = $now ?? time();
		if ( ! isset( $claims['exp'] ) || ! is_numeric( $claims['exp'] ) || (int) $claims['exp'] + self::LEEWAY <= $now ) {
			return null;
		}
		if ( isset( $claims['iat'] ) && is_numeric( $claims['iat'] ) && (int) $claims['iat'] - self::LEEWAY > $now ) {
			return null;
		}
		if ( ! in_array( $claims['iss'] ?? null, $issuers, true ) ) {
			return null;
		}

		$token_audiences = is_array( $claims['aud'] ?? null ) ? $claims['aud'] : array( $claims['aud'] ?? null );
		if ( ! array_intersect( $token_audiences, $audiences ) ) {
			return null;
		}

		if ( ! isset( $claims['sub'] ) || '' === (string) $claims['sub'] ) {
			return null;
		}

		return $claims;
	}

	/**
	 * Apple sends email_verified as the string "true", Google as a boolean.
	 *
	 * @param array<string, mixed> $claims
	 */
	public static function verified_email( array $claims ): ?string {
		$email    = isset( $claims['email'] ) ? strtolower( trim( (string) $claims['email'] ) ) : '';
		$verified = $claims['email_verified'] ?? false;
		if ( '' === $email || ! ( true === $verified || 'true' === $verified ) ) {
			return null;
		}
		return $email;
	}

	/**
	 * PEM public key from a JWK's modulus and exponent (SubjectPublicKeyInfo, DER encoded).
	 */
	public static function rsa_pem( string $n, string $e ): string {
		$modulus  = self::der_integer( self::base64url_decode( $n ) );
		$exponent = self::der_integer( self::base64url_decode( $e ) );
		$rsa_key  = self::der( 0x30, $modulus . $exponent );

		// AlgorithmIdentifier: rsaEncryption (1.2.840.113549.1.1.1) with NULL parameters.
		$algorithm = hex2bin( '300d06092a864886f70d0101010500' );
		$spki      = self::der( 0x30, $algorithm . self::der( 0x03, "\x00" . $rsa_key ) );

		return "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $spki ), 64, "\n" ) . "-----END PUBLIC KEY-----\n";
	}

	private static function der_integer( string $bytes ): string {
		$bytes = ltrim( $bytes, "\x00" );
		if ( '' === $bytes || ord( $bytes[0] ) > 0x7f ) {
			$bytes = "\x00" . $bytes;
		}
		return self::der( 0x02, $bytes );
	}

	private static function der( int $tag, string $content ): string {
		$length = strlen( $content );
		if ( $length < 0x80 ) {
			$encoded_length = chr( $length );
		} else {
			$length_bytes   = ltrim( pack( 'N', $length ), "\x00" );
			$encoded_length = chr( 0x80 | strlen( $length_bytes ) ) . $length_bytes;
		}
		return chr( $tag ) . $encoded_length . $content;
	}

	private static function base64url_decode( string $data ): string {
		$decoded = base64_decode( strtr( $data, '-_', '+/' ), true );
		return false === $decoded ? '' : $decoded;
	}
}
