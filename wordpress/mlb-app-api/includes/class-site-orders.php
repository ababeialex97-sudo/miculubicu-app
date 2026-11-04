<?php
/**
 * Orders placed on the website, shown in the "Comenzi aplicație" panel next to the app's.
 *
 * The site checkout has no location or fulfillment choice of its own, so the first time the
 * panel sees a site order it fills them in: pickup when the customer chose the pickup
 * shipping method, delivery otherwise, and the first location offering it.
 */

namespace MLB\AppApi;

defined( 'ABSPATH' ) || exit;

class Site_Orders {

	public const SOURCE = 'site';

	/** How WooCommerce marks orders from the classic and the block checkout. */
	private const CREATED_VIA = array( 'checkout', 'store-api' );

	public static function is_site_order( \WC_Order $order ): bool {
		$source = (string) $order->get_meta( '_mlb_source' );
		return self::SOURCE === $source || ( '' === $source && in_array( $order->get_created_via(), self::CREATED_VIA, true ) );
	}

	/**
	 * Site orders created since $since (Unix time), newest first, ready for the panel.
	 *
	 * @return \WC_Order[]
	 */
	public static function since( int $since ): array {
		$orders = array();
		foreach ( self::CREATED_VIA as $via ) {
			$orders = array_merge(
				$orders,
				wc_get_orders(
					array(
						'type'         => 'shop_order',
						'status'       => array_keys( wc_get_order_statuses() ),
						'limit'        => 200,
						'created_via'  => $via,
						'date_created' => '>=' . $since,
					)
				)
			);
		}

		$orders = array_filter( $orders, array( self::class, 'is_site_order' ) );
		array_walk( $orders, array( self::class, 'prepare' ) );
		return array_values( $orders );
	}

	/**
	 * Gives a site order what the panel and the time estimate need, once.
	 */
	public static function prepare( \WC_Order $order ): void {
		if ( '' !== (string) $order->get_meta( '_mlb_fulfillment' ) ) {
			return;
		}

		$fulfillment = self::chose_pickup( $order ) ? 'pickup' : 'delivery';
		$location    = self::default_location( $fulfillment );

		$order->update_meta_data( '_mlb_source', self::SOURCE );
		$order->update_meta_data( '_mlb_fulfillment', $fulfillment );
		if ( $location ) {
			$order->update_meta_data( '_mlb_location_id', (string) $location['id'] );
		}
		$order->save();
	}

	/**
	 * The location website orders go to: the first one offering delivery, or pickup.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function default_location( string $fulfillment ): ?array {
		foreach ( Settings::get_locations() as $location ) {
			if ( ! empty( $location[ $fulfillment ] ) ) {
				return $location;
			}
		}
		return null;
	}

	private static function chose_pickup( \WC_Order $order ): bool {
		$pickup = Settings::get_pickup_method();
		foreach ( $order->get_shipping_methods() as $line ) {
			if ( $pickup ? (int) $line->get_instance_id() === (int) $pickup->get_instance_id() : 'free_shipping' === $line->get_method_id() ) {
				return true;
			}
		}
		return false;
	}
}
