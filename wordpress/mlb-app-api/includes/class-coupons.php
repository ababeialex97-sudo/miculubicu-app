<?php
/**
 * Promotions on top of WooCommerce coupons, so every discount reaches GrandChef
 * through its "Spread discount" option.
 *
 * Two extra flags on the coupon screen:
 * - "Afișează în aplicație": listed on the app's Fidelitate screen;
 * - "Doar la prima comandă": valid only for a customer with no earlier order.
 * Loyalty rewards are coupons tied to one customer (`_mlb_customer_id`).
 */

namespace MLB\AppApi;

defined( 'ABSPATH' ) || exit;

class Coupons {

	public const SHOW_IN_APP_META = '_mlb_show_in_app';
	public const FIRST_ORDER_META = '_mlb_first_order_only';
	public const CUSTOMER_META    = '_mlb_customer_id';

	/** Orders in these statuses count as "earlier orders" for first-order coupons. */
	private const PLACED_STATUSES = array( 'processing', 'completed', 'on-hold' );

	public static function init(): void {
		add_action( 'woocommerce_coupon_options', array( self::class, 'render_fields' ), 10, 2 );
		add_action( 'woocommerce_coupon_options_save', array( self::class, 'save_fields' ), 10, 2 );
		add_filter( 'woocommerce_coupon_is_valid', array( self::class, 'validate' ), 10, 3 );
	}

	public static function render_fields( int $coupon_id, \WC_Coupon $coupon ): void {
		woocommerce_wp_checkbox(
			array(
				'id'          => self::SHOW_IN_APP_META,
				'label'       => 'Afișează în aplicație',
				'description' => 'Apare în ecranul Fidelitate, cu descrierea cuponului.',
				'value'       => wc_bool_to_string( 'yes' === $coupon->get_meta( self::SHOW_IN_APP_META ) ),
			)
		);
		woocommerce_wp_checkbox(
			array(
				'id'          => self::FIRST_ORDER_META,
				'label'       => 'Doar la prima comandă',
				'description' => 'Valabil doar pentru clienții fără nicio comandă anterioară.',
				'value'       => wc_bool_to_string( 'yes' === $coupon->get_meta( self::FIRST_ORDER_META ) ),
			)
		);
	}

	public static function save_fields( int $coupon_id, \WC_Coupon $coupon ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the coupon form nonce.
		$coupon->update_meta_data( self::SHOW_IN_APP_META, isset( $_POST[ self::SHOW_IN_APP_META ] ) ? 'yes' : 'no' );
		$coupon->update_meta_data( self::FIRST_ORDER_META, isset( $_POST[ self::FIRST_ORDER_META ] ) ? 'yes' : 'no' );
		// phpcs:enable
		$coupon->save();
	}

	/**
	 * Runs for every coupon check, on the website too. Throwing makes WooCommerce show the message.
	 *
	 * @param bool                                 $valid
	 * @param \WC_Discounts|null                   $discounts
	 * @throws \Exception When the coupon doesn't apply to this customer.
	 */
	public static function validate( $valid, \WC_Coupon $coupon, $discounts = null ) {
		if ( ! $valid ) {
			return $valid;
		}

		$owner     = (int) $coupon->get_meta( self::CUSTOMER_META );
		$first     = 'yes' === $coupon->get_meta( self::FIRST_ORDER_META );
		if ( $owner <= 0 && ! $first ) {
			return $valid;
		}

		$object      = $discounts instanceof \WC_Discounts ? $discounts->get_object() : null;
		$customer_id = $object instanceof \WC_Order ? $object->get_customer_id() : get_current_user_id();
		$order_id    = $object instanceof \WC_Order ? $object->get_id() : 0;

		if ( $owner > 0 && $owner !== $customer_id ) {
			throw new \Exception( 'Codul nu este valabil pentru contul tău.' );
		}

		if ( $first ) {
			if ( $customer_id <= 0 ) {
				throw new \Exception( 'Intră în cont ca să folosești acest cod.' );
			}
			if ( self::has_earlier_order( $customer_id, $order_id ) ) {
				throw new \Exception( 'Codul este valabil doar la prima comandă.' );
			}
		}

		return $valid;
	}

