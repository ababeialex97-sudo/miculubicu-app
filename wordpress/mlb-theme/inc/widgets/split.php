<?php
/**
 * Photo + text block (Abator, Povestea noastră, Hotel...). Optional list of small boxes and a button.
 */

namespace MLB\Theme\Widgets;

use Elementor\Controls_Manager;
use Elementor\Repeater;

defined( 'ABSPATH' ) || exit;

class Split extends Base {

	public function get_name(): string {
		return 'mlb-split';
	}

	public function get_title(): string {
		return __( 'Poză și text', 'mlb-theme' );
	}

	public function get_icon(): string {
		return 'eicon-image-box';
	}

	protected function register_controls(): void {
		$this->content_section( 'content', __( 'Conținut', 'mlb-theme' ) );
		$this->media_control( 'image', __( 'Poză', 'mlb-theme' ) );
		$this->add_control(
			'image_side',
			array(
				'label'   => __( 'Poza în', 'mlb-theme' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'left'  => __( 'Stânga', 'mlb-theme' ),
					'right' => __( 'Dreapta', 'mlb-theme' ),
				),
				'default' => 'left',
			)
		);
		$this->switch_control( 'boxed', __( 'Pe card (fundal)', 'mlb-theme' ) );
		$this->text_control( 'eyebrow', __( 'Supratitlu', 'mlb-theme' ), 'DIN ABATORUL NOSTRU' );
		$this->textarea_control( 'title', __( 'Titlu', 'mlb-theme' ), 'Crude, pentru grătarul tău de acasă' );
		$this->add_control(
			'text',
			array(
				'label'   => __( 'Text', 'mlb-theme' ),
				'type'    => Controls_Manager::WYSIWYG,
				'default' => '<p>Aceeași carne pe care o punem noi pe jar: mici cruzi de 100 g, ceafă, cârnăciori și pastramă de oaie din oi tăiate în abatorul propriu.</p>',
			)
		);
		$item = new Repeater();
		$item->add_control( 'title', array( 'label' => __( 'Titlu', 'mlb-theme' ), 'type' => Controls_Manager::TEXT, 'default' => '', 'label_block' => true ) );
		$item->add_control( 'subtitle', array( 'label' => __( 'Detaliu', 'mlb-theme' ), 'type' => Controls_Manager::TEXT, 'default' => '', 'label_block' => true ) );
		$this->add_control(
			'items',
			array(
				'label'       => __( 'Casete mici (opțional)', 'mlb-theme' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $item->get_controls(),
				'title_field' => '{{{ title }}}',
				'default'     => array(),
			)
		);
		$this->text_control( 'button_label', __( 'Text buton', 'mlb-theme' ), '' );
		$this->url_control( 'button_link', __( 'Link buton', 'mlb-theme' ), '#meniu' );
		$this->add_control(
			'button_style',
			array(
				'label'   => __( 'Stil buton', 'mlb-theme' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'primary' => __( 'Plin (roșu)', 'mlb-theme' ),
					'outline' => __( 'Contur (muștar)', 'mlb-theme' ),
					'link'    => __( 'Link simplu', 'mlb-theme' ),
				),
				'default' => 'outline',
			)
		);
		$this->add_responsive_control(
			'image_height',
			array(
				'label'      => __( 'Înălțime minimă poză', 'mlb-theme' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 200, 'max' => 800 ) ),
				'selectors'  => array( '{{WRAPPER}} .mlb-split__image' => 'min-height: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();

		$this->color_controls(
			array(
				'bg'      => __( 'Fundal secțiune', 'mlb-theme' ),
				'surface' => __( 'Fundal card', 'mlb-theme' ),
				'text'    => __( 'Titlu', 'mlb-theme' ),
				'muted'   => __( 'Text', 'mlb-theme' ),
				'accent'  => __( 'Supratitlu și buton contur', 'mlb-theme' ),
				'primary' => __( 'Buton plin', 'mlb-theme' ),
				'line'    => __( 'Chenare casete', 'mlb-theme' ),
			)
		);
		$this->typography_controls(
			array(
				'title' => array( __( 'Titlu', 'mlb-theme' ), '.mlb-split__title' ),
				'text'  => array( __( 'Text', 'mlb-theme' ), '.mlb-split__text' ),
			)
		);
	}

	protected function render(): void {
		$s       = $this->get_settings_for_display();
		$classes = 'mlb-split mlb-split--image-' . ( 'right' === $s['image_side'] ? 'right' : 'left' ) . ( 'yes' === $s['boxed'] ? ' mlb-split--boxed' : '' );

		echo '<section class="mlb-section mlb-split-section"><div class="mlb-container"><div class="' . esc_attr( $classes ) . '">';
		echo $this->image( $s['image'] ?? null, 'large', 'mlb-split__image', false, (string) $s['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="mlb-split__copy">';
		echo $this->text_el( 'span', 'eyebrow', 'mlb-eyebrow', (string) $s['eyebrow'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->text_el( 'h2', 'title', 'mlb-split__title mlb-heading', (string) $s['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->text_el( 'div', 'text', 'mlb-split__text', (string) $s['text'], 'advanced' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$items = (array) $s['items'];
		if ( $items ) {
			echo '<div class="mlb-split__items">';
			foreach ( $items as $index => $item ) {
				echo '<div class="mlb-mini-box">';
				echo $this->repeater_text_el( 'div', 'title', 'items', $index, 'mlb-mini-box__title', (string) ( $item['title'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $this->repeater_text_el( 'div', 'subtitle', 'items', $index, 'mlb-mini-box__text', (string) ( $item['subtitle'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '</div>';
			}
			echo '</div>';
		}

		$style = in_array( $s['button_style'], array( 'primary', 'outline', 'link' ), true ) ? $s['button_style'] : 'outline';
		echo $this->button( 'button', (string) $s['button_label'], $s['button_link'] ?? null, $style ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div></div></div></section>';
	}
}
