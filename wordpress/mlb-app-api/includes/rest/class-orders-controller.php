<?php
/**
 * Customer orders: place an order, list history, read one order (status).
 *
 * App orders are regular WooCommerce orders. Moving them to `processing` lets the
 * existing GrandChef plugin send them to the restaurant; the app never talks to GrandChef.
 */

namespace MLB\AppApi\Rest;

use MLB\AppApi\Auth;
use MLB\AppApi\Coupons;
use MLB\AppApi\Formatter;
use MLB\AppApi\Settings;
use MLB\AppApi\Statuses;

defined( 'ABSPATH' ) || exit;

class Orders_Controller {

	private const MAX_ITEMS        = 50;
	private const MAX_QUANTITY     = 50;
	private const PREFERENCES_META = 'Preferinte'; // Exact key read by the GrandChef plugin.

	/**
	 * Seconds before a GrandChef connection gives up. The GrandChef plugin calls fsockopen()
	 * without a timeout, so without this a network problem hangs the request until a 504.
	 */
	private const GRANDCHEF_SOCKET_TIMEOUT = 10;

	public function register_routes( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/orders',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_orders' ),
					'permission_callback' => array( Auth::class, 'require_customer' ),
					'args'                => array(
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 20,
							'minimum' => 1,
							'maximum' => 50,
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_order' ),
					'permission_callback' => array( Auth::class, 'require_customer' ),
					'args'                => $this->create_args(),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/orders/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_order' ),
				'permission_callback' => array( Auth::class, 'require_customer' ),
			)
		);
	}

	public function list_orders( \WP_REST_Request $request ) {
		$result = wc_get_orders(
			array(
				'customer_id' => get_current_user_id(),
				'type'        => 'shop_order',
				'status'      => array_keys( wc_get_order_statuses() ),
				'orderby'     => 'date',
				'order'       => 'DESC',
				'limit'       => (int) $request['per_page'],
				'paged'       => (int) $request['page'],
				'paginate'    => true,
			)
		);

		$response = rest_ensure_response( array_map( array( Formatter::class, 'order' ), $result->orders ) );
		$response->header( 'X-WP-Total', (string) $result->total );
		$response->header( 'X-WP-TotalPages', (string) $result->max_num_pages );

		return $response;
	}

	public function get_order( \WP_REST_Request $request ) {
		$order = $this->find_customer_order( (int) $request['id'] );
		if ( ! $order ) {
			return new \WP_Error( 'mlb_order_not_found', 'Comanda nu a fost găsită.', array( 'status' => 404 ) );
		}

		return rest_ensure_response( Formatter::order( $order ) );
	}

	public function create_order( \WP_REST_Request $request ) {
		$customer_id     = get_current_user_id();
		$client_order_id = (string) $request['client_order_id'];

		// A retried request (e.g. after a timeout) returns the order already created.
		if ( '' !== $client_order_id ) {
			$existing = $this->find_by_client_order_id( $customer_id, $client_order_id );
			if ( $existing ) {
				return rest_ensure_response( Formatter::order( $existing ) );
			}
		}

		$location = Settings::get_location( (string) $request['location_id'] );
		if ( ! $location ) {
			return self::error( 'mlb_invalid_location', 'Punctul de lucru ales nu există.' );
		}

		$fulfillment = (string) $request['fulfillment'];
		$method      = 'delivery' === $fulfillment ? Settings::get_delivery_method() : Settings::get_pickup_method();
		if ( ! $method || empty( $location[ $fulfillment ] ) ) {
			$message = 'delivery' === $fulfillment ? 'Livrarea nu este disponibilă pentru acest punct de lucru.' : 'Ridicarea nu este disponibilă pentru acest punct de lucru.';
			return self::error( 'mlb_fulfillment_unavailable', $message );
		}

		$address = (array) $request['address'];
		if ( 'delivery' === $fulfillment && ( '' === trim( (string) ( $address['address_1'] ?? '' ) ) || '' === trim( (string) ( $address['city'] ?? '' ) ) ) ) {
			return self::error( 'mlb_address_required', 'Adresa de livrare este obligatorie.' );
		}

		$gateways = WC()->payment_gateways()->payment_gateways();
		$gateway  = $gateways[ (string) $request['payment_method'] ] ?? null;
		if ( ! $gateway || 'yes' !== $gateway->enabled ) {
			return self::error( 'mlb_payment_unavailable', 'Metoda de plată nu este disponibilă.' );
		}

		$lines = self::resolve_lines( (array) $request['items'] );
		if ( is_wp_error( $lines ) ) {
			return $lines;
		}

		// Checked before the order exists, so a wrong code never leaves a half-made order behind.
		$customer     = new \WC_Customer( $customer_id );
		$coupon_codes = Coupons::normalize_codes( $request['coupon_codes'] );
		if ( $coupon_codes ) {
			$preview = Coupons::preview( $lines, $coupon_codes, $customer );
			if ( is_wp_error( $preview ) ) {
				return $preview;
			}
		}

		$order    = wc_create_order(
			array(
				'customer_id' => $customer_id,
				'created_via' => 'mlb-app',
			)
		);
		if ( is_wp_error( $order ) ) {
			return self::error( 'mlb_order_failed', 'Comanda nu a putut fi creată. Încearcă din nou.', 500 );
		}

		foreach ( $lines as $line ) {
			$item_id = $order->add_product( $line['product'], $line['quantity'] );
			if ( '' !== $line['preferences'] && $item_id ) {
				$item = $order->get_item( $item_id );
				$item->add_meta_data( self::PREFERENCES_META, $line['preferences'], true );
				$item->save();
			}
		}

		$phone = sanitize_text_field( (string) $request['phone'] );
		$order->set_billing_first_name( $customer->get_first_name() );
		$order->set_billing_last_name( $customer->get_last_name() );
		$order->set_billing_email( $customer->get_email() );
		$order->set_billing_phone( $phone );

		if ( 'delivery' === $fulfillment ) {
			$order->set_shipping_first_name( $customer->get_first_name() );
			$order->set_shipping_last_name( $customer->get_last_name() );
			$order->set_shipping_address_1( sanitize_text_field( (string) $address['address_1'] ) );
			$order->set_shipping_address_2( sanitize_text_field( (string) ( $address['address_2'] ?? '' ) ) );
			$order->set_shipping_city( sanitize_text_field( (string) $address['city'] ) );
			$order->set_shipping_country( 'RO' );
			$order->set_shipping_phone( $phone );
			$order->set_billing_address_1( $order->get_shipping_address_1() );
			$order->set_billing_address_2( $order->get_shipping_address_2() );
			$order->set_billing_city( $order->get_shipping_city() );
			$order->set_billing_country( 'RO' );
		}

		$order->set_payment_method( $gateway );

		$note = sanitize_textarea_field( (string) $request['note'] );
		$order->update_meta_data( '_mlb_note', $note );
		$order->set_customer_note( Settings::is_test_mode() ? trim( Settings::TEST_ORDER_NOTE . "\n" . $note ) : $note );

		$order->update_meta_data( '_mlb_source', 'app' );
		$order->update_meta_data( '_mlb_location_id', (string) $location['id'] );
		$order->update_meta_data( '_mlb_fulfillment', $fulfillment );
		Statuses::record( $order, Statuses::RECEIVED );
		if ( '' !== $client_order_id ) {
			$order->update_meta_data( '_mlb_client_order_id', $client_order_id );
		}

		// Item totals first, so the free delivery threshold can look at the subtotal.
		$order->calculate_totals();
		$order->save();

		// Coupons become order discounts; the GrandChef plugin's "Spread discount" puts them on the products.
		foreach ( $coupon_codes as $code ) {
			$applied = $order->apply_coupon( $code );
			if ( is_wp_error( $applied ) ) {
				$order->delete( true );
				return self::error( 'mlb_coupon_invalid', wp_strip_all_tags( $applied->get_error_message() ) );
			}
		}

		$order->add_item( $this->shipping_item( $method, self::delivery_cost_for( $fulfillment, $location, (float) $order->get_subtotal() ) ) );
		$order->calculate_totals();
		$order->save();

		do_action( 'mlb_app_api_order_created', $order, $request );

		// Moving to processing triggers the GrandChef plugin, which sends the order to the restaurant.
		$previous_timeout = ini_get( 'default_socket_timeout' );
		ini_set( 'default_socket_timeout', (string) self::GRANDCHEF_SOCKET_TIMEOUT ); // phpcs:ignore WordPress.PHP.IniSet.Risky
		$order->update_status( 'processing', 'Comandă plasată din aplicație.' );
		ini_set( 'default_socket_timeout', (string) $previous_timeout ); // phpcs:ignore WordPress.PHP.IniSet.Risky

		return new \WP_REST_Response( Formatter::order( wc_get_order( $order->get_id() ) ), 201 );
	}

	/**
	 * Validates the requested items against the catalogue. Prices always come from WooCommerce.
	 *
	 * @param array<int, mixed> $items
	 * @return array<int, array{product: \WC_Product, quantity: int, preferences: string}>|\WP_Error
	 */
	public static function resolve_lines( array $items ) {
		if ( empty( $items ) ) {
			return self::error( 'mlb_empty_cart', 'Coșul este gol.' );
		}

		$lines = array();
		foreach ( $items as $item ) {
			$product_id   = (int) ( $item['product_id'] ?? 0 );
			$variation_id = (int) ( $item['variation_id'] ?? 0 );
			$product      = wc_get_product( $variation_id > 0 ? $variation_id : $product_id );

			$valid = $product
				&& 'publish' === get_post_status( $product_id )
				&& ( $variation_id > 0
					? $product instanceof \WC_Product_Variation && $product->get_parent_id() === $product_id
					: ! $product instanceof \WC_Product_Variable && ! $product instanceof \WC_Product_Variation );
			if ( ! $valid ) {
				return self::error( 'mlb_invalid_product', 'Un produs din coș nu mai este disponibil.' );
			}

			if ( ! $product->is_purchasable() || ! $product->is_in_stock() ) {
				/* translators: %s: product name */
				return self::error( 'mlb_product_unavailable', sprintf( 'Produsul „%s” nu mai este disponibil.', $product->get_name() ) );
			}

			$lines[] = array(
				'product'     => $product,
				'quantity'    => (int) $item['quantity'],
				'preferences' => sanitize_text_field( (string) ( $item['preferences'] ?? '' ) ),
			);
		}

		return $lines;
	}

	/**
	 * Delivery fee for a subtotal (before discounts): zero above the location's free delivery threshold.
	 *
	 * @param array<string, mixed> $location
	 */
	public static function delivery_cost( array $location, float $subtotal ): float {
		$threshold = Settings::get_free_delivery_threshold( $location );
		return ( $threshold > 0 && $subtotal >= $threshold ) ? 0.0 : Settings::get_delivery_fee( $location );
	}

	/**
	 * @param array<string, mixed> $location
	 */
	private static function delivery_cost_for( string $fulfillment, array $location, float $subtotal ): float {
		return 'delivery' === $fulfillment ? self::delivery_cost( $location, $subtotal ) : 0.0;
	}

	/**
	 * Uses the zone's own method title and ID: the GrandChef plugin maps delivery types by title.
	 * Free delivery keeps the delivery (flat_rate) method with a zero cost; the free_shipping
	 * method is mapped to pickup in GrandChef.
	 */
	private function shipping_item( \WC_Shipping_Method $method, float $cost ): \WC_Order_Item_Shipping {
		$item = new \WC_Order_Item_Shipping();
		$item->set_method_title( $method->get_title() );
		$item->set_method_id( $method->id );
		$item->set_instance_id( (string) $method->get_instance_id() );
		$item->set_total( wc_format_decimal( $cost ) );

		return $item;
	}

	private function find_customer_order( int $order_id ): ?\WC_Order {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order || $order->get_customer_id() !== get_current_user_id() ) {
			return null;
		}
		return $order;
	}

	private function find_by_client_order_id( int $customer_id, string $client_order_id ): ?\WC_Order {
		$orders = wc_get_orders(
			array(
				'customer_id' => $customer_id,
				'limit'       => 1,
				'status'      => array_keys( wc_get_order_statuses() ),
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'key'   => '_mlb_client_order_id',
						'value' => $client_order_id,
					),
				),
			)
		);
		return $orders[0] ?? null;
	}

	private static function error( string $code, string $message, int $status = 400 ): \WP_Error {
		return new \WP_Error( $code, $message, array( 'status' => $status ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function items_schema(): array {
		return array(
			'type'     => 'array',
			'required' => true,
			'minItems' => 1,
			'maxItems' => self::MAX_ITEMS,
			'items'    => array(
				'type'       => 'object',
				'properties' => array(
					'product_id'   => array(
						'type'     => 'integer',
						'required' => true,
					),
					'variation_id' => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'quantity'     => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
						'maximum'  => self::MAX_QUANTITY,
					),
					'preferences'  => array(
						'type'      => 'string',
						'maxLength' => 200,
					),
				),
			),
		);
	}

	/**
	 * A promo code plus a loyalty reward at most.
	 *
	 * @return array<string, mixed>
	 */
	public static function coupon_codes_schema(): array {
		return array(
			'type'     => 'array',
			'default'  => array(),
			'maxItems' => 2,
			'items'    => array(
				'type'      => 'string',
				'maxLength' => 50,
			),
		);
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function create_args(): array {
		return array(
			'items'           => self::items_schema(),
			'coupon_codes'    => self::coupon_codes_schema(),
			'fulfillment'     => array(
				'type'     => 'string',
				'enum'     => array( 'delivery', 'pickup' ),
				'required' => true,
			),
			'location_id'     => array(
				'type'     => 'string',
				'required' => true,
			),
			'address'         => array(
				'type'       => 'object',
				'default'    => array(),
				'properties' => array(
					'address_1' => array(
						'type'      => 'string',
						'maxLength' => 200,
					),
					'address_2' => array(
						'type'      => 'string',
						'maxLength' => 200,
					),
					'city'      => array(
						'type'      => 'string',
						'maxLength' => 100,
					),
				),
			),
			'phone'           => array(
				'type'      => 'string',
				'required'  => true,
				'minLength' => 6,
				'maxLength' => 30,
			),
			'note'            => array(
				'type'      => 'string',
				'default'   => '',
				'maxLength' => 500,
			),
			'payment_method'  => array(
				'type'    => 'string',
				'enum'    => array( 'cod' ),
				'default' => 'cod',
			),
			'client_order_id' => array(
				'type'      => 'string',
				'default'   => '',
				'maxLength' => 64,
			),
		);
	}
}
