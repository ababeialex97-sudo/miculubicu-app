<?php
/**
 * Renders the header, home page and footer templates with sample products into one static HTML file,
 * to look at the theme without WordPress (layout and styles only; Elementor's own CSS is not included).
 *
 * Run: php wordpress/mlb-theme/tests/preview.php /path/to/output.html
 */

require __DIR__ . '/stubs.php';
require MLB_THEME_DIR . '/inc/woocommerce.php';
require MLB_THEME_DIR . '/inc/templates.php';
require MLB_THEME_DIR . '/inc/elementor.php';
require MLB_THEME_DIR . '/inc/widgets/class-base.php';
foreach ( MLB_THEME_WIDGETS as $file => $class ) {
	require_once MLB_THEME_DIR . '/inc/widgets/' . $file . '.php';
}

$GLOBALS['mlb_test_products'] = array(
	new WC_Product( 1, 'Pachet 2 persoane', '<del>72 lei</del> <ins>64 lei</ins>', '', '10 mici, cartofi prăjiți, muștar, pâine', array( 'pachete' ) ),
	new WC_Product( 2, 'Pachet familie', '<del>139 lei</del> <ins>119 lei</ins>', '', '20 mici, 2 cârnăciori, 2 cartofi, sosuri', array( 'pachete' ), array( 'cel-mai-comandat' ) ),
	new WC_Product( 3, 'Pachet grătar acasă', '<del>160 lei</del> <ins>145 lei</ins>', '', '30 mici cruzi, 1 kg ceafă crudă, muștar', array( 'pachete' ) ),
	new WC_Product( 4, 'Mici oaie-porc', '4,50 lei', '85', 'Oaie și porc, usturoi din grădină, piper proaspăt.', array( 'mici' ), array( 'cel-mai-comandat' ) ),
	new WC_Product( 5, 'Mici Angus-Mangaliță', '6,50 lei', '85', 'Black Angus și Mangalița, condimente naturale.', array( 'mici' ) ),
	new WC_Product( 6, 'Ceafă de porc', '24 lei', '180', 'Ceafă de porc pe jar de cireș.', array( 'fripturi' ) ),
	new WC_Product( 7, 'Cârnăciori porc', '6 lei', '65', 'Cârnăciori de porc, rețeta casei.', array( 'fripturi' ) ),
	new WC_Product( 8, 'Crispy pui', '19 lei', '180', 'Pui crocant, pentru cine nu vrea grătar.', array( 'fripturi' ) ),
	new WC_Product( 9, 'Cartofi prăjiți', '9 lei', '150', 'Garnitura clasică.', array( 'garnituri' ) ),
	new WC_Product( 10, 'Muștar', '2 lei', '50', 'Muștar picant.', array( 'sosuri' ) ),
);

function mlb_preview_render( array $blocks ): string {
	$html = '';
	foreach ( $blocks as $block ) {
		$id = isset( $block['settings']['_element_id'] ) ? ' id="' . $block['settings']['_element_id'] . '"' : '';
		$html .= '<div class="elementor-element e-con e-con-full"' . $id . '>';
		foreach ( $block['elements'] as $element ) {
			$class  = '\\MLB\\Theme\\Widgets\\' . MLB_THEME_WIDGETS[ substr( $element['widgetType'], 4 ) ];
			$widget = new $class();
			$widget->test_settings = $element['settings'];
			$html  .= '<div class="elementor-element elementor-widget elementor-widget-' . $element['widgetType'] . '"><div class="elementor-widget-container">' . $widget->render_for_test() . '</div></div>';
		}
		$html .= '</div>';
	}
	return $html;
}

$css  = 'file://' . MLB_THEME_DIR . '/assets/css/';
$page = '<!doctype html><html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
	. '<link rel="stylesheet" href="' . $css . 'fonts.css"><link rel="stylesheet" href="' . $css . 'theme.css"></head><body>'
	. '<div class="mlb-site-header" data-mlb-header>' . mlb_preview_render( mlb_theme_template_header() ) . '</div>'
	. '<main id="continut" class="mlb-site-main">' . mlb_preview_render( mlb_theme_template_home() ) . '</main>'
	. '<div class="mlb-site-footer">' . mlb_preview_render( mlb_theme_template_footer() ) . '</div>'
	. '<script src="file://' . MLB_THEME_DIR . '/assets/js/theme.js"></script></body></html>';

file_put_contents( $argv[1] ?? 'php://stdout', $page );
