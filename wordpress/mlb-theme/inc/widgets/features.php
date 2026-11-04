<?php
/**
 * Strip of short selling points with icons (abator propriu, fără aditivi...).
 */

namespace MLB\Theme\Widgets;

use Elementor\Controls_Manager;
use Elementor\Icons_Manager;
use Elementor\Repeater;

defined( 'ABSPATH' ) || exit;

class Features extends Base {

	public function get_name(): string {
		return 'mlb-features';
	}

	public function get_title(): string {
		return __( 'Avantaje (bandă cu iconițe)', 'mlb-theme' );
	}

	public function get_icon(): string {
		return 'eicon-icon-box';
	}

	protected function register_controls(): void {
		$this->content_section( 'content', __( 'Avantaje', 'mlb-theme' ) );
		$item = new Repeater();
		$item->add_control(
			'icon',
			array(
				'label'   => __( 'Iconiță', 'mlb-theme' ),
				'type'    => Controls_Manager::ICONS,
				'default' => array( 'value' => 'fas fa-check-circle', 'library' => 'fa-solid' ),
			)
		);
		$item->add_control( 'title', array( 'label' => __( 'Titlu', 'mlb-theme' ), 'type' => Controls_Manager::TEXT, 'default' => '', 'label_block' => true ) );
		$item->add_control( 'text', array( 'label' => __( 'Text', 'mlb-theme' ), 'type' => Controls_Manager::TEXTAREA, 'default' => '', 'rows' => 2 ) );
		$this->add_control(
			'items',
			array(
				'label'       => __( 'Elemente', 'mlb-theme' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $item->get_controls(),
				'title_field' => '{{{ title }}}',
				'default'     => array(
					array( 'icon' => array( 'value' => 'fas fa-shield-alt', 'library' => 'fa-solid' ), 'title' => 'Abator propriu', 'text' => 'Cu ștampilă ovală sanitar-veterinară.' ),
					array( 'icon' => array( 'value' => 'fas fa-leaf', 'library' => 'fa-solid' ), 'title' => 'Fără aditivi', 'text' => 'Fără coloranți, fără carne injectată.' ),
					array( 'icon' => array( 'value' => 'fas fa-fire', 'library' => 'fa-solid' ), 'title' => 'Jar de cireș', 'text' => 'Cărbune încins și lemn de cireș.' ),
					array( 'icon' => array( 'value' => 'fas fa-motorcycle', 'library' => 'fa-solid' ), 'title' => 'Livrare caldă', 'text' => 'Plata cash la livrare sau la ridicare.' ),
				),
			)
		);
		$this->columns_control( '.mlb-features__grid', 4, 2, 1 );
		$this->end_controls_section();

		$this->color_controls(
			array(
				'band-bg' => __( 'Fundal bandă', 'mlb-theme' ),
				'text'    => __( 'Titluri', 'mlb-theme' ),
				'muted'   => __( 'Text', 'mlb-theme' ),
				'accent'  => __( 'Iconițe', 'mlb-theme' ),
			)
		);
		$this->typography_controls(
			array(
				'title' => array( __( 'Titluri', 'mlb-theme' ), '.mlb-feature__title' ),
				'text'  => array( __( 'Text', 'mlb-theme' ), '.mlb-feature__text' ),
			)
		);
	}

	protected function render(): void {
		$s = $this->get_settings_for_display();
		echo '<section class="mlb-section mlb-features"><div class="mlb-container mlb-features__grid">';
		foreach ( (array) $s['items'] as $index => $item ) {
			echo '<div class="mlb-feature"><span class="mlb-feature__icon">';
			if ( ! empty( $item['icon']['value'] ) ) {
				Icons_Manager::render_icon( ( $item['icon'] ?? '' ), array( 'aria-hidden' => 'true' ) );
			}
			echo '</span><div>';
			echo $this->repeater_text_el( 'div', 'title', 'items', $index, 'mlb-feature__title', (string) ( $item['title'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $this->repeater_text_el( 'div', 'text', 'items', $index, 'mlb-feature__text', (string) ( $item['text'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</div></div>';
		}
		echo '</div></section>';
	}
}
