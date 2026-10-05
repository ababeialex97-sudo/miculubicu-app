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

	public const META_KEY     = '_mlb_status';
	public const HISTORY_META = '_mlb_status_history';

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
	 * Sets the status and appends it to the history, without saving the order.
	 */
	public static function record( \WC_Order $order, string $status ): void {
		$history   = self::history( $order );
		$history[] = array(
			'status' => $status,
			'at'     => gmdate( DATE_ATOM ),
		);
		$order->update_meta_data( self::META_KEY, $status );
		$order->update_meta_data( self::HISTORY_META, $history );
	}

	/**
	 * @return array<int, array{status: string, at: string}>
	 */
	public static function history( \WC_Order $order ): array {
		$history = $order->get_meta( self::HISTORY_META );
		return is_array( $history ) ? array_values( $history ) : array();
	}

	/**
	 * Statuses staff can move an order to next, in the order the panel shows them.
	 *
	 * @return string[]
	 */
	public static function next( string $status, string $fulfillment ): array {
		switch ( $status ) {
			case self::RECEIVED:
				return array( self::CONFIRMED, self::CANCELLED );
			case self::CONFIRMED:
				return array( self::PREPARING, self::CANCELLED );
			case self::PREPARING:
				return array( 'pickup' === $fulfillment ? self::READY_FOR_PICKUP : self::ON_THE_WAY, self::CANCELLED );
			case self::ON_THE_WAY:
			case self::READY_FOR_PICKUP:
				return array( self::COMPLETED, self::CANCELLED );
			default:
				return array();
		}
	}

	public static function is_final( string $status ): bool {
		return self::COMPLETED === $status || self::CANCELLED === $status;
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
