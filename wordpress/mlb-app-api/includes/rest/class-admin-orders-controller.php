<?php
/**
 * Staff endpoints behind the "Comenzi aplicație" panel. Cookie-authenticated WordPress users only.
 */

namespace MLB\AppApi\Rest;

use MLB\AppApi\Formatter;
use MLB\AppApi\Order_Status;
use MLB\AppApi\Settings;
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
					'status' => array(
						'type'     => 'string',
						'enum'     => array_keys( Statuses::labels() ),
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

		if ( 'active' === $scope ) {
			$orders = array_filter( $orders, static fn ( \WC_Order $order ): bool => ! Statuses::is_final( Statuses::for_order( $order ) ) );
		}

		return rest_ensure_response( array_values( array_map( array( self::class, 'format' ), $orders ) ) );
	}

	public function change_status( \WP_REST_Request $request ) {
		$order = wc_get_order( (int) $request['id'] );
		if ( ! $order instanceof \WC_Order || 'app' !== $order->get_meta( '_mlb_source' ) ) {
			return new \WP_Error( 'mlb_order_not_found', 'Comanda nu a fost găsită.', array( 'status' => 404 ) );
		}

		$result = Order_Status::change( $order, (string) $request['status'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( self::format( wc_get_order( $order->get_id() ) ) );
	}

	/**
	 * The customer-facing order plus what staff need: who, where, GrandChef delivery, next steps.
	 *
	 * @return array<string, mixed>
	 */
	public static function format( \WC_Order $order ): array {
		$data     = Formatter::order( $order );
		$location = Settings::get_location( (string) $order->get_meta( '_mlb_location_id' ) );

		return array_merge(
			$data,
			array(
				'customer_name' => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
				'location_name' => $location ? (string) $location['name'] : '',
				'note'          => $order->get_customer_note(),
				'next_statuses' => Statuses::next( $data['status'], (string) $data['fulfillment'] ),
				'grandchef'     => array(
					'sent'     => '' !== (string) $order->get_meta( '_gc_order_id' ),
					'order_id' => (string) $order->get_meta( '_gc_order_id' ),
				),
				'edit_url'      => $order->get_edit_order_url(),
			)
		);
	}
}
