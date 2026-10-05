<?php
/**
 * "Setări aplicație" under WooCommerce: locations, free delivery, shipping methods, loyalty card, test mode.
 */

namespace MLB\AppApi\Admin;

use MLB\AppApi\Loyalty;
use MLB\AppApi\Loyalty_Rules;
use MLB\AppApi\Rest\Menu_Controller;
use MLB\AppApi\Settings;

defined( 'ABSPATH' ) || exit;

class Settings_Page {

	public const SLUG = 'mlb-app-settings';

	private const ACTION = 'mlb_app_save_settings';

	/** Spare empty rows shown for adding a location. */
	private const SPARE_ROWS = 1;

	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'register' ) );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'save' ) );
	}

	public static function register(): void {
		add_submenu_page( 'woocommerce', 'Setări aplicație', 'Setări aplicație', 'manage_woocommerce', self::SLUG, array( self::class, 'render' ) );
	}

	public static function render(): void {
		$locations = array_values( Settings::get_locations() );
		for ( $i = 0; $i < self::SPARE_ROWS; $i++ ) {
			$locations[] = array();
		}
		$methods = self::shipping_methods();
		$loyalty = Loyalty::settings();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$saved = isset( $_GET['saved'] );
		?>
		<div class="wrap">
			<h1>Setări aplicație</h1>
			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p>Setările au fost salvate.</p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<?php wp_nonce_field( self::ACTION ); ?>

				<h2>Puncte de lucru</h2>
				<p class="description">Costul livrării și pragul gol înseamnă valorile generale de mai jos. Un rând fără nume e ignorat.</p>
				<table class="widefat striped">
					<thead>
						<tr>
							<th>Nume</th>
							<th>Adresă</th>
							<th>Telefon</th>
							<th>Livrare</th>
							<th>Ridicare</th>
							<th>Cost livrare</th>
							<th>Livrare gratuită de la</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $locations as $index => $location ) : ?>
						<tr>
							<td>
								<input type="hidden" name="locations[<?php echo (int) $index; ?>][id]" value="<?php echo esc_attr( $location['id'] ?? '' ); ?>">
								<input type="text" class="regular-text" style="width:100%" name="locations[<?php echo (int) $index; ?>][name]" value="<?php echo esc_attr( $location['name'] ?? '' ); ?>" aria-label="Nume punct de lucru">
							</td>
							<td><input type="text" style="width:100%" name="locations[<?php echo (int) $index; ?>][address]" value="<?php echo esc_attr( $location['address'] ?? '' ); ?>" aria-label="Adresă"></td>
							<td><input type="tel" style="width:100%" name="locations[<?php echo (int) $index; ?>][phone]" value="<?php echo esc_attr( $location['phone'] ?? '' ); ?>" aria-label="Telefon"></td>
							<td><input type="checkbox" name="locations[<?php echo (int) $index; ?>][delivery]" value="1" <?php checked( ! empty( $location['delivery'] ) || empty( $location ) ); ?> aria-label="Livrare"></td>
							<td><input type="checkbox" name="locations[<?php echo (int) $index; ?>][pickup]" value="1" <?php checked( ! empty( $location['pickup'] ) || empty( $location ) ); ?> aria-label="Ridicare"></td>
							<td><input type="number" min="0" step="0.01" style="width:90px" name="locations[<?php echo (int) $index; ?>][delivery_fee]" value="<?php echo esc_attr( $location['delivery_fee'] ?? '' ); ?>" aria-label="Cost livrare"></td>
							<td><input type="number" min="0" step="0.01" style="width:90px" name="locations[<?php echo (int) $index; ?>][free_delivery_threshold]" value="<?php echo esc_attr( $location['free_delivery_threshold'] ?? '' ); ?>" aria-label="Livrare gratuită de la"></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<h2>Livrare</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="mlb-free">Livrare gratuită de la (lei)</label></th>
						<td>
							<input id="mlb-free" type="number" min="0" step="0.01" name="free_delivery_threshold" value="<?php echo esc_attr( (string) get_option( Settings::OPTION_FREE_DELIVERY_FROM, '0' ) ); ?>">
							<p class="description">0 = fără livrare gratuită. Costul de bază al livrării vine din metoda de livrare WooCommerce aleasă mai jos.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mlb-delivery-method">Metoda pentru livrare</label></th>
						<td><?php self::method_select( 'delivery_instance_id', 'mlb-delivery-method', Settings::OPTION_DELIVERY_INSTANCE, $methods, 'flat_rate' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><label for="mlb-pickup-method">Metoda pentru ridicare</label></th>
						<td>
							<?php self::method_select( 'pickup_instance_id', 'mlb-pickup-method', Settings::OPTION_PICKUP_INSTANCE, $methods, 'free_shipping' ); ?>
							<p class="description">GrandChef asociază metodele după titlu, deci trebuie să fie cele din pagina de asociere GrandChef.</p>
						</td>
					</tr>
				</table>

				<h2>Card de fidelitate</h2>
				<p class="description">Fiecare comandă din aplicație marcată „Finalizată” adaugă o ștampilă. Cardul plin devine un cupon personal, de o singură folosință. Promoțiile și codurile se fac din Marketing → Cupoane (bifează „Afișează în aplicație”).</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Card activ</th>
						<td><label><input type="checkbox" name="loyalty[enabled]" value="1" <?php checked( $loyalty['enabled'] ); ?>> Clienții primesc ștampile</label></td>
					</tr>
					<tr>
						<th scope="row"><label for="mlb-loyalty-required">Comenzi pentru recompensă</label></th>
						<td><input id="mlb-loyalty-required" type="number" min="1" max="20" step="1" name="loyalty[required]" value="<?php echo esc_attr( (string) $loyalty['required'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="mlb-loyalty-amount">Reducere</label></th>
						<td>
							<input id="mlb-loyalty-amount" type="number" min="0" step="0.01" style="width:90px" name="loyalty[reward_amount]" value="<?php echo esc_attr( (string) $loyalty['reward_amount'] ); ?>">
							<select name="loyalty[reward_type]" aria-label="Tip reducere">
								<option value="percent" <?php selected( $loyalty['reward_type'], 'percent' ); ?>>% din comandă</option>
								<option value="fixed" <?php selected( $loyalty['reward_type'], 'fixed' ); ?>>lei</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mlb-loyalty-days">Valabilitate recompensă (zile)</label></th>
						<td><input id="mlb-loyalty-days" type="number" min="1" max="365" step="1" name="loyalty[reward_days]" value="<?php echo esc_attr( (string) $loyalty['reward_days'] ); ?>"></td>
					</tr>
				</table>

				<h2>Mod de test</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Comenzi de test</th>
						<td>
							<label><input type="checkbox" name="test_mode" value="1" <?php checked( Settings::is_test_mode() ); ?>> Marchează comenzile din aplicație cu „<?php echo esc_html( Settings::TEST_ORDER_NOTE ); ?>”</label>
							<p class="description">Lasă bifat cât timp site-ul e legat de serverul GrandChef de TEST.</p>
						</td>
					</tr>
				</table>

				<?php submit_button( 'Salvează' ); ?>
			</form>
		</div>
		<?php
	}

	public static function save(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Nu ai acces.', 403 );
		}
		check_admin_referer( self::ACTION );

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.
		$rows = isset( $_POST['locations'] ) && is_array( $_POST['locations'] ) ? wp_unslash( $_POST['locations'] ) : array();
		// phpcs:enable

		update_option( Settings::OPTION_LOCATIONS, self::sanitize_locations( $rows ) );
		update_option( Settings::OPTION_FREE_DELIVERY_FROM, self::money( wp_unslash( $_POST['free_delivery_threshold'] ?? '0' ) ) ?? '0' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		update_option( Settings::OPTION_DELIVERY_INSTANCE, absint( $_POST['delivery_instance_id'] ?? 0 ) );
		update_option( Settings::OPTION_PICKUP_INSTANCE, absint( $_POST['pickup_instance_id'] ?? 0 ) );
		update_option( Settings::OPTION_TEST_MODE, empty( $_POST['test_mode'] ) ? 'no' : 'yes' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- normalized below.
		$loyalty            = isset( $_POST['loyalty'] ) && is_array( $_POST['loyalty'] ) ? wp_unslash( $_POST['loyalty'] ) : array();
		$loyalty['enabled'] = ! empty( $loyalty['enabled'] );
		if ( isset( $loyalty['reward_amount'] ) ) {
			$loyalty['reward_amount'] = str_replace( ',', '.', (string) $loyalty['reward_amount'] );
		}
		update_option( Loyalty::OPTION, Loyalty_Rules::normalize( $loyalty ) );

		Menu_Controller::flush_cache();

		wp_safe_redirect( add_query_arg( 'saved', '1', admin_url( 'admin.php?page=' . self::SLUG ) ) );
		exit;
	}

	/**
	 * @param array<int, mixed> $rows
	 * @return array<int, array<string, mixed>>
	 */
	public static function sanitize_locations( array $rows ): array {
		$locations = array();
		$used_ids  = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$name = sanitize_text_field( (string) ( $row['name'] ?? '' ) );
			if ( '' === $name ) {
				continue;
			}

			// IDs are stored on orders, so an existing location keeps its ID even when renamed.
			$id = sanitize_key( (string) ( $row['id'] ?? '' ) );
			if ( '' === $id ) {
				$id = sanitize_key( sanitize_title( $name ) );
			}
			$base   = '' === $id ? 'loc' : $id;
			$suffix = 2;
			while ( '' === $id || in_array( $id, $used_ids, true ) ) {
				$id = $base . '-' . $suffix++;
			}
			$used_ids[] = $id;

			$location = array(
				'id'       => $id,
				'name'     => $name,
				'address'  => sanitize_text_field( (string) ( $row['address'] ?? '' ) ),
				'phone'    => sanitize_text_field( (string) ( $row['phone'] ?? '' ) ),
				'delivery' => ! empty( $row['delivery'] ),
				'pickup'   => ! empty( $row['pickup'] ),
			);
			foreach ( array( 'delivery_fee', 'free_delivery_threshold' ) as $key ) {
				$value = self::money( $row[ $key ] ?? '' );
				if ( null !== $value ) {
					$location[ $key ] = $value;
				}
			}
			$locations[] = $location;
		}

		return $locations;
	}

	/**
	 * A non-negative decimal string, or null when the field is empty or invalid.
	 */
	private static function money( $value ): ?string {
		$value = str_replace( ',', '.', trim( (string) $value ) );
		if ( '' === $value || ! is_numeric( $value ) || (float) $value < 0 ) {
			return null;
		}
		return wc_format_decimal( $value, 2 );
	}

	/**
	 * @return array<int, array{instance_id: int, method_id: string, label: string}>
	 */
	private static function shipping_methods(): array {
		$methods = array();
		foreach ( \WC_Shipping_Zones::get_zones() as $zone ) {
			foreach ( $zone['shipping_methods'] as $method ) {
				$methods[] = array(
					'instance_id' => (int) $method->get_instance_id(),
					'method_id'   => (string) $method->id,
					'label'       => sprintf( '%s – %s (%s)%s', $zone['zone_name'], $method->get_title(), $method->id, 'yes' === $method->enabled ? '' : ', dezactivată' ),
				);
			}
		}
		return $methods;
	}

	/**
	 * @param array<int, array{instance_id: int, method_id: string, label: string}> $methods
	 */
	private static function method_select( string $name, string $id, string $option, array $methods, string $method_id ): void {
		$current = (int) get_option( $option, 0 );
		echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
		echo '<option value="0">' . esc_html( sprintf( 'Automat (prima metodă %s activă)', $method_id ) ) . '</option>';
		foreach ( $methods as $method ) {
			if ( $method['method_id'] !== $method_id ) {
				continue;
			}
			echo '<option value="' . esc_attr( (string) $method['instance_id'] ) . '"' . selected( $current, $method['instance_id'], false ) . '>' . esc_html( $method['label'] ) . '</option>';
		}
		echo '</select>';
	}
}
