<?php
/**
 * Packaging charged on app orders, and the bread and mustard the restaurant adds for free.
 *
 * Both go on the order as regular WooCommerce products (hidden from the menu), because the
 * GrandChef plugin only sends products: that way they reach the kitchen and the receipt
 * with their own GrandChef IDs. Free extras are added at 0 lei. Nothing is added until the
 * pack product is chosen in "Setări aplicație".
 */

namespace MLB\AppApi;

defined( 'ABSPATH' ) || exit;

class Packaging {

	public const OPTION     = 'mlb_app_api_packaging';
	public const EXTRA_META = '_mlb_extra';

	public const UNITS_META   = '_mlb_pack_units';
	public const BOX_META     = '_mlb_pack_box';
	public const PLATTER_META = '_mlb_platter';
	public const BREAD_META   = '_mlb_free_bread';
	public const MUSTARD_META = '_mlb_free_mustard';

	/** Free extras on the order, kept for the app whether or not they went on as products. */
	private const ORDER_BREAD_META   = '_mlb_free_bread';
	private const ORDER_MUSTARD_META = '_mlb_free_mustard_ml';

	private const DEFAULTS = array(
		'pack_product'    => 0,
		'platter_product' => 0,
		'bread_product'   => 0,
		'mustard_product' => 0,
		'capacity'        => 10,
		'mustard_ml'      => 20,
		'at_least_one'    => true,
		'pickup'          => true,
	);