	/**
	 * Coupons a customer can use from the app: public "show in app" ones and their own rewards.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function for_customer( int $customer_id ): array {
		$args = array(
			'post_type'      => 'shop_coupon',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		);

		$public = get_posts(
			$args + array(
				'meta_key'   => self::SHOW_IN_APP_META, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value' => 'yes', // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		$own    = $customer_id > 0 ? get_posts(
			$args + array(
				'meta_key'   => self::CUSTOMER_META, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value' => (string) $customer_id, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		) : array();

		$coupons = array();
		foreach ( array_unique( array_merge( $own, $public ) ) as $coupon_id ) {
			$coupon = new \WC_Coupon( (int) $coupon_id );
			if ( ! self::is_usable( $coupon, $customer_id ) ) {
				continue;
			}
			$coupons[] = self::format( $coupon );
		}
		return $coupons;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function format( \WC_Coupon $coupon ): array {
		$type    = in_array( $coupon->get_discount_type(), array( 'percent' ), true ) ? 'percent' : 'fixed';
		$expires = $coupon->get_date_expires();

		return array(
			'code'             => $coupon->get_code(),
			'description'      => $coupon->get_description(),
			'discount_type'    => $type,
			'amount'           => Formatter::money( $coupon->get_amount() ),
			'amount_label'     => Loyalty_Rules::amount_label( $type, (float) $coupon->get_amount() ),
			'minimum_amount'   => Formatter::money( $coupon->get_minimum_amount() ),
			'first_order_only' => 'yes' === $coupon->get_meta( self::FIRST_ORDER_META ),
			'loyalty_reward'   => (int) $coupon->get_meta( self::CUSTOMER_META ) > 0,
			'expires_at'       => $expires ? $expires->format( DATE_ATOM ) : null,
		);
	}

	/**
	 * Checks coupon codes against the items, without creating an order.
	 * Returns the discount per code, or the first error in the customer's words.
	 *
	 * @param array<int, array{product: \WC_Product, quantity: int}> $lines
	 * @param string[]                                              $codes
	 * @return array{discount: float, coupons: array<int, array<string, mixed>>}|\WP_Error
	 */
	public static function preview( array $lines, array $codes, \WC_Customer $customer ) {
		$order = new \WC_Order();
		$order->set_customer_id( $customer->get_id() );
		$order->set_billing_email( $customer->get_email() );

		foreach ( $lines as $line ) {
			$price = (float) wc_get_price_excluding_tax( $line['product'], array( 'qty' => $line['quantity'] ) );
			$item  = new \WC_Order_Item_Product();
			$item->set_product( $line['product'] );
			$item->set_quantity( $line['quantity'] );
			$item->set_subtotal( $price );
			$item->set_total( $price );
			$order->add_item( $item );
		}

		$discounts = new \WC_Discounts( $order );
		$applied   = array();
		foreach ( self::normalize_codes( $codes ) as $code ) {
			$coupon = new \WC_Coupon( $code );
			if ( ! $coupon->get_id() ) {
				return new \WP_Error( 'mlb_coupon_invalid', sprintf( 'Codul „%s” nu există.', $code ), array( 'status' => 400 ) );
			}
			$result = $discounts->apply_coupon( $coupon );
			if ( is_wp_error( $result ) ) {
				return new \WP_Error( 'mlb_coupon_invalid', wp_strip_all_tags( $result->get_error_message() ), array( 'status' => 400 ) );
			}
			$applied[] = $coupon;
		}

		$by_coupon = $discounts->get_discounts_by_coupon();
		$coupons   = array();
		foreach ( $applied as $coupon ) {
			$coupons[] = array_merge(
				self::format( $coupon ),
				array( 'discount' => Formatter::money( $by_coupon[ $coupon->get_code() ] ?? 0 ) )
			);
		}

		return array(
			'discount' => (float) array_sum( $by_coupon ),
			'coupons'  => $coupons,
		);
	}

	/**
	 * Lower-cased, trimmed, unique codes, at most two (a promo code plus a loyalty reward).
	 *
	 * @param mixed $codes
	 * @return string[]
	 */
	public static function normalize_codes( $codes ): array {
		$codes = array_filter( array_map( static fn ( $code ): string => wc_format_coupon_code( (string) $code ), (array) $codes ) );
		return array_slice( array_values( array_unique( $codes ) ), 0, 2 );
	}

	private static function is_usable( \WC_Coupon $coupon, int $customer_id ): bool {
		$expires = $coupon->get_date_expires();
		if ( $expires && $expires->getTimestamp() < time() ) {
			return false;
		}
		if ( $coupon->get_usage_limit() > 0 && $coupon->get_usage_count() >= $coupon->get_usage_limit() ) {
			return false;
		}
		if ( 'yes' === $coupon->get_meta( self::FIRST_ORDER_META ) && $customer_id > 0 && self::has_earlier_order( $customer_id, 0 ) ) {
			return false;
		}
		return true;
	}

	private static function has_earlier_order( int $customer_id, int $exclude_order_id ): bool {
		$ids = wc_get_orders(
			array(
				'customer_id' => $customer_id,
				'status'      => self::PLACED_STATUSES,
				'limit'       => 1,
				'return'      => 'ids',
				'exclude'     => $exclude_order_id > 0 ? array( $exclude_order_id ) : array(),
			)
		);
		return ! empty( $ids );
	}
}
