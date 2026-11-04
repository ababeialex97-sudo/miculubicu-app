<?php
/**
 * Thin announcement bar (first-order discount, free delivery threshold).
 */

namespace MLB\Theme\Widgets;

defined( 'ABSPATH' ) || exit;

class Promo_Bar extends Base {

	public function get_name(): string {
		return 'mlb-promo-bar';
	}

	public function get_title(): string {
		return __( 'Bandă promoție', 'mlb-theme' );
	}

	public function get_icon(): string {
		return 'eicon-alert';
	}

	protected function register_controls(): void {
		$this->content_section( 'content', __( 'Conținut', 'mlb-theme' ) );
		$this->text_control( 'text', __( 'Text', 'mlb-theme' ), '−10% la prima comandă online cu codul' );
		$this->text_control( 'code', __( 'Cod promoțional (gol = ascuns)', 'mlb-theme' ), '[COD]' );
		$this->text_control( 'extra', __( 'Text după cod', 'mlb-theme' ), 'Livrare gratuită peste [prag] lei' );
		$this->url_control( 'link', __( 'Link (opțional)', 'mlb-theme' ) );
		$this->switch_control( 'hide_on_scroll', __( 'Se ascunde la derulare', 'mlb-theme' ) );
		$this->end_controls_section();

		$this->color_controls(
			array(
				'promo-bg'      => __( 'Fundal', 'mlb-theme' ),
				'promo-text'    => __( 'Text', 'mlb-theme' ),
				'promo-code-bg' => __( 'Fundal cod', 'mlb-theme' ),
				'promo-code'    => __( 'Text cod', 'mlb-theme' ),
			)
		);
		$this->typography_controls( array( 'text' => array( __( 'Text', 'mlb-theme' ), '.mlb-promo' ) ), false );
	}

	protected function render(): void {
		$s     = $this->get_settings_for_display();
		$open  = $this->link_open( 'link', $s['link'] ?? null, 'mlb-promo__link' );
		$class = 'mlb-promo' . ( 'yes' === ( $s['hide_on_scroll'] ?? '' ) ? ' mlb-promo--collapsible' : '' );

		echo '<div class="' . esc_attr( $class ) . '"><div class="mlb-promo__inner">';
		echo $open; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped attributes.
		echo $this->text_el( 'span', 'text', 'mlb-promo__text', (string) $s['text'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->text_el( 'b', 'code', 'mlb-promo__code', (string) $s['code'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( '' !== trim( (string) $s['extra'] ) ) {
			echo '<span class="mlb-promo__sep" aria-hidden="true">·</span>';
			echo $this->text_el( 'span', 'extra', 'mlb-promo__extra', (string) $s['extra'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '' !== $open ? '</a>' : '';
		echo '</div></div>';
	}
}
