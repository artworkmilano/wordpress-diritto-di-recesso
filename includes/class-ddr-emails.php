<?php
/**
 * Email del plugin:
 *  - link di verifica per gli ospiti (gating della procedura);
 *  - avviso di ricevimento del recesso su supporto durevole (art. 54-bis c.6),
 *    comprensivo del contenuto della dichiarazione e di data e ora;
 *  - notifica all'amministratore.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DDR_Emails {

	protected static function headers() {
		return array( 'Content-Type: text/html; charset=UTF-8' );
	}

	protected static function wrap( $title, $body_html ) {
		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		ob_start();
		?>
		<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;color:#222;">
			<h2 style="font-size:18px;border-bottom:2px solid #eee;padding-bottom:8px;"><?php echo esc_html( $title ); ?></h2>
			<?php echo $body_html; // gia' sanificato dal chiamante. ?>
			<p style="font-size:12px;color:#888;margin-top:24px;border-top:1px solid #eee;padding-top:12px;">
				<?php echo esc_html( $site ); ?>
			</p>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Invia il link di verifica all'email di fatturazione (ospiti).
	 */
	public static function send_verification_link( $to, $name, $order, $link ) {
		$subject = sprintf(
			/* translators: %s nome sito */
			__( '[%s] Verifica la richiesta di recesso', 'diritto-di-recesso' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);

		$body  = '<p>' . sprintf(
			/* translators: %s nome cliente */
			esc_html__( 'Ciao %s,', 'diritto-di-recesso' ),
			esc_html( $name )
		) . '</p>';
		$body .= '<p>' . sprintf(
			/* translators: %s numero ordine */
			esc_html__( 'Hai avviato una procedura di recesso per l’ordine %s. Per proseguire in modo sicuro, conferma di essere l’intestatario dell’ordine cliccando il pulsante qui sotto.', 'diritto-di-recesso' ),
			esc_html( $order->get_order_number() )
		) . '</p>';
		$body .= '<p style="text-align:center;margin:28px 0;">';
		$body .= '<a href="' . esc_url( $link ) . '" style="background:#1a1a1a;color:#fff;text-decoration:none;padding:12px 22px;border-radius:6px;display:inline-block;">';
		$body .= esc_html( ddr_link_label() );
		$body .= '</a></p>';
		$body .= '<p style="font-size:12px;color:#888;">' . esc_html__( 'Il link è valido per 30 minuti. Se non hai richiesto tu il recesso, ignora questa email: nessuna azione verrà eseguita.', 'diritto-di-recesso' ) . '</p>';

		return wp_mail( $to, $subject, self::wrap( __( 'Conferma la richiesta di recesso', 'diritto-di-recesso' ), $body ), self::headers() );
	}

	/**
	 * Avviso di ricevimento su supporto durevole (art. 54-bis c.6).
	 * DEVE contenere il contenuto della dichiarazione + data e ora.
	 *
	 * @param array $request riga DB (hydrated).
	 */
	public static function send_receipt( $request ) {
		$decl = isset( $request['declaration_data'] ) ? $request['declaration_data'] : array();

		$subject = sprintf(
			/* translators: 1: nome sito 2: codice ricevuta */
			__( '[%1$s] Avviso di ricevimento del recesso – %2$s', 'diritto-di-recesso' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			$request['receipt_code']
		);

		$dt = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $request['created_at'] );

		$rows  = '';
		$rows .= self::row( __( 'Codice ricevuta', 'diritto-di-recesso' ), $request['receipt_code'] );
		$rows .= self::row( __( 'Data e ora di trasmissione', 'diritto-di-recesso' ), $dt );
		$rows .= self::row( __( 'Numero ordine', 'diritto-di-recesso' ), isset( $decl['order_number'] ) ? $decl['order_number'] : $request['order_id'] );
		$rows .= self::row( __( 'Intestatario', 'diritto-di-recesso' ), $request['customer_name'] );
		$rows .= self::row( __( 'Email', 'diritto-di-recesso' ), $request['customer_email'] );
		$rows .= self::row( __( 'Prodotti oggetto del recesso', 'diritto-di-recesso' ), self::items_text( $request ) );
		if ( ! empty( $request['reason'] ) ) {
			$rows .= self::row( __( 'Motivazione (facoltativa)', 'diritto-di-recesso' ), $request['reason'] );
		}

		$body  = '<p>' . esc_html__( 'Confermiamo di aver ricevuto la tua dichiarazione di recesso. Questo messaggio costituisce avviso di ricevimento su supporto durevole ai sensi dell’art. 54-bis del Codice del Consumo.', 'diritto-di-recesso' ) . '</p>';
		$body .= '<table style="width:100%;border-collapse:collapse;font-size:14px;margin:16px 0;">' . $rows . '</table>';
		$body .= '<p style="font-size:13px;color:#555;">' . esc_html__( 'La presente dichiarazione di recesso si intende validamente esercitata alla data e ora sopra indicate. Riceverai a breve istruzioni operative per l’eventuale restituzione dei beni e il rimborso.', 'diritto-di-recesso' ) . '</p>';

		$ok = wp_mail( $request['customer_email'], $subject, self::wrap( __( 'Avviso di ricevimento del recesso', 'diritto-di-recesso' ), $body ), self::headers() );

		/**
		 * Per un supporto durevole piu' robusto si puo' agganciare qui la
		 * generazione di un PDF allegato. Lasciato come hook.
		 */
		do_action( 'ddr_after_receipt_sent', $request, $ok );

		return $ok;
	}

	/**
	 * Notifica all'amministratore (e ai destinatari configurati).
	 */
	public static function notify_admin( $request ) {
		$recipients = get_option( 'ddr_admin_recipients', '' );
		$recipients = $recipients ? array_map( 'trim', explode( ',', $recipients ) ) : array( get_option( 'admin_email' ) );

		$decl = isset( $request['declaration_data'] ) ? $request['declaration_data'] : array();
		$dt   = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $request['created_at'] );

		$subject = sprintf(
			/* translators: %s numero ordine */
			__( 'Nuova richiesta di recesso – ordine %s', 'diritto-di-recesso' ),
			isset( $decl['order_number'] ) ? $decl['order_number'] : $request['order_id']
		);

		$rows  = '';
		$rows .= self::row( __( 'Codice ricevuta', 'diritto-di-recesso' ), $request['receipt_code'] );
		$rows .= self::row( __( 'Data e ora', 'diritto-di-recesso' ), $dt );
		$rows .= self::row( __( 'Ordine', 'diritto-di-recesso' ), isset( $decl['order_number'] ) ? $decl['order_number'] : $request['order_id'] );
		$rows .= self::row( __( 'Cliente', 'diritto-di-recesso' ), $request['customer_name'] . ' (' . $request['customer_email'] . ')' );
		$rows .= self::row( __( 'Prodotti', 'diritto-di-recesso' ), self::items_text( $request ) );
		if ( ! empty( $request['reason'] ) ) {
			$rows .= self::row( __( 'Motivazione', 'diritto-di-recesso' ), $request['reason'] );
		}
		$rows .= self::row( 'IP', $request['ip_address'] );

		$edit = admin_url( 'admin.php?page=ddr-richieste' );
		$body  = '<p>' . esc_html__( 'È stata registrata una nuova richiesta di recesso online.', 'diritto-di-recesso' ) . '</p>';
		$body .= '<table style="width:100%;border-collapse:collapse;font-size:14px;margin:16px 0;">' . $rows . '</table>';
		$body .= '<p><a href="' . esc_url( $edit ) . '">' . esc_html__( 'Vai all’elenco richieste', 'diritto-di-recesso' ) . '</a></p>';

		return wp_mail( $recipients, $subject, self::wrap( __( 'Nuova richiesta di recesso', 'diritto-di-recesso' ), $body ), self::headers() );
	}

	/**
	 * Email al cliente quando l'esercente cambia lo stato della richiesta.
	 */
	public static function send_status_update( $request ) {
		$status = $request['status'];

		$intro = array(
			'ricevuta'    => __( 'Abbiamo aggiornato lo stato della tua richiesta di recesso.', 'diritto-di-recesso' ),
			'lavorazione' => __( 'La tua richiesta di recesso è ora in lavorazione.', 'diritto-di-recesso' ),
			'completata'  => __( 'La tua richiesta di recesso è stata completata.', 'diritto-di-recesso' ),
			'annullata'   => __( 'La tua richiesta di recesso è stata annullata. Per chiarimenti rispondi a questa email.', 'diritto-di-recesso' ),
		);
		$message = isset( $intro[ $status ] ) ? $intro[ $status ] : $intro['ricevuta'];

		$subject = sprintf(
			/* translators: 1: nome sito 2: codice ricevuta */
			__( '[%1$s] Aggiornamento richiesta di recesso – %2$s', 'diritto-di-recesso' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			$request['receipt_code']
		);

		$rows  = '';
		$rows .= self::row( __( 'Codice ricevuta', 'diritto-di-recesso' ), $request['receipt_code'] );
		$rows .= self::row( __( 'Ordine', 'diritto-di-recesso' ), $request['order_id'] );
		$rows .= self::row( __( 'Prodotti', 'diritto-di-recesso' ), self::items_text( $request ) );
		$rows .= self::row( __( 'Stato', 'diritto-di-recesso' ), DDR_DB::status_label( $status ) );

		$body  = '<p>' . esc_html( $message ) . '</p>';
		$body .= '<table style="width:100%;border-collapse:collapse;font-size:14px;margin:16px 0;">' . $rows . '</table>';

		return wp_mail( $request['customer_email'], $subject, self::wrap( __( 'Aggiornamento richiesta di recesso', 'diritto-di-recesso' ), $body ), self::headers() );
	}

	/**
	 * Riepilogo testuale dei prodotti recessi dalla riga richiesta.
	 */
	protected static function items_text( $request ) {
		$items = isset( $request['items_data'] ) ? $request['items_data'] : array();
		if ( empty( $items ) || ! is_array( $items ) ) {
			return __( 'Intero ordine', 'diritto-di-recesso' );
		}
		$parts = array();
		foreach ( $items as $it ) {
			$name = isset( $it['name'] ) ? $it['name'] : '';
			$qty  = isset( $it['qty'] ) ? (int) $it['qty'] : 0;
			$parts[] = $qty > 1 ? sprintf( '%s ×%d', $name, $qty ) : $name;
		}
		return implode( ', ', $parts );
	}

	protected static function row( $label, $value ) {
		return '<tr>'
			. '<td style="padding:6px 10px;border:1px solid #eee;background:#fafafa;font-weight:bold;width:40%;">' . esc_html( $label ) . '</td>'
			. '<td style="padding:6px 10px;border:1px solid #eee;">' . esc_html( $value ) . '</td>'
			. '</tr>';
	}
}
