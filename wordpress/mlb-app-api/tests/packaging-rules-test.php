<?php
/**
 * Plain PHP checks for packaging and free extras (no WordPress needed).
 *
 * Run: php wordpress/mlb-app-api/tests/packaging-rules-test.php
 */

define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../includes/class-packaging-rules.php';

use MLB\AppApi\Packaging_Rules;

$failures = 0;
function check( string $name, bool $ok ): void {
	global $failures;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . PHP_EOL;
	$failures += $ok ? 0 : 1;
}

$mic     = array( 'units' => 1, 'box' => 'mici', 'platter' => false, 'bread' => 1, 'mustard' => 1 );
$mic2    = array( 'units' => 1, 'box' => 'Mici ', 'platter' => false, 'bread' => 1, 'mustard' => 1 );
$ceafa   = array( 'units' => 1, 'box' => 'ceafa', 'platter' => false, 'bread' => 2, 'mustard' => 2 );
$cartofi = array( 'units' => 0, 'box' => '', 'platter' => false, 'bread' => 0, 'mustard' => 0 );
$platou  = array( 'units' => 0, 'box' => '', 'platter' => true, 'bread' => 0, 'mustard' => 0 );
$line    = static fn ( array $product, int $quantity ): array => array( 'quantity' => $quantity ) + $product;

$r = Packaging_Rules::count( array( $line( $mic, 8 ), $line( $ceafa, 4 ) ), 10, true );
check( '8 mici + 4 cefe: 2 packs', 2 === $r['packs'] );
check( '8 mici + 4 cefe: 16 bread slices, 16 mustard portions', 16 === $r['bread'] && 16 === $r['mustard'] );

check( 'mici and cefe never share a box: 3 + 2 = 2 packs', 2 === Packaging_Rules::count( array( $line( $mic, 3 ), $line( $ceafa, 2 ) ), 10, true )['packs'] );
check( 'two kinds of mici share a box', 1 === Packaging_Rules::count( array( $line( $mic, 4 ), $line( $mic2, 6 ) ), 10, true )['packs'] );
check( '15 mici + 12 cefe: 4 packs', 4 === Packaging_Rules::count( array( $line( $mic, 15 ), $line( $ceafa, 12 ) ), 10, true )['packs'] );
check( '10 mici: 1 pack', 1 === Packaging_Rules::count( array( $line( $mic, 10 ) ), 10, true )['packs'] );
check( '11 mici: 2 packs', 2 === Packaging_Rules::count( array( $line( $mic, 11 ) ), 10, true )['packs'] );
check( '20 cefe: 2 packs', 2 === Packaging_Rules::count( array( $line( $ceafa, 20 ) ), 10, true )['packs'] );
check( 'portion of 5 mici counts 5', 1 === Packaging_Rules::count( array( $line( array( 'units' => 5 ) + $mic, 2 ) ), 10, true )['packs'] );

check( 'fries alone: 1 pack when at least one is on', 1 === Packaging_Rules::count( array( $line( $cartofi, 2 ) ), 10, true )['packs'] );
check( 'fries alone: no pack when off', 0 === Packaging_Rules::count( array( $line( $cartofi, 2 ) ), 10, false )['packs'] );

$r = Packaging_Rules::count( array( $line( $platou, 2 ) ), 10, true );
check( 'platters alone: their boxes, no pack', 0 === $r['packs'] && 2 === $r['platters'] );
$r = Packaging_Rules::count( array( $line( $platou, 1 ), $line( $mic, 3 ) ), 10, true );
check( 'platter + mici: 1 platter box + 1 pack', 1 === $r['packs'] && 1 === $r['platters'] );

check( 'empty order: nothing', 0 === Packaging_Rules::count( array(), 10, true )['packs'] );

exit( $failures > 0 ? 1 : 0 );
