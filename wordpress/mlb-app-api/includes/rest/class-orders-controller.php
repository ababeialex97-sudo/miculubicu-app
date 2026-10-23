<?php
/**
 * Orders: place an order (with an account or as a guest), list history, read one order (status),
 * and move guest orders to an account after logging in.
 *
 * App orders are regular WooCommerce orders. Moving them to `processing` lets the
 * existing GrandChef plugin send them to the restaurant; the app never talks to GrandChef.
 */

namespace MLB\AppApi\Rest;

use MLB\AppApi\Auth;
use MLB\AppApi\Coupons;
use MLB\AppApi\Formatter;
use MLB\AppApi\Guest_Orders;
use MLB\AppApi\Loyalty;
use MLB\AppApi\Settings;
use MLB\AppApi\Statuses;

defined( 'ABSPATH' ) || exit;

class Orders_Controller {

	private const MAX_ITEMS        = 50;
	private const MAX_QUANTITY     = 50;
	private const PREFERENCES_META = 'Preferinte'; // Exact key read by the GrandChef plugin.

	/** Payment methods the app can use: cash, or card to the courier / at the counter. */
	public const PAYMENT_METHODS = array( 'cod', 'mlb_card' ); // mlb_card: Card_Gateway::ID.

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

		// Ordering without an account: the response carries a token that only this device keeps.
		register_rest_route(
			$namespace,
			'/orders/guest',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_guest_order' ),
				'permission_callback' => '__return_true',
				'args'                => $this->create_args() + self::guest_contact_args(),
			)
		);

		register_rest_route(
			$namespace,
			'/orders/guest/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_guest_order' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$namespace,
			'/orders/guest/lookup',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'lookup_guest_orders' ),
				'permission_callback' => '__return_true',
				'args'                => array( 'orders' => self::guest_refs_schema() ),
			)
		);

		register_rest_route(
			$namespace,
			'/orders/guest/(?P<id>\d+)/push-token',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'register_guest_push_token' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'token' => array(
						'type'     => 'string',
						'required' => true,
						'pattern'  => Push_Controller::TOKEN_PATTERN,
					),
				),
			)
		);

		// After logging in, orders placed without an account move to the account (history, loyalty stamps).
		register_rest_route(
			$namespace,
			'/orders/claim',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'claim_guest_orders' ),
				'permission_callback' => array( Auth::class, 'require_customer' ),
				'args'                => array( 'orders' => self::guest_refs_schema() ),
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

		$customer = new \WC_Customer( $customer_id );
		$order    = $this->place_order(
			$request,
			$customer_id,
			array(
				'first_name' => $customer->get_first_name(),
				'last_name'  => $customer->get_last_name(),
				'email'      => $customer->get_email(),
			)
		);
		if ( is_wp_error( $order ) ) {
			return $order;
		}

		return new \WP_REST_Response( Formatter::order( $order ), 201 );
	}

	public function create_guest_order( \WP_REST_Request $request ) {
		if ( ! Settings::guest_checkout_enabled() ) {
			return self::error( 'mlb_guest_disabled', 'Intră în cont ca să comanzi.', 403 );
		}

		$client_order_id = (string) $request['client_order_id'];
		if ( '' !== $client_order_id ) {
			$existing = $this->find_by_client_order_id( 0, $client_order_id );
			if ( $existing ) {
				// The first response never reached the app, so its token is lost: hand out a new one.
				return rest_ensure_response( self::guest_payload( $existing, Guest_Orders::issue_token( $existing ) ) );
			}
		}

		$email = trim( (string) $request['email'] );
		if ( '' !== $email && ! is_email( $email ) ) {
			return self::error( 'mlb_invalid_email', 'Adresa de email nu pare corectă.' );
		}

		if ( Guest_Orders::is_rate_limited() ) {
			return self::error( 'mlb_too_many_orders', 'Prea multe comenzi într-un timp scurt. Sună la restaurant sau încearcă mai târziu.', 429 );
		}

		$order = $this->place_order(
			$request,
			0,
			array(
				'first_name' => sanitize_text_field( (string) $request['first_name'] ),
				'last_name'  => sanitize_text_field( (string) $request['last_name'] ),
				'email'      => sanitize_email( $email ),
			)
		);
		if ( is_wp_error( $order ) ) {
			return $order;
		}

		Guest_Orders::record_order();

		return new \WP_REST_Response( self::guest_payload( $order, Guest_Orders::issue_token( $order ) ), 201 );
	}

	public function get_guest_order( \WP_REST_Request $request ) {
		$order = Guest_Orders::find( (int) $request['id'], (string) $request->get_header( Guest_Orders::TOKEN_HEADER ) );
		if ( ! $order ) {
			return new \WP_Error( 'mlb_order_not_found', 'Comanda nu a fost găsită.', array( 'status' => 404 ) );
		}

		return rest_ensure_response( Formatter::order( $order ) );
	}

	/**
	 * The device's guest orders in one request, newest first. Unknown or claimed orders are left out.
	 */
	public function lookup_guest_orders( \WP_REST_Request $request ) {
		$orders = array();
		foreach ( (array) $request['orders'] as $ref ) {
			$order = Guest_Orders::find( (int) $ref['id'], (string) $ref['token'] );
			if ( $order ) {
				$orders[] = Formatter::order( $order );
			}
		}
		usort( $orders, static fn ( array $a, array $b ): int => $b['id'] <=> $a['id'] );

		return rest_ensure_response( $orders );
	}

	public function register_guest_push_token( \WP_REST_Request $request ) {
		$order = Guest_Orders::find( (int) $request['id'], (string) $request->get_header( Guest_Orders::TOKEN_HEADER ) );
		if ( ! $order ) {
			return new \WP_Error( 'mlb_order_not_found', 'Comanda nu a fost găsită.', array( 'status' => 404 ) );
		}

		Guest_Orders::add_push_token( $order, (string) $request['token'] );

		return new \WP_REST_Response( array( 'registered' => true ), 201 );
	}

	/**
	 * Moves guest orders to the logged-in customer. Completed ones add their loyalty stamp now.
	 */
	public function claim_guest_orders( \WP_REST_Request $request ) {
		$customer_id = get_current_user_id();
		$claimed     = array();

		foreach ( (array) $request['orders'] as $ref ) {
			$order = Guest_Orders::find( (int) $ref['id'], (string) $ref['token'] );
			if ( ! $order ) {
				continue;
			}

			Guest_Orders::attach_to_customer( $order, $customer_id );
			if ( $order->has_status( 'completed' ) ) {
				Loyalty::stamp( $order->get_id(), $order );
			}
			$claimed[] = $order->get_id();
		}

		return rest_ensure_response( array( 'claimed' => $claimed ) );
	}

	/**
	 * Validates the request, creates the WooCommerce order and hands it to GrandChef.
	 *
	 * @param array{first_name: string, last_name: string, email: string} $contact
	 * @return \WC_Order|\WP_Error
	 */
	private function place_order( \WP_REST_Request $request, int $customer_id, array $contact ) {
		$client_order_id = (string) $request['client_order_id'];

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
		$customer = new \WC_Customer( $customer_id );
		if ( $customer_id <= 0 ) {
			$customer->set_billing_email( $contact['email'] );
		}
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
		$order->set_billing_first_name( $contact['first_name'] );
		$order->set_billing_last_name( $contact['last_name'] );
		$order->set_billing_email( $contact['email'] );
		$order->set_billing_phone( $phone );

		if ( 'delivery' === $fulfillment ) {
			$order->set_shipping_first_name( $contact['first_name'] );
			$order->set_shipping_last_name( $contact['last_name'] );
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

		return wc_get_order( $order->get_id() );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function guest_payload( \WC_Order $order, string $token ): array {
		return Formatter::order( $order ) + array( 'guest_token' => $token );
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
				// customer_id 0 would be ignored as "any customer", so guests are checked below.
				'customer_id' => $customer_id > 0 ? $customer_id : '',
				'limit'       => 5,
				'status'      => array_keys( wc_get_order_statuses() ),
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'key'   => '_mlb_client_order_id',
						'value' => $client_order_id,
					),
				),
			)
		);
		foreach ( $orders as $order ) {
			if ( $order->get_customer_id() === $customer_id ) {
				return $order;
			}
		}
		return null;
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
	 * Who the order is for, when there is no account to take it from.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function guest_contact_args(): array {
		return array(
			'first_name' => array(
				'type'      => 'string',
				'required'  => true,
				'minLength' => 1,
				'maxLength' => 100,
			),
			'last_name'  => array(
				'type'      => 'string',
				'default'   => '',
				'maxLength' => 100,
			),
			// Optional: only used for the order confirmation and to link the order to an account later.
			'email'      => array(
				'type'      => 'string',
				'default'   => '',
				'maxLength' => 100,
			),
		);
	}

	/**
	 * A device's guest orders: each order ID with the token handed out when it was placed.
	 *
	 * @return array<string, mixed>
	 */
	private static function guest_refs_schema(): array {
		return array(
			'type'     => 'array',
			'required' => true,
			'maxItems' => Guest_Orders::MAX_PER_DEVICE,
			'items'    => array(
				'type'       => 'object',
				'properties' => array(
					'id'    => array(
						'type'     => 'integer',
						'required' => true,
					),
					'token' => array(
						'type'      => 'string',
						'required'  => true,
						'maxLength' => 100,
					),
				),
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
				'enum'    => self::PAYMENT_METHODS,
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
