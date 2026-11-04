<?php
/**
 * Plain PHP checks for the address cleanup before geocoding (no WordPress needed).
 *
 * Run: php wordpress/mlb-app-api/tests/street-only-test.php
 */

define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../includes/class-delivery-quote.php';

use MLB\AppApi\Delivery_Quote;

$failures = 0;
function check( string $name, bool $ok ): void {
	global $failures;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . PHP_EOL;
	$failures += $ok ? 0 : 1;
}

$cases = array(
	'Str. Libertății nr. 5, bl. A2, sc. B, ap. 12' => 'Str. Libertății 5',
	'Bd. Republicii nr.12 Bl.3 Sc.A Et.2 Ap.10'    => 'Bd. Republicii 12',
	'Strada Ștefan cel Mare 10, ap 4'              => 'Strada Ștefan cel Mare 10',
	'Str. Castanilor bl 214, sc. B'                => 'Str. Castanilor',
	'Nicolae Iorga 61'                             => 'Nicolae Iorga 61',
	'Str. Bloc Nou 3'                              => 'Str. Bloc Nou 3',
	'Str. Apărătorii Patriei 7, et. P'             => 'Str. Apărătorii Patriei 7',
	'Strada Scării 5'                              => 'Strada Scării 5',
);
foreach ( $cases as $input => $expected ) {
	$got = Delivery_Quote::street_only( $input );
	check( $input . ' → ' . $expected . ( $got === $expected ? '' : ' (got: ' . $got . ')' ), $got === $expected );
}

exit( $failures > 0 ? 1 : 0 );
