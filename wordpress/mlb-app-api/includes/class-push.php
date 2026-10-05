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
		$customer_id = $order->get_customer_id();
		if ( $customer_id <= 0 ) {
			return;
		}

		$text = Push_Messages::for_status( $status, (string) $order->get_meta( '_mlb_fulfillment' ), (string) $order->get_order_number() );
		if ( null === $text ) {
			return;
		}

		self::send_to_user(
			$customer_id,
			$text['title'],
			$text['body'],
			array(
				'orderId' => $order->get_id(),
				'status'  => $status,
			)
		);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function send_to_user( int $user_id, string $title, string $body, array $data ): void {
		$tokens = Push_Controller::tokens_for_user( $user_id );
		if ( empty( $tokens ) ) {
			return;
		}

		$headers = array(
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
		);
		if ( defined( 'MLB_APP_API_EXPO_ACCESS_TOKEN' ) && '' !== MLB_APP_API_EXPO_ACCESS_TOKEN ) {
			$headers['Authorization'] = 'Bearer ' . MLB_APP_API_EXPO_ACCESS_TOKEN;
		}

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
			foreach ( Push_Messages::dead_tokens( $batch, is_array( $decoded ) ? $decoded : null ) as $token ) {
				delete_user_meta( $user_id, Push_Controller::META_KEY, $token );
			}
		}
	}
}
