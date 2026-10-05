<?php
/**
 * Loyalty card: every completed app order adds a stamp; a full card turns into a
 * personal one-time coupon (see Coupons), announced with a push notification.
 */

namespace MLB\AppApi;

defined( 'ABSPATH' ) || exit;

class Loyalty {

	public const OPTION        = 'mlb_app_api_loyalty';
	public const STAMPS_META   = '_mlb_loyalty_stamps';
	public const STAMPED_META  = '_mlb_loyalty_stamped';

	public static function init(): void {
		add_action( 'woocommerce_order_status_completed', array( self::class, 'stamp' ), 30, 2 );
	}

	/**
	 * @return array{enabled: bool, required: int, reward_type: string, reward_amount: float, reward_days: int}
	 */
	public static function settings(): array {
		return Loyalty_Rules::normalize( get_option( self::OPTION ) );
	}

	public static function stamps( int $customer_id ): int {
		return (int) get_user_meta( $customer_id, self::STAMPS_META, true );
	}

	/**
	 * Adds a stamp for a completed app order, once per order.
	 */
	public static function stamp( int $order_id, \WC_Order $order ): void {
		$settings    = self::settings();
		$customer_id = $order->get_customer_id();
		if ( ! $settings['enabled'] || $customer_id <= 0 || 'app' !== $order->get_meta( '_mlb_source' ) || $order->get_meta( self::STAMPED_META ) ) {
			return;
		}

		$order->update_meta_data( self::STAMPED_META, gmdate( DATE_ATOM ) );
		$order->save();

		$result = Loyalty_Rules::add_stamp( self::stamps( $customer_id ), $settings['required'] );
		update_user_meta( $customer_id, self::STAMPS_META, $result['stamps'] );

		if ( $result['reward'] ) {
			$coupon = self::create_reward( $customer_id, $settings );
			if ( $coupon ) {
				Push::send_to_user(
					$customer_id,
					'Ai câștigat o reducere!',
					sprintf( 'Cardul de fidelitate e plin: ai %s reducere la următoarea comandă.', Loyalty_Rules::amount_label( $settings['reward_type'], $settings['reward_amount'] ) ),
					array( 'screen' => 'fidelitate' )
				);
			}
		}
	}

	/**
	 * A one-time coupon only this customer can use, valid for the configured number of days.
	 *
	 * @param array{reward_type: string, reward_amount: float, reward_days: int} $settings
	 */
	public static function create_reward( int $customer_id, array $settings ): ?\WC_Coupon {
		$user = get_user_by( 'id', $customer_id );
		if ( ! $user ) {
			return null;
		}

		do {
			$code = 'bicu-' . strtolower( wp_generate_password( 6, false, false ) );
		} while ( wc_get_coupon_id_by_code( $code ) );

		$coupon = new \WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_description( 'Recompensă card de fidelitate' );
		$coupon->set_discount_type( 'percent' === $settings['reward_type'] ? 'percent' : 'fixed_cart' );
		$coupon->set_amount( $settings['reward_amount'] );
		$coupon->set_usage_limit( 1 );
		$coupon->set_usage_limit_per_user( 1 );
		$coupon->set_email_restrictions( array( $user->user_email ) );
		$coupon->set_date_expires( time() + $settings['reward_days'] * DAY_IN_SECONDS );
		$coupon->update_meta_data( Coupons::CUSTOMER_META, (string) $customer_id );
		$coupon->save();

		return $coupon->get_id() ? $coupon : null;
	}
}
