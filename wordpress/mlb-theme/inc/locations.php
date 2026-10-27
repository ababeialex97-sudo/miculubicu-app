<?php
/**
 * Header and footer come from Elementor templates, so they are edited in Elementor like any page.
 *
 * With Elementor Pro, its Theme Builder conditions win. With the free Elementor, the templates
 * chosen under Appearance › Micu' lu' Bicu (theme mods below) are used.
 */

defined( 'ABSPATH' ) || exit;

const MLB_THEME_MOD_HEADER = 'mlb_header_template';
const MLB_THEME_MOD_FOOTER = 'mlb_footer_template';

add_action(
	'elementor/theme/register_locations',
	static function ( $manager ): void {
		$manager->register_location( 'header' );
		$manager->register_location( 'footer' );
	}
);

function mlb_theme_elementor_ready(): bool {
	return did_action( 'elementor/loaded' ) && class_exists( '\Elementor\Plugin' );
}

/**
 * Prints the header or footer. Returns false when nothing was printed, so the caller can fall back.
 */
function mlb_theme_do_location( string $location ): bool {
	if ( function_exists( 'elementor_theme_do_location' ) && elementor_theme_do_location( $location ) ) {
		return true;
	}

	if ( ! mlb_theme_elementor_ready() ) {
		return false;
	}

	$mod         = 'header' === $location ? MLB_THEME_MOD_HEADER : MLB_THEME_MOD_FOOTER;
	$template_id = (int) get_theme_mod( $mod, 0 );
	if ( $template_id <= 0 || 'publish' !== get_post_status( $template_id ) ) {
		return false;
	}

	$html = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $template_id, true );
	if ( '' === trim( (string) $html ) ) {
		return false;
	}

	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor output.
	return true;
}

/**
 * Elementor needs the header/footer template CSS in <head> to avoid a flash of unstyled header.
 */
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		if ( ! mlb_theme_elementor_ready() || ! class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
			return;
		}
		foreach ( array( MLB_THEME_MOD_HEADER, MLB_THEME_MOD_FOOTER ) as $mod ) {
			$template_id = (int) get_theme_mod( $mod, 0 );
			if ( $template_id > 0 ) {
				\Elementor\Core\Files\CSS\Post::create( $template_id )->enqueue();
			}
		}
	},
	20
);
