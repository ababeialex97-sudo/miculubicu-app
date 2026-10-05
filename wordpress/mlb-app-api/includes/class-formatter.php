<?php
/**
 * Shapes WooCommerce objects into the JSON the app consumes.
 */

namespace MLB\AppApi;

defined( 'ABSPATH' ) || exit;

class Formatter {

	/**
	 * Monetary values are returned as decimal strings to avoid float rounding in the app.
	 */
	public static function money( $amount ): string {
		return wc_format_decimal( (float) $amount, wc_get_price_decimals() );
	}

	public static function plain_text( string $html ): string {
		return trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * @return array<string, string>|null
	 */
	public static function image( int $attachment_id ): ?array {
		if ( $attachment_id <= 0 ) {
			return null;
		}

		$full  = wp_get_attachment_image_url( $attachment_id, 'full' );
		$thumb = wp_get_attachment_image_url( $attachment_id, 'woocommerce_thumbnail' );
		if ( ! $full ) {
			return null;
		}

		return array(
			'url'       => $full,
			'thumbnail' => $thumb ? $thumb : $full,
			'alt'       => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function customer( \WC_Customer $customer ): array {
		return array(
			'id'         => $customer->get_id(),
			'email'      => $customer->get_email(),
			'first_name' => $customer->get_first_name(),
			'last_name'  => $customer->get_last_name(),
			'phone'      => $customer->get_billing_phone(),
			'address'    => array(
				'address_1' => $customer->get_shipping_address_1(),
				'address_2' => $customer->get_shipping_address_2(),
				'city'      => $customer->get_shipping_city(),
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function order( \WC_Order $order ): array {
		$items = array();
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
			$product = $item->get_product();
			$items[] = array(
				'product_id'   => $item->get_product_id(),
				'variation_id' => $item->get_variation_id(),
				'name'         => $item->get_name(),
				'quantity'     => $item->get_quantity(),
				// Before coupon discounts, which are shown once on the order (discount_total).
				'total'        => self::money( (float) $item->get_subtotal() + (float) $item->get_subtotal_tax() ),
				'preferences'  => (string) $item->get_meta( 'Preferinte' ),
				'image'        => $product ? self::image( (int) $product->get_image_id() ) : null,
			);
		}

		$status  = Statuses::for_order( $order );
		$created = $order->get_date_created();

		return array(
			'id'              => $order->get_id(),
			'number'          => $order->get_order_number(),
			'created_at'      => $created ? $created->format( DATE_ATOM ) : null,
			'status'          => $status,
			'status_label'    => Statuses::labels()[ $status ],
			'status_history'  => Statuses::history( $order ),
			'fulfillment'     => (string) $order->get_meta( '_mlb_fulfillment' ),
			'location_id'     => (string) $order->get_meta( '_mlb_location_id' ),
			'items'           => $items,
			'subtotal'        => self::money( $order->get_subtotal() ),
			'shipping_total'  => self::money( (float) $order->get_shipping_total() + (float) $order->get_shipping_tax() ),
			'discount_total'  => self::money( (float) $order->get_discount_total() + (float) $order->get_discount_tax() ),
			'coupon_codes'    => array_values( $order->get_coupon_codes() ),
			'total'           => self::money( $order->get_total() ),
			'currency'        => $order->get_currency(),
			'payment_method'  => $order->get_payment_method(),
			'address'         => array(
				'address_1' => $order->get_shipping_address_1(),
				'address_2' => $order->get_shipping_address_2(),
				'city'      => $order->get_shipping_city(),
			),
			'phone'           => $order->get_billing_phone(),
			'note'            => $order->meta_exists( '_mlb_note' ) ? (string) $order->get_meta( '_mlb_note' ) : $order->get_customer_note(),
		);
	}
}
