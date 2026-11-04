<?php
/**
 * Site header: logo, navigation, phone, live cart button, mobile menu.
 */

namespace MLB\Theme\Widgets;

use Elementor\Controls_Manager;
use Elementor\Repeater;

defined( 'ABSPATH' ) || exit;

class Site_Header extends Base {

	public function get_name(): string {
		return 'mlb-site-header';
	}

	public function get_title(): string {
		return __( 'Antet site', 'mlb-theme' );
	}

	public function get_icon(): string {
		return 'eicon-header';
	}

	protected function is_dynamic_content(): bool {
		return true; // Cart count and total.
	}

	protected function register_controls(): void {
		$this->content_section( 'brand', __( 'Logo', 'mlb-theme' ) );
		$this->add_control(
			'logo',
			array(
				'label'       => __( 'Logo (gol = logo-ul din Personalizare)', 'mlb-theme' ),
				'type'        => Controls_Manager::MEDIA,
				'default'     => array( 'url' => '' ),
			)
		);
		$this->text_control( 'brand_name', __( 'Nume', 'mlb-theme' ), "Micu' lu' Bicu" );
		$this->text_control( 'brand_tagline', __( 'Slogan', 'mlb-theme' ), 'De la Bicu, direct pe jar' );
		$this->end_controls_section();

		$this->content_section( 'navigation', __( 'Meniu', 'mlb-theme' ) );
		$menus = array( '' => __( 'Linkurile de mai jos', 'mlb-theme' ) );
		foreach ( wp_get_nav_menus() as $menu ) {
			$menus[ (string) $menu->term_id ] = $menu->name;
		}
		$this->add_control(
			'menu_source',
			array(
				'label'   => __( 'Sursa meniului', 'mlb-theme' ),
				'type'    => Controls_Manager::SELECT,
				'options' => $menus,
				'default' => '',
			)
		);
		$links = new Repeater();
		$links->add_control( 'label', array( 'label' => __( 'Text', 'mlb-theme' ), 'type' => Controls_Manager::TEXT, 'default' => '' ) );
		$links->add_control( 'link', array( 'label' => __( 'Link', 'mlb-theme' ), 'type' => Controls_Manager::URL, 'default' => array( 'url' => '#' ) ) );
		$this->add_control(
			'links',
			array(
				'label'       => __( 'Linkuri', 'mlb-theme' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $links->get_controls(),
				'title_field' => '{{{ label }}}',
				'condition'   => array( 'menu_source' => '' ),
				'default'     => array(
					array( 'label' => 'Meniu', 'link' => array( 'url' => '/#meniu' ) ),
					array( 'label' => 'Locații', 'link' => array( 'url' => '/#locatii' ) ),
					array( 'label' => 'Abator', 'link' => array( 'url' => '/#abator' ) ),
					array( 'label' => 'Despre noi', 'link' => array( 'url' => '/#poveste' ) ),
					array( 'label' => 'Hotel & Restaurant', 'link' => array( 'url' => '#' ) ),
					array( 'label' => 'Contact', 'link' => array( 'url' => '#contact' ) ),
				),
			)
		);
		$this->end_controls_section();

		$this->content_section( 'actions', __( 'Telefon și coș', 'mlb-theme' ) );
		$this->text_control( 'phone_label', __( 'Telefon afișat', 'mlb-theme' ), '0741 245 245' );
		$this->text_control( 'phone_number', __( 'Număr de apelat', 'mlb-theme' ), '0741245245' );
		$this->switch_control( 'show_cart', __( 'Arată butonul de coș', 'mlb-theme' ) );
		$this->text_control( 'cart_label', __( 'Text buton coș', 'mlb-theme' ), 'Coș', array( 'condition' => array( 'show_cart' => 'yes' ) ) );
		$this->text_control( 'cta_label', __( 'Text buton (fără coș)', 'mlb-theme' ), 'Comandă online', array( 'condition' => array( 'show_cart' => '' ) ) );
		$this->url_control( 'cta_link', __( 'Link buton (fără coș)', 'mlb-theme' ), '/#meniu' );
		$this->end_controls_section();

		$this->color_controls(
			array(
				'header-bg' => __( 'Fundal', 'mlb-theme' ),
				'text'      => __( 'Text', 'mlb-theme' ),
				'accent'    => __( 'Accent (slogan, iconițe)', 'mlb-theme' ),
				'primary'   => __( 'Buton coș', 'mlb-theme' ),
				'line'      => __( 'Chenare', 'mlb-theme' ),
			)
		);
		$this->typography_controls(
			array(
				'brand' => array( __( 'Nume', 'mlb-theme' ), '.mlb-brand__name' ),
				'nav'   => array( __( 'Meniu', 'mlb-theme' ), '.mlb-menu a' ),
			),
			false
		);
	}

	protected function render(): void {
		$s      = $this->get_settings_for_display();
		$nav_id = 'mlb-nav-' . $this->get_id();

		echo '<header class="mlb-header"><div class="mlb-container mlb-header__inner">';

		// Brand.
		echo '<a class="mlb-brand" href="' . esc_url( home_url( '/' ) ) . '" aria-label="' . esc_attr( (string) $s['brand_name'] . ', ' . __( 'acasă', 'mlb-theme' ) ) . '">';
		$logo_id = (int) ( $s['logo']['id'] ?? 0 );
		if ( ! $logo_id ) {
			$logo_id = (int) get_theme_mod( 'custom_logo' );
		}
		if ( $logo_id ) {
			echo wp_get_attachment_image( $logo_id, 'thumbnail', false, array( 'class' => 'mlb-brand__logo', 'alt' => '', 'loading' => 'eager' ) );
		} elseif ( ! empty( $s['logo']['url'] ) ) {
			echo '<img class="mlb-brand__logo" src="' . esc_url( $s['logo']['url'] ) . '" alt="">';
		}
		echo '<span class="mlb-brand__text"><span class="mlb-brand__name">' . esc_html( (string) $s['brand_name'] ) . '</span>';
		if ( '' !== trim( (string) $s['brand_tagline'] ) ) {
			echo '<span class="mlb-brand__tag">' . esc_html( (string) $s['brand_tagline'] ) . '</span>';
		}
		echo '</span></a>';

		// Navigation.
		echo '<nav class="mlb-header__nav" id="' . esc_attr( $nav_id ) . '" aria-label="' . esc_attr__( 'Meniu principal', 'mlb-theme' ) . '">';
		if ( '' !== (string) $s['menu_source'] ) {
			wp_nav_menu(
				array(
					'menu'        => (int) $s['menu_source'],
					'container'   => false,
					'menu_class'  => 'mlb-menu',
					'depth'       => 1,
					'fallback_cb' => false,
				)
			);
		} else {
			echo '<ul class="mlb-menu">';
			foreach ( (array) $s['links'] as $index => $item ) {
				$open = $this->link_open( 'nav-link-' . $index, $item['link'] ?? null, 'mlb-menu__link' );
				if ( '' !== $open && '' !== trim( (string) ( $item['label'] ?? '' ) ) ) {
					echo '<li>' . $open . esc_html( (string) ( $item['label'] ?? '' ) ) . '</a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			}
			echo '</ul>';
		}
		if ( '' !== trim( (string) $s['phone_number'] ) ) {
			echo '<a class="mlb-btn mlb-btn--ghost mlb-header__phone-mobile" href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', (string) $s['phone_number'] ) ) . '">' . $this->icon( 'phone' ) . esc_html( (string) $s['phone_label'] ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</nav>';

		// Actions.
		echo '<div class="mlb-header__actions">';
		if ( '' !== trim( (string) $s['phone_number'] ) ) {
			echo '<a class="mlb-btn mlb-btn--ghost mlb-header__phone" href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', (string) $s['phone_number'] ) ) . '">' . $this->icon( 'phone' ) . '<span>' . esc_html( (string) $s['phone_label'] ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( 'yes' === $s['show_cart'] && function_exists( 'wc_get_cart_url' ) ) {
			printf(
				'<a class="mlb-btn mlb-btn--primary mlb-cartbtn" href="%1$s">%2$s<span class="mlb-cartbtn__label">%3$s</span><span class="mlb-cartbtn__total">%4$s</span>%5$s</a>',
				esc_url( wc_get_cart_url() ),
				$this->icon( 'cart' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				esc_html( (string) $s['cart_label'] ),
				mlb_theme_cart_total_html(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				mlb_theme_cart_count_html() // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);
		} else {
			echo $this->button( 'cta', (string) ( $s['cta_label'] ?? '' ), $s['cta_link'] ?? null ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '<button type="button" class="mlb-header__toggle" aria-expanded="false" aria-controls="' . esc_attr( $nav_id ) . '" aria-label="' . esc_attr__( 'Deschide meniul', 'mlb-theme' ) . '">' . $this->icon( 'menu' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';

		echo '</div></header>';
	}
}
