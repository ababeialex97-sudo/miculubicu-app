<?php
/**
 * Plain PHP checks for reading Google Places answers (no WordPress needed).
 *
 * Run: php wordpress/mlb-app-api/tests/places-test.php
 */

define( 'ABSPATH', __DIR__ );
function remove_accents( string $text ): string {
	return strtr( $text, array( 'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ț' => 't', 'Ă' => 'A', 'Ș' => 'S', 'Ț' => 'T' ) );
}
require __DIR__ . '/../includes/class-places.php';

use MLB\AppApi\Places;

$failures = 0;
function check( string $name, bool $ok ): void {
	global $failures;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . PHP_EOL;
	$failures += $ok ? 0 : 1;
}

$suggestions = Places::parse_suggestions(
	array(
		'suggestions' => array(
			array(
				'placePrediction' => array(
					'placeId'          => 'ChIJabc123',
					'text'             => array( 'text' => 'Strada Libertății 5, Vaslui, România' ),
					'structuredFormat' => array(
						'mainText'      => array( 'text' => 'Strada Libertății 5' ),
						'secondaryText' => array( 'text' => 'Vaslui, România' ),
					),
				),
			),
			array( 'queryPrediction' => array( 'text' => array( 'text' => 'libertatii' ) ) ),
		),
	)
);
check( 'one place suggestion, query predictions skipped', 1 === count( $suggestions ) );
check( 'main text and town without the country', 'Strada Libertății 5' === $suggestions[0]['main'] && 'Vaslui' === $suggestions[0]['secondary'] );

$component = static fn ( string $text, array $types ): array => array( 'longText' => $text, 'types' => $types );

$house = Places::parse_place(
	array(
		'location'          => array( 'latitude' => 46.64, 'longitude' => 27.73 ),
		'formattedAddress'  => 'Strada Libertății 5, Vaslui 730000, România',
		'types'             => array( 'street_address' ),
		'addressComponents' => array(
			$component( '5', array( 'street_number' ) ),
			$component( 'Strada Libertății', array( 'route' ) ),
			$component( 'Vaslui', array( 'locality', 'political' ) ),
		),
	)
);
check( 'house: street and number, town, exact', 'Strada Libertății 5' === $house['address_1'] && 'Vaslui' === $house['city'] && $house['precise'] && 46.64 === $house['lat'] );

$street = Places::parse_place(
	array(
		'location'          => array( 'latitude' => 46.6, 'longitude' => 27.7 ),
		'types'             => array( 'route' ),
		'addressComponents' => array( $component( 'Strada Castanilor', array( 'route' ) ), $component( 'Vaslui', array( 'locality' ) ) ),
	)
);
check( 'bare street is not exact', 'Strada Castanilor' === $street['address_1'] && ! $street['precise'] );

$hospital = Places::parse_place(
	array(
		'location'          => array( 'latitude' => 46.63, 'longitude' => 27.72 ),
		'displayName'       => array( 'text' => 'Spitalul Județean de Urgență Vaslui' ),
		'types'             => array( 'hospital', 'point_of_interest', 'establishment' ),
		'addressComponents' => array( $component( 'Strada Ștefan cel Mare', array( 'route' ) ), $component( 'Vaslui', array( 'locality' ) ) ),
	)
);
check( 'named place keeps its name and is exact', str_starts_with( $hospital['address_1'], 'Spitalul Județean' ) && $hospital['precise'] );

check( 'no location: null', null === Places::parse_place( array( 'types' => array( 'route' ) ) ) );

check( 'picked address matches the same text typed differently', Places::memory_key( 'Strada Libertății 5', 'Vaslui' ) === Places::memory_key( ' strada libertatii  5', 'VASLUI ' ) );
check( 'another number is another address', Places::memory_key( 'Strada Libertății 5', 'Vaslui' ) !== Places::memory_key( 'Strada Libertății 7', 'Vaslui' ) );

exit( $failures > 0 ? 1 : 0 );
