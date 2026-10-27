<?php
/**
 * Minimal stand-ins for WordPress, WooCommerce and Elementor, enough to render the theme's widgets
 * outside WordPress. Used by tests/render-test.php and tests/preview.php; never loaded by the theme.
 */

namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'MLB_THEME_DIR', dirname( __DIR__ ) );
	define( 'MLB_THEME_URL', '..' );
	define( 'MLB_THEME_VERSION', 'test' );

	$GLOBALS['mlb_test_theme_mods'] = array();
	$GLOBALS['mlb_test_products']   = array();

	function __( $text, $domain = '' ) { return $text; }
	function esc_html__( $text, $domain = '' ) { return esc_html( $text ); }
	function esc_attr__( $text, $domain = '' ) { return esc_attr( $text ); }
	function esc_html_e( $text, $domain = '' ) { echo esc_html( $text ); }
	function _n( $single, $plural, $number, $domain = '' ) { return 1 === $number ? $single : $plural; }
	function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $url ) { return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' ); }
	function esc_url_raw( $url ) { return (string) $url; }
	function wp_kses_post( $html ) { return (string) $html; }
	function tag_escape( $tag ) { return preg_replace( '/[^a-z0-9]/', '', strtolower( $tag ) ); }
	function wp_strip_all_tags( $text ) { return trim( strip_tags( (string) $text ) ); }
	function wp_trim_words( $text, $num = 55 ) {
		$words = preg_split( '/\s+/', trim( $text ) );
		return count( $words ) > $num ? implode( ' ', array_slice( $words, 0, $num ) ) . '&hellip;' : implode( ' ', $words );
	}
	function home_url( $path = '' ) { return 'https://miculubicu.test' . $path; }
	function admin_url( $path = '' ) { return 'https://miculubicu.test/wp-admin/' . $path; }
	function get_theme_mod( $name, $default = false ) { return $GLOBALS['mlb_test_theme_mods'][ $name ] ?? $default; }
	function wp_get_attachment_image( $id, $size = 'thumbnail', $icon = false, $attr = array() ) { return ''; }
	function wp_get_nav_menus() { return array(); }
	function wp_nav_menu( $args ) {}
	function taxonomy_exists( $tax ) { return true; }
	function get_terms( $args ) {
		$terms = array();
		foreach ( $GLOBALS['mlb_test_products'] as $product ) {
			foreach ( $product->terms as $term ) {
				$terms[ $term->slug ] = $term;
			}
		}
		return array_values( $terms );
	}
	function get_term_meta( $id, $key, $single = false ) { return 0; }
	function get_the_terms( $post_id, $tax ) {
		foreach ( $GLOBALS['mlb_test_products'] as $product ) {
			if ( $product->get_id() === $post_id ) {
				return 'product_cat' === $tax ? $product->terms : array();
			}
		}
		return false;
	}
	function has_term( $term, $tax, $post_id ) {
		foreach ( $GLOBALS['mlb_test_products'] as $product ) {
			if ( $product->get_id() === $post_id ) {
				$slugs = 'product_tag' === $tax ? $product->tags : wp_list_pluck( $product->terms, 'slug' );
				return (bool) array_intersect( (array) $term, $slugs );
			}
		}
		return false;
	}
	function wp_list_pluck( $list, $field ) { return array_map( static fn( $item ) => is_object( $item ) ? $item->$field : $item[ $field ], $list ); }
	function wc_format_weight( $weight ) { return $weight . ' g'; }
	function wc_get_cart_url() { return 'https://miculubicu.test/cos/'; }
	function wc_get_products( $args ) {
		$products = $GLOBALS['mlb_test_products'];
		if ( ! empty( $args['category'] ) ) {
			$products = array_values( array_filter( $products, static fn( $p ) => (bool) array_intersect( $args['category'], wp_list_pluck( $p->terms, 'slug' ) ) ) );
		}
		return array_slice( $products, 0, (int) $args['limit'] );
	}
	function WC() {
		static $wc;
		if ( ! $wc ) {
			$wc          = new stdClass();
			$wc->cart    = new class() {
				public function get_cart_contents_count() { return 2; }
				public function get_cart_subtotal() { return '<span class="woocommerce-Price-amount amount">58,00&nbsp;<span class="woocommerce-Price-currencySymbol">lei</span></span>'; }
			};
			$wc->session = null;
		}
		return $wc;
	}
	function add_action( ...$args ) {}
	function add_filter( ...$args ) {}
	function remove_action( ...$args ) {}
	function did_action( $hook ) { return 0; }
	function wp_generate_uuid4() { return bin2hex( random_bytes( 16 ) ); }

	class WP_Term {
		public int $term_id = 0;
		public function __construct( public string $slug = '', public string $name = '' ) {}
	}

	class WC_Product {
		public array $terms = array();
		public array $tags  = array();
		public function __construct( private int $id, private string $name, private string $price, private string $weight, private string $desc, array $cats, array $tags = array(), private string $type = 'simple' ) {
			$this->terms = array_map( static fn( $slug ) => new WP_Term( $slug, ucfirst( $slug ) ), $cats );
			$this->tags  = $tags;
		}
		public function get_id() { return $this->id; }
		public function get_name() { return $this->name; }
		public function get_permalink() { return 'https://miculubicu.test/produs/' . $this->id; }
		public function get_image_id() { return 0; }
		public function get_weight() { return $this->weight; }
		public function get_short_description() { return $this->desc; }
		public function get_description() { return ''; }
		public function get_price_html() { return $this->price; }
		public function is_purchasable() { return true; }
		public function is_in_stock() { return true; }
		public function is_type( $type ) { return $type === $this->type; }
		public function add_to_cart_url() { return '?add-to-cart=' . $this->id; }
		public function get_sku() { return ''; }
	}
}

