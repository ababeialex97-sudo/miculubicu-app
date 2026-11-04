<?php
/**
 * "Bucătărie" accounts: staff who only see the "Comenzi aplicație" panel, optionally for one
 * location. They can't open WooCommerce, products, pages, plugins or settings.
 */

namespace MLB\AppApi\Admin;

use MLB\AppApi\Settings;

defined( 'ABSPATH' ) || exit;

class Kitchen_Role {

	public const ROLE         = 'mlb_kitchen';
	public const CAP          = 'mlb_manage_app_orders';
	public const LOCATION_META = 'mlb_kitchen_location';

	/** Bump when the role or the capabilities below change. */
	private const VERSION        = '1';
	private const VERSION_OPTION = 'mlb_app_api_roles_version';

	public static function init(): void {
		add_action( 'init', array( self::class, 'ensure_roles' ) );
		add_filter( 'woocommerce_prevent_admin_access', array( self::class, 'allow_admin' ) );
		add_filter( 'login_redirect', array( self::class, 'login_redirect' ), 10, 3 );
		add_filter( 'woocommerce_login_redirect', array( self::class, 'woocommerce_login_redirect' ), 10, 2 );
		add_action( 'admin_menu', array( self::class, 'trim_menu' ), 999 );
		add_action( 'load-index.php', array( self::class, 'skip_dashboard' ) );
		add_action( 'show_user_profile', array( self::class, 'profile_field' ) );
		add_action( 'edit_user_profile', array( self::class, 'profile_field' ) );
		add_action( 'personal_options_update', array( self::class, 'save_profile_field' ) );
		add_action( 'edit_user_profile_update', array( self::class, 'save_profile_field' ) );
	}

	/**
	 * Runs once per version, also after the plugin is updated by uploading the zip (which
	 * doesn't fire the activation hook).
	 */
	public static function ensure_roles(): void {
		if ( self::VERSION === get_option( self::VERSION_OPTION ) ) {
			return;
		}
		remove_role( self::ROLE );
		add_role(
			self::ROLE,
			'Bucătărie',
			array(
				'read'    => true,
				self::CAP => true,
			)
		);
		foreach ( array( 'administrator', 'shop_manager' ) as $name ) {
			$role = get_role( $name );
			if ( $role ) {
				$role->add_cap( self::CAP );
			}
		}
		update_option( self::VERSION_OPTION, self::VERSION );
	}

	/** Staff who manage the panel but not the shop. */
	public static function is_kitchen( ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();
		return $user_id > 0 && user_can( $user_id, self::CAP ) && ! user_can( $user_id, 'manage_woocommerce' );
	}

	/**
	 * The location a kitchen account is limited to; '' means every location.
	 */
	public static function location_of( ?int $user_id = null ): string {
		$user_id = $user_id ?? get_current_user_id();
		if ( ! self::is_kitchen( $user_id ) ) {
			return '';
		}
		$id = (string) get_user_meta( $user_id, self::LOCATION_META, true );
		return '' !== $id && Settings::get_location( $id ) ? $id : '';
	}

	public static function panel_url(): string {
		return admin_url( 'admin.php?page=' . Orders_Page::SLUG );
	}

	/** WooCommerce sends users without edit_posts to "My account"; the kitchen needs wp-admin. */
	public static function allow_admin( $prevent ) {
		return self::is_kitchen() ? false : $prevent;
	}

	/**
	 * @param string             $redirect_to
	 * @param string             $requested
	 * @param \WP_User|\WP_Error $user
	 */
	public static function login_redirect( $redirect_to, $requested, $user ) {
		return $user instanceof \WP_User && self::is_kitchen( $user->ID ) ? self::panel_url() : $redirect_to;
	}

	/**
	 * @param string   $redirect_to
	 * @param \WP_User $user
	 */
	public static function woocommerce_login_redirect( $redirect_to, $user ) {
		return $user instanceof \WP_User && self::is_kitchen( $user->ID ) ? self::panel_url() : $redirect_to;
	}

	public static function trim_menu(): void {
		if ( self::is_kitchen() ) {
			remove_menu_page( 'index.php' );
		}
	}

	public static function skip_dashboard(): void {
		if ( self::is_kitchen() ) {
			wp_safe_redirect( self::panel_url() );
			exit;
		}
	}

	public static function profile_field( \WP_User $user ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! in_array( self::ROLE, (array) $user->roles, true ) ) {
			return;
		}
		$current = (string) get_user_meta( $user->ID, self::LOCATION_META, true );
		?>
		<h2>Bucătărie</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="mlb-kitchen-location">Punct de lucru</label></th>
				<td>
					<select name="mlb_kitchen_location" id="mlb-kitchen-location">
						<option value="">Toate punctele de lucru</option>
						<?php foreach ( Settings::get_locations() as $location ) : ?>
							<option value="<?php echo esc_attr( (string) $location['id'] ); ?>" <?php selected( $current, (string) $location['id'] ); ?>><?php echo esc_html( (string) $location['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description">Contul vede în „Comenzi aplicație” doar comenzile acestui punct de lucru.</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * The profile form's own nonce (update-user_{id}) is checked by WordPress before this runs.
	 */
	public static function save_profile_field( int $user_id ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'edit_user', $user_id ) || ! isset( $_POST['mlb_kitchen_location'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		$id = sanitize_text_field( wp_unslash( $_POST['mlb_kitchen_location'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( '' === $id || null === Settings::get_location( $id ) ) {
			delete_user_meta( $user_id, self::LOCATION_META );
			return;
		}
		update_user_meta( $user_id, self::LOCATION_META, $id );
	}
}
