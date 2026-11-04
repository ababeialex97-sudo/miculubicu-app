<?php
/**
 * Delivery on the website priced like in the app: by road distance to the address, with the
 * same bands and free delivery threshold. Without bands, coordinates or a Maps key, or for an
 * address the map can't find, the delivery method keeps its flat cost.
 */

namespace MLB\AppApi;

use MLB\AppApi\Rest\Orders_Controller;

defined( 'ABSPATH' ) || exit;

class Site_Shipping {

	public static function init(): void {
		add_filter( 'woocommerce_package_rates', array( self::class, 'price' ), 20, 2 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_suggestions' ) );
	}

	/**
	 * Google address suggestions under the checkout street field, when Maps is set up.
	 */
	public static function enqueue_suggestions(): void {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || ! Maps::is_configured() ) {
			return;
		}
		$url = plugins_url( 'assets/', MLB_APP_API_FILE );
		wp_enqueue_style( 'mlb-address-suggestions', $url . 'address-suggestions.css', array(), MLB_APP_API_VERSION );
		wp_enqueue_script( 'mlb-address-suggestions', $url . 'address-suggestions.js', array(), MLB_APP_API_VERSION, true );
		wp_localize_script( 'mlb-address-suggestions', 'mlbAddressSuggestions', array( 'restUrl' => esc_url_raw( rest_url( 'mlb/v1/' ) ) ) );
	}

	/**
	 * @param array<string, \WC_Shipping_Rate> $rates
	 * @param array<string, mixed>             $package
	 * @return array<string, \WC_Shipping_Rate>
	 */
	public static function price( $rates, $package ) {
		$method   = Settings::get_delivery_method();
		$location = Site_Orders::default_location( 'delivery' );
		$address  = (array) ( $package['destination'] ?? array() );
		if ( ! $method || ! $location || '' === trim( (string) ( $address['address'] ?? '' ) ) ) {
			return $rates;
		}

		foreach ( (array) $rates as $key => $rate ) {
			if ( ! $rate instanceof \WC_Shipping_Rate || (int) $rate->get_instance_id() !== (int) $method->get_instance_id() ) {
				continue;
			}

			$quote = Delivery_Quote::quote(
				$location,
				array(
					'address_1' => (string) ( $address['address'] ?? '' ),
					'address_2' => (string) ( $address['address_2'] ?? '' ),
					'city'      => (string) ( $address['city'] ?? '' ),
				)
			);
			if ( ! $quote['available'] ) {
				// Too far: only pickup is left to choose.
				unset( $rates[ $key ] );
				continue;
			}
			if ( 'distance' !== $quote['mode'] || null === $quote['fee'] ) {
				continue;
			}

			$cost = Orders_Controller::delivery_cost( $location, self::food_subtotal( $package ), (float) $quote['fee'] );
			$rate->set_cost( wc_format_decimal( $cost ) );
			if ( array_sum( $rate->get_taxes() ) > 0 ) {
				$rate->set_taxes( \WC_Tax::calc_shipping_tax( $cost, \WC_Tax::get_shipping_tax_rates() ) );
			}
			if ( null !== $quote['distance_km'] ) {
				$rate->add_meta_data( 'Distanță', Distance_Pricing::km_label( (float) $quote['distance_km'] ) );
			}
		}
		return $rates;
	}

	/**
	 * Food only, like in the app: packaging doesn't count toward free delivery.
	 *
	 * @param array<string, mixed> $package
	 */
	private static function food_subtotal( array $package ): float {
		$total = 0.0;
		foreach ( (array) ( $package['contents'] ?? array() ) as $item ) {
			if ( empty( $item['mlb_extra'] ) ) {
				$total += (float) ( $item['line_subtotal'] ?? 0 ) + (float) ( $item['line_subtotal_tax'] ?? 0 );
			}
		}
		return $total;
	}
}
