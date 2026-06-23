<?php
/**
 * Gestione della tabella delle richieste di recesso (audit trail).
 *
 * Conserva ogni dichiarazione di recesso con il suo contenuto, i prodotti e le
 * quantita' oggetto del recesso, data e ora, IP e user agent, per poter essere
 * prodotta come prova in caso di contestazione (art. 54-bis, tracciabilita').
 *
 * Recesso PARZIALE: una richiesta non blocca piu' l'intero ordine. Vengono
 * registrati i singoli item (riga ordine + quantita'). Sullo stesso ordine sono
 * possibili piu' richieste nel tempo, finche' restano quantita' recedibili.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DDR_DB {

	/**
	 * Stati ammessi per una richiesta.
	 */
	public static function statuses() {
		return array(
			'ricevuta'   => __( 'Ricevuta', 'diritto-di-recesso' ),
			'lavorazione'=> __( 'In lavorazione', 'diritto-di-recesso' ),
			'completata' => __( 'Completata', 'diritto-di-recesso' ),
			'annullata'  => __( 'Annullata', 'diritto-di-recesso' ),
		);
	}

	public static function status_label( $status ) {
		$all = self::statuses();
		return isset( $all[ $status ] ) ? $all[ $status ] : $status;
	}

	/**
	 * Nome della tabella (senza prefisso wpdb).
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'ddr_richieste';
	}

	/**
	 * Crea/aggiorna la tabella (attivazione + upgrade).
	 */
	public static function create_table() {
		global $wpdb;
		$table           = self::table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			receipt_code VARCHAR(32) NOT NULL,
			order_id BIGINT(20) UNSIGNED NOT NULL,
			user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			customer_name VARCHAR(191) NOT NULL DEFAULT '',
			customer_email VARCHAR(191) NOT NULL DEFAULT '',
			declaration LONGTEXT NULL,
			items LONGTEXT NULL,
			reason TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'ricevuta',
			receipt_channel VARCHAR(50) NOT NULL DEFAULT 'email',
			ip_address VARCHAR(45) NOT NULL DEFAULT '',
			user_agent VARCHAR(255) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY receipt_code (receipt_code),
			KEY order_id (order_id),
			KEY user_id (user_id),
			KEY status (status)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'ddr_db_version', DDR_VERSION );
	}

	/**
	 * Esegue l'upgrade dello schema se la versione DB e' obsoleta.
	 * Richiamato in admin_init: aggiunge le colonne nuove (items, updated_at)
	 * sulle installazioni create con una versione precedente.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'ddr_db_version' ) === DDR_VERSION ) {
			return;
		}
		self::create_table();
	}

	/**
	 * Inserisce una richiesta. Ritorna la riga (hydrated) o false.
	 *
	 * @param array $data Dati gia' sanificati. Chiave `items` = array di
	 *                    { line_item_id, product_id, name, qty }.
	 */
	public static function insert( array $data ) {
		global $wpdb;

		$now = current_time( 'mysql' );

		$row = array(
			'receipt_code'    => self::generate_receipt_code(),
			'order_id'        => absint( $data['order_id'] ),
			'user_id'         => absint( $data['user_id'] ),
			'customer_name'   => sanitize_text_field( $data['customer_name'] ),
			'customer_email'  => sanitize_email( $data['customer_email'] ),
			'declaration'     => wp_json_encode( $data['declaration'] ),
			'items'           => wp_json_encode( isset( $data['items'] ) ? $data['items'] : array() ),
			'reason'          => isset( $data['reason'] ) ? sanitize_textarea_field( $data['reason'] ) : '',
			'status'          => 'ricevuta',
			'receipt_channel' => isset( $data['receipt_channel'] ) ? sanitize_text_field( $data['receipt_channel'] ) : 'email',
			'ip_address'      => self::client_ip(),
			'user_agent'      => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : '',
			'created_at'      => $now,
			'updated_at'      => $now,
		);

		$ok = $wpdb->insert(
			self::table(),
			$row,
			array( '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $ok ) {
			return false;
		}

		return self::get( $wpdb->insert_id );
	}

	/**
	 * Aggiorna lo stato di una richiesta.
	 */
	public static function update_status( $id, $status ) {
		global $wpdb;
		if ( ! array_key_exists( $status, self::statuses() ) ) {
			return false;
		}
		return (bool) $wpdb->update(
			self::table(),
			array( 'status' => $status, 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Recupera una richiesta per id.
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ),
			ARRAY_A
		);
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Recupera una richiesta dal codice ricevuta (per la pagina stampabile).
	 */
	public static function get_by_receipt( $code ) {
		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE receipt_code = %s", sanitize_text_field( $code ) ),
			ARRAY_A
		);
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Tutte le richieste (non annullate) di un ordine.
	 */
	public static function get_by_order( $order_id, $include_cancelled = true ) {
		global $wpdb;
		$table = self::table();
		$where = $include_cancelled ? '' : " AND status <> 'annullata'";
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE order_id = %d{$where} ORDER BY created_at DESC",
				absint( $order_id )
			),
			ARRAY_A
		);
		return array_map( array( __CLASS__, 'hydrate' ), $rows );
	}

	/**
	 * Mappa line_item_id => quantita' gia' recessa per un ordine
	 * (somma su tutte le richieste con stato diverso da "annullata").
	 *
	 * E' la base del recesso parziale + richieste multiple: la quantita'
	 * ancora recedibile di una riga = acquistata - gia' recessa.
	 *
	 * @return array<int,int>
	 */
	public static function withdrawn_quantities( $order_id ) {
		$totals = array();
		foreach ( self::get_by_order( $order_id, false ) as $req ) {
			if ( empty( $req['items_data'] ) || ! is_array( $req['items_data'] ) ) {
				continue;
			}
			foreach ( $req['items_data'] as $it ) {
				$li  = isset( $it['line_item_id'] ) ? (int) $it['line_item_id'] : 0;
				$qty = isset( $it['qty'] ) ? (int) $it['qty'] : 0;
				if ( $li > 0 ) {
					$totals[ $li ] = ( isset( $totals[ $li ] ) ? $totals[ $li ] : 0 ) + $qty;
				}
			}
		}
		return $totals;
	}

	/**
	 * Elenco paginato per la schermata admin.
	 */
	public static function all( $args = array() ) {
		global $wpdb;
		$table   = self::table();
		$per     = isset( $args['per_page'] ) ? absint( $args['per_page'] ) : 50;
		$offset  = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$per,
				$offset
			),
			ARRAY_A
		);
		return array_map( array( __CLASS__, 'hydrate' ), $results );
	}

	public static function count_all() {
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * Decodifica i campi JSON.
	 */
	protected static function hydrate( $row ) {
		if ( isset( $row['declaration'] ) ) {
			$row['declaration_data'] = json_decode( $row['declaration'], true );
		}
		if ( isset( $row['items'] ) ) {
			$row['items_data'] = json_decode( $row['items'], true );
			if ( ! is_array( $row['items_data'] ) ) {
				$row['items_data'] = array();
			}
		}
		return $row;
	}

	/**
	 * Codice ricevuta univoco e leggibile.
	 */
	protected static function generate_receipt_code() {
		return 'REC-' . strtoupper( wp_generate_password( 10, false, false ) );
	}

	/**
	 * IP client. Di default usa solo REMOTE_ADDR (privacy).
	 * Se l'opzione `ddr_trust_proxy` e' attiva, considera gli header del proxy
	 * (Cloudflare / X-Forwarded-For) prendendo il primo IP valido.
	 */
	protected static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		if ( 'yes' === get_option( 'ddr_trust_proxy', 'no' ) ) {
			$candidates = array();
			if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
				$candidates[] = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
			}
			if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				$parts = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
				$candidates[] = trim( $parts[0] );
			}
			foreach ( $candidates as $cand ) {
				if ( filter_var( $cand, FILTER_VALIDATE_IP ) ) {
					$ip = $cand;
					break;
				}
			}
		}

		return substr( $ip, 0, 45 );
	}
}
