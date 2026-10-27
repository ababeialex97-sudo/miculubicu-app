<?php
/**
 * Plain PHP checks for Google / Apple ID token verification (no WordPress needed).
 *
 * Run: php wordpress/mlb-app-api/tests/id-token-test.php
 */

define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../includes/class-id-token.php';

use MLB\AppApi\Id_Token;

$failures = 0;
function check( string $name, bool $ok ): void {
	global $failures;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . PHP_EOL;
	$failures += $ok ? 0 : 1;
}

function b64url( string $data ): string {
	return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
}

/**
 * A provider key pair, published as a JWK like Google and Apple do.
 *
 * @return array{0: \OpenSSLAsymmetricKey, 1: array<string, string>}
 */
function provider_key( string $kid ): array {
	$private = openssl_pkey_new(
		array(
			'private_key_bits' => 2048,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
		)
	);
	$details = openssl_pkey_get_details( $private );
	return array(
		$private,
		array(
			'kty' => 'RSA',
			'kid' => $kid,
			'alg' => 'RS256',
			'use' => 'sig',
			'n'   => b64url( $details['rsa']['n'] ),
			'e'   => b64url( $details['rsa']['e'] ),
		),
	);
}

/**
 * @param array<string, mixed> $claims
 */
function sign( array $claims, \OpenSSLAsymmetricKey $key, string $kid, string $alg = 'RS256' ): string {
	$input = b64url( (string) json_encode( array( 'alg' => $alg, 'kid' => $kid, 'typ' => 'JWT' ) ) ) . '.' . b64url( (string) json_encode( $claims ) );
	openssl_sign( $input, $signature, $key, OPENSSL_ALGO_SHA256 );
	return $input . '.' . b64url( $signature );
}

list( $key, $jwk )         = provider_key( 'k1' );
list( $other, $other_jwk ) = provider_key( 'k2' );
$keys                      = array( $other_jwk, $jwk );

$now      = 1700000000;
$issuers  = array( 'https://accounts.google.com', 'accounts.google.com' );
$audience = array( 'web-client.apps.googleusercontent.com' );
$claims   = array(
	'iss'            => 'https://accounts.google.com',
	'aud'            => 'web-client.apps.googleusercontent.com',
	'sub'            => '1234567890',
	'email'          => 'Ion@Example.com',
	'email_verified' => true,
	'iat'            => $now - 10,
	'exp'            => $now + 3600,
);

$token = sign( $claims, $key, 'k1' );
check( 'valid token accepted', Id_Token::verify( $token, $keys, $issuers, $audience, $now ) === $claims );
check( 'verified email lower-cased', 'ion@example.com' === Id_Token::verified_email( $claims ) );
check( 'unverified email ignored', null === Id_Token::verified_email( array( 'email' => 'a@b.ro', 'email_verified' => false ) ) );
check( 'apple string "true" counts as verified', 'a@b.ro' === Id_Token::verified_email( array( 'email' => 'a@b.ro', 'email_verified' => 'true' ) ) );

check( 'wrong audience rejected', null === Id_Token::verify( $token, $keys, $issuers, array( 'another-app' ), $now ) );
check( 'no configured audience rejected', null === Id_Token::verify( $token, $keys, $issuers, array(), $now ) );
check( 'wrong issuer rejected', null === Id_Token::verify( sign( array( 'iss' => 'https://evil.example' ) + $claims, $key, 'k1' ), $keys, $issuers, $audience, $now ) );
check( 'expired rejected', null === Id_Token::verify( $token, $keys, $issuers, $audience, $now + 3600 + 121 ) );
check( 'small clock skew tolerated', null !== Id_Token::verify( $token, $keys, $issuers, $audience, $now + 3600 + 60 ) );
check( 'issued in the future rejected', null === Id_Token::verify( sign( array( 'iat' => $now + 600 ) + $claims, $key, 'k1' ), $keys, $issuers, $audience, $now ) );
check( 'audience list accepted', null !== Id_Token::verify( sign( array( 'aud' => array( 'x', 'web-client.apps.googleusercontent.com' ) ) + $claims, $key, 'k1' ), $keys, $issuers, $audience, $now ) );

check( 'signed with another key rejected', null === Id_Token::verify( sign( $claims, $other, 'k1' ), $keys, $issuers, $audience, $now ) );
check( 'unknown key id rejected', null === Id_Token::verify( sign( $claims, $key, 'k9' ), $keys, $issuers, $audience, $now ) );
check( 'HS256 rejected', null === Id_Token::verify( sign( $claims, $key, 'k1', 'HS256' ), $keys, $issuers, $audience, $now ) );

list( $h, $p, $s ) = explode( '.', $token );
$forged            = b64url( (string) json_encode( array( 'sub' => 'someone-else' ) + $claims ) );
check( 'tampered claims rejected', null === Id_Token::verify( "$h.$forged.$s", $keys, $issuers, $audience, $now ) );
check( 'garbage rejected', null === Id_Token::verify( 'a.b', $keys, $issuers, $audience, $now ) );

$pem = Id_Token::rsa_pem( $jwk['n'], $jwk['e'] );
check( 'JWK converts to the same public key', openssl_pkey_get_details( openssl_pkey_get_public( $pem ) )['key'] === openssl_pkey_get_details( $key )['key'] );

exit( $failures > 0 ? 1 : 0 );
