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
		add_action( 'admin_post_ddr_check_updates', array( __CLASS__, 'handle_check_updates' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'maybe_media' ) );
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
			__( 'Testi legali', 'diritto-di-recesso' ),
			__( 'Testi legali', 'diritto-di-recesso' ),
			'manage_woocommerce',
			'ddr-legal',
			array( 'DDR_Legal', 'render_admin_page' )
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
		register_setting( 'ddr_settings', 'ddr_orders_button', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => 'yes' ) );
		register_setting( 'ddr_settings', 'ddr_account_tab', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => 'yes' ) );
		register_setting( 'ddr_settings', 'ddr_link_label', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );
		register_setting( 'ddr_settings', 'ddr_footer_style', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => 'link' ) );
		register_setting( 'ddr_settings', 'ddr_btn_icon', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => 'yes' ) );
		register_setting( 'ddr_settings', 'ddr_enforce_cutoff', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => 'no' ) );
		register_setting( 'ddr_settings', 'ddr_accent', array( 'sanitize_callback' => 'sanitize_hex_color', 'default' => '#ea580c' ) );
		register_setting( 'ddr_settings', 'ddr_btn_bg', array( 'sanitize_callback' => 'sanitize_hex_color', 'default' => '#1a1a1a' ) );
		register_setting( 'ddr_settings', 'ddr_btn_text', array( 'sanitize_callback' => 'sanitize_hex_color', 'default' => '#ffffff' ) );
		register_setting( 'ddr_settings', 'ddr_menu_location', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );
		register_setting( 'ddr_settings', 'ddr_modal', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => 'no' ) );
		register_setting( 'ddr_settings', 'ddr_overlay', array( 'sanitize_callback' => 'sanitize_hex_color', 'default' => '#0f172a' ) );
		register_setting( 'ddr_settings', 'ddr_trust_proxy', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => 'no' ) );
		register_setting( 'ddr_settings', 'ddr_delete_data', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => 'no' ) );
		register_setting( 'ddr_settings', 'ddr_customer_emails', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => 'yes' ) );
		register_setting( 'ddr_settings', 'ddr_auto_refund', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => 'no' ) );
		register_setting( 'ddr_settings', 'ddr_pdf_enable', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => 'yes' ) );
		register_setting( 'ddr_settings', 'ddr_pdf_attach', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => 'yes' ) );
		register_setting( 'ddr_settings', 'ddr_pdf_logo', array( 'sanitize_callback' => 'absint', 'default' => 0 ) );
		register_setting( 'ddr_settings', 'ddr_radius', array( 'sanitize_callback' => 'absint', 'default' => 10 ) );
		register_setting( 'ddr_settings', 'ddr_shadow', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => 'yes' ) );
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

			<?php self::render_updates_box(); ?>

			<form method="post" action="options.php" class="ddr-settings-form">
				<?php settings_fields( 'ddr_settings' ); ?>

				<h2 class="title"><?php esc_html_e( 'Recesso', 'diritto-di-recesso' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ddr_window_days"><?php esc_html_e( 'Giorni di recesso', 'diritto-di-recesso' ); ?></label></th>
						<td>
							<input type="number" min="1" name="ddr_window_days" id="ddr_window_days" value="<?php echo esc_attr( get_option( 'ddr_window_days', 14 ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Default 14. L’art. 53 può estendere fino a 12 mesi se l’informativa precontrattuale è carente.', 'diritto-di-recesso' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Limita agli ordini dal 19/06/2026', 'diritto-di-recesso' ); ?></th>
						<td>
							<label><input type="checkbox" name="ddr_enforce_cutoff" value="yes" <?php checked( 'yes', get_option( 'ddr_enforce_cutoff', 'no' ) ); ?> /> <?php esc_html_e( 'Mostra la funzione solo per ordini conclusi dalla data di applicabilità dell’obbligo', 'diritto-di-recesso' ); ?></label>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Punto di accesso', 'diritto-di-recesso' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ddr_link_label"><?php esc_html_e( 'Testo del pulsante di recesso', 'diritto-di-recesso' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" name="ddr_link_label" id="ddr_link_label" value="<?php echo esc_attr( get_option( 'ddr_link_label', '' ) ); ?>" placeholder="<?php esc_attr_e( 'Recedere dal contratto qui', 'diritto-di-recesso' ); ?>" />
							<p class="description"><?php esc_html_e( 'Etichetta del link/pulsante (footer, area ordini, form). Vuoto = “Recedere dal contratto qui”, l’esempio indicato dall’art. 54-bis c.3.', 'diritto-di-recesso' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Link nel footer', 'diritto-di-recesso' ); ?></th>
						<td>
							<label><input type="checkbox" name="ddr_footer_link" value="yes" <?php checked( 'yes', get_option( 'ddr_footer_link', 'yes' ) ); ?> /> <?php esc_html_e( 'Mostra automaticamente il link di recesso nel footer del sito', 'diritto-di-recesso' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ddr_footer_style"><?php esc_html_e( 'Stile nel footer', 'diritto-di-recesso' ); ?></label></th>
						<td>
							<?php $fs = get_option( 'ddr_footer_style', 'link' ); ?>
							<select name="ddr_footer_style" id="ddr_footer_style">
								<option value="link" <?php selected( $fs, 'link' ); ?>><?php esc_html_e( 'Link testuale (discreto, consigliato)', 'diritto-di-recesso' ); ?></option>
								<option value="button" <?php selected( $fs, 'button' ); ?>><?php esc_html_e( 'Pulsante', 'diritto-di-recesso' ); ?></option>
								<option value="pill" <?php selected( $fs, 'pill' ); ?>><?php esc_html_e( 'Badge (pill)', 'diritto-di-recesso' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Il link testuale si integra come una normale voce di footer; non invadente e conforme (posizione chiaramente visibile).', 'diritto-di-recesso' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Icona', 'diritto-di-recesso' ); ?></th>
						<td>
							<label><input type="checkbox" name="ddr_btn_icon" value="yes" <?php checked( 'yes', get_option( 'ddr_btn_icon', 'yes' ) ); ?> /> <?php esc_html_e( 'Mostra l’icona accanto al testo del link/pulsante', 'diritto-di-recesso' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Pulsante in “I miei ordini”', 'diritto-di-recesso' ); ?></th>
						<td>
							<label><input type="checkbox" name="ddr_orders_button" value="yes" <?php checked( 'yes', get_option( 'ddr_orders_button', 'yes' ) ); ?> /> <?php esc_html_e( 'Mostra il pulsante di recesso nella tabella degli ordini dell’area account', 'diritto-di-recesso' ); ?></label>
							<p class="description"><?php esc_html_e( 'Se il tuo tema rende stretta la colonna azioni, puoi disattivarlo: il recesso resta disponibile nel dettaglio ordine e nel tab “Diritto di recesso”.', 'diritto-di-recesso' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Voce “Diritto di recesso” nell’account', 'diritto-di-recesso' ); ?></th>
						<td>
							<label><input type="checkbox" name="ddr_account_tab" value="yes" <?php checked( 'yes', get_option( 'ddr_account_tab', 'yes' ) ); ?> /> <?php esc_html_e( 'Mostra la voce dedicata nel menu dell’area “Il mio account”', 'diritto-di-recesso' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ddr_menu_location"><?php esc_html_e( 'Aggiungi a un menu', 'diritto-di-recesso' ); ?></label></th>
						<td>
							<?php $ddr_menus = get_registered_nav_menus(); $ddr_cur = get_option( 'ddr_menu_location', '' ); ?>
							<select name="ddr_menu_location" id="ddr_menu_location">
								<option value=""><?php esc_html_e( '— Nessuno —', 'diritto-di-recesso' ); ?></option>
								<?php foreach ( $ddr_menus as $loc => $name ) : ?>
									<option value="<?php echo esc_attr( $loc ); ?>" <?php selected( $ddr_cur, $loc ); ?>><?php echo esc_html( $name ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Aggiunge la voce di recesso al menu selezionato. In alternativa usa lo shortcode:', 'diritto-di-recesso' ); ?>
								<code>[diritto_recesso_link style="pill"]</code> (<code>pill</code> / <code>button</code> / <code>link</code>).
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Apri in finestra modale', 'diritto-di-recesso' ); ?></th>
						<td>
							<label><input type="checkbox" name="ddr_modal" value="yes" <?php checked( 'yes', get_option( 'ddr_modal', 'no' ) ); ?> /> <?php esc_html_e( 'Apri il flusso di recesso in una finestra modale (overlay) invece di cambiare pagina', 'diritto-di-recesso' ); ?></label>
							<p style="margin-top:8px;">
								<label for="ddr_overlay"><?php esc_html_e( 'Colore overlay:', 'diritto-di-recesso' ); ?> </label>
								<input type="color" name="ddr_overlay" id="ddr_overlay" value="<?php echo esc_attr( get_option( 'ddr_overlay', '#0f172a' ) ); ?>" />
							</p>
							<p class="description"><?php esc_html_e( 'Vale per il link footer, l’area account, lo shortcode e le voci di menu. L’overlay usa il colore scelto con trasparenza.', 'diritto-di-recesso' ); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Aspetto', 'diritto-di-recesso' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ddr_accent"><?php esc_html_e( 'Colore accento', 'diritto-di-recesso' ); ?></label></th>
						<td>
							<input type="color" name="ddr_accent" id="ddr_accent" value="<?php echo esc_attr( get_option( 'ddr_accent', '#ea580c' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Colore dell’icona e dei link “stile testo”. Intonalo al brand del sito.', 'diritto-di-recesso' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ddr_btn_bg"><?php esc_html_e( 'Colore pulsante', 'diritto-di-recesso' ); ?></label></th>
						<td>
							<input type="color" name="ddr_btn_bg" id="ddr_btn_bg" value="<?php echo esc_attr( get_option( 'ddr_btn_bg', '#1a1a1a' ) ); ?>" />
							<input type="color" name="ddr_btn_text" id="ddr_btn_text" value="<?php echo esc_attr( get_option( 'ddr_btn_text', '#ffffff' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Sfondo e testo del pulsante principale.', 'diritto-di-recesso' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ddr_radius"><?php esc_html_e( 'Raggio dei bordi', 'diritto-di-recesso' ); ?></label></th>
						<td>
							<input type="number" min="0" max="40" name="ddr_radius" id="ddr_radius" value="<?php echo esc_attr( get_option( 'ddr_radius', 10 ) ); ?>" /> px
							<p class="description"><?php esc_html_e( 'Arrotondamento globale di box, card, pulsanti e campi. 0 = squadrato.', 'diritto-di-recesso' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Ombra', 'diritto-di-recesso' ); ?></th>
						<td>
							<label><input type="checkbox" name="ddr_shadow" value="yes" <?php checked( 'yes', get_option( 'ddr_shadow', 'yes' ) ); ?> /> <?php esc_html_e( 'Mostra l’ombra (box-shadow) su box, badge e modale', 'diritto-di-recesso' ); ?></label>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Ricevuta & PDF', 'diritto-di-recesso' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Ricevuta PDF', 'diritto-di-recesso' ); ?></th>
						<td>
							<label><input type="checkbox" name="ddr_pdf_enable" value="yes" <?php checked( 'yes', get_option( 'ddr_pdf_enable', 'yes' ) ); ?> /> <?php esc_html_e( 'Abilita la generazione del PDF della ricevuta (pulsante “Scarica PDF”)', 'diritto-di-recesso' ); ?></label><br />
							<label><input type="checkbox" name="ddr_pdf_attach" value="yes" <?php checked( 'yes', get_option( 'ddr_pdf_attach', 'yes' ) ); ?> /> <?php esc_html_e( 'Allega il PDF all’email di avviso ricevimento', 'diritto-di-recesso' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Logo del PDF', 'diritto-di-recesso' ); ?></th>
						<td>
							<?php $logo_id = (int) get_option( 'ddr_pdf_logo', 0 ); $logo_src = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : ''; ?>
							<input type="hidden" name="ddr_pdf_logo" id="ddr_pdf_logo" value="<?php echo esc_attr( $logo_id ); ?>" />
							<div id="ddr_pdf_logo_preview" style="margin-bottom:8px;">
								<?php if ( $logo_src ) : ?><img src="<?php echo esc_url( $logo_src ); ?>" style="max-width:240px;height:auto;border:1px solid #ddd;padding:4px;background:#fff;" /><?php endif; ?>
							</div>
							<button type="button" class="button" id="ddr_pdf_logo_btn"><?php esc_html_e( 'Scegli logo', 'diritto-di-recesso' ); ?></button>
							<button type="button" class="button" id="ddr_pdf_logo_clear"><?php esc_html_e( 'Rimuovi', 'diritto-di-recesso' ); ?></button>
							<p class="description">
								<strong><?php esc_html_e( 'Consigliato: PNG o JPG, lato lungo ~600 px (orizzontale), fondo trasparente o bianco.', 'diritto-di-recesso' ); ?></strong><br />
								<?php esc_html_e( 'Se vuoto, viene usato (in ordine): logo email di WooCommerce, logo del sito, infine il nome del negozio in testo.', 'diritto-di-recesso' ); ?>
							</p>
							<?php
							$svg_ok  = class_exists( 'DDR_PDF' ) && DDR_PDF::can_rasterize_svg();
							$is_svg  = $logo_id && 'image/svg+xml' === get_post_mime_type( $logo_id );
							if ( $svg_ok ) {
								echo '<p class="description">' . esc_html__( 'Conversione SVG su questo server: disponibile (gli SVG vengono convertiti automaticamente).', 'diritto-di-recesso' ) . '</p>';
							} else {
								echo '<p class="description">' . esc_html__( 'Conversione SVG su questo server: NON disponibile. Per il PDF carica un PNG o JPG.', 'diritto-di-recesso' ) . '</p>';
							}
							if ( $is_svg && ! $svg_ok ) {
								echo '<p style="color:#b32d2e;"><strong>' . esc_html__( 'Il logo selezionato è un SVG e questo server non può convertirlo: nel PDF verrà usato il logo successivo o il testo. Carica un PNG/JPG.', 'diritto-di-recesso' ) . '</strong></p>';
							}
							?>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Notifiche & rimborsi', 'diritto-di-recesso' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ddr_admin_recipients"><?php esc_html_e( 'Destinatari notifiche', 'diritto-di-recesso' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" name="ddr_admin_recipients" id="ddr_admin_recipients" value="<?php echo esc_attr( get_option( 'ddr_admin_recipients', '' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Email separate da virgola. Vuoto = email amministratore del sito.', 'diritto-di-recesso' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Email al cliente sui cambi di stato', 'diritto-di-recesso' ); ?></th>
						<td>
							<label><input type="checkbox" name="ddr_customer_emails" value="yes" <?php checked( 'yes', get_option( 'ddr_customer_emails', 'yes' ) ); ?> /> <?php esc_html_e( 'Avvisa il cliente via email quando cambi lo stato della sua richiesta (in lavorazione, completata, annullata)', 'diritto-di-recesso' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Rimborso automatico', 'diritto-di-recesso' ); ?></th>
						<td>
							<label><input type="checkbox" name="ddr_auto_refund" value="yes" <?php checked( 'yes', get_option( 'ddr_auto_refund', 'no' ) ); ?> /> <?php esc_html_e( 'Quando segni una richiesta come “completata”, crea un rimborso WooCommerce per i prodotti recessi con ripristino dello stock', 'diritto-di-recesso' ); ?></label>
							<p class="description"><?php esc_html_e( 'Registra il rimborso e riporta lo stock, ma NON movimenta denaro tramite il gateway: l’eventuale restituzione al cliente va confermata manualmente. Disattivato di default.', 'diritto-di-recesso' ); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Avanzate', 'diritto-di-recesso' ); ?></h2>
				<table class="form-table" role="presentation">
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

	/* --------------------------- Aggiornamenti -------------------------- */

	/**
	 * Box stato aggiornamenti: versione installata vs ultima su GitHub + check manuale.
	 */
	public static function render_updates_box() {
		$repo = defined( 'DDR_GITHUB_REPO' ) ? DDR_GITHUB_REPO : '';
		if ( ! $repo ) {
			return;
		}
		$release = method_exists( 'DDR_Updater', 'fetch_release' ) ? DDR_Updater::fetch_release() : null;
		$latest  = $release ? $release->version : null;
		$update  = ( $latest && version_compare( $latest, DDR_VERSION, '>' ) );
		if ( isset( $_GET['ddr_checked'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Controllo aggiornamenti eseguito.', 'diritto-di-recesso' ) . '</p></div>';
		}
		?>
		<h2><?php esc_html_e( 'Aggiornamenti', 'diritto-di-recesso' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Versione installata', 'diritto-di-recesso' ); ?></th>
				<td><code><?php echo esc_html( DDR_VERSION ); ?></code></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Ultima su GitHub', 'diritto-di-recesso' ); ?></th>
				<td>
					<?php if ( $latest ) : ?>
						<code><?php echo esc_html( $latest ); ?></code>
						<?php if ( $update ) : ?>
							<strong style="color:#b32d2e;"> — <?php esc_html_e( 'aggiornamento disponibile', 'diritto-di-recesso' ); ?></strong>
							<a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'vai ad aggiornare', 'diritto-di-recesso' ); ?></a>
						<?php else : ?>
							<span style="color:#1a7f37;"> — <?php esc_html_e( 'sei aggiornato', 'diritto-di-recesso' ); ?></span>
						<?php endif; ?>
					<?php else : ?>
						<em><?php esc_html_e( 'non rilevabile (nessuna release o connessione a GitHub non riuscita).', 'diritto-di-recesso' ); ?></em>
					<?php endif; ?>
					<p class="description"><?php printf( esc_html__( 'Repository: %s', 'diritto-di-recesso' ), '<code>' . esc_html( $repo ) . '</code>' ); ?></p>
				</td>
			</tr>
		</table>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ddr_check_updates" />
			<?php wp_nonce_field( 'ddr_check_updates' ); ?>
			<?php submit_button( __( 'Controlla aggiornamenti adesso', 'diritto-di-recesso' ), 'secondary', 'submit', false ); ?>
		</form>
		<hr />
		<?php
	}

	/**
	 * Carica il media picker solo nella pagina impostazioni del plugin.
	 */
	public static function maybe_media( $hook ) {
		if ( ! isset( $_GET['page'] ) || 'ddr-settings' !== $_GET['page'] ) {
			return;
		}
		wp_enqueue_media();
		$js = <<<JS
jQuery(function($){
	var frame;
	$('#ddr_pdf_logo_btn').on('click', function(e){
		e.preventDefault();
		if(frame){ frame.open(); return; }
		frame = wp.media({ title: 'Logo PDF', button:{ text:'Usa questo logo' }, library:{ type:'image' }, multiple:false });
		frame.on('select', function(){
			var a = frame.state().get('selection').first().toJSON();
			$('#ddr_pdf_logo').val(a.id);
			var url = (a.sizes && a.sizes.medium) ? a.sizes.medium.url : a.url;
			$('#ddr_pdf_logo_preview').html('<img src="'+url+'" style="max-width:240px;height:auto;border:1px solid #ddd;padding:4px;background:#fff;" />');
		});
		frame.open();
	});
	$('#ddr_pdf_logo_clear').on('click', function(e){
		e.preventDefault();
		$('#ddr_pdf_logo').val('');
		$('#ddr_pdf_logo_preview').empty();
	});
});
JS;
		wp_add_inline_script( 'jquery-core', $js );
	}

	public static function handle_check_updates() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'diritto-di-recesso' ) );
		}
		check_admin_referer( 'ddr_check_updates' );
		if ( method_exists( 'DDR_Updater', 'force_recheck' ) ) {
			DDR_Updater::force_recheck();
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'ddr-settings', 'ddr_checked' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
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

		$before = DDR_DB::get( $id );
		$old    = $before ? $before['status'] : '';

		DDR_DB::update_status( $id, $status );

		$request = DDR_DB::get( $id );
		if ( $request ) {
			// Email al cliente (se abilitata).
			if ( 'yes' === get_option( 'ddr_customer_emails', 'yes' ) ) {
				DDR_Emails::send_status_update( $request );
			}
			// Rimborso automatico al passaggio a "completata" (se abilitato).
			if ( 'completata' === $status && 'completata' !== $old && 'yes' === get_option( 'ddr_auto_refund', 'no' ) ) {
				DDR_Refund::process( $request );
			}
		}

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

		// Neutralizza la CSV formula injection: anteponi un apice ai valori che
		// iniziano con =,+,-,@ (o tab/CR) così Excel/Sheets non li esegue.
		$safe = static function ( $v ) {
			$v = (string) $v;
			if ( '' !== $v && in_array( $v[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
				$v = "'" . $v;
			}
			return $v;
		};

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
				array_map(
					$safe,
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
