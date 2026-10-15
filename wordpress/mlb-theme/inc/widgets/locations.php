<?php
/**
 * Locations: photo, address, opening hours, call and navigate buttons.
 */

namespace MLB\Theme\Widgets;

use Elementor\Controls_Manager;
use Elementor\Repeater;
use Elementor\Utils;

defined( 'ABSPATH' ) || exit;

class Locations extends Base {

	public function get_name(): string {
		return 'mlb-locations';
	}

	public function get_title(): string {
		return __( 'Locații', 'mlb-theme' );
	}

	public function get_icon(): string {
		return 'eicon-map-pin';
	}

	protected function register_controls(): void {
		$this->content_section( 'heading', __( 'Titlu', 'mlb-theme' ) );
		$this->text_control( 'eyebrow', __( 'Supratitlu', 'mlb-theme' ), 'LOCAȚII' );
		$this->textarea_control( 'title', __( 'Titlu', 'mlb-theme' ), 'Unde ne găsești în Vaslui' );
		$this->end_controls_section();

		$this->content_section( 'content', __( 'Locații', 'mlb-theme' ) );
		$item = new Repeater();
		$item->add_control( 'image', array( 'label' => __( 'Poză', 'mlb-theme' ), 'type' => Controls_Manager::MEDIA, 'default' => array( 'url' => Utils::get_placeholder_image_src() ) ) );
		$item->add_control( 'name', array( 'label' => __( 'Nume', 'mlb-theme' ), 'type' => Controls_Manager::TEXT, 'default' => '', 'label_block' => true ) );
		$item->add_control( 'address', array( 'label' => __( 'Adresă', 'mlb-theme' ), 'type' => Controls_Manager::TEXT, 'default' => '', 'label_block' => true ) );
		$item->add_control( 'hours', array( 'label' => __( 'Program', 'mlb-theme' ), 'type' => Controls_Manager::TEXTAREA, 'default' => 'Program: [orele de funcționare]', 'rows' => 2 ) );
		$item->add_control( 'phone_label', array( 'label' => __( 'Text buton telefon', 'mlb-theme' ), 'type' => Controls_Manager::TEXT, 'default' => '', 'label_block' => true ) );
		$item->add_control( 'phone_number', array( 'label' => __( 'Număr de apelat', 'mlb-theme' ), 'type' => Controls_Manager::TEXT, 'default' => '' ) );
		$item->add_control( 'map_label', array( 'label' => __( 'Text buton hartă', 'mlb-theme' ), 'type' => Controls_Manager::TEXT, 'default' => 'Navighează cu Waze' ) );
		$item->add_control( 'map_link', array( 'label' => __( 'Link hartă (Waze / Google Maps)', 'mlb-theme' ), 'type' => Controls_Manager::URL, 'default' => array( 'url' => '' ) ) );
		$this->add_control(
			'items',
			array(
				'label'       => __( 'Locații', 'mlb-theme' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $item->get_controls(),
				'title_field' => '{{{ name }}}',
				'default'     => array(
					array( 'hours' => 'Program: [orele de funcționare]', 'map_label' => 'Navighează cu Waze', 'map_link' => array( 'url' => 'https://waze.com/ul?q=Micu%20lu%20Bicu%20La%20Spital%20Vaslui&navigate=yes', 'is_external' => 'on' ), 'name' => 'La Spital', 'address' => 'Str. Ștefan cel Mare, Vaslui', 'phone_label' => 'Sună: 0741 245 245', 'phone_number' => '0741245245' ),
					array( 'hours' => 'Program: [orele de funcționare]', 'map_label' => 'Navighează cu Waze', 'map_link' => array( 'url' => 'https://waze.com/ul?q=Micu%20lu%20Bicu%20La%20Gara%20Vaslui&navigate=yes', 'is_external' => 'on' ), 'name' => 'La Gară', 'address' => 'Strada Gării, Vaslui', 'phone_label' => 'Sună: 0735 099 099', 'phone_number' => '0735099099' ),
					array( 'hours' => 'Program: [orele de funcționare]', 'map_label' => 'Navighează cu Waze', 'map_link' => array( 'url' => 'https://waze.com/ul?q=Micu%20lu%20Bicu%20Piata%20Vidin%20Vaslui&navigate=yes', 'is_external' => 'on' ), 'name' => 'Piața Vidin', 'address' => 'Piața Centrală Vaslui, Piața Vidin', 'phone_label' => 'Sună: [telefon]', 'phone_number' => '' ),
				),
			)
		);
		$this->columns_control( '.mlb-locations__grid', 3, 2, 1 );
		$this->end_controls_section();

		$this->color_controls(
			array(
				'bg'      => __( 'Fundal secțiune', 'mlb-theme' ),
				'surface' => __( 'Fundal card', 'mlb-theme' ),
				'text'    => __( 'Titluri', 'mlb-theme' ),
				'muted'   => __( 'Text', 'mlb-theme' ),
				'accent'  => __( 'Supratitlu și iconițe', 'mlb-theme' ),
				'primary' => __( 'Buton telefon', 'mlb-theme' ),
				'line'    => __( 'Chenar buton hartă', 'mlb-theme' ),
			)
		);
		$this->typography_controls(
			array(
				'title' => array( __( 'Titlu secțiune', 'mlb-theme' ), '.mlb-locations__title' ),
				'name'  => array( __( 'Nume locație', 'mlb-theme' ), '.mlb-location__name' ),
			)
		);
	}

	protected function render(): void {
		$s = $this->get_settings_for_display();
		echo '<section class="mlb-section mlb-locations"><div class="mlb-container">';
		echo '<div class="mlb-heading-group">';
		echo $this->text_el( 'span', 'eyebrow', 'mlb-eyebrow', (string) $s['eyebrow'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->text_el( 'h2', 'title', 'mlb-locations__title mlb-heading', (string) $s['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div><div class="mlb-locations__grid">';

		foreach ( (array) $s['items'] as $index => $item ) {
			echo '<article class="mlb-location">';
			echo $this->image( $item['image'] ?? null, 'medium_large', 'mlb-location__image', false, (string) ( $item['name'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<div class="mlb-location__body">';
			echo $this->repeater_text_el( 'h3', 'name', 'items', $index, 'mlb-location__name mlb-heading', (string) ( $item['name'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			if ( '' !== trim( (string) ( $item['address'] ?? '' ) ) ) {
				echo '<p class="mlb-location__line">' . $this->icon( 'pin' ) . '<span>' . esc_html( (string) ( $item['address'] ?? '' ) ) . '</span></p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			if ( '' !== trim( (string) ( $item['hours'] ?? '' ) ) ) {
				echo '<p class="mlb-location__line">' . $this->icon( 'clock' ) . '<span>' . nl2br( esc_html( (string) ( $item['hours'] ?? '' ) ) ) . '</span></p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}

			$buttons = '';
			$number  = preg_replace( '/[^0-9+]/', '', (string) ( $item['phone_number'] ?? '' ) );
			if ( '' !== $number && '' !== trim( (string) ( $item['phone_label'] ?? '' ) ) ) {
				$buttons .= '<a class="mlb-btn mlb-btn--primary" href="tel:' . esc_attr( $number ) . '">' . esc_html( (string) ( $item['phone_label'] ?? '' ) ) . '</a>';
			}
			$buttons .= $this->button( 'map-' . $index, (string) ( $item['map_label'] ?? '' ), $item['map_link'] ?? null, 'ghost' );
			if ( '' !== $buttons ) {
				echo '<div class="mlb-location__buttons">' . $buttons . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</div></article>';
		}

		echo '</div></div></section>';
	}
}
