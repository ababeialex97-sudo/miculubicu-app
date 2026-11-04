<?php
/**
 * Appearance › Micu' lu' Bicu: one-click setup of the Elementor content and the header/footer choice.
 *
 * Nothing runs on theme activation; every step is an explicit choice on this page,
 * so the live site only changes when the owner decides.
 */

defined( 'ABSPATH' ) || exit;

require_once MLB_THEME_DIR . '/inc/templates.php';

const MLB_THEME_INSTALLED_OPTION = 'mlb_theme_installed';

add_action(
	'admin_menu',
	static function (): void {
		add_theme_page(
			"Micu' lu' Bicu",
			"Micu' lu' Bicu",
			'edit_theme_options',
			'mlb-theme',
			'mlb_theme_render_admin_page'
		);
	}
);

add_action( 'admin_post_mlb_theme_install', 'mlb_theme_handle_install' );
add_action( 'admin_post_mlb_theme_locations', 'mlb_theme_handle_locations' );

/**
 * @return array<int, string> Elementor library templates: id => title.
 */
function mlb_theme_library_templates(): array {
	$posts = get_posts(
		array(
			'post_type'      => 'elementor_library',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);
	$out = array();
	foreach ( $posts as $post ) {
		$out[ $post->ID ] = $post->post_title;
	}
	return $out;
}

function mlb_theme_render_admin_page(): void {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}

	$elementor = mlb_theme_elementor_ready();
	$wc        = class_exists( 'WooCommerce' );
	$installed = (array) get_option( MLB_THEME_INSTALLED_OPTION, array() );
	$messages  = get_transient( 'mlb_theme_messages_' . get_current_user_id() );
	delete_transient( 'mlb_theme_messages_' . get_current_user_id() );
	?>
	<div class="wrap">
		<h1>Micu' lu' Bicu</h1>

		<?php foreach ( (array) $messages as $message ) : ?>
			<div class="notice notice-<?php echo esc_attr( $message[0] ); ?>"><p><?php echo wp_kses_post( $message[1] ); ?></p></div>
		<?php endforeach; ?>

		<?php if ( ! $elementor ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'Elementor nu este activ. Tema are nevoie de Elementor (versiunea gratuită ajunge).', 'mlb-theme' ); ?></p></div>
		<?php endif; ?>
		<?php if ( ! $wc ) : ?>
			<div class="notice notice-warning"><p><?php esc_html_e( 'WooCommerce nu este activ: secțiunile cu produse și coșul nu vor apărea.', 'mlb-theme' ); ?></p></div>
		<?php endif; ?>

		<h2><?php esc_html_e( '1. Instalare conținut', 'mlb-theme' ); ?></h2>
		<p><?php esc_html_e( 'Creează antetul, subsolul și pagina de start, construite din widget-urile temei. Fă un backup al site-ului înainte.', 'mlb-theme' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="mlb_theme_install">
			<?php wp_nonce_field( 'mlb_theme_install' ); ?>
			<fieldset>
				<p><label><input type="checkbox" name="steps[]" value="kit" checked> <?php esc_html_e( 'Culorile și fonturile mărcii în Elementor (Setări site › Culori și fonturi globale)', 'mlb-theme' ); ?></label></p>
				<p><label><input type="checkbox" name="steps[]" value="header_footer" checked> <?php esc_html_e( 'Șabloanele „Antet” și „Subsol” (și le folosește pe tot site-ul)', 'mlb-theme' ); ?></label></p>
				<p><label><input type="checkbox" name="steps[]" value="home" checked> <?php esc_html_e( 'Pagina „Acasă (nou)”, salvată ca ciornă', 'mlb-theme' ); ?></label></p>
				<p style="margin-left:24px"><label><input type="checkbox" name="steps[]" value="front"> <?php esc_html_e( 'Publică-o și setează-o ca primă pagină a site-ului', 'mlb-theme' ); ?></label></p>
				<p><label><input type="checkbox" name="steps[]" value="terms" checked> <?php esc_html_e( 'Categoria WooCommerce „Pachete” și eticheta „Cel mai comandat”, dacă lipsesc', 'mlb-theme' ); ?></label></p>
			</fieldset>
			<?php submit_button( __( 'Instalează', 'mlb-theme' ), 'primary', 'submit', true, $elementor ? array() : array( 'disabled' => 'disabled' ) ); ?>
		</form>

		<?php if ( $installed ) : ?>
			<h3><?php esc_html_e( 'Create deja', 'mlb-theme' ); ?></h3>
			<ul>
				<?php foreach ( $installed as $key => $post_id ) : ?>
					<?php if ( get_post( (int) $post_id ) ) : ?>
						<li><?php echo esc_html( get_the_title( (int) $post_id ) ); ?> · <a href="<?php echo esc_url( admin_url( 'post.php?post=' . (int) $post_id . '&action=elementor' ) ); ?>"><?php esc_html_e( 'Editează cu Elementor', 'mlb-theme' ); ?></a></li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<h2><?php esc_html_e( '2. Antet și subsol', 'mlb-theme' ); ?></h2>
		<p><?php esc_html_e( 'Alege ce șablon Elementor apare sus și jos pe toate paginile. Cu Elementor Pro, regulile din Theme Builder au prioritate.', 'mlb-theme' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="mlb_theme_locations">
			<?php wp_nonce_field( 'mlb_theme_locations' ); ?>
			<table class="form-table" role="presentation">
				<?php
				foreach ( array( MLB_THEME_MOD_HEADER => __( 'Antet', 'mlb-theme' ), MLB_THEME_MOD_FOOTER => __( 'Subsol', 'mlb-theme' ) ) as $mod => $label ) :
					$current = (int) get_theme_mod( $mod, 0 );
					?>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( $mod ); ?>"><?php echo esc_html( $label ); ?></label></th>
						<td>
							<select name="<?php echo esc_attr( $mod ); ?>" id="<?php echo esc_attr( $mod ); ?>">
								<option value="0"><?php esc_html_e( '— antetul simplu al temei —', 'mlb-theme' ); ?></option>
								<?php foreach ( mlb_theme_library_templates() as $id => $title ) : ?>
									<option value="<?php echo (int) $id; ?>" <?php selected( $current, $id ); ?>><?php echo esc_html( $title ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button( __( 'Salvează', 'mlb-theme' ), 'secondary' ); ?>
		</form>

		<h2><?php esc_html_e( 'Cum editezi', 'mlb-theme' ); ?></h2>
		<ul style="list-style:disc;margin-left:20px">
			<li><?php esc_html_e( 'Textele, pozele, butoanele și culorile fiecărei secțiuni: deschizi pagina cu Elementor și dai clic pe secțiune (sau direct pe text).', 'mlb-theme' ); ?></li>
			<li><?php esc_html_e( 'Culorile și fonturile pentru tot site-ul: în Elementor › Setări site › Culori globale / Fonturi globale.', 'mlb-theme' ); ?></li>
			<li><?php esc_html_e( 'Produsele, prețurile și pozele lor: WooCommerce › Produse (apar automat și în aplicație).', 'mlb-theme' ); ?></li>
			<li><?php esc_html_e( 'Insigna „Cel mai comandat”: pui eticheta „Cel mai comandat” pe produs. Pachetele: produse în categoria „Pachete”, cu preț redus (preț vechi tăiat).', 'mlb-theme' ); ?></li>
			<li><?php esc_html_e( 'Programul și telefonul pentru Google: Aspect › Personalizează › Date restaurant.', 'mlb-theme' ); ?></li>
		</ul>
	</div>
	<?php
}

function mlb_theme_redirect_with( array $messages ): void {
	set_transient( 'mlb_theme_messages_' . get_current_user_id(), $messages, 60 );
	wp_safe_redirect( admin_url( 'themes.php?page=mlb-theme' ) );
	exit;
}

function mlb_theme_handle_locations(): void {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( esc_html__( 'Nu ai voie să faci asta.', 'mlb-theme' ), 403 );
	}
	check_admin_referer( 'mlb_theme_locations' );

	foreach ( array( MLB_THEME_MOD_HEADER, MLB_THEME_MOD_FOOTER ) as $mod ) {
		$id = isset( $_POST[ $mod ] ) ? absint( $_POST[ $mod ] ) : 0;
		if ( $id > 0 && 'elementor_library' !== get_post_type( $id ) ) {
			$id = 0;
		}
		set_theme_mod( $mod, $id );
	}
	mlb_theme_redirect_with( array( array( 'success', esc_html__( 'Salvat.', 'mlb-theme' ) ) ) );
}

function mlb_theme_handle_install(): void {
	if ( ! current_user_can( 'edit_theme_options' ) || ! current_user_can( 'publish_pages' ) ) {
		wp_die( esc_html__( 'Nu ai voie să faci asta.', 'mlb-theme' ), 403 );
	}
	check_admin_referer( 'mlb_theme_install' );

	if ( ! mlb_theme_elementor_ready() ) {
		mlb_theme_redirect_with( array( array( 'error', esc_html__( 'Activează Elementor mai întâi.', 'mlb-theme' ) ) ) );
	}

	$steps     = array_map( 'sanitize_key', (array) ( $_POST['steps'] ?? array() ) );
	$installed = (array) get_option( MLB_THEME_INSTALLED_OPTION, array() );
	$messages  = array();

	if ( in_array( 'terms', $steps, true ) ) {
		mlb_theme_ensure_terms();
		$messages[] = array( 'success', esc_html__( 'Categoria „Pachete” și eticheta „Cel mai comandat” sunt pregătite.', 'mlb-theme' ) );
	}

	if ( in_array( 'kit', $steps, true ) ) {
		$messages[] = mlb_theme_apply_kit()
			? array( 'success', esc_html__( 'Culorile și fonturile au fost setate în Elementor.', 'mlb-theme' ) )
			: array( 'error', esc_html__( 'Nu am găsit setările Elementor ale site-ului (kit).', 'mlb-theme' ) );
	}

	if ( in_array( 'header_footer', $steps, true ) ) {
		foreach ( array(
			'header' => array( "Antet Micu' lu' Bicu", mlb_theme_template_header(), MLB_THEME_MOD_HEADER ),
			'footer' => array( "Subsol Micu' lu' Bicu", mlb_theme_template_footer(), MLB_THEME_MOD_FOOTER ),
		) as $key => list( $title, $elements, $mod ) ) {
			$id = mlb_theme_existing( $installed, $key );
			if ( ! $id ) {
				$id = mlb_theme_create_document( 'section', $title, $elements, 'publish' );
			}
			if ( $id ) {
				$installed[ $key ] = $id;
				set_theme_mod( $mod, $id );
				$messages[] = array( 'success', sprintf( '%s: <a href="%s">%s</a>', esc_html( $title ), esc_url( admin_url( 'post.php?post=' . $id . '&action=elementor' ) ), esc_html__( 'editează cu Elementor', 'mlb-theme' ) ) );
			} else {
				$messages[] = array( 'error', esc_html( $title ) . ': ' . esc_html__( 'nu a putut fi creat.', 'mlb-theme' ) );
			}
		}
	}

	if ( in_array( 'home', $steps, true ) ) {
		$publish = in_array( 'front', $steps, true );
		$id      = mlb_theme_existing( $installed, 'home' );
		if ( ! $id ) {
			$id = mlb_theme_create_document( 'wp-page', 'Acasă (nou)', mlb_theme_template_home(), $publish ? 'publish' : 'draft' );
		} elseif ( $publish ) {
			wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
		}

		if ( $id ) {
			$installed['home'] = $id;
			update_post_meta( $id, '_wp_page_template', 'elementor_header_footer' );
			if ( $publish ) {
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $id );
			}
			$messages[] = array(
				'success',
				sprintf(
					'%s · <a href="%s">%s</a> · <a href="%s" target="_blank">%s</a>',
					$publish ? esc_html__( 'Pagina „Acasă (nou)” e prima pagină a site-ului.', 'mlb-theme' ) : esc_html__( 'Pagina „Acasă (nou)” e salvată ca ciornă.', 'mlb-theme' ),
					esc_url( admin_url( 'post.php?post=' . $id . '&action=elementor' ) ),
					esc_html__( 'editează cu Elementor', 'mlb-theme' ),
					esc_url( get_preview_post_link( $id ) ? get_preview_post_link( $id ) : get_permalink( $id ) ),
					esc_html__( 'previzualizează', 'mlb-theme' )
				),
			);
		} else {
			$messages[] = array( 'error', esc_html__( 'Pagina de start nu a putut fi creată.', 'mlb-theme' ) );
		}
	}

	update_option( MLB_THEME_INSTALLED_OPTION, $installed, false );
	mlb_theme_redirect_with( $messages ? $messages : array( array( 'warning', esc_html__( 'Nu ai bifat nimic.', 'mlb-theme' ) ) ) );
}

function mlb_theme_existing( array $installed, string $key ): int {
	$id = (int) ( $installed[ $key ] ?? 0 );
	return $id > 0 && get_post( $id ) && 'trash' !== get_post_status( $id ) ? $id : 0;
}

/**
 * Creates an Elementor document through Elementor's own API, so its meta and CSS are set up as if built in the editor.
 */
function mlb_theme_create_document( string $type, string $title, array $elements, string $status ): int {
	try {
		$document = \Elementor\Plugin::instance()->documents->create(
			$type,
			array(
				'post_title'  => $title,
				'post_status' => $status,
			)
		);
		if ( ! $document || is_wp_error( $document ) ) {
			return 0;
		}
		$document->save( array( 'elements' => $elements ) );
		return (int) $document->get_main_id();
	} catch ( \Throwable $e ) {
		return 0;
	}
}

function mlb_theme_ensure_terms(): void {
	if ( taxonomy_exists( 'product_cat' ) && ! term_exists( 'pachete', 'product_cat' ) ) {
		wp_insert_term( 'Pachete', 'product_cat', array( 'slug' => 'pachete' ) );
	}
	if ( taxonomy_exists( 'product_tag' ) && ! term_exists( 'cel-mai-comandat', 'product_tag' ) ) {
		wp_insert_term( 'Cel mai comandat', 'product_tag', array( 'slug' => 'cel-mai-comandat' ) );
	}
}
