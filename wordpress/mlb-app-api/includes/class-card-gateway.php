<?php
/**
 * "Card la livrare / la ridicare": the customer pays by card to the courier or at the counter.
 *
 * An offline method like cash on delivery, but its own gateway, so GrandChef can map it to its
 * card payment type (the GrandChef plugin maps payment types by gateway). Enabled from
 * WooCommerce › Setări › Plăți; disabled by default.
 */

namespace MLB\AppApi;

defined( 'ABSPATH' ) || exit;

class Card_Gateway extends \WC_Payment_Gateway {

	public const ID = 'mlb_card';

	public function __construct() {
		$this->id                 = self::ID;
		$this->method_title       = 'Card la livrare / la ridicare';
		$this->method_description = 'Clientul plătește cu cardul la curier sau la ridicare (POS). Asociază metoda cu tipul de plată „card” din GrandChef.';
		$this->has_fields         = false;

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = (string) $this->get_option( 'title' );
		$this->description = (string) $this->get_option( 'description' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	public function init_form_fields(): void {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => 'Activează',
				'type'    => 'checkbox',
				'label'   => 'Plata cu cardul la livrare sau la ridicare',
				'default' => 'no',
			),
			'title'       => array(
				'title'   => 'Titlu',
				'type'    => 'text',
				'default' => 'Card la livrare',
			),
			'description' => array(
				'title'   => 'Descriere',
				'type'    => 'textarea',
				'default' => 'Plătești cu cardul la curier sau la ridicare.',
			),
		);
	}

	/**
	 * Nothing is charged online: the order goes straight to processing, like cash on delivery,
	 * which is also what sends it to GrandChef.
	 *
	 * @param int $order_id
	 * @return array<string, string>
	 */
	public function process_payment( $order_id ): array {
		$order = wc_get_order( $order_id );
		$order->update_status( 'processing', 'Plată cu cardul la livrare / la ridicare.' );
		WC()->cart->empty_cart();

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}
}
