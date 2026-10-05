<?php
/**
 * Registers Expo push tokens for the logged-in customer.
 *
 * Each token is its own `_mlb_push_token` user meta row, so a device that switches
 * accounts can be detached from the previous customer with a meta lookup.
 */

namespace MLB\AppApi\Rest;

use MLB\AppApi\Auth;

defined( 'ABSPATH' ) || exit;

class Push_Controller {

	public const META_KEY = '_mlb_push_token';

	private const MAX_TOKENS_PER_USER = 10;

	public function register_routes( string $namespace ): void {
		$args = array(
			'token' => array(
				'type'     => 'string',
				'required' => true,
				'pattern'  => '^(ExponentPushToken|ExpoPushToken)\[[A-Za-z0-9_-]+\]$',
			),
		);

		register_rest_route(
			$namespace,
			'/push-tokens',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'register_token' ),
					'permission_callback' => array( Auth::class, 'require_customer' ),
					'args'                => $args,
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_token' ),
					'permission_callback' => array( Auth::class, 'require_customer' ),
					'args'                => $args,
				),
			)
		);
	}

	/**
	 * @return string[]
	 */
	public static function tokens_for_user( int $user_id ): array {
		return array_values( array_unique( array_map( 'strval', (array) get_user_meta( $user_id, self::META_KEY, false ) ) ) );
	}

	public function register_token( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$token   = (string) $request['token'];

		foreach ( get_users(
			array(
				'meta_key'   => self::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value' => $token, // phpcs:ignore WordPress.DB.SlowDBQuery
				'fields'     => 'ID',
				'exclude'    => array( $user_id ),
			)
		) as $other_user_id ) {
			delete_user_meta( (int) $other_user_id, self::META_KEY, $token );
		}

		$tokens = self::tokens_for_user( $user_id );
		if ( ! in_array( $token, $tokens, true ) ) {
			// Keep only the most recent devices.
			while ( count( $tokens ) >= self::MAX_TOKENS_PER_USER ) {
				delete_user_meta( $user_id, self::META_KEY, array_shift( $tokens ) );
			}
			add_user_meta( $user_id, self::META_KEY, $token );
		}

		return new \WP_REST_Response( array( 'registered' => true ), 201 );
	}

	public function delete_token( \WP_REST_Request $request ) {
		delete_user_meta( get_current_user_id(), self::META_KEY, (string) $request['token'] );

		return rest_ensure_response( array( 'deleted' => true ) );
	}
}
