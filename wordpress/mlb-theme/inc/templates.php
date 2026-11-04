<?php
/**
 * Starting content for the header, footer and home page, as Elementor element trees.
 *
 * Widgets fall back to their own defaults (the approved design) for every setting not given here.
 */

defined( 'ABSPATH' ) || exit;

function mlb_theme_element_id(): string {
	return substr( md5( wp_generate_uuid4() ), 0, 7 );
}

/**
 * One full-width, zero-padding container holding one theme widget.
 *
 * @param array<string, mixed> $settings Widget settings that differ from the widget defaults.
 */
function mlb_theme_block( string $widget, array $settings = array(), string $anchor = '' ): array {
	$container = array(
		'content_width' => 'full',
		'padding'       => array( 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ),
		'flex_gap'      => array( 'unit' => 'px', 'size' => 0, 'column' => '0', 'row' => '0', 'isLinked' => true ),
	);
	if ( '' !== $anchor ) {
		$container['_element_id'] = $anchor;
	}

	return array(
		'id'       => mlb_theme_element_id(),
		'elType'   => 'container',
		'isInner'  => false,
		'settings' => $container,
		'elements' => array(
			array(
				'id'         => mlb_theme_element_id(),
				'elType'     => 'widget',
				'widgetType' => 'mlb-' . $widget,
				'settings'   => $settings,
				'elements'   => array(),
			),
		),
	);
}

/**
 * @return array<int, array<string, mixed>>
 */
function mlb_theme_template_header(): array {
	return array(
		mlb_theme_block( 'promo-bar' ),
		mlb_theme_block( 'site-header' ),
	);
}

/**
 * @return array<int, array<string, mixed>>
 */
function mlb_theme_template_footer(): array {
	return array(
		mlb_theme_block( 'site-footer' ),
		mlb_theme_block( 'cart-bar' ),
	);
}

/**
 * @return array<int, array<string, mixed>>
 */
function mlb_theme_template_home(): array {
	$item = static fn( string $title, string $subtitle ): array => array(
		'_id'      => mlb_theme_element_id(),
		'title'    => $title,
		'subtitle' => $subtitle,
	);

	return array(
		mlb_theme_block( 'hero', array(), 'acasa' ),
		mlb_theme_block( 'features' ),
		mlb_theme_block(
			'products',
			array(
				'eyebrow'          => 'PACHETE',
				'title'            => 'Comandă pentru toată lumea',
				'categories'       => array( 'pachete' ),
				'limit'            => 3,
				'show_tabs'        => '',
				'show_weight'      => '',
				'card_style'       => 'large',
				'columns'          => '3',
				'columns_tablet'   => '2',
				'columns_mobile'   => '1',
				'badge_tag'        => 'cel-mai-comandat',
				'badge_text'       => 'Cel mai vândut',
			),
			'pachete'
		),
		mlb_theme_block(
			'products',
			array(
				'exclude_categories' => array( 'pachete' ),
				'limit'              => 8,
			),
			'meniu'
		),
		mlb_theme_block(
			'split',
			array(
				'boxed'        => 'yes',
				'items'        => array(
					$item( 'Mici cruzi', 'oaie-porc · 100 g' ),
					$item( 'Ceafă crudă', 'porc · 200 g' ),
					$item( 'Cârnăciori cruzi', 'porc · 70 g' ),
					$item( 'Pastramă de oaie', 'abator propriu' ),
				),
				'button_label' => 'Comandă produse crude',
				'button_link'  => array( 'url' => '#meniu' ),
			),
			'abator'
		),
		mlb_theme_block( 'locations', array(), 'locatii' ),
		mlb_theme_block( 'reviews', array(), 'recenzii' ),
		mlb_theme_block( 'app-band', array(), 'aplicatie' ),
		mlb_theme_block(
			'split',
			array(
				'image_side'   => 'right',
				'boxed'        => '',
				'eyebrow'      => 'POVESTEA NOASTRĂ',
				'title'        => 'Micii nu se mănâncă singuri. Se fac, se împart și se povestesc.',
				'text'         => '<p>Micu\' lu\' Bicu e o promisiune: mici făcuți cinstit, ca odinioară. Avem abatorul nostru, ca să știm de unde vine fiecare bucată de carne, iar fiecare mic are 100 de grame crud.</p>',
				'button_label' => "Hotel & Restaurant London",
				'button_link'  => array( 'url' => '#' ),
				'button_style' => 'link',
			),
			'poveste'
		),
	);
}
