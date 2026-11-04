<?php
/**
 * Theme supports, assets, Customizer business details and structured data.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	static function (): void {
		load_theme_textdomain( 'mlb-theme', MLB_THEME_DIR . '/languages' );

		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'custom-logo', array( 'height' => 120, 'width' => 120, 'flex-width' => true, 'flex-height' => true ) );
		add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'woocommerce' );
		add_theme_support( 'wc-product-gallery-lightbox' );
		add_theme_support( 'wc-product-gallery-slider' );

		register_nav_menus(
			array(
				'primary' => __( 'Meniu principal', 'mlb-theme' ),
				'footer'  => __( 'Meniu subsol', 'mlb-theme' ),
			)
		);
	}
);

/**
 * Elementor's full-width templates still call the theme header/footer, so content width is ours to set.
 */
add_action(
	'after_setup_theme',
	static function (): void {
		$GLOBALS['content_width'] = 1240;
	},
	0
);

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		wp_enqueue_style( 'mlb-fonts', MLB_THEME_URL . '/assets/css/fonts.css', array(), MLB_THEME_VERSION );
		wp_enqueue_style( 'mlb-theme', MLB_THEME_URL . '/assets/css/theme.css', array( 'mlb-fonts' ), MLB_THEME_VERSION );
		wp_enqueue_script( 'mlb-theme', MLB_THEME_URL . '/assets/js/theme.js', array(), MLB_THEME_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
	}
);

/**
 * Preload the two fonts every page uses above the fold, so text doesn't swap visibly.
 */
add_action(
	'wp_head',
	static function (): void {
		foreach ( array( 'barlow-condensed-800-latin.woff2', 'barlow-400-latin.woff2' ) as $file ) {
			printf(
				'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
				esc_url( MLB_THEME_URL . '/assets/fonts/' . $file )
			);
		}
	},
	1
);

// Emoji scripts are dead weight on every page.
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );

/**
 * Customizer: business details used by the structured data and the fallback header/footer.
 */
add_action(
	'customize_register',
	static function ( WP_Customize_Manager $wp_customize ): void {
		$wp_customize->add_section(
			'mlb_business',
			array(
				'title'       => __( 'Date restaurant (Google)', 'mlb-theme' ),
				'description' => __( 'Apar în rezultatele Google (program, telefon, adresă). Textele de pe pagini se schimbă din Elementor.', 'mlb-theme' ),
				'priority'    => 30,
			)
		);

		$fields = array(
			'mlb_business_name'    => array( __( 'Nume', 'mlb-theme' ), "Micu' lu' Bicu", 'text' ),
			'mlb_business_phone'   => array( __( 'Telefon principal', 'mlb-theme' ), '0741 245 245', 'text' ),
			'mlb_business_street'  => array( __( 'Adresa principală', 'mlb-theme' ), '', 'text' ),
			'mlb_business_city'    => array( __( 'Oraș', 'mlb-theme' ), 'Vaslui', 'text' ),
			'mlb_business_hours'   => array( __( 'Program (ex: Mo-Su 10:00-22:00)', 'mlb-theme' ), '', 'text' ),
			'mlb_business_cuisine' => array( __( 'Bucătărie', 'mlb-theme' ), 'Românească, grătar', 'text' ),
			'mlb_business_price'   => array( __( 'Interval preț (ex: 20-80 lei)', 'mlb-theme' ), '', 'text' ),
			'mlb_business_social'  => array( __( 'Linkuri sociale (unul pe rând)', 'mlb-theme' ), '', 'textarea' ),
		);

		foreach ( $fields as $id => list( $label, $default, $type ) ) {
			$wp_customize->add_setting(
				$id,
				array(
					'default'           => $default,
					'sanitize_callback' => 'textarea' === $type ? 'sanitize_textarea_field' : 'sanitize_text_field',
				)
			);
			$wp_customize->add_control(
				$id,
				array(
					'label'   => $label,
					'section' => 'mlb_business',
					'type'    => $type,
				)
			);
		}
	}
);

function mlb_theme_business( string $key ): string {
	$defaults = array(
		'name'    => "Micu' lu' Bicu",
		'phone'   => '0741 245 245',
		'city'    => 'Vaslui',
		'cuisine' => 'Românească, grătar',
	);
	return trim( (string) get_theme_mod( 'mlb_business_' . $key, $defaults[ $key ] ?? '' ) );
}

/**
 * Restaurant structured data on the front page (name, phone, address, hours, menu link, ordering link).
 */
add_action(
	'wp_head',
	static function (): void {
		if ( ! is_front_page() ) {
			return;
		}

		$data = array(
			'@context'      => 'https://schema.org',
			'@type'         => 'Restaurant',
			'name'          => mlb_theme_business( 'name' ),
			'url'           => home_url( '/' ),
			'telephone'     => mlb_theme_business( 'phone' ),
			'servesCuisine' => mlb_theme_business( 'cuisine' ),
			'address'       => array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => mlb_theme_business( 'street' ),
				'addressLocality' => mlb_theme_business( 'city' ),
				'addressCountry'  => 'RO',
			),
		);

		$logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			$data['image'] = wp_get_attachment_image_url( $logo_id, 'full' );
		}
		if ( '' !== mlb_theme_business( 'hours' ) ) {
			$data['openingHours'] = array_map( 'trim', explode( ',', mlb_theme_business( 'hours' ) ) );
		}
		if ( '' !== mlb_theme_business( 'price' ) ) {
			$data['priceRange'] = mlb_theme_business( 'price' );
		}
		$social = array_filter( array_map( 'trim', explode( "\n", mlb_theme_business( 'social' ) ) ) );
		if ( $social ) {
			$data['sameAs'] = array_values( array_map( 'esc_url_raw', $social ) );
		}
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$data['hasMenu']          = wc_get_page_permalink( 'shop' );
			$data['acceptsReservations'] = false;
		}

		$data = array_filter( $data, static fn( $value ) => '' !== $value && array() !== $value );
		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "</script>\n";
	}
);

/**
 * Fallback header, used until an Elementor header template is chosen.
 */
function mlb_theme_fallback_header(): void {
	?>
	<header class="mlb-fallback-header">
		<div class="mlb-container mlb-fallback-header__inner">
			<a class="mlb-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php
				if ( has_custom_logo() ) {
					echo wp_get_attachment_image( (int) get_theme_mod( 'custom_logo' ), 'thumbnail', false, array( 'class' => 'mlb-brand__logo', 'alt' => '' ) );
				}
				?>
				<span class="mlb-brand__name"><?php echo esc_html( mlb_theme_business( 'name' ) ); ?></span>
			</a>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => 'nav',
					'container_class' => 'mlb-fallback-header__nav',
					'fallback_cb'    => false,
					'depth'          => 1,
				)
			);
			?>
		</div>
	</header>
	<?php
}

function mlb_theme_fallback_footer(): void {
	?>
	<footer class="mlb-fallback-footer">
		<div class="mlb-container">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'footer',
					'container'      => 'nav',
					'fallback_cb'    => false,
					'depth'          => 1,
				)
			);
			?>
			<p>&copy; <?php echo esc_html( gmdate( 'Y' ) . ' ' . mlb_theme_business( 'name' ) ); ?></p>
		</div>
	</footer>
	<?php
}
