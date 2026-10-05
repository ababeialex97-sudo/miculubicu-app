<?php
/**
 * Loyalty card rules: stamps per completed order, a reward every N stamps.
 *
 * Kept free of WordPress dependencies so it can be unit tested with plain PHP.
 */

namespace MLB\AppApi;

defined( 'ABSPATH' ) || exit;

class Loyalty_Rules {

	public const DEFAULTS = array(
		'enabled'       => true,
		'required'      => 4,
		'reward_type'   => 'percent',
		'reward_amount' => 10.0,
		'reward_days'   => 60,
	);

	/**
	 * Fills in and clamps stored settings.
	 *
	 * @param mixed $raw
	 * @return array{enabled: bool, required: int, reward_type: string, reward_amount: float, reward_days: int}
	 */
	public static function normalize( $raw ): array {
		$raw = is_array( $raw ) ? $raw : array();

		$type   = in_array( $raw['reward_type'] ?? '', array( 'percent', 'fixed' ), true ) ? $raw['reward_type'] : self::DEFAULTS['reward_type'];
		$amount = isset( $raw['reward_amount'] ) && is_numeric( $raw['reward_amount'] ) ? (float) $raw['reward_amount'] : self::DEFAULTS['reward_amount'];
		$amount = max( 0.0, 'percent' === $type ? min( 100.0, $amount ) : $amount );

		return array(
			'enabled'       => array_key_exists( 'enabled', $raw ) ? (bool) $raw['enabled'] : self::DEFAULTS['enabled'],
			'required'      => max( 1, min( 20, (int) ( $raw['required'] ?? self::DEFAULTS['required'] ) ) ),
			'reward_type'   => $type,
			'reward_amount' => $amount,
			'reward_days'   => max( 1, min( 365, (int) ( $raw['reward_days'] ?? self::DEFAULTS['reward_days'] ) ) ),
		);
	}

	/**
	 * Adds one stamp. When the card fills up it starts over and a reward is earned.
	 *
	 * @return array{stamps: int, reward: bool}
	 */
	public static function add_stamp( int $stamps, int $required ): array {
		$stamps = max( 0, $stamps ) + 1;
		if ( $stamps >= $required ) {
			return array(
				'stamps' => 0,
				'reward' => true,
			);
		}
		return array(
			'stamps' => $stamps,
			'reward' => false,
		);
	}

	/**
	 * "10%" or "15 lei".
	 */
	public static function amount_label( string $type, float $amount ): string {
		$number = rtrim( rtrim( number_format( $amount, 2, ',', '.' ), '0' ), ',' );
		return 'percent' === $type ? $number . '%' : $number . ' lei';
	}
}
