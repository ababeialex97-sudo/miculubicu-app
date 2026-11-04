<?php
/**
 * Sends push notifications through the Expo Push Service.
 */

namespace MLB\AppApi;

use MLB\AppApi\Rest\Push_Controller;

defined( 'ABSPATH' ) || exit;

class Push {

	private const ENDPOINT = 'https://exp.host/--/api/v2/push/send';

	/**
	 * Tells the customer about a new order status. Failures are logged, never thrown:
	 * a missed notification must not block the status change itself.
	 */
	public static function notify_status( \WC_Order $order, string $status ): void {
		$text = Push_Messages::for_status( $status, (string) $order->get_meta( '_mlb_fulfillment' ), (string) $order->get_order_number(), Delivery_Quote::eta_minutes_left( $order ) );
		if ( null === $text ) {
			return;
		}

		$data = array(
			'orderId' => $order->get_id(),
			'status'  => $status,
		);

		$customer_id = $order->get_customer_id();
		if ( $customer_id > 0 ) {
			self::send_to_user( $customer_id, $text['title'], $text['body'], $data );
			return;
		}

		// Guest orders keep the devices to notify on the order itself.
		$dead = self::send( Guest_Orders::push_tokens( $order ), $text['title'], $text['body'], $data );
		if ( $dead ) {
			Guest_Orders::remove_push_tokens( $order, $dead );
		}
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function send_to_user( int $user_id, string $title, string $body, array $data ): void {
		foreach ( self::send( Push_Controller::tokens_for_user( $user_id ), $title, $body, $data ) as $token ) {
			delete_user_meta( $user_id, Push_Controller::META_KEY, $token );
		}
	}

	/**
	 * Sends one message to each token and returns the tokens Expo reports as no longer registered.
	 *
	 * @param string[]             $tokens
	 * @param array<string, mixed> $data
	 * @return string[]
	 */
	private static function send( array $tokens, string $title, string $body, array $data ): array {
		if ( empty( $tokens ) ) {
			return array();
		}

		$headers = array(
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
		);
		if ( defined( 'MLB_APP_API_EXPO_ACCESS_TOKEN' ) && '' !== MLB_APP_API_EXPO_ACCESS_TOKEN ) {
			$headers['Authorization'] = 'Bearer ' . MLB_APP_API_EXPO_ACCESS_TOKEN;
		}

		$dead = array();
		foreach ( Push_Messages::batches( $tokens, $title, $body, $data ) as $batch ) {
			$response = wp_remote_post(
				self::ENDPOINT,
				array(
					'headers' => $headers,
					'body'    => (string) wp_json_encode( $batch ),
					'timeout' => 5,
				)
			);

			if ( is_wp_error( $response ) ) {
				wc_get_logger()->warning( 'Expo push failed: ' . $response->get_error_message(), array( 'source' => 'mlb-app-api' ) );
				continue;
			}

			$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			$dead    = array_merge( $dead, Push_Messages::dead_tokens( $batch, is_array( $decoded ) ? $decoded : null ) );
		}

		return $dead;
	}
}
