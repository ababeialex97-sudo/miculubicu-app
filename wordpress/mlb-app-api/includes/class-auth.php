<?php
/**
 * Customer authentication with bearer tokens (JWT, HS256).
 *
 * Tokens carry the user ID and a per-user token version; bumping the version
 * (e.g. on password change) invalidates every token issued before.
 */

namespace MLB\AppApi;

defined( 'ABSPATH' ) || exit;

class Auth {

	public const TOKEN_VERSION_META = '_mlb_token_version';
	public const TOKEN_TTL          = 30 * DAY_IN_SECONDS;

	private const MAX_LOGIN_FAILURES = 5;
	private const LOCKOUT_SECONDS    = 15 * MINUTE_IN_SECONDS;

	public static function issue_token( \WP_User $user ): string {
		$now = time();

		return Jwt::encode(
			array(
				'iss' => get_site_url(),
				'sub' => $user->ID,
				'ver' => self::token_version( $user->ID ),
				'iat' => $now,
				'exp' => $now + (int) apply_filters( 'mlb_app_api_token_ttl', self::TOKEN_TTL ),
			),
			Settings::get_jwt_secret()
		);
	}

	/**
	 * Permission callback for routes that need a logged-in customer.
	 * On success the user becomes the current WordPress user for the request.
	 *
	 * @return true|\WP_Error
	 */
	public static function require_customer( \WP_REST_Request $request ) {
		$user = self::user_from_request( $request );
		if ( ! $user ) {
			return new \WP_Error( 'mlb_unauthorized', 'Trebuie să fii autentificat.', array( 'status' => 401 ) );
		}

		wp_set_current_user( $user->ID );
		return true;
	}

	public static function user_from_request( \WP_REST_Request $request ): ?\WP_User {
		$token = self::bearer_token( $request );
		if ( null === $token ) {
			return null;
		}

		$payload = Jwt::decode( $token, Settings::get_jwt_secret() );
		if ( null === $payload || ( $payload['iss'] ?? '' ) !== get_site_url() ) {
			return null;
		}

		$user = get_user_by( 'id', (int) ( $payload['sub'] ?? 0 ) );
		if ( ! $user || ( $payload['ver'] ?? null ) !== self::token_version( $user->ID ) ) {
			return null;
		}

		return $user;
	}

	public static function revoke_tokens( int $user_id ): void {
		update_user_meta( $user_id, self::TOKEN_VERSION_META, self::token_version( $user_id ) + 1 );
	}

	public static function is_locked_out( string $login ): bool {
		return (int) get_transient( self::lockout_key( $login ) ) >= self::MAX_LOGIN_FAILURES;
	}

	public static function record_failed_login( string $login ): void {
		$key = self::lockout_key( $login );
		set_transient( $key, (int) get_transient( $key ) + 1, self::LOCKOUT_SECONDS );
	}

	public static function clear_failed_logins( string $login ): void {
		delete_transient( self::lockout_key( $login ) );
	}

	private static function token_version( int $user_id ): int {
		return (int) get_user_meta( $user_id, self::TOKEN_VERSION_META, true );
	}

	private static function lockout_key( string $login ): string {
		return 'mlb_login_fail_' . md5( strtolower( trim( $login ) ) );
	}

	/**
	 * Some Apache setups strip the Authorization header, so fall back to the server variables.
	 */
	private static function bearer_token( \WP_REST_Request $request ): ?string {
		$header = (string) $request->get_header( 'authorization' );

		if ( '' === $header ) {
			foreach ( array( 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION' ) as $key ) {
				if ( ! empty( $_SERVER[ $key ] ) ) {
					$header = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
					break;
				}
			}
		}

		if ( ! preg_match( '/^Bearer\s+(\S+)$/i', trim( $header ), $matches ) ) {
			return null;
		}
		return $matches[1];
	}
}
