<?php
/**
 * Order statuses shown in the app.
 *
 * GrandChef never reports statuses back, so staff set these from the admin panel (step 4).
 * They are stored in the `_mlb_status` order meta, separate from the WooCommerce status,
 * which stays `processing` so the GrandChef plugin is not triggered again.
 */

namespace MLB\AppApi;

defined( 'ABSPATH' ) || exit;

class Statuses {

	public const META_KEY = '_mlb_status';

	public const RECEIVED         = 'received';
	public const CONFIRMED        = 'confirmed';
	public const PREPARING        = 'preparing';
	public const ON_THE_WAY       = 'on_the_way';
	public const READY_FOR_PICKUP = 'ready_for_pickup';
	public const COMPLETED        = 'completed';
	public const CANCELLED        = 'cancelled';

	/**
	 * @return array<string, string>
	 */
	public static function labels(): array {
		return array(
			self::RECEIVED         => 'Comandă primită',
			self::CONFIRMED        => 'Comandă confirmată',
			self::PREPARING        => 'Se pregătește',
			self::ON_THE_WAY       => 'În drum spre tine',
			self::READY_FOR_PICKUP => 'Gata de ridicare',
			self::COMPLETED        => 'Finalizată',
			self::CANCELLED        => 'Anulată',
		);
	}

	public static function is_valid( string $status ): bool {
		return array_key_exists( $status, self::labels() );
	}

	/**
	 * App status for any order, including orders placed on the website.
	 */
	public static function for_order( \WC_Order $order ): string {
		$status = (string) $order->get_meta( self::META_KEY );
		if ( self::is_valid( $status ) ) {
			return $status;
		}

		switch ( $order->get_status() ) {
			case 'completed':
				return self::COMPLETED;
			case 'cancelled':
			case 'failed':
			case 'refunded':
				return self::CANCELLED;
			default:
				return self::RECEIVED;
		}
	}
}
