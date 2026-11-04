<?php
/**
 * Plain PHP checks for who counts as a kitchen account and which location it sees.
 *
 * Run: php wordpress/mlb-app-api/tests/kitchen-role-test.php
 */

namespace MLB\AppApi {
	class Settings {
		public static function get_location( string $id ): ?array {
			return in_array( $id, array( 'centru', 'gara' ), true ) ? array( 'id' => $id ) : null;
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ );
	$caps = array(
		1 => array( 'mlb_manage_app_orders', 'manage_woocommerce' ), // admin
		2 => array( 'mlb_manage_app_orders' ),                       // kitchen, one location
		3 => array( 'mlb_manage_app_orders' ),                       // kitchen, all locations
		4 => array( 'mlb_manage_app_orders' ),                       // kitchen, deleted location
		5 => array(),                                                // customer
	);
	$meta = array( 1 => 'centru', 2 => 'gara', 4 => 'vechi' );
	function user_can( int $id, string $cap ): bool {
		global $caps;
		return in_array( $cap, $caps[ $id ] ?? array(), true );
	}
	function get_user_meta( int $id, string $key, bool $single ) {
		global $meta;
		return $meta[ $id ] ?? '';
	}
	function get_current_user_id(): int {
		return 0;
	}

	require __DIR__ . '/../includes/admin/class-kitchen-role.php';

	$failures = 0;
	function check( string $name, bool $ok ): void {
		global $failures;
		echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . PHP_EOL;
		$failures += $ok ? 0 : 1;
	}

	use MLB\AppApi\Admin\Kitchen_Role;

	check( 'admin is not a kitchen account', ! Kitchen_Role::is_kitchen( 1 ) );
	check( 'admin sees every location', '' === Kitchen_Role::location_of( 1 ) );
	check( 'kitchen account', Kitchen_Role::is_kitchen( 2 ) );
	check( 'kitchen sees its location', 'gara' === Kitchen_Role::location_of( 2 ) );
	check( 'kitchen without location sees all', '' === Kitchen_Role::location_of( 3 ) );
	check( 'deleted location falls back to all', '' === Kitchen_Role::location_of( 4 ) );
	check( 'customer is not a kitchen account', ! Kitchen_Role::is_kitchen( 5 ) );
	check( 'logged out is not a kitchen account', ! Kitchen_Role::is_kitchen() );

	exit( $failures > 0 ? 1 : 0 );
}
