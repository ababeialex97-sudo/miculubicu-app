<?php
/**
 * Site footer: brand, editable columns (phones, links, consumer protection), ANPC pictograms, legal line.
 */

namespace MLB\Theme\Widgets;

use Elementor\Controls_Manager;
use Elementor\Repeater;

defined( 'ABSPATH' ) || exit;

class Site_Footer extends Base {

	public function get_name(): string {
		return 'mlb-site-footer';
	}

	public function get_title(): string {
		return __( 'Subsol site', 'mlb-theme' );
	}

	public function get_icon(): string {
		return 'eicon-footer';
	}

	protected function register_controls(): void {
		$this->content_section( 'brand', __( 'Logo și slogan', 'mlb-theme' ) );
		$this->add_control(
			'logo',
			array(
				'label'   => __( 'Logo (gol = logo-ul din Personalizare)', 'mlb-theme' ),
				'type'    => Controls_Manager::MEDIA,
				'default' => array( 'url' => '' ),
			)
		);
		$this->textarea_control( 'tagline', __( 'Slogan', 'mlb-theme' ), 'De la Bicu, direct pe jar. Vaslui.' );
		$social = new Repeater();
		$social->add_control( 'label', array( 'label' => __( 'Text', 'mlb-theme' ), 'type' => Controls_Manager::TEXT, 'default' => '' ) );
		$social->add_control( 'link', array( 'label' => __( 'Link', 'mlb-theme' ), 'type' => Controls_Manager::URL, 'default' => array( 'url' => '' ) ) );
		$this->add_control(
			'social',
			array(
				'label'       => __( 'Rețele sociale', 'mlb-theme' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $social->get_controls(),
				'title_field' => '{{{ label }}}',
				'default'     => array(
					array( 'label' => 'Facebook', 'link' => array( 'url' => 'https://www.facebook.com/' ) ),
				),
			)
		);
		$this->end_controls_section();

		$this->content_section( 'columns_section', __( 'Coloane', 'mlb-theme' ) );
		$column = new Repeater();
		$column->add_control( 'title', array( 'label' => __( 'Titlu', 'mlb-theme' ), 'type' => Controls_Manager::TEXT, 'default' => '', 'label_block' => true ) );
		$column->add_control( 'content', array( 'label' => __( 'Conținut', 'mlb-theme' ), 'type' => Controls_Manager::WYSIWYG, 'default' => '' ) );
		$this->add_control(
			'columns_list',
			array(
				'label'       => __( 'Coloane', 'mlb-theme' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $column->get_controls(),
				'title_field' => '{{{ title }}}',
				'default'     => array(
					array(
						'title'   => 'Telefoane',
						'content' => '<p>La Spital · <a href="tel:0741245245">0741 245 245</a><br>La Gară · <a href="tel:0735099099">0735 099 099</a><br>Abator · <a href="tel:0787580252">0787 580 252</a><br>London Grill · <a href="tel:0744214637">0744 214 637</a></p>',
					),
					array(
						'title'   => 'Comenzi',
						'content' => '<p><a href="/#meniu">Meniu și prețuri</a><br><a href="/#locatii">Livrare și ridicare</a><br><a href="#">Aplicația</a><br><a href="/termeni-si-conditii/">Termeni și condiții</a><br><a href="/politica-de-confidentialitate/">Politica de confidențialitate</a></p>',
					),
					array(
						'title'   => 'Protecția consumatorului',
						'content' => '<p><a href="https://anpc.ro" rel="noopener">ANPC</a><br><a href="https://ec.europa.eu/consumers/odr" rel="noopener">Soluționarea online a litigiilor (SOL)</a></p>',
					),
				),
			)
		);
		$this->end_controls_section();

		$this->content_section( 'legal', __( 'Pictograme ANPC și date firmă', 'mlb-theme' ) );
		$this->add_control(
			'anpc_note',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Legea cere pictogramele ANPC (SAL) și SOL pe site. Încarcă imaginile oficiale de pe anpc.ro.', 'mlb-theme' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			)
		);
		$this->add_control( 'sal_image', array( 'label' => __( 'Pictogramă ANPC SAL', 'mlb-theme' ), 'type' => Controls_Manager::MEDIA, 'default' => array( 'url' => '' ) ) );
		$this->url_control( 'sal_link', __( 'Link SAL', 'mlb-theme' ), 'https://anpc.ro/ce-este-sal/' );
		$this->add_control( 'sol_image', array( 'label' => __( 'Pictogramă SOL', 'mlb-theme' ), 'type' => Controls_Manager::MEDIA, 'default' => array( 'url' => '' ) ) );
		$this->url_control( 'sol_link', __( 'Link SOL', 'mlb-theme' ), 'https://ec.europa.eu/consumers/odr' );
		$this->text_control( 'company', __( 'Date firmă', 'mlb-theme' ), '[Denumire firmă] · CUI [CUI] · Nr. Reg. Com. [J..]' );
		$this->text_control( 'copyright', __( 'Copyright ({an} = anul curent)', 'mlb-theme' ), "© {an} Micu' lu' Bicu" );
		$this->end_controls_section();

		$this->color_controls(
			array(
				'footer-bg' => __( 'Fundal', 'mlb-theme' ),
				'text'      => __( 'Titluri', 'mlb-theme' ),
				'muted'     => __( 'Text', 'mlb-theme' ),
				'accent'    => __( 'Linkuri', 'mlb-theme' ),
				'line'      => __( 'Linie de sus', 'mlb-theme' ),
			)
		);
		$this->typography_controls(
			array(
				'title' => array( __( 'Titluri coloane', 'mlb-theme' ), '.mlb-footer__title' ),
				'text'  => array( __( 'Text', 'mlb-theme' ), '.mlb-footer__content' ),
			)
		);
	}

	protected function render(): void {
		$s = $this->get_settings_for_display();

		echo '<footer class="mlb-footer mlb-section" id="contact"><div class="mlb-container">';
		echo '<div class="mlb-footer__grid">';

		echo '<div class="mlb-footer__brand">';
		$logo_id = (int) ( $s['logo']['id'] ?? 0 );
		if ( ! $logo_id ) {
			$logo_id = (int) get_theme_mod( 'custom_logo' );
		}
		if ( $logo_id ) {
			echo wp_get_attachment_image( $logo_id, 'thumbnail', false, array( 'class' => 'mlb-footer__logo', 'loading' => 'lazy' ) );
		}
		echo $this->text_el( 'p', 'tagline', 'mlb-footer__tagline', (string) $s['tagline'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$links = '';
		foreach ( (array) $s['social'] as $index => $item ) {
			$open = $this->link_open( 'social-' . $index, $item['link'] ?? null, 'mlb-footer__social-link' );
			if ( '' !== $open && '' !== trim( (string) ( $item['label'] ?? '' ) ) ) {
				$links .= $open . esc_html( (string) ( $item['label'] ?? '' ) ) . '</a>';
			}
		}
		if ( '' !== $links ) {
			echo '<p class="mlb-footer__social">' . $links . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';

		foreach ( (array) $s['columns_list'] as $index => $column ) {
			echo '<div class="mlb-footer__col">';
			echo $this->repeater_text_el( 'h2', 'title', 'columns_list', $index, 'mlb-footer__title', (string) ( $column['title'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<div class="mlb-footer__content">' . wp_kses_post( (string) ( $column['content'] ?? '' ) ) . '</div>';
			echo '</div>';
		}
		echo '</div>';

		echo '<div class="mlb-footer__bottom">';
		$badges = '';
		foreach ( array( 'sal', 'sol' ) as $badge ) {
			$image_id = (int) ( $s[ $badge . '_image' ]['id'] ?? 0 );
			if ( ! $image_id ) {
				continue;
			}
			$open    = $this->link_open( $badge . '_link', $s[ $badge . '_link' ] ?? null, 'mlb-footer__badge' );
			$img     = wp_get_attachment_image( $image_id, 'medium', false, array( 'loading' => 'lazy', 'alt' => 'sal' === $badge ? 'ANPC – Soluționarea alternativă a litigiilor' : 'Soluționarea online a litigiilor' ) );
			$badges .= '' !== $open ? $open . $img . '</a>' : $img;
		}
		if ( '' !== $badges ) {
			echo '<div class="mlb-footer__badges">' . $badges . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '<p class="mlb-footer__legal">';
		echo esc_html( str_replace( '{an}', gmdate( 'Y' ), (string) $s['copyright'] ) );
		if ( '' !== trim( (string) $s['company'] ) ) {
			echo '<span class="mlb-footer__company">' . esc_html( (string) $s['company'] ) . '</span>';
		}
		echo '</p></div>';

		echo '</div></footer>';
	}
}
