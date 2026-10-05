<?php
/**
 * Extra product fields edited in WooCommerce > Products > General.
 *
 * Preference options are the quick choices shown in the app ("Cu muștar", "Bine făcuți").
 * The customer's picks are joined with their note and saved on the order line under the
 * `Preferinte` meta key, which GrandChef receives as the product note.
 */

namespace MLB\AppApi;

defined( 'ABSPATH' ) || exit;

class Product_Fields {

	public const PREFERENCES_META = '_mlb_preference_options';

	private const MAX_OPTIONS = 12;

	public static function init(): void {
		add_action( 'woocommerce_product_options_general_product_data', array( self::class, 'render' ) );
		add_action( 'woocommerce_admin_process_product_object', array( self::class, 'save' ) );
	}

	/**
	 * @return string[]
	 */
	public static function preference_options( \WC_Product $product ): array {
		return self::parse( (string) $product->get_meta( self::PREFERENCES_META ) );
	}

	public static function render(): void {
		woocommerce_wp_textarea_input(
			array(
				'id'          => self::PREFERENCES_META,
				'label'       => 'Preferințe în aplicație',
				'description' => 'Câte o opțiune pe rând (de exemplu: Cu muștar). Clientul le poate bifa în aplicație.',
				'desc_tip'    => true,
				'rows'        => 4,
			)
		);
	}

	public static function save( \WC_Product $product ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the product form nonce.
		if ( ! isset( $_POST[ self::PREFERENCES_META ] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$options = self::parse( sanitize_textarea_field( wp_unslash( $_POST[ self::PREFERENCES_META ] ) ) );
		$product->update_meta_data( self::PREFERENCES_META, implode( "\n", $options ) );
	}

	/**
	 * @return string[]
	 */
	private static function parse( string $raw ): array {
		$options = array_filter( array_map( 'trim', preg_split( '/[\r\n]+/', $raw ) ?: array() ) );
		$options = array_map( static fn ( string $option ): string => mb_substr( $option, 0, 40 ), $options );
		return array_slice( array_values( array_unique( $options ) ), 0, self::MAX_OPTIONS );
	}
}
