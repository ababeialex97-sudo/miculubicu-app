<?php
/**
 * Mustard band promoting the mobile app and the loyalty card.
 */

namespace MLB\Theme\Widgets;

use Elementor\Controls_Manager;
use Elementor\Repeater;

defined( 'ABSPATH' ) || exit;

class App_Band extends Base {

	public function get_name(): string {
		return 'mlb-app-band';
	}

	public function get_title(): string {
		return __( 'Bandă aplicație', 'mlb-theme' );
	}

	public function get_icon(): string {
		return 'eicon-device-mobile';
	}

	protected function register_controls(): void {
		$this->content_section( 'content', __( 'Texte și linkuri', 'mlb-theme' ) );
		$this->text_control( 'eyebrow', __( 'Supratitlu', 'mlb-theme' ), "APLICAȚIA MICU' LU' BICU" );
		$this->textarea_control( 'title', __( 'Titlu', 'mlb-theme' ), 'Comanzi din telefon și strângi ștampile' );
		$this->textarea_control( 'text', __( 'Text', 'mlb-theme' ), 'Urmărești comanda pas cu pas, repeți o comandă dintr-o atingere, iar după 4 comenzi primești reducere la următoarea.' );
		$this->text_control( 'ios_label', __( 'Text App Store', 'mlb-theme' ), 'App Store' );
		$this->url_control( 'ios_link', __( 'Link App Store (gol = ascuns)', 'mlb-theme' ), '#' );
		$this->text_control( 'android_label', __( 'Text Google Play', 'mlb-theme' ), 'Google Play' );
		$this->url_control( 'android_link', __( 'Link Google Play (gol = ascuns)', 'mlb-theme' ), '#' );
		$this->end_controls_section();

		$this->content_section( 'phone', __( 'Telefon', 'mlb-theme' ) );
		$this->add_control(
			'phone_image',
			array(
				'label'       => __( 'Captură din aplicație (gol = machetă desenată)', 'mlb-theme' ),
				'type'        => Controls_Manager::MEDIA,
				'default'     => array( 'url' => '' ),
			)
		);
		$this->text_control( 'mock_title', __( 'Titlu ecran', 'mlb-theme' ), 'Fidelitate' );
		$this->text_control( 'mock_card', __( 'Titlu card', 'mlb-theme' ), 'Cardul lui Bicu · 3 / 4' );
		$coupon = new Repeater();
		$coupon->add_control( 'text', array( 'label' => __( 'Text', 'mlb-theme' ), 'type' => Controls_Manager::TEXT, 'default' => '' ) );
		$this->add_control(
			'mock_coupons',
			array(
				'label'       => __( 'Cupoane afișate', 'mlb-theme' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $coupon->get_controls(),
				'title_field' => '{{{ text }}}',
				'default'     => array(
					array( 'text' => '−10% recompensa ta' ),
					array( 'text' => 'Livrare gratuită peste [prag] lei' ),
				),
			)
		);
		$this->end_controls_section();

		$this->color_controls(
			array(
				'band-accent' => __( 'Fundal bandă', 'mlb-theme' ),
				'on-accent'   => __( 'Text pe bandă', 'mlb-theme' ),
			)
		);
		$this->typography_controls(
			array(
				'title' => array( __( 'Titlu', 'mlb-theme' ), '.mlb-app-band__title' ),
				'text'  => array( __( 'Text', 'mlb-theme' ), '.mlb-app-band__text' ),
			)
		);
	}

	protected function render(): void {
		$s = $this->get_settings_for_display();

		echo '<section class="mlb-section mlb-app-band-section"><div class="mlb-container"><div class="mlb-app-band">';
		echo '<div class="mlb-app-band__copy">';
		echo $this->text_el( 'span', 'eyebrow', 'mlb-eyebrow', (string) $s['eyebrow'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->text_el( 'h2', 'title', 'mlb-app-band__title mlb-heading', (string) $s['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->text_el( 'p', 'text', 'mlb-app-band__text', (string) $s['text'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$stores = $this->button( 'ios', (string) $s['ios_label'], $s['ios_link'] ?? null, 'dark', 'mlb-btn--lg' )
			. $this->button( 'android', (string) $s['android_label'], $s['android_link'] ?? null, 'dark', 'mlb-btn--lg' );
		if ( '' !== $stores ) {
			echo '<div class="mlb-app-band__stores">' . $stores . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';

		echo '<div class="mlb-phone" aria-hidden="true"><div class="mlb-phone__screen">';
		$image_id = (int) ( $s['phone_image']['id'] ?? 0 );
		if ( $image_id ) {
			echo wp_get_attachment_image( $image_id, 'medium_large', false, array( 'class' => 'mlb-phone__img', 'loading' => 'lazy', 'alt' => '' ) );
		} else {
			echo '<span class="mlb-phone__title mlb-heading">' . esc_html( (string) $s['mock_title'] ) . '</span>';
			echo '<div class="mlb-phone__card"><span class="mlb-phone__card-title mlb-heading">' . esc_html( (string) $s['mock_card'] ) . '</span>';
			echo '<span class="mlb-loyalty-card__stamps"><span class="mlb-stamp is-filled"></span><span class="mlb-stamp is-filled"></span><span class="mlb-stamp is-filled"></span><span class="mlb-stamp"></span><span class="mlb-stamp is-reward"></span></span></div>';
			foreach ( (array) $s['mock_coupons'] as $coupon ) {
				if ( '' !== trim( (string) ( $coupon['text'] ?? '' ) ) ) {
					echo '<span class="mlb-phone__coupon">' . esc_html( (string) ( $coupon['text'] ?? '' ) ) . '</span>';
				}
			}
		}
		echo '</div></div>';

		echo '</div></div></section>';
	}
}
