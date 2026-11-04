<?php
/**
 * Hero: rating, headline, calls to action, delivery address box, big photo and loyalty card.
 */

namespace MLB\Theme\Widgets;

use Elementor\Controls_Manager;
use Elementor\Repeater;

defined( 'ABSPATH' ) || exit;

class Hero extends Base {

	public function get_name(): string {
		return 'mlb-hero';
	}

	public function get_title(): string {
		return __( 'Prima secțiune (hero)', 'mlb-theme' );
	}

	public function get_icon(): string {
		return 'eicon-banner';
	}

	protected function is_dynamic_content(): bool {
		return true; // Shows the address the visitor already entered.
	}

	protected function register_controls(): void {
		$this->content_section( 'content', __( 'Texte și butoane', 'mlb-theme' ) );
		$this->switch_control( 'show_rating', __( 'Arată nota Google', 'mlb-theme' ) );
		$this->add_control(
			'rating_stars',
			array(
				'label'     => __( 'Stele', 'mlb-theme' ),
				'type'      => Controls_Manager::NUMBER,
				'min'       => 1,
				'max'       => 5,
				'default'   => 5,
				'condition' => array( 'show_rating' => 'yes' ),
			)
		);
		$this->text_control( 'rating_text', __( 'Text notă', 'mlb-theme' ), '[nota] pe Google · [nr] recenzii', array( 'condition' => array( 'show_rating' => 'yes' ) ) );
		$this->textarea_control( 'title', __( 'Titlu', 'mlb-theme' ), 'Mici de 100 de grame, din abatorul nostru, pe jar de cireș.' );
		$this->textarea_control( 'text', __( 'Text', 'mlb-theme' ), 'Carne românească, fără aditivi și fără coloranți. Piperul îl măcinăm chiar înainte să intre în carne. Comanzi aici sau din aplicație și ți-i aducem calzi.' );
		$this->text_control( 'button_label', __( 'Buton principal', 'mlb-theme' ), 'Comandă acum' );
		$this->url_control( 'button_link', __( 'Link buton principal', 'mlb-theme' ), '#meniu' );
		$this->text_control( 'button2_label', __( 'Buton secundar', 'mlb-theme' ), 'Vezi locațiile' );
		$this->url_control( 'button2_link', __( 'Link buton secundar', 'mlb-theme' ), '#locatii' );
		$this->end_controls_section();

		$this->content_section( 'delivery', __( 'Caseta „Livrăm la tine?”', 'mlb-theme' ) );
		$this->switch_control( 'show_delivery', __( 'Arată caseta', 'mlb-theme' ) );
		$this->text_control( 'delivery_label', __( 'Titlu', 'mlb-theme' ), 'Livrăm la tine?' );
		$this->text_control( 'delivery_placeholder', __( 'Text în câmp', 'mlb-theme' ), 'Strada și numărul, Vaslui' );
		$this->text_control( 'delivery_button', __( 'Text buton', 'mlb-theme' ), 'Comandă' );
		$this->url_control( 'delivery_target', __( 'Unde duce (adresa se păstrează pentru finalizarea comenzii)', 'mlb-theme' ), '#meniu' );
		$this->text_control( 'delivery_saved', __( 'Text după ce a scris adresa', 'mlb-theme' ), 'Livrăm la:' );
		$info = new Repeater();
		$info->add_control( 'text', array( 'label' => __( 'Text', 'mlb-theme' ), 'type' => Controls_Manager::TEXT, 'default' => '' ) );
		$this->add_control(
			'delivery_info',
			array(
				'label'       => __( 'Informații sub câmp', 'mlb-theme' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $info->get_controls(),
				'title_field' => '{{{ text }}}',
				'default'     => array(
					array( 'text' => 'Livrare în ~[45] min' ),
					array( 'text' => 'Cost livrare [x] lei' ),
					array( 'text' => 'Plata cash la livrare' ),
				),
			)
		);
		$this->end_controls_section();

		$this->content_section( 'media', __( 'Poză și card fidelitate', 'mlb-theme' ) );
		$this->media_control( 'image', __( 'Poză', 'mlb-theme' ) );
		$this->add_responsive_control(
			'image_height',
			array(
				'label'      => __( 'Înălțime poză', 'mlb-theme' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'vh' ),
				'range'      => array( 'px' => array( 'min' => 200, 'max' => 900 ) ),
				'selectors'  => array( '{{WRAPPER}} .mlb-hero__image' => 'height: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->switch_control( 'show_card', __( 'Arată cardul de fidelitate', 'mlb-theme' ) );
		$this->text_control( 'card_title', __( 'Titlu card', 'mlb-theme' ), 'Cardul lui Bicu', array( 'condition' => array( 'show_card' => 'yes' ) ) );
		$this->text_control( 'card_text', __( 'Text card', 'mlb-theme' ), 'La 4 comenzi finalizate, reducere la următoarea.', array( 'condition' => array( 'show_card' => 'yes' ) ) );
		$this->add_control(
			'card_stamps',
			array(
				'label'     => __( 'Ștampile', 'mlb-theme' ),
				'type'      => Controls_Manager::NUMBER,
				'min'       => 1,
				'max'       => 10,
				'default'   => 4,
				'condition' => array( 'show_card' => 'yes' ),
			)
		);
		$this->add_control(
			'card_filled',
			array(
				'label'     => __( 'Ștampile pline (exemplu)', 'mlb-theme' ),
				'type'      => Controls_Manager::NUMBER,
				'min'       => 0,
				'max'       => 10,
				'default'   => 2,
				'condition' => array( 'show_card' => 'yes' ),
			)
		);
		$this->end_controls_section();

		$this->color_controls(
			array(
				'bg'      => __( 'Fundal secțiune', 'mlb-theme' ),
				'text'    => __( 'Titlu', 'mlb-theme' ),
				'muted'   => __( 'Text', 'mlb-theme' ),
				'accent'  => __( 'Accent (stele, buton secundar, card)', 'mlb-theme' ),
				'primary' => __( 'Buton principal', 'mlb-theme' ),
				'surface' => __( 'Fundal casetă livrare', 'mlb-theme' ),
			)
		);
		$this->typography_controls(
			array(
				'title' => array( __( 'Titlu', 'mlb-theme' ), '.mlb-hero__title' ),
				'text'  => array( __( 'Text', 'mlb-theme' ), '.mlb-hero__text' ),
			)
		);
	}

	protected function render(): void {
		$s = $this->get_settings_for_display();

		echo '<section class="mlb-section mlb-hero"><div class="mlb-container mlb-hero__grid">';
		echo '<div class="mlb-hero__copy">';

		if ( 'yes' === $s['show_rating'] ) {
			echo '<p class="mlb-hero__rating">' . $this->stars( (int) $s['rating_stars'] ) . $this->text_el( 'span', 'rating_text', 'mlb-hero__rating-text', (string) $s['rating_text'] ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo $this->text_el( 'h1', 'title', 'mlb-hero__title mlb-heading', (string) $s['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->text_el( 'p', 'text', 'mlb-hero__text', (string) $s['text'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$buttons = $this->button( 'button', (string) $s['button_label'], $s['button_link'] ?? null, 'primary', 'mlb-btn--lg' )
			. $this->button( 'button2', (string) $s['button2_label'], $s['button2_link'] ?? null, 'outline', 'mlb-btn--lg' );
		if ( '' !== $buttons ) {
			echo '<div class="mlb-hero__buttons">' . $buttons . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		if ( 'yes' === $s['show_delivery'] ) {
			$this->render_delivery( $s );
		}
		echo '</div>';

		echo '<div class="mlb-hero__media">';
		echo $this->image( $s['image'] ?? null, 'large', 'mlb-hero__image', true, __( 'Poză: mici pe jar', 'mlb-theme' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( 'yes' === $s['show_card'] ) {
			$total  = max( 1, (int) $s['card_stamps'] );
			$filled = min( $total, max( 0, (int) $s['card_filled'] ) );
			echo '<div class="mlb-loyalty-card">';
			echo $this->text_el( 'span', 'card_title', 'mlb-loyalty-card__title', (string) $s['card_title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<span class="mlb-loyalty-card__stamps" aria-hidden="true">';
			for ( $i = 0; $i < $total; $i++ ) {
				echo '<span class="mlb-stamp' . ( $i < $filled ? ' is-filled' : '' ) . '"></span>';
			}
			echo '<span class="mlb-stamp is-reward"></span></span>';
			echo $this->text_el( 'span', 'card_text', 'mlb-loyalty-card__text', (string) $s['card_text'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</div>';
		}
		echo '</div>';

		echo '</div></section>';
	}

	/**
	 * @param array<string, mixed> $s
	 */
	private function render_delivery( array $s ): void {
		$target = is_array( $s['delivery_target'] ?? null ) && ! empty( $s['delivery_target']['url'] ) ? (string) $s['delivery_target']['url'] : home_url( '/' );
		if ( str_starts_with( $target, '#' ) ) {
			$target = home_url( '/' ) . $target;
		}
		$input_id = 'mlb-adresa-' . $this->get_id();
		$saved    = '';
		if ( function_exists( 'WC' ) && null !== WC()->session ) {
			$saved = (string) WC()->session->get( 'mlb_address', '' );
		}

		echo '<form class="mlb-delivery" method="get" action="' . esc_url( $target ) . '">';
		echo '<label class="mlb-delivery__label" for="' . esc_attr( $input_id ) . '">' . esc_html( (string) $s['delivery_label'] ) . '</label>';
		echo '<div class="mlb-delivery__row">';
		printf(
			'<input class="mlb-delivery__input" id="%1$s" name="%2$s" type="text" autocomplete="street-address" placeholder="%3$s" value="%4$s" required>',
			esc_attr( $input_id ),
			esc_attr( MLB_THEME_ADDRESS_PARAM ),
			esc_attr( (string) $s['delivery_placeholder'] ),
			esc_attr( $saved )
		);
		echo '<button class="mlb-btn mlb-btn--accent" type="submit">' . esc_html( (string) $s['delivery_button'] ) . '</button>';
		echo '</div>';
		if ( '' !== $saved && '' !== trim( (string) $s['delivery_saved'] ) ) {
			echo '<p class="mlb-delivery__saved">' . esc_html( (string) $s['delivery_saved'] ) . ' <strong>' . esc_html( $saved ) . '</strong></p>';
		}
		$items = array_filter( (array) $s['delivery_info'], static fn( $item ) => '' !== trim( (string) ( $item['text'] ?? '' ) ) );
		if ( $items ) {
			echo '<ul class="mlb-delivery__info">';
			foreach ( $items as $item ) {
				echo '<li>' . esc_html( (string) ( $item['text'] ?? '' ) ) . '</li>';
			}
			echo '</ul>';
		}
		echo '</form>';
	}
}
