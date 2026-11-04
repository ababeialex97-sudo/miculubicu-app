<?php
/**
 * Changes an order's app status: history, WooCommerce status, order note and push.
 */

namespace MLB\AppApi;

defined( 'ABSPATH' ) || exit;

class Order_Status {

	/** Set while this class changes the WooCommerce status, so the sync hooks don't loop. */
	private static bool $syncing = false;

	public static function init(): void {
		// Staff who complete or cancel an order from WooCommerce keep the app in sync.
		add_action( 'woocommerce_order_status_completed', array( self::class, 'sync_from_woocommerce' ), 20, 2 );
		add_action( 'woocommerce_order_status_cancelled', array( self::class, 'sync_from_woocommerce' ), 20, 2 );
	}

	/**
	 * @param int|null $prep_minutes Preparation time the kitchen picked when accepting.
	 * @return true|\WP_Error
	 */
	public static function change( \WC_Order $order, string $status, ?int $prep_minutes = null ) {
		if ( ! Statuses::is_valid( $status ) ) {
			return new \WP_Error( 'mlb_invalid_status', 'Status necunoscut.', array( 'status' => 400 ) );
		}

		$current = Statuses::for_order( $order );
		if ( $current === $status ) {
			return true;
		}
		if ( Statuses::is_final( $current ) ) {
			return new \WP_Error( 'mlb_order_closed', 'Comanda e deja închisă.', array( 'status' => 409 ) );
		}

		Statuses::record( $order, $status );
		$order->add_order_note( 'Status în aplicație: ' . Statuses::labels()[ $status ] );
		if ( Statuses::CONFIRMED === $status && $prep_minutes ) {
			Delivery_Quote::record_estimate( $order, $prep_minutes );
		}
		if ( Statuses::ON_THE_WAY === $status ) {
			Delivery_Quote::record_eta( $order );
		}

		self::$syncing = true;
		try {
			// Completed orders count for loyalty (step 5); cancelled ones release stock.
			if ( Statuses::COMPLETED === $status ) {
				$order->update_status( 'completed' );
			} elseif ( Statuses::CANCELLED === $status ) {
				$order->update_status( 'cancelled' );
			} else {
				$order->save();
			}
		} finally {
			self::$syncing = false;
		}

		Push::notify_status( $order, $status );
		do_action( 'mlb_app_api_status_changed', $order, $status, $current );

		return true;
	}

	/**
	 * The kitchen needs longer than it said: moves the times and tells the customer.
	 *
	 * @return true|\WP_Error
	 */
	public static function delay( \WC_Order $order, int $minutes ) {
		if ( ! in_array( Statuses::for_order( $order ), array( Statuses::CONFIRMED, Statuses::PREPARING ), true ) || ! $order->get_meta( Delivery_Quote::READY_META ) ) {
			return new \WP_Error( 'mlb_cannot_delay', 'Timpul se poate schimba doar cât comanda e în lucru.', array( 'status' => 409 ) );
		}
		Delivery_Quote::delay( $order, $minutes );
		$order->save();
		Push::notify_delay( $order );
		return true;
	}

	public static function sync_from_woocommerce( int $order_id, \WC_Order $order ): void {
		if ( self::$syncing || 'app' !== $order->get_meta( '_mlb_source' ) ) {
			return;
		}

		$status = 'completed' === $order->get_status() ? Statuses::COMPLETED : Statuses::CANCELLED;
		if ( Statuses::for_order( $order ) === $status ) {
			return;
		}

		Statuses::record( $order, $status );
		$order->save();
		Push::notify_status( $order, $status );
	}
}
