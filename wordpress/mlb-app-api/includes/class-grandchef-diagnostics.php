<?php
/**
 * Writes an order note after each hand-off to GrandChef: how long it took, whether the GrandChef
 * plugin got an order ID back, and the PHP warnings raised on the way (connection refused,
 * timeouts...). The GrandChef plugin itself doesn't say why an order wasn't added.
 */

namespace MLB\AppApi;

defined( 'ABSPATH' ) || exit;

class GrandChef_Diagnostics {

	/** The GrandChef plugin sends on this hook at the default priority (10). */
	private const HOOK = 'woocommerce_order_status_processing';

	private static float $started = 0.0;

	/** @var string[] */
	private static array $warnings = array();

	private static bool $handler_set = false;

	public static function init(): void {
		add_action( self::HOOK, array( self::class, 'before' ), 1 );
		add_action( self::HOOK, array( self::class, 'after' ), PHP_INT_MAX );
	}

	public static function before(): void {
		self::$started  = microtime( true );
		self::$warnings = array();

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- diagnostics only, restored in after().
		set_error_handler(
			static function ( int $errno, string $message ): bool {
				if ( count( self::$warnings ) < 5 ) {
					self::$warnings[] = $message;
				}
				return false; // Let PHP and WordPress handle it as usual.
			}
		);
		self::$handler_set = true;

		// A fatal error or timeout never reaches after(): leave a trace in the log instead.
		register_shutdown_function( array( self::class, 'on_shutdown' ) );
	}

	/**
	 * @param int $order_id
	 */
	public static function after( $order_id ): void {
		if ( self::$handler_set ) {
			restore_error_handler();
			self::$handler_set = false;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$gc_id   = self::meta( $order, '_gc_order_id' );
		$seconds = number_format( microtime( true ) - self::$started, 1 );

		if ( '' !== $gc_id ) {
			$note = sprintf( 'GrandChef: trimisă în %s s, comanda GrandChef #%s.', $seconds, $gc_id );
		} else {
			$details = array();
			foreach ( array( '_gc_order_success', '_added_to_gc' ) as $key ) {
				$value = self::meta( $order, $key );
				if ( '' !== $value ) {
					$details[] = $key . ' = ' . $value;
				}
			}
			$details = array_merge( $details, self::$warnings );
			$note    = sprintf( 'GrandChef: NETRIMISĂ după %s s.', $seconds ) . ( $details ? ' Detalii: ' . implode( ' | ', $details ) : ' Plugin-ul GrandChef nu a raportat nicio eroare.' );
		}

		$order->add_order_note( $note );
		wc_get_logger()->info( 'Order #' . $order->get_id() . ': ' . $note, array( 'source' => 'mlb-grandchef' ) );
		self::$started = 0.0;
	}

	public static function on_shutdown(): void {
		$error = error_get_last();
		if ( self::$started > 0 && $error && in_array( $error['type'], array( E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			wc_get_logger()->error( 'GrandChef hand-off stopped by a fatal error: ' . $error['message'], array( 'source' => 'mlb-grandchef' ) );
		}
	}

	/**
	 * The GrandChef plugin uses update_post_meta(), which with HPOS is not the order's own meta.
	 */
	private static function meta( \WC_Order $order, string $key ): string {
		$value = $order->get_meta( $key );
		if ( '' === $value || null === $value ) {
			$value = get_post_meta( $order->get_id(), $key, true );
		}
		return is_scalar( $value ) ? (string) $value : (string) wp_json_encode( $value );
	}
}
