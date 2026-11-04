<?php
/**
 * WooCommerce glue: live cart count/total in the header, and the "Livrăm la tine?" address prefill.
 */

defined( 'ABSPATH' ) || exit;

function mlb_theme_has_wc(): bool {
	return function_exists( 'WC' ) && null !== WC()->cart;
}

function mlb_theme_cart_count_html(): string {
	$count = mlb_theme_has_wc() ? (int) WC()->cart->get_cart_contents_count() : 0;
	return sprintf( '<span class="mlb-cart-count" data-count="%1$d">%1$d</span>', $count );
}

function mlb_theme_cart_total_html(): string {
	$total = mlb_theme_has_wc() ? WC()->cart->get_cart_subtotal() : '';
	return '<span class="mlb-cart-total">' . wp_kses_post( $total ) . '</span>';
}

/**
 * Every cart button in the theme carries these spans; WooCommerce swaps them after an AJAX add-to-cart.
 */
add_filter(
	'woocommerce_add_to_cart_fragments',
	static function ( array $fragments ): array {
		$fragments['span.mlb-cart-count'] = mlb_theme_cart_count_html();
		$fragments['span.mlb-cart-total'] = mlb_theme_cart_total_html();
		return $fragments;
	}
);

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}
		// Needed on every page because the cart button lives in the header.
		wp_enqueue_script( 'wc-cart-fragments' );
		wp_enqueue_script( 'wc-add-to-cart' );
	}
);

const MLB_THEME_ADDRESS_PARAM = 'mlb_adresa';

/**
 * The hero's address box sends ?mlb_adresa=...; keep it in the WooCommerce session for checkout.
 */
add_action(
	'template_redirect',
	static function (): void {
		if ( empty( $_GET[ MLB_THEME_ADDRESS_PARAM ] ) || ! function_exists( 'WC' ) || null === WC()->session ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only prefill.
			return;
		}
		$address = sanitize_text_field( wp_unslash( $_GET[ MLB_THEME_ADDRESS_PARAM ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $address ) {
			return;
		}
		if ( ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
		WC()->session->set( 'mlb_address', mb_substr( $address, 0, 200 ) );
	}
);

add_filter(
	'woocommerce_checkout_get_value',
	static function ( $value, string $input ) {
		if ( null !== $value && '' !== $value ) {
			return $value;
		}
		if ( ! in_array( $input, array( 'billing_address_1', 'shipping_address_1' ), true ) || ! function_exists( 'WC' ) || null === WC()->session ) {
			return $value;
		}
		$address = WC()->session->get( 'mlb_address' );
		return is_string( $address ) && '' !== $address ? $address : $value;
	},
	10,
	2
);

/**
 * Product grids on the theme's own shop pages: 3 columns, 12 per page.
 */
add_filter( 'loop_shop_columns', static fn() => 3 );
add_filter( 'loop_shop_per_page', static fn() => 12 );
