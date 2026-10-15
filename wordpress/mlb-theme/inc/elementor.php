<?php
/**
 * Elementor integration: the "Micu' lu' Bicu" widget category and its widgets.
 *
 * Each site section (hero, menu, locations...) is one widget with all its texts, images,
 * lists and colors exposed as Elementor controls, so the owner edits everything from the panel.
 */

defined( 'ABSPATH' ) || exit;

const MLB_THEME_WIDGETS = array(
	'promo-bar'     => 'Promo_Bar',
	'site-header'   => 'Site_Header',
	'hero'          => 'Hero',
	'features'      => 'Features',
	'section-title' => 'Section_Title',
	'products'      => 'Products',
	'split'         => 'Split',
	'locations'     => 'Locations',
	'reviews'       => 'Reviews',
	'app-band'      => 'App_Band',
	'site-footer'   => 'Site_Footer',
	'cart-bar'      => 'Cart_Bar',
);

add_action(
	'elementor/elements/categories_registered',
	static function ( $elements_manager ): void {
		$elements_manager->add_category(
			'mlb',
			array(
				'title' => "Micu' lu' Bicu",
				'icon'  => 'eicon-flash',
			)
		);
	}
);

add_action(
	'elementor/widgets/register',
	static function ( $widgets_manager ): void {
		require_once MLB_THEME_DIR . '/inc/widgets/class-base.php';
		foreach ( MLB_THEME_WIDGETS as $file => $class ) {
			require_once MLB_THEME_DIR . '/inc/widgets/' . $file . '.php';
			$fqcn = '\\MLB\\Theme\\Widgets\\' . $class;
			$widgets_manager->register( new $fqcn() );
		}
	}
);

/**
 * The theme ships Barlow and Barlow Condensed itself. Listing them as system fonts keeps them
 * in Elementor's font picker without Elementor also loading them from Google Fonts.
 */
add_filter(
	'elementor/fonts/additional_fonts',
	static function ( array $fonts ): array {
		$fonts['Barlow']           = 'system';
		$fonts['Barlow Condensed'] = 'system';
		return $fonts;
	}
);

/**
 * The theme's own stylesheet must also load inside the Elementor editor preview.
 */
add_action(
	'elementor/preview/enqueue_styles',
	static function (): void {
		wp_enqueue_style( 'mlb-fonts' );
		wp_enqueue_style( 'mlb-theme' );
	}
);
