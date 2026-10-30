<?php
/**
 * Plain PHP checks for delivery pricing by distance (no WordPress needed).
 *
 * Run: php wordpress/mlb-app-api/tests/distance-pricing-test.php
 */

define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../includes/class-distance-pricing.php';

use MLB\AppApi\Distance_Pricing;

$failures = 0;
function check( string $name, bool $ok ): void {
	global $failures;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . PHP_EOL;
	$failures += $ok ? 0 : 1;
}

$bands = Distance_Pricing::parse_bands( "4 = 15\n2=10\r\n6 km : 20 lei\nnonsense\n0 = 5\n2,5 = 12,50" );
check( 'parses, sorts and drops bad lines', array( 2.0, 2.5, 4.0, 6.0 ) === array_column( $bands, 'up_to_km' ) );
check( 'decimal comma', 12.5 === $bands[1]['fee'] );
check( 'round trip to text', "2 = 10\n2.5 = 12.5\n4 = 15\n6 = 20" === Distance_Pricing::bands_to_text( $bands ) );
check( 'empty text, no bands', array() === Distance_Pricing::parse_bands( '' ) );

check( 'inside first band', 10.0 === Distance_Pricing::fee_for( 1.2, $bands ) );
check( 'band edge belongs to the band', 10.0 === Distance_Pricing::fee_for( 2.0, $bands ) );
check( 'just over an edge', 12.5 === Distance_Pricing::fee_for( 2.1, $bands ) );
check( 'beyond the last band: no delivery', null === Distance_Pricing::fee_for( 6.1, $bands ) );
check( 'max km is the last band', 6.0 === Distance_Pricing::max_km( $bands ) );

check( 'coordinates from Google Maps', array( 'lat' => 46.640712, 'lng' => 27.727631 ) === Distance_Pricing::parse_coordinates( '46.640712, 27.727631' ) );
check( 'coordinates without space', null !== Distance_Pricing::parse_coordinates( '46.64,27.72' ) );
check( 'rejects text', null === Distance_Pricing::parse_coordinates( 'Vaslui' ) );
check( 'rejects out of range', null === Distance_Pricing::parse_coordinates( '95, 27' ) );

check( 'km label', '3,2 km' === Distance_Pricing::km_label( 3.24 ) );

exit( $failures > 0 ? 1 : 0 );