namespace Elementor {
	class Controls_Manager {
		const TAB_CONTENT = 'content';
		const TAB_STYLE   = 'style';
		const TEXT        = 'text';
		const TEXTAREA    = 'textarea';
		const WYSIWYG     = 'wysiwyg';
		const URL         = 'url';
		const MEDIA       = 'media';
		const SWITCHER    = 'switcher';
		const NUMBER      = 'number';
		const SELECT      = 'select';
		const SELECT2     = 'select2';
		const REPEATER    = 'repeater';
		const ICONS       = 'icons';
		const COLOR       = 'color';
		const SLIDER      = 'slider';
		const DIMENSIONS  = 'dimensions';
		const CHOOSE      = 'choose';
		const RAW_HTML    = 'raw_html';
	}

	class Group_Control_Typography {
		public static function get_type() { return 'typography'; }
	}

	class Utils {
		public static function get_placeholder_image_src() { return 'placeholder.png'; }
	}

	class Icons_Manager {
		public static function render_icon( $icon, $attrs = array() ) {
			echo '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/></svg>';
		}
	}

	class Plugin {
		public $editor;
		public static function instance() {
			static $plugin;
			if ( ! $plugin ) {
				$plugin         = new self();
				$plugin->editor = new class() {
					public function is_edit_mode() { return false; }
				};
			}
			return $plugin;
		}
	}

	class Repeater {
		private array $controls = array();
		public function add_control( $id, $args ) { $this->controls[ $id ] = $args; }
		public function get_controls() { return $this->controls; }
	}

	/**
	 * Records controls and checks their shape; renders with control defaults merged with given settings.
	 */
	abstract class Widget_Base {
		public array $controls      = array();
		public array $test_settings = array();
		private array $attrs        = array();
		private ?string $section    = null;

		public function __construct() {
			$this->register_controls();
			if ( null !== $this->section ) {
				throw new \LogicException( static::class . ': section ' . $this->section . ' not closed' );
			}
		}

		abstract protected function register_controls();

		public function start_controls_section( $id, $args ) {
			if ( null !== $this->section ) {
				throw new \LogicException( static::class . ": section $id opened inside {$this->section}" );
			}
			$this->section = $id;
		}

		public function end_controls_section() { $this->section = null; }

		public function add_control( $id, $args ) {
			if ( null === $this->section ) {
				throw new \LogicException( static::class . ": control $id outside a section" );
			}
			if ( isset( $this->controls[ $id ] ) ) {
				throw new \LogicException( static::class . ": duplicate control $id" );
			}
			if ( empty( $args['type'] ) ) {
				throw new \LogicException( static::class . ": control $id has no type" );
			}
			$this->controls[ $id ] = $args;
		}

		public function add_responsive_control( $id, $args ) { $this->add_control( $id, $args ); }

		public function add_group_control( $type, $args ) { $this->add_control( $args['name'], array( 'type' => $type ) ); }

		public function get_id() { return 'abc1234'; }

		public function get_settings_for_display() {
			$settings = array();
			foreach ( $this->controls as $id => $control ) {
				$settings[ $id ] = $control['default'] ?? ( 'repeater' === $control['type'] ? array() : '' );
			}
			return array_merge( $settings, $this->test_settings );
		}

		public function add_render_attribute( $key, $attr, $value = null ) {
			$this->attrs[ $key ][ $attr ][] = $value;
		}

		public function add_inline_editing_attributes( $key, $toolbar = 'basic' ) {
			$this->add_render_attribute( $key, 'class', 'elementor-inline-editing' );
		}

		public function get_render_attribute_string( $key ) {
			$out = array();
			foreach ( $this->attrs[ $key ] ?? array() as $attr => $values ) {
				$out[] = $attr . '="' . esc_attr( implode( ' ', $values ) ) . '"';
			}
			return implode( ' ', $out );
		}

		public function add_link_attributes( $key, $link ) {
			$this->add_render_attribute( $key, 'href', $link['url'] );
			if ( ! empty( $link['is_external'] ) ) {
				$this->add_render_attribute( $key, 'target', '_blank' );
			}
		}

		public function get_repeater_setting_key( $setting, $repeater, $index ) { return "$repeater.$index.$setting"; }

		public function render_for_test() {
			ob_start();
			$this->render();
			return ob_get_clean();
		}
	}
}
