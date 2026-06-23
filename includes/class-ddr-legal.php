<?php
/**
 * Testi legali editabili (compliance art. 54-bis / Dir. 2011/83/UE).
 *
 * Fornisce testi predefiniti in italiano (clausola di recesso, modulo tipo,
 * esclusioni art. 59) editabili da un pannello con editor WYSIWYG, con i dati
 * dell'esercente recuperati da WooCommerce/WordPress (nome negozio, email,
 * giorni di recesso). I testi sono riutilizzabili via shortcode:
 *
 *   [ddr_recesso_condizioni]   clausola/condizioni di recesso
 *   [ddr_recesso_modulo]       modulo tipo di recesso
 *   [ddr_recesso_esclusioni]   eccezioni al diritto di recesso
 *
 * Vanno inseriti, per legge, nell'informativa precontrattuale e nelle Condizioni
 * Generali di Vendita (art. 49 c.1 lett. h) e nella pagina "Diritto di recesso".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DDR_Legal {

	/**
	 * Definizione dei campi: chiave => [ option, titolo pannello, shortcode ].
	 */
	public static function fields() {
		return array(
			'condizioni'  => array(
				'option'    => 'ddr_legal_condizioni',
				'title'     => __( 'Clausola di recesso (condizioni)', 'diritto-di-recesso' ),
				'shortcode' => 'ddr_recesso_condizioni',
				'where'     => __( 'Da inserire nelle Condizioni Generali di Vendita e nella pagina “Diritto di recesso” (informativa precontrattuale, art. 49 c.1 lett. h).', 'diritto-di-recesso' ),
			),
			'modulo'      => array(
				'option'    => 'ddr_legal_modulo',
				'title'     => __( 'Modulo tipo di recesso', 'diritto-di-recesso' ),
				'shortcode' => 'ddr_recesso_modulo',
				'where'     => __( 'Da mettere a disposizione nella pagina “Diritto di recesso” (Allegato I.B Dir. 2011/83/UE). L’uso del modulo non è obbligatorio per il consumatore.', 'diritto-di-recesso' ),
			),
			'esclusioni'  => array(
				'option'    => 'ddr_legal_esclusioni',
				'title'     => __( 'Esclusioni legali (art. 59)', 'diritto-di-recesso' ),
				'shortcode' => 'ddr_recesso_esclusioni',
				'where'     => __( 'Da indicare nell’informativa precontrattuale / Condizioni di Vendita per chiarire i casi in cui il recesso non si applica.', 'diritto-di-recesso' ),
			),
		);
	}

	public static function init() {
		foreach ( self::fields() as $key => $f ) {
			add_shortcode( $f['shortcode'], function () use ( $key ) {
				return '<div class="ddr-legal-text">' . wp_kses_post( wpautop( DDR_Legal::get_text( $key ) ) ) . '</div>';
			} );
		}
	}

	/* ------------------------------------------------------------------ */

	/**
	 * Dati esercente da WooCommerce/WordPress per popolare i testi.
	 */
	protected static function merchant() {
		$email = get_option( 'woocommerce_email_from_address' );
		if ( ! $email ) {
			$email = get_option( 'admin_email' );
		}
		return array(
			'shop'  => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'email' => $email,
			'days'  => (int) get_option( 'ddr_window_days', 14 ),
		);
	}

	/**
	 * Testo: valore salvato dall'esercente, altrimenti il predefinito.
	 */
	public static function get_text( $key ) {
		$fields = self::fields();
		if ( ! isset( $fields[ $key ] ) ) {
			return '';
		}
		$saved = get_option( $fields[ $key ]['option'], '' );
		return '' !== trim( (string) $saved ) ? $saved : self::default_text( $key );
	}

	/**
	 * Testi predefiniti (HTML), con dati esercente sostituiti.
	 */
	public static function default_text( $key ) {
		$m = self::merchant();

		switch ( $key ) {
			case 'condizioni':
				return
					'<h3>' . esc_html__( 'Diritto di recesso', 'diritto-di-recesso' ) . '</h3>' .
					'<p>' . sprintf(
						/* translators: %d giorni */
						esc_html__( 'In conformità alla Direttiva 2011/83/UE sui diritti dei consumatori, dispone di %d giorni per esercitare il diritto di recesso senza dover fornire alcuna motivazione e senza penalità. Il termine decorre dal giorno in cui lei, o un terzo da lei designato, acquisisce il possesso fisico dei beni (o dell’ultimo bene in caso di ordine di più beni consegnati separatamente).', 'diritto-di-recesso' ),
						$m['days']
					) . '</p>' .
					'<p>' . esc_html__( 'Per esercitare tale diritto può utilizzare il pulsante di recesso disponibile nel suo account cliente e nel dettaglio dell’ordine, oppure comunicarci la sua decisione mediante una dichiarazione esplicita (ad esempio il modulo tipo riportato di seguito). Le invieremo senza indugio una conferma del recesso su un supporto durevole.', 'diritto-di-recesso' ) . '</p>' .
					'<p>' . sprintf(
						/* translators: %d giorni */
						esc_html__( 'In caso di recesso le rimborseremo tutti i pagamenti ricevuti, comprese le spese di consegna standard, entro e non oltre %d giorni dal giorno in cui siamo informati della sua decisione, utilizzando lo stesso mezzo di pagamento dell’ordine salvo diverso accordo espresso. Possiamo trattenere il rimborso finché non abbiamo ricevuto i beni o finché lei non abbia dimostrato di averli rispediti.', 'diritto-di-recesso' ),
						14
					) . '</p>';

			case 'modulo':
				return
					'<h3>' . esc_html__( 'Modulo tipo di recesso', 'diritto-di-recesso' ) . '</h3>' .
					'<p><em>' . esc_html__( '(Compilare e restituire il presente modulo solo se si desidera recedere dal contratto.)', 'diritto-di-recesso' ) . '</em></p>' .
					'<p>' . sprintf(
						/* translators: 1: nome negozio 2: email */
						esc_html__( 'All’attenzione di %1$s, %2$s:', 'diritto-di-recesso' ),
						esc_html( $m['shop'] ),
						esc_html( $m['email'] )
					) . '</p>' .
					'<p>' . esc_html__( 'Con la presente io/noi (*) notifico/notifichiamo (*) il recesso dal mio/nostro (*) contratto di vendita dei seguenti beni (*)/per la prestazione dei seguenti servizi (*):', 'diritto-di-recesso' ) . '</p>' .
					'<p>' .
						esc_html__( 'Ordinato il (*)/ricevuto il (*):', 'diritto-di-recesso' ) . '<br />' .
						esc_html__( 'Nome del consumatore o dei consumatori:', 'diritto-di-recesso' ) . '<br />' .
						esc_html__( 'Indirizzo del consumatore o dei consumatori:', 'diritto-di-recesso' ) . '<br />' .
						esc_html__( 'Firma del consumatore o dei consumatori (solo se il presente modulo è notificato in versione cartacea):', 'diritto-di-recesso' ) . '<br />' .
						esc_html__( 'Data:', 'diritto-di-recesso' ) .
					'</p>' .
					'<p><small>' . esc_html__( '(*) Cancellare la dicitura inutile.', 'diritto-di-recesso' ) . '</small></p>';

			case 'esclusioni':
				return
					'<h3>' . esc_html__( 'Eccezioni al diritto di recesso', 'diritto-di-recesso' ) . '</h3>' .
					'<p>' . esc_html__( 'In conformità alla Direttiva 2011/83/UE, il diritto di recesso non si applica a:', 'diritto-di-recesso' ) . '</p>' .
					'<ul>' .
						'<li>' . esc_html__( 'la fornitura di beni confezionati su misura o chiaramente personalizzati;', 'diritto-di-recesso' ) . '</li>' .
						'<li>' . esc_html__( 'la fornitura di beni che rischiano di deteriorarsi o scadere rapidamente;', 'diritto-di-recesso' ) . '</li>' .
						'<li>' . esc_html__( 'la fornitura di beni sigillati che non si prestano a essere restituiti per motivi igienici o di protezione della salute e che sono stati aperti dopo la consegna;', 'diritto-di-recesso' ) . '</li>' .
						'<li>' . esc_html__( 'la fornitura di beni che, dopo la consegna, risultano per loro natura inscindibilmente mescolati con altri beni;', 'diritto-di-recesso' ) . '</li>' .
						'<li>' . esc_html__( 'la fornitura di registrazioni audio o video sigillate o di software informatici aperti dopo la consegna;', 'diritto-di-recesso' ) . '</li>' .
						'<li>' . esc_html__( 'la fornitura di contenuto digitale mediante un supporto non materiale se l’esecuzione è iniziata con il suo previo consenso espresso e con l’accettazione della perdita del diritto di recesso.', 'diritto-di-recesso' ) . '</li>' .
					'</ul>' .
					'<p>' . esc_html__( 'Il pulsante di recesso viene nascosto automaticamente per gli ordini che contengono soltanto prodotti esclusi.', 'diritto-di-recesso' ) . '</p>';
		}

		return '';
	}

	/* ----------------------------- Admin ------------------------------- */

	/**
	 * Salvataggio del pannello testi legali.
	 */
	protected static function maybe_save() {
		if ( ! isset( $_POST['ddr_legal_nonce'] ) ) {
			return false;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'ddr_legal_save', 'ddr_legal_nonce' ) ) {
			return false;
		}

		foreach ( self::fields() as $key => $f ) {
			$raw = isset( $_POST[ $f['option'] ] ) ? wp_unslash( $_POST[ $f['option'] ] ) : '';
			update_option( $f['option'], wp_kses_post( $raw ) );
		}
		return true;
	}

	/**
	 * Render del pannello admin "Testi legali".
	 */
	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$saved = self::maybe_save();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Diritto di Recesso – Testi legali', 'diritto-di-recesso' ); ?></h1>

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Testi salvati.', 'diritto-di-recesso' ); ?></p></div>
			<?php endif; ?>

			<div class="notice notice-info inline" style="padding:12px 14px;">
				<p style="margin-top:0;"><strong><?php esc_html_e( 'Dove inserire questi testi (obblighi di legge).', 'diritto-di-recesso' ); ?></strong></p>
				<p><?php esc_html_e( 'L’art. 49 c.1 lett. h del Codice del Consumo impone che condizioni, termini e procedure di recesso — e l’esistenza e collocazione della funzione digitale — siano nell’informativa precontrattuale e nelle Condizioni Generali di Vendita.', 'diritto-di-recesso' ); ?></p>
				<ul style="list-style:disc;margin-left:20px;">
					<li><?php esc_html_e( 'Crea/aggiorna una pagina “Diritto di recesso” (collegala nel footer) con la clausola, il modulo tipo e le esclusioni.', 'diritto-di-recesso' ); ?></li>
					<li><?php esc_html_e( 'Inserisci la clausola anche nelle Condizioni Generali di Vendita e richiamala al checkout.', 'diritto-di-recesso' ); ?></li>
					<li><?php esc_html_e( 'Puoi incollare il testo a mano oppure usare lo shortcode indicato sotto ogni riquadro: in questo modo, se aggiorni qui il testo, si aggiorna ovunque.', 'diritto-di-recesso' ); ?></li>
				</ul>
				<p style="margin-bottom:0;"><em><?php esc_html_e( 'Strumento tecnico, non sostituisce un parere legale. I testi predefiniti sono un punto di partenza da adattare alla tua attività.', 'diritto-di-recesso' ); ?></em></p>
			</div>

			<form method="post" action="">
				<?php wp_nonce_field( 'ddr_legal_save', 'ddr_legal_nonce' ); ?>
				<?php foreach ( self::fields() as $key => $f ) :
					$content = self::get_text( $key );
					$editor_id = 'ddr_editor_' . $key;
					?>
					<h2><?php echo esc_html( $f['title'] ); ?></h2>
					<p class="description"><?php echo esc_html( $f['where'] ); ?></p>
					<?php
					wp_editor(
						$content,
						$editor_id,
						array(
							'textarea_name' => $f['option'],
							'textarea_rows' => 12,
							'media_buttons' => false,
						)
					);
					?>
					<p class="description">
						<?php esc_html_e( 'Shortcode riutilizzabile:', 'diritto-di-recesso' ); ?>
						<code>[<?php echo esc_html( $f['shortcode'] ); ?>]</code>
						&nbsp;—&nbsp;
						<?php esc_html_e( 'Per ripristinare il testo predefinito, svuota il campo e salva.', 'diritto-di-recesso' ); ?>
					</p>
					<hr />
				<?php endforeach; ?>
				<?php submit_button( __( 'Salva testi', 'diritto-di-recesso' ) ); ?>
			</form>
		</div>
		<?php
	}
}