	public static function init(): void {
		add_action( 'woocommerce_product_options_general_product_data', array( self::class, 'render_product_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( self::class, 'save_product_fields' ) );
	}

	/**
	 * @return array{pack_product: int, platter_product: int, bread_product: int, mustard_product: int, capacity: int, mustard_ml: int, at_least_one: bool, pickup: bool}
	 */
	public static function settings(): array {
		$saved = get_option( self::OPTION );
		return self::normalize( is_array( $saved ) ? $saved : array() );
	}

	/**
	 * @param array<string, mixed> $raw
	 * @return array{pack_product: int, platter_product: int, bread_product: int, mustard_product: int, capacity: int, mustard_ml: int, at_least_one: bool, pickup: bool}
	 */
	public static function normalize( array $raw ): array {
		$settings = self::DEFAULTS;
		foreach ( array( 'pack_product', 'platter_product', 'bread_product', 'mustard_product' ) as $key ) {
			$settings[ $key ] = absint( $raw[ $key ] ?? 0 );
		}
		$settings['capacity']     = max( 1, min( 100, (int) ( $raw['capacity'] ?? self::DEFAULTS['capacity'] ) ) );
		$settings['mustard_ml']   = max( 0, min( 1000, (int) ( $raw['mustard_ml'] ?? self::DEFAULTS['mustard_ml'] ) ) );
		$settings['at_least_one'] = (bool) ( $raw['at_least_one'] ?? self::DEFAULTS['at_least_one'] );
		$settings['pickup']       = (bool) ( $raw['pickup'] ?? self::DEFAULTS['pickup'] );
		return $settings;
	}

	public static function is_enabled(): bool {
		return null !== self::product( self::settings()['pack_product'] );
	}

	/**
	 * The packaging and free extras products can't be ordered on their own.
	 */
	/**
	 * The pack and platter products: hidden, only ever added by this plugin. Bread and mustard
	 * are left out on purpose, since they can also be menu products the customer pays for.
	 */
	public static function is_extra_product( int $product_id ): bool {
		$settings = self::settings();
		return $product_id > 0 && in_array( $product_id, array( $settings['pack_product'], $settings['platter_product'] ), true );
	}

	/**
	 * Pieces, box, platter flag and free extras for one product, per unit. Variations use their product's values.
	 *
	 * @return array{units: int, box: string, platter: bool, bread: int, mustard: int}
	 */
	public static function product_rules( \WC_Product $product ): array {
		$id = $product->get_parent_id() ?: $product->get_id();
		if ( ! metadata_exists( 'post', $id, self::UNITS_META ) ) {
			$parent = $product->get_parent_id() ? wc_get_product( $id ) : $product;
			return Packaging_Rules::guess( $parent ? $parent->get_name() : $product->get_name() );
		}
		return array(
			'units'   => max( 0, (int) get_post_meta( $id, self::UNITS_META, true ) ),
			// "Ceafă" and "ceafa" are the same box.
			'box'     => Packaging_Rules::box_key( remove_accents( (string) get_post_meta( $id, self::BOX_META, true ) ) ),
			'platter' => 'yes' === get_post_meta( $id, self::PLATTER_META, true ),
			'bread'   => max( 0, (int) get_post_meta( $id, self::BREAD_META, true ) ),
			'mustard' => max( 0, (int) get_post_meta( $id, self::MUSTARD_META, true ) ),
		);
	}

	/**
	 * What the order gets, or null when packaging is off (or not charged for this fulfillment).
	 *
	 * @param array<int, array{product: \WC_Product, quantity: int}> $lines
	 * @return array{packs: int, platters: int, bread: int, mustard: int, pack_price: float, platter_price: float, total: float, mustard_ml: int}|null
	 */
	public static function summary( array $lines, string $fulfillment ): ?array {
		$settings = self::settings();
		$pack     = self::product( $settings['pack_product'] );
		if ( ! $pack || ( 'pickup' === $fulfillment && ! $settings['pickup'] ) ) {
			return null;
		}

		$rule_lines = array();
		foreach ( $lines as $line ) {
			$rule_lines[] = array( 'quantity' => $line['quantity'] ) + self::product_rules( $line['product'] );
		}
		$count = Packaging_Rules::count( $rule_lines, $settings['capacity'], $settings['at_least_one'] );

		$platter       = self::product( $settings['platter_product'] );
		$pack_price    = (float) wc_get_price_to_display( $pack );
		$platter_price = $platter ? (float) wc_get_price_to_display( $platter ) : 0.0;
		if ( ! $platter ) {
			$count['platters'] = 0;
		}

		return $count + array(
			'pack_price'    => $pack_price,
			'platter_price' => $platter_price,
			'total'         => $count['packs'] * $pack_price + $count['platters'] * $platter_price,
			'mustard_ml'    => $count['mustard'] * $settings['mustard_ml'],
		);
	}

	/**
	 * Adds the packaging and free extras lines. Free extras without a product of their own
	 * go to the note GrandChef sends to the kitchen.
	 *
	 * @param array{packs: int, platters: int, bread: int, mustard: int, mustard_ml: int} $summary
	 */
	public static function add_to_order( \WC_Order $order, array $summary ): void {
		foreach ( self::wanted_lines( $summary ) as $type => $line ) {
			self::add_line( $order, $line['product'], $line['quantity'], $type, $line['free'] );
		}
		self::record_freebies( $order, $summary );
	}

	/**
	 * The packaging and free extras lines an order should have, by type.
	 *
	 * @param array{packs: int, platters: int, bread: int, mustard: int} $summary
	 * @return array<string, array{product: int, quantity: int, free: bool}>
	 */
	public static function wanted_lines( array $summary ): array {
		$settings = self::settings();
		$lines    = array(
			'pack'    => array( $settings['pack_product'], $summary['packs'], false ),
			'platter' => array( $settings['platter_product'], $summary['platters'], false ),
			'bread'   => array( $settings['bread_product'], $summary['bread'], true ),
			'mustard' => array( $settings['mustard_product'], $summary['mustard'], true ),
		);

		$wanted = array();
		foreach ( $lines as $type => list( $product_id, $quantity, $free ) ) {
			if ( $quantity > 0 && self::product( $product_id ) ) {
				$wanted[ $type ] = array(
					'product'  => $product_id,
					'quantity' => $quantity,
					'free'     => $free,
				);
			}
		}
		return $wanted;
	}

	/**
	 * Keeps the free extras on the order for the app, and writes the ones without a product
	 * of their own in the note GrandChef sends to the kitchen.
	 *
	 * @param array{bread: int, mustard: int, mustard_ml: int} $summary
	 */
	public static function record_freebies( \WC_Order $order, array $summary ): void {
		$settings = self::settings();
		$order->update_meta_data( self::ORDER_BREAD_META, $summary['bread'] );
		$order->update_meta_data( self::ORDER_MUSTARD_META, $summary['mustard_ml'] );

		$for_kitchen = array();
		if ( $summary['bread'] > 0 && ! self::product( $settings['bread_product'] ) ) {
			$for_kitchen[] = sprintf( '%d felii de pâine', $summary['bread'] );
		}
		if ( $summary['mustard'] > 0 && ! self::product( $settings['mustard_product'] ) ) {
			$for_kitchen[] = sprintf( '%d ml muștar', $summary['mustard_ml'] );
		}
		if ( $for_kitchen ) {
			$order->set_customer_note( trim( $order->get_customer_note() . "\nGratuit: " . implode( ', ', $for_kitchen ) ) );
		}
	}

	/**
	 * "16 felii de pâine și 320 ml de muștar", or '' when there is nothing free.
	 */
	public static function freebies_label( int $bread, int $mustard_ml ): string {
		$parts = array();
		if ( $bread > 0 ) {
			$parts[] = sprintf( '%d %s de pâine', $bread, 1 === $bread ? 'felie' : ( $bread < 20 ? 'felii' : 'de felii' ) );
		}
		if ( $mustard_ml > 0 ) {
			$parts[] = sprintf( '%d ml de muștar', $mustard_ml );
		}
		return implode( ' și ', $parts );
	}

	/**
	 * What the app shows for an order's packaging and free extras.
	 *
	 * @return array{packs: int, platters: int, packaging_total: string, bread: int, mustard_ml: int}
	 */
	public static function order_extras( \WC_Order $order ): array {
		$extras = array(
			'packs'           => 0,
			'platters'        => 0,
			'packaging_total' => 0.0,
			'bread'           => (int) $order->get_meta( self::ORDER_BREAD_META ),
			'mustard_ml'      => (int) $order->get_meta( self::ORDER_MUSTARD_META ),
		);
		foreach ( $order->get_items() as $item ) {
			$type = (string) $item->get_meta( self::EXTRA_META );
			if ( 'pack' === $type || 'platter' === $type ) {
				$extras[ 'pack' === $type ? 'packs' : 'platters' ] += $item->get_quantity();
				$extras['packaging_total']                          += (float) $item->get_total() + (float) $item->get_total_tax();
			}
		}
		$extras['packaging_total'] = Formatter::money( $extras['packaging_total'] );
		return $extras;
	}

	/**
	 * Packaging settings for the app, or null when packaging is off.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function config(): ?array {
		$settings = self::settings();
		$pack     = self::product( $settings['pack_product'] );
		if ( ! $pack ) {
			return null;
		}
		$platter = self::product( $settings['platter_product'] );

		return array(
			'capacity'      => $settings['capacity'],
			'pack_price'    => Formatter::money( wc_get_price_to_display( $pack ) ),
			'platter_price' => $platter ? Formatter::money( wc_get_price_to_display( $platter ) ) : null,
			'at_least_one'  => $settings['at_least_one'],
			'pickup'        => $settings['pickup'],
			'mustard_ml'    => $settings['mustard_ml'],
		);
	}

	public static function render_product_fields(): void {
		global $post;
		// Not set up yet: show what the name suggests, saved as soon as the product is updated.
		$guess = $post && ! metadata_exists( 'post', $post->ID, self::UNITS_META ) ? Packaging_Rules::guess( get_the_title( $post ) ) : null;

		echo '<div class="options_group">';
		woocommerce_wp_text_input(
			array(
				'id'                => self::UNITS_META,
				'value'             => $guess ? (string) $guess['units'] : null,
				'label'             => 'Bucăți la ambalaj',
				'description'       => 'Câte bucăți ocupă în cutie: 1 pentru un mic sau o ceafă, 5 pentru o porție de 5 mici, 0 dacă nu intră în cutie (cartofi, sosuri).',
				'desc_tip'          => true,
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => self::BOX_META,
				'value'       => $guess ? $guess['box'] : null,
				'label'       => 'Cutie',
				'placeholder' => 'mici',
				'description' => 'În ce cutie intră: „mici”, „carnaciori” sau „ceafa”. Produsele cu aceeași cutie se pun împreună (până la capacitatea din Setări aplicație), cele cu cutii diferite separat.',
				'desc_tip'    => true,
			)
		);
		woocommerce_wp_checkbox(
			array(
				'id'          => self::PLATTER_META,
				'value'       => $guess ? ( $guess['platter'] ? 'yes' : 'no' ) : null,
				'label'       => 'Platou',
				'description' => 'Primește ambalajul de platou.',
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'                => self::BREAD_META,
				'value'             => $guess ? (string) $guess['bread'] : null,
				'label'             => 'Pâine gratis (felii)',
				'description'       => 'Felii de pâine din partea casei, la fiecare bucată comandată din acest produs.',
				'desc_tip'          => true,
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'                => self::MUSTARD_META,
				'value'             => $guess ? (string) $guess['mustard'] : null,
				'label'             => 'Muștar gratis (porții)',
				'description'       => 'Porții de muștar din partea casei, la fiecare bucată comandată. Mărimea porției (ml) se setează în Setări aplicație.',
				'desc_tip'          => true,
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
			)
		);
		echo '</div>';
	}

	public static function save_product_fields( \WC_Product $product ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the product form nonce.
		if ( ! isset( $_POST[ self::UNITS_META ] ) ) {
			return;
		}
		foreach ( array( self::UNITS_META, self::BREAD_META, self::MUSTARD_META ) as $key ) {
			$product->update_meta_data( $key, absint( $_POST[ $key ] ?? 0 ) );
		}
		$product->update_meta_data( self::BOX_META, sanitize_text_field( wp_unslash( $_POST[ self::BOX_META ] ?? '' ) ) );
		$product->update_meta_data( self::PLATTER_META, empty( $_POST[ self::PLATTER_META ] ) ? 'no' : 'yes' );
		// phpcs:enable
	}

	/**
	 * @return bool Whether a line was added.
	 */
	private static function add_line( \WC_Order $order, int $product_id, int $quantity, string $type, bool $free ): bool {
		$product = self::product( $product_id );
		if ( ! $product || $quantity <= 0 ) {
			return false;
		}

		$args    = $free ? array(
			'subtotal' => 0,
			'total'    => 0,
		) : array();
		$item_id = $order->add_product( $product, $quantity, $args );
		if ( ! $item_id ) {
			return false;
		}
		$item = $order->get_item( $item_id );
		$item->add_meta_data( self::EXTRA_META, $type, true );
		$item->save();
		return true;
	}

	private static function product( int $id ): ?\WC_Product {
		$product = $id > 0 ? wc_get_product( $id ) : null;
		return $product instanceof \WC_Product && ! $product instanceof \WC_Product_Variable ? $product : null;
	}
}
