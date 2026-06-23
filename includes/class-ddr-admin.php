<?php
/**
 * Area amministrazione: impostazioni e elenco richieste di recesso.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DDR_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( 'DDR_DB', 'maybe_upgrade' ) );
		// Azioni admin: cambio stato + export CSV.
		add_action( 'admin_post_ddr_set_status', array( __CLASS__, 'handle_set_status' ) );
		add_action( 'admin_post_ddr_export_csv', array( __CLASS__, 'handle_export_csv' ) );
		// Esclusione recesso a livello prodotto.
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'product_field' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_product_field' ) );
	}

	public static function menu() {
		add_menu_page(
			__( 'Diritto di Recesso', 'diritto-di-recesso' ),
			__( 'Diritto di Recesso', 'diritto-di-recesso' ),
			'manage_woocommerce',
			'ddr-richieste',
			array( __CLASS__, 'render_requests' ),
			'dashicons-undo',
			56
		);
		add_submenu_page(
			'ddr-richieste',
			__( 'Richieste', 'diritto-di-recesso' ),
			__( 'Richieste', 'diritto-di-recesso' ),
			'manage_woocommerce',
			'ddr-richieste',
			array( __CLASS__, 'render_requests' )
		);
		add_submenu_page(
			'ddr-richieste',
			__( 'Impostazioni', 'diritto-di-recesso' ),
			__( 'Impostazioni', 'diritto-di-recesso' ),
			'manage_woocommerce',
			'ddr-settings',
			array( __CLASS__, 'render_settings' )
		);
	}

	/* ----------------------------- Settings ----------------------------- */

	public static function register_settings() {
		register_setting( 'ddr_settings', 'ddr_window_days', array( 'sanitize_callback' => 'absint', 'default' => 14 ) );
		register_setting( 'ddr_settings', 'ddr_admin_recipients', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );
		register_setting( 'ddr_settings', 'ddr_footer_link', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => 'yes' ) );
		register_setting( 'ddr_settings', 'ddr_enforce_cutoff', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => 'no' ) );
		register_setting( 'ddr_settings', 'ddr_accent', array( 'sanitize_callback' => 'sanitize_hex_color', 'default' => '#ea580c' ) );
		register_setting( 'ddr_settings', 'ddr_trust_proxy', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => 'no' ) );
		register_setting( 'ddr_settings', 'ddr_delete_data', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => 'no' ) );
	}

	public static function render_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$page_id = (int) get_option( 'ddr_page_id', 0 );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Diritto di Recesso 54-bis – Impostazioni', 'diritto-di-recesso' ); ?></h1>

			<?php if ( $page_id ) : ?>
				<p><?php esc_html_e( 'Pagina pubblica del recesso:', 'diritto-di-recesso' ); ?>
					<a href="<?php echo esc_url( get_permalink( $page_id ) ); ?>" target="_blank"><?php echo esc_html( get_permalink( $page_id ) ); ?></a>
				</p>
			<?php else : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'La pagina del recesso non è stata creata. Disattiva e riattiva il plugin, oppure crea una pagina con lo shortcode [diritto_recesso].', 'diritto-di-recesso' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'ddr_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ddr_window_days"><?php esc_html_e( 'Giorni di recesso', 'diritto-di-recesso' ); ?></label></th>
						<td>
							<input type="number" min="1" name="ddr_window_days" id="ddr_window_days" value="<?php echo esc_attr( get_option( 'ddr_window_days', 14 ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Default 14. L’art. 53 può estendere fino a 12 mesi se l’informativa precontrattuale è carente.', 'diritto-di-recesso' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ddr_admin_recipients"><?php esc_html_e( 'Destinatari notifiche', 'diritto-di-recesso' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" name="ddr_admin_recipients" id="ddr_admin_recipients" value="<?php echo esc_attr( get_option( 'ddr_admin_recipients', '' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Email separate da virgola. Vuoto = email amministratore del sito.', 'diritto-di-recesso' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Link nel footer', 'diritto-di-recesso' ); ?></th>
						<td>
							<label><input type="checkbox" name="ddr_footer_link" value="yes" <?php checked( 'yes', get_option( 'ddr_footer_link', 'yes' ) ); ?> /> <?php esc_html_e( 'Mostra “Recedere dal contratto qui” nel footer del sito', 'diritto-di-recesso' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ddr_accent"><?php esc_html_e( 'Colore accento badge', 'diritto-di-recesso' ); ?></label></th>
						<td>
							<input type="color" name="ddr_accent" id="ddr_accent" value="<?php echo esc_attr( get_option( 'ddr_accent', '#ea580c' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Colore dell’icona del badge nel footer. Intonalo al brand del sito.', 'diritto-di-recesso' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Limita agli ordini dal 19/06/2026', 'diritto-di-recesso' ); ?></th>
						<td>
							<label><input type="checkbox" name="ddr_enforce_cutoff" value="yes" <?php checked( 'yes', get_option( 'ddr_enforce_cutoff', 'no' ) ); ?> /> <?php esc_html_e( 'Mostra la funzione solo per ordini conclusi dalla data di applicabilità dell’obbligo', 'diritto-di-recesso' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'IP dietro proxy/CDN', 'diritto-di-recesso' ); ?></th>
						<td>
							<label><input type="checkbox" name="ddr_trust_proxy" value="yes" <?php checked( 'yes', get_option( 'ddr_trust_proxy', 'no' ) ); ?> /> <?php esc_html_e( 'Rileva l’IP reale del cliente dagli header del proxy (Cloudflare / X-Forwarded-For)', 'diritto-di-recesso' ); ?></label>
							<p class="description"><?php esc_html_e( 'Attiva solo se il sito è dietro Cloudflare o un reverse proxy affidabile. Di default viene registrato l’IP della connessione diretta.', 'diritto-di-recesso' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Cancellazione dati', 'diritto-di-recesso' ); ?></th>
						<td>
							<label><input type="checkbox" name="ddr_delete_data" value="yes" <?php checked( 'yes', get_option( 'ddr_delete_data', 'no' ) ); ?> /> <?php esc_html_e( 'Elimina tabella, opzioni e pagina alla disinstallazione del plugin', 'diritto-di-recesso' ); ?></label>
							<p class="description"><?php esc_html_e( 'Attenzione: rimuove definitivamente l’audit trail delle richieste di recesso. Lascia disattivato per conservare le prove.', 'diritto-di-recesso' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/* ----------------------------- Richieste ---------------------------- */

	public static function render_requests() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page = 50;
		$offset   = ( $paged - 1 ) * $per_page;
		$total    = DDR_DB::count_all();
		$rows     = DDR_DB::all( array( 'per_page' => $per_page, 'offset' => $offset ) );
		?>
		$export_url = wp_nonce_url( admin_url( 'admin-post.php?action=ddr_export_csv' ), 'ddr_export_csv' );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Richieste di recesso', 'diritto-di-recesso' ); ?></h1>
			<a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action"><?php esc_html_e( 'Esporta CSV', 'diritto-di-recesso' ); ?></a>
			<hr class="wp-header-end" />
			<?php
			if ( isset( $_GET['ddr_updated'] ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Stato della richiesta aggiornato.', 'diritto-di-recesso' ) . '</p></div>';
			}
			?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Data e ora', 'diritto-di-recesso' ); ?></th>
						<th><?php esc_html_e( 'Ricevuta', 'diritto-di-recesso' ); ?></th>
						<th><?php esc_html_e( 'Ordine', 'diritto-di-recesso' ); ?></th>
						<th><?php esc_html_e( 'Cliente', 'diritto-di-recesso' ); ?></th>
						<th><?php esc_html_e( 'Prodotti', 'diritto-di-recesso' ); ?></th>
						<th><?php esc_html_e( 'Stato', 'diritto-di-recesso' ); ?></th>
						<th><?php esc_html_e( 'Motivazione', 'diritto-di-recesso' ); ?></th>
						<th>IP</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="8"><?php esc_html_e( 'Nessuna richiesta registrata.', 'diritto-di-recesso' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $r ) :
							$dt        = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $r['created_at'] );
							$order_url = admin_url( 'post.php?post=' . $r['order_id'] . '&action=edit' );
							?>
							<tr>
								<td><?php echo esc_html( $dt ); ?></td>
								<td><code><?php echo esc_html( $r['receipt_code'] ); ?></code></td>
								<td><a href="<?php echo esc_url( $order_url ); ?>">#<?php echo esc_html( $r['order_id'] ); ?></a></td>
								<td><?php echo esc_html( $r['customer_name'] ); ?><br /><small><?php echo esc_html( $r['customer_email'] ); ?></small></td>
								<td><?php echo esc_html( DDR_Frontend::items_summary( $r['items_data'] ) ); ?></td>
								<td>
									<strong><?php echo esc_html( DDR_DB::status_label( $r['status'] ) ); ?></strong>
									<div class="row-actions"><?php echo wp_kses_post( self::status_actions( $r ) ); ?></div>
								</td>
								<td><?php echo esc_html( $r['reason'] ); ?></td>
								<td><?php echo esc_html( $r['ip_address'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php
			$total_pages = (int) ceil( $total / $per_page );
			if ( $total_pages > 1 ) {
				echo '<div class="tablenav"><div class="tablenav-pages">';
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'current'   => $paged,
							'total'     => $total_pages,
							'prev_text' => '«',
							'next_text' => '»',
						)
					)
				);
				echo '</div></div>';
			}
			?>
		</div>
		<?php
	}

	/**
	 * Link di cambio stato (row actions) per una richiesta.
	 */
	protected static function status_actions( $r ) {
		$links = array();
		$current = $r['status'];
		$targets = array(
			'lavorazione' => __( 'In lavorazione', 'diritto-di-recesso' ),
			'completata'  => __( 'Completata', 'diritto-di-recesso' ),
			'annullata'   => __( 'Annulla', 'diritto-di-recesso' ),
		);
		foreach ( $targets as $status => $label ) {
			if ( $status === $current ) {
				continue;
			}
			$url = wp_nonce_url(
				admin_url( 'admin-post.php?action=ddr_set_status&id=' . (int) $r['id'] . '&status=' . $status ),
				'ddr_set_status_' . (int) $r['id']
			);
			$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		return implode( ' | ', $links );
	}

	/**
	 * Handler: cambio stato di una richiesta.
	 */
	public static function handle_set_status() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'diritto-di-recesso' ) );
		}
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		check_admin_referer( 'ddr_set_status_' . $id );

		DDR_DB::update_status( $id, $status );

		wp_safe_redirect( add_query_arg( array( 'page' => 'ddr-richieste', 'ddr_updated' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Handler: export CSV di tutte le richieste.
	 */
	public static function handle_export_csv() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'diritto-di-recesso' ) );
		}
		check_admin_referer( 'ddr_export_csv' );

		$rows = DDR_DB::all( array( 'per_page' => 100000, 'offset' => 0 ) );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=recesso-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		// BOM per Excel.
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv(
			$out,
			array( 'ID', 'Ricevuta', 'Data', 'Ordine', 'Cliente', 'Email', 'Prodotti', 'Stato', 'Motivazione', 'IP' )
		);
		foreach ( $rows as $r ) {
			fputcsv(
				$out,
				array(
					$r['id'],
					$r['receipt_code'],
					$r['created_at'],
					$r['order_id'],
					$r['customer_name'],
					$r['customer_email'],
					DDR_Frontend::items_summary( $r['items_data'] ),
					DDR_DB::status_label( $r['status'] ),
					$r['reason'],
					$r['ip_address'],
				)
			);
		}
		fclose( $out );
		exit;
	}

	/* ------------------- Esclusione a livello prodotto ------------------ */

	public static function product_field() {
		woocommerce_wp_checkbox(
			array(
				'id'          => '_ddr_excluso',
				'label'       => __( 'Escluso dal recesso (art. 59)', 'diritto-di-recesso' ),
				'description' => __( 'Spunta se questo prodotto non dà diritto di recesso (es. beni su misura, sigillati e aperti, deperibili).', 'diritto-di-recesso' ),
			)
		);
	}

	public static function save_product_field( $post_id ) {
		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			return;
		}
		$val = isset( $_POST['_ddr_excluso'] ) ? 'yes' : 'no';
		$product->update_meta_data( '_ddr_excluso', $val );
		$product->save();
	}
}
