<?php
/**
 * Plain PHP checks for the loyalty rules (no WordPress needed).
 *
 * Run: php wordpress/mlb-app-api/tests/loyalty-rules-test.php
 */

define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../includes/class-loyalty-rules.php';

use MLB\AppApi\Loyalty_Rules;

$failures = 0;
function check( string $name, bool $ok ): void {
	global $failures;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . PHP_EOL;
	$failures += $ok ? 0 : 1;
}

$defaults = Loyalty_Rules::normalize( null );
check( 'defaults: 4 orders, 10%', 4 === $defaults['required'] && 'percent' === $defaults['reward_type'] && 10.0 === $defaults['reward_amount'] );

$clamped = Loyalty_Rules::normalize( array( 'required' => 0, 'reward_type' => 'percent', 'reward_amount' => 150, 'enabled' => false ) );
check( 'clamps required and percent, keeps disabled', 1 === $clamped['required'] && 100.0 === $clamped['reward_amount'] && false === $clamped['enabled'] );
check( 'unknown type falls back to percent', 'percent' === Loyalty_Rules::normalize( array( 'reward_type' => 'x' ) )['reward_type'] );

$stamps = 0;
$rewards = 0;
for ( $i = 0; $i < 9; $i++ ) {
	$result  = Loyalty_Rules::add_stamp( $stamps, 4 );
	$stamps  = $result['stamps'];
	$rewards += $result['reward'] ? 1 : 0;
}
check( '9 orders with 4 required: 2 rewards, 1 stamp left', 2 === $rewards && 1 === $stamps );

check( 'percent label', '10%' === Loyalty_Rules::amount_label( 'percent', 10 ) );
check( 'fixed label with decimals', '12,5 lei' === Loyalty_Rules::amount_label( 'fixed', 12.5 ) );
check( 'fixed label whole', '15 lei' === Loyalty_Rules::amount_label( 'fixed', 15 ) );

exit( $failures > 0 ? 1 : 0 );
