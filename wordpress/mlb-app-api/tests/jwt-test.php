<?php
/**
 * Plain PHP checks for the JWT class (no WordPress needed).
 *
 * Run: php wordpress/mlb-app-api/tests/jwt-test.php
 */

define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../includes/class-jwt.php';

use MLB\AppApi\Jwt;

$failures = 0;
function check( string $name, bool $ok ): void {
	global $failures;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . PHP_EOL;
	$failures += $ok ? 0 : 1;
}

$secret  = 'test-secret';
$now     = 1700000000;
$payload = array(
	'sub' => 42,
	'ver' => 0,
	'exp' => $now + 60,
);
$token   = Jwt::encode( $payload, $secret );

check( 'round trip', Jwt::decode( $token, $secret, $now ) === $payload );
check( 'wrong secret rejected', null === Jwt::decode( $token, 'other', $now ) );
check( 'expired rejected', null === Jwt::decode( $token, $secret, $now + 60 ) );

list( $h, $p, $s ) = explode( '.', $token );
$forged            = rtrim( strtr( base64_encode( (string) json_encode( array( 'sub' => 1, 'ver' => 0, 'exp' => $now + 60 ) ) ), '+/', '-_' ), '=' );
check( 'tampered payload rejected', null === Jwt::decode( "$h.$forged.$s", $secret, $now ) );

$none = rtrim( strtr( base64_encode( '{"alg":"none","typ":"JWT"}' ), '+/', '-_' ), '=' );
check( 'alg none rejected', null === Jwt::decode( "$none.$p.", $secret, $now ) );
check( 'garbage rejected', null === Jwt::decode( 'abc', $secret, $now ) );
check( 'missing exp rejected', null === Jwt::decode( Jwt::encode( array( 'sub' => 1 ), $secret ), $secret, $now ) );

exit( $failures > 0 ? 1 : 0 );
