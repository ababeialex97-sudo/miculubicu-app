<?php
/**
 * Checks for guest order tokens with a minimal stand-in for WooCommerce orders.
 *
 * Run: php wordpress/mlb-app-api/tests/guest-orders-test.php
 */

define( 'ABSPATH', __DIR__ );
define( 'HOUR_IN_SECONDS', 3600 );

// phpcs:disable Generic.Files.OneObjectStructurePerFile

class WC_Order {
	public array $meta     = array();
	public int $customer   = 0;
	public array $notes    = array();
	public function __construct( public int $id ) {}
	public function get_id(): int { return $this->id; }
	public function get_customer_id(): int { return $this->customer; }
	public function set_customer_id( int $id ): void { $this->customer = $id; }
	public function get_meta( string $key ) { return $this->meta[ $key ] ?? ''; }
	public function update_meta_data( string $key, $value ): void { $this->meta[ $key ] = $value; }
	public function delete_meta_data( string $key ): void { unset( $this->meta[ $key ] ); }
	public function add_order_note( string $note ): void { $this->notes[] = $note; }
	public function save(): void {}
}

$orders = array();
function wc_get_order( int $id ) {
	global $orders;
	return $orders[ $id ] ?? false;
}

require __DIR__ . '/../includes/class-guest-orders.php';

use MLB\AppApi\Guest_Orders;

$failures = 0;
function check( string $name, bool $ok ): void {
	global $failures;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . PHP_EOL;
	$failures += $ok ? 0 : 1;
}

$orders[10] = new WC_Order( 10 );
$orders[11] = new WC_Order( 11 );
$token      = Guest_Orders::issue_token( $orders[10] );
$other      = Guest_Orders::issue_token( $orders[11] );

check( 'token finds its order', Guest_Orders::find( 10, $token ) === $orders[10] );
check( 'only a hash is stored', ! in_array( $token, $orders[10]->meta, true ) );
check( 'wrong token rejected', null === Guest_Orders::find( 10, $other ) );
check( 'empty token rejected', null === Guest_Orders::find( 10, '' ) );
check( 'missing order rejected', null === Guest_Orders::find( 99, $token ) );

$rotated = Guest_Orders::issue_token( $orders[10] );
check( 'a new token replaces the old one', null === Guest_Orders::find( 10, $token ) && null !== Guest_Orders::find( 10, $rotated ) );

foreach ( array( 'ExponentPushToken[a]', 'ExponentPushToken[b]', 'ExponentPushToken[a]', 'ExponentPushToken[c]', 'ExponentPushToken[d]' ) as $push ) {
	Guest_Orders::add_push_token( $orders[10], $push );
}
check( 'push tokens deduplicated, newest three kept', Guest_Orders::push_tokens( $orders[10] ) === array( 'ExponentPushToken[b]', 'ExponentPushToken[c]', 'ExponentPushToken[d]' ) );
Guest_Orders::remove_push_tokens( $orders[10], array( 'ExponentPushToken[c]' ) );
check( 'dead push token removed', Guest_Orders::push_tokens( $orders[10] ) === array( 'ExponentPushToken[b]', 'ExponentPushToken[d]' ) );

Guest_Orders::attach_to_customer( $orders[10], 7 );
check( 'claimed order belongs to the customer', 7 === $orders[10]->get_customer_id() );
check( 'claimed order no longer readable with the token', null === Guest_Orders::find( 10, $rotated ) );
check( 'claimed order drops guest push tokens', array() === Guest_Orders::push_tokens( $orders[10] ) );

exit( $failures > 0 ? 1 : 0 );
