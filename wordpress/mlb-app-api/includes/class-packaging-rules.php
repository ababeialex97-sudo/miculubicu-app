<?php
/**
 * How many packs, platter boxes and free extras an order gets.
 *
 * A pack (bag, meat box, fries box) holds up to `capacity` pieces of one kind of box: mici
 * and cefe never share a box (12 mici = 2 packs, 8 mici + 4 cefe = 2 packs). Platters bring
 * their own platter box. Bread and mustard are on the house, per piece.
 *
 * Kept free of WordPress dependencies so it can be unit tested with plain PHP; the app
 * mirrors it in src/lib/packaging.ts.
 */

namespace MLB\AppApi;

defined( 'ABSPATH' ) || exit;

class Packaging_Rules {

	/**
	 * @param array<int, array{quantity: int, units: int, box: string, platter: bool, bread: int, mustard: int}> $lines
	 *        Per product: pieces it takes in a box and which box (e.g. "mici", "ceafa"), whether it
	 *        is a platter, and the bread slices and mustard portions it comes with, per unit ordered.
	 * @return array{packs: int, platters: int, bread: int, mustard: int}
	 */
	public static function count( array $lines, int $capacity, bool $at_least_one_pack ): array {
		$units    = array();
		$platters = 0;
		$bread    = 0;
		$mustard  = 0;

		foreach ( $lines as $line ) {
			$quantity = max( 0, (int) $line['quantity'] );
			$box      = self::box_key( (string) ( $line['box'] ?? '' ) );

			$units[ $box ] = ( $units[ $box ] ?? 0 ) + max( 0, (int) $line['units'] ) * $quantity;
			$platters     += $line['platter'] ? $quantity : 0;
			$bread        += max( 0, (int) $line['bread'] ) * $quantity;
			$mustard      += max( 0, (int) $line['mustard'] ) * $quantity;
		}

		$packs = 0;
		foreach ( $units as $count ) {
			$packs += (int) ceil( $count / max( 1, $capacity ) );
		}
		// Fries or sauces alone still leave in a bag; a platter has its own box.
		if ( 0 === $packs && 0 === $platters && $at_least_one_pack && $lines ) {
			$packs = 1;
		}

		return array(
			'packs'    => $packs,
			'platters' => $platters,
			'bread'    => $bread,
			'mustard'  => $mustard,
		);
	}

	/**
	 * Rules for a product nobody has set up yet, read from its name: mici and cârnăciori get
	 * a slice of bread and a mustard portion per piece, ceafă two of each, platters their own
	 * box. Anything else takes no room in a box and comes with nothing.
	 *
	 * @return array{units: int, box: string, platter: bool, bread: int, mustard: int}
	 */
	public static function guess( string $name ): array {
		$name = mb_strtolower( strtr( $name, array( 'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't', 'Ă' => 'a', 'Â' => 'a', 'Î' => 'i', 'Ș' => 's', 'Ş' => 's', 'Ț' => 't', 'Ţ' => 't' ) ) );
		$none = array(
			'units'   => 0,
			'box'     => '',
			'platter' => false,
			'bread'   => 0,
			'mustard' => 0,
		);

		if ( str_contains( $name, 'platou' ) ) {
			return array( 'platter' => true ) + $none;
		}
		$kinds = array(
			'carnac' => array( 'carnaciori', 1 ),
			'ceaf'   => array( 'ceafa', 2 ),
		);
		foreach ( $kinds as $needle => list( $box, $extras ) ) {
			if ( str_contains( $name, $needle ) ) {
				return array(
					'units'   => 1,
					'box'     => $box,
					'platter' => false,
					'bread'   => $extras,
					'mustard' => $extras,
				);
			}
		}
		if ( preg_match( '/\bmici?\b/u', $name ) ) {
			return array(
				'units'   => 1,
				'box'     => 'mici',
				'platter' => false,
				'bread'   => 1,
				'mustard' => 1,
			);
		}
		return $none;
	}

	/**
	 * "Mici " and "mici" are the same box; products without a box share one.
	 */
	public static function box_key( string $box ): string {
		$box = trim( mb_strtolower( $box ) );
		return '' === $box ? '-' : $box;
	}
}
