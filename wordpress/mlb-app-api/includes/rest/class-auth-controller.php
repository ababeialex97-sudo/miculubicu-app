<?php
/**
 * Customer accounts: register, login, password reset, profile.
 *
 * Accounts are regular WooCommerce customers, shared with the website.
 */

namespace MLB\AppApi\Rest;

use MLB\AppApi\Auth;
use MLB\AppApi\Formatter;

defined( 'ABSPATH' ) || exit;

class Auth_Controller {

	public function register_routes( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/auth/register',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'register' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'email'      => array(
						'type'     => 'string',
						'format'   => 'email',
						'required' => true,
					),
					'password'   => array(
						'type'      => 'string',
						'required'  => true,
						'minLength' => 8,
					),
					'first_name' => array(
						'type'      => 'string',
						'required'  => true,
						'minLength' => 1,
						'maxLength' => 100,
					),
					'last_name'  => array(
						'type'      => 'string',
						'default'   => '',
						'maxLength' => 100,
					),
					'phone'      => array(
						'type'      => 'string',
						'required'  => true,
						'minLength' => 6,
						'maxLength' => 30,
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/auth/login',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'login' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'email'    => array(
						'type'     => 'string',
						'required' => true,
					),
					'password' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/auth/password-reset',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'password_reset' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'email' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/me',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_me' ),
					'permission_callback' => array( Auth::class, 'require_customer' ),
				),
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $this, 'update_me' ),
					'permission_callback' => array( Auth::class, 'require_customer' ),
					'args'                => array(
						'first_name' => array(
							'type'      => 'string',
							'maxLength' => 100,
						),
						'last_name'  => array(
							'type'      => 'string',
							'maxLength' => 100,
						),
						'phone'      => array(
							'type'      => 'string',
							'maxLength' => 30,
						),
						'address'    => array(
							'type'       => 'object',
							'properties' => array(
								'address_1' => array(
									'type'      => 'string',
									'maxLength' => 200,
								),
								'address_2' => array(
									'type'      => 'string',
									'maxLength' => 200,
								),
								'city'      => array(
									'type'      => 'string',
									'maxLength' => 100,
								),
							),
						),
					),
				),
			)
		);
	}

	public function register( \WP_REST_Request $request ) {
		$email = sanitize_email( (string) $request['email'] );
		if ( email_exists( $email ) ) {
			return new \WP_Error( 'mlb_email_exists', 'Există deja un cont cu această adresă de email.', array( 'status' => 409 ) );
		}

		$user_id = wc_create_customer(
			$email,
			'',
			(string) $request['password'],
			array(
				'first_name' => sanitize_text_field( (string) $request['first_name'] ),
				'last_name'  => sanitize_text_field( (string) $request['last_name'] ),
			)
		);
		if ( is_wp_error( $user_id ) ) {
			return new \WP_Error( 'mlb_register_failed', wp_strip_all_tags( $user_id->get_error_message() ), array( 'status' => 400 ) );
		}

		$customer = new \WC_Customer( $user_id );
		$customer->set_billing_first_name( $customer->get_first_name() );
		$customer->set_billing_last_name( $customer->get_last_name() );
		$customer->set_billing_email( $email );
		$customer->set_billing_phone( sanitize_text_field( (string) $request['phone'] ) );
		$customer->save();

		$user = get_user_by( 'id', $user_id );

		return new \WP_REST_Response( $this->session_payload( $user, $customer ), 201 );
	}

	public function login( \WP_REST_Request $request ) {
		$login = trim( (string) $request['email'] );

		if ( Auth::is_locked_out( $login ) ) {
			return new \WP_Error( 'mlb_too_many_attempts', 'Prea multe încercări. Încearcă din nou peste 15 minute.', array( 'status' => 429 ) );
		}

		$user = wp_authenticate( $login, (string) $request['password'] );
		if ( is_wp_error( $user ) ) {
			Auth::record_failed_login( $login );
			return new \WP_Error( 'mlb_invalid_credentials', 'Email sau parolă greșită.', array( 'status' => 401 ) );
		}

		Auth::clear_failed_logins( $login );

		return rest_ensure_response( $this->session_payload( $user, new \WC_Customer( $user->ID ) ) );
	}

	/**
	 * Always answers the same way so the endpoint cannot be used to discover accounts.
	 */
	public function password_reset( \WP_REST_Request $request ) {
		$login = trim( (string) $request['email'] );
		if ( '' !== $login && get_user_by( 'email', $login ) ) {
			retrieve_password( $login );
		}

		return rest_ensure_response(
			array(
				'message' => 'Dacă există un cont cu această adresă, vei primi un email pentru resetarea parolei.',
			)
		);
	}

	public function get_me() {
		return rest_ensure_response( Formatter::customer( new \WC_Customer( get_current_user_id() ) ) );
	}

	public function update_me( \WP_REST_Request $request ) {
		$customer = new \WC_Customer( get_current_user_id() );

		if ( null !== $request['first_name'] ) {
			$customer->set_first_name( sanitize_text_field( (string) $request['first_name'] ) );
			$customer->set_billing_first_name( $customer->get_first_name() );
		}
		if ( null !== $request['last_name'] ) {
			$customer->set_last_name( sanitize_text_field( (string) $request['last_name'] ) );
			$customer->set_billing_last_name( $customer->get_last_name() );
		}
		if ( null !== $request['phone'] ) {
			$customer->set_billing_phone( sanitize_text_field( (string) $request['phone'] ) );
		}
		if ( is_array( $request['address'] ) ) {
			$address = $request['address'];
			if ( isset( $address['address_1'] ) ) {
				$customer->set_shipping_address_1( sanitize_text_field( $address['address_1'] ) );
			}
			if ( isset( $address['address_2'] ) ) {
				$customer->set_shipping_address_2( sanitize_text_field( $address['address_2'] ) );
			}
			if ( isset( $address['city'] ) ) {
				$customer->set_shipping_city( sanitize_text_field( $address['city'] ) );
			}
		}

		$customer->save();

		return rest_ensure_response( Formatter::customer( $customer ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function session_payload( \WP_User $user, \WC_Customer $customer ): array {
		return array(
			'token'    => Auth::issue_token( $user ),
			'customer' => Formatter::customer( $customer ),
		);
	}
}
