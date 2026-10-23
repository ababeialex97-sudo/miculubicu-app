<?php
/**
 * Orders placed without an account.
 *
 * The app gets a random token with the order and keeps it on the device; only a hash is stored
 * on the order. The token lets that device read the order's status, receive push notifications
 * and, after logging in, move the order to the account.
 */

namespace MLB\AppApi;

defined( 'ABSPATH' ) || exit;

class Guest_Orders {

	public const TOKEN_HEADER   = 'X-MLB-Order-Token';
	public const TOKEN_META     = '_mlb_guest_token_hash';
	public const PUSH_META      = '_mlb_push_tokens';
	public const MAX_PER_DEVICE = 20;

	private const MAX_PUSH_TOKENS = 3;

	/** Orders one network address may place without an account in RATE_WINDOW seconds. */
	private const RATE_LIMIT  = 6;
	private const RATE_WINDOW = HOUR_IN_SECONDS;

	/**
	 * A new token for the order; any earlier token stops working.
	 */
	public static function issue_token( \WC_Order $order ): string {
		$token = bin2hex( random_bytes( 24 ) );
		$order->update_meta_data( self::TOKEN_META, hash( 'sha256', $token ) );
		$order->save();

		return $token;
	}

	/**
	 * The guest order the token belongs to, or null. Orders already moved to an account are not
	 * returned: from then on the account's own token is needed.
	 */
	public static function find( int $order_id, string $token ): ?\WC_Order {
		if ( $order_id <= 0 || '' === $token ) {
			return null;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order || $order->get_customer_id() > 0 ) {
			return null;
		}

		$hash = (string) $order->get_meta( self::TOKEN_META );
		if ( '' === $hash || ! hash_equals( $hash, hash( 'sha256', $token ) ) ) {
			return null;
		}

		return $order;
	}

	public static function add_push_token( \WC_Order $order, string $token ): void {
		$tokens = self::push_tokens( $order );
		if ( in_array( $token, $tokens, true ) ) {
			return;
		}

		$tokens[] = $token;
		$order->update_meta_data( self::PUSH_META, array_slice( $tokens, -self::MAX_PUSH_TOKENS ) );
		$order->save();
	}

	/**
	 * @return string[]
	 */
	public static function push_tokens( \WC_Order $order ): array {
		return array_values( array_map( 'strval', (array) ( $order->get_meta( self::PUSH_META ) ?: array() ) ) );
	}

	/**
	 * @param string[] $tokens
	 */
	public static function remove_push_tokens( \WC_Order $order, array $tokens ): void {
		$order->update_meta_data( self::PUSH_META, array_values( array_diff( self::push_tokens( $order ), $tokens ) ) );
		$order->save();
	}

	/**
	 * The order now belongs to the customer: their account receives its notifications.
	 */
	public static function attach_to_customer( \WC_Order $order, int $customer_id ): void {
		$order->set_customer_id( $customer_id );
		$order->delete_meta_data( self::TOKEN_META );
		$order->delete_meta_data( self::PUSH_META );
		$order->add_order_note( 'Comanda fără cont a fost mutată în contul clientului, din aplicație.' );
		$order->save();
	}

	public static function is_rate_limited(): bool {
		return (int) get_transient( self::rate_key() ) >= self::RATE_LIMIT;
	}

	public static function record_order(): void {
		$key = self::rate_key();
		set_transient( $key, (int) get_transient( $key ) + 1, self::RATE_WINDOW );
	}

	/**
	 * The site is behind Cloudflare, which passes the visitor's address in its own header.
	 */
	private static function rate_key(): string {
		$ip = '';
		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'REMOTE_ADDR' ) as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				break;
			}
		}
		return 'mlb_guest_orders_' . md5( $ip );
	}
}
