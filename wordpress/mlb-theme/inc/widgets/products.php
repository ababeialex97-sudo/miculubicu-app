<?php
/**
 * Products straight from WooCommerce (same menu as the app), with category tabs and AJAX "Adaugă".
 * Also used for meal deals: pick the "Pachete" category and the large card style.
 */

namespace MLB\Theme\Widgets;

use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

class Products extends Base {

	public function get_name(): string {
		return 'mlb-products';
	}

	public function get_title(): string {
		return __( 'Produse din meniu', 'mlb-theme' );
	}

	public function get_icon(): string {
		return 'eicon-products';
	}

	protected function is_dynamic_content(): bool {
		return true; // Prices, stock and the cart change without the page being edited.
	}

	public function get_script_depends(): array {
		return array( 'mlb-theme', 'wc-add-to-cart' );
	}

	protected function register_controls(): void {
		$this->content_section( 'heading', __( 'Titlu', 'mlb-theme' ) );
		$this->text_control( 'eyebrow', __( 'Supratitlu', 'mlb-theme' ), 'MENIU ȘI PREȚURI' );
		$this->textarea_control( 'title', __( 'Titlu', 'mlb-theme' ), 'Ce punem pe jar azi' );
		$this->end_controls_section();

		$this->content_section( 'query', __( 'Produse', 'mlb-theme' ) );
		$this->add_control(
			'notice',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Produsele, pozele și prețurile vin din WooCommerce › Produse (aceleași ca în aplicație).', 'mlb-theme' ),
				'content_classes' => 'elementor-descriptor',
			)
		);
		$this->add_control(
			'categories',
			array(
				'label'       => __( 'Categorii (gol = toate)', 'mlb-theme' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'options'     => $this->category_options(),
				'label_block' => true,
			)
		);
		$this->add_control(
			'exclude_categories',
			array(
				'label'       => __( 'Exclude categoriile', 'mlb-theme' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'options'     => $this->category_options(),
				'label_block' => true,
			)
		);
		$this->add_control(
			'limit',
			array(
				'label'   => __( 'Câte produse', 'mlb-theme' ),
				'type'    => Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 60,
				'default' => 8,
			)
		);
		$this->add_control(
			'orderby',
			array(
				'label'   => __( 'Ordine', 'mlb-theme' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'menu_order' => __( 'Ordinea din WooCommerce', 'mlb-theme' ),
					'popularity' => __( 'Cele mai vândute', 'mlb-theme' ),
					'price'      => __( 'Preț crescător', 'mlb-theme' ),
					'date'       => __( 'Cele mai noi', 'mlb-theme' ),
				),
				'default' => 'menu_order',
			)
		);
		$this->switch_control( 'show_tabs', __( 'Taburi pe categorii', 'mlb-theme' ) );
		$this->text_control( 'tabs_all', __( 'Text tab „toate”', 'mlb-theme' ), 'Toate', array( 'condition' => array( 'show_tabs' => 'yes' ) ) );
		$this->end_controls_section();

		$this->content_section( 'card', __( 'Card produs', 'mlb-theme' ) );
		$this->add_control(
			'card_style',
			array(
				'label'   => __( 'Stil', 'mlb-theme' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'card'   => __( 'Card (meniu)', 'mlb-theme' ),
					'large'  => __( 'Card mare (pachete)', 'mlb-theme' ),
					'list'   => __( 'Listă compactă', 'mlb-theme' ),
				),
				'default' => 'card',
			)
		);
		$this->columns_control( '.mlb-products__grid', 4, 2, 1 );
		$this->switch_control( 'show_description', __( 'Descriere scurtă', 'mlb-theme' ) );
		$this->switch_control( 'show_weight', __( 'Gramaj', 'mlb-theme' ) );
		$this->text_control( 'button_label', __( 'Text buton', 'mlb-theme' ), 'Adaugă' );
		$this->text_control( 'choose_label', __( 'Text buton (produse cu variante)', 'mlb-theme' ), 'Alege' );
		$this->text_control( 'badge_tag', __( 'Etichetă WooCommerce pentru insignă (slug)', 'mlb-theme' ), 'cel-mai-comandat' );
		$this->text_control( 'badge_text', __( 'Text insignă', 'mlb-theme' ), 'Cel mai comandat' );
		$this->text_control( 'empty_text', __( 'Text când nu sunt produse', 'mlb-theme' ), '' );
		$this->end_controls_section();

		$this->color_controls(
			array(
				'bg'      => __( 'Fundal secțiune', 'mlb-theme' ),
				'surface' => __( 'Fundal card', 'mlb-theme' ),
				'text'    => __( 'Titluri', 'mlb-theme' ),
				'muted'   => __( 'Text secundar', 'mlb-theme' ),
				'accent'  => __( 'Preț, insignă, supratitlu', 'mlb-theme' ),
				'primary' => __( 'Buton', 'mlb-theme' ),
				'line'    => __( 'Chenare', 'mlb-theme' ),
			)
		);
		$this->typography_controls(
			array(
				'title' => array( __( 'Titlu secțiune', 'mlb-theme' ), '.mlb-products__title' ),
				'name'  => array( __( 'Nume produs', 'mlb-theme' ), '.mlb-product__name' ),
				'price' => array( __( 'Preț', 'mlb-theme' ), '.mlb-product__price' ),
			)
		);
	}

	/**
	 * @return array<string, string>
	 */
	private function category_options(): array {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);
		$options = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$options[ $term->slug ] = $term->name;
		}
		return $options;
	}

	protected function render(): void {
		$s = $this->get_settings_for_display();

		if ( ! function_exists( 'wc_get_products' ) ) {
			$this->editor_notice( __( 'WooCommerce nu este activ.', 'mlb-theme' ) );
			return;
		}

		$args = array(
			'status'     => 'publish',
			'limit'      => max( 1, (int) $s['limit'] ),
			'visibility' => 'catalog',
			'orderby'    => in_array( $s['orderby'], array( 'menu_order', 'popularity', 'price', 'date' ), true ) ? $s['orderby'] : 'menu_order',
			'order'      => 'date' === $s['orderby'] || 'popularity' === $s['orderby'] ? 'DESC' : 'ASC',
		);
		if ( 'popularity' === $args['orderby'] ) {
			$args['orderby']  = 'meta_value_num';
			$args['meta_key'] = 'total_sales'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		}
		if ( 'price' === $args['orderby'] ) {
			$args['orderby']  = 'meta_value_num';
			$args['meta_key'] = '_price'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		}
		$include = array_values( array_filter( (array) $s['categories'] ) );
		$exclude = array_values( array_filter( (array) $s['exclude_categories'] ) );
		if ( ! $include && $exclude ) {
			// wc_get_products() has no category exclusion: ask for every other category instead, so the limit still holds.
			$include = array_values( array_diff( array_keys( $this->category_options() ), $exclude ) );
		}
		if ( $include ) {
			$args['category'] = $include;
		}
		$products = wc_get_products( $args );

		if ( $exclude ) {
			// Products that sit in both an included and an excluded category.
			$products = array_filter(
				$products,
				static fn( \WC_Product $product ) => ! has_term( $exclude, 'product_cat', $product->get_id() )
			);
		}

		$style = in_array( $s['card_style'], array( 'card', 'large', 'list' ), true ) ? $s['card_style'] : 'card';

		echo '<section class="mlb-section mlb-products mlb-products--' . esc_attr( $style ) . '" data-mlb-products>';
		echo '<div class="mlb-container">';

		$title   = $this->text_el( 'h2', 'title', 'mlb-products__title mlb-heading', (string) $s['title'] );
		$eyebrow = $this->text_el( 'span', 'eyebrow', 'mlb-eyebrow', (string) $s['eyebrow'] );
		$tabs    = 'yes' === $s['show_tabs'] ? $this->tabs_html( $products, (string) $s['tabs_all'] ) : '';
		if ( '' !== $title . $eyebrow . $tabs ) {
			echo '<div class="mlb-products__head"><div class="mlb-heading-group">' . $eyebrow . $title . '</div>' . $tabs . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		if ( ! $products ) {
			if ( '' !== trim( (string) $s['empty_text'] ) ) {
				echo '<p class="mlb-products__empty">' . esc_html( (string) $s['empty_text'] ) . '</p>';
			}
			$this->editor_notice( __( 'Nu există produse publicate în categoriile alese. Adaugă-le în WooCommerce › Produse.', 'mlb-theme' ) );
		} else {
			echo '<div class="mlb-products__grid">';
			foreach ( $products as $product ) {
				$this->render_product( $product, $s, $style );
			}
			echo '</div>';
		}

		echo '</div></section>';
	}

	/**
	 * @param \WC_Product[] $products
	 */
	private function tabs_html( array $products, string $all_label ): string {
		$terms = array();
		foreach ( $products as $product ) {
			foreach ( (array) get_the_terms( $product->get_id(), 'product_cat' ) as $term ) {
				if ( $term instanceof \WP_Term && 'uncategorized' !== $term->slug ) {
					$terms[ $term->slug ] = $term;
				}
			}
		}
		if ( count( $terms ) < 2 ) {
			return '';
		}
		uasort( $terms, static fn( $a, $b ) => ( (int) get_term_meta( $a->term_id, 'order', true ) ) <=> ( (int) get_term_meta( $b->term_id, 'order', true ) ) );

		$html = '<div class="mlb-tabs" role="toolbar" aria-label="' . esc_attr__( 'Categorii', 'mlb-theme' ) . '">';
		$html .= '<button type="button" class="mlb-tab is-active" aria-pressed="true" data-filter="">' . esc_html( $all_label ) . '</button>';
		foreach ( $terms as $term ) {
			$html .= '<button type="button" class="mlb-tab" aria-pressed="false" data-filter="' . esc_attr( $term->slug ) . '">' . esc_html( $term->name ) . '</button>';
		}
		return $html . '</div>';
	}

	/**
	 * @param array<string, mixed> $s
	 */
	private function render_product( \WC_Product $product, array $s, string $style ): void {
		$id    = $product->get_id();
		$link  = $product->get_permalink();
		$name  = $product->get_name();
		$terms = get_the_terms( $id, 'product_cat' );
		$slugs = is_array( $terms ) ? wp_list_pluck( $terms, 'slug' ) : array();

		$badge = '';
		if ( '' !== trim( (string) $s['badge_tag'] ) && '' !== trim( (string) $s['badge_text'] ) && has_term( (string) $s['badge_tag'], 'product_tag', $id ) ) {
			$badge = '<span class="mlb-badge">' . esc_html( (string) $s['badge_text'] ) . '</span>';
		}

		$size = 'large' === $style ? 'woocommerce_single' : 'woocommerce_thumbnail';
		$img  = $product->get_image_id()
			? wp_get_attachment_image( (int) $product->get_image_id(), $size, false, array( 'class' => 'mlb-product__img', 'loading' => 'lazy', 'decoding' => 'async', 'alt' => $name ) )
			: '<div class="mlb-product__img mlb-ph" role="img" aria-label="' . esc_attr( $name ) . '"></div>';

		$weight = '';
		if ( 'yes' === $s['show_weight'] && '' !== (string) $product->get_weight() ) {
			$weight = wc_format_weight( $product->get_weight() );
		}

		$description = '';
		if ( 'yes' === $s['show_description'] ) {
			$description = wp_trim_words( wp_strip_all_tags( $product->get_short_description() ? $product->get_short_description() : $product->get_description() ), 'large' === $style ? 22 : 14 );
		}

		printf( '<article class="mlb-product" data-cats="%s">', esc_attr( implode( ' ', $slugs ) ) );
		echo '<a class="mlb-product__media" href="' . esc_url( $link ) . '" tabindex="-1" aria-hidden="true">' . $img . $badge . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="mlb-product__body">';
		echo '<div class="mlb-product__top"><h3 class="mlb-product__name"><a href="' . esc_url( $link ) . '">' . esc_html( $name ) . '</a></h3>';
		if ( '' !== $weight ) {
			echo '<span class="mlb-product__weight">' . esc_html( $weight ) . '</span>';
		}
		echo '</div>';
		if ( '' !== $description ) {
			echo '<p class="mlb-product__desc">' . esc_html( $description ) . '</p>';
		}
		echo '<div class="mlb-product__bottom"><span class="mlb-product__price">' . wp_kses_post( $product->get_price_html() ) . '</span>';
		echo $this->cart_button( $product, $s, $style ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div></div></article>';
	}

	/**
	 * Simple products are added by AJAX (WooCommerce's own add-to-cart script); others open the product page.
	 *
	 * @param array<string, mixed> $s
	 */
	private function cart_button( \WC_Product $product, array $s, string $style ): string {
		$name = $product->get_name();
		if ( ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return '<span class="mlb-product__soldout">' . esc_html__( 'Indisponibil', 'mlb-theme' ) . '</span>';
		}

		$compact = 'card' === $style || 'list' === $style;
		if ( $product->is_type( 'simple' ) ) {
			$label = (string) $s['button_label'];
			return sprintf(
				'<a href="%1$s" data-quantity="1" data-product_id="%2$d" data-product_sku="%3$s" class="button add_to_cart_button ajax_add_to_cart mlb-add%4$s" aria-label="%5$s" rel="nofollow">%6$s</a>',
				esc_url( $product->add_to_cart_url() ),
				$product->get_id(),
				esc_attr( $product->get_sku() ),
				$compact ? ' mlb-add--round' : '',
				/* translators: %s: product name */
				esc_attr( sprintf( __( 'Adaugă %s în coș', 'mlb-theme' ), $name ) ),
				$compact ? $this->icon( 'plus' ) : esc_html( $label )
			);
		}

		return sprintf(
			'<a href="%1$s" class="mlb-add mlb-add--choose" aria-label="%2$s">%3$s</a>',
			esc_url( $product->get_permalink() ),
			/* translators: %s: product name */
			esc_attr( sprintf( __( 'Alege varianta pentru %s', 'mlb-theme' ), $name ) ),
			esc_html( (string) $s['choose_label'] )
		);
	}

	private function editor_notice( string $message ): void {
		if ( \Elementor\Plugin::instance()->editor->is_edit_mode() ) {
			echo '<div class="mlb-editor-notice">' . esc_html( $message ) . '</div>';
		}
	}
}
