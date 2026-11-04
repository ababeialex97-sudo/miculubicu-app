<?php
/**
 * Customer reviews. Only real reviews (e.g. copied from Google with the author's public name).
 */

namespace MLB\Theme\Widgets;

use Elementor\Controls_Manager;
use Elementor\Repeater;

defined( 'ABSPATH' ) || exit;

class Reviews extends Base {

	public function get_name(): string {
		return 'mlb-reviews';
	}

	public function get_title(): string {
		return __( 'Recenzii', 'mlb-theme' );
	}

	public function get_icon(): string {
		return 'eicon-testimonial';
	}

	protected function register_controls(): void {
		$this->content_section( 'heading', __( 'Titlu', 'mlb-theme' ) );
		$this->textarea_control( 'title', __( 'Titlu', 'mlb-theme' ), 'Ce zic vasluienii' );
		$this->text_control( 'summary', __( 'Rezumat notă', 'mlb-theme' ), '[nota] din [nr] recenzii Google' );
		$this->url_control( 'summary_link', __( 'Link către recenzii (Google)', 'mlb-theme' ) );
		$this->end_controls_section();

		$this->content_section( 'content', __( 'Recenzii', 'mlb-theme' ) );
		$this->add_control(
			'notice',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Pune doar recenzii reale, copiate de pe Google sau Facebook, cu numele public al autorului.', 'mlb-theme' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			)
		);
		$item = new Repeater();
		$item->add_control( 'stars', array( 'label' => __( 'Stele', 'mlb-theme' ), 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => 5, 'default' => 5 ) );
		$item->add_control( 'text', array( 'label' => __( 'Recenzie', 'mlb-theme' ), 'type' => Controls_Manager::TEXTAREA, 'default' => '', 'rows' => 4 ) );
		$item->add_control( 'author', array( 'label' => __( 'Autor', 'mlb-theme' ), 'type' => Controls_Manager::TEXT, 'default' => '' ) );
		$item->add_control( 'source', array( 'label' => __( 'Sursă', 'mlb-theme' ), 'type' => Controls_Manager::TEXT, 'default' => 'Google' ) );
		$this->add_control(
			'items',
			array(
				'label'       => __( 'Recenzii', 'mlb-theme' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $item->get_controls(),
				'title_field' => '{{{ author }}}',
				'default'     => array(
					array( 'text' => '[Recenzie reală, preluată de pe Google]', 'author' => '[Nume]' ),
					array( 'text' => '[Recenzie reală, preluată de pe Google]', 'author' => '[Nume]' ),
					array( 'text' => '[Recenzie reală, preluată de pe Google]', 'author' => '[Nume]' ),
				),
			)
		);
		$this->columns_control( '.mlb-reviews__grid', 3, 2, 1 );
		$this->end_controls_section();

		$this->color_controls(
			array(
				'bg'      => __( 'Fundal secțiune', 'mlb-theme' ),
				'surface' => __( 'Fundal card', 'mlb-theme' ),
				'text'    => __( 'Text', 'mlb-theme' ),
				'muted'   => __( 'Autor', 'mlb-theme' ),
				'accent'  => __( 'Stele', 'mlb-theme' ),
			)
		);
		$this->typography_controls(
			array(
				'title' => array( __( 'Titlu', 'mlb-theme' ), '.mlb-reviews__title' ),
				'text'  => array( __( 'Recenzie', 'mlb-theme' ), '.mlb-review__text' ),
			)
		);
	}

	protected function render(): void {
		$s = $this->get_settings_for_display();
		echo '<section class="mlb-section mlb-reviews"><div class="mlb-container">';
		echo '<div class="mlb-reviews__head">';
		echo $this->text_el( 'h2', 'title', 'mlb-reviews__title mlb-heading', (string) $s['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( '' !== trim( (string) $s['summary'] ) ) {
			$open = $this->link_open( 'summary_link', $s['summary_link'] ?? null, 'mlb-reviews__summary-link' );
			echo '<p class="mlb-reviews__summary">' . $this->stars( 5 ) . $open . esc_html( (string) $s['summary'] ) . ( '' !== $open ? '</a>' : '' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div><div class="mlb-reviews__grid">';
		foreach ( (array) $s['items'] as $index => $item ) {
			echo '<figure class="mlb-review">';
			echo $this->stars( (int) ( $item['stars'] ?? 5 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<blockquote class="mlb-review__quote">' . $this->repeater_text_el( 'p', 'text', 'items', $index, 'mlb-review__text', (string) ( $item['text'] ?? '' ) ) . '</blockquote>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$who = trim( (string) ( $item['author'] ?? '' ) . ( '' !== trim( (string) ( $item['source'] ?? '' ) ) ? ', ' . ( $item['source'] ?? '' ) : '' ), ', ' );
			if ( '' !== $who ) {
				echo '<figcaption class="mlb-review__author">' . esc_html( $who ) . '</figcaption>';
			}
			echo '</figure>';
		}
		echo '</div></div></section>';
	}
}
