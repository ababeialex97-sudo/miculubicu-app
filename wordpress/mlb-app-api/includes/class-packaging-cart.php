<?php
/**
 * Packaging and free extras on site orders, the same as in the app.
 *
 * The site cart gets the packaging products as extra lines, kept in step with the food on
 * every recalculation: the customer sees them in the cart and at checkout but can't change or
 * remove them. Bread and mustard lines cost 0 lei.
 */

namespace MLB\AppApi;

defined( 'ABSPATH' ) || exit;

class Packaging_Cart {

	/** Cart item data key marking a line added here, with its type (pack, platter, bread, mustard). */
	private const CART_KEY = 'mlb_extra';

	/** Set while this class changes the cart, so its own changes don't trigger it again. */
	private static bool $syncing = false;

	public static function init(): void {
		add_action( 'woocommerce_before_calculate_totals', array( self::class, 'sync' ), 5 );
		add_filter( 'woocommerce_add_to_cart_validation', array( self::class, 'block_direct_add' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_quantity', array( self::class, 'fixed_quantity' ), 10, 3 );
		add_filter( 'woocommerce_cart_item_remove_link', array( self::class, 'no_remove_link' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( self::class, 'mark_line' ), 10, 3 );
		add_action( 'woocommerce_checkout_create_order', array( self::class, 'mark_order' ) );
		add_action( 'woocommerce_cart_totals_before_order_total', array( self::class, 'freebies_row' ) );
		add_action( 'woocommerce_review_order_before_order_total', array( self::class, 'freebies_row' ) );
		// Discounts never apply to packaging, on the site or in the app.
		add_filter( 'woocommerce_coupon_is_valid_for_product', array( self::class, 'no_discount' ), 10, 2 );
	}

	public static function sync( \WC_Cart $cart ): void {
		if ( self::$syncing || ( is_admin() && ! wp_doing_ajax() ) ) {
			return;
		}

		$summary = Packaging::summary( self::food_lines( $cart ), self::fulfillment() );
		$wanted  = $summary ? Packaging::wanted_lines( $summary ) : array();

		self::$syncing = true;
		try {
			foreach ( $cart->get_cart() as $key => $item ) {
				$type = (string) ( $item[ self::CART_KEY ] ?? '' );
				if ( '' === $type ) {
					continue;
				}
				$line = $wanted[ $type ] ?? null;
				if ( ! $line || (int) $item['product_id'] !== $line['product'] ) {
					$cart->remove_cart_item( $key );
					continue;
				}
				if ( (int) $item['quantity'] !== $line['quantity'] ) {
					$cart->set_quantity( $key, $line['quantity'], false );
				}
				unset( $wanted[ $type ] );
			}
			foreach ( $wanted as $type => $line ) {
				$cart->add_to_cart( $line['product'], $line['quantity'], 0, array(), array( self::CART_KEY => $type ) );
			}
		} finally {
			self::$syncing = false;
		}

		foreach ( $cart->get_cart() as $item ) {
			if ( in_array( $item[ self::CART_KEY ] ?? '', array( 'bread', 'mustard' ), true ) ) {
				$item['data']->set_price( 0 );
			}
		}
	}

	/**
	 * The packaging products are hidden from the shop and only ever added by sync().
	 *
	 * @param bool       $valid
	 * @param int|string $product_id
	 */
	public static function block_direct_add( $valid, $product_id ): bool {
		if ( ! self::$syncing && Packaging::is_extra_product( (int) $product_id ) ) {
			wc_add_notice( 'Ambalajul se adaugă automat.', 'error' );
			return false;
		}
		return (bool) $valid;
	}

	/**
	 * Themes pass these as strings or numbers, so the parameters stay untyped.
	 *
	 * @param string|int           $html
	 * @param string               $key
	 * @param array<string, mixed> $item
	 * @return string|int
	 */
	public static function fixed_quantity( $html, $key, $item ) {
		return empty( $item[ self::CART_KEY ] ) ? $html : (string) (int) $item['quantity'];
	}

	/**
	 * @param string $link
	 * @param string $key
	 */
	public static function no_remove_link( $link, $key ): string {
		$item = WC()->cart ? WC()->cart->get_cart_item( $key ) : array();
		return empty( $item[ self::CART_KEY ] ) ? (string) $link : '';
	}

	/**
	 * @param array<string, mixed> $values
	 */
	public static function mark_line( \WC_Order_Item_Product $item, string $key, array $values ): void {
		if ( ! empty( $values[ self::CART_KEY ] ) ) {
			$item->add_meta_data( Packaging::EXTRA_META, (string) $values[ self::CART_KEY ], true );
		}
	}

	public static function mark_order( \WC_Order $order ): void {
		$cart    = WC()->cart;
		$summary = $cart ? Packaging::summary( self::food_lines( $cart ), self::fulfillment() ) : null;
		if ( $summary ) {
			Packaging::record_freebies( $order, $summary );
		}
	}

	public static function freebies_row(): void {
		$cart    = WC()->cart;
		$summary = $cart ? Packaging::summary( self::food_lines( $cart ), self::fulfillment() ) : null;
		$label   = $summary ? Packaging::freebies_label( $summary['bread'], $summary['mustard_ml'] ) : '';
		if ( '' === $label ) {
			return;
		}
		echo '<tr class="mlb-freebies"><th>Din partea casei</th><td data-title="Din partea casei">' . esc_html( $label ) . '</td></tr>';
	}

	/**
	 * @param bool        $valid
	 * @param \WC_Product $product
	 */
	public static function no_discount( $valid, $product ) {
		return $product instanceof \WC_Product && Packaging::is_extra_product( $product->get_id() ) ? false : $valid;
	}

	/**
	 * @return array<int, array{product: \WC_Product, quantity: int}>
	 */
	private static function food_lines( \WC_Cart $cart ): array {
		$lines = array();
		foreach ( $cart->get_cart() as $item ) {
			if ( ! empty( $item[ self::CART_KEY ] ) || ! $item['data'] instanceof \WC_Product ) {
				continue;
			}
			$lines[] = array(
				'product'  => $item['data'],
				'quantity' => (int) $item['quantity'],
			);
		}
		return $lines;
	}

	/**
	 * Pickup when the customer chose the pickup shipping method; delivery otherwise.
	 */
	private static function fulfillment(): string {
		$pickup = Settings::get_pickup_method();
		$chosen = WC()->session ? (array) WC()->session->get( 'chosen_shipping_methods', array() ) : array();
		if ( $pickup && in_array( $pickup->id . ':' . $pickup->get_instance_id(), $chosen, true ) ) {
			return 'pickup';
		}
		return 'delivery';
	}
}
