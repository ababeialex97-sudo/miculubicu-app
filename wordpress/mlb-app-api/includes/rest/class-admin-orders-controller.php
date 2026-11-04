<?php
/**
 * Staff endpoints behind the "Comenzi aplicație" panel. Cookie-authenticated WordPress users only.
 */

namespace MLB\AppApi\Rest;

use MLB\AppApi\Delivery_Quote;
use MLB\AppApi\Formatter;
use MLB\AppApi\Order_Status;
use MLB\AppApi\Settings;
use MLB\AppApi\Site_Orders;
use MLB\AppApi\Statuses;

defined( 'ABSPATH' ) || exit;

class Admin_Orders_Controller {

	/** Open orders older than this are assumed forgotten and drop off the panel. */
	private const ACTIVE_WINDOW = 2 * DAY_IN_SECONDS;

	public function register_routes( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/admin/orders',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_orders' ),
				'permission_callback' => array( self::class, 'can_manage' ),
				'args'                => array(
					'scope' => array(
						'type'    => 'string',
						'enum'    => array( 'active', 'today' ),
						'default' => 'active',
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/admin/orders/(?P<id>\d+)/status',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'change_status' ),
				'permission_callback' => array( self::class, 'can_manage' ),
				'args'                => array(
					'status'       => array(
						'type'     => 'string',
						'enum'     => array_keys( Statuses::labels() ),
						'required' => true,
					),
					'prep_minutes' => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 240,
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/admin/orders/(?P<id>\d+)/delay',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'delay' ),
				'permission_callback' => array( self::class, 'can_manage' ),
				'args'                => array(
					'minutes' => array(
						'type'     => 'integer',
						'minimum'  => 1,
						'maximum'  => 120,
						'required' => true,
					),
				),
			)
		);
	}

	public static function can_manage(): bool {
		return current_user_can( 'edit_shop_orders' );
	}

	public function list_orders( \WP_REST_Request $request ) {
		$scope = (string) $request['scope'];
		$since = 'today' === $scope
			? strtotime( 'today', current_time( 'timestamp' ) ) - (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
			: time() - self::ACTIVE_WINDOW;

		$orders = wc_get_orders(
			array(
				'type'         => 'shop_order',
				'status'       => array_keys( wc_get_order_statuses() ),
				'limit'        => 200,
				'orderby'      => 'date',
				'order'        => 'DESC',
				'date_created' => '>=' . $since,
				'meta_query'   => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'key'   => '_mlb_source',
						'value' => 'app',
					),
				),
			)
		);

		// Website orders too, so the kitchen sees every order in one place.
		$orders = array_merge( $orders, Site_Orders::since( $since ) );
		usort( $orders, static fn ( \WC_Order $a, \WC_Order $b ): int => $b->get_date_created() <=> $a->get_date_created() );

		if ( 'active' === $scope ) {
			$orders = array_filter( $orders, static fn ( \WC_Order $order ): bool => ! Statuses::is_final( Statuses::for_order( $order ) ) );
		}

		return rest_ensure_response( array_values( array_map( array( self::class, 'format' ), $orders ) ) );
	}

	public function change_status( \WP_REST_Request $request ) {
		$order = self::app_order( (int) $request['id'] );
		if ( is_wp_error( $order ) ) {
			return $order;
		}

		$prep   = null === $request['prep_minutes'] ? null : (int) $request['prep_minutes'];
		$result = Order_Status::change( $order, (string) $request['status'], $prep );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( self::format( wc_get_order( $order->get_id() ) ) );
	}

	public function delay( \WP_REST_Request $request ) {
		$order = self::app_order( (int) $request['id'] );
		if ( is_wp_error( $order ) ) {
			return $order;
		}

		$result = Order_Status::delay( $order, (int) $request['minutes'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( self::format( wc_get_order( $order->get_id() ) ) );
	}

	/**
	 * @return \WC_Order|\WP_Error
	 */
	private static function app_order( int $id ) {
		$order = wc_get_order( $id );
		if ( ! $order instanceof \WC_Order || ( 'app' !== $order->get_meta( '_mlb_source' ) && ! Site_Orders::is_site_order( $order ) ) ) {
			return new \WP_Error( 'mlb_order_not_found', 'Comanda nu a fost găsită.', array( 'status' => 404 ) );
		}
		return $order;
	}

	/**
	 * The customer-facing order plus what staff need: who, where, GrandChef delivery, next steps.
	 *
	 * @return array<string, mixed>
	 */
	public static function format( \WC_Order $order ): array {
		// Website orders get their map point here, the first time staff see them.
		if ( 'delivery' === $order->get_meta( '_mlb_fulfillment' ) && ! $order->meta_exists( Delivery_Quote::MATCH_META ) && ! Statuses::is_final( Statuses::for_order( $order ) ) ) {
			Delivery_Quote::locate_order( $order );
			$order->save();
		}
		$data     = Formatter::order( $order );
		$location = Settings::get_location( (string) $order->get_meta( '_mlb_location_id' ) );

		return array_merge(
			$data,
			array(
				'source'        => 'app' === $order->get_meta( '_mlb_source' ) ? 'app' : 'site',
				'customer_name' => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
				'location_name' => $location ? (string) $location['name'] : '',
				'note'          => $order->get_customer_note(),
				'next_statuses' => Statuses::next( $data['status'], (string) $data['fulfillment'] ),
				'address_match' => (string) $order->get_meta( Delivery_Quote::MATCH_META ) ?: null,
				'prep_minutes'  => $order->meta_exists( Delivery_Quote::PREP_META ) ? (int) $order->get_meta( Delivery_Quote::PREP_META ) : null,
				'drive_minutes' => $order->meta_exists( Delivery_Quote::DRIVE_META ) ? (int) $order->get_meta( Delivery_Quote::DRIVE_META ) : null,
				'grandchef'     => array(
					'sent'     => '' !== self::grandchef_order_id( $order ),
					'order_id' => self::grandchef_order_id( $order ),
				),
				'edit_url'      => $order->get_edit_order_url(),
			)
		);
	}

	/**
	 * The GrandChef plugin writes its meta with update_post_meta(), which with HPOS does not
	 * reach the order's own meta table, so the post meta is checked as well.
	 */
	private static function grandchef_order_id( \WC_Order $order ): string {
		$id = (string) $order->get_meta( '_gc_order_id' );
		if ( '' === $id ) {
			$id = (string) get_post_meta( $order->get_id(), '_gc_order_id', true );
		}
		return $id;
	}
}
