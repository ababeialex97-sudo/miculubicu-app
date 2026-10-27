<?php
/**
 * "Continuă cu Google" / "Continuă cu Apple": turns a verified ID token into a WooCommerce customer.
 *
 * The provider's user ID ("sub") is stored on the account, so a later change of email at the
 * provider still finds the same customer. An existing customer with the same verified email is
 * linked instead of duplicated; staff accounts are never reachable this way.
 */

namespace MLB\AppApi;

defined( 'ABSPATH' ) || exit;

class Social_Login {

	/** Set on accounts created from the app with Google or Apple: they have no password of their own. */
	public const SOCIAL_ONLY_META = '_mlb_social_only';

	private const PROVIDERS = array(
		'google' => array(
			'jwks'    => 'https://www.googleapis.com/oauth2/v3/certs',
			'issuers' => array( 'https://accounts.google.com', 'accounts.google.com' ),
			'meta'    => '_mlb_google_sub',
			'label'   => 'Google',
		),
		'apple'  => array(
			'jwks'    => 'https://appleid.apple.com/auth/keys',
			'issuers' => array( 'https://appleid.apple.com' ),
			'meta'    => '_mlb_apple_sub',
			'label'   => 'Apple',
		),
	);

	private const KEYS_TTL = 6 * HOUR_IN_SECONDS;

	/**
	 * @param array{first_name?: string, last_name?: string} $names Apple only sends the name to the app, once.
	 * @return \WP_User|\WP_Error
	 */
	public static function login( string $provider, string $token, array $names = array() ) {
		$config    = self::PROVIDERS[ $provider ];
		$audiences = 'google' === $provider ? Settings::google_client_ids() : Settings::apple_client_ids();
		if ( empty( $audiences ) ) {
			/* translators: %s: Google or Apple */
			return self::error( 'mlb_social_disabled', sprintf( 'Conectarea cu %s nu este disponibilă.', $config['label'] ), 400 );
		}

		$claims = self::verify( $provider, $token, $audiences );
		if ( null === $claims ) {
			return self::error( 'mlb_social_invalid', 'Conectarea nu a reușit. Încearcă din nou.', 401 );
		}

		$sub  = (string) $claims['sub'];
		$user = self::user_by_sub( $config['meta'], $sub );
		if ( $user ) {
			return self::is_customer( $user ) ? $user : self::staff_error();
		}

		$email = Id_Token::verified_email( $claims );
		if ( null === $email ) {
			return self::error( 'mlb_social_no_email', 'Contul nu are o adresă de email confirmată. Folosește emailul și parola.', 400 );
		}

		$user = get_user_by( 'email', $email );
		if ( $user ) {
			if ( ! self::is_customer( $user ) ) {
				return self::staff_error();
			}
			update_user_meta( $user->ID, $config['meta'], $sub );
			return $user;
		}

		$first_name = sanitize_text_field( (string) ( $names['first_name'] ?? $claims['given_name'] ?? '' ) );
		$last_name  = sanitize_text_field( (string) ( $names['last_name'] ?? $claims['family_name'] ?? '' ) );

		$user_id = wc_create_customer(
			$email,
			'',
			wp_generate_password( 32, true, true ),
			array(
				'first_name' => $first_name,
				'last_name'  => $last_name,
			)
		);
		if ( is_wp_error( $user_id ) ) {
			return self::error( 'mlb_register_failed', wp_strip_all_tags( $user_id->get_error_message() ), 400 );
		}

		update_user_meta( $user_id, $config['meta'], $sub );
		update_user_meta( $user_id, self::SOCIAL_ONLY_META, $provider );

		$customer = new \WC_Customer( $user_id );
		$customer->set_billing_first_name( $first_name );
		$customer->set_billing_last_name( $last_name );
		$customer->set_billing_email( $email );
		$customer->save();

		return get_user_by( 'id', $user_id );
	}

	public static function is_social_only( int $user_id ): bool {
		return '' !== (string) get_user_meta( $user_id, self::SOCIAL_ONLY_META, true );
	}

	/**
	 * Fetches the provider's keys again once when the token is signed with a key we haven't seen:
	 * providers rotate keys every few weeks.
	 *
	 * @param string[] $audiences
	 * @return array<string, mixed>|null
	 */
	private static function verify( string $provider, string $token, array $audiences ): ?array {
		$config = self::PROVIDERS[ $provider ];

		$claims = Id_Token::verify( $token, self::keys( $provider, false ), $config['issuers'], $audiences );
		if ( null === $claims && self::unknown_key( $token, self::keys( $provider, false ) ) ) {
			$claims = Id_Token::verify( $token, self::keys( $provider, true ), $config['issuers'], $audiences );
		}
		return $claims;
	}

	/**
	 * @param array<int, array<string, mixed>> $keys
	 */
	private static function unknown_key( string $token, array $keys ): bool {
		$header = json_decode( (string) base64_decode( strtr( explode( '.', $token )[0], '-_', '+/' ) ), true );
		$kid    = is_array( $header ) ? ( $header['kid'] ?? null ) : null;
		return null !== $kid && ! in_array( $kid, array_column( $keys, 'kid' ), true );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function keys( string $provider, bool $refresh ): array {
		$cache_key = 'mlb_jwks_' . $provider;
		$keys      = get_transient( $cache_key );
		if ( is_array( $keys ) && ! $refresh ) {
			return $keys;
		}

		// A forced refresh at most once a minute, so bad tokens can't make us hammer the provider.
		if ( $refresh ) {
			if ( get_transient( $cache_key . '_refreshed' ) ) {
				return is_array( $keys ) ? $keys : array();
			}
			set_transient( $cache_key . '_refreshed', 1, MINUTE_IN_SECONDS );
		}

		$response = wp_remote_get( self::PROVIDERS[ $provider ]['jwks'], array( 'timeout' => 5 ) );
		$decoded  = is_wp_error( $response ) ? null : json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $decoded ) || empty( $decoded['keys'] ) || ! is_array( $decoded['keys'] ) ) {
			wc_get_logger()->warning( 'Could not fetch ' . $provider . ' sign-in keys.', array( 'source' => 'mlb-app-api' ) );
			return is_array( $keys ) ? $keys : array();
		}

		set_transient( $cache_key, $decoded['keys'], self::KEYS_TTL );
		return $decoded['keys'];
	}

	private static function user_by_sub( string $meta_key, string $sub ): ?\WP_User {
		$users = get_users(
			array(
				'meta_key'   => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value' => $sub, // phpcs:ignore WordPress.DB.SlowDBQuery
				'number'     => 1,
			)
		);
		return $users[0] ?? null;
	}

	private static function is_customer( \WP_User $user ): bool {
		return ! array_diff( (array) $user->roles, array( 'customer', 'subscriber' ) );
	}

	private static function staff_error(): \WP_Error {
		return self::error( 'mlb_staff_account', 'Acest cont nu se poate folosi în aplicație.', 403 );
	}

	private static function error( string $code, string $message, int $status ): \WP_Error {
		return new \WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
