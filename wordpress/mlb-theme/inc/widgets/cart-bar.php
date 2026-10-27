<?php
/**
 * Phone-only bar fixed at the bottom: "Vezi coșul · N produse · total", or "Comandă online" when the cart is empty.
 */

namespace MLB\Theme\Widgets;

defined( 'ABSPATH' ) || exit;

class Cart_Bar extends Base {

	public function get_name(): string {
		return 'mlb-cart-bar';
	}

	public function get_title(): string {
		return __( 'Bară coș (telefon)', 'mlb-theme' );
	}

	public function get_icon(): string {
		return 'eicon-cart';
	}

	protected function is_dynamic_content(): bool {
		return true;
	}

	protected function register_controls(): void {
		$this->content_section( 'content', __( 'Conținut', 'mlb-theme' ) );
		$this->text_control( 'label', __( 'Text cu produse în coș', 'mlb-theme' ), 'Vezi coșul' );
		$this->text_control( 'empty_label', __( 'Text cu coșul gol', 'mlb-theme' ), 'Comandă online' );
		$this->url_control( 'empty_link', __( 'Link cu coșul gol', 'mlb-theme' ), '/#meniu' );
		$this->end_controls_section();

		$this->color_controls(
			array(
				'primary'   => __( 'Buton', 'mlb-theme' ),
				'header-bg' => __( 'Fundal bară', 'mlb-theme' ),
			)
		);
	}

	protected function render(): void {
		if ( ! function_exists( 'wc_get_cart_url' ) ) {
			return;
		}
		$s     = $this->get_settings_for_display();
		$empty = is_array( $s['empty_link'] ?? null ) && ! empty( $s['empty_link']['url'] ) ? (string) $s['empty_link']['url'] : home_url( '/' );

		printf(
			'<div class="mlb-cartbar" data-empty-href="%1$s" data-cart-href="%2$s"><a class="mlb-cartbar__btn" href="%2$s"><span class="mlb-cartbar__full">%3$s · %4$s</span><span class="mlb-cartbar__empty">%5$s</span>%6$s</a></div>',
			esc_url( $empty ),
			esc_url( wc_get_cart_url() ),
			esc_html( (string) $s['label'] ),
			mlb_theme_cart_count_html(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_html( (string) $s['empty_label'] ),
			mlb_theme_cart_total_html() // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}
}
