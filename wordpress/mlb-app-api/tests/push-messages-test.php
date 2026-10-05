<?php
/**
 * Plain PHP checks for the push message builder (no WordPress needed).
 *
 * Run: php wordpress/mlb-app-api/tests/push-messages-test.php
 */

define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../includes/class-push-messages.php';

use MLB\AppApi\Push_Messages;

$failures = 0;
function check( string $name, bool $ok ): void {
	global $failures;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . PHP_EOL;
	$failures += $ok ? 0 : 1;
}

$text = Push_Messages::for_status( 'on_the_way', 'delivery', '1084' );
check( 'on_the_way mentions the order', null !== $text && str_contains( $text['body'], '#1084' ) );
check( 'received sends nothing', null === Push_Messages::for_status( 'received', 'delivery', '1' ) );
check( 'completed pickup says enjoy', 'Poftă bună!' === Push_Messages::for_status( 'completed', 'pickup', '1' )['title'] );
check( 'completed delivery says delivered', 'Comanda a fost livrată' === Push_Messages::for_status( 'completed', 'delivery', '1' )['title'] );

$tokens  = array_map( static fn ( $i ) => "ExponentPushToken[t$i]", range( 1, 205 ) );
$batches = Push_Messages::batches( array_merge( $tokens, array( 'ExponentPushToken[t1]' ) ), 'T', 'B', array( 'orderId' => 5 ) );
check( 'batches of 100, duplicates dropped', array( 100, 100, 5 ) === array_map( 'count', $batches ) );
check( 'message carries data', 5 === $batches[0][0]['data']['orderId'] && 'ExponentPushToken[t1]' === $batches[0][0]['to'] );

$batch    = Push_Messages::batches( array( 'ExponentPushToken[a]', 'ExponentPushToken[b]', 'ExponentPushToken[c]' ), 'T', 'B', array() )[0];
$response = array(
	'data' => array(
		array( 'status' => 'ok', 'id' => 'x' ),
		array( 'status' => 'error', 'details' => array( 'error' => 'DeviceNotRegistered' ) ),
		array( 'status' => 'error', 'details' => array( 'error' => 'MessageRateExceeded' ) ),
	),
);
check( 'only unregistered devices are dropped', array( 'ExponentPushToken[b]' ) === Push_Messages::dead_tokens( $batch, $response ) );
check( 'bad response drops nothing', array() === Push_Messages::dead_tokens( $batch, null ) );

exit( $failures > 0 ? 1 : 0 );
