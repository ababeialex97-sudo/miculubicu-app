<?php
/**
 * Public menu: categories and the products in them, ready to render in the app.
 *
 * The menu is small (~20 products), so it is returned in one response and cached
 * until a product or category changes.
 */

namespace MLB\AppApi\Rest;

use MLB\AppApi\Formatter;

defined( 'ABSPATH' ) || exit;

class Menu_Controller {

	public const CACHE_KEY = 'mlb_app_api_menu';

	public function register_routes( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/menu',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_menu' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function flush_cache(): void {
		delete_transient( self::CACHE_KEY );
	}

	public function get_menu() {
		$menu = get_transient( self::CACHE_KEY );
		if ( ! is_array( $menu ) ) {
			$menu = $this->build_menu();
			set_transient( self::CACHE_KEY, $menu, HOUR_IN_SECONDS );
		}

		return rest_ensure_response( $menu );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function build_menu(): array {
		$products = wc_get_products(
			array(
				'status'     => 'publish',
				'visibility' => 'catalog',
				'limit'      => -1,
				'orderby'    => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
			)
		);

		$formatted    = array();
		$category_ids = array();
		foreach ( $products as $product ) {
			if ( ! in_array( $product->get_type(), array( 'simple', 'variable' ), true ) ) {
				continue;
			}
			$item = $this->format_product( $product );
			if ( null === $item ) {
				continue;
			}
			$formatted[]  = $item;
			$category_ids = array_merge( $category_ids, $item['category_ids'] );
		}

		return array(
			'currency'   => get_woocommerce_currency(),
			'categories' => $this->format_categories( array_unique( $category_ids ), $formatted ),
			'products'   => $formatted,
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function format_product( \WC_Product $product ): ?array {
		$variations = array();
		if ( $product instanceof \WC_Product_Variable ) {
			foreach ( $product->get_children() as $child_id ) {
				$variation = wc_get_product( $child_id );
				if ( ! $variation instanceof \WC_Product_Variation || ! $variation->variation_is_visible() ) {
					continue;
				}
				$variations[] = array(
					'id'            => $variation->get_id(),
					'name'          => Formatter::plain_text( wc_get_formatted_variation( $variation, true, false, false ) ),
					'attributes'    => $variation->get_variation_attributes( false ),
					'price'         => Formatter::money( wc_get_price_to_display( $variation ) ),
					'regular_price' => Formatter::money( wc_get_price_to_display( $variation, array( 'price' => $variation->get_regular_price() ) ) ),
					'in_stock'      => $variation->is_in_stock() && $variation->is_purchasable(),
				);
			}
			if ( empty( $variations ) ) {
				return null;
			}
		}

		$gallery = array();
		foreach ( $product->get_gallery_image_ids() as $image_id ) {
			$image = Formatter::image( (int) $image_id );
			if ( $image ) {
				$gallery[] = $image;
			}
		}

		$weight = $product->get_weight();

		return array(
			'id'                => $product->get_id(),
			'name'              => $product->get_name(),
			'slug'              => $product->get_slug(),
			'type'              => $product->get_type(),
			'short_description' => Formatter::plain_text( $product->get_short_description() ),
			'description'       => Formatter::plain_text( $product->get_description() ),
			'price'             => Formatter::money( wc_get_price_to_display( $product ) ),
			'regular_price'     => Formatter::money( wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price() ) ) ),
			'on_sale'           => $product->is_on_sale(),
			'in_stock'          => $product->is_in_stock() && $product->is_purchasable(),
			'weight'            => '' !== $weight ? array(
				'value' => $weight,
				'unit'  => get_option( 'woocommerce_weight_unit' ),
			) : null,
			'image'             => Formatter::image( (int) $product->get_image_id() ),
			'gallery'           => $gallery,
			'category_ids'      => array_map( 'intval', $product->get_category_ids() ),
			'variations'        => $variations,
		);
	}

	/**
	 * @param int[]                             $ids
	 * @param array<int, array<string, mixed>> $products
	 * @return array<int, array<string, mixed>>
	 */
	private function format_categories( array $ids, array $products ): array {
		if ( empty( $ids ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'include'    => $ids,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) ) {
			return array();
		}

		// Same order as the drag-and-drop order in WooCommerce > Products > Categories.
		usort(
			$terms,
			static function ( \WP_Term $a, \WP_Term $b ): int {
				$order = (int) get_term_meta( $a->term_id, 'order', true ) <=> (int) get_term_meta( $b->term_id, 'order', true );
				return 0 !== $order ? $order : strcmp( $a->name, $b->name );
			}
		);

		$categories = array();
		foreach ( $terms as $term ) {
			$product_ids = array();
			foreach ( $products as $product ) {
				if ( in_array( $term->term_id, $product['category_ids'], true ) ) {
					$product_ids[] = $product['id'];
				}
			}

			$categories[] = array(
				'id'          => $term->term_id,
				'name'        => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
				'slug'        => $term->slug,
				'description' => Formatter::plain_text( $term->description ),
				'image'       => Formatter::image( (int) get_term_meta( $term->term_id, 'thumbnail_id', true ) ),
				'product_ids' => $product_ids,
			);
		}

		return $categories;
	}
}
