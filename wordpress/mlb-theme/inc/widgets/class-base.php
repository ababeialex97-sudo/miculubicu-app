<?php
/**
 * Shared helpers for the theme's Elementor widgets.
 */

namespace MLB\Theme\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Utils;
use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

abstract class Base extends Widget_Base {

	public function get_categories(): array {
		return array( 'mlb' );
	}

	public function get_keywords(): array {
		return array( 'micu', 'bicu', 'mlb' );
	}

	public function get_style_depends(): array {
		return array( 'mlb-theme' );
	}

	public function get_script_depends(): array {
		return array( 'mlb-theme' );
	}

	/* ---------- Control helpers ---------- */

	protected function content_section( string $id, string $label ): void {
		$this->start_controls_section( $id, array( 'label' => $label ) );
	}

	protected function style_section( string $id, string $label ): void {
		$this->start_controls_section(
			$id,
			array(
				'label' => $label,
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
	}

	protected function text_control( string $id, string $label, string $default = '', array $extra = array() ): void {
		$this->add_control(
			$id,
			array_merge(
				array(
					'label'       => $label,
					'type'        => Controls_Manager::TEXT,
					'default'     => $default,
					'label_block' => true,
					'dynamic'     => array( 'active' => true ),
				),
				$extra
			)
		);
	}

	protected function textarea_control( string $id, string $label, string $default = '', array $extra = array() ): void {
		$this->text_control( $id, $label, $default, array_merge( array( 'type' => Controls_Manager::TEXTAREA, 'rows' => 3 ), $extra ) );
	}

	protected function url_control( string $id, string $label, string $default = '' ): void {
		$this->add_control(
			$id,
			array(
				'label'       => $label,
				'type'        => Controls_Manager::URL,
				'default'     => array( 'url' => $default ),
				'dynamic'     => array( 'active' => true ),
				'label_block' => true,
			)
		);
	}

	protected function media_control( string $id, string $label ): void {
		$this->add_control(
			$id,
			array(
				'label'   => $label,
				'type'    => Controls_Manager::MEDIA,
				'default' => array( 'url' => Utils::get_placeholder_image_src() ),
				'dynamic' => array( 'active' => true ),
			)
		);
	}

	protected function switch_control( string $id, string $label, bool $on = true ): void {
		$this->add_control(
			$id,
			array(
				'label'        => $label,
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'Da', 'mlb-theme' ),
				'label_off'    => __( 'Nu', 'mlb-theme' ),
				'return_value' => 'yes',
				'default'      => $on ? 'yes' : '',
			)
		);
	}

	/**
	 * Style tab: colors as CSS variables on the widget wrapper, so one control recolors every element that uses it.
	 *
	 * @param array<string, string> $vars CSS variable suffix => label.
	 */
	protected function color_controls( array $vars ): void {
		$this->style_section( 'style_colors', __( 'Culori', 'mlb-theme' ) );
		foreach ( $vars as $var => $label ) {
			$this->add_control(
				'color_' . str_replace( '-', '_', $var ),
				array(
					'label'     => $label,
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}}' => '--mlb-' . $var . ': {{VALUE}};' ),
				)
			);
		}
		$this->end_controls_section();
	}

	/**
	 * Style tab: typography for the given parts plus the section's inner spacing.
	 *
	 * @param array<string, array{0: string, 1: string}> $parts id => [label, selector].
	 */
	protected function typography_controls( array $parts, bool $with_padding = true ): void {
		$this->style_section( 'style_typography', __( 'Text și spațiere', 'mlb-theme' ) );
		foreach ( $parts as $id => list( $label, $selector ) ) {
			$this->add_group_control(
				Group_Control_Typography::get_type(),
				array(
					'name'     => $id . '_typography',
					'label'    => $label,
					'selector' => '{{WRAPPER}} ' . $selector,
				)
			);
		}
		if ( $with_padding ) {
			$this->add_responsive_control(
				'section_padding',
				array(
					'label'      => __( 'Spațiu interior secțiune', 'mlb-theme' ),
					'type'       => Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em', '%' ),
					'selectors'  => array( '{{WRAPPER}} .mlb-section' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
		}
		$this->end_controls_section();
	}

	protected function columns_control( string $selector, int $desktop, int $tablet = 2, int $mobile = 1, int $max = 4 ): void {
		$options = array();
		for ( $i = 1; $i <= $max; $i++ ) {
			$options[ (string) $i ] = (string) $i;
		}
		$this->add_responsive_control(
			'columns',
			array(
				'label'           => __( 'Coloane', 'mlb-theme' ),
				'type'            => Controls_Manager::SELECT,
				'options'         => $options,
				'default'         => (string) $desktop,
				'tablet_default'  => (string) $tablet,
				'mobile_default'  => (string) $mobile,
				'selectors'       => array( '{{WRAPPER}} ' . $selector => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));' ),
			)
		);
	}

	/* ---------- Render helpers ---------- */

	/**
	 * A text element that the owner can also edit by clicking it in the Elementor preview.
	 */
	protected function text_el( string $tag, string $setting, string $class, string $value, string $mode = 'none' ): string {
		if ( '' === trim( $value ) ) {
			return '';
		}
		$this->add_render_attribute( $setting, 'class', $class );
		$this->add_inline_editing_attributes( $setting, $mode );
		$content = 'none' === $mode ? nl2br( esc_html( $value ) ) : wp_kses_post( $value );
		return sprintf( '<%1$s %2$s>%3$s</%1$s>', tag_escape( $tag ), $this->get_render_attribute_string( $setting ), $content );
	}

	/**
	 * Same as text_el, for a field inside a repeater item.
	 */
	protected function repeater_text_el( string $tag, string $field, string $repeater, int $index, string $class, string $value ): string {
		if ( '' === trim( $value ) ) {
			return '';
		}
		$key = $this->get_repeater_setting_key( $field, $repeater, $index );
		$this->add_render_attribute( $key, 'class', $class );
		$this->add_inline_editing_attributes( $key, 'none' );
		return sprintf( '<%1$s %2$s>%3$s</%1$s>', tag_escape( $tag ), $this->get_render_attribute_string( $key ), nl2br( esc_html( $value ) ) );
	}

	/**
	 * Opening <a> for an Elementor URL control, or '' when the URL is empty.
	 *
	 * @param array<string, mixed>|null $link
	 */
	protected function link_open( string $key, $link, string $class, string $aria_label = '' ): string {
		if ( ! is_array( $link ) || empty( $link['url'] ) ) {
			return '';
		}
		$this->add_link_attributes( $key, $link );
		$this->add_render_attribute( $key, 'class', $class );
		if ( '' !== $aria_label ) {
			$this->add_render_attribute( $key, 'aria-label', $aria_label );
		}
		return '<a ' . $this->get_render_attribute_string( $key ) . '>';
	}

	/**
	 * A button-styled link; nothing when either the label or the URL is empty.
	 *
	 * @param array<string, mixed>|null $link
	 */
	protected function button( string $key, string $label, $link, string $variant = 'primary', string $extra_class = '' ): string {
		$open = '' !== trim( $label ) ? $this->link_open( $key, $link, trim( 'mlb-btn mlb-btn--' . $variant . ' ' . $extra_class ) ) : '';
		return '' === $open ? '' : $open . esc_html( $label ) . '</a>';
	}

	/**
	 * Image from a MEDIA control. The Elementor placeholder renders as a warm "add a photo" block instead.
	 *
	 * @param array<string, mixed>|null $media
	 */
	protected function image( $media, string $size, string $class, bool $eager = false, string $placeholder_label = '' ): string {
		$id  = is_array( $media ) ? (int) ( $media['id'] ?? 0 ) : 0;
		$url = is_array( $media ) ? (string) ( $media['url'] ?? '' ) : '';

		if ( $id > 0 ) {
			$attrs = array(
				'class'    => $class,
				'loading'  => $eager ? 'eager' : 'lazy',
				'decoding' => 'async',
			);
			if ( $eager ) {
				$attrs['fetchpriority'] = 'high';
			}
			$html = wp_get_attachment_image( $id, $size, false, $attrs );
			if ( '' !== $html ) {
				return $html;
			}
		}

		if ( '' !== $url && Utils::get_placeholder_image_src() !== $url ) {
			return sprintf( '<img class="%s" src="%s" alt="" loading="%s" decoding="async">', esc_attr( $class ), esc_url( $url ), $eager ? 'eager' : 'lazy' );
		}

		return sprintf(
			'<div class="%s mlb-ph" role="img" aria-label="%s"><span class="mlb-ph__label">%s</span></div>',
			esc_attr( $class ),
			esc_attr( $placeholder_label ),
			esc_html( $placeholder_label ? $placeholder_label : __( 'Adaugă o poză', 'mlb-theme' ) )
		);
	}

	protected function icon( string $name ): string {
		$paths = array(
			'phone'  => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
			'cart'   => '<path d="M6 7h12l-1 13H7z"/><path d="M9 7a3 3 0 0 1 6 0"/>',
			'arrow'  => '<path d="M5 12h14M13 6l6 6-6 6"/>',
			'plus'   => '<path d="M12 5v14M5 12h14"/>',
			'menu'   => '<path d="M4 7h16M4 12h16M4 17h16"/>',
			'clock'  => '<circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/>',
			'pin'    => '<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
			'star'   => '<path d="M12 3.5l2.6 5.3 5.9.9-4.2 4.1 1 5.8-5.3-2.8-5.3 2.8 1-5.8-4.2-4.1 5.9-.9z" fill="currentColor" stroke="none"/>',
		);
		return '<svg class="mlb-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ( $paths[ $name ] ?? '' ) . '</svg>';
	}

	protected function stars( int $count ): string {
		$count = max( 0, min( 5, $count ) );
		/* translators: %d: number of stars */
		$label = sprintf( _n( '%d stea din 5', '%d stele din 5', $count, 'mlb-theme' ), $count );
		return '<span class="mlb-stars" role="img" aria-label="' . esc_attr( $label ) . '">' . str_repeat( $this->icon( 'star' ), $count ) . '</span>';
	}
}
