<?php
/**
 * Renders every widget with its defaults and every starting template, outside WordPress.
 * Fails on PHP warnings, unknown widgets, settings that match no control, and empty output.
 *
 * Run: php wordpress/mlb-theme/tests/render-test.php
 */

require __DIR__ . '/stubs.php';
require MLB_THEME_DIR . '/inc/woocommerce.php';
require MLB_THEME_DIR . '/inc/templates.php';
require MLB_THEME_DIR . '/inc/elementor.php';
require MLB_THEME_DIR . '/inc/widgets/class-base.php';
foreach ( MLB_THEME_WIDGETS as $file => $class ) {
	require_once MLB_THEME_DIR . '/inc/widgets/' . $file . '.php';
}

set_error_handler(
	static function ( $severity, $message, $file, $line ) {
		throw new ErrorException( $message, 0, $severity, $file, $line );
	}
);

$GLOBALS['mlb_test_products'] = array(
	new WC_Product( 1, 'Pachet familie', '<del>139 lei</del> <ins>119 lei</ins>', '', '20 mici', array( 'pachete' ), array( 'cel-mai-comandat' ) ),
	new WC_Product( 2, 'Mici oaie-porc', '4,50 lei', '85', 'Oaie și porc.', array( 'mici' ), array( 'cel-mai-comandat' ) ),
	new WC_Product( 3, 'Ceafă', '24 lei', '180', 'Ceafă.', array( 'fripturi' ) ),
	new WC_Product( 4, 'Platou', '90 lei', '', 'Alege.', array( 'fripturi' ), array(), 'variable' ),
);

$failures = 0;
function check( string $name, bool $ok, string $detail = '' ): void {
	global $failures;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . ( $ok || '' === $detail ? '' : ' – ' . $detail ) . PHP_EOL;
	$failures += $ok ? 0 : 1;
}

function widget_for( string $type ) {
	$key = substr( $type, 4 );
	if ( 0 !== strpos( $type, 'mlb-' ) || ! isset( MLB_THEME_WIDGETS[ $key ] ) ) {
		return null;
	}
	$class = '\\MLB\\Theme\\Widgets\\' . MLB_THEME_WIDGETS[ $key ];
	return new $class();
}

// Every widget renders with its defaults.
foreach ( MLB_THEME_WIDGETS as $key => $class ) {
	try {
		$widget = widget_for( 'mlb-' . $key );
		$html   = $widget->render_for_test();
		check( "widget $key renders", '' !== trim( $html ) );
		check( "widget $key name", 'mlb-' . $key === $widget->get_name() );
	} catch ( Throwable $e ) {
		check( "widget $key renders", false, $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine() );
	}
}

// Templates only use known widgets and existing controls.
$templates = array(
	'header' => mlb_theme_template_header(),
	'footer' => mlb_theme_template_footer(),
	'home'   => mlb_theme_template_home(),
);
foreach ( $templates as $name => $blocks ) {
	$ids = array();
	foreach ( $blocks as $block ) {
		$ids[] = $block['id'];
		check( "$name: top level is a container", 'container' === $block['elType'] );
		foreach ( $block['elements'] as $element ) {
			$ids[]  = $element['id'];
			$widget = widget_for( $element['widgetType'] );
			check( "$name: {$element['widgetType']} exists", null !== $widget );
			if ( ! $widget ) {
				continue;
			}
			foreach ( array_keys( $element['settings'] ) as $setting ) {
				$base = preg_replace( '/_(tablet|mobile)$/', '', $setting );
				check( "$name: {$element['widgetType']}.$setting is a control", isset( $widget->controls[ $base ] ) );
			}
			try {
				$widget->test_settings = $element['settings'];
				check( "$name: {$element['widgetType']} renders", '' !== trim( $widget->render_for_test() ) );
			} catch ( Throwable $e ) {
				check( "$name: {$element['widgetType']} renders", false, $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine() );
			}
		}
	}
	check( "$name: element ids are unique", count( $ids ) === count( array_unique( $ids ) ) );
	check( "$name: element ids look like Elementor ids", ! array_filter( $ids, static fn( $id ) => ! preg_match( '/^[0-9a-f]{7}$/', $id ) ) );
}

// Menu excludes meal deals but still fills its limit from the other categories.
$menu = widget_for( 'mlb-products' );
$menu->test_settings = array( 'exclude_categories' => array( 'pachete' ), 'limit' => 3 );
$html = $menu->render_for_test();
check( 'menu excludes pachete', false === strpos( $html, 'Pachet familie' ) );
check( 'menu fills its limit', 3 === substr_count( $html, 'class="mlb-product"' ) );
check( 'badge on tagged product', false !== strpos( $html, 'Cel mai comandat' ) );
check( 'simple product adds by ajax', false !== strpos( $html, 'ajax_add_to_cart' ) && false !== strpos( $html, 'data-product_id="2"' ) );
check( 'variable product opens its page', false !== strpos( $html, 'mlb-add--choose' ) );
check( 'category tabs', false !== strpos( $html, 'data-filter="mici"' ) && false === strpos( $html, 'data-filter="pachete"' ) );

$deals = widget_for( 'mlb-products' );
$deals->test_settings = array( 'categories' => array( 'pachete' ), 'card_style' => 'large' );
$html = $deals->render_for_test();
check( 'deals show old price struck through', false !== strpos( $html, '<del>139 lei</del>' ) );
check( 'deals use text button', false !== strpos( $html, '>Adaugă</a>' ) );

// Cart count and total spans keep the class WooCommerce fragments replace.
check( 'cart count fragment markup', 1 === preg_match( '/^<span class="mlb-cart-count" data-count="2">2<\/span>$/', mlb_theme_cart_count_html() ) );
check( 'cart total fragment markup', 0 === strpos( mlb_theme_cart_total_html(), '<span class="mlb-cart-total">' ) );

echo PHP_EOL . ( $failures ? "$failures failure(s)" : 'All checks passed' ) . PHP_EOL;
exit( $failures ? 1 : 0 );
