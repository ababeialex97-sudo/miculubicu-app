<?php
/**
 * Eyebrow + title + text, for building any extra section from Elementor.
 */

namespace MLB\Theme\Widgets;

use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

class Section_Title extends Base {

	public function get_name(): string {
		return 'mlb-section-title';
	}

	public function get_title(): string {
		return __( 'Titlu de secțiune', 'mlb-theme' );
	}

	public function get_icon(): string {
		return 'eicon-t-letter';
	}

	protected function register_controls(): void {
		$this->content_section( 'content', __( 'Conținut', 'mlb-theme' ) );
		$this->text_control( 'eyebrow', __( 'Supratitlu', 'mlb-theme' ), 'SUPRATITLU' );
		$this->textarea_control( 'title', __( 'Titlu', 'mlb-theme' ), 'Titlul secțiunii' );
		$this->textarea_control( 'text', __( 'Text', 'mlb-theme' ), '' );
		$this->add_control(
			'tag',
			array(
				'label'   => __( 'Nivel titlu', 'mlb-theme' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array( 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3' ),
				'default' => 'h2',
			)
		);
		$this->add_responsive_control(
			'align',
			array(
				'label'     => __( 'Aliniere', 'mlb-theme' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'left'   => array( 'title' => __( 'Stânga', 'mlb-theme' ), 'icon' => 'eicon-text-align-left' ),
					'center' => array( 'title' => __( 'Centru', 'mlb-theme' ), 'icon' => 'eicon-text-align-center' ),
				),
				'default'   => 'left',
				'selectors' => array( '{{WRAPPER}} .mlb-section-title' => 'text-align: {{VALUE}};' ),
			)
		);
		$this->end_controls_section();

		$this->color_controls(
			array(
				'accent' => __( 'Supratitlu', 'mlb-theme' ),
				'text'   => __( 'Titlu', 'mlb-theme' ),
				'muted'  => __( 'Text', 'mlb-theme' ),
			)
		);
		$this->typography_controls(
			array(
				'title' => array( __( 'Titlu', 'mlb-theme' ), '.mlb-section-title__title' ),
				'text'  => array( __( 'Text', 'mlb-theme' ), '.mlb-section-title__text' ),
			),
			false
		);
	}

	protected function render(): void {
		$s   = $this->get_settings_for_display();
		$tag = in_array( $s['tag'], array( 'h1', 'h2', 'h3' ), true ) ? $s['tag'] : 'h2';
		echo '<div class="mlb-section-title">';
		echo $this->text_el( 'span', 'eyebrow', 'mlb-eyebrow', (string) $s['eyebrow'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->text_el( $tag, 'title', 'mlb-section-title__title mlb-heading', (string) $s['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->text_el( 'p', 'text', 'mlb-section-title__text', (string) $s['text'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
	}
}
