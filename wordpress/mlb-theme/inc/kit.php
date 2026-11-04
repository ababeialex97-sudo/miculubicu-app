<?php
/**
 * Brand colors and fonts as Elementor global settings (Site Settings › Global Colors / Fonts).
 *
 * The theme CSS reads the same globals through Elementor's CSS variables, so changing a global
 * color in Elementor recolors the whole site, including the theme's widgets.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Elementor CSS variable names, used as `var(--e-global-color-<id>)` in assets/css/theme.css.
 */
function mlb_theme_kit_colors(): array {
	return array(
		'system' => array(
			array( '_id' => 'primary', 'title' => 'Roșu jar (butoane)', 'color' => '#C8321F' ),
			array( '_id' => 'secondary', 'title' => 'Muștar (accente, prețuri)', 'color' => '#F2B705' ),
			array( '_id' => 'text', 'title' => 'Text', 'color' => '#FFFFFF' ),
			array( '_id' => 'accent', 'title' => 'Text secundar', 'color' => '#D2D2D2' ),
		),
		'custom' => array(
			array( '_id' => 'mlbbg', 'title' => 'Fundal', 'color' => '#232323' ),
			array( '_id' => 'mlbsurface', 'title' => 'Carduri', 'color' => '#2F2F2F' ),
			array( '_id' => 'mlbline', 'title' => 'Chenare', 'color' => '#474747' ),
			array( '_id' => 'mlbdark', 'title' => 'Fundal subsol', 'color' => '#1B1B1B' ),
			array( '_id' => 'mlbonaccent', 'title' => 'Text pe muștar', 'color' => '#141414' ),
			array( '_id' => 'mlbmuted', 'title' => 'Text estompat', 'color' => '#A9A9A9' ),
		),
	);
}

function mlb_theme_kit_typography(): array {
	return array(
		array(
			'_id'                       => 'primary',
			'title'                     => 'Titluri',
			'typography_typography'     => 'custom',
			'typography_font_family'    => 'Barlow Condensed',
			'typography_font_weight'    => '800',
			'typography_text_transform' => 'uppercase',
			'typography_line_height'    => array( 'unit' => 'em', 'size' => 1 ),
		),
		array(
			'_id'                    => 'secondary',
			'title'                  => 'Subtitluri',
			'typography_typography'  => 'custom',
			'typography_font_family' => 'Barlow',
			'typography_font_weight' => '700',
		),
		array(
			'_id'                    => 'text',
			'title'                  => 'Text',
			'typography_typography'  => 'custom',
			'typography_font_family' => 'Barlow',
			'typography_font_weight' => '400',
		),
		array(
			'_id'                    => 'accent',
			'title'                  => 'Butoane',
			'typography_typography'  => 'custom',
			'typography_font_family' => 'Barlow',
			'typography_font_weight' => '700',
		),
	);
}

/**
 * Writes the brand globals into the active Elementor kit. Existing custom colors with other ids are kept.
 */
function mlb_theme_apply_kit(): bool {
	if ( ! mlb_theme_elementor_ready() ) {
		return false;
	}

	$plugin = \Elementor\Plugin::instance();
	$kit    = $plugin->kits_manager->get_active_kit();
	if ( ! $kit || ! $kit->get_id() ) {
		return false;
	}

	$colors   = mlb_theme_kit_colors();
	$settings = (array) $kit->get_settings();

	$ours   = wp_list_pluck( $colors['custom'], '_id' );
	$custom = array_values(
		array_filter(
			(array) ( $settings['custom_colors'] ?? array() ),
			static fn( $color ) => ! in_array( $color['_id'] ?? '', $ours, true )
		)
	);

	$brand = array(
		'system_colors'              => $colors['system'],
		'custom_colors'              => array_merge( $colors['custom'], $custom ),
		'system_typography'          => mlb_theme_kit_typography(),
		'container_width'            => array( 'unit' => 'px', 'size' => 1240 ),
		'body_background_background' => 'classic',
		'body_background_color'      => '#232323',
		'body_color'                 => '#FFFFFF',
		'link_normal_color'          => '#F2B705',
	);

	if ( method_exists( $kit, 'update_settings' ) ) {
		$kit->update_settings( $brand );
	} else {
		update_post_meta( $kit->get_id(), '_elementor_page_settings', array_merge( $settings, $brand ) );
	}

	$plugin->files_manager->clear_cache();
	return true;
}
